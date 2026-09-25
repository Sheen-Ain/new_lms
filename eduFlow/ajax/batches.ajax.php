<?php
error_reporting(0);
ini_set("display_errors", "0");
ob_start(); // Buffer all output so stray warnings never break JSON


if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) jsonResponse('error', 'Unauthorized');
$me = (int)$_SESSION['user_id'];
$myRole = $_SESSION['role'] ?? '';
if (!in_array($myRole, ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  case 'list':
    listBatches();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    saveBatch(false);
    break;
  case 'update':
    saveBatch(true);
    break;
  case 'delete':
    deleteBatch();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  case 'toggle_status':
    toggleStatus();
    break;
  case 'assign_teachers':
    assignTeachers();
    break;
  case 'enroll_students':
    enrollStudents();
    break;
  case 'get_teachers':
    getTeachers();
    break;
  case 'get_students':
    getStudents();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}

function listBatches()
{
  global $conn, $myRole, $me;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 10));
  $search = trim($_POST['search'] ?? '');
  $courseId = (int)($_POST['course_id'] ?? 0);
  $status = trim($_POST['status'] ?? '');
  $where = [];
  $params = [];
  $types = '';
  if ($myRole === 'teacher') {
    $where[] = "b.id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)";
    $params[] = $me;
    $types .= 'i';
  }
  if ($search) {
    $s = "%$search%";
    $where[] = "b.name LIKE ?";
    $params[] = $s;
    $types .= 's';
  }
  if ($courseId) {
    $where[] = "b.course_id=?";
    $params[] = $courseId;
    $types .= 'i';
  }
  if ($status) {
    $where[] = "b.status=?";
    $params[] = $status;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;

  $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM batches b $wSQL");
  if ($types) $cs->bind_param($types, ...$params);
  $cs->execute();
  $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
  $cs->close();

  $sql = "SELECT b.*,c.title as course_title,
        (SELECT COUNT(*) FROM batch_students WHERE batch_id=b.id) as student_count,
        (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM batch_teachers bt JOIN users u ON bt.teacher_id=u.id WHERE bt.batch_id=b.id) as teacher_names
        FROM batches b LEFT JOIN courses c ON b.course_id=c.id
        $wSQL ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['batches' => $batches, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

function getOne()
{
  global $conn;
  $id = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  $stmt = $conn->prepare("SELECT b.*,c.title as course_title FROM batches b LEFT JOIN courses c ON b.course_id=c.id WHERE b.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $b = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$b) jsonResponse('error', 'Not found');
  jsonResponse('success', 'OK', $b);
}

function saveBatch($isUpdate)
{
  global $conn, $me;
  if ($me && $_SESSION['role'] !== 'admin') jsonResponse('error', 'Admin only');
  $name = trim($_POST['name'] ?? '');
  $courseId = (int)($_POST['course_id'] ?? 0);
  $desc = trim($_POST['description'] ?? '');
  $start = $_POST['start_date'] ?? '';
  $end = $_POST['end_date'] ?? '';
  $max = (int)($_POST['max_students'] ?? 50);
  $status = $_POST['status'] ?? 'active';
  if (!$name || !$courseId) jsonResponse('error', 'Name and course are required');
  $startVal = $start ?: null;
  $endVal = $end ?: null;
  if ($isUpdate) {
    $id = (int)($_POST['batch_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');
    $stmt = $conn->prepare("UPDATE batches SET name=?,course_id=?,description=?,start_date=?,end_date=?,max_students=?,status=? WHERE id=?");
    $stmt->bind_param('sisssisi', $name, $courseId, $desc, $startVal, $endVal, $max, $status, $id);
    if (!$stmt->execute()) jsonResponse('error', 'Update failed');
    $stmt->close();
    logActivity($conn, $me, "Updated batch: $name", 'batches');
    jsonResponse('success', 'Batch updated');
  } else {
    $stmt = $conn->prepare("INSERT INTO batches (name,course_id,description,start_date,end_date,max_students,status,created_by) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sississi', $name, $courseId, $desc, $startVal, $endVal, $max, $status, $me);
    if (!$stmt->execute()) jsonResponse('error', 'Create failed: ' . $conn->error);
    $id = $conn->insert_id;
    $stmt->close();
    logActivity($conn, $me, "Created batch: $name (ID $id)", 'batches');
    jsonResponse('success', 'Batch created', ['batch_id' => $id]);
  }
}

function deleteBatch()
{
  global $conn, $me;
  $id = (int)($_POST['batch_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT name FROM batches WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $b = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$b) jsonResponse('error', 'Not found');
  $stmt = $conn->prepare("DELETE FROM batches WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted batch: {$b['name']}", 'batches');
  jsonResponse('success', 'Batch deleted');
}

function bulkDelete()
{
  global $conn, $me, $myRole;
  if ($myRole !== 'admin') jsonResponse('error', 'Access denied');
  $raw = $_POST['ids'] ?? '';
  $ids = array_filter(array_map('intval', explode(',', $raw)));
  if (empty($ids)) jsonResponse('error', 'No IDs provided');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM batches WHERE id IN ($placeholders)");
  $stmt->bind_param($types, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Delete failed: ' . $conn->error);
  $count = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $count batches", 'batches');
  jsonResponse('success', "$count batch(es) deleted");
}

function toggleStatus()
{
  global $conn, $me;
  $id = (int)($_POST['batch_id'] ?? 0);
  $status = $_POST['status'] ?? 'active';
  if (!in_array($status, ['active', 'inactive', 'completed'])) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("UPDATE batches SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Batch ID $id → $status", 'batches');
  jsonResponse('success', 'Updated');
}

function getTeachers()
{
  global $conn;
  $id = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $all = $conn->query("SELECT u.id,u.full_name,u.email FROM users u JOIN user_roles ur ON u.id=ur.user_id WHERE ur.role_id=2 AND u.status='active' ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
  $stmt = $conn->prepare("SELECT u.id,u.full_name,u.email FROM batch_teachers bt JOIN users u ON bt.teacher_id=u.id WHERE bt.batch_id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['all' => $all, 'assigned' => $assigned]);
}

function assignTeachers()
{
  global $conn, $me;
  $id = (int)($_POST['batch_id'] ?? 0);
  $ids = array_filter(array_map('intval', explode(',', $_POST['teacher_ids'] ?? '')));
  if (!$id) jsonResponse('error', 'Invalid batch');
  $conn->query("DELETE FROM batch_teachers WHERE batch_id=$id");
  foreach ($ids as $tid) {
    $s = $conn->prepare("INSERT IGNORE INTO batch_teachers (batch_id,teacher_id,assigned_by) VALUES (?,?,?)");
    $s->bind_param('iii', $id, $tid, $me);
    $s->execute();
    $s->close();
  }
  logActivity($conn, $me, "Assigned " . count($ids) . " teachers to batch $id", 'batches');
  jsonResponse('success', 'Teachers assigned');
}

function getStudents()
{
  global $conn;
  $id = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $all = $conn->query("SELECT u.id,u.full_name,u.email FROM users u JOIN user_roles ur ON u.id=ur.user_id WHERE ur.role_id=1 AND u.status='active' ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);
  $stmt = $conn->prepare("SELECT u.id,u.full_name,u.email FROM batch_students bs JOIN users u ON bs.student_id=u.id WHERE bs.batch_id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $enrolled = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['all' => $all, 'enrolled' => $enrolled]);
}

function enrollStudents()
{
  global $conn, $me;
  $id = (int)($_POST['batch_id'] ?? 0);
  $ids = array_filter(array_map('intval', explode(',', $_POST['student_ids'] ?? '')));
  if (!$id) jsonResponse('error', 'Invalid batch');
  // Check max
  $batch = $conn->query("SELECT max_students FROM batches WHERE id=$id")->fetch_assoc();
  if ($batch && count($ids) > (int)$batch['max_students']) {
    jsonResponse('error', 'Exceeds maximum students (' . $batch['max_students'] . ')');
  }
  $conn->query("DELETE FROM batch_students WHERE batch_id=$id");
  foreach ($ids as $sid) {
    $s = $conn->prepare("INSERT IGNORE INTO batch_students (batch_id,student_id,enrolled_by) VALUES (?,?,?)");
    $s->bind_param('iii', $id, $sid, $me);
    $s->execute();
    $s->close();
  }
  logActivity($conn, $me, "Enrolled " . count($ids) . " students in batch $id", 'batches');
  jsonResponse('success', 'Students enrolled');
}
