<?php
require_once 'DataStore.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$host = isset($input['host']) ? $input['host'] : '';
$port = isset($input['port']) ? (int)$input['port'] : 21;
$username = isset($input['username']) ? $input['username'] : '';
$password = isset($input['password']) ? $input['password'] : '';

if (empty($host) || empty($username)) {
    jsonResponse(['error' => 'Host and username are required'], 400);
}

// Suppress warnings for FTP connection to handle errors manually
error_reporting(0);

$conn = ftp_connect($host, $port, 10);
if (!$conn) {
    jsonResponse(['success' => false, 'message' => 'Could not connect to host ' . $host]);
}

$login = ftp_login($conn, $username, $password);
if (!$login) {
    ftp_close($conn);
    jsonResponse(['success' => false, 'message' => 'FTP login failed for user ' . $username]);
}

// Enable passive mode
ftp_pasv($conn, true);

ftp_close($conn);

jsonResponse(['success' => true, 'message' => 'Connection successful!']);
