<?php
error_reporting(0);
ini_set("display_errors", "0");
ob_start(); // Buffer all output so stray warnings never break JSON


if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');
if (($_SESSION['role'] ?? '') === 'student') jsonResponse('error', 'Access denied');
$me = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'clear_all':
        clearAll();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}
function clearAll()
{
    global $conn, $me;
    if (($_SESSION['role'] ?? '') !== 'admin') jsonResponse('error', 'Admin only');
    $conn->query("DELETE FROM activity_logs");
    logActivity($conn, $me, "Cleared all activity logs", 'activity_logs');
    jsonResponse('success', 'All activity logs cleared');
}
