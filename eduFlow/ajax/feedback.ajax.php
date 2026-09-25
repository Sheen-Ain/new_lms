<?php
// ============================================================
// FEEDBACK AJAX — v2
// Roles:
//   Admin:   admin_batches, list_sessions, create_session,
//            toggle_session, delete_session, list_entries,
//            delete_entry, mark_reviewed, get_stats,
//            export_entries
//   Teacher: teacher_sessions, list_entries, get_stats
//   Student: student_batches, submit_feedback, check_submitted
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // Admin-only
    case 'admin_batches':  requireRole('admin'); adminBatches();  break;
    case 'list_sessions':  requireRole('admin'); listSessions();  break;
    case 'create_session': requireRole('admin'); createSession(); break;
    case 'toggle_session': requireRole('admin'); toggleSession(); break;
    case 'delete_session': requireRole('admin'); deleteSession(); break;
    case 'delete_entry':   requireRole('admin'); deleteEntry();   break;
    case 'mark_reviewed':  requireRole('admin'); markReviewed();  break;
    case 'export_entries': requireAdminOrTeacher(); exportEntries(); break;

    // Admin + Teacher
    case 'list_entries':   requireAdminOrTeacher(); listEntries(); break;
    case 'get_stats':      requireAdminOrTeacher(); getStats();    break;
    case 'teacher_sessions': requireAdminOrTeacher(); teacherSessions(); break;

    // Student-only
    case 'student_batches':  requireRole('student'); studentBatches();  break;
    case 'submit_feedback':  requireRole('student'); submitFeedback();  break;
    case 'check_submitted':  requireRole('student'); checkSubmitted();  break;

    default: jsonResponse('error', 'Unknown action');
}

// ── Category master list ─────────────────────────────────────
function getAllCategories(): array
{
    return [
        'teaching'      => ['label' => 'Teaching Quality',       'emoji' => '🎓', 'color' => '#6366f1'],
        'curriculum'    => ['label' => 'Curriculum & Content',   'emoji' => '📚', 'color' => '#8b5cf6'],
        'assignments'   => ['label' => 'Assignments & Tests',    'emoji' => '📝', 'color' => '#06b6d4'],
        'communication' => ['label' => 'Teacher Communication',  'emoji' => '💬', 'color' => '#0891b2'],
        'scheduling'    => ['label' => 'Schedule & Timing',      'emoji' => '⏰', 'color' => '#f59e0b'],
        'resources'     => ['label' => 'Learning Resources',     'emoji' => '📖', 'color' => '#10b981'],
        'organization'  => ['label' => 'Organization & Mgmt',    'emoji' => '🏛', 'color' => '#059669'],
        'facilities'    => ['label' => 'Facilities & Environment','emoji' => '🏫', 'color' => '#d97706'],
        'platform'      => ['label' => 'LMS Platform',           'emoji' => '💻', 'color' => '#ec4899'],
        'suggestions'   => ['label' => 'Suggestions & Ideas',    'emoji' => '💡', 'color' => '#f97316'],
        'general'       => ['label' => 'General',                'emoji' => '📋', 'color' => '#6b7280'],
    ];
}

// ════════════════════════════════════════════════════════════
// ADMIN ACTIONS
// ════════════════════════════════════════════════════════════

function adminBatches()
{
    global $conn;
    $batches = $conn->query("
        SELECT b.id, b.name, b.status, b.end_date,
               c.title AS course_title,
               (SELECT COUNT(*) FROM feedback_sessions WHERE batch_id = b.id) AS session_count,
               (SELECT COUNT(*) FROM feedback_sessions WHERE batch_id = b.id AND is_active=1) AS active_sessions,
               (SELECT COUNT(*) FROM feedback_entries fe
                JOIN feedback_sessions fs ON fe.session_id=fs.id
                WHERE fs.batch_id = b.id) AS total_entries,
               (SELECT COUNT(*) FROM feedback_entries fe
                JOIN feedback_sessions fs ON fe.session_id=fs.id
                WHERE fs.batch_id = b.id AND fe.is_reviewed=0) AS unread_entries
        FROM batches b
        JOIN courses c ON b.course_id = c.id
        ORDER BY b.status='active' DESC, c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['batches' => $batches, 'categories' => getAllCategories()]);
}

function listSessions()
{
    global $conn;
    $batchId = (int)($_POST['batch_id'] ?? 0);
    if (!$batchId) jsonResponse('error', 'Batch ID required');

    $sessions = $conn->query("
        SELECT fs.*,
               u.full_name AS activated_by_name,
               (SELECT COUNT(*) FROM feedback_entries WHERE session_id=fs.id) AS entry_count,
               (SELECT COUNT(*) FROM feedback_entries WHERE session_id=fs.id AND is_reviewed=0) AS unread_count,
               (SELECT COUNT(*) FROM feedback_tokens  WHERE session_id=fs.id) AS submitter_count,
               (SELECT COUNT(*) FROM batch_students WHERE batch_id=fs.batch_id) AS enrolled_count
        FROM feedback_sessions fs
        LEFT JOIN users u ON fs.activated_by=u.id
        WHERE fs.batch_id=$batchId
        ORDER BY fs.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['sessions' => $sessions]);
}

function createSession()
{
    global $conn, $me;
    $batchId     = (int)($_POST['batch_id']   ?? 0);
    $title       = trim($_POST['title']        ?? 'Batch Feedback');
    $description = trim($_POST['description']  ?? '');
    $activateNow = (int)($_POST['activate']    ?? 0);

    if (!$batchId) jsonResponse('error', 'Batch ID required');
    if (!$title)   jsonResponse('error', 'Title is required');

    $batch = $conn->query("SELECT id,name FROM batches WHERE id=$batchId")->fetch_assoc();
    if (!$batch) jsonResponse('error', 'Batch not found');

    if ($activateNow) {
        $conn->query("UPDATE feedback_sessions SET is_active=0, deactivated_at=NOW() WHERE batch_id=$batchId AND is_active=1");
    }

    $isActive    = $activateNow ? 1 : 0;
    $activatedAt = $activateNow ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("INSERT INTO feedback_sessions (batch_id,title,description,is_active,activated_by,activated_at) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssis', $batchId, $title, $description, $isActive, $me, $activatedAt);
    if (!$stmt->execute()) jsonResponse('error', 'Failed to create: ' . $conn->error);
    $newId = (int)$conn->insert_id;
    $stmt->close();

    logActivity($conn, $me, "Created feedback session for batch ID $batchId" . ($activateNow ? ' (activated)' : ''), 'feedback');
    jsonResponse('success', 'Feedback session created' . ($activateNow ? ' and activated.' : '.'), ['session_id' => $newId]);
}

function toggleSession()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $activate  = (int)($_POST['activate']   ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $session = $conn->query("SELECT * FROM feedback_sessions WHERE id=$sessionId")->fetch_assoc();
    if (!$session) jsonResponse('error', 'Session not found');

    if ($activate) {
        $conn->query("UPDATE feedback_sessions SET is_active=0, deactivated_at=NOW() WHERE batch_id={$session['batch_id']} AND is_active=1 AND id!=$sessionId");
        $conn->query("UPDATE feedback_sessions SET is_active=1, activated_at=NOW(), deactivated_at=NULL, activated_by=$me WHERE id=$sessionId");
        logActivity($conn, $me, "Activated feedback session ID $sessionId", 'feedback');
        jsonResponse('success', 'Feedback is now LIVE — students can submit.');
    } else {
        $conn->query("UPDATE feedback_sessions SET is_active=0, deactivated_at=NOW() WHERE id=$sessionId");
        logActivity($conn, $me, "Deactivated feedback session ID $sessionId", 'feedback');
        jsonResponse('success', 'Feedback session closed.');
    }
}

function deleteSession()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');
    $conn->query("DELETE FROM feedback_sessions WHERE id=$sessionId");
    logActivity($conn, $me, "Deleted feedback session ID $sessionId", 'feedback');
    jsonResponse('success', 'Session and all its entries deleted.');
}

function deleteEntry()
{
    global $conn, $me;
    $entryId = (int)($_POST['entry_id'] ?? 0);
    if (!$entryId) jsonResponse('error', 'Entry ID required');
    $conn->query("DELETE FROM feedback_entries WHERE id=$entryId");
    logActivity($conn, $me, "Deleted feedback entry ID $entryId", 'feedback');
    jsonResponse('success', 'Entry removed.');
}

function markReviewed()
{
    global $conn, $me;
    $entryId    = (int)($_POST['entry_id']   ?? 0);
    $sessionId  = (int)($_POST['session_id'] ?? 0);
    $markAll    = (int)($_POST['mark_all']   ?? 0);

    if ($markAll && $sessionId) {
        $conn->query("UPDATE feedback_entries SET is_reviewed=1 WHERE session_id=$sessionId");
        jsonResponse('success', 'All entries marked as reviewed.');
    } elseif ($entryId) {
        $reviewed = (int)($_POST['reviewed'] ?? 1);
        $conn->query("UPDATE feedback_entries SET is_reviewed=$reviewed WHERE id=$entryId");
        jsonResponse('success', 'Updated.');
    } else {
        jsonResponse('error', 'Invalid request');
    }
}

// ── List entries (admin + teacher) with search + category filter ─
function listEntries()
{
    global $conn, $myRole, $me;
    $sessionId    = (int)($_POST['session_id']    ?? 0);
    $search       = trim($_POST['search']         ?? '');
    $filterCat    = trim($_POST['filter_category'] ?? '');
    $filterStatus = trim($_POST['filter_status']   ?? ''); // all|unread|reviewed
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    // Verify access
    $session = $conn->query("SELECT fs.*, b.name AS batch_name, c.title AS course_title
        FROM feedback_sessions fs
        JOIN batches b ON fs.batch_id=b.id
        JOIN courses c ON b.course_id=c.id
        WHERE fs.id=$sessionId")->fetch_assoc();
    if (!$session) jsonResponse('error', 'Session not found');

    if ($myRole === 'teacher') {
        $allowed = $conn->query("SELECT id FROM batch_teachers WHERE batch_id={$session['batch_id']} AND teacher_id=$me")->fetch_assoc();
        if (!$allowed) jsonResponse('error', 'Access denied');
    }

    // Build WHERE clauses
    $where = ["session_id = $sessionId"];
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where[] = "content LIKE '%$s%'";
    }
    if ($filterCat) {
        $fc = $conn->real_escape_string($filterCat);
        // JSON contains check — works on MySQL 5.7+ and MariaDB 10.2+
        $where[] = "JSON_CONTAINS(categories, '\"$fc\"')";
    }
    if ($filterStatus === 'unread')   $where[] = "is_reviewed = 0";
    if ($filterStatus === 'reviewed') $where[] = "is_reviewed = 1";

    $wSQL = 'WHERE ' . implode(' AND ', $where);
    $entries = $conn->query("SELECT id, content, categories, is_reviewed, submitted_at FROM feedback_entries $wSQL ORDER BY submitted_at DESC")->fetch_all(MYSQLI_ASSOC);

    // Decode JSON categories
    foreach ($entries as &$e) {
        $decoded = json_decode($e['categories'] ?? '[]', true);
        $e['categories'] = is_array($decoded) ? $decoded : ['general'];
    }

    jsonResponse('success', 'OK', ['entries' => $entries, 'session' => $session]);
}

// ── Get stats for a session ───────────────────────────────────
function getStats()
{
    global $conn, $myRole, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $session = $conn->query("SELECT * FROM feedback_sessions WHERE id=$sessionId")->fetch_assoc();
    if (!$session) jsonResponse('error', 'Session not found');

    if ($myRole === 'teacher') {
        $allowed = $conn->query("SELECT id FROM batch_teachers WHERE batch_id={$session['batch_id']} AND teacher_id=$me")->fetch_assoc();
        if (!$allowed) jsonResponse('error', 'Access denied');
    }

    $enrolled   = (int)$conn->query("SELECT COUNT(*) AS c FROM batch_students WHERE batch_id={$session['batch_id']}")->fetch_assoc()['c'];
    $submitted  = (int)$conn->query("SELECT COUNT(*) AS c FROM feedback_tokens WHERE session_id=$sessionId")->fetch_assoc()['c'];
    $entryCount = (int)$conn->query("SELECT COUNT(*) AS c FROM feedback_entries WHERE session_id=$sessionId")->fetch_assoc()['c'];
    $unread     = (int)$conn->query("SELECT COUNT(*) AS c FROM feedback_entries WHERE session_id=$sessionId AND is_reviewed=0")->fetch_assoc()['c'];

    // Count per category (JSON-based — iterate in PHP for compatibility)
    $allEntries = $conn->query("SELECT categories FROM feedback_entries WHERE session_id=$sessionId")->fetch_all(MYSQLI_ASSOC);
    $catCounts  = [];
    foreach (array_keys(getAllCategories()) as $k) $catCounts[$k] = 0;
    foreach ($allEntries as $row) {
        $cats = json_decode($row['categories'] ?? '[]', true);
        if (!is_array($cats)) continue;
        foreach ($cats as $cat) {
            if (isset($catCounts[$cat])) $catCounts[$cat]++;
        }
    }

    jsonResponse('success', 'OK', [
        'enrolled'      => $enrolled,
        'submitted'     => $submitted,
        'entry_count'   => $entryCount,
        'unread'        => $unread,
        'response_rate' => $enrolled > 0 ? round(($submitted/$enrolled)*100, 1) : 0,
        'categories'    => $catCounts,
    ]);
}

// ── Export entries to Excel (HTML-XLS) ───────────────────────
function exportEntries()
{
    global $conn, $myRole, $me;
    if (ob_get_level() > 0) ob_end_clean();

    $sessionId = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
    if (!$sessionId) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'Invalid ID']); exit; }

    $session = $conn->query("SELECT fs.*, b.name AS batch_name, c.title AS course_title FROM feedback_sessions fs JOIN batches b ON fs.batch_id=b.id JOIN courses c ON b.course_id=c.id WHERE fs.id=$sessionId")->fetch_assoc();
    if (!$session) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'Not found']); exit; }

    // Teacher scope check
    if ($myRole === 'teacher') {
        $allowed = $conn->query("SELECT id FROM batch_teachers WHERE batch_id={$session['batch_id']} AND teacher_id=$me")->fetch_assoc();
        if (!$allowed) { header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
    }

    $entries = $conn->query("SELECT id, content, categories, is_reviewed, submitted_at FROM feedback_entries WHERE session_id=$sessionId ORDER BY submitted_at DESC")->fetch_all(MYSQLI_ASSOC);

    $cats     = getAllCategories();
    $safeName = preg_replace('/[^a-z0-9_\-]+/', '_', strtolower($session['title']));
    $filename = 'feedback_' . $safeName . '_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Calibri,Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
.title td{background:#1e1b4b;color:#fff;font-size:15pt;font-weight:bold;padding:14px 18px;}
.sub   td{background:#312e81;color:#c7d2fe;font-size:9.5pt;padding:6px 18px;}
.gap   td{height:12px;}
.hdr  th{background:#4f46e5;color:#fff;font-size:9.5pt;font-weight:bold;padding:8px 12px;border:1px solid #6366f1;}
.dr   td{border:1px solid #e2e8f0;padding:8px 12px;font-size:9pt;vertical-align:top;}
.dr:nth-child(even) td{background:#f8fafc;}
.reviewed{color:#059669;font-weight:bold;}
.unread  {color:#d97706;font-weight:bold;}
</style></head><body><table cellspacing="0" cellpadding="0">';

    echo '<tr class="title"><td colspan="4">📣 EduFlow — Anonymous Feedback Export</td></tr>';
    echo '<tr class="sub"><td colspan="4">' . htmlspecialchars($session['title']) . '  |  ' . htmlspecialchars($session['course_title']) . ' — ' . htmlspecialchars($session['batch_name']) . '  |  ' . count($entries) . ' entries  |  Exported: ' . date('M j, Y g:i A') . '</td></tr>';
    echo '<tr class="gap"><td colspan="4"></td></tr>';
    echo '<tr class="hdr"><th>#</th><th>Categories</th><th>Feedback</th><th>Submitted</th></tr>';

    foreach ($entries as $i => $e) {
        $catList  = json_decode($e['categories'] ?? '[]', true);
        $catNames = is_array($catList) ? implode(', ', array_map(fn($c) => ($cats[$c]['emoji'] ?? '') . ' ' . ($cats[$c]['label'] ?? $c), $catList)) : 'General';
        $time     = $e['submitted_at'] ? date('M j, Y g:i A', strtotime($e['submitted_at'])) : '—';
        $status   = $e['is_reviewed'] ? '<span class="reviewed">✓ Reviewed</span>' : '<span class="unread">● Unread</span>';
        echo '<tr class="dr">'
           . '<td style="text-align:center;width:40px;">' . ($i+1) . '</td>'
           . '<td style="white-space:nowrap;">' . htmlspecialchars($catNames) . '</td>'
           . '<td style="white-space:pre-wrap;max-width:500px;">' . htmlspecialchars($e['content']) . '</td>'
           . '<td style="white-space:nowrap;">' . $time . '</td>'
           . '</tr>';
    }

    if (!$entries) echo '<tr class="dr"><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No feedback entries yet.</td></tr>';
    echo '</table></body></html>';
    exit;
}

// ════════════════════════════════════════════════════════════
// TEACHER ACTIONS
// ════════════════════════════════════════════════════════════

function teacherSessions()
{
    global $conn, $me;
    $sessions = $conn->query("
        SELECT fs.*,
               b.name  AS batch_name, c.title AS course_title,
               (SELECT COUNT(*) FROM feedback_entries WHERE session_id=fs.id) AS entry_count,
               (SELECT COUNT(*) FROM feedback_entries WHERE session_id=fs.id AND is_reviewed=0) AS unread_count,
               (SELECT COUNT(*) FROM feedback_tokens  WHERE session_id=fs.id) AS submitter_count,
               (SELECT COUNT(*) FROM batch_students   WHERE batch_id=fs.batch_id) AS enrolled_count
        FROM feedback_sessions fs
        JOIN batches b ON fs.batch_id  = b.id
        JOIN courses c ON b.course_id  = c.id
        WHERE fs.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=$me)
        ORDER BY fs.is_active DESC, fs.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['sessions' => $sessions, 'categories' => getAllCategories()]);
}

// ════════════════════════════════════════════════════════════
// STUDENT ACTIONS
// ════════════════════════════════════════════════════════════

function studentBatches()
{
    global $conn, $me;
    $batches = $conn->query("
        SELECT b.id AS batch_id, b.name AS batch_name, c.title AS course_title,
               fs.id AS session_id, fs.title AS session_title,
               fs.description AS session_description,
               fs.is_active AS feedback_active, fs.activated_at
        FROM batch_students bs
        JOIN batches b  ON bs.batch_id  = b.id
        JOIN courses c  ON b.course_id  = c.id
        LEFT JOIN feedback_sessions fs ON fs.batch_id=b.id AND fs.is_active=1
        WHERE bs.student_id=$me
        ORDER BY fs.is_active DESC, c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($batches as &$row) {
        $row['already_submitted'] = false;
        if ($row['session_id'] && $row['feedback_active']) {
            $tok    = _makeToken((int)$row['session_id'], $me);
            $exists = $conn->query("SELECT id FROM feedback_tokens WHERE session_id={$row['session_id']} AND token_hash='$tok'")->fetch_assoc();
            $row['already_submitted'] = (bool)$exists;
        }
    }

    jsonResponse('success', 'OK', ['batches' => $batches, 'categories' => getAllCategories()]);
}

function checkSubmitted()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');
    $tok    = _makeToken($sessionId, $me);
    $exists = $conn->query("SELECT id FROM feedback_tokens WHERE session_id=$sessionId AND token_hash='$tok'")->fetch_assoc();
    jsonResponse('success', 'OK', ['submitted' => (bool)$exists]);
}

function submitFeedback()
{
    global $conn, $me;

    $sessionId  = (int)($_POST['session_id'] ?? 0);
    $content    = trim($_POST['content']     ?? '');
    $catsRaw    = $_POST['categories']       ?? '["general"]';

    if (!$sessionId)          jsonResponse('error', 'Session ID required');
    if (strlen($content) < 20) jsonResponse('error', 'Please write at least 20 characters.');

    // Validate + sanitize categories (allow only known keys)
    $validKeys   = array_keys(getAllCategories());
    $catsDecoded = json_decode($catsRaw, true);
    if (!is_array($catsDecoded) || empty($catsDecoded)) $catsDecoded = ['general'];
    $catsClean = array_values(array_filter($catsDecoded, fn($c) => in_array($c, $validKeys)));
    if (empty($catsClean)) $catsClean = ['general'];
    $catsJson = json_encode($catsClean);

    // Verify session is active and student is enrolled
    $session = $conn->query("SELECT id, batch_id, is_active FROM feedback_sessions WHERE id=$sessionId AND is_active=1")->fetch_assoc();
    if (!$session) jsonResponse('error', 'This feedback session is not currently active.');

    $enrolled = $conn->query("SELECT id FROM batch_students WHERE batch_id={$session['batch_id']} AND student_id=$me")->fetch_assoc();
    if (!$enrolled) jsonResponse('error', 'You are not enrolled in this batch.');

    // Duplicate check via anonymous token
    $tok     = _makeToken($sessionId, $me);
    $tokSafe = $conn->real_escape_string($tok);
    $exists  = $conn->query("SELECT id FROM feedback_tokens WHERE session_id=$sessionId AND token_hash='$tokSafe'")->fetch_assoc();
    if ($exists) jsonResponse('error', 'You have already submitted feedback for this session.');

    $conn->begin_transaction();
    try {
        $contentSafe  = $conn->real_escape_string($content);
        $catsSafe     = $conn->real_escape_string($catsJson);
        $batchId      = (int)$session['batch_id'];

        // Anonymous entry — NO student reference anywhere
        $conn->query("INSERT INTO feedback_entries (session_id, batch_id, content, categories) VALUES ($sessionId, $batchId, '$contentSafe', '$catsSafe')");
        if ($conn->error) throw new Exception($conn->error);

        // Duplicate-prevention token only (no student_id stored)
        $conn->query("INSERT INTO feedback_tokens (session_id, token_hash) VALUES ($sessionId, '$tokSafe')");
        if ($conn->error) throw new Exception($conn->error);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse('error', 'Submission failed. Please try again.');
    }

    // NO logActivity — would link student identity to submission timestamp
    jsonResponse('success', 'Thank you! Your anonymous feedback has been submitted.');
}

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

function _makeToken(int $sessionId, int $studentId): string
{
    return hash_hmac('sha256', "$sessionId|$studentId", PUSHER_SECRET . DB_PASS);
}

function requireRole(string $role)
{
    global $myRole;
    if ($myRole !== $role) jsonResponse('error', 'Access denied');
}

function requireAdminOrTeacher()
{
    global $myRole;
    if (!in_array($myRole, ['admin','teacher'])) jsonResponse('error', 'Access denied');
}