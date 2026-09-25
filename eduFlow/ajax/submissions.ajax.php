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
    listSubs();
    break;
  case 'get_one':
    getOne();
    break;
  case 'grade':
    gradeSub();
    break;
  case 'submit':
    submitSub();
    break;
  case 'delete':
    deleteSub();
    break;
  case 'bulk_delete':
    bulkDelete();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}
function listSubs()
{
  global $conn, $me, $myRole;
  $page = (int)($_POST['page'] ?? 1);
  $perPage = min(100, (int)($_POST['per_page'] ?? 15));
  $search = trim($_POST['search'] ?? '');
  $batchId = (int)($_POST['batch_id'] ?? 0);
  $status = trim($_POST['status'] ?? '');
  $assignId = (int)($_POST['assignment_id'] ?? 0);
  $where = [];
  $params = [];
  $types = '';
  if ($myRole === 'teacher') {
    $where[] = "a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)";
    $params[] = $me;
    $types .= 'i';
  } elseif ($myRole === 'student') {
    $where[] = "s.student_id=?";
    $params[] = $me;
    $types .= 'i';
  }
  if ($search) {
    $sv = "%$search%";
    $where[] = "(u.full_name LIKE ? OR a.title LIKE ?)";
    $params[] = $sv;
    $params[] = $sv;
    $types .= 'ss';
  }
  if ($batchId) {
    $where[] = "a.batch_id=?";
    $params[] = $batchId;
    $types .= 'i';
  }
  if ($assignId) {
    $where[] = "s.assignment_id=?";
    $params[] = $assignId;
    $types .= 'i';
  }
  if ($status) {
    $where[] = "s.status=?";
    $params[] = $status;
    $types .= 's';
  }
  $wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $offset = ($page - 1) * $perPage;
  $cs = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions s JOIN assignments a ON s.assignment_id=a.id JOIN users u ON s.student_id=u.id $wSQL");
  if ($types) $cs->bind_param($types, ...$params);
  $cs->execute();
  $total = (int)$cs->get_result()->fetch_assoc()['cnt'];
  $cs->close();
  $sql = "SELECT s.*,u.full_name as student_name,a.title as assignment_title,a.total_marks,b.name as batch_name
        FROM submissions s JOIN assignments a ON s.assignment_id=a.id JOIN users u ON s.student_id=u.id
        LEFT JOIN batches b ON a.batch_id=b.id $wSQL ORDER BY s.submitted_at DESC LIMIT ? OFFSET ?";
  $allT = $types . 'ii';
  $allP = array_merge($params, [$perPage, $offset]);
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($allT, ...$allP);
  $stmt->execute();
  $subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  jsonResponse('success', 'OK', ['submissions' => $subs, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}
function getOne()
{
  global $conn;
  $id = (int)($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  $stmt = $conn->prepare("SELECT s.*,u.full_name as student_name,a.title as assignment_title,a.total_marks,b.name as batch_name, gb.full_name as graded_by_name FROM submissions s JOIN assignments a ON s.assignment_id=a.id JOIN users u ON s.student_id=u.id LEFT JOIN batches b ON a.batch_id=b.id LEFT JOIN users gb ON s.graded_by=gb.id WHERE s.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $sub = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$sub) jsonResponse('error', 'Not found');
  jsonResponse('success', 'OK', $sub);
}
function gradeSub()
{
  global $conn, $me, $myRole;
  if (!in_array($myRole, ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
  $id = (int)($_POST['submission_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid ID');
  $marks    = $_POST['marks'] ?? '';
  $feedback = trim($_POST['feedback'] ?? '');
  $status   = $_POST['status'] ?? 'graded';
  if (!in_array($status, ['graded', 'returned'])) jsonResponse('error', 'Invalid status');

  $stmt = $conn->prepare("SELECT a.total_marks FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE s.id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $sub = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$sub) jsonResponse('error', 'Submission not found');

  // Allow floats (e.g. 9.5, 0.34); reject negatives; reject > total_marks
  $marksVal = null;
  if ($marks !== '') {
    $marksVal = (float)$marks;
    if ($marksVal < 0) jsonResponse('error', 'Marks cannot be negative');
    if ($marksVal > (float)$sub['total_marks']) {
      jsonResponse('error', 'Marks exceed total (' . $sub['total_marks'] . ')');
    }
    // Round to 2 decimal places for storage
    $marksVal = round($marksVal, 2);
  }

  // If returning to student: clear marks so they can resubmit cleanly
  if ($status === 'returned') {
    $stmt = $conn->prepare("UPDATE submissions SET marks=?,feedback=?,status='returned',graded_at=NOW(),graded_by=? WHERE id=?");
    $stmt->bind_param('dsii', $marksVal, $feedback, $me, $id);
  } else {
    $stmt = $conn->prepare("UPDATE submissions SET marks=?,feedback=?,status='graded',graded_at=NOW(),graded_by=? WHERE id=?");
    $stmt->bind_param('dsii', $marksVal, $feedback, $me, $id);
  }
  if (!$stmt->execute()) jsonResponse('error', 'Failed to save grade: ' . $conn->error);
  $stmt->close();
  logActivity($conn, $me, "Graded submission ID $id → $status (marks: $marksVal)", 'submissions');
  jsonResponse('success', 'Grade saved successfully');
}
function submitSub()
{
  global $conn, $me, $myRole;
  if ($myRole !== 'student') jsonResponse('error', 'Students only');
  $assignId = (int)($_POST['assignment_id'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');
  if (!$assignId) jsonResponse('error', 'Invalid assignment');
  // Check assignment exists and overdue
  $stmt = $conn->prepare("SELECT due_date,allow_late,status FROM assignments WHERE id=?");
  $stmt->bind_param('i', $assignId);
  $stmt->execute();
  $assign = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$assign || $assign['status'] !== 'active') jsonResponse('error', 'Assignment not available');
  if ($assign['due_date'] && strtotime($assign['due_date']) < time() && !$assign['allow_late']) {
    jsonResponse('error', 'This assignment is overdue and does not accept late submissions');
  }
  // ONE-TIME ONLY: check if already submitted
  $stmt = $conn->prepare("SELECT id,status FROM submissions WHERE assignment_id=? AND student_id=?");
  $stmt->bind_param('ii', $assignId, $me);
  $stmt->execute();
  $existing = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($existing && $existing['status'] === 'submitted') jsonResponse('error', 'You have already submitted this assignment. Submissions are final.');
  if ($existing && $existing['status'] === 'graded') jsonResponse('error', 'This assignment has been graded. You cannot resubmit.');
  // 'returned' status means teacher sent it back — student can resubmit with a new file
  // ── FILE IS REQUIRED — reject empty submissions server-side ──
  if (empty($_FILES['file']['name']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE || $_FILES['file']['size'] === 0) {
    jsonResponse('error', 'A file attachment is required. Please upload your work before submitting.');
  }
  $fileName = $filePath = $fileSize = null;
  if (!empty($_FILES['file']['name'])) {
    $v = validateUpload($_FILES['file'], ALLOWED_FILE_EXTENSIONS, UPLOAD_MAX_MB);
    if (!$v['ok']) jsonResponse('error', $v['error']);
    // Fetch student info + assignment title for structured folder
    $stuRow = $conn->query("SELECT full_name, user_id_number FROM users WHERE id=$me")->fetch_assoc();
    $aTitle = $conn->query("SELECT title FROM assignments WHERE id=$assignId")->fetch_assoc();
    $saved = saveUpload($_FILES['file'], 'submissions', [
      'student_id'        => $me,
      'student_name'      => $stuRow['full_name'] ?? 'student',
      'student_id_number' => $stuRow['user_id_number'] ?? null,
      'assignment_title'  => $aTitle['title'] ?? 'assignment',
    ]);
    if (!$saved) jsonResponse('error', 'Failed to save file');
    $fileName = $_FILES['file']['name'];
    $filePath = $saved;
    $fileSize = $_FILES['file']['size'];
  }
  $isLate = $assign['due_date'] && strtotime($assign['due_date']) < time() ? 1 : 0;
  if ($existing) {
    $stmt = $conn->prepare("UPDATE submissions SET file_name=?,file_path=?,file_size=?,notes=?,status='submitted',is_late=?,submitted_at=NOW() WHERE id=?");
    $stmt->bind_param('ssssii', $fileName, $filePath, $fileSize, $notes, $isLate, $existing['id']);
  } else {
    $stmt = $conn->prepare("INSERT INTO submissions (assignment_id,student_id,file_name,file_path,file_size,notes,status,is_late) VALUES (?,?,?,?,?,?,'submitted',?)");
    $stmt->bind_param('iissssi', $assignId, $me, $fileName, $filePath, $fileSize, $notes, $isLate);
  }
  if (!$stmt->execute()) jsonResponse('error', 'Submission failed: ' . $conn->error);
  $stmt->close();
  logActivity($conn, $me, "Submitted assignment ID $assignId" . ($isLate ? ' (late)' : ''), 'submissions');
  jsonResponse('success', 'Assignment submitted successfully');
}
function deleteSub()
{
  global $conn, $me, $myRole;
  if (!in_array($myRole, ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
  $id = (int)($_POST['submission_id'] ?? 0);
  if (!$id) jsonResponse('error', 'Invalid');
  $stmt = $conn->prepare("SELECT file_path FROM submissions WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $f = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($f && $f['file_path']) @unlink(__DIR__ . '/../uploads/submissions/' . $f['file_path']);
  $s = $conn->prepare("DELETE FROM submissions WHERE id=?");
  $s->bind_param('i', $id);
  $s->execute();
  $s->close();
  logActivity($conn, $me, "Deleted submission ID $id", 'submissions');
  jsonResponse('success', 'Deleted');
}
function bulkDelete()
{
  global $conn, $me, $myRole;
  if (!in_array($myRole, ['admin', 'teacher'])) jsonResponse('error', 'Access denied');
  $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
  if (empty($ids)) jsonResponse('error', 'No IDs');
  $ph = implode(',', array_fill(0, count($ids), '?'));
  $tp = str_repeat('i', count($ids));
  $stmt = $conn->prepare("SELECT file_path FROM submissions WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  foreach ($files as $f) if ($f['file_path']) @unlink(__DIR__ . '/../uploads/submissions/' . $f['file_path']);
  $stmt = $conn->prepare("DELETE FROM submissions WHERE id IN ($ph)");
  $stmt->bind_param($tp, ...$ids);
  $stmt->execute();
  $cnt = $stmt->affected_rows;
  $stmt->close();
  logActivity($conn, $me, "Bulk deleted $cnt submissions", 'submissions');
  jsonResponse('success', "$cnt submission(s) deleted");
}