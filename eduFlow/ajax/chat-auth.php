<?php
// ============================================================
// PUSHER CHANNEL AUTH
// ============================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pusher.php';
require_once __DIR__ . '/../includes/helpers.php';

ob_clean();
if (!headers_sent()) header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$me          = (int)$_SESSION['user_id'];
$socketId    = $_POST['socket_id']    ?? '';
$channelName = $_POST['channel_name'] ?? '';

if (!$socketId || !$channelName) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$allowed = false;

// ── User's own notification channel ──────────────────────────
if ($channelName === "private-user-{$me}") {
    $allowed = true;
}

// ── Typing channel for a conversation the user is part of ────
if (preg_match('/^private-typing-(\d+)$/', $channelName, $m)) {
    $convId = (int)$m[1];
    $stmt = $conn->prepare(
        "SELECT id FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)"
    );
    $stmt->bind_param('iii', $convId, $me, $me);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $allowed = true;
    $stmt->close();
}

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

echo json_encode(pusherChannelAuth($socketId, $channelName));
