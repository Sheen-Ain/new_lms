<?php
// Quick diagnostic — visit this URL on byethost to verify PHP + DB + cURL
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
ob_clean();
header('Content-Type: application/json');

$result = [
    'php_version'    => PHP_VERSION,
    'curl_available' => function_exists('curl_init'),
    'session_id'     => session_id() ? 'active' : 'none',
    'logged_in'      => !empty($_SESSION['user_id']),
    'db_connected'   => ($conn && !$conn->connect_error),
    'mysql_version'  => ($conn && !$conn->connect_error) ? $conn->server_info : 'N/A',
];

echo json_encode($result);