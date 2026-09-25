<?php
// ============================================================
// LIVE SESSIONS AJAX
// ============================================================
// Actions (teacher / admin):
//   start            → create session in 'waiting' state
//   open             → move waiting → active (teacher starts class)
//   end              → end a session
//   get_waiting_list → students waiting (for teacher sidebar panel)
// Actions (student):
//   knock            → register in waiting room
//   get_session_status → poll: waiting | active | ended
//   join             → log join attendance
//   leave            → log leave attendance
// Actions (shared):
//   get_active       → sessions visible to this user
//   get_participants → live count + session status poll
// Actions (admin only):
//   admin_list       → paginated history of all sessions
//   admin_delete     → delete a session record
//   admin_end        → force-end any active session

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pusher.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// One-time migration: add 'waiting' status if not already in ENUM
@$conn->query("ALTER TABLE `live_sessions` MODIFY COLUMN `status` ENUM('waiting','active','ended') DEFAULT 'waiting'");

switch ($action) {
    case 'start':
        startSession();
        break;
    case 'open':
        openSession();
        break;
    case 'end':
        endSession();
        break;
    case 'get_waiting_list':
        getWaitingList();
        break;
    case 'knock':
        knockSession();
        break;
    case 'get_session_status':
        getSessionStatus();
        break;
    case 'join':
        joinSession();
        break;
    case 'leave':
        leaveSession();
        break;
    case 'get_active':
        getActiveSessions();
        break;
    case 'get_participants':
        getParticipants();
        break;
    case 'get_one':
        getOneSession();
        break;
    case 'admin_list':
        adminList();
        break;
    case 'admin_delete':
        adminDelete();
        break;
    case 'admin_end':
        adminEnd();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}

// ════════════════════════════════════════════════════════════
// TEACHER / ADMIN ACTIONS
// ════════════════════════════════════════════════════════════

function startSession()
{
    global $conn, $me, $myRole;

    if (!in_array($myRole, ['teacher', 'admin']))
        jsonResponse('error', 'Only teachers or admins can start sessions');

    $batchId = (int)($_POST['batch_id'] ?? 0);
    $title   = trim($_POST['title'] ?? '') ?: 'Live Class';

    if (!$batchId) jsonResponse('error', 'Batch ID is required');

    if ($myRole === 'teacher') {
        $s = $conn->prepare("SELECT id FROM batch_teachers WHERE batch_id=? AND teacher_id=?");
        $s->bind_param('ii', $batchId, $me);
        $s->execute();
        if (!$s->get_result()->num_rows) jsonResponse('error', 'You are not assigned to this batch');
        $s->close();
    }

    // Block if a waiting or active session already exists for this batch
    $s = $conn->prepare("SELECT id, status FROM live_sessions WHERE batch_id=? AND status IN ('waiting','active') LIMIT 1");
    $s->bind_param('i', $batchId);
    $s->execute();
    $existing = $s->get_result()->fetch_assoc();
    $s->close();
    if ($existing) jsonResponse('error', 'A session is already running for this batch', ['session_id' => $existing['id']]);

    // Room name = AppID/random — required format for JaaS
    $appId    = defined('JAAS_APP_ID') ? JAAS_APP_ID : '';
    $suffix   = bin2hex(random_bytes(10));
    $roomName = $appId ? ($appId . '/' . $suffix) : ('lms-' . $suffix);

    $status = 'waiting';
    $s = $conn->prepare("INSERT INTO live_sessions (batch_id, room_name, title, started_by, status) VALUES (?,?,?,?,?)");
    $s->bind_param('issss', $batchId, $roomName, $title, $me, $status);
    $s->execute();
    $sessionId = (int)$conn->insert_id;
    $s->close();

    // Batch + teacher info
    $s = $conn->prepare("SELECT b.name AS batch_name, c.title AS course_title FROM batches b JOIN courses c ON b.course_id=c.id WHERE b.id=?");
    $s->bind_param('i', $batchId);
    $s->execute();
    $batchInfo = $s->get_result()->fetch_assoc();
    $s->close();

    $s = $conn->prepare("SELECT full_name FROM users WHERE id=?");
    $s->bind_param('i', $me);
    $s->execute();
    $teacher = $s->get_result()->fetch_assoc();
    $s->close();

    // Notify students via Pusher
    $s = $conn->prepare("SELECT student_id FROM batch_students WHERE batch_id=?");
    $s->bind_param('i', $batchId);
    $s->execute();
    $students = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($students as $st) {
        pusherTrigger('private-user-' . $st['student_id'], 'live-started', [
            'session_id'   => $sessionId,
            'batch_id'     => $batchId,
            'batch_name'   => $batchInfo['batch_name']   ?? '',
            'course_title' => $batchInfo['course_title'] ?? '',
            'teacher_name' => $teacher['full_name']       ?? '',
            'title'        => $title,
            'status'       => 'waiting',
        ]);
    }

    logActivity($conn, $me, "Started live session: {$title} (Batch: {$batchInfo['batch_name']})", 'live');

    jsonResponse('success', 'Session created', [
        'session_id' => $sessionId,
        'room_name'  => $roomName,
        'status'     => 'waiting',
    ]);
}

function openSession()
{
    global $conn, $me, $myRole;

    if (!in_array($myRole, ['teacher', 'admin']))
        jsonResponse('error', 'Access denied');

    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    if ($myRole === 'teacher') {
        $s = $conn->prepare("SELECT id, batch_id FROM live_sessions WHERE id=? AND started_by=? AND status='waiting'");
        $s->bind_param('ii', $sessionId, $me);
    } else {
        $s = $conn->prepare("SELECT id, batch_id FROM live_sessions WHERE id=? AND status='waiting'");
        $s->bind_param('i', $sessionId);
    }
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$session) jsonResponse('error', 'Session not found or not in waiting state');

    $s = $conn->prepare("UPDATE live_sessions SET status='active', started_at=NOW() WHERE id=?");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $s->close();

    // Notify students
    $s = $conn->prepare("SELECT student_id FROM batch_students WHERE batch_id=?");
    $s->bind_param('i', $session['batch_id']);
    $s->execute();
    $students = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($students as $st) {
        pusherTrigger('private-user-' . $st['student_id'], 'live-opened', ['session_id' => $sessionId]);
    }

    logActivity($conn, $me, "Opened live session ID {$sessionId}", 'live');
    jsonResponse('success', 'Class is now live');
}

function endSession()
{
    global $conn, $me, $myRole;

    if (!in_array($myRole, ['teacher', 'admin']))
        jsonResponse('error', 'Access denied');

    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    if ($myRole === 'teacher') {
        $s = $conn->prepare("SELECT id, batch_id, title FROM live_sessions WHERE id=? AND started_by=? AND status IN ('waiting','active')");
        $s->bind_param('ii', $sessionId, $me);
    } else {
        $s = $conn->prepare("SELECT id, batch_id, title FROM live_sessions WHERE id=? AND status IN ('waiting','active')");
        $s->bind_param('i', $sessionId);
    }
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$session) jsonResponse('error', 'Session not found or already ended');

    $s = $conn->prepare("UPDATE live_sessions SET status='ended', ended_at=NOW() WHERE id=?");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $s->close();

    $conn->query("UPDATE session_attendees SET left_at=NOW() WHERE session_id={$sessionId} AND left_at IS NULL");

    $s = $conn->prepare("SELECT student_id FROM batch_students WHERE batch_id=?");
    $s->bind_param('i', $session['batch_id']);
    $s->execute();
    $students = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($students as $st) {
        pusherTrigger('private-user-' . $st['student_id'], 'live-ended', [
            'session_id' => $sessionId,
            'batch_id'   => $session['batch_id'],
        ]);
    }

    logActivity($conn, $me, "Ended live session: {$session['title']}", 'live');
    jsonResponse('success', 'Session ended');
}

function getWaitingList()
{
    global $conn, $me, $myRole;

    if (!in_array($myRole, ['teacher', 'admin']))
        jsonResponse('error', 'Access denied');

    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("
        SELECT sa.user_id, UNIX_TIMESTAMP(sa.joined_at) AS joined_ts,
               u.full_name, u.profile_picture, u.gender
        FROM session_attendees sa
        JOIN users u ON sa.user_id = u.id
        WHERE sa.session_id=? AND sa.left_at IS NULL
        ORDER BY sa.joined_at ASC
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $list = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    $s = $conn->prepare("
        SELECT ls.status, ls.title,
               (SELECT COUNT(*) FROM batch_students WHERE batch_id=ls.batch_id) AS total_students
        FROM live_sessions ls WHERE ls.id=?
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $meta = $s->get_result()->fetch_assoc();
    $s->close();

    jsonResponse('success', 'OK', [
        'waiting'        => $list,
        'waiting_count'  => count($list),
        'total_students' => (int)($meta['total_students'] ?? 0),
        'session_status' => $meta['status'] ?? 'unknown',
    ]);
}

// ════════════════════════════════════════════════════════════
// STUDENT ACTIONS
// ════════════════════════════════════════════════════════════

function knockSession()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("SELECT id, status, started_by FROM live_sessions WHERE id=? AND status IN ('waiting','active')");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) jsonResponse('error', 'Session not found');

    $s = $conn->prepare("INSERT INTO session_attendees (session_id, user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE joined_at=NOW(), left_at=NULL");
    $s->bind_param('ii', $sessionId, $me);
    $s->execute();
    $s->close();

    // Get current waiting count and notify teacher via Pusher (replaces teacher-side polling)
    $wc = $conn->query("SELECT COUNT(*) AS cnt FROM session_attendees WHERE session_id=$sessionId AND left_at IS NULL")->fetch_assoc();
    pusherTrigger('private-user-' . $row['started_by'], 'waiting-update', [
        'session_id'    => $sessionId,
        'waiting_count' => (int)($wc['cnt'] ?? 0),
    ]);

    jsonResponse('success', 'OK', ['session_status' => $row['status']]);
}

function getSessionStatus()
{
    global $conn;
    $sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("
        SELECT ls.id, ls.status, ls.title, ls.room_name,
               TIMESTAMPDIFF(SECOND, ls.started_at, COALESCE(ls.ended_at,NOW())) AS duration_seconds,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id AND left_at IS NULL) AS live_count,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id) AS total_joined,
               u.full_name AS teacher_name
        FROM live_sessions ls
        JOIN users u ON ls.started_by=u.id
        WHERE ls.id=?
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $data = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$data) jsonResponse('error', 'Session not found');
    jsonResponse('success', 'OK', $data);
}

// ════════════════════════════════════════════════════════════
// SHARED ACTIONS
// ════════════════════════════════════════════════════════════

function joinSession()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("SELECT id FROM live_sessions WHERE id=? AND status='active'");
    $s->bind_param('i', $sessionId);
    $s->execute();
    if (!$s->get_result()->num_rows) jsonResponse('error', 'Session not active');
    $s->close();

    $s = $conn->prepare("INSERT INTO session_attendees (session_id, user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE joined_at=NOW(), left_at=NULL");
    $s->bind_param('ii', $sessionId, $me);
    $s->execute();
    $s->close();
    jsonResponse('success', 'Joined');
}

function leaveSession()
{
    global $conn, $me;
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("UPDATE session_attendees SET left_at=NOW() WHERE session_id=? AND user_id=? AND left_at IS NULL");
    $s->bind_param('ii', $sessionId, $me);
    $s->execute();
    $s->close();
    jsonResponse('success', 'Left');
}

function getActiveSessions()
{
    global $conn, $me, $myRole;

    if ($myRole === 'student') {
        $s = $conn->prepare("
            SELECT ls.id, ls.batch_id, ls.title, ls.started_at, ls.status,
                   b.name AS batch_name, c.title AS course_title,
                   u.full_name AS teacher_name,
                   TIMESTAMPDIFF(MINUTE, ls.started_at, NOW()) AS duration_minutes
            FROM live_sessions ls
            JOIN batches b ON ls.batch_id=b.id
            JOIN courses c ON b.course_id=c.id
            JOIN users u ON ls.started_by=u.id
            WHERE ls.status IN ('waiting','active')
              AND ls.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=?)
            ORDER BY ls.started_at DESC
        ");
        $s->bind_param('i', $me);
    } elseif ($myRole === 'teacher') {
        $s = $conn->prepare("
            SELECT ls.id, ls.batch_id, ls.title, ls.started_at, ls.status,
                   b.name AS batch_name, c.title AS course_title,
                   u.full_name AS teacher_name,
                   TIMESTAMPDIFF(MINUTE, ls.started_at, NOW()) AS duration_minutes
            FROM live_sessions ls
            JOIN batches b ON ls.batch_id=b.id
            JOIN courses c ON b.course_id=c.id
            JOIN users u ON ls.started_by=u.id
            WHERE ls.status IN ('waiting','active')
              AND ls.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)
            ORDER BY ls.started_at DESC
        ");
        $s->bind_param('i', $me);
    } else {
        // Admin — all
        $r = $conn->query("
            SELECT ls.id, ls.batch_id, ls.title, ls.started_at, ls.status,
                   b.name AS batch_name, c.title AS course_title,
                   u.full_name AS teacher_name,
                   TIMESTAMPDIFF(MINUTE, ls.started_at, NOW()) AS duration_minutes
            FROM live_sessions ls
            JOIN batches b ON ls.batch_id=b.id
            JOIN courses c ON b.course_id=c.id
            JOIN users u ON ls.started_by=u.id
            WHERE ls.status IN ('waiting','active')
            ORDER BY ls.started_at DESC
        ");
        jsonResponse('success', 'OK', ['sessions' => $r->fetch_all(MYSQLI_ASSOC)]);
    }
    $s->execute();
    $sessions = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    jsonResponse('success', 'OK', ['sessions' => $sessions]);
}

function getParticipants()
{
    global $conn;
    $sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("
        SELECT ls.status, ls.ended_at,
               TIMESTAMPDIFF(SECOND, ls.started_at, COALESCE(ls.ended_at,NOW())) AS duration_seconds,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id AND left_at IS NULL) AS live_count,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id) AS total_joined
        FROM live_sessions ls WHERE ls.id=?
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $data = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$data) jsonResponse('error', 'Session not found');
    jsonResponse('success', 'OK', $data);
}

// ════════════════════════════════════════════════════════════
// ADMIN-ONLY ACTIONS
// ════════════════════════════════════════════════════════════

function getOneSession()
{
    global $conn, $me, $myRole;
    $sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("
        SELECT ls.*,
               b.name  AS batch_name,
               c.title AS course_title,
               u.full_name        AS teacher_name,
               u.profile_picture  AS teacher_pic,
               TIMESTAMPDIFF(SECOND, ls.started_at, COALESCE(ls.ended_at, NOW())) AS duration_seconds,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id AND left_at IS NULL) AS live_attendees,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id)                     AS total_joined,
               (SELECT COUNT(*) FROM batch_students WHERE batch_id=ls.batch_id)                    AS total_students
        FROM live_sessions ls
        JOIN batches b ON ls.batch_id=b.id
        JOIN courses c ON b.course_id=c.id
        JOIN users u ON ls.started_by=u.id
        WHERE ls.id=?
    ");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$session) jsonResponse('error', 'Session not found');

    // Access control
    if ($myRole === 'student') {
        $c = $conn->prepare("SELECT id FROM batch_students WHERE batch_id=? AND student_id=?");
        $c->bind_param('ii', $session['batch_id'], $me);
        $c->execute();
        if (!$c->get_result()->num_rows) jsonResponse('error', 'Access denied');
        $c->close();
    } elseif ($myRole === 'teacher') {
        $c = $conn->prepare("SELECT id FROM batch_teachers WHERE batch_id=? AND teacher_id=?");
        $c->bind_param('ii', $session['batch_id'], $me);
        $c->execute();
        if (!$c->get_result()->num_rows) jsonResponse('error', 'Access denied');
        $c->close();
    }
    // admin can access any session

    jsonResponse('success', 'OK', ['session' => $session]);
}

function adminList()
{
    global $conn, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Access denied');

    $page    = max(1, (int)($_POST['page'] ?? 1));
    $perPage = 15;
    $status  = trim($_POST['status_filter'] ?? '');
    $search  = trim($_POST['search'] ?? '');
    $offset  = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    $types = '';

    if ($status && in_array($status, ['waiting', 'active', 'ended'])) {
        $where[] = "ls.status=?";
        $params[] = $status;
        $types .= 's';
    }
    if ($search) {
        $like = "%$search%";
        $where[] = "(ls.title LIKE ? OR u.full_name LIKE ? OR b.name LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $cs = $conn->prepare("SELECT COUNT(*) AS cnt FROM live_sessions ls JOIN batches b ON ls.batch_id=b.id JOIN courses c ON b.course_id=c.id JOIN users u ON ls.started_by=u.id $whereSQL");
    if ($types && $cs) $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
    $cs->close();

    $sql = "
        SELECT ls.id, ls.title, ls.status, ls.started_at, ls.ended_at, ls.room_name,
               b.name AS batch_name, c.title AS course_title,
               u.full_name AS teacher_name,
               TIMESTAMPDIFF(MINUTE, ls.started_at, COALESCE(ls.ended_at,NOW())) AS duration_minutes,
               (SELECT COUNT(*) FROM session_attendees WHERE session_id=ls.id) AS total_joined,
               (SELECT COUNT(*) FROM batch_students WHERE batch_id=ls.batch_id) AS total_students
        FROM live_sessions ls
        JOIN batches b ON ls.batch_id=b.id
        JOIN courses c ON b.course_id=c.id
        JOIN users u ON ls.started_by=u.id
        $whereSQL ORDER BY ls.started_at DESC LIMIT ? OFFSET ?
    ";
    $ds = $conn->prepare($sql);
    $allParams = array_merge($params, [$perPage, $offset]);
    $ds->bind_param($types . 'ii', ...$allParams);
    $ds->execute();
    $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
    $ds->close();

    jsonResponse('success', 'OK', [
        'sessions' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ]);
}

function adminDelete()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Access denied');

    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("SELECT id, title FROM live_sessions WHERE id=?");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) jsonResponse('error', 'Session not found');

    $conn->query("DELETE FROM session_attendees WHERE session_id=$sessionId");
    $conn->query("DELETE FROM live_sessions WHERE id=$sessionId");

    logActivity($conn, $me, "Admin deleted session: {$row['title']} (ID $sessionId)", 'live');
    jsonResponse('success', 'Session deleted');
}

function adminEnd()
{
    global $conn, $me, $myRole;
    if ($myRole !== 'admin') jsonResponse('error', 'Access denied');

    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (!$sessionId) jsonResponse('error', 'Session ID required');

    $s = $conn->prepare("SELECT id, batch_id, title FROM live_sessions WHERE id=? AND status IN ('waiting','active')");
    $s->bind_param('i', $sessionId);
    $s->execute();
    $session = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$session) jsonResponse('error', 'Session not found or already ended');

    $conn->query("UPDATE live_sessions SET status='ended', ended_at=NOW() WHERE id=$sessionId");
    $conn->query("UPDATE session_attendees SET left_at=NOW() WHERE session_id=$sessionId AND left_at IS NULL");

    $s = $conn->prepare("SELECT student_id FROM batch_students WHERE batch_id=?");
    $s->bind_param('i', $session['batch_id']);
    $s->execute();
    $students = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($students as $st) {
        pusherTrigger('private-user-' . $st['student_id'], 'live-ended', [
            'session_id' => $sessionId,
            'batch_id' => $session['batch_id'],
        ]);
    }

    logActivity($conn, $me, "Admin force-ended session: {$session['title']}", 'live');
    jsonResponse('success', 'Session ended by admin');
}
