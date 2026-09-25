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
if ($_SESSION['role'] !== 'admin') jsonResponse('error', 'Access denied');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  case 'list':
    listCourses();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    saveCourse(false);
    break;
  case 'update':
    saveCourse(true);
    break;
  case 'delete':
    deleteCourse();
    break;
  case 'toggle_status':
    toggleStatus();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}

function listCourses()
{
  global $conn;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 10));
  $search = trim($_POST['search'] ?? '');
  $status = trim($_POST['status'] ?? '');
  $where = [];
  $params = [];
  $types = '';
  if ($search) {
    $s = "%$search%";
    $where[] = "c.title LIKE ?";
    $params[] = $s;
    $types .= 's';
  }
  if ($status) {
    $where[] = "c.status=?";
    $params[] = $status;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;

  $cStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM courses c $wSQL");
  if ($types) $cStmt->bind_param($types, ...$params);
  $cStmt->execute();
  $total = (int)$cStmt->get_result()->fetch_assoc()['cnt'];
  $cStmt->close();

  $sql = "SELECT c.*, u.full_name as creator_name, (SELECT COUNT(*) FROM batches WHERE course_id=c.id) as batch_count FROM courses c LEFT JOIN users u ON c.created_by=u.id $wSQL ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['courses' => $courses, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

function getOne()
{
  global $conn;
  $id = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  $stmt = $conn->prepare("SELECT c.*, u.full_name as creator_name FROM courses c LEFT JOIN users u ON c.created_by=u.id WHERE c.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $c = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$c) jsonResponse('error', 'Not found');
  jsonResponse('success', 'OK', $c);
}

function saveCourse($isUpdate)
{
  global $conn, $me;
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $status = $_POST['status'] ?? 'active';
  if (!$title) jsonResponse('error', 'Title is required');

  // Handle thumbnail upload
  $thumbnail = null;
  if (!empty($_FILES['thumbnail']['name'])) {
    $v = validateUpload($_FILES['thumbnail'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5);
    if (!$v['ok']) jsonResponse('error', $v['error']);
    $thumbnail = saveUpload($_FILES['thumbnail'], 'topics');
    if (!$thumbnail) jsonResponse('error', 'Failed to save thumbnail');
  }

  if ($isUpdate) {
    $id = (int)($_POST['course_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid ID');
    if ($thumbnail) {
      $stmt = $conn->prepare("UPDATE courses SET title=?,description=?,thumbnail=?,status=? WHERE id=?");
      $stmt->bind_param('ssssi', $title, $desc, $thumbnail, $status, $id);
    } else {
      $stmt = $conn->prepare("UPDATE courses SET title=?,description=?,status=? WHERE id=?");
      $stmt->bind_param('sssi', $title, $desc, $status, $id);
    }
    if (!$stmt->execute()) jsonResponse('error', 'Update failed');
    $stmt->close();
    logActivity($conn, $me, "Updated course: $title", 'courses');
    jsonResponse('success', 'Course updated successfully');
  } else {
    $stmt = $conn->prepare("INSERT INTO courses (title,description,thumbnail,status,created_by) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $title, $desc, $thumbnail, $status, $me);
    if (!$stmt->execute()) jsonResponse('error', 'Create failed: ' . $conn->error);
    $id = $conn->insert_id;
    $stmt->close();
    logActivity($conn, $me, "Created course: $title (ID $id)", 'courses');
    jsonResponse('success', 'Course created successfully', ['course_id' => $id]);
  }
}

function deleteCourse()
{
  global $conn, $me;
  $id = (int)($_POST['course_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  $stmt = $conn->prepare("SELECT title FROM courses WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $c = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$c) jsonResponse('error', 'Not found');
  $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Delete failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted course: {$c['title']}", 'courses');
  jsonResponse('success', 'Course deleted');
}

function toggleStatus()
{
  global $conn, $me;
  $id = (int)($_POST['course_id'] ?? 0);
  $status = $_POST['status'] ?? 'active';
  if (!in_array($status, ['active', 'inactive', 'archived'])) jsonResponse('error', 'Invalid status');
  $stmt = $conn->prepare("UPDATE courses SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Course ID $id status → $status", 'courses');
  jsonResponse('success', 'Status updated');
}

function bulkDelete()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (!$ids) jsonResponse('error', 'No items selected');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM courses WHERE id IN ($ph)");
  $stmt->bind_param($types, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt courses", 'courses');
  jsonResponse('success', "$cnt course(s) deleted");
}
