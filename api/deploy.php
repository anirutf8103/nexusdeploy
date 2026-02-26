<?php
require_once 'DataStore.php';

// Turn off error reporting to JSON gracefully
error_reporting(0);
ini_set('display_errors', 0);

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$projectStore = new DataStore('projects');
$serverStore = new DataStore('servers');
$stateStore = new DataStore('state');
$logsStore = new DataStore('logs');

if ($action === 'dry-run' && $method === 'GET') {
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';
    $project = $projectStore->getById($projectId);
    if (!$project) {
        jsonResponse(['error' => 'Project not found'], 404);
    }

    $localPath = rtrim($project['local_path'], '/\\');
    if (!is_dir($localPath)) {
        jsonResponse(['error' => 'Local path does not exist: ' . $localPath], 400);
    }

    // Verify Server and Remote Path exists before allowing deploy
    $server = $serverStore->getById($project['server_id']);
    if (!$server) {
        jsonResponse(['error' => 'Server not found or mapped'], 404);
    }

    $remotePath = rtrim($project['remote_path'], '/');
    if (empty($remotePath)) $remotePath = '/';

    $port = !empty($server['port']) ? $server['port'] : 21;
    $conn = @ftp_connect($server['host'], $port, 5);
    if (!$conn) {
        jsonResponse(['error' => 'FTP Connection failed to host ' . $server['host']], 500);
    }

    $login = @ftp_login($conn, $server['username'], $server['password'] ?? '');
    if (!$login) {
        @ftp_close($conn);
        jsonResponse(['error' => 'FTP Authentication failed for user ' . $server['username']], 401);
    }

    if (!@ftp_chdir($conn, $remotePath)) {
        @ftp_close($conn);
        jsonResponse(['error' => 'Remote path does not exist on server: ' . $remotePath], 404);
    }
    @ftp_close($conn);

    $ignoreList = isset($project['ignore_list']) ? $project['ignore_list'] : [];

    $state = $stateStore->read();
    $projectState = isset($state[$projectId]) ? $state[$projectId] : [];

    $filesToUpload = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) continue;

        $filePath = $item->getPathname();
        $relativePath = ltrim(substr($filePath, strlen($localPath)), '/\\');

        // Check ignore list
        $ignore = false;
        foreach ($ignoreList as $ignoredItem) {
            $ignoredItem = trim($ignoredItem);
            if (empty($ignoredItem)) continue;
            // simple strpos or fnmatch
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

        $mtime = filemtime($filePath);
        $size = filesize($filePath);
        $hash = md5_file($filePath); // MD5 is good enough for comparison

        if (!isset($projectState[$relativePath]) || $projectState[$relativePath]['hash'] !== $hash) {
            $filesToUpload[] = [
                'path' => $relativePath,
                'size' => $size,
                'mtime' => $mtime,
                'hash' => $hash,
                'full_path' => $filePath
            ];
        }
    }

    jsonResponse(['files' => $filesToUpload, 'total' => count($filesToUpload)]);
} elseif ($action === 'upload_batch' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['project_id']) || empty($input['files'])) {
        jsonResponse(['error' => 'Invalid batch data'], 400);
    }

    $projectId = $input['project_id'];
    $files = $input['files'];
    $triggerWebhook = isset($input['trigger_webhook']) ? filter_var($input['trigger_webhook'], FILTER_VALIDATE_BOOLEAN) : false;
    $isLastBatch = isset($input['is_last_batch']) ? filter_var($input['is_last_batch'], FILTER_VALIDATE_BOOLEAN) : false;

    $project = $projectStore->getById($projectId);
    if (!$project) jsonResponse(['error' => 'Project not found'], 404);

    $server = $serverStore->getById($project['server_id']);
    if (!$server) jsonResponse(['error' => 'Server not found or mapped'], 404);

    $localBase = rtrim($project['local_path'], '/\\');
    $remoteBase = rtrim($project['remote_path'], '/');
    if (empty($remoteBase)) $remoteBase = '/';

    // cURL doesn't cleanly support paths starting with double slashes if host doesn't require it, 
    // better to format: ftp://user:pass@host:port/remoteDir/file
    $port = !empty($server['port']) ? $server['port'] : 21;
    $ftpUrl = 'ftp://' . $server['host'] . ':' . $port . $remoteBase;

    // We will use CURLOPT_USERPWD instead of placing it in URL to avoid URL encoding issues with special chars
    $credentials = $server['username'] . ':' . ($server['password'] ?? '');

    $mh = curl_multi_init();
    $curlHandles = [];
    $fileHandles = [];

    $startTime = microtime(true);

    foreach ($files as $file) {
        $filePath = ltrim($file['path'], '/\\');
        $localFile = $localBase . DIRECTORY_SEPARATOR . $filePath;

        if (!file_exists($localFile)) continue;

        $remoteFileUrl = rtrim($ftpUrl, '/') . '/' . str_replace('\\', '/', $filePath);

        $ch = curl_init();
        $fp = fopen($localFile, 'r');
        $fileHandles[$filePath] = $fp;

        curl_setopt($ch, CURLOPT_URL, $remoteFileUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $credentials);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
        curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);

        // Ensure proper transfer mode
        $ext = pathinfo($localFile, PATHINFO_EXTENSION);
        $asciiExts = ['txt', 'html', 'css', 'js', 'json', 'php', 'md', 'xml'];
        if (in_array(strtolower($ext), $asciiExts)) {
            curl_setopt($ch, CURLOPT_TRANSFERTEXT, true);
        }

        curl_multi_add_handle($mh, $ch);
        $curlHandles[$filePath] = $ch;
    }

    // Execute all queries simultaneously
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    $multiResults = [];
    while ($info = curl_multi_info_read($mh)) {
        if ($info['msg'] == CURLMSG_DONE) {
            $multiResults[(int)$info['handle']] = $info['result'];
        }
    }

    $successList = [];
    $failedList = [];

    $fullState = $stateStore->read();
    if (!isset($fullState[$projectId])) $fullState[$projectId] = [];

    foreach ($curlHandles as $filePath => $ch) {
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $resultCode = isset($multiResults[(int)$ch]) ? $multiResults[(int)$ch] : -1;

        // FTP success is usually indicated by an empty error string and/or a valid code (226=Closing data connection. Requested file action successful)
        // cURL translates success code into 0 for curl_errno, or specific HTTP codes. For FTP over cURL, 0 error means success.

        // Let's use curl_errno() logic securely
        if ($resultCode === CURLE_OK) {
            $successList[] = $filePath;

            // Find hash mapping
            $fileHash = '';
            foreach ($files as $f) {
                if (ltrim($f['path'], '/\\') === $filePath) {
                    $fileHash = $f['hash'];
                    break;
                }
            }
            $fullState[$projectId][$filePath] = [
                'hash' => $fileHash,
                'uploaded_at' => date('c')
            ];
        } else {
            $failedList[] = [
                'path' => $filePath,
                'error' => $error ?: 'Unknown cURL error'
            ];
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);

    foreach ($fileHandles as $fp) {
        if (is_resource($fp)) fclose($fp);
    }

    $stateStore->write($fullState);

    $timeTaken = round((microtime(true) - $startTime) * 1000); // milliseconds

    $webhookResult = null;
    if ($triggerWebhook && $isLastBatch && !empty($project['webhook_url'])) {
        $whCh = curl_init($project['webhook_url']);
        curl_setopt($whCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($whCh, CURLOPT_TIMEOUT, 10);
        $startWhTime = microtime(true);
        $whResponse = curl_exec($whCh);
        $whCode = curl_getinfo($whCh, CURLINFO_HTTP_CODE);
        $whError = curl_error($whCh);
        curl_close($whCh);

        $webhookResult = [
            'success' => ($whCode >= 200 && $whCode < 300),
            'status_code' => $whCode,
            'response_body' => $whResponse ? substr($whResponse, 0, 500) : '',
            'error' => $whError,
            'execution_time_ms' => round((microtime(true) - $startWhTime) * 1000)
        ];
    }

    $responsePayload = [
        'success' => count($successList),
        'failed' => count($failedList),
        'success_files' => $successList,
        'failed_files' => $failedList,
        'time_taken_ms' => $timeTaken
    ];
    if ($webhookResult !== null) {
        $responsePayload['webhook_result'] = $webhookResult;
    }

    jsonResponse($responsePayload);
} else {
    jsonResponse(['error' => 'Invalid action'], 400);
}
