<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];

    // Mark offline
    $stmt = $conn->prepare("UPDATE users SET is_online=0, last_seen=NOW() WHERE id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    logActivity($conn, $userId, 'Logged out', 'auth');
}

// Destroy session
session_destroy();

// Clear cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie(session_name(), '', time() - 3600, '/');

header('Location: ' . BASE_PATH . '/auth/login.php?msg=logged_out');
exit;
