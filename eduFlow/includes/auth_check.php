<?php
// ============================================================
// AUTH CHECK — Session Guard
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

// Define required role (set before including this file)
// e.g., $requiredRole = 'admin';  $requiredRole = 'teacher';  $requiredRole = 'student';
if (!isset($requiredRole)) $requiredRole = null;

// Check if logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

// Fetch fresh user data
$stmt = $conn->prepare("
    SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) as all_roles
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    session_destroy();
    header('Location: ' . BASE_PATH . '/auth/login.php');
    exit;
}

// Check account status
if ($currentUser['status'] !== 'active') {
    session_destroy();
    header('Location: ' . BASE_PATH . '/auth/login.php?error=account_inactive');
    exit;
}

// Role check
if ($requiredRole && $currentUser['current_role'] !== $requiredRole) {
    // Check if user has the required role
    $userRoles = explode(',', $currentUser['all_roles'] ?? '');
    if (!in_array($requiredRole, $userRoles)) {
        // Redirect to their dashboard
        $dashMap = ['admin' => BASE_PATH . '/admin/', 'teacher' => BASE_PATH . '/teacher/', 'student' => BASE_PATH . '/student/'];
        $redirect = $dashMap[$currentUser['current_role']] ?? BASE_PATH . '/auth/login.php';
        header('Location: ' . $redirect . '?error=unauthorized');
        exit;
    }
}

// Update last seen + online status (throttle: only every 60 seconds)
$lastUpdate = $_SESSION['last_activity_update'] ?? 0;
if (time() - $lastUpdate > 60) {
    $stmt = $conn->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['last_activity_update'] = time();
}

// Set session role from DB current_role
$_SESSION['role'] = $currentUser['current_role'];
$userRole = $currentUser['current_role'];

// Make currentUser available globally
$_currentUser = $currentUser;
