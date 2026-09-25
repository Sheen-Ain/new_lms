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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  case 'list':
    listAnn();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    saveAnn(false);
    break;
  case 'update':
    saveAnn(true);
    break;
  case 'delete':
    deleteAnn();
    break;
  case 'toggle_pin':
    togglePin();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}

function listAnn()
{
  global $conn, $me, $myRole;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 10));
  $batchId = (int)($_POST['batch_id'] ?? 0);
  $priority = trim($_POST['priority'] ?? '');
  $status = trim($_POST['status'] ?? '');
  $where = ['(an.status=\'published\')'];
  $params = [];
  $types = '';
  if ($myRole === 'admin') array_shift($where); // Admin sees all
  if ($myRole === 'student') {
    $where[] = "(an.batch_id IS NULL OR an.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=?))";
    $params[] = $me;
    $types .= 'i';
  } elseif ($myRole === 'teacher') {
    $where[] = "(an.batch_id IS NULL OR an.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?))";
    $params[] = $me;
    $types .= 'i';
  }
  if ($batchId) {
    $where[] = "an.batch_id=?";
    $params[] = $batchId;
    $types .= 'i';
  }
  if ($priority) {
    $where[] = "an.priority=?";
    $params[] = $priority;
    $types .= 's';
  }
  if ($status && $myRole === 'admin') {
    $where[] = "an.status=?";
    $params[] = $status;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;
  $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM announcements an $wSQL");
  if ($types) $cs->bind_param($types, ...$params);
  $cs->execute();
  $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
  $cs->close();
  $sql = "SELECT an.*,b.name as batch_name,u.full_name as creator_name FROM announcements an LEFT JOIN batches b ON an.batch_id=b.id LEFT JOIN users u ON an.created_by=u.id $wSQL ORDER BY an.is_pinned DESC,an.created_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['announcements' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

function getOne()
{
  global $conn;
  $id = (int)($_POST['announcement_id'] ?? $_GET['announcement_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT an.*,b.name as batch_name FROM announcements an LEFT JOIN batches b ON an.batch_id=b.id WHERE an.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $a = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$a) jsonResponse('error', 'Not found');
  jsonResponse('success', 'OK', $a);
}

function saveAnn($isUpdate)
{
  global $conn, $me;
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $batchId = (int)($_POST['batch_id'] ?? 0) ?: null;
  $priority = $_POST['priority'] ?? 'normal';
  $pinned = (int)($_POST['is_pinned'] ?? 0);
  $status = $_POST['status'] ?? 'published';
  if (!$title || !$content) jsonResponse('error', 'Title and content required');
  if (!in_array($priority, ['normal', 'important', 'urgent'])) $priority = 'normal';
  if ($isUpdate) {
    $id = (int)($_POST['announcement_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid');
    $stmt = $conn->prepare("UPDATE announcements SET title=?,content=?,batch_id=?,priority=?,is_pinned=?,status=? WHERE id=?");
    $stmt->bind_param('ssisssi', $title, $content, $batchId, $priority, $pinned, $status, $id);
    if (!$stmt->execute()) jsonResponse('error', 'Failed');
    $stmt->close();
    logActivity($conn, $me, "Updated announcement: $title", 'announcements');
    jsonResponse('success', 'Updated');
  } else {
    $stmt = $conn->prepare("INSERT INTO announcements (title,content,batch_id,priority,is_pinned,status,created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('ssisssi', $title, $content, $batchId, $priority, $pinned, $status, $me);
    if (!$stmt->execute()) jsonResponse('error', 'Failed');
    $id = $conn->insert_id;
    $stmt->close();
    logActivity($conn, $me, "Created announcement: $title", 'announcements');
    jsonResponse('success', 'Created', ['id' => $id]);
  }
}

function deleteAnn()
{
  global $conn, $me;
  $id = (int)($_POST['announcement_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted announcement $id", 'announcements');
  jsonResponse('success', 'Deleted');
}

function togglePin()
{
  global $conn, $me;
  $id = (int)($_POST['announcement_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $conn->query("UPDATE announcements SET is_pinned = NOT is_pinned WHERE id=$id");
  $new = $conn->query("SELECT is_pinned FROM announcements WHERE id=$id")->fetch_assoc()['is_pinned'];
  logActivity($conn, $me, "Toggled pin on announcement $id", 'announcements');
  jsonResponse('success', $new ? 'Pinned' : 'Unpinned', ['is_pinned' => (bool)$new]);
}

function bulkDelete()
{
  global $conn, $me;
  if (!in_array($_SESSION['role'] ?? '', ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No IDs');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM announcements WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt announcements", 'announcements');
  jsonResponse('success', "$cnt announcement(s) deleted");
}
