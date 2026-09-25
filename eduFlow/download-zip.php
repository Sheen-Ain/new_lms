<?php

/**
 * download-zip.php — Bulk ZIP download with smart filenames
 *
 * Topics ZIP:      topicname.zip  containing → file1.pdf, notes.jpg
 * Assignments ZIP: assignmentname.zip         → file1.pdf, file2.docx
 * Submissions ZIP: assignmentname.zip         → 00012_ali_solution.pdf, 00015_sara_hw.php
 */
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

require_once __DIR__ . '/config/db.php';

$type = $_POST['type'] ?? $_GET['type'] ?? '';
$ids  = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? $_GET['ids'] ?? '')));
$me   = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if (!$type || empty($ids)) {
    http_response_code(400);
    exit('Invalid request');
}
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP extension not available');
}

define('UPLOAD_ROOT', realpath(__DIR__ . '/uploads'));

function slugZ(string $s): string
{
    return preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($s))));
}
function padIdZ(int $id): string
{
    return str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

$files   = [];  // [['zip_entry_name' => ..., 'disk_path' => ...], ...]
$zipName = 'files.zip';

/* ── Topic files ─────────────────────────────────────────────── */
if ($type === 'topic_files') {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    $stmt = $conn->prepare("
        SELECT tf.file_name, tf.file_path, t.title AS topic_title
        FROM topic_files tf
        JOIN topics t ON tf.topic_id = t.id
        WHERE tf.id IN ($ph)
    ");
    $stmt->bind_param($tp, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $topicTitle = $rows[0]['topic_title'] ?? 'topic';
    $zipName    = slugZ($topicTitle) . '.zip';

    foreach ($rows as $r) {
        // Inside ZIP: just the original filename (no prefix needed — it's one topic)
        $files[] = [
            'zip_name'  => $r['file_name'],
            'disk_path' => UPLOAD_ROOT . '/topics/' . $r['file_path'],
        ];
    }

    /* ── Assignment files ─────────────────────────────────────────── */
} elseif ($type === 'assignment_files') {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    $stmt = $conn->prepare("
        SELECT af.file_name, af.file_path, a.title AS assignment_title
        FROM assignment_files af
        JOIN assignments a ON af.assignment_id = a.id
        WHERE af.id IN ($ph)
    ");
    $stmt->bind_param($tp, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $aTitle  = $rows[0]['assignment_title'] ?? 'assignment';
    $zipName = slugZ($aTitle) . '.zip';

    foreach ($rows as $r) {
        $files[] = [
            'zip_name'  => $r['file_name'],
            'disk_path' => UPLOAD_ROOT . '/assignments/' . $r['file_path'],
        ];
    }

    /* ── Submissions ─────────────────────────────────────────────── */
} elseif ($type === 'my_submissions') {
    // Students can only bulk-download their own submissions
    if ($role !== 'student') {
        http_response_code(403);
        exit('Access denied');
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    // Extra AND s.student_id=? ensures student can never grab another student's files
    $stmt = $conn->prepare("
        SELECT s.file_name, s.file_path, a.title AS assignment_title
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE s.id IN ($ph) AND s.student_id = ? AND s.file_path IS NOT NULL AND s.file_path != ''
    ");
    $allParams = array_merge($ids, [$me]);
    $allTypes  = $tp . 'i';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $zipName = 'my_submissions.zip';

    foreach ($rows as $r) {
        $storedBase = basename($r['file_path']);
        $aSlug      = slugZ($r['assignment_title'] ?? 'assignment');
        $zipEntry   = $aSlug . '_' . $storedBase;
        $files[] = [
            'zip_name'  => $zipEntry,
            'disk_path' => UPLOAD_ROOT . '/submissions/' . $r['file_path'],
        ];
    }
} elseif ($type === 'submissions') {
    if (!in_array($role, ['admin', 'teacher'])) {
        http_response_code(403);
        exit('Access denied');
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tp = str_repeat('i', count($ids));
    $stmt = $conn->prepare("
        SELECT s.file_name, s.file_path, s.student_id,
               u.full_name AS student_name, u.user_id_number AS student_num,
               a.title AS assignment_title
        FROM submissions s
        JOIN users u       ON s.student_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        WHERE s.id IN ($ph) AND s.file_path IS NOT NULL AND s.file_path != ''
    ");
    $stmt->bind_param($tp, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $aTitle  = $rows[0]['assignment_title'] ?? 'submissions';
    $zipName = slugZ($aTitle) . '.zip';

    foreach ($rows as $r) {
        // New-style: file_path is "assignment_name/00012_ali_filename.ext"
        $storedBase = basename($r['file_path']);
        $ext        = strtolower(pathinfo($storedBase, PATHINFO_EXTENSION));

        // If filename already starts with 5-digit ID (new format), use basename directly
        if (preg_match('/^\d{5}_/', $storedBase)) {
            $zipEntry = $storedBase;
        } else {
            // Legacy flat filename — generate clean name
            $sidPart  = $r['student_num'] ?: padIdZ((int)$r['student_id']);
            $namePart = slugZ(explode(' ', $r['student_name'])[0]);
            $zipEntry = $sidPart . '_' . $namePart . ($ext ? '.' . $ext : '');
        }

        $files[] = [
            'zip_name'  => $zipEntry,
            'disk_path' => UPLOAD_ROOT . '/submissions/' . $r['file_path'],
        ];
    }
} else {
    http_response_code(400);
    exit('Invalid type');
}

if (empty($files)) {
    http_response_code(404);
    exit('No files found');
}

/* ── Build ZIP ─────────────────────────────────────────────── */
$tmpFile = tempnam(sys_get_temp_dir(), 'lms_zip_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Cannot create ZIP');
}

$seen = [];
foreach ($files as $f) {
    $diskPath = realpath($f['disk_path']);
    if (!$diskPath || strpos($diskPath, UPLOAD_ROOT) !== 0) continue; // security check
    if (!file_exists($diskPath)) continue;

    // Deduplicate zip entry names
    $name  = $f['zip_name'];
    $base  = pathinfo($name, PATHINFO_FILENAME);
    $ext   = pathinfo($name, PATHINFO_EXTENSION);
    $final = $name;
    $i = 1;
    while (isset($seen[$final])) {
        $final = $base . '_' . $i . ($ext ? '.' . $ext : '');
        $i++;
    }
    $seen[$final] = true;
    $zip->addFile($diskPath, $final);
}
$zip->close();

if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
    http_response_code(500);
    exit('ZIP is empty — files may be missing on disk');
}

/* ── Stream ────────────────────────────────────────────────── */
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
ob_clean();
flush();
readfile($tmpFile);
@unlink($tmpFile);
exit;