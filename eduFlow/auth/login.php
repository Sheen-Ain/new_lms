<?php
// ============================================================
// AUTH — LOGIN PAGE
// Includes: Entry Test button (admin-activated), Apply for Course,
//           Login gate check for students (application status),
//           Block modals (no_application / need_test / pending / rejected)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/' . ($_SESSION['role'] ?? 'student') . '/');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '';
$msg      = $_GET['msg'] ?? '';

// ── Check for gate-blocked state (set by POST handler below) ─
$gateStatus = null;
if (!empty($_GET['gate']) && !empty($_SESSION['pending_gate'])) {
    $gateStatus = $_SESSION['pending_gate'];
    unset($_SESSION['pending_gate']);
}

// ── STUDENT GATE CHECK HELPER ─────────────────────────────────
function checkStudentGate($conn, $userId)
{
    // Check bypass_gate first — admin can grant dashboard access without an application
    $bypassCheck = $conn->query("SELECT bypass_gate FROM users WHERE id=$userId")->fetch_assoc();
    if ($bypassCheck && (int)$bypassCheck['bypass_gate'] === 1) {
        return ['blocked' => false];
    }

    // Get most recent application
    $stmt = $conn->prepare("
        SELECT ca.id, ca.status, ca.rejection_reason, ca.batch_id,
               b.name AS batch_name, c.title AS course_title
        FROM course_applications ca
        JOIN batches b ON ca.batch_id  = b.id
        JOIN courses c ON b.course_id  = c.id
        WHERE ca.user_id = ?
        ORDER BY ca.applied_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        return ['blocked' => true, 'status' => 'no_application'];
    }

    if ($app['status'] === 'approved') {
        return ['blocked' => false];
    }

    if ($app['status'] === 'rejected') {
        return [
            'blocked' => true,
            'status'  => 'rejected',
            'course'  => $app['course_title'],
            'batch'   => $app['batch_name'],
            'reason'  => $app['rejection_reason'] ?? '',
        ];
    }

    // Pending or test_submitted — check if they've attempted the entry test
    $stmt = $conn->prepare("
        SELECT ta.id, ta.status
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.student_id = ? AND t.batch_id = ? AND t.type = 'entry'
        ORDER BY ta.started_at DESC LIMIT 1
    ");
    $stmt->bind_param('ii', $userId, $app['batch_id']);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$attempt || $attempt['status'] === 'in_progress') {
        return [
            'blocked' => true,
            'status'  => 'need_test',
            'course'  => $app['course_title'],
            'batch'   => $app['batch_name'],
        ];
    }

    return [
        'blocked' => true,
        'status'  => 'pending_review',
        'course'  => $app['course_title'],
        'batch'   => $app['batch_name'],
    ];
}

// ── POST — FORM LOGIN ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your Student ID or email, and password.';
    } else {
        $isId  = preg_match('/^\d{5}$/', $email);
        $field = $isId ? 'u.user_id_number' : 'u.email';

        $stmt = $conn->prepare("
            SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) AS all_roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE $field = ? GROUP BY u.id LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user)
            $error = $isId ? 'No account found with that Student ID.' : 'No account found with that email.';
        elseif (!password_verify($password, $user['password']))
            $error = 'Incorrect password. Please try again.';
        elseif (!$user['is_verified'])
            $error = 'Please verify your email address before logging in.';
        elseif ($user['status'] !== 'active')
            $error = 'Your account is inactive. Please contact support.';
        else {
            $roles = array_filter(explode(',', $user['all_roles'] ?? ''));

            // Gate check for pure students (no admin/teacher roles)
            $isPureStudent = !in_array('admin', $roles) && !in_array('teacher', $roles);

            if ($isPureStudent) {
                $gate = checkStudentGate($conn, (int)$user['id']);
                if ($gate['blocked']) {
                    // Don't start LMS session — just redirect with gate data
                    session_regenerate_id(true);
                    $_SESSION['pending_gate'] = $gate;
                    header('Location: ' . BASE_PATH . '/auth/login.php?gate=1');
                    exit;
                }
            }

            // Normal login success
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['current_role'] ?: ($roles[0] ?? 'student');
            $_SESSION['theme']   = $user['theme_preference'];

            $stmt = $conn->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE id=?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }

            logActivity($conn, $user['id'], 'Logged in', 'auth');
            $dest = !empty($redirect) ? $redirect : BASE_PATH . '/' . $_SESSION['role'] . '/';
            header('Location: ' . $dest);
            exit;
        }
    }
}

// ── Check if entry test is active (for the animated button) ──
$activeEntryTest = $conn->query("
    SELECT t.id, t.title, t.time_minutes, b.name AS batch_name, c.title AS course_title,
           (SELECT COUNT(*) FROM test_question_map WHERE test_id=t.id) AS question_count
    FROM tests t
    JOIN batches b ON t.batch_id  = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE t.type='entry' AND t.entry_active=1 AND t.status='active'
    LIMIT 1
")->fetch_assoc();

$pageTitle = 'Sign In';
include __DIR__ . '/header.php';
?>

<style>
/* ── Demo button ────────────────────────────────────────────── */
.auth-btn-demo {
    margin-top: 4px;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    box-shadow: 0 4px 16px rgba(37,99,235,0.25);
    border: 1px dashed rgba(255,255,255,0.35);
}
.auth-btn-demo:hover {
    box-shadow: 0 6px 22px rgba(37,99,235,0.4);
}
.auth-demo-hint {
    margin-top: 6px;
    font-size: 0.78rem;
    color: var(--text-muted);
    text-align: center;
}
.auth-input.demo-filled {
    outline: 2px solid #10b981;
    outline-offset: 1px;
}

/* ── Entry Test Card (Animated Glow Border) ────────────────── */
.et-card-wrap {
    margin-top: 20px;
}

.et-card {
    position: relative;
    border-radius: 14px;
    padding: 2px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899, #6366f1);
    background-size: 300% 300%;
    animation: gradientShift 3s ease infinite;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    display: block;
}

.et-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(99,102,241,0.4);
}

.et-card:active { transform: scale(0.98); }

@keyframes gradientShift {
    0%   { background-position: 0% 50%; }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.et-card-inner {
    background: var(--bg-card);
    border-radius: 13px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.et-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(99,102,241,0.35);
    animation: iconPulse 2s ease infinite;
}

@keyframes iconPulse {
    0%, 100% { box-shadow: 0 4px 14px rgba(99,102,241,0.35); }
    50%       { box-shadow: 0 4px 22px rgba(99,102,241,0.6); }
}

.et-icon-wrap svg { width:22px; height:22px; stroke:#fff; fill:none; stroke-width:2; stroke-linecap:round; }

.et-text { flex: 1; min-width: 0; }

.et-live-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.2);
    color: #dc2626;
    font-size: 0.62rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    width: fit-content;
}

.et-live-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #dc2626;
    animation: pulseDot 1.2s ease infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.4; transform: scale(1.5); }
}

.et-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text);
    margin-bottom: 2px;
}

.et-sub {
    font-size: 0.72rem;
    color: var(--text-muted);
}

.et-arrow {
    color: var(--primary);
    flex-shrink: 0;
}

/* ── Apply for Course Button ───────────────────────────────── */
.apply-btn {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.15s;
    text-decoration: none;
    margin-top: 10px;
}

.apply-btn:hover {
    border-color: var(--primary);
    background: rgba(99,102,241,0.04);
}

.apply-btn-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(16,185,129,0.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.apply-btn-icon svg { width:20px;height:20px;stroke:#10b981;fill:none;stroke-width:2;stroke-linecap:round; }

.apply-btn-text { flex:1; text-align:left; }
.apply-btn-title { font-weight:700; font-size:0.875rem; color:var(--text); }
.apply-btn-sub   { font-size:0.72rem; color:var(--text-muted); margin-top:1px; }

/* ── Gate block modals ─────────────────────────────────────── */
.gate-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.gate-card {
    background: var(--bg-card);
    border-radius: 24px;
    padding: 40px 36px;
    max-width: min(420px, calc(100vw - 36px));
    width: 100%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.35s cubic-bezier(0.34,1.56,0.64,1);
}

@keyframes slideUp {
    from { opacity:0; transform:translateY(30px) scale(0.95); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}

.gate-lottie {
    width: 140px;
    height: 140px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gate-icon-fallback {
    font-size: 72px;
    line-height: 1;
    animation: floatIcon 3s ease-in-out infinite;
}

@keyframes floatIcon {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-8px); }
}

.gate-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: 1.2rem;
    color: var(--text);
    margin-bottom: 8px;
}

.gate-sub {
    font-size: 0.875rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin-bottom: 6px;
}

.gate-course-chip {
    display: inline-block;
    background: rgba(99,102,241,0.1);
    color: var(--primary);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 99px;
    padding: 4px 14px;
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 22px;
}

.gate-reason {
    background: rgba(239,68,68,0.06);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.82rem;
    color: var(--text-secondary);
    text-align: left;
    margin-bottom: 22px;
    line-height: 1.6;
}

.gate-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ── Entry Test Modal ──────────────────────────────────────── */
.et-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(8px);
    z-index: 8000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.et-modal-overlay.open {
    display: flex;
    animation: fadeIn 0.25s ease;
}

.et-modal {
    background: var(--bg-card);
    border-radius: 22px;
    padding: 32px;
    max-width: min(440px, calc(100vw - 36px));
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1);
    max-height: 90vh;
    overflow-y: auto;
}

.et-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.et-modal-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    color: var(--text);
}

.et-close-btn {
    width: 32px; height: 32px;
    border: none; background: var(--bg);
    border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted);
    transition: background 0.15s;
}

.et-close-btn:hover { background: var(--border); }

/* ── Custom Searchable Dropdown ────────────────────────────── */
.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius, 10px);
    background: var(--bg-input, #f8fafc);
    color: var(--text);
    font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: border-color 0.15s;
    user-select: none;
}

.custom-select-trigger:focus,
.custom-select.open .custom-select-trigger {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}

.custom-select-trigger .placeholder { color: var(--text-muted); }

.custom-select-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0; right: 0;
    background: var(--bg-card);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg, 12px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    z-index: 100;
    display: none;
    overflow: hidden;
}

.custom-select.open .custom-select-dropdown { display: block; animation: dropDown 0.18s ease; }

@keyframes dropDown {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}

.cs-search {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
}

.cs-search input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--bg);
    font-size: 0.84rem;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    outline: none;
    transition: border-color 0.15s;
}

.cs-search input:focus { border-color: var(--primary); }

.cs-list {
    max-height: 200px;
    overflow-y: auto;
    padding: 6px;
}

.cs-item {
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.12s;
    font-size: 0.875rem;
    color: var(--text);
}

.cs-item:hover  { background: var(--bg); }
.cs-item.active { background: rgba(99,102,241,0.1); color: var(--primary); font-weight: 600; }

.cs-item-sub {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.cs-empty {
    padding: 16px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.84rem;
}
</style>

<div class="auth-page">
  <!-- Left panel -->
  <div class="auth-panel-left">
    <canvas id="auth-aurora" class="auth-aurora"></canvas>
    <div class="auth-panel-content">
      <div class="auth-brand">
        <div class="auth-brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
          </svg>
        </div>
        <div>
          <div class="auth-brand-name">EduFlow</div>
          <div class="auth-brand-sub">Learning Management</div>
        </div>
      </div>

      <div>
        <h1 class="auth-hero-title">Welcome back.<br>Let's learn.</h1>
        <p class="auth-hero-sub">Sign in and pick up right where you left off — courses, assignments and grades all in one place.</p>
      </div>

      <div style="margin-top:8px;">
        <?php foreach ([
          ['layers',          'Organised courses, batches &amp; topics'],
          ['clipboard-check', 'Assignments, submissions &amp; instant grading'],
          ['video',           'Live sessions with waiting room control'],
          ['bar-chart-2',     'Real-time progress analytics'],
        ] as [$icon, $text]): ?>
          <div class="auth-feature">
            <div class="auth-feature-icon">
              <i data-lucide="<?= $icon ?>" style="width:18px;height:18px;color:rgba(255,255,255,.8);"></i>
            </div>
            <span class="auth-feature-text"><?= $text ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="auth-stats">
        <div><div class="auth-stat-num">5+</div><div class="auth-stat-lbl">Modules</div></div>
        <div><div class="auth-stat-num">3</div><div class="auth-stat-lbl">Role levels</div></div>
        <div><div class="auth-stat-num">24/7</div><div class="auth-stat-lbl">Access</div></div>
      </div>
    </div>
  </div>

  <!-- Right form -->
  <div class="auth-panel-right">
    <div class="auth-form-wrap">
      <div class="auth-form-logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
          <polyline points="10 17 15 12 10 7"/>
          <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
      </div>
      <h1 class="auth-title">Sign in</h1>
      <p class="auth-sub">Enter your credentials to access your dashboard</p>

      <?php if ($msg === 'logged_out'): ?>
        <div class="auth-alert auth-alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          You've been signed out successfully.
        </div>
      <?php elseif ($msg === 'registered'): ?>
        <div class="auth-alert auth-alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Account created! Check your email to verify before signing in.
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="auth-alert auth-alert-error" id="login-err">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="login-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif; ?>

        <div style="margin-bottom:18px;">
          <label class="auth-label" for="email">Student ID or Email</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <input type="text" id="email" name="email" class="auth-input<?= $error ? ' error' : '' ?>" placeholder="12345 or you@example.com" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username" autofocus>
          </div>
          <div class="auth-field-err" id="err-email"></div>
        </div>

        <div style="margin-bottom:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
            <label class="auth-label" for="password" style="margin-bottom:0;">Password</label>
            <a href="<?= BASE_PATH ?>/auth/forgot-password.php" class="auth-link" style="font-size:.8rem;">Forgot?</a>
          </div>
          <div class="auth-input-wrap">
            <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <input type="password" id="password" name="password" class="auth-input has-right<?= $error ? ' error' : '' ?>" placeholder="Enter your password" autocomplete="current-password">
            <button type="button" class="auth-input-eye" id="eye-pass" onclick="toggleEye('password','eye-pass')">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="auth-field-err" id="err-pass"></div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
          <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:.85rem;color:var(--text-secondary);">
            <div class="toggle" style="flex-shrink:0;">
              <input type="checkbox" name="remember" <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </div>
            Remember me for 30 days
          </label>
        </div>

        <button type="submit" class="auth-btn" id="login-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          <span id="login-btn-text">Sign In</span>
        </button>

        <!-- Demo: one-click autofill of admin credentials -->
        <button type="button" class="auth-btn auth-btn-demo" id="demo-btn" onclick="fillDemoCredentials()">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
          <span>Use Demo Admin</span>
        </button>
        <p class="auth-footer-text auth-demo-hint">
          Demo: auto-fills the admin credentials for a quick preview.
        </p>

        <p class="auth-footer-text">
          Don't have an account? <a href="<?= BASE_PATH ?>/auth/register.php" class="auth-link">Create one</a>
        </p>
      </form>

      <!-- Divider -->
      <div class="auth-divider">or</div>

      <!-- ── Entry Test Button (only when admin has one active) ── -->
      <?php if ($activeEntryTest): ?>
      <div class="et-card-wrap">
        <a class="et-card" onclick="openEntryTestModal()" href="javascript:void(0)">
          <div class="et-card-inner">
            <div class="et-icon-wrap">
              <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </div>
            <div class="et-text">
              <div class="et-live-badge">
                <span class="et-live-dot"></span> Live Now
              </div>
              <div class="et-title">Attempt Entry Test</div>
              <div class="et-sub"><?= e($activeEntryTest['course_title']) ?> · <?= $activeEntryTest['time_minutes'] ?> min · <?= $activeEntryTest['question_count'] ?> questions</div>
            </div>
            <div class="et-arrow">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
          </div>
        </a>
      </div>
      <?php endif; ?>

      <!-- ── Apply for a Course Button (always visible) ── -->
      <button class="apply-btn" onclick="openApplyModal()">
        <div class="apply-btn-icon">
          <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div class="apply-btn-text">
          <div class="apply-btn-title">Apply for a Course</div>
          <div class="apply-btn-sub">Already registered? Apply for an open batch</div>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="color:var(--text-muted);"><polyline points="9 18 15 12 9 6"/></svg>
      </button>

    </div><!-- /.auth-form-wrap -->
  </div><!-- /.auth-panel-right -->
</div><!-- /.auth-page -->


<!-- ═══════════════════════════════════════════════════════════
     ENTRY TEST MODAL — Student ID + Password + Batch
═══════════════════════════════════════════════════════════ -->
<div class="et-modal-overlay" id="et-modal-overlay">
  <div class="et-modal">
    <div class="et-modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div class="et-modal-title">Entry Test</div>
      </div>
      <button class="et-close-btn" onclick="closeEntryTestModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Lottie / icon area -->
    <div style="text-align:center;margin-bottom:20px;">
      <div style="font-size:56px;animation:floatIcon 3s ease-in-out infinite;display:inline-block;">✏️</div>
      <!-- To use Lottie: replace above with:
      <lottie-player src="<?= BASE_PATH ?>/assets/lottie/entry-test.json"
        background="transparent" speed="1" style="width:120px;height:120px;margin:0 auto;"
        autoplay loop></lottie-player>
      And add: <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
      -->
      <div style="font-size:0.78rem;color:var(--text-muted);margin-top:8px;">Sign in to attempt the entry test</div>
    </div>

    <div class="auth-alert auth-alert-error" id="et-error" style="display:none;"></div>

    <div style="margin-bottom:14px;">
      <label class="auth-label">Student ID or Email</label>
      <div class="auth-input-wrap">
        <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <input type="text" id="et-identifier" class="auth-input" placeholder="12345 or you@example.com" autocomplete="off">
      </div>
    </div>

    <div style="margin-bottom:14px;">
      <label class="auth-label">Password</label>
      <div class="auth-input-wrap">
        <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        <input type="password" id="et-password" class="auth-input has-right" placeholder="Your password" autocomplete="current-password">
        <button type="button" class="auth-input-eye" onclick="toggleEye('et-password', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div style="margin-bottom:20px;">
      <label class="auth-label">Batch / Course</label>
      <div class="custom-select" id="et-batch-select">
        <div class="custom-select-trigger" onclick="toggleCustomSelect('et-batch-select')" tabindex="0">
          <span id="et-batch-label" class="placeholder">Select your batch…</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="custom-select-dropdown">
          <div class="cs-search"><input type="text" placeholder="Search batch…" oninput="filterCustomSelect('et-batch-select', this.value)"></div>
          <div class="cs-list" id="et-batch-list">
            <div class="cs-empty">Loading…</div>
          </div>
        </div>
      </div>
      <input type="hidden" id="et-batch-id">
    </div>

    <button class="auth-btn" onclick="submitEntryTestAuth()" id="et-submit-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      <span id="et-submit-text">Enter Test</span>
    </button>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     APPLY FOR COURSE MODAL
═══════════════════════════════════════════════════════════ -->
<div class="et-modal-overlay" id="apply-modal-overlay">
  <div class="et-modal">
    <div class="et-modal-header">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        </div>
        <div class="et-modal-title">Apply for a Course</div>
      </div>
      <button class="et-close-btn" onclick="closeApplyModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <p style="font-size:0.84rem;color:var(--text-muted);margin-bottom:20px;line-height:1.6;">
      Already have an EduFlow account? Verify your identity and apply for an open batch.
    </p>

    <div class="auth-alert auth-alert-success" id="apply-success" style="display:none;"></div>
    <div class="auth-alert auth-alert-error"   id="apply-error"   style="display:none;"></div>

    <div id="apply-form">
      <div style="margin-bottom:14px;">
        <label class="auth-label">Student ID or Email</label>
        <div class="auth-input-wrap">
          <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          <input type="text" id="apply-identifier" class="auth-input" placeholder="12345 or you@example.com" autocomplete="off">
        </div>
      </div>

      <div style="margin-bottom:14px;">
        <label class="auth-label">CNIC <span style="font-size:0.72rem;color:var(--text-muted);font-weight:400;">(for identity verification)</span></label>
        <div class="auth-input-wrap">
          <span class="auth-input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span>
          <input type="text" id="apply-cnic" class="auth-input" placeholder="XXXXX-XXXXXXX-X" maxlength="15" autocomplete="off">
        </div>
      </div>

      <div style="margin-bottom:20px;">
        <label class="auth-label">Select Batch</label>
        <div class="custom-select" id="apply-batch-select">
          <div class="custom-select-trigger" onclick="toggleCustomSelect('apply-batch-select')" tabindex="0">
            <span id="apply-batch-label" class="placeholder">Select a batch…</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <div class="custom-select-dropdown">
            <div class="cs-search"><input type="text" placeholder="Search batch…" oninput="filterCustomSelect('apply-batch-select', this.value)"></div>
            <div class="cs-list" id="apply-batch-list">
              <div class="cs-empty">Loading…</div>
            </div>
          </div>
        </div>
        <input type="hidden" id="apply-batch-id">
      </div>

      <button class="auth-btn" onclick="submitApplication()" id="apply-submit-btn"
              style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 20px rgba(16,185,129,.35);">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="apply-submit-text">Submit Application</span>
      </button>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     GATE BLOCK MODALS (rendered only when triggered)
═══════════════════════════════════════════════════════════ -->
<?php if ($gateStatus): ?>
<div class="gate-overlay" id="gate-overlay">
  <div class="gate-card">

    <?php if ($gateStatus['status'] === 'no_application'): ?>
      <div class="gate-lottie"><div class="gate-icon-fallback">📋</div></div>
      <h2 class="gate-title">Apply for a Course First</h2>
      <p class="gate-sub">You haven't applied for any course yet. Apply for a batch to get started.</p>
      <div class="gate-actions">
        <button class="auth-btn" onclick="document.getElementById('gate-overlay').remove(); openApplyModal();"
                style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 16px rgba(16,185,129,.3);">
          Apply for a Course →
        </button>
        <button onclick="document.getElementById('gate-overlay').remove();" style="background:none;border:none;color:var(--text-muted);font-size:0.84rem;cursor:pointer;padding:8px;">
          Back to Login
        </button>
      </div>

    <?php elseif ($gateStatus['status'] === 'need_test'): ?>
      <div class="gate-lottie"><div class="gate-icon-fallback">✏️</div></div>
      <h2 class="gate-title">Attempt the Entry Test</h2>
      <p class="gate-sub">You've applied for a course but haven't completed the entry test yet.</p>
      <div class="gate-course-chip"><?= e($gateStatus['course'] ?? '') ?></div>
      <p class="gate-sub" style="font-size:0.78rem;">The entry test button appears on the login page when admin activates it.</p>
      <div class="gate-actions">
        <?php if ($activeEntryTest): ?>
        <button class="auth-btn" onclick="document.getElementById('gate-overlay').remove(); openEntryTestModal();">
          Attempt Entry Test ✏️
        </button>
        <?php else: ?>
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:10px;padding:12px 16px;font-size:0.82rem;color:var(--text-secondary);margin-bottom:8px;">
          ⏳ No entry test is currently active. Check back later or contact admin.
        </div>
        <?php endif; ?>
        <button onclick="document.getElementById('gate-overlay').remove();" style="background:none;border:none;color:var(--text-muted);font-size:0.84rem;cursor:pointer;padding:8px;">
          Back to Login
        </button>
      </div>

    <?php elseif ($gateStatus['status'] === 'pending_review'): ?>
      <div class="gate-lottie"><div class="gate-icon-fallback">⏳</div></div>
      <h2 class="gate-title">Under Review</h2>
      <p class="gate-sub">You've completed the entry test. Admin is reviewing your result.</p>
      <div class="gate-course-chip"><?= e($gateStatus['course'] ?? '') ?></div>
      <p class="gate-sub" style="font-size:0.78rem;">You'll receive an email notification once a decision is made. This usually takes 1-3 business days.</p>
      <div class="gate-actions">
        <button onclick="document.getElementById('gate-overlay').remove();" class="auth-btn" style="background:linear-gradient(135deg,#0891b2,#0284c7);box-shadow:0 4px 16px rgba(6,182,212,.3);">
          Got it
        </button>
      </div>

    <?php elseif ($gateStatus['status'] === 'rejected'): ?>
      <div class="gate-lottie"><div class="gate-icon-fallback">😔</div></div>
      <h2 class="gate-title">Application Not Approved</h2>
      <p class="gate-sub">Your application for <strong><?= e($gateStatus['course'] ?? '') ?></strong> was not approved this time.</p>
      <?php if (!empty($gateStatus['reason'])): ?>
      <div class="gate-reason"><strong style="display:block;margin-bottom:4px;color:var(--text-secondary);">Reason from Admin:</strong><?= e($gateStatus['reason']) ?></div>
      <?php else: ?>
      <div class="gate-reason">No specific reason provided. Please contact admin for more information.</div>
      <?php endif; ?>
      <p class="gate-sub" style="font-size:0.78rem;">You may re-apply when the next batch opens.</p>
      <div class="gate-actions">
        <button class="auth-btn" onclick="document.getElementById('gate-overlay').remove(); openApplyModal();"
                style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 16px rgba(16,185,129,.3);">
          Apply for Next Batch →
        </button>
        <button onclick="document.getElementById('gate-overlay').remove();" style="background:none;border:none;color:var(--text-muted);font-size:0.84rem;cursor:pointer;padding:8px;">
          Back to Login
        </button>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>


<script>
const APP_URL  = '<?= BASE_PATH ?>';
const APPS_URL = APP_URL + '/ajax/applications.ajax.php';
const ET_URL   = APP_URL + '/ajax/entry-test.ajax.php';

// ── Batch data (loaded once) ─────────────────────────────────
let allBatches     = [];
let etBatches      = []; // batches specifically with active entry test
let batchesLoaded  = false;

async function loadBatches() {
    if (batchesLoaded) return;
    const res = await fetch(APPS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_open_batches'
    }).then(r => r.json()).catch(() => null);

    if (res && res.status === 'success') {
        allBatches = res.data.batches || [];
    }
    batchesLoaded = true;
}

// ── Custom Searchable Dropdown ────────────────────────────────
function buildDropdownItems(containerId, items, selectedId, onSelect) {
    const list = document.getElementById(containerId);
    if (!list) return;
    if (!items.length) {
        list.innerHTML = '<div class="cs-empty">No batches available</div>';
        return;
    }
    list.innerHTML = items.map(b =>
        `<div class="cs-item${b.id == selectedId ? ' active' : ''}" data-id="${b.id}"
              data-label="${escHtml(b.course_title + ' — ' + b.name)}"
              onclick="selectCustomItem('${containerId.replace('-list', '-select')}', ${b.id}, '${escHtml(b.course_title + ' — ' + b.name)}')">
            ${escHtml(b.course_title)}
            <div class="cs-item-sub">${escHtml(b.name)}</div>
        </div>`
    ).join('');
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function toggleCustomSelect(wrapperId) {
    const el = document.getElementById(wrapperId);
    const isOpen = el.classList.contains('open');
    // Close all first
    document.querySelectorAll('.custom-select.open').forEach(e => e.classList.remove('open'));
    if (!isOpen) {
        el.classList.add('open');
        const searchInput = el.querySelector('.cs-search input');
        if (searchInput) { searchInput.value = ''; searchInput.focus(); }
    }
}

function selectCustomItem(wrapperId, id, label) {
    const el = document.getElementById(wrapperId);
    el.querySelector('.custom-select-trigger span').textContent = label;
    el.querySelector('.custom-select-trigger span').classList.remove('placeholder');

    // Set hidden input
    const suffix = wrapperId.replace('-select', '-id').replace('et-batch', 'et-batch').replace('apply-batch', 'apply-batch');
    const hiddenId = wrapperId === 'et-batch-select' ? 'et-batch-id' : 'apply-batch-id';
    document.getElementById(hiddenId).value = id;

    el.classList.remove('open');

    // Update active state
    el.querySelectorAll('.cs-item').forEach(item => {
        item.classList.toggle('active', item.dataset.id == id);
    });
}

function filterCustomSelect(wrapperId, query) {
    const listId = wrapperId.replace('-select', '-list');
    const list   = document.getElementById(listId);
    const q      = query.toLowerCase().trim();
    if (!list) return;
    list.querySelectorAll('.cs-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
    const visible = list.querySelectorAll('.cs-item[style=""],.cs-item:not([style])');
    const emptyEl = list.querySelector('.cs-empty');
    if (emptyEl) emptyEl.style.display = [...list.querySelectorAll('.cs-item')].every(i => i.style.display==='none') ? '' : 'none';
}

// Close dropdowns on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('.custom-select')) {
        document.querySelectorAll('.custom-select.open').forEach(el => el.classList.remove('open'));
    }
});

// ── Entry Test Modal ──────────────────────────────────────────
async function openEntryTestModal() {
    document.getElementById('et-modal-overlay').classList.add('open');
    document.getElementById('et-error').style.display = 'none';
    document.getElementById('et-identifier').value = '';
    document.getElementById('et-password').value   = '';
    document.getElementById('et-batch-id').value   = '';
    document.getElementById('et-batch-label').textContent = 'Select your batch…';
    document.getElementById('et-batch-label').classList.add('placeholder');

    // Load entry test batches
    const res = await fetch(APPS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_active_entry_test'
    }).then(r => r.json()).catch(() => null);

    etBatches = (res && res.status === 'success' && res.data.batches) ? res.data.batches : [];
    buildDropdownItems('et-batch-list', etBatches, null, null);

    document.getElementById('et-identifier').focus();
}

function closeEntryTestModal() {
    document.getElementById('et-modal-overlay').classList.remove('open');
}

async function submitEntryTestAuth() {
    const identifier = document.getElementById('et-identifier').value.trim();
    const password   = document.getElementById('et-password').value;
    const batchId    = document.getElementById('et-batch-id').value;
    const errEl      = document.getElementById('et-error');

    errEl.style.display = 'none';

    if (!identifier || !password || !batchId) {
        showEtError('Please fill in all fields and select a batch.');
        return;
    }

    const btn      = document.getElementById('et-submit-btn');
    const btnText  = document.getElementById('et-submit-text');
    btn.disabled   = true;
    btnText.textContent = 'Verifying…';

    const body = new URLSearchParams({ action: 'auth', identifier, password, batch_id: batchId });
    const res  = await fetch(ET_URL, { method: 'POST', body }).then(r => r.json()).catch(() => null);

    btn.disabled = false;
    btnText.textContent = 'Enter Test';

    if (!res || res.status !== 'success') {
        showEtError(res?.message || 'Something went wrong. Please try again.');
        return;
    }

    // Redirect to entry test page
    window.location.href = res.data.redirect || (APP_URL + '/auth/entry-test.php');
}

function showEtError(msg) {
    const el = document.getElementById('et-error');
    el.textContent = msg;
    el.style.display = 'flex';
}

// ── Apply Modal ───────────────────────────────────────────────
async function openApplyModal() {
    document.getElementById('apply-modal-overlay').classList.add('open');
    document.getElementById('apply-error').style.display   = 'none';
    document.getElementById('apply-success').style.display = 'none';
    document.getElementById('apply-form').style.display    = 'block';
    document.getElementById('apply-identifier').value = '';
    document.getElementById('apply-cnic').value        = '';
    document.getElementById('apply-batch-id').value    = '';
    document.getElementById('apply-batch-label').textContent = 'Select a batch…';
    document.getElementById('apply-batch-label').classList.add('placeholder');

    await loadBatches();
    buildDropdownItems('apply-batch-list', allBatches, null, null);
    document.getElementById('apply-identifier').focus();
}

function closeApplyModal() {
    document.getElementById('apply-modal-overlay').classList.remove('open');
}

async function submitApplication() {
    const identifier = document.getElementById('apply-identifier').value.trim();
    const cnic       = document.getElementById('apply-cnic').value.trim();
    const batchId    = document.getElementById('apply-batch-id').value;
    const errEl      = document.getElementById('apply-error');
    const sucEl      = document.getElementById('apply-success');

    errEl.style.display = 'none';
    sucEl.style.display = 'none';

    if (!identifier || !cnic || !batchId) {
        errEl.textContent = 'Please fill in all fields and select a batch.';
        errEl.style.display = 'flex';
        return;
    }

    const btn     = document.getElementById('apply-submit-btn');
    const btnText = document.getElementById('apply-submit-text');
    btn.disabled  = true;
    btnText.textContent = 'Submitting…';

    const body = new URLSearchParams({ action: 'apply_for_batch', identifier, cnic, batch_id: batchId });
    const res  = await fetch(APPS_URL, { method: 'POST', body }).then(r => r.json()).catch(() => null);

    btn.disabled = false;
    btnText.textContent = 'Submit Application';

    if (!res || res.status !== 'success') {
        errEl.textContent = res?.message || 'Something went wrong. Please try again.';
        errEl.style.display = 'flex';
        return;
    }

    // Success
    document.getElementById('apply-form').style.display = 'none';
    sucEl.innerHTML = `✅ Application submitted successfully for <strong>${escHtml(res.data.course)}</strong>! Watch for an entry test notification from admin.`;
    sucEl.style.display = 'flex';
}

// ── CNIC auto-format ──────────────────────────────────────────
document.getElementById('apply-cnic').addEventListener('input', function () {
    let val = this.value.replace(/[^0-9]/g, '');
    if (val.length > 5)  val = val.slice(0,5)  + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0,13) + '-' + val.slice(13);
    if (val.length > 15) val = val.slice(0,15);
    this.value = val;
});

// ── Demo: autofill admin credentials ──────────────────────────
function fillDemoCredentials() {
    const emailInput = document.getElementById('email');
    const passInput  = document.getElementById('password');
    if (!emailInput || !passInput) return;

    const DEMO_EMAIL    = 'admin@lms.com';
    const DEMO_PASSWORD = 'Admin@1234';

    emailInput.value = DEMO_EMAIL;
    passInput.value  = DEMO_PASSWORD;

    // Clear any stale inline validation
    document.getElementById('err-email').textContent = '';
    document.getElementById('err-pass').textContent  = '';

    // Highlight the filled password briefly so the user sees the autofill
    passInput.classList.add('demo-filled');
    setTimeout(() => passInput.classList.remove('demo-filled'), 1200);

    const form = document.getElementById('login-form');
    if (form && typeof form.requestSubmit === 'function') {
        form.requestSubmit(); // runs the existing submit handler + validation
    } else {
        form.submit(); // fallback for older browsers
    }
}

// ── Login form submit ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initAurora('auth-aurora');

    const form = document.getElementById('login-form');
    form.addEventListener('submit', e => {
        const email = document.getElementById('email').value.trim();
        const pass  = document.getElementById('password').value;
        let ok = true;
        document.getElementById('err-email').textContent = '';
        document.getElementById('err-pass').textContent  = '';
        if (!email) {
            document.getElementById('err-email').textContent = 'Student ID or email is required';
            ok = false;
        } else if (!/^\d{5}$/.test(email) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            document.getElementById('err-email').textContent = 'Enter a 5-digit Student ID or valid email';
            ok = false;
        }
        if (!pass) {
            document.getElementById('err-pass').textContent = 'Password is required';
            ok = false;
        }
        if (!ok) {
            e.preventDefault();
            form.style.animation = 'shake .4s ease';
            setTimeout(() => form.style.animation = '', 400);
            return;
        }
        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        document.getElementById('login-btn-text').textContent = 'Signing in…';
        btn.insertAdjacentHTML('afterbegin', '<span class="auth-spinner" style="margin-right:8px;"></span>');
    });

    <?php if ($error): ?>
    document.getElementById('login-form').style.animation = 'shake .4s ease';
    setTimeout(() => document.getElementById('login-form').style.animation = '', 400);
    <?php endif; ?>
});

// Enter key on ET modal
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && document.getElementById('et-modal-overlay').classList.contains('open')) {
        submitEntryTestAuth();
    }
    if (e.key === 'Escape') {
        closeEntryTestModal();
        closeApplyModal();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>