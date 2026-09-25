<?php
error_reporting(0);
ini_set("display_errors", "0");
ob_start(); // Buffer all output so stray warnings never break JSON


if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  jsonResponse('error', 'Unauthorized');
}
$me = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
if (!in_array($myRole, ['admin'])) {
  jsonResponse('error', 'Access denied');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  case 'list':
    listUsers();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    createUser();
    break;
  case 'update':
    updateUser();
    break;
  case 'delete':
    deleteUser();
    break;
  case 'toggle_status':
    toggleStatus();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  case 'bulk_status':
    bulkStatus();
    break;
  case 'toggle_bypass_gate':
    toggleBypassGate();
    break;
  case 'get_unenrolled_students':
    getUnenrolledStudents();
    break;
  case 'admin_create_application':
    adminCreateApplication();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}

function listUsers()
{
  global $conn;
  $page    = max(1, (int)($_POST['page'] ?? 1));
  $perPage = min(100, max(5, (int)($_POST['per_page'] ?? 10)));
  $search  = trim($_POST['search'] ?? '');
  $role    = trim($_POST['role'] ?? '');
  $status  = trim($_POST['status'] ?? '');
  $verified = $_POST['verified'] ?? '';
  $sortCol = in_array($_POST['sort_col'] ?? '', ['full_name', 'email', 'status', 'last_seen', 'created_at']) ? ($_POST['sort_col']) : 'created_at';
  $sortDir = ($_POST['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

  $where = [];
  $params = [];
  $types = '';

  if ($search) {
    $s = "%$search%";
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = $s;
    $params[] = $s;
    $types .= 'ss';
  }
  if ($status) {
    $where[] = "u.status=?";
    $params[] = $status;
    $types .= 's';
  }
  if ($verified !== '') {
    $where[] = "u.is_verified=?";
    $params[] = (int)$verified;
    $types .= 'i';
  }
  if ($role) {
    $where[] = "u.id IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE r.name=?)";
    $params[] = $role;
    $types .= 's';
  }

  $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;

  // Count
  $countSQL = "SELECT COUNT(DISTINCT u.id) as cnt FROM users u $whereSQL";
  $stmt = $conn->prepare($countSQL);
  if ($types && $stmt) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)$stmt->get_result()->fetch_assoc()['cnt'];
  $stmt->close();

  // Data
  $sql = "SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ',') as all_roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id=ur.user_id
        LEFT JOIN roles r ON ur.role_id=r.id
        $whereSQL
        GROUP BY u.id
        ORDER BY u.$sortCol $sortDir
        LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);
  $allTypes = $types . 'ii';
  $allParams = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($allTypes, ...$allParams);
  $stmt->execute();
  $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // Mask passwords
  foreach ($users as &$u) unset($u['password']);

  jsonResponse('success', 'OK', ['users' => $users, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

function getOne()
{
  global $conn;
  $id = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid user ID');
  $stmt = $conn->prepare("SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ',') as all_roles FROM users u LEFT JOIN user_roles ur ON u.id=ur.user_id LEFT JOIN roles r ON ur.role_id=r.id WHERE u.id=? GROUP BY u.id");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$u) jsonResponse('error', 'User not found');
  unset($u['password']);
  jsonResponse('success', 'OK', $u);
}

function createUser()
{
  global $conn, $me;
  $name     = trim($_POST['full_name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $phone    = trim($_POST['phone'] ?? '');
  $gender   = $_POST['gender'] ?? '';
  $cnic     = trim($_POST['cnic'] ?? '');
  $status   = $_POST['status'] ?? 'active';
  $verified = (int)($_POST['is_verified'] ?? 0);
  $roles    = array_filter(explode(',', $_POST['roles'] ?? ''));

  if (!$name || !$email || !$pass) jsonResponse('error', 'Name, email and password are required');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse('error', 'Invalid email');
  if (strlen($pass) < 8) jsonResponse('error', 'Password must be at least 8 characters');
  if (!in_array($gender, ['male', 'female', 'other'])) jsonResponse('error', 'Please select a gender');

  // Check duplicate email
  $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  if ($stmt->get_result()->num_rows) jsonResponse('error', 'Email already registered');
  $stmt->close();

  // CNIC — optional for admin, but validate format if provided
  $cnicFormatted = null;
  if ($cnic !== '') {
    $cnicClean = preg_replace('/[^0-9]/', '', $cnic);
    if (strlen($cnicClean) !== 13) jsonResponse('error', 'CNIC must be 13 digits (XXXXX-XXXXXXX-X)');
    $cnicFormatted = substr($cnicClean, 0, 5) . '-' . substr($cnicClean, 5, 7) . '-' . substr($cnicClean, 12, 1);
    // Check duplicate CNIC
    $stmt = $conn->prepare("SELECT id FROM users WHERE cnic=?");
    $stmt->bind_param('s', $cnicFormatted);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) jsonResponse('error', 'A user with this CNIC already exists');
    $stmt->close();
  }

  // Auto-generate unique 5-digit ID
  $userId = null;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    $candidate = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    $chk = $conn->prepare("SELECT id FROM users WHERE user_id_number=?");
    $chk->bind_param('s', $candidate);
    $chk->execute();
    $exists = $chk->get_result()->num_rows;
    $chk->close();
    if (!$exists) {
      $userId = $candidate;
      break;
    }
  }
  if (!$userId) jsonResponse('error', 'Could not generate a unique ID. Please try again.');

  $hash      = password_hash($pass, PASSWORD_BCRYPT);
  $firstRole = !empty($roles) ? (['1' => 'student', '2' => 'teacher', '3' => 'admin'][$roles[0]] ?? 'student') : 'student';

  // Use empty string for null CNIC so bind_param works simply
  $cnicVal = $cnicFormatted ?? '';

  $ins = $conn->prepare(
    "INSERT INTO users (full_name,email,password,phone,gender,cnic,user_id_number,status,is_verified,`current_role`)
     VALUES (?,?,?,?,?,NULLIF(?,''),?,?,?,?)"
  );
  $ins->bind_param('ssssssssis', $name, $email, $hash, $phone, $gender, $cnicVal, $userId, $status, $verified, $firstRole);
  if (!$ins->execute()) jsonResponse('error', 'Failed to create user: ' . $conn->error);
  $uid = $conn->insert_id;
  $ins->close();

  // Assign roles
  foreach ($roles as $rid) {
    $rid = (int)$rid;
    $s = $conn->prepare("INSERT IGNORE INTO user_roles (user_id,role_id,assigned_by) VALUES (?,?,?)");
    $s->bind_param('iii', $uid, $rid, $me);
    $s->execute();
    $s->close();
  }

  logActivity($conn, $me, "Created user: $name (ID $userId)", 'users');
  jsonResponse('success', 'User created successfully', ['user_id' => $uid, 'student_id' => $userId]);
}

function updateUser()
{
  global $conn, $me;
  $id       = (int)($_POST['user_id'] ?? 0);
  $name     = trim($_POST['full_name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $phone    = trim($_POST['phone'] ?? '');
  $gender   = $_POST['gender'] ?? '';
  $cnic     = trim($_POST['cnic'] ?? '');
  $status   = $_POST['status'] ?? 'active';
  $verified = (int)($_POST['is_verified'] ?? 0);
  $pass     = $_POST['password'] ?? '';
  $roles    = array_filter(explode(',', $_POST['roles'] ?? ''));

  if (!$id || !$name || !$email) jsonResponse('error', 'Required fields missing');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse('error', 'Invalid email');
  if (!in_array($gender, ['male', 'female', 'other', ''])) jsonResponse('error', 'Invalid gender');

  // Duplicate email check
  $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
  $stmt->bind_param('si', $email, $id);
  $stmt->execute();
  if ($stmt->get_result()->num_rows) jsonResponse('error', 'Email already in use');
  $stmt->close();

  // CNIC — optional, validate if provided
  $cnicFormatted = '';
  if ($cnic !== '') {
    $cnicClean = preg_replace('/[^0-9]/', '', $cnic);
    if (strlen($cnicClean) !== 13) jsonResponse('error', 'CNIC must be 13 digits (XXXXX-XXXXXXX-X)');
    $cnicFormatted = substr($cnicClean, 0, 5) . '-' . substr($cnicClean, 5, 7) . '-' . substr($cnicClean, 12, 1);
    // Duplicate CNIC check (excluding this user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE cnic=? AND id!=?");
    $stmt->bind_param('si', $cnicFormatted, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) jsonResponse('error', 'Another user already has this CNIC');
    $stmt->close();
  }

  if ($pass) {
    if (strlen($pass) < 8) jsonResponse('error', 'Password must be at least 8 characters');
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,password=?,phone=?,gender=?,cnic=NULLIF(?,''),status=?,is_verified=? WHERE id=?");
    $stmt->bind_param('sssssssii', $name, $email, $hash, $phone, $gender, $cnicFormatted, $status, $verified, $id);
  } else {
    $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,gender=?,cnic=NULLIF(?,''),status=?,is_verified=? WHERE id=?");
    $stmt->bind_param('ssssssii', $name, $email, $phone, $gender, $cnicFormatted, $status, $verified, $id);
  }
  if (!$stmt->execute()) jsonResponse('error', 'Update failed');
  $stmt->close();

  // Update roles
  $s = $conn->prepare("DELETE FROM user_roles WHERE user_id=?");
  $s->bind_param('i', $id);
  $s->execute();
  $s->close();
  foreach ($roles as $rid) {
    $rid = (int)$rid;
    $s = $conn->prepare("INSERT IGNORE INTO user_roles (user_id,role_id,assigned_by) VALUES (?,?,?)");
    $s->bind_param('iii', $id, $rid, $me);
    $s->execute();
    $s->close();
  }

  logActivity($conn, $me, "Updated user ID $id", 'users');
  jsonResponse('success', 'User updated successfully');
}

function deleteUser()
{
  global $conn, $me;
  $id = (int)($_POST['user_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  if ($id === $me) jsonResponse('error', 'Cannot delete yourself');
  $stmt = $conn->prepare("SELECT full_name FROM users WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$u) jsonResponse('error', 'User not found');
  $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Delete failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted user: {$u['full_name']} (ID $id)", 'users');
  jsonResponse('success', 'User deleted successfully');
}

function toggleStatus()
{
  global $conn, $me;
  $id = (int)($_POST['user_id'] ?? 0);
  $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
  if (!$id) jsonResponse('error', 'Invalid ID');
  $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Set user ID $id status to $status", 'users');
  jsonResponse('success', "User $status");
}

function bulkDelete()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (!$ids) jsonResponse('error', 'No users selected');
  $ids = array_diff($ids, [$me]); // Cannot delete self
  if (!$ids) jsonResponse('error', 'Cannot delete yourself');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
  $stmt->bind_param($types, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Bulk delete failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt users", 'users');
  jsonResponse('success', "$cnt user(s) deleted");
}

function bulkStatus()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
  if (!$ids) jsonResponse('error', 'No users selected');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = 's' . str_repeat('i', count($ids));
  $stmt = $conn->prepare("UPDATE users SET status=? WHERE id IN ($placeholders)");
  $stmt->bind_param($types, $status, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk set $cnt users to $status", 'users');
  jsonResponse('success', "$cnt user(s) updated");
}

// ── Toggle bypass gate ────────────────────────────────────────
function toggleBypassGate()
{
    global $conn, $me;
    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId) jsonResponse('error', 'Invalid user ID');

    // Only works on students
    $user = $conn->query("SELECT id, full_name, bypass_gate, current_role FROM users WHERE id=$userId")->fetch_assoc();
    if (!$user) jsonResponse('error', 'User not found');

    $newVal = $user['bypass_gate'] ? 0 : 1;
    $conn->query("UPDATE users SET bypass_gate=$newVal WHERE id=$userId");

    $label = $newVal ? 'enabled dashboard bypass' : 'disabled dashboard bypass';
    logActivity($conn, $me, "Admin $label for {$user['full_name']} (ID $userId)", 'users');
    jsonResponse('success', $newVal ? 'Dashboard access granted.' : 'Dashboard access restricted.', ['bypass_gate' => $newVal]);
}

// ── Get students who have no application in any batch ─────────
function getUnenrolledStudents()
{
    global $conn;

    $search  = trim($_POST['search'] ?? '');
    $batchId = (int)($_POST['batch_id'] ?? 0);

    // Batches for the dropdown
    $batches = $conn->query("
        SELECT b.id, b.name, c.title AS course_title
        FROM batches b JOIN courses c ON b.course_id=c.id
        WHERE b.status='active'
        ORDER BY c.title ASC, b.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    // Students with no approved application for the chosen batch
    $whereSearch = '';
    if ($search) {
        $s = $conn->real_escape_string($search);
        $whereSearch = "AND (u.full_name LIKE '%$s%' OR u.user_id_number LIKE '%$s%' OR u.email LIKE '%$s%')";
    }

    $batchFilter = '';
    if ($batchId) {
        $batchFilter = "AND u.id NOT IN (SELECT user_id FROM course_applications WHERE batch_id=$batchId)";
    }

    $students = $conn->query("
        SELECT u.id, u.full_name, u.email, u.user_id_number, u.cnic, u.gender, u.status, u.bypass_gate
        FROM users u
        WHERE u.current_role = 'student'
          $whereSearch
          $batchFilter
        ORDER BY u.full_name ASC
        LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    jsonResponse('success', 'OK', ['students' => $students, 'batches' => $batches]);
}

// ── Admin creates application on behalf of a student ──────────
function adminCreateApplication()
{
    global $conn, $me;
    require_once __DIR__ . '/../includes/mailer.php';

    $studentId = (int)($_POST['student_id'] ?? 0);
    $batchId   = (int)($_POST['batch_id']   ?? 0);
    if (!$studentId || !$batchId) jsonResponse('error', 'Student and batch are required');

    $student = $conn->query("
        SELECT id, full_name, email, cnic, status FROM users WHERE id=$studentId AND current_role='student'
    ")->fetch_assoc();
    if (!$student) jsonResponse('error', 'Student not found');

    $batch = $conn->query("
        SELECT b.id, b.name, c.title AS course_title, b.course_id
        FROM batches b JOIN courses c ON b.course_id=c.id
        WHERE b.id=$batchId AND b.status='active'
    ")->fetch_assoc();
    if (!$batch) jsonResponse('error', 'Batch not found or inactive');

    // Check for existing application
    $existing = $conn->query("
        SELECT id, status FROM course_applications WHERE user_id=$studentId AND batch_id=$batchId
    ")->fetch_assoc();

    if ($existing) {
        if ($existing['status'] === 'approved') {
            jsonResponse('error', 'Student is already approved and enrolled in this batch.');
        }
        // Update existing to approved
        $conn->query("
            UPDATE course_applications
            SET status='approved', reviewed_at=NOW(), reviewed_by=$me
            WHERE id={$existing['id']}
        ");
    } else {
        // Create new application — pre-approved
        $stmt = $conn->prepare("
            INSERT INTO course_applications (user_id, batch_id, status, reviewed_at, reviewed_by)
            VALUES (?, ?, 'approved', NOW(), ?)
        ");
        $stmt->bind_param('iii', $studentId, $batchId, $me);
        if (!$stmt->execute()) jsonResponse('error', 'Failed to create application: ' . $conn->error);
        $stmt->close();
    }

    // Activate student account if inactive
    $conn->query("UPDATE users SET status='active', is_verified=1 WHERE id=$studentId AND (status='inactive' OR is_verified=0)");

    // Enroll in batch_students
    $conn->query("INSERT IGNORE INTO batch_students (batch_id, student_id, enrolled_by) VALUES ($batchId, $studentId, $me)");

    // Send approval email
    mailApplicationApproved($student['email'], $student['full_name'], $batch['course_title']);

    logActivity($conn, $me, "Admin created approved application for {$student['full_name']} → {$batch['course_title']} ({$batch['name']})", 'applications');
    jsonResponse('success', "{$student['full_name']} enrolled in {$batch['name']} and notified by email.");
}