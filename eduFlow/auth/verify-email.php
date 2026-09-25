<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/' . ($_SESSION['role'] ?? 'student') . '/');
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

$status = 'invalid'; // invalid | expired | already | success
$token  = trim($_GET['token'] ?? '');
$name   = '';

if ($token) {
    $stmt = $conn->prepare("
        SELECT ev.user_id, ev.expires_at, u.full_name, u.email, u.is_verified
        FROM email_verifications ev
        JOIN users u ON ev.user_id = u.id
        WHERE ev.token = ?
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $status = 'invalid';
    } elseif ($row['is_verified']) {
        $status = 'already';
        $name   = $row['full_name'];
    } elseif (strtotime($row['expires_at']) < time()) {
        $status = 'expired';
        $conn->query("DELETE FROM email_verifications WHERE user_id=" . (int)$row['user_id']);
    } else {
        $uid = (int)$row['user_id'];
        $conn->query("UPDATE users SET is_verified=1, status='active' WHERE id=$uid");
        $conn->query("DELETE FROM email_verifications WHERE user_id=$uid");
        logActivity($conn, $uid, 'Email verified — account activated', 'auth');
        // Send 'next steps' email instead of welcome — student must apply for a course first
        $stu = $conn->query("SELECT user_id_number FROM users WHERE id=$uid")->fetch_assoc();
        mailApplicationReceived($row['email'], $row['full_name'], $stu['user_id_number'] ?? '');
        $status = 'success';
        $name   = $row['full_name'];
    }
}

$titles = [
    'success' => 'Email Verified',
    'already' => 'Already Verified',
    'expired' => 'Link Expired',
    'invalid' => 'Invalid Link',
];
$pageTitle = $titles[$status] ?? 'Email Verification';
include __DIR__ . '/header.php';
?>

<div class="auth-page">
    <!-- Left panel -->
    <div class="auth-panel-left">
        <canvas id="auth-aurora" class="auth-aurora"></canvas>
        <div class="auth-brand">
            <div class="auth-brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                    <path d="M6 12v5c3 3 9 3 12 0v-5" />
                </svg>
            </div>
            <div>
                <div class="auth-brand-name">EduFlow</div>
                <div class="auth-brand-sub">Learning Management</div>
            </div>
        </div>
        <div>
            <h1 class="auth-hero-title">Email<br>Verification</h1>
            <p class="auth-hero-sub">One quick step to activate your account and start your learning journey on EduFlow.</p>
        </div>
        <div style="margin-top:36px;padding:22px;background:rgba(255,255,255,.06);border-radius:14px;border:1px solid rgba(255,255,255,.1);">
            <div style="font-size:.75rem;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">Why verify?</div>
            <?php foreach (
                [
                    ['shield-check', 'Confirms your identity and ownership of the email'],
                    ['bell',         'Enables assignment deadlines and grade notifications'],
                    ['lock',         'Allows password recovery if you ever get locked out'],
                ] as [$ico, $txt]
            ): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                    <div style="width:30px;height:30px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="<?= $ico ?>" style="width:14px;height:14px;color:rgba(255,255,255,.7);"></i>
                    </div>
                    <span style="font-size:.82rem;color:rgba(255,255,255,.55);line-height:1.5;"><?= $txt ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-panel-right">
        <div class="auth-form-wrap" style="text-align:center;">

            <?php if ($status === 'success'): ?>
                <!-- ✅ Success -->
                <div style="width:84px;height:84px;background:rgba(16,185,129,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <h1 class="auth-title" style="color:var(--success);">
                    <?= $name ? 'Welcome, ' . e(explode(' ', $name)[0]) . '!' : 'Email Verified!' ?>
                </h1>
                <p class="auth-sub" style="margin-bottom:28px;">
                    Your email is verified! Check your inbox for next steps.<br>
                    You need to <strong>apply for a course</strong> before you can log in.
                </p>
                <div style="background:var(--bg);border-radius:14px;padding:16px 20px;border:1px solid var(--border);margin-bottom:24px;text-align:left;">
                    <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">You can now</div>
                    <?php foreach (
                        [
                            ['book-open',   'Browse your assigned courses and topics'],
                            ['clipboard',   'View and submit assignments'],
                            ['bar-chart-2', 'Track your grades and progress'],
                        ] as [$ico, $txt]
                    ): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:6px 0;">
                            <div style="width:28px;height:28px;background:rgba(99,102,241,.1);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i data-lucide="<?= $ico ?>" style="width:13px;height:13px;color:var(--primary);"></i>
                            </div>
                            <span style="font-size:.84rem;color:var(--text-secondary);"><?= $txt ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-btn" style="text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Sign In Now
                </a>

            <?php elseif ($status === 'already'): ?>
                <!-- ℹ Already verified -->
                <div style="width:84px;height:84px;background:rgba(99,102,241,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
                <h1 class="auth-title">Already Verified</h1>
                <p class="auth-sub" style="margin-bottom:28px;">
                    <?= $name ? e(explode(' ', $name)[0]) . ', your' : 'Your' ?> account is already active.<br>
                    Go ahead and sign in.
                </p>
                <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-btn" style="text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Sign In
                </a>

            <?php elseif ($status === 'expired'): ?>
                <!-- ⏰ Expired -->
                <div style="width:84px;height:84px;background:rgba(245,158,11,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
                <h1 class="auth-title">Link Expired</h1>
                <p class="auth-sub" style="margin-bottom:28px;">
                    This verification link has expired.<br>
                    Links are valid for <strong>24 hours</strong> from registration.<br>
                    Register again to receive a fresh link.
                </p>
                <a href="<?= BASE_PATH ?>/auth/register.php" class="auth-btn" style="text-decoration:none;margin-bottom:12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <line x1="19" y1="8" x2="19" y2="14" />
                        <line x1="22" y1="11" x2="16" y2="11" />
                    </svg>
                    Register Again
                </a>
                <p class="auth-footer-text">Already have an account? <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-link">Sign in</a></p>

            <?php else: ?>
                <!-- ❌ Invalid -->
                <div style="width:84px;height:84px;background:rgba(239,68,68,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                </div>
                <h1 class="auth-title">Invalid Link</h1>
                <p class="auth-sub" style="margin-bottom:28px;">
                    This verification link is invalid or has already been used.<br>
                    If you haven't verified yet, please register again.
                </p>
                <div style="display:flex;gap:10px;">
                    <a href="<?= BASE_PATH ?>/auth/register.php" class="auth-btn" style="flex:1;text-decoration:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <line x1="19" y1="8" x2="19" y2="14" />
                            <line x1="22" y1="11" x2="16" y2="11" />
                        </svg>
                        Register
                    </a>
                    <a href="<?= BASE_PATH ?>/auth/login.php" class="auth-btn" style="flex:1;text-decoration:none;background:var(--bg);color:var(--primary);border:1.5px solid var(--border);box-shadow:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                        Sign In
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        initAurora('auth-aurora');
        if (window.lucide) lucide.createIcons();
    });
</script>

<?php include __DIR__ . '/footer.php'; ?>