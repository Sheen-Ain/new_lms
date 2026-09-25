<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    header('Location: ' . BASE_PATH . '/' . $role . '/');
} else {
    header('Location: ' . BASE_PATH . '/auth/login.php');
}
exit;
