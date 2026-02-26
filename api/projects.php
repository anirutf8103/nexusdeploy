<?php
require_once 'DataStore.php';

$store = new DataStore('projects');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $project = $store->getById($_GET['id']);
        if ($project) jsonResponse($project);
        else jsonResponse(['error' => 'Project not found'], 404);
    }
    jsonResponse($store->read());
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name']) || !isset($input['local_path'])) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    $project = $store->add([
        'name' => $input['name'],
        'local_path' => rtrim($input['local_path'], '/\\'),
        'ignore_list' => isset($input['ignore_list']) ? $input['ignore_list'] : ['.git', 'node_modules', 'vendor', '.env'],
        'server_id' => isset($input['server_id']) ? $input['server_id'] : null,
        'remote_path' => isset($input['remote_path']) ? $input['remote_path'] : '/',
        'webhook_url' => isset($input['webhook_url']) ? $input['webhook_url'] : ''
    ]);
    jsonResponse($project, 201);
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) jsonResponse(['error' => 'Missing ID'], 400);

    $updateData = [];
    if (isset($input['name'])) $updateData['name'] = $input['name'];
    if (isset($input['local_path'])) $updateData['local_path'] = rtrim($input['local_path'], '/\\');
    if (isset($input['ignore_list'])) $updateData['ignore_list'] = $input['ignore_list'];
    if (isset($input['server_id'])) $updateData['server_id'] = $input['server_id'];
    if (isset($input['remote_path'])) $updateData['remote_path'] = $input['remote_path'];
    if (isset($input['webhook_url'])) $updateData['webhook_url'] = $input['webhook_url'];

    $updated = $store->update($input['id'], $updateData);
    if ($updated) jsonResponse($updated);
    else jsonResponse(['error' => 'Project not found'], 404);
} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) jsonResponse(['error' => 'Missing ID'], 400);
    if ($store->delete($input['id'])) jsonResponse(['success' => true]);
    else jsonResponse(['error' => 'Project not found'], 404);
}
