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
    listTopics();
    break;
  case 'list_simple':
    listSimple();
    break;
  case 'get_one':
    getOne();
    break;
  case 'create':
    saveTopic(false);
    break;
  case 'update':
    saveTopic(true);
    break;
  case 'delete':
    deleteTopic();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  case 'reorder':
    reorderTopics();
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
  case 'get_topic_assignments':
    getTopicAssignments();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}
function listTopics()
{
  global $conn, $me, $myRole;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 10));
  $batchId = (int)($_POST['batch_id'] ?? 0);
  $status = trim($_POST['status'] ?? '');
  $search = trim($_POST['search'] ?? '');
  $where = [];
  $params = [];
  $types = '';
  if ($myRole === 'student') {
    $where[] = "(t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=?) OR t.batch_id IS NULL)";
    $params[] = $me;
    $types .= 'i';
  } elseif ($myRole === 'teacher') {
    $where[] = "(t.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?) OR t.batch_id IS NULL)";
    $params[] = $me;
    $types .= 'i';
  }
  if ($batchId == -1) {
    $where[] = "t.batch_id IS NULL";
  } elseif ($batchId) {
    $where[] = "t.batch_id=?";
    $params[] = $batchId;
    $types .= 'i';
  }
  if ($status) {
    $where[] = "t.status=?";
    $params[] = $status;
    $types .= 's';
  }
  if ($search) {
    $s = "%$search%";
    $where[] = "t.title LIKE ?";
    $params[] = $s;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;
  $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM topics t $wSQL");
  if ($types) $cs->bind_param($types, ...$params);
  $cs->execute();
  $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
  $cs->close();
  $sql = "SELECT t.*,b.name as batch_name,(SELECT COUNT(*) FROM topic_files WHERE topic_id=t.id) as file_count,(SELECT COUNT(*) FROM assignments WHERE topic_id=t.id AND status='active') as assignment_count FROM topics t LEFT JOIN batches b ON t.batch_id=b.id $wSQL ORDER BY COALESCE(t.batch_id,0),t.sort_order,t.created_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['topics' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}
function listSimple()
{
  global $conn;
  $batchId = (int)($_POST['batch_id'] ?? $_GET['batch_id'] ?? 0);
  if (!$batchId) jsonResponse('success', 'OK', []);
  $items = $conn->query("SELECT id,title FROM topics WHERE (batch_id=$batchId OR batch_id IS NULL) AND status='active' ORDER BY sort_order,title")->fetch_all(MYSQLI_ASSOC);
  jsonResponse('success', 'OK', $items);
}
function getOne()
{
  global $conn;
  $id = (int)($_POST['topic_id'] ?? $_GET['topic_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT t.*,b.name as batch_name FROM topics t LEFT JOIN batches b ON t.batch_id=b.id WHERE t.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $t = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$t) jsonResponse('error', 'Not found');
  $stmt = $conn->prepare("SELECT * FROM topic_files WHERE topic_id=? ORDER BY uploaded_at DESC");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $t['files'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', $t);
}
function saveTopic($isUpdate)
{
  global $conn, $me;
  $title = trim($_POST['title'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  $batchId = (int)($_POST['batch_id'] ?? 0) ?: null;
  $sortOrder = (int)($_POST['sort_order'] ?? 0);
  $status = $_POST['status'] ?? 'active';
  if (!$title) jsonResponse('error', 'Title required');
  if ($isUpdate) {
    $id = (int)($_POST['topic_id'] ?? 0);
    if (!$id) jsonResponse('error', 'Invalid');
    $stmt = $conn->prepare("UPDATE topics SET title=?,description=?,batch_id=?,sort_order=?,status=? WHERE id=?");
    $stmt->bind_param('ssissi', $title, $desc, $batchId, $sortOrder, $status, $id);
    if (!$stmt->execute()) jsonResponse('error', 'Failed');
    $stmt->close();
    logActivity($conn, $me, "Updated topic: $title", 'topics');
    jsonResponse('success', 'Topic updated');
  } else {
    $stmt = $conn->prepare("INSERT INTO topics (title,description,batch_id,sort_order,status,created_by) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssiisi', $title, $desc, $batchId, $sortOrder, $status, $me);
    if (!$stmt->execute()) jsonResponse('error', 'Failed: ' . $conn->error);
    $id = $conn->insert_id;
    $stmt->close();
    logActivity($conn, $me, "Created topic: $title", 'topics');
    jsonResponse('success', 'Topic created', ['topic_id' => $id]);
  }
}
function deleteTopic()
{
  global $conn, $me;
  $id = (int)($_POST['topic_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $files = $conn->query("SELECT file_path FROM topic_files WHERE topic_id=$id")->fetch_all(MYSQLI_ASSOC);
  foreach ($files as $f) @unlink(__DIR__ . '/../uploads/topics/' . $f['file_path']);
  $stmt = $conn->prepare("DELETE FROM topics WHERE id=?");
  $stmt->bind_param('i', $id);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, "Deleted topic ID $id", 'topics');
  jsonResponse('success', 'Topic deleted');
}
function bulkDelete()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No IDs');
  foreach ($ids as $id) {
    $files = $conn->query("SELECT file_path FROM topic_files WHERE topic_id=$id")->fetch_all(MYSQLI_ASSOC);
    foreach ($files as $f) @unlink(__DIR__ . '/../uploads/topics/' . $f['file_path']);
  }
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("DELETE FROM topics WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt topics", 'topics');
  jsonResponse('success', "$cnt topic(s) deleted");
}
function reorderTopics()
{
  global $conn;
  $orders = json_decode($_POST['orders'] ?? '[]', true);
  foreach ($orders as $o) {
    $id = (int)$o['id'];
    $sort = (int)$o['sort'];
    $conn->query("UPDATE topics SET sort_order=$sort WHERE id=$id");
  }
  jsonResponse('success', 'Reordered');
}
function uploadFile()
{
  global $conn, $me;
  $topicId = (int)($_POST['topic_id'] ?? 0);
  if (!$topicId) jsonResponse('error', 'Invalid topic');
  if (empty($_FILES['file']['name'])) jsonResponse('error', 'No file provided');
  // Uses global ALLOWED_FILE_EXTENSIONS from config.php
  $v = validateUpload($_FILES['file'], ALLOWED_FILE_EXTENSIONS, UPLOAD_MAX_MB);
  if (!$v['ok']) jsonResponse('error', $v['error']);
  // Fetch topic title for structured folder name
  $topicRow = $conn->query("SELECT title FROM topics WHERE id=$topicId")->fetch_assoc();
  $saved = saveUpload($_FILES['file'], 'topics', ['topic_title' => $topicRow['title'] ?? 'topic']);
  if (!$saved) jsonResponse('error', 'Failed to save');
  $fname = $_FILES['file']['name'];
  $fsize = $_FILES['file']['size'];
  $ftype = $_FILES['file']['type'];
  $stmt = $conn->prepare("INSERT INTO topic_files (topic_id,file_name,file_path,file_size,file_type,status,uploaded_by) VALUES (?,?,?,?,?,'active',?)");
  $stmt->bind_param('issssi', $topicId, $fname, $saved, $fsize, $ftype, $me);
  if (!$stmt->execute()) jsonResponse('error', 'DB error');
  $fid = $conn->insert_id;
  $stmt->close();
  logActivity($conn, $me, "Uploaded file to topic $topicId", 'topics');
  jsonResponse('success', 'File uploaded', ['file_id' => $fid, 'file_name' => $fname, 'file_path' => $saved, 'file_size' => $fsize]);
}
function deleteFile()
{
  global $conn, $me;
  $id = (int)($_POST['file_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT * FROM topic_files WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $f = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$f) jsonResponse('error', 'Not found');
  @unlink(__DIR__ . '/../uploads/topics/' . $f['file_path']);
  $s = $conn->prepare("DELETE FROM topic_files WHERE id=?");
  $s->bind_param('i', $id);
  $s->execute();
  $s->close();
  logActivity($conn, $me, "Deleted topic file $id", 'topics');
  jsonResponse('success', 'File deleted');
}
function bulkDeleteFiles()
{
  global $conn, $me;
  $ids = array_filter(array_map('intval', explode(',', $_POST['file_ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No file IDs');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("SELECT file_path FROM topic_files WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  foreach ($files as $f) @unlink(__DIR__ . '/../uploads/topics/' . $f['file_path']);
  $stmt = $conn->prepare("DELETE FROM topic_files WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt topic files", 'topics');
  jsonResponse('success', "$cnt file(s) deleted");
}
function toggleFileStatus()
{
  global $conn, $me;
  $id = (int)($_POST['file_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $status = $_POST['status'] ?? 'active';
  if (!in_array($status, ['active', 'inactive'])) jsonResponse('error', 'Invalid status');
  $stmt = $conn->prepare("UPDATE topic_files SET status=? WHERE id=?");
  $stmt->bind_param('si', $status, $id);
  $stmt->execute();
  $stmt->close();
  jsonResponse('success', 'Updated', ['status' => $status]);
}

function getTopicAssignments()
{
  global $conn, $me;
  $topicId = (int)($_POST['topic_id'] ?? 0);
  if (!$topicId) jsonResponse('error', 'Invalid topic');
  // fetch student uid from session for submission status
  $stmt = $conn->prepare("
    SELECT a.*,b.name as batch_name,
      (SELECT COUNT(*) FROM assignment_files WHERE assignment_id=a.id AND status='active') as file_count,
      sub.status as sub_status, sub.marks as sub_marks, sub.id as sub_id
    FROM assignments a
    LEFT JOIN batches b ON a.batch_id=b.id
    LEFT JOIN submissions sub ON sub.assignment_id=a.id AND sub.student_id=?
    WHERE a.topic_id=? AND a.status='active'
    ORDER BY a.due_date ASC, a.created_at DESC
  ");
  $stmt->bind_param('ii', $me, $topicId);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', $rows);
}