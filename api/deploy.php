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
    
    // Use cURL instead of native ftp_ functions because it properly handles FTPS with self-signed certs
    $testUrl = 'ftp://' . $server['host'] . ':' . $port . rtrim($remotePath, '/') . '/';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_USERPWD, $server['username'] . ':' . ($server['password'] ?? ''));
    
    // Force explicit FTPS but ignore self-signed certificate errors
    curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Just fetch directory listing to verify auth and path
    curl_setopt($ch, CURLOPT_DIRLISTONLY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 15 seconds mostly because some servers have 10-second DNS lookup delays on login
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_errno !== 0) {
        jsonResponse(['error' => 'FTP Error: ' . $curl_error], 500);
    }

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

    $credentials = $server['username'] . ':' . ($server['password'] ?? '');
    
    $startTime = microtime(true);
    $successList = [];
    $failedList = [];
    $fullState = $stateStore->read();
    if (!isset($fullState[$projectId])) $fullState[$projectId] = [];

    foreach ($files as $file) {
        $filePath = ltrim($file['path'], '/\\');
        $localFile = $localBase . DIRECTORY_SEPARATOR . $filePath;

        if (!file_exists($localFile)) continue;

        $remoteFileUrl = rtrim($ftpUrl, '/') . '/' . str_replace('\\', '/', $filePath);
        $fp = fopen($localFile, 'r');
        if (!$fp) {
            $failedList[] = ['path' => $filePath, 'error' => 'Could not open local file for reading'];
            continue;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remoteFileUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $credentials);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
        curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
        
        // Secure FTPS settings
        curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Ensure proper transfer mode
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
        curl_close($ch);

        if ($result !== false) {
            $successList[] = $filePath;
            $fullState[$projectId][$filePath] = [
                'hash' => $file['hash'],
                'uploaded_at' => date('c')
            ];
        } else {
            $failedList[] = [
                'path' => $filePath,
                'error' => $error ?: 'Unknown cURL error'
            ];
        }
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
} elseif ($action === 'mark_all_deployed' && $method === 'POST') {
    // Mark all local files as "deployed" in state without uploading them
    $input = json_decode(file_get_contents('php://input'), true);
    $projectId = isset($input['project_id']) ? $input['project_id'] : '';

    if (empty($projectId)) {
        jsonResponse(['error' => 'project_id is required'], 400);
    }

    $project = $projectStore->getById($projectId);
    if (!$project) {
        jsonResponse(['error' => 'Project not found'], 404);
    }

    $localPath = rtrim($project['local_path'], '/\\');
    if (!is_dir($localPath)) {
        jsonResponse(['error' => 'Local path does not exist: ' . $localPath], 400);
    }

    $ignoreList = isset($project['ignore_list']) ? $project['ignore_list'] : [];

    $fullState = $stateStore->read();
    if (!isset($fullState[$projectId])) $fullState[$projectId] = [];

    $markedCount = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) continue;

        $filePath = $item->getPathname();
        $relativePath = ltrim(substr($filePath, strlen($localPath)), '/\\');

        // Apply ignore list
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

        $hash = md5_file($filePath);
        $fullState[$projectId][$relativePath] = [
            'hash' => $hash,
            'uploaded_at' => date('c')
        ];
        $markedCount++;
    }

    $stateStore->write($fullState);

    jsonResponse([
        'success' => true,
        'marked_count' => $markedCount,
        'message' => "Marked {$markedCount} files as deployed in local state."
    ]);
} else {
    jsonResponse(['error' => 'Invalid action'], 400);
}
