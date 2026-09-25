<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';


function respond($status, $message, $data = [])
{
    ob_end_clean();
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$action = trim($_POST['action'] ?? '');

/* ════════════════════════════════════════════════
   REGISTER — CNIC + unique ID + credentials email
════════════════════════════════════════════════ */
if ($action === 'register') {
    $name  = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $cnic  = trim($_POST['cnic'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $conf  = $_POST['confirm_password'] ?? '';

    // ── Basic validation ──────────────────────────
    if (!$name || !$email || !$cnic || !$pass)
        respond('error', 'All fields are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        respond('error', 'Invalid email address.');
    if (strlen($pass) < 8)
        respond('error', 'Password must be at least 8 characters.');
    if ($pass !== $conf)
        respond('error', 'Passwords do not match.');
    if (!in_array($gender, ['male', 'female', 'other']))
        respond('error', 'Please select your gender.');

    // ── CNIC validation ───────────────────────────
    // Accept XXXXX-XXXXXXX-X or 13 raw digits, normalize to XXXXX-XXXXXXX-X
    $cnicClean = preg_replace('/[^0-9]/', '', $cnic);
    if (strlen($cnicClean) !== 13)
        respond('error', 'CNIC must be 13 digits (format: XXXXX-XXXXXXX-X).');
    $cnicFormatted = substr($cnicClean, 0, 5) . '-' . substr($cnicClean, 5, 7) . '-' . substr($cnicClean, 12, 1);

    // ── Duplicate checks ──────────────────────────
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) respond('error', 'This email is already registered.');
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM users WHERE cnic=?");
    $stmt->bind_param('s', $cnicFormatted);
    $stmt->execute();
    if ($stmt->get_result()->num_rows)
        respond('error', 'An account with this CNIC already exists. Each CNIC can only be used once.');
    $stmt->close();

    // ── Generate unique 5-digit student ID ────────
    $studentId = null;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
        $chk = $conn->prepare("SELECT id FROM users WHERE user_id_number=?");
        $chk->bind_param('s', $candidate);
        $chk->execute();
        $exists = $chk->get_result()->num_rows;
        $chk->close();
        if (!$exists) {
            $studentId = $candidate;
            break;
        }
    }
    if (!$studentId) respond('error', 'Could not generate a unique ID. Please try again.');

    // ── Insert user ───────────────────────────────
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare(
        "INSERT INTO users (full_name,email,password,cnic,user_id_number,gender,status,is_verified,`current_role`)
         VALUES (?,?,?,?,?,?,'inactive',0,'student')"
    );
    $stmt->bind_param('ssssss', $name, $email, $hash, $cnicFormatted, $studentId, $gender);
    if (!$stmt->execute()) respond('error', 'Registration failed. Please try again.');
    $uid = $conn->insert_id;
    $stmt->close();

    // Assign student role
    $s = $conn->prepare("INSERT INTO user_roles (user_id,role_id,assigned_by) VALUES (?,1,?)");
    $s->bind_param('ii', $uid, $uid);
    $s->execute();
    $s->close();

    // ── Email verification token ──────────────────
    $vToken  = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
    $conn->query("DELETE FROM email_verifications WHERE user_id=$uid");
    $vs = $conn->prepare("INSERT INTO email_verifications (user_id,token,expires_at) VALUES (?,?,?)");
    $vs->bind_param('iss', $uid, $vToken, $expires);
    $vs->execute();
    $vs->close();

    logActivity($conn, $uid, "New registration: $name (ID: $studentId)", 'auth');

    // ── Send credentials email ────────────────────
    $result = mailVerificationWithCredentials($email, $name, $vToken, $studentId, $cnicFormatted, $pass);

    $responseData = [
        'email'      => $email,
        'student_id' => $studentId,
        'cnic'       => $cnicFormatted,
        'name'       => $name,
    ];

    if ($result['ok']) {
        respond('success', 'registered', $responseData);
    } else {
        $responseData['dev_link'] = BASE_PATH . '/auth/verify-email.php?token=' . urlencode($vToken);
        respond('success', 'registered_nomail', $responseData);
    }
}

/* ════════════════════════════════════════════════
   SEND OTP — forgot password step 1
════════════════════════════════════════════════ */
if ($action === 'send_otp') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        respond('error', 'Please enter a valid email address.');

    $stmt = $conn->prepare("SELECT id,full_name FROM users WHERE email=? AND status='active' AND is_verified=1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Always respond identically (prevent email enumeration)
    if ($user) {
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $expires = date('Y-m-d H:i:s', time() + 900);
        $uid     = (int)$user['id'];
        $conn->query("DELETE FROM password_resets WHERE user_id=$uid");
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id,token,expires_at,is_used) VALUES (?,?,?,0)");
        $stmt->bind_param('iss', $uid, $otp, $expires);
        $stmt->execute();
        $stmt->close();
        logActivity($conn, $uid, 'Password reset OTP sent', 'auth');

        $result = mailPasswordOTP($email, $user['full_name'], $otp);
        if (!$result['ok']) {
            // Dev fallback — return OTP in response so dev can test
            respond('success', 'otp_sent', ['email' => $email, 'dev_otp' => $otp]);
        }
    }
    respond('success', 'otp_sent', ['email' => $email]);
}

/* ════════════════════════════════════════════════
   VERIFY OTP — forgot password step 2
════════════════════════════════════════════════ */
if ($action === 'verify_otp') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $otp   = trim($_POST['otp'] ?? '');

    if (!$email || !$otp)
        respond('error', 'Email and code are required.');
    if (!preg_match('/^\d{6}$/', $otp))
        respond('error', 'Enter the 6-digit code from your email.');

    // Rate limit — max 5 attempts tracked in session
    $key = 'otp_attempts_' . md5($email);
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    if ($_SESSION[$key] > 5) {
        unset($_SESSION[$key]);
        respond('error', 'Too many incorrect attempts. Please request a new code.');
    }

    $stmt = $conn->prepare(
        "SELECT pr.user_id FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE u.email=? AND pr.token=? AND pr.expires_at > NOW() AND pr.is_used=0"
    );
    $stmt->bind_param('ss', $email, $otp);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) respond('error', 'Incorrect or expired code. Try again.');

    // OTP is valid — store a short-lived reset token in session
    $resetToken = bin2hex(random_bytes(16));
    $_SESSION['pr_token'] = $resetToken;
    $_SESSION['pr_uid']   = (int)$row['user_id'];
    $_SESSION['pr_exp']   = time() + 900; // 15 min
    unset($_SESSION[$key]); // clear attempt counter

    respond('success', 'otp_valid', ['reset_token' => $resetToken]);
}

/* ════════════════════════════════════════════════
   RESET PASSWORD — step 3
════════════════════════════════════════════════ */
if ($action === 'reset_password') {
    $resetToken = trim($_POST['reset_token'] ?? '');
    $pass       = $_POST['new_password'] ?? '';
    $conf       = $_POST['confirm_password'] ?? '';

    // Validate session token
    if (
        empty($_SESSION['pr_token']) ||
        empty($_SESSION['pr_uid']) ||
        $_SESSION['pr_token'] !== $resetToken ||
        ($_SESSION['pr_exp'] ?? 0) < time()
    ) {
        respond('error', 'Session expired. Please start over.');
    }

    if (strlen($pass) < 8) respond('error', 'Password must be at least 8 characters.');
    if ($pass !== $conf)   respond('error', 'Passwords do not match.');

    $uid  = (int)$_SESSION['pr_uid'];
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $s    = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $s->bind_param('si', $hash, $uid);
    $s->execute();
    $s->close();
    $conn->query("DELETE FROM password_resets WHERE user_id=$uid");

    logActivity($conn, $uid, 'Password reset completed', 'auth');
    unset($_SESSION['pr_token'], $_SESSION['pr_uid'], $_SESSION['pr_exp']);
    respond('success', 'Password reset successfully! You can now sign in.');
}

respond('error', 'Unknown action.');
