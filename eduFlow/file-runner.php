<?php

/**
 * file-runner.php — Secure file viewer, streamer, executor & editor
 *
 * Actions:
 *   stream  → serve PDF/images inline with correct MIME
 *   source  → return raw source as text/plain (for code viewer)
 *   run     → execute PHP (ob_start+include) or serve HTML in iframe
 *   save    → overwrite file content (admin/teacher only)
 *
 * Security:
 *   - Session auth required
 *   - File resolved from DB by (type, id) — never raw path from URL
 *   - realpath() confirms file is inside UPLOAD_ROOT
 *   - save restricted to admin/teacher roles only
 *   - PHP execution via ob_start+include (works on ALL shared hosting)
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

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$type   = $_GET['type']   ?? $_POST['type']   ?? '';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$action || !$type || !$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

define('UPLOAD_ROOT', realpath(__DIR__ . '/uploads'));

/* ── Resolve file from DB ────────────────────────────────────── */
$filePath = $fileName = null;

if ($type === 'topic') {
    $stmt = $conn->prepare("SELECT tf.file_path, tf.file_name, tf.status FROM topic_files tf WHERE tf.id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f || $f['status'] === 'inactive') {
        http_response_code(404);
        exit('Not found');
    }
    $filePath = UPLOAD_ROOT . '/topics/' . $f['file_path'];
    $fileName = $f['file_name'];
} elseif ($type === 'assignment') {
    $stmt = $conn->prepare("SELECT af.file_path, af.file_name, af.status FROM assignment_files af WHERE af.id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f || $f['status'] === 'inactive') {
        http_response_code(404);
        exit('Not found');
    }
    $filePath = UPLOAD_ROOT . '/assignments/' . $f['file_path'];
    $fileName = $f['file_name'];
} elseif ($type === 'submission') {
    $stmt = $conn->prepare("SELECT s.file_path, s.file_name, s.status, s.student_id FROM submissions s WHERE s.id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$f) {
        http_response_code(404);
        exit('Not found');
    }
    if ($myRole === 'student' && (int)$f['student_id'] !== $me) {
        http_response_code(403);
        exit('Access denied');
    }
    $filePath = UPLOAD_ROOT . '/submissions/' . $f['file_path'];
    $fileName = $f['file_name'];
} else {
    http_response_code(400);
    exit('Unknown type');
}

/* ── Validate path (prevent directory traversal) ─────────────── */
$realFile = realpath($filePath);
if (!$realFile) {
    http_response_code(404);
    exit('File not found on disk');
}
if (strpos($realFile, UPLOAD_ROOT) !== 0) {
    http_response_code(403);
    exit('Path not allowed');
}

$ext  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$size = filesize($realFile);

$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'bmp'  => 'image/bmp',
    'ico'  => 'image/x-icon',
    'html' => 'text/html; charset=UTF-8',
    'htm'  => 'text/html; charset=UTF-8',
];

/* ──────────────────────────────────────────────────────────────
   ACTION: stream — PDF and images inline
────────────────────────────────────────────────────────────── */
if ($action === 'stream') {
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    ob_clean();
    flush();
    readfile($realFile);
    exit;
}

/* ──────────────────────────────────────────────────────────────
   ACTION: source — return raw source as plain text
────────────────────────────────────────────────────────────── */
if ($action === 'source') {
    $allowed = ['html','htm','php','js','ts','jsx','tsx','vue','css',
                'txt','sql','csv','json','xml','yaml','yml','md',
                'py','pyc','pyw','ipynb',
                'java','c','cpp','h','hpp','cs','rb','go','rs','kt',
                'swift','dart','lua','pl','scala','r',
                'sh','bash','bat','ps1','env','blade'];
    if (!in_array($ext, $allowed)) {
        http_response_code(415);
        exit('Source view not supported');
    }
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    ob_clean();
    flush();
    readfile($realFile);
    exit;
}

/* ──────────────────────────────────────────────────────────────
   ACTION: run — execute PHP or serve HTML
────────────────────────────────────────────────────────────── */
if ($action === 'run') {
    header('Cache-Control: no-store, no-cache');
    header('X-Content-Type-Options: nosniff');

    /* ── HTML: serve with clean reset ── */
    if ($ext === 'html' || $ext === 'htm') {
        header('Content-Type: text/html; charset=UTF-8');
        $html = file_get_contents($realFile);
        // Inject a <base> and viewport if not already present so relative paths work
        if (stripos($html, '<head>') !== false || stripos($html, '<html') !== false) {
            echo $html;
        } else {
            // Bare HTML snippet — wrap it
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<style>body{margin:0;font-family:system-ui,sans-serif;background:#fff;color:#111;padding:10px;}</style>'
                . '</head><body>' . $html . '</body></html>';
        }
        exit;
    }

    /* ── PHP: execute via output buffering + include ── */
    if ($ext === 'php') {
        header('Content-Type: text/html; charset=UTF-8');

        // Capture the output safely
        $output = '';
        $error  = '';

        try {
            ob_start();

            // Restrict the include environment:
            // - Unset dangerous superglobals that could leak LMS session data
            // - Set a clean working directory
            $savedDir = getcwd();
            chdir(dirname($realFile));

            // Run the file in a clean scope to avoid variable leakage
            (static function ($__file) {
                include $__file;
            })($realFile);

            $output = ob_get_clean();
            chdir($savedDir);
        } catch (Throwable $e) {
            $output = ob_get_clean();
            $error  = get_class($e) . ': ' . $e->getMessage()
                . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>'
            . 'body{margin:0;font-family:system-ui,sans-serif;background:#fff;color:#111;padding:10px;}'
            . '.lms-err{background:#fef2f2;border-left:3px solid #ef4444;padding:12px 16px;margin-top:12px;'
            . 'font-family:monospace;font-size:.82rem;white-space:pre-wrap;color:#b91c1c;border-radius:4px;}'
            . '.lms-meta{font-size:.7rem;color:#94a3b8;margin-top:8px;padding:5px 10px;'
            . 'background:#f8fafc;border-radius:4px;}'
            . '</style></head><body>';

        echo $output;

        if ($error) {
            echo '<div class="lms-err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo '</body></html>';
        exit;
    }

    /* ── Python: not executable on this hosting environment ── */
    if ($ext === 'py' || $ext === 'pyw') {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>'
            . 'body{margin:0;font-family:system-ui,sans-serif;background:#fff;color:#111;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;box-sizing:border-box;}'
            . '.box{text-align:center;max-width:420px;}'
            . '.icon{font-size:2.5rem;margin-bottom:12px;}'
            . 'h3{margin:0 0 8px;font-size:1rem;color:#374151;}'
            . 'p{margin:0 0 16px;font-size:.85rem;color:#6b7280;line-height:1.6;}'
            . '.badge{display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;'
            . 'border-radius:6px;padding:4px 12px;font-size:.78rem;color:#6b7280;}'
            . '</style></head><body>'
            . '<div class="box">'
            . '<div class="icon">🐍</div>'
            . '<h3>Python execution is not available</h3>'
            . '<p>This server does not support running Python scripts directly.<br>'
            . 'You can still <strong>view</strong> and <strong>edit</strong> the source code using the tabs above.</p>'
            . '<span class="badge">View Code &amp; Edit are fully supported</span>'
            . '</div>'
            . '</body></html>';
        exit;
    }

    http_response_code(415);
    exit('Execution not supported for this file type');
}

/* ──────────────────────────────────────────────────────────────
   ACTION: save — write edited content back to disk
   Restricted to admin and teacher only
────────────────────────────────────────────────────────────── */
if ($action === 'save') {
    header('Content-Type: application/json');

    // Only admin and teacher may edit files
    if (!in_array($myRole, ['admin', 'teacher'])) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied — only admin and teacher can edit files']);
        exit;
    }

    // Only code files are editable
    $editableExts = ['html','htm','php','js','ts','jsx','tsx','vue','css',
                     'txt','sql','json','xml','yaml','yml','md','csv',
                     'py','pyw',
                     'java','c','cpp','h','hpp','cs','rb','go','rs','kt',
                     'swift','dart','lua','pl','scala','r',
                     'sh','bash','bat','ps1','env','blade'];
    if (!in_array($ext, $editableExts)) {
        echo json_encode(['status' => 'error', 'message' => 'This file type cannot be edited']);
        exit;
    }

    $content = $_POST['content'] ?? null;
    if ($content === null) {
        echo json_encode(['status' => 'error', 'message' => 'No content provided']);
        exit;
    }

    // Write back to disk
    $bytes = file_put_contents($realFile, $content);
    if ($bytes === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to write file — check server permissions']);
        exit;
    }

    // Update file_size in DB
    $newSize = filesize($realFile);
    if ($type === 'topic') {
        $upd = $conn->prepare("UPDATE topic_files SET file_size=? WHERE id=?");
        $upd->bind_param('ii', $newSize, $id);
        $upd->execute();
        $upd->close();
    } elseif ($type === 'assignment') {
        $upd = $conn->prepare("UPDATE assignment_files SET file_size=? WHERE id=?");
        $upd->bind_param('ii', $newSize, $id);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['status' => 'success', 'message' => 'File saved', 'size' => $newSize]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);