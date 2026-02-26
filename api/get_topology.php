<?php
header('Content-Type: application/json');

$projectsFile = '../data/projects.json';
$serversFile = '../data/servers.json';
$logsFile = '../data/logs.json';

$projects = file_exists($projectsFile) ? json_decode(file_get_contents($projectsFile), true) : [];
$servers = file_exists($serversFile) ? json_decode(file_get_contents($serversFile), true) : [];
$logs = file_exists($logsFile) ? json_decode(file_get_contents($logsFile), true) : [];

if (!is_array($projects)) $projects = [];
if (!is_array($servers)) $servers = [];
if (!is_array($logs)) $logs = [];

// build a map for the latest log status per server
$serverStatus = [];
foreach ($logs as $log) {
    if (isset($log['server_id']) && isset($log['status']) && isset($log['created_at'])) {
        $sid = $log['server_id'];
        $time = strtotime($log['created_at']);
        if (!isset($serverStatus[$sid]) || $time > $serverStatus[$sid]['time']) {
            $serverStatus[$sid] = [
                'status' => $log['status'],
                'time' => $time
            ];
        }
    }
}

// build nodes
$nodes = [];
$edges = [];

// Add Server Nodes
foreach ($servers as $srv) {
    $sid = $srv['id'];
    $status = isset($serverStatus[$sid]) ? $serverStatus[$sid]['status'] : 'Unknown';
    $nodes[] = [
        'id' => 's_' . $sid,
        'label' => $srv['name'],
        'group' => 'server',
        'status' => $status
    ];
}

// Add Project Nodes and Edges
foreach ($projects as $proj) {
    $pid = $proj['id'];
    $nodes[] = [
        'id' => 'p_' . $pid,
        'label' => $proj['name'],
        'group' => 'project'
    ];

    // Handle single server project
    if (isset($proj['server_id']) && !empty($proj['server_id'])) {
        $edges[] = [
            'from' => 'p_' . $pid,
            'to' => 's_' . $proj['server_id'],
            'has_webhook' => !empty($proj['webhook_url'])
        ];
    }

    // Handle multi-server if exists
    if (isset($proj['servers']) && is_array($proj['servers'])) {
        foreach ($proj['servers'] as $s) {
            $edges[] = [
                'from' => 'p_' . $pid,
                'to' => 's_' . $s,
                'has_webhook' => !empty($proj['webhook_url'])
            ];
        }
    }
}

echo json_encode(['nodes' => $nodes, 'edges' => $edges]);
