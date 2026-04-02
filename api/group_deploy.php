<?php
require_once 'DataStore.php';

error_reporting(0);
ini_set('display_errors', 0);

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$projectStore = new DataStore('projects');
$serverStore = new DataStore('servers');
$stateStore = new DataStore('state');
$logsStore = new DataStore('logs');

if ($action === 'dry-run' && $method === 'GET') {
    $localPathStr = isset($_GET['local_path']) ? base64_decode($_GET['local_path']) : '';

    if (empty($localPathStr)) {
        jsonResponse(['error' => 'Local path is required'], 400);
    }

    $allProjects = $projectStore->read();
    $allServers = $serverStore->read();

    $groupProjects = [];
    foreach ($allProjects as $p) {
        if (trim($p['local_path'], '/\\') === trim($localPathStr, '/\\')) {
            $groupProjects[] = $p;
        }
    }

    if (empty($groupProjects)) {
        jsonResponse(['error' => 'No projects mapped to this local path.'], 404);
    }

    // Verify local path exists
    $localPath = rtrim($groupProjects[0]['local_path'], '/\\');
    if (!is_dir($localPath)) {
        jsonResponse(['error' => 'Local path does not exist: ' . $localPath], 400);
    }

    $masterQueue = [];
    $state = $stateStore->read();

    // Scan directory ONCE
    $filesMap = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) continue;
        $filePath = $item->getPathname();
        $relativePath = ltrim(substr($filePath, strlen($localPath)), '/\\');

        $filesMap[$relativePath] = [
            'mtime' => filemtime($filePath),
            'size' => filesize($filePath),
            'hash' => md5_file($filePath),
            'full_path' => $filePath
        ];
    }

    // Now loop over each project in the group to figure out its unique master_queue entries
    $verifiedServers = [];
    foreach ($groupProjects as $project) {
        $pId = $project['id'];
        $serverId = $project['server_id'];
        $server = null;

        foreach ($allServers as $s) {
            if ($s['id'] === $serverId) {
                $server = $s;
                break;
            }
        }

        if (!$server) continue; // Skip unmapped servers

        $remotePath = rtrim($project['remote_path'], '/');
        if (empty($remotePath)) $remotePath = '/';

        // Verify connection and remote path exist before queuing
        $verifyKey = $serverId . '_' . $remotePath;
        if (!isset($verifiedServers[$verifyKey])) {
            $port = !empty($server['port']) ? $server['port'] : 21;
            
            $testUrl = 'ftp://' . $server['host'] . ':' . $port . rtrim($remotePath, '/') . '/';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_USERPWD, $server['username'] . ':' . ($server['password'] ?? ''));
            curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_DIRLISTONLY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_errno !== 0) {
                jsonResponse(['error' => 'FTP Error on server ' . $server['host'] . ' (' . $project['name'] . '): ' . $curl_error], 500);
            }
            
            $verifiedServers[$verifyKey] = true;
        }

        $ignoreList = isset($project['ignore_list']) ? $project['ignore_list'] : [];
        $pState = isset($state[$pId]) ? $state[$pId] : [];

        foreach ($filesMap as $relativePath => $fData) {
            // Check ignore list
            $ignore = false;
            foreach ($ignoreList as $ignoredItem) {
                $ignoredItem = trim($ignoredItem);
                if (empty($ignoredItem)) continue;
                if (
                    $relativePath === $ignoredItem ||
                    strpos($relativePath, $ignoredItem . '/') === 0 ||
                    basename($relativePath) === $ignoredItem ||
                    strpos($relativePath, '/' . $ignoredItem . '/') !== false
                ) {
                    $ignore = true;
                    break;
                }
            }

            if ($ignore) continue;

            if (!isset($pState[$relativePath]) || $pState[$relativePath]['hash'] !== $fData['hash']) {
                $masterQueue[] = [
                    'project_id' => $pId,
                    'project_name' => $project['name'],
                    'server_id' => $serverId,
                    'remote_path' => rtrim($project['remote_path'], '/'),
                    'file' => [
                        'path' => $relativePath,
                        'size' => $fData['size'],
                        'hash' => $fData['hash']
                    ]
                ];
            }
        }
    }

    jsonResponse(['master_queue' => $masterQueue, 'total' => count($masterQueue)]);
} elseif ($action === 'upload_batch' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $localPathStr = isset($input['local_path']) ? $input['local_path'] : '';
    $queue = isset($input['queue']) ? $input['queue'] : [];
    $isLastBatch = isset($input['is_last_batch']) ? filter_var($input['is_last_batch'], FILTER_VALIDATE_BOOLEAN) : false;

    if (empty($queue)) jsonResponse(['error' => 'Queue is empty'], 400);

    $allServers = $serverStore->read();

    // We need server credentials mapped out
    $serverMeta = [];
    foreach ($allServers as $s) {
        $serverMeta[$s['id']] = $s;
    }

    $localBase = rtrim($localPathStr, '/\\');

    $serverHandles = [];

    $startTime = microtime(true);
    $results = [];
    $serverStats = [];
    $fullState = $stateStore->read();

    foreach ($queue as $index => $qItem) {
        $sId = $qItem['server_id'];
        $server = isset($serverMeta[$sId]) ? $serverMeta[$sId] : null;
        if (!$server) continue;
        
        $pId = $qItem['project_id'];
        $filePath = ltrim($qItem['file']['path'], '/\\');
        $localFile = $localBase . DIRECTORY_SEPARATOR . $filePath;

        if (!isset($fullState[$pId])) $fullState[$pId] = [];
        if (!isset($serverStats[$sId])) $serverStats[$sId] = ['success' => 0, 'failed' => 0];

        if (!file_exists($localFile)) {
            $results[] = [
                'status' => 'failed',
                'project_name' => $qItem['project_name'],
                'path' => $filePath,
                'error' => 'Local file not found'
            ];
            $serverStats[$sId]['failed']++;
            continue;
        }

        $remoteBase = rtrim($qItem['remote_path'], '/');
        if (empty($remoteBase)) $remoteBase = '/';

        $port = !empty($server['port']) ? $server['port'] : 21;
        $ftpUrl = 'ftp://' . $server['host'] . ':' . $port . $remoteBase;
        $remoteFileUrl = rtrim($ftpUrl, '/') . '/' . str_replace('\\', '/', $filePath);

        // Fetch or create persistent connection handle for this specific server
        if (!isset($serverHandles[$sId])) {
            $ch = curl_init();
            $credentials = $server['username'] . ':' . ($server['password'] ?? '');
            curl_setopt($ch, CURLOPT_USERPWD, $credentials);
            curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
            curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_UPLOAD, 1);
            $serverHandles[$sId] = $ch;
        }
        $ch = $serverHandles[$sId];

        $fp = fopen($localFile, 'r');
        curl_setopt($ch, CURLOPT_URL, $remoteFileUrl);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));

        $ext = pathinfo($localFile, PATHINFO_EXTENSION);
        $asciiExts = ['txt', 'html', 'css', 'js', 'json', 'php', 'md', 'xml'];
        if (in_array(strtolower($ext), $asciiExts)) {
            curl_setopt($ch, CURLOPT_TRANSFERTEXT, true);
        } else {
            curl_setopt($ch, CURLOPT_TRANSFERTEXT, false);
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        fclose($fp);

        if ($result !== false) {
            $results[] = [
                'status' => 'success',
                'project_name' => $qItem['project_name'],
                'path' => $filePath
            ];
            $serverStats[$sId]['success']++;

            $fullState[$pId][$filePath] = [
                'hash' => $qItem['file']['hash'],
                'uploaded_at' => date('c')
            ];
        } else {
            $results[] = [
                'status' => 'failed',
                'project_name' => $qItem['project_name'],
                'path' => $filePath,
                'error' => $error ?: 'Unknown cURL error'
            ];
            $serverStats[$sId]['failed']++;
        }
    }

    // Close all persistent server handles
    foreach ($serverHandles as $ch) {
        curl_close($ch);
    }

    $stateStore->write($fullState);

    $timeTaken = round((microtime(true) - $startTime) * 1000); // milliseconds

    // Aggregate globals
    $globalSuccess = 0;
    $globalFailed = 0;
    foreach ($serverStats as $stat) {
        $globalSuccess += $stat['success'];
        $globalFailed += $stat['failed'];
    }

    $responsePayload = [
        'success' => $globalSuccess,
        'failed' => $globalFailed,
        'results' => $results,
        'server_stats' => $serverStats,
        'time_taken_ms' => $timeTaken
    ];

    jsonResponse($responsePayload);
} elseif ($action === 'run_webhooks' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $webhooks = isset($input['webhooks']) ? $input['webhooks'] : [];

    $webhookResults = [];
    $whMulti = curl_multi_init();
    $whHandles = [];

    foreach ($webhooks as $wh) {
        $pId = $wh['project_id'];
        if ($wh['ftp_success'] === true && !empty($wh['webhook_url'])) {
            $ch = curl_init($wh['webhook_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_multi_add_handle($whMulti, $ch);
            $whHandles[$pId] = [
                'ch' => $ch,
                'wh' => $wh,
                'start_time' => microtime(true)
            ];
        } else {
            $webhookResults[$pId] = [
                'server_name' => $wh['server_name'],
                'ftp_success' => $wh['ftp_success'],
                'webhook_triggered' => false,
                'webhook_status' => null,
                'webhook_response' => 'FTP Upload Failed or Webhook URL missing, skipped.',
                'success' => false
            ];
        }
    }

    if (!empty($whHandles)) {
        $whRunning = null;
        do {
            curl_multi_exec($whMulti, $whRunning);
            curl_multi_select($whMulti);
        } while ($whRunning > 0);

        foreach ($whHandles as $pId => $whData) {
            $ch = $whData['ch'];
            $whResponse = curl_multi_getcontent($ch);
            $whCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $whError = curl_error($ch);

            $webhookResults[$pId] = [
                'server_name' => $whData['wh']['server_name'],
                'ftp_success' => true,
                'webhook_triggered' => true,
                'webhook_status' => $whCode,
                'webhook_response' => $whResponse ? substr($whResponse, 0, 500) : $whError,
                'success' => ($whCode >= 200 && $whCode < 300)
            ];

            curl_multi_remove_handle($whMulti, $ch);
            curl_close($ch);
        }
    }
    curl_multi_close($whMulti);

    jsonResponse(['results' => $webhookResults]);
} else {
    jsonResponse(['error' => 'Invalid action'], 400);
}
