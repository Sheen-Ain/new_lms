<?php
// ============================================================
// PRESENCE AJAX — Online Users (scoped by role)
// ============================================================
// Actions:
//   get_presence  → fetch batchmates/batch-teachers online status
//                   (also acts as heartbeat — updates caller last_seen)
//   heartbeat     → lightweight ping to stay marked online
// ============================================================

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');

$me     = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_presence':
        getPresence();
        break;
    case 'heartbeat':
        heartbeat();
        break;
    default:
        jsonResponse('error', 'Unknown action');
}

// ── Heartbeat ────────────────────────────────────────────────
function heartbeat()
{
    global $conn, $me;
    $stmt = $conn->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE id=?");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $stmt->close();
    jsonResponse('success', 'OK');
}

// ── Get Presence ─────────────────────────────────────────────
function getPresence()
{
    global $conn, $me, $myRole;

    // --- Always update caller's heartbeat on presence fetch ---
    $hb = $conn->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE id=?");
    $hb->bind_param('i', $me);
    $hb->execute();
    $hb->close();

    // A user is "active" if last_seen within 2 minutes
    $ONLINE_MINUTES = 2;

    // ── STUDENT: sees ONLINE teachers of their batches + same-batch students ──
    // Pure admins never appear in student or teacher presence lists —
    // they only appear if they also hold the teacher role AND are in batch_teachers.
    if ($myRole === 'student') {
        $users = getStudentPresence($conn, $me, $ONLINE_MINUTES);
    }
    // ── TEACHER (or admin who switched to teacher): sees batch students + co-teachers ──
    elseif ($myRole === 'teacher') {
        $users = getTeacherPresence($conn, $me, $ONLINE_MINUTES);
    }
    // ── ADMIN view: sees all active users ─────────────────────
    else {
        $users = getAdminPresence($conn, $me, $ONLINE_MINUTES);
    }

    $onlineCount = count(array_filter($users, function ($u) {
        return (int)$u['is_active'] === 1;
    }));

    jsonResponse('success', 'OK', [
        'users'        => $users,
        'online_count' => $onlineCount,
        'total'        => count($users),
    ]);
}

// ── Student Presence ─────────────────────────────────────────
// Rules:
//   Teachers  → ONLINE only, no batch names shown, always display as "Teacher"
//              pure admins (no teacher role) are NEVER shown
//   Students  → same batch, show online + offline
function getStudentPresence($conn, $me, $onlineMinutes)
{
    $om = (int)$onlineMinutes; // local copy — bind_param needs references
    $seen  = [];
    $teachers = [];
    $students = [];

    // --- Teachers of my batches (online AND offline) ---
    // Join on batch_teachers ensures only real teachers (not pure admins) appear.
    // Always display_role='teacher' regardless of their current_role switch.
    // Shown online+offline so a multi-role user (teacher+student) is always
    // identified as teacher and never falls through to the student query below.
    // shared_batches is NULL — batch names are not exposed to students.
    $sql = "
        SELECT DISTINCT
            u.id, u.full_name, u.profile_picture, u.gender,
            u.last_seen,
            CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END AS is_active,
            'teacher' AS display_role,
            NULL AS shared_batches
        FROM users u
        JOIN batch_teachers bt ON bt.teacher_id = u.id
        JOIN batch_students bs ON bs.batch_id = bt.batch_id AND bs.student_id = ?
        WHERE u.status = 'active'
        ORDER BY is_active DESC, u.full_name ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $om, $me);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $seen[$r['id']] = true;
        $teachers[] = $r;
    }

    // --- Fellow students in same batches (online + offline) ---
    $sql2 = "
        SELECT DISTINCT
            u.id, u.full_name, u.profile_picture, u.gender,
            u.last_seen,
            CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END AS is_active,
            'student' AS display_role,
            NULL AS shared_batches
        FROM users u
        JOIN batch_students bs_them ON bs_them.student_id = u.id
        JOIN batch_students bs_me   ON bs_me.batch_id = bs_them.batch_id AND bs_me.student_id = ?
        WHERE u.id != ?
          AND u.status = 'active'
        GROUP BY u.id
        ORDER BY is_active DESC, u.full_name ASC
    ";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('iii', $om, $me, $me);
    $stmt2->execute();
    $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    foreach ($rows2 as $r) {
        if (!isset($seen[$r['id']])) {
            $students[] = $r;
        }
    }

    // Teachers first (all online), then students (online first then offline)
    usort($students, function ($a, $b) {
        $cmp = ($b['is_active'] <=> $a['is_active']);
        return $cmp !== 0 ? $cmp : strcmp($a['full_name'], $b['full_name']);
    });

    return array_merge($teachers, $students);
}

// ── Teacher Presence ─────────────────────────────────────────
// Returns: students in my batches + fellow teachers in same batches
function getTeacherPresence($conn, $me, $onlineMinutes)
{
    $om = (int)$onlineMinutes;
    $users = [];
    $seen  = [];

    // --- Students in my batches ---
    $sql = "
        SELECT DISTINCT
            u.id, u.full_name, u.profile_picture, u.current_role, u.gender,
            u.last_seen,
            CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END AS is_active,
            'student' AS display_role,
            GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS shared_batches
        FROM users u
        JOIN batch_students bs ON bs.student_id = u.id
        JOIN batch_teachers bt ON bt.batch_id = bs.batch_id AND bt.teacher_id = ?
        JOIN batches b         ON b.id = bs.batch_id
        WHERE u.status = 'active'
        GROUP BY u.id
        ORDER BY is_active DESC, u.full_name ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $om, $me);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $seen[$r['id']] = true;
        $users[] = $r;
    }

    // --- Fellow teachers in my batches ---
    $sql2 = "
        SELECT DISTINCT
            u.id, u.full_name, u.profile_picture, u.current_role, u.gender,
            u.last_seen,
            CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END AS is_active,
            'teacher' AS display_role,
            GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS shared_batches
        FROM users u
        JOIN batch_teachers bt_them ON bt_them.teacher_id = u.id
        JOIN batch_teachers bt_me   ON bt_me.batch_id = bt_them.batch_id AND bt_me.teacher_id = ?
        JOIN batches b              ON b.id = bt_them.batch_id
        WHERE u.id != ?
          AND u.status = 'active'
        GROUP BY u.id
        ORDER BY is_active DESC, u.full_name ASC
    ";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('iii', $om, $me, $me);
    $stmt2->execute();
    $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    $coTeachers = [];
    foreach ($rows2 as $r) {
        if (!isset($seen[$r['id']])) {
            $coTeachers[] = $r;
        }
    }

    $allUsers = array_merge($coTeachers, $users);
    usort($allUsers, function ($a, $b) {
        if ($a['is_active'] != $b['is_active']) return (int)$b['is_active'] - (int)$a['is_active'];
        $ra = $a['display_role'] === 'teacher' ? 0 : 1;
        $rb = $b['display_role'] === 'teacher' ? 0 : 1;
        if ($ra !== $rb) return $ra - $rb;
        return strcmp($a['full_name'], $b['full_name']);
    });

    return $allUsers;
}

// ── Admin Presence ────────────────────────────────────────────
// Returns: all active users (up to 60, most recently seen first)
function getAdminPresence($conn, $me, $onlineMinutes)
{
    $om = (int)$onlineMinutes;
    $sql = "
        SELECT u.id, u.full_name, u.profile_picture, u.current_role, u.gender,
               u.last_seen,
               CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE) THEN 1 ELSE 0 END AS is_active,
               u.current_role AS display_role,
               NULL AS shared_batches
        FROM users u
        WHERE u.id != ?
          AND u.status = 'active'
        ORDER BY is_active DESC, u.last_seen DESC
        LIMIT 60
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $om, $me);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}
