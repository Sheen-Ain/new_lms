<?php
// ============================================================
// APPLICATIONS AJAX
// Public:  get_open_batches, apply_for_batch, get_active_entry_test
// Admin:   list_applications, get_application, approve_application,
//          reject_application, bulk_approve, bulk_reject
// ============================================================
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ── Public (no auth required) ─────────────────────────
    case 'get_open_batches':      getOpenBatches();      break;
    case 'apply_for_batch':       applyForBatch();       break;
    case 'get_active_entry_test': getActiveEntryTest();  break;

    // ── Admin only ────────────────────────────────────────
    case 'list_applications':     listApplications();    break;
    case 'get_application':       getApplication();      break;
    case 'approve_application':   approveApplication();  break;
    case 'reject_application':    rejectApplication();   break;
    case 'bulk_approve':          bulkApprove();         break;
    case 'bulk_reject':           bulkReject();          break;
    case 'export_applications':   exportApplications();  break;
    case 'get_eligible_students': getEligibleStudents(); break;
    case 'admin_enroll_student':  adminEnrollStudent();  break;

    default: jsonResponse('error', 'Unknown action');
}

// ════════════════════════════════════════════════════════════
// PUBLIC ENDPOINTS
// ════════════════════════════════════════════════════════════

function getOpenBatches()
{
    global $conn;
    $batches = $conn->query("
        SELECT b.id, b.name, c.title AS course_title, b.start_date, b.end_date
        FROM batches b
        JOIN courses c ON b.course_id = c.id
        WHERE b.status = 'active'
        ORDER BY c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['batches' => $batches]);
}

function applyForBatch()
{
    global $conn;

    $identifier = trim($_POST['identifier'] ?? '');
    $cnic       = trim($_POST['cnic'] ?? '');
    $batchId    = (int)($_POST['batch_id'] ?? 0);

    if (!$identifier || !$cnic || !$batchId) {
        jsonResponse('error', 'All fields are required.');
    }

    // Normalise CNIC
    $cnicClean = preg_replace('/[^0-9]/', '', $cnic);
    if (strlen($cnicClean) !== 13) {
        jsonResponse('error', 'CNIC must be 13 digits (e.g. 42301-1234567-8).');
    }
    $cnicFormatted = substr($cnicClean, 0, 5) . '-' . substr($cnicClean, 5, 7) . '-' . substr($cnicClean, 12, 1);

    // Locate user
    $isStudentId = (bool)preg_match('/^\d{5}$/', $identifier);
    $field       = $isStudentId ? 'user_id_number' : 'email';
    $stmt        = $conn->prepare("SELECT id, full_name, cnic, is_verified, status FROM users WHERE $field = ? LIMIT 1");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user)                        jsonResponse('error', 'No account found. Please register first.');
    if (!$user['is_verified'])         jsonResponse('error', 'Please verify your email address before applying.');
    if ($user['status'] !== 'active')  jsonResponse('error', 'Your account is inactive. Contact support.');
    if ($user['cnic'] !== $cnicFormatted) jsonResponse('error', 'CNIC does not match our records.');

    // Validate batch
    $batch = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM batches b JOIN courses c ON b.course_id = c.id
        WHERE b.id = $batchId AND b.status = 'active'
    ")->fetch_assoc();
    if (!$batch) jsonResponse('error', 'Selected batch is not available.');

    // Check duplicate
    $existing = $conn->query("
        SELECT id, status FROM course_applications
        WHERE user_id = {$user['id']} AND batch_id = $batchId
    ")->fetch_assoc();

    if ($existing) {
        $labels = [
            'pending'        => 'pending review',
            'test_submitted' => 'under review (test submitted)',
            'approved'       => 'approved — you can log in!',
            'rejected'       => 'rejected for this batch',
        ];
        jsonResponse('error', 'You already applied for this batch. Status: ' . ($labels[$existing['status']] ?? $existing['status']) . '.');
    }

    $stmt = $conn->prepare("INSERT INTO course_applications (user_id, batch_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param('ii', $user['id'], $batchId);
    if (!$stmt->execute()) jsonResponse('error', 'Failed to submit application. Please try again.');
    $stmt->close();

    logActivity($conn, $user['id'], "Applied for batch: {$batch['name']} ({$batch['course_title']})", 'applications');

    jsonResponse('success', 'Application submitted successfully!', [
        'course' => $batch['course_title'],
        'batch'  => $batch['name'],
        'name'   => $user['full_name'],
    ]);
}

function getActiveEntryTest()
{
    global $conn;

    $test = $conn->query("
        SELECT t.id, t.title, t.description, t.time_minutes,
               b.name AS batch_name, c.title AS course_title,
               (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count
        FROM tests t
        JOIN batches b ON t.batch_id  = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE t.type = 'entry' AND t.entry_active = 1 AND t.status = 'active'
        LIMIT 1
    ")->fetch_assoc();

    if (!$test) {
        jsonResponse('success', 'none', ['active' => false]);
    }

    $batches = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM tests t
        JOIN batches b ON t.batch_id  = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE t.type = 'entry' AND t.entry_active = 1 AND t.status = 'active'
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'active', [
        'active'  => true,
        'test'    => $test,
        'batches' => $batches,
    ]);
}

// ════════════════════════════════════════════════════════════
// ADMIN ENDPOINTS
// ════════════════════════════════════════════════════════════

function requireAdmin()
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        jsonResponse('error', 'Unauthorized');
    }
}

// ── LIST APPLICATIONS (paginated, filterable) ────────────────
function listApplications()
{
    global $conn;
    requireAdmin();

    $page    = max(1, (int)($_POST['page']     ?? 1));
    $perPage = min(100, (int)($_POST['per_page'] ?? 15));
    $search  = trim($_POST['search']   ?? '');
    $status  = trim($_POST['status']   ?? '');
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];
    $types  = '';

    if ($search) {
        $s        = '%' . $search . '%';
        $where[]  = "(u.full_name LIKE ? OR u.user_id_number LIKE ? OR u.email LIKE ?)";
        $params[] = $s; $params[] = $s; $params[] = $s;
        $types   .= 'sss';
    }
    if ($status) {
        $where[]  = "ca.status = ?";
        $params[] = $status;
        $types   .= 's';
    }
    if ($batchId) {
        $where[]  = "ca.batch_id = ?";
        $params[] = $batchId;
        $types   .= 'i';
    }

    $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $cs = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM course_applications ca
        JOIN users u ON ca.user_id = u.id
        $wSQL
    ");
    if ($types) $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
    $cs->close();

    // Main — pull the most-recent entry test attempt for this student+batch
    $sql = "
        SELECT
            ca.id, ca.status, ca.rejection_reason, ca.applied_at, ca.reviewed_at,
            u.id AS user_id, u.full_name, u.email, u.user_id_number, u.cnic,
            b.id AS batch_id, b.name AS batch_name,
            c.title AS course_title,
            rv.full_name AS reviewer_name,
            ta.id AS attempt_id, ta.score, ta.percentage,
            ta.correct_count, ta.wrong_count, ta.unanswered_count,
            ta.total_questions, ta.submitted_at AS test_submitted_at,
            ta.status AS attempt_status
        FROM course_applications ca
        JOIN users   u  ON ca.user_id   = u.id
        JOIN batches b  ON ca.batch_id  = b.id
        JOIN courses c  ON b.course_id  = c.id
        LEFT JOIN users rv ON ca.reviewed_by = rv.id
        LEFT JOIN test_attempts ta ON ta.id = (
            SELECT ta2.id FROM test_attempts ta2
            JOIN tests t2 ON ta2.test_id = t2.id
            WHERE ta2.student_id = ca.user_id
              AND t2.batch_id    = ca.batch_id
              AND t2.type        = 'entry'
            ORDER BY ta2.started_at DESC
            LIMIT 1
        )
        $wSQL
        ORDER BY
            FIELD(ca.status, 'test_submitted', 'pending', 'approved', 'rejected'),
            ca.applied_at DESC
        LIMIT ? OFFSET ?
    ";
    $allT = $types . 'ii';
    $allP = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($allT, ...$allP);
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Stats
    $stats = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending')        AS pending,
            SUM(status = 'test_submitted') AS test_submitted,
            SUM(status = 'approved')       AS approved,
            SUM(status = 'rejected')       AS rejected
        FROM course_applications
    ")->fetch_assoc();

    // Batches for filter dropdown
    $batches = $conn->query("
        SELECT DISTINCT b.id, b.name, c.title AS course_title
        FROM course_applications ca
        JOIN batches b ON ca.batch_id  = b.id
        JOIN courses c ON b.course_id  = c.id
        ORDER BY c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', [
        'applications' => $apps,
        'total'        => $total,
        'page'         => $page,
        'per_page'     => $perPage,
        'stats'        => $stats,
        'batches'      => $batches,
    ]);
}

// ── GET SINGLE APPLICATION (for detail modal) ────────────────
function getApplication()
{
    global $conn;
    requireAdmin();

    $id = (int)($_POST['application_id'] ?? $_GET['application_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');

    $stmt = $conn->prepare("
        SELECT
            ca.*,
            u.full_name, u.email, u.user_id_number, u.cnic, u.gender, u.status AS user_status,
            b.name AS batch_name,
            c.title AS course_title,
            rv.full_name AS reviewer_name,
            ta.id AS attempt_id, ta.score, ta.percentage,
            ta.correct_count, ta.wrong_count, ta.unanswered_count,
            ta.total_questions, ta.submitted_at AS test_submitted_at
        FROM course_applications ca
        JOIN users   u  ON ca.user_id   = u.id
        JOIN batches b  ON ca.batch_id  = b.id
        JOIN courses c  ON b.course_id  = c.id
        LEFT JOIN users rv ON ca.reviewed_by = rv.id
        LEFT JOIN test_attempts ta ON ta.id = (
            SELECT ta2.id FROM test_attempts ta2
            JOIN tests t2 ON ta2.test_id = t2.id
            WHERE ta2.student_id = ca.user_id
              AND t2.batch_id    = ca.batch_id
              AND t2.type        = 'entry'
            ORDER BY ta2.started_at DESC LIMIT 1
        )
        WHERE ca.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) jsonResponse('error', 'Application not found');
    jsonResponse('success', 'OK', $app);
}

// ── APPROVE ──────────────────────────────────────────────────
function approveApplication()
{
    global $conn;
    requireAdmin();
    require_once __DIR__ . '/../includes/mailer.php';

    $appId = (int)($_POST['application_id'] ?? 0);
    if (!$appId) jsonResponse('error', 'Invalid application ID');

    $app = _fetchApp($conn, $appId);
    if (!$app) jsonResponse('error', 'Application not found');
    if ($app['status'] === 'approved') jsonResponse('error', 'Already approved');

    $adminId = (int)$_SESSION['user_id'];
    $stmt    = $conn->prepare("
        UPDATE course_applications
        SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ii', $adminId, $appId);
    $stmt->execute();
    $stmt->close();

    // Activate user account if still inactive
    $conn->query("UPDATE users SET status='active' WHERE id={$app['user_id']} AND status='inactive'");

    // Enroll student into the batch (INSERT IGNORE so it's idempotent)
    $conn->query("INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES ({$app['batch_id']}, {$app['user_id']}, $adminId)");

    mailApplicationApproved($app['email'], $app['full_name'], $app['course_title']);
    logActivity($conn, $adminId, "Approved application #{$appId} — {$app['full_name']} → {$app['course_title']}", 'applications');

    jsonResponse('success', 'Application approved and student notified by email.');
}

// ── REJECT ───────────────────────────────────────────────────
function rejectApplication()
{
    global $conn;
    requireAdmin();
    require_once __DIR__ . '/../includes/mailer.php';

    $appId  = (int)($_POST['application_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$appId) jsonResponse('error', 'Invalid application ID');

    $app = _fetchApp($conn, $appId);
    if (!$app) jsonResponse('error', 'Application not found');
    if ($app['status'] === 'rejected') jsonResponse('error', 'Already rejected');

    $adminId = (int)$_SESSION['user_id'];
    $stmt    = $conn->prepare("
        UPDATE course_applications
        SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param('sii', $reason, $adminId, $appId);
    $stmt->execute();
    $stmt->close();

    mailApplicationRejected($app['email'], $app['full_name'], $app['course_title'], $reason);
    logActivity($conn, $adminId, "Rejected application #{$appId} — {$app['full_name']} → {$app['course_title']}", 'applications');

    jsonResponse('success', 'Application rejected and student notified by email.');
}

// ── BULK APPROVE ─────────────────────────────────────────────
function bulkApprove()
{
    global $conn;
    requireAdmin();
    require_once __DIR__ . '/../includes/mailer.php';

    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (empty($ids)) jsonResponse('error', 'No applications selected');

    $adminId = (int)$_SESSION['user_id'];
    $approved = 0;

    foreach ($ids as $appId) {
        $app = _fetchApp($conn, $appId);
        if (!$app || $app['status'] === 'approved') continue;

        $stmt = $conn->prepare("
            UPDATE course_applications
            SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $adminId, $appId);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE users SET status='active' WHERE id={$app['user_id']} AND status='inactive'");
        $conn->query("INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES ({$app['batch_id']}, {$app['user_id']}, $adminId)");
        mailApplicationApproved($app['email'], $app['full_name'], $app['course_title']);
        $approved++;
    }

    logActivity($conn, $adminId, "Bulk approved $approved applications", 'applications');
    jsonResponse('success', $approved . ' application(s) approved and students notified.');
}

// ── BULK REJECT ──────────────────────────────────────────────
function bulkReject()
{
    global $conn;
    requireAdmin();
    require_once __DIR__ . '/../includes/mailer.php';

    $ids    = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    $reason = trim($_POST['reason'] ?? '');
    if (empty($ids)) jsonResponse('error', 'No applications selected');

    $adminId = (int)$_SESSION['user_id'];
    $rejected = 0;

    foreach ($ids as $appId) {
        $app = _fetchApp($conn, $appId);
        if (!$app || $app['status'] === 'rejected') continue;

        $stmt = $conn->prepare("
            UPDATE course_applications
            SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sii', $reason, $adminId, $appId);
        $stmt->execute();
        $stmt->close();

        mailApplicationRejected($app['email'], $app['full_name'], $app['course_title'], $reason);
        $rejected++;
    }

    logActivity($conn, $adminId, "Bulk rejected $rejected applications", 'applications');
    jsonResponse('success', $rejected . ' application(s) rejected and students notified.');
}

// ── Shared: fetch application with user+course info ──────────
function _fetchApp($conn, $appId)
{
    $stmt = $conn->prepare("
        SELECT ca.id, ca.status, ca.user_id, ca.batch_id,
               u.full_name, u.email,
               b.name AS batch_name,
               c.title AS course_title
        FROM course_applications ca
        JOIN users   u ON ca.user_id  = u.id
        JOIN batches b ON ca.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE ca.id = ?
    ");
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

// ── EXPORT APPLICATIONS TO EXCEL ────────────────────────────
function exportApplications()
{
    global $conn;
    requireAdmin();
    if (ob_get_level() > 0) ob_end_clean();

    $status  = trim($_GET['status']   ?? $_POST['status']   ?? '');
    $batchId = (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0);
    $search  = trim($_GET['search']   ?? $_POST['search']   ?? '');

    $where  = [];
    $params = [];
    $types  = '';

    if ($search) {
        $s = '%' . $search . '%';
        $where[]  = "(u.full_name LIKE ? OR u.user_id_number LIKE ? OR u.email LIKE ? OR u.cnic LIKE ?)";
        $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        $types   .= 'ssss';
    }
    if ($status) { $where[] = "ca.status = ?"; $params[] = $status; $types .= 's'; }
    if ($batchId){ $where[] = "ca.batch_id = ?"; $params[] = $batchId; $types .= 'i'; }

    $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            ca.id AS app_id, ca.status, ca.rejection_reason, ca.applied_at, ca.reviewed_at,
            u.full_name, u.email, u.user_id_number, u.cnic, u.gender, u.phone,
            b.name AS batch_name, c.title AS course_title,
            rv.full_name AS reviewer_name,
            ta.score, ta.percentage, ta.correct_count, ta.wrong_count,
            ta.unanswered_count, ta.total_questions, ta.submitted_at AS test_submitted_at,
            ta.status AS attempt_status
        FROM course_applications ca
        JOIN users   u  ON ca.user_id   = u.id
        JOIN batches b  ON ca.batch_id  = b.id
        JOIN courses c  ON b.course_id  = c.id
        LEFT JOIN users rv ON ca.reviewed_by = rv.id
        LEFT JOIN test_attempts ta ON ta.id = (
            SELECT ta2.id FROM test_attempts ta2
            JOIN tests t2 ON ta2.test_id = t2.id
            WHERE ta2.student_id = ca.user_id AND t2.batch_id = ca.batch_id AND t2.type = 'entry'
            ORDER BY ta2.started_at DESC LIMIT 1
        )
        $wSQL
        ORDER BY FIELD(ca.status,'test_submitted','pending','approved','rejected'), ca.applied_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $filename = 'applications_' . date('Y-m-d') . ($status ? '_' . $status : '') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    $statusLabels = [
        'pending'        => 'Pending',
        'test_submitted' => 'Test Submitted',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
    ];
    $bandMap = fn($p) => $p>=90?'Superb':($p>=75?'Excellent':($p>=60?'Good':($p>=40?'Average':'Weak')));
    $bandColor = fn($p) => $p>=90?'#059669':($p>=75?'#4f46e5':($p>=60?'#0891b2':($p>=40?'#d97706':'#dc2626')));

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Calibri,Arial,sans-serif;font-size:9.5pt;}
table{border-collapse:collapse;width:100%;}
.title td{background:#1e1b4b;color:#fff;font-size:14pt;font-weight:bold;padding:12px 16px;}
.sub   td{background:#312e81;color:#c7d2fe;font-size:9pt;padding:5px 16px;}
.gap   td{height:10px;}
.hdr  th{background:#4f46e5;color:#fff;font-size:8.5pt;font-weight:bold;padding:7px 10px;border:1px solid #6366f1;text-align:left;white-space:nowrap;}
.dr   td{border:1px solid #e2e8f0;padding:6px 10px;font-size:8.5pt;vertical-align:middle;}
.dr:nth-child(even) td{background:#f8fafc;}
.status-pending  {color:#d97706;font-weight:bold;}
.status-submitted{color:#4f46e5;font-weight:bold;}
.status-approved {color:#059669;font-weight:bold;}
.status-rejected {color:#dc2626;font-weight:bold;}
</style></head><body><table cellspacing="0" cellpadding="0">';

    echo '<tr class="title"><td colspan="17">EduFlow LMS — Applications Export</td></tr>';
    echo '<tr class="sub"><td colspan="17">'
        . 'Total: ' . count($apps) . ' applications'
        . ($status ? '  |  Status: ' . ($statusLabels[$status] ?? $status) : '')
        . '  |  Exported: ' . date('M j, Y  g:i A')
        . '</td></tr>';
    echo '<tr class="gap"><td colspan="17"></td></tr>';
    echo '<tr class="hdr">'
        . '<th>#</th>'
        . '<th>Student ID</th>'
        . '<th>Full Name</th>'
        . '<th>Email</th>'
        . '<th>CNIC</th>'
        . '<th>Gender</th>'
        . '<th>Phone</th>'
        . '<th>Course</th>'
        . '<th>Batch</th>'
        . '<th>App Status</th>'
        . '<th>Applied On</th>'
        . '<th>Test Score</th>'
        . '<th>Test %</th>'
        . '<th>Correct</th>'
        . '<th>Wrong</th>'
        . '<th>Test Submitted</th>'
        . '<th>Reviewed By</th>'
        . '</tr>';

    foreach ($apps as $i => $a) {
        $statusCls = 'status-' . str_replace('_', '', $a['status']);
        $appliedAt = $a['applied_at'] ? date('M j, Y g:i A', strtotime($a['applied_at'])) : '—';
        $testSubAt = $a['test_submitted_at'] ? date('M j, Y g:i A', strtotime($a['test_submitted_at'])) : '—';
        $reviewAt  = $a['reviewed_at'] ? date('M j, Y g:i A', strtotime($a['reviewed_at'])) : '—';
        $pct = $a['percentage'] !== null ? (float)$a['percentage'] : null;
        $pctStr   = $pct !== null ? number_format($pct, 1) . '% (' . $bandMap($pct) . ')' : '—';
        $pctColor = $pct !== null ? $bandColor($pct) : '#94a3b8';
        $scoreStr = $a['score'] !== null ? number_format((float)$a['score'], 2) : '—';
        $correctStr = $a['correct_count'] ?? '—';
        $wrongStr   = $a['wrong_count']   ?? '—';
        $statusText = $statusLabels[$a['status']] ?? $a['status'];

        echo '<tr class="dr">'
            . '<td style="text-align:center;">' . ($i+1) . '</td>'
            . '<td style="font-weight:bold;">'  . htmlspecialchars($a['user_id_number'] ?? '—') . '</td>'
            . '<td>'                            . htmlspecialchars($a['full_name'])  . '</td>'
            . '<td>'                            . htmlspecialchars($a['email'])      . '</td>'
            . '<td style="font-family:Courier New,monospace;">' . htmlspecialchars($a['cnic'] ?? '—') . '</td>'
            . '<td style="text-transform:capitalize;">' . htmlspecialchars($a['gender'] ?? '—') . '</td>'
            . '<td>'                            . htmlspecialchars($a['phone'] ?? '—') . '</td>'
            . '<td>'                            . htmlspecialchars($a['course_title'])  . '</td>'
            . '<td>'                            . htmlspecialchars($a['batch_name'])   . '</td>'
            . '<td class="' . $statusCls . '">' . $statusText . '</td>'
            . '<td style="white-space:nowrap;">'  . $appliedAt  . '</td>'
            . '<td style="text-align:center;font-weight:bold;">' . $scoreStr . '</td>'
            . '<td style="text-align:center;font-weight:bold;color:' . $pctColor . ';">' . $pctStr . '</td>'
            . '<td style="text-align:center;color:#059669;font-weight:bold;">' . $correctStr . '</td>'
            . '<td style="text-align:center;color:#dc2626;font-weight:bold;">' . $wrongStr   . '</td>'
            . '<td style="white-space:nowrap;">'  . $testSubAt  . '</td>'
            . '<td>'                              . htmlspecialchars($a['reviewer_name'] ?? '—') . '</td>'
            . '</tr>';

        if ($a['status'] === 'rejected' && $a['rejection_reason']) {
            echo '<tr class="dr" style="background:#fff8f8!important;">'
                . '<td colspan="17" style="color:#dc2626;font-style:italic;padding-left:40px;">'
                . 'Rejection reason: ' . htmlspecialchars($a['rejection_reason'])
                . '</td></tr>';
        }
    }

    if (!$apps) echo '<tr class="dr"><td colspan="17" style="text-align:center;color:#94a3b8;padding:20px;">No applications found.</td></tr>';
    echo '</table></body></html>';
    exit;
}

// ── GET ELIGIBLE STUDENTS (no pending/approved app for batch) ─
function getEligibleStudents()
{
    global $conn;
    requireAdmin();

    $batchId = (int)($_POST['batch_id'] ?? 0);
    $search  = trim($_POST['search']   ?? '');

    // Active batches always returned for dropdown
    $batches = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM batches b
        JOIN courses c ON b.course_id = c.id
        WHERE b.status = 'active'
        ORDER BY c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $searchSQL = '';
    if ($search) {
        $s = $conn->real_escape_string($search);
        $searchSQL = "AND (u.full_name LIKE '%$s%' OR u.user_id_number LIKE '%$s%' OR u.email LIKE '%$s%' OR u.cnic LIKE '%$s%')";
    }

    // Exclude students who already have a pending/approved app for this batch
    $excludeSQL = '';
    if ($batchId) {
        $excludeSQL = "AND u.id NOT IN (
            SELECT user_id FROM course_applications
            WHERE batch_id = $batchId AND status IN ('pending','test_submitted','approved')
        )";
    }

    $students = $conn->query("
        SELECT u.id, u.full_name, u.email, u.user_id_number, u.cnic, u.gender,
               u.phone, u.status AS user_status, u.bypass_gate
        FROM users u
        WHERE u.id IN (
            SELECT user_id FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id WHERE r.name = 'student'
        )
        $searchSQL
        $excludeSQL
        ORDER BY u.full_name ASC
        LIMIT 150
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', [
        'students' => $students,
        'batches'  => $batches,
    ]);
}

// ── ADMIN ENROLL STUDENT (create approved application) ────────
function adminEnrollStudent()
{
    global $conn;
    requireAdmin();
    require_once __DIR__ . '/../includes/mailer.php';

    $studentId = (int)($_POST['student_id'] ?? 0);
    $batchId   = (int)($_POST['batch_id']   ?? 0);
    if (!$studentId || !$batchId) jsonResponse('error', 'Student and batch are required.');

    $student = $conn->query("SELECT id, full_name, email FROM users WHERE id=$studentId")->fetch_assoc();
    if (!$student) jsonResponse('error', 'Student not found.');

    $batch = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM batches b JOIN courses c ON b.course_id=c.id
        WHERE b.id=$batchId AND b.status='active'
    ")->fetch_assoc();
    if (!$batch) jsonResponse('error', 'Batch not found or not active.');

    $adminId = (int)$_SESSION['user_id'];

    // Upsert application to approved
    $existing = $conn->query("SELECT id, status FROM course_applications WHERE user_id=$studentId AND batch_id=$batchId")->fetch_assoc();
    if ($existing) {
        if ($existing['status'] === 'approved') {
            jsonResponse('error', 'This student is already enrolled in that batch.');
        }
        $conn->query("UPDATE course_applications SET status='approved', reviewed_at=NOW(), reviewed_by=$adminId WHERE id={$existing['id']}");
    } else {
        $stmt = $conn->prepare("INSERT INTO course_applications (user_id,batch_id,status,reviewed_at,reviewed_by) VALUES (?,?,'approved',NOW(),?)");
        $stmt->bind_param('iii', $studentId, $batchId, $adminId);
        if (!$stmt->execute()) jsonResponse('error', 'Failed to create application: ' . $conn->error);
        $stmt->close();
    }

    // Activate + verify account
    $conn->query("UPDATE users SET status='active', is_verified=1 WHERE id=$studentId");

    // Enroll in batch
    $conn->query("INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES ($batchId, $studentId, $adminId)");

    // Notify student
    mailApplicationApproved($student['email'], $student['full_name'], $batch['course_title']);

    logActivity($conn, $adminId, "Admin enrolled {$student['full_name']} → {$batch['course_title']} ({$batch['name']})", 'applications');
    jsonResponse('success', $student['full_name'] . ' has been enrolled in ' . $batch['name'] . ' and notified by email.');
}