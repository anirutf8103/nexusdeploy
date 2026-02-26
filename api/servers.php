<?php
require_once 'DataStore.php';

$store = new DataStore('servers');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $server = $store->getById($_GET['id']);
        if ($server) jsonResponse($server);
        else jsonResponse(['error' => 'Server not found'], 404);
    }
    jsonResponse($store->read());
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name']) || !isset($input['host']) || !isset($input['username'])) {
        jsonResponse(['error' => 'Missing required fields'], 400);
    }
    $server = $store->add([
        'name' => $input['name'],
        'host' => $input['host'],
        'port' => isset($input['port']) ? (int)$input['port'] : 21,
        'username' => $input['username'],
        'password' => isset($input['password']) ? $input['password'] : ''
    ]);
    jsonResponse($server, 201);
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) jsonResponse(['error' => 'Missing ID'], 400);

    $updateData = [];
    if (isset($input['name'])) $updateData['name'] = $input['name'];
    if (isset($input['host'])) $updateData['host'] = $input['host'];
    if (isset($input['port'])) $updateData['port'] = (int)$input['port'];
    if (isset($input['username'])) $updateData['username'] = $input['username'];
    if (isset($input['password'])) $updateData['password'] = $input['password'];

    $updated = $store->update($input['id'], $updateData);
    if ($updated) jsonResponse($updated);
    else jsonResponse(['error' => 'Server not found'], 404);
} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) jsonResponse(['error' => 'Missing ID'], 400);
    if ($store->delete($input['id'])) jsonResponse(['success' => true]);
    else jsonResponse(['error' => 'Server not found'], 404);
}
