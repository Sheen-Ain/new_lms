<?php

/**
 * download.php — Single-file download with smart filenames
 *
 * Topics:      topicname_originalfile.ext
 * Assignments: assignmentname_originalfile.ext
 * Submissions: 00012_studentname_originalfile.ext
 */
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id']   ?? 0);
$me   = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!$type || !$id) {
    http_response_code(400);
    exit('Invalid request');
}

define('UPLOAD_ROOT', realpath(__DIR__ . '/uploads'));

$filePath  = null;  // absolute path on disk
$fileName  = null;  // download filename shown to user

/* ── Helpers ───────────────────────────────────────────────── */
function slugPart(string $s): string
{
    return preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($s))));
}
function padId(int $id): string
{
    return str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

/* ── Resolve file ──────────────────────────────────────────── */
if ($type === 'topic') {
    $stmt = $conn->prepare("
        SELECT tf.file_name, tf.file_path, t.title AS topic_title
        FROM topic_files tf
        JOIN topics t ON tf.topic_id = t.id
        WHERE tf.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f) {
        http_response_code(404);
        exit('Not found');
    }

    $filePath = UPLOAD_ROOT . '/topics/' . $f['file_path'];
    $ext      = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
    $origBase = pathinfo($f['file_name'], PATHINFO_FILENAME);
    $fileName = slugPart($f['topic_title']) . '_' . slugPart($origBase) . '.' . $ext;
} elseif ($type === 'assignment') {
    $stmt = $conn->prepare("
        SELECT af.file_name, af.file_path, a.title AS assignment_title
        FROM assignment_files af
        JOIN assignments a ON af.assignment_id = a.id
        WHERE af.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f) {
        http_response_code(404);
        exit('Not found');
    }

    $filePath = UPLOAD_ROOT . '/assignments/' . $f['file_path'];
    $ext      = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
    $origBase = pathinfo($f['file_name'], PATHINFO_FILENAME);
    $fileName = slugPart($f['assignment_title']) . '_' . slugPart($origBase) . '.' . $ext;
} elseif ($type === 'submission') {
    $stmt = $conn->prepare("
        SELECT s.file_name, s.file_path, s.student_id,
               u.full_name AS student_name, u.user_id_number AS student_num,
               a.title AS assignment_title
        FROM submissions s
        JOIN users u       ON s.student_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        WHERE s.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f) {
        http_response_code(404);
        exit('Not found');
    }

    // Students can only download their own submissions
    if ($role === 'student' && (int)$f['student_id'] !== $me) {
        http_response_code(403);
        exit('Access denied');
    }

    $filePath = UPLOAD_ROOT . '/submissions/' . $f['file_path'];

    // The stored file_path is now: assignment_name/00012_ali_filename.ext
    // Use the basename directly — student ID is already embedded in the filename
    $storedBasename = basename($f['file_path']);
    $ext            = strtolower(pathinfo($storedBasename, PATHINFO_EXTENSION));

    // If new-style filename (contains underscore pattern with digits), use as-is
    // Otherwise fall back to generating: 00012_ali_originalfile.ext
    if (preg_match('/^\d{5}_/', $storedBasename)) {
        $fileName = $storedBasename;
    } else {
        // Legacy flat file
        $origBase = pathinfo($f['file_name'], PATHINFO_FILENAME);
        $sidPart  = $f['student_num'] ?: padId((int)$f['student_id']);
        $namePart = slugPart(explode(' ', $f['student_name'])[0]);
        $fileName = $sidPart . '_' . $namePart . '_' . slugPart($origBase) . '.' . $ext;
    }
} else {
    http_response_code(400);
    exit('Invalid type');
}

/* ── Security: path traversal check ───────────────────────── */
$realFile = realpath($filePath);
if (!$realFile) {
    http_response_code(404);
    exit('File not found on disk');
}
if (strpos($realFile, UPLOAD_ROOT) !== 0) {
    http_response_code(403);
    exit('Path not allowed');
}

/* ── Stream ────────────────────────────────────────────────── */
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
ob_clean();
flush();
readfile($realFile);
exit;
