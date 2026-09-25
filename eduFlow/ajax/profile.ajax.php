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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  case 'update':
    updateProfile();
    break;
  case 'change_password':
    changePassword();
    break;
  case 'upload_avatar':
    uploadAvatar();
    break;
  case 'update_theme':
    updateTheme();
    break;
  case 'switch_role':
    switchRole();
    break;
  default:
    jsonResponse('error', 'Unknown action');
}

function updateProfile()
{
  global $conn, $me;
  $name   = trim($_POST['full_name'] ?? '');
  $phone  = trim($_POST['phone'] ?? '');
  $gender = $_POST['gender'] ?? '';
  $bio    = trim($_POST['bio'] ?? '');
  if (!$name) jsonResponse('error', 'Name required');

  // ── Students cannot change gender — it is locked at registration ──
  // Fetch actual assigned roles (not current_role which can switch)
  $roleStmt = $conn->prepare(
    "SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?"
  );
  $roleStmt->bind_param('i', $me);
  $roleStmt->execute();
  $myRoles = array_column($roleStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name');
  $roleStmt->close();

  $isPureStudent = in_array('student', $myRoles) && !in_array('teacher', $myRoles) && !in_array('admin', $myRoles);

  if ($isPureStudent) {
    // Ignore any gender submitted — re-use whatever is already in DB
    $g = $conn->prepare("SELECT gender FROM users WHERE id=?");
    $g->bind_param('i', $me);
    $g->execute();
    $gender = $g->get_result()->fetch_assoc()['gender'] ?? '';
    $g->close();
  }

  $stmt = $conn->prepare("UPDATE users SET full_name=?,phone=?,gender=?,bio=? WHERE id=?");
  $stmt->bind_param('ssssi', $name, $phone, $gender, $bio, $me);
  if (!$stmt->execute()) jsonResponse('error', 'Update failed');
  $stmt->close();
  logActivity($conn, $me, 'Updated profile', 'profile');
  jsonResponse('success', 'Profile updated');
}

function changePassword()
{
  global $conn, $me;
  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  if (!$current || !$new || !$confirm) jsonResponse('error', 'All fields required');
  if ($new !== $confirm) jsonResponse('error', 'New passwords do not match');
  if (strlen($new) < 8) jsonResponse('error', 'Password must be at least 8 characters');
  $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
  $stmt->bind_param('i', $me);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$u || !password_verify($current, $u['password'])) jsonResponse('error', 'Current password is incorrect');
  $hash = password_hash($new, PASSWORD_BCRYPT);
  $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
  $stmt->bind_param('si', $hash, $me);
  if (!$stmt->execute()) jsonResponse('error', 'Failed');
  $stmt->close();
  logActivity($conn, $me, 'Changed password', 'profile');
  jsonResponse('success', 'Password changed successfully');
}

function uploadAvatar()
{
  global $conn, $me;
  if (empty($_FILES['avatar']['name'])) jsonResponse('error', 'No file provided');
  $v = validateUpload($_FILES['avatar'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5);
  if (!$v['ok']) jsonResponse('error', $v['error']);
  // Get current user info for structured folder + delete old avatar
  $uRow = $conn->query("SELECT full_name, user_id_number, profile_picture FROM users WHERE id=$me")->fetch_assoc();
  $saved = saveUpload($_FILES['avatar'], 'profiles', [
    'user_id'        => $me,
    'user_name'      => $uRow['full_name'] ?? 'user',
    'user_id_number' => $uRow['user_id_number'] ?? null,
    'old_path'       => $uRow['profile_picture'] ?? null,
  ]);
  if (!$saved) jsonResponse('error', 'Failed to save');
  $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
  $stmt->bind_param('si', $saved, $me);
  if (!$stmt->execute()) jsonResponse('error', 'DB error');
  $stmt->close();
  logActivity($conn, $me, 'Updated avatar', 'profile');
  jsonResponse('success', 'Avatar updated', ['filename' => $saved, 'url' => BASE_PATH . '/uploads/profiles/' . $saved]);
}

function updateTheme()
{
  global $conn, $me;
  $theme = in_array($_POST['theme'] ?? '', ['light', 'dark', 'system']) ? $_POST['theme'] : 'system';
  $stmt = $conn->prepare("UPDATE users SET theme_preference=? WHERE id=?");
  $stmt->bind_param('si', $theme, $me);
  $stmt->execute();
  $stmt->close();
  $_SESSION['theme'] = $theme;
  jsonResponse('success', 'Theme updated');
}

function switchRole()
{
  global $conn, $me;
  $role = trim($_POST['role'] ?? '');
  if (!in_array($role, ['admin', 'teacher', 'student'])) jsonResponse('error', 'Invalid role');
  // Verify user has this role
  $stmt = $conn->prepare("SELECT ur.id FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE ur.user_id=? AND r.name=?");
  $stmt->bind_param('is', $me, $role);
  $stmt->execute();
  if (!$stmt->get_result()->num_rows) jsonResponse('error', 'You do not have this role');
  $stmt->close();
  $stmt = $conn->prepare("UPDATE users SET `current_role`=? WHERE id=?");
  $stmt->bind_param('si', $role, $me);
  $stmt->execute();
  $stmt->close();
  $_SESSION['role'] = $role;
  logActivity($conn, $me, "Switched role to $role", 'profile');
  jsonResponse('success', "Switched to $role", ['role' => $role, 'redirect' => BASE_PATH . '/' . $role . '/']);
}
