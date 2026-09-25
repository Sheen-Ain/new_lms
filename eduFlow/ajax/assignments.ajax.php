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
    listAssignments();
    break;
  case 'list_simple':
    listSimple();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    saveAssignment(false);
    break;
  case 'update':
    saveAssignment(true);
    break;
  case 'delete':
    deleteAssignment();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  case 'toggle_status':
    toggleStatus();
    break;
  case 'upload_file':
    uploadFile();
    break;
  case 'delete_file':
    deleteFile();
    break;
  case 'bulk_delete_files':
    bulkDeleteFiles();
    break;
  case 'toggle_file_status':
    toggleFileStatus();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}
function listAssignments()
{
  global $conn, $me, $myRole;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 15));
  $search = trim($_POST['search'] ?? '');
  $batchId = (int)($_POST['batch_id'] ?? 0);
  $status = trim($_POST['status'] ?? '');
  $where = [];
  $params = [];
  $types = '';
  if ($myRole === 'teacher') {
    $where[] = "a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)";
    $params[] = $me;
    $types .= 'i';
  } elseif ($myRole === 'student') {
    $where[] = "a.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=?) AND a.status='active'";
    $params[] = $me;
    $types .= 'i';
  }
  if ($search) {
    $s = "%$search%";
    $where[] = "a.title LIKE ?";
    $params[] = $s;
    $types .= 's';
  }
  if ($batchId) {
    $where[] = "a.batch_id=?";
    $params[] = $batchId;
    $types .= 'i';
  }
  if ($status) {
    $where[] = "a.status=?";
    $params[] = $status;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;
  $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM assignments a $wSQL");
  if ($types) $cs->bind_param($types, ...$params);
  $cs->execute();
  $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
  $cs->close();
  $sql = "SELECT a.*,b.name as batch_name,t.title as topic_title,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as submission_count,(SELECT COUNT(*) FROM assignment_files WHERE assignment_id=a.id) as file_count FROM assignments a LEFT JOIN batches b ON a.batch_id=b.id LEFT JOIN topics t ON a.topic_id=t.id $wSQL ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  // For student: attach their submission status
  if ($myRole === 'student') {
    foreach ($items as &$a) {
      $s = $conn->prepare("SELECT id,status,marks,file_name,file_size FROM submissions WHERE assignment_id=? AND student_id=?");
      $s->bind_param('ii', $a['id'], $me);
      $s->execute();
      $a['my_submission'] = $s->get_result()->fetch_assoc();
      $s->close();
    }
  }
  jsonResponse('success', 'OK', ['assignments' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}
function listSimple()
{
  global $conn, $me, $myRole;
  $batchId = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);
  if (!$batchId) jsonResponse('success', 'OK', []);
  $items = $conn->query("SELECT id,title FROM assignments WHERE batch_id=$batchId AND status='active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);
  jsonResponse('success', 'OK', $items);
}
function getOne()
{
  global $conn;
  $id = (int)($_POST['assignment_id'] ?? $_GET['assignment_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT a.*,b.name as batch_name FROM assignments a LEFT JOIN batches b ON a.batch_id=b.id WHERE a.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $a = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$a) jsonResponse('error', 'Not found');
  $stmt = $conn->prepare("SELECT * FROM assignment_files WHERE assignment_id=? ORDER BY uploaded_at DESC");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $a['files'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', $a);
}
function saveAssignment($isUpdate)
{
  global $conn, $me;
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $batchId = (int)($_POST['batch_id'] ?? 0);
  $topicId = (int)($_POST['topic_id'] ?? 0) ?: null;
  $marks = (int)($_POST['total_marks'] ?? 100);
  $due = trim($_POST['due_date'] ?? '') ?: null;
  $late = (int)($_POST['allow_late'] ?? 0);
  $status = $_POST['status'] ?? 'active';
  if (!$title || !$batchId) jsonResponse('error', 'Title and batch required');
  $dueVal = null;
  if ($due) {
    $ts = strtotime($due);
    $dueVal = $ts ? date('Y-m-d H:i:s', $ts) : null;
  }
  if ($isUpdate) {
    $id = (int)($_POST['assignment_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid');
    $stmt = $conn->prepare("UPDATE assignments SET title=?,description=?,batch_id=?,topic_id=?,total_marks=?,due_date=?,allow_late=?,status=? WHERE id=?");
    $stmt->bind_param('ssiiisisi', $title, $desc, $batchId, $topicId, $marks, $dueVal, $late, $status, $id);
    if (!$stmt->execute()) jsonResponse('error', 'Update failed: ' . $conn->error);
    $stmt->close();
    logActivity($conn, $me, "Updated assignment: $title", 'assignments');
    jsonResponse('success', 'Assignment updated');
  } else {
    $stmt = $conn->prepare("INSERT INTO assignments (title,description,batch_id,topic_id,total_marks,due_date,allow_late,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('ssiiisisi', $title, $desc, $batchId, $topicId, $marks, $dueVal, $late, $status, $me);
    if (!$stmt->execute()) jsonResponse('error', 'Failed: ' . $conn->error);
    $id = $conn->insert_id;
    $stmt->close();
    logActivity($conn, $me, "Created assignment: $title (ID $id)", 'assignments');
    jsonResponse('success', 'Assignment created', ['assignment_id' => $id]);
  }
}
function deleteAssignment()
{
  global $conn, $me;
  $id = (int)($_POST['assignment_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $files = $conn->query("SELECT file_path FROM assignment_files WHERE assignment_id=$id")->fetch_all(MYSQLI_ASSOC);
  foreach ($files as $f) @unlink(__DIR__ . '/../uploads/assignments/' . $f['file_path']);
  $stmt = $conn->prepare("DELETE FROM assignments WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted assignment ID $id", 'assignments');
  jsonResponse('success', 'Deleted');
}
function bulkDelete()
{
  global $conn, $me;
  if (!in_array($_SESSION['role'] ?? '', ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No IDs');
  foreach ($ids as $id) {
    $files = $conn->query("SELECT file_path FROM assignment_files WHERE assignment_id=$id")->fetch_all(MYSQLI_ASSOC);
    foreach ($files as $f) @unlink(__DIR__ . '/../uploads/assignments/' . $f['file_path']);
  }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM assignments WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt assignments", 'assignments');
  jsonResponse('success', "$cnt assignment(s) deleted");
}
function toggleStatus()
{
  global $conn, $me;
  $id = (int)($_POST['assignment_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $status = $_POST['status'] ?? 'active';
  if (!in_array($status, ['active', 'inactive', 'draft'])) jsonResponse('error', 'Invalid status');
  $stmt = $conn->prepare("UPDATE assignments SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();
  $stmt->close();
  jsonResponse('success', 'Updated', ['status' => $status]);
}
function uploadFile()
{
  global $conn, $me;
  $assignId = (int)($_POST['assignment_id'] ?? 0);
  if (!$assignId) jsonResponse('error', 'Invalid assignment');
  if (empty($_FILES['file']['name'])) jsonResponse('error', 'No file provided');
  // Uses global ALLOWED_FILE_EXTENSIONS from config.php
  $v = validateUpload($_FILES['file'], ALLOWED_FILE_EXTENSIONS, UPLOAD_MAX_MB);
  if (!$v['ok']) jsonResponse('error', $v['error']);
  // Fetch assignment title for structured folder name
  $aRow = $conn->query("SELECT title FROM assignments WHERE id=$assignId")->fetch_assoc();
  $saved = saveUpload($_FILES['file'], 'assignments', ['assignment_title' => $aRow['title'] ?? 'assignment']);
  if (!$saved) jsonResponse('error', 'Failed to save');
  $fname = $_FILES['file']['name'];
  $fsize = $_FILES['file']['size'];
  $ftype = $_FILES['file']['type'];
  $stmt = $conn->prepare("INSERT INTO assignment_files (assignment_id,file_name,file_path,file_size,file_type,status,uploaded_by) VALUES (?,?,?,?,?,'active',?)");
  $stmt->bind_param('issssi', $assignId, $fname, $saved, $fsize, $ftype, $me);
  if (!$stmt->execute()) jsonResponse('error', 'DB error');
  $fid = $conn->insert_id;
  $stmt->close();
  logActivity($conn, $me, "Uploaded file to assignment $assignId", 'assignments');
  jsonResponse('success', 'File uploaded', ['file_id' => $fid, 'file_name' => $fname, 'file_path' => $saved, 'file_size' => $fsize]);
}
function deleteFile()
{
  global $conn, $me;
  $id = (int)($_POST['file_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT * FROM assignment_files WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $f = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$f) jsonResponse('error', 'Not found');
  @unlink(__DIR__ . '/../uploads/assignments/' . $f['file_path']);
  $s = $conn->prepare("DELETE FROM assignment_files WHERE id=?");
  $s->bind_param('i', $id);
  $s->execute();
  $s->close();
  jsonResponse('success', 'File deleted');
}
function bulkDeleteFiles()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['file_ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No file IDs');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("SELECT file_path FROM assignment_files WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  foreach ($files as $f) @unlink(__DIR__ . '/../uploads/assignments/' . $f['file_path']);
  $stmt = $conn->prepare("DELETE FROM assignment_files WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $cnt = $stmt->affected_rows;
  $stmt->close();
  jsonResponse('success', "$cnt file(s) deleted");
}
function toggleFileStatus()
{
  global $conn, $me;
  $id = (int)($_POST['file_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $status = $_POST['status'] ?? 'active';
  if (!in_array($status, ['active', 'inactive'])) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("UPDATE assignment_files SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();
  $stmt->close();
  jsonResponse('success', 'Updated', ['status' => $status]);
}
