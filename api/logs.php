<?php
require_once 'DataStore.php';

$store = new DataStore('logs');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $logs = $store->read();
    usort($logs, function ($a, $b) {
        $timeA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
        $timeB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
        return $timeB - $timeA; // Default descending sort
    });
    jsonResponse($logs);
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['project_id']) || !isset($input['server_id']) || !isset($input['status'])) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    $logData = [
        'project_id' => $input['project_id'],
        'server_id' => $input['server_id'],
        'files_uploaded' => isset($input['files_uploaded']) ? $input['files_uploaded'] : [],
        'time_taken' => isset($input['time_taken']) ? $input['time_taken'] : 0,
        'status' => $input['status'], // 'Success' or 'Failed'
    ];
    if (isset($input['webhook_result'])) {
        $logData['webhook_result'] = $input['webhook_result'];
    }
    $log = $store->add($logData);
    jsonResponse($log, 201);
}
