<?php
// ============================================================
// ENTRY TEST PAGE — Test-taking UI
// Requires $_SESSION['entry_test'] to exist
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// No LMS auth — uses its own session key
if (empty($_SESSION['entry_test'])) {
    // Check if result just arrived
    if (!empty($_SESSION['entry_test_result'])) {
        // Show result-only page (no test UI)
        $result = $_SESSION['entry_test_result'];
        unset($_SESSION['entry_test_result']);
    } else {
        header('Location: ' . BASE_PATH . '/auth/login.php');
        exit;
    }
}

$etSession   = $_SESSION['entry_test'] ?? null;
$showResult  = isset($result);

if ($etSession) {
    // Verify attempt is still in_progress
    $attempt = $conn->query("SELECT status FROM test_attempts WHERE id={$etSession['attempt_id']}")->fetch_assoc();
    if ($attempt && $attempt['status'] !== 'in_progress') {
        // Already submitted — show result if possible
        $result = $conn->query("
            SELECT score, percentage, total_questions, correct_count, wrong_count, unanswered_count
            FROM test_attempts WHERE id={$etSession['attempt_id']}
        ")->fetch_assoc();
        unset($_SESSION['entry_test']);
        $showResult = true;
        $etSession  = null;
    }
}

$pageTitle = 'Entry Test';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entry Test — EduFlow LMS</title>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/assets/eduflow_icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
    <script>
        (function() {
            try {
                const t = localStorage.getItem('lms_theme') || 'system';
                if (t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme:dark)').matches))
                    document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
        window.LMS_BASE = '<?= BASE_PATH ?>';
    </script>
    <style>
        /* ── Layout ──────────────────────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
        }

        .et-page {
            display: grid;
            grid-template-rows: 56px 1fr;
            height: 100svh;
            overflow: hidden;
        }

        /* ── Top Bar ─────────────────────────────────────────────────── */
        .et-topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            flex-shrink: 0;
        }

        .et-topbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            color: var(--primary);
            letter-spacing: -0.03em;
        }

        .et-topbar-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .et-topbar-student {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: right;
        }

        /* ── Body grid: sidebar + main ────────────────────────────────── */
        .et-body {
            display: grid;
            grid-template-columns: 240px 1fr;
            overflow: hidden;
            height: 100%;
        }

        /* ── Sidebar ─────────────────────────────────────────────────── */
        .et-sidebar {
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 20px 16px;
            overflow-y: auto;
            gap: 20px;
        }

        /* Timer */
        .et-timer-wrap {
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            text-align: center;
        }

        .et-timer-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .et-timer {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 2px;
            line-height: 1;
            transition: color 0.3s;
        }

        .et-timer.warning {
            color: #f59e0b;
        }

        .et-timer.danger {
            color: #ef4444;
            animation: timerPulse 0.5s ease infinite alternate;
        }

        @keyframes timerPulse {
            from {
                opacity: 1;
            }

            to {
                opacity: 0.5;
            }
        }

        /* Stats */
        .et-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .et-stat {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }

        .et-stat-val {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--primary);
        }

        .et-stat-lbl {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 2px;
        }

        /* Question Navigator */
        .et-nav-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .et-qnav {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 5px;
        }

        .et-qbtn {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 7px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            font-family: 'Poppins', sans-serif;
        }

        .et-qbtn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .et-qbtn.current {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .et-qbtn.answered {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .et-qbtn.answered.current {
            background: var(--success);
            color: #fff;
            border-color: var(--success);
        }

        /* Submit button (sidebar) */
        .et-submit-side {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
            margin-top: auto;
        }

        .et-submit-side:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* ── Main Question Area ───────────────────────────────────────── */
        .et-main {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .et-qarea {
            flex: 1;
            overflow-y: auto;
            padding: 32px;
        }

        .et-q-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .et-q-badge {
            font-family: 'Poppins', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }

        .et-q-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary, #8b5cf6));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .et-q-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
            line-height: 1.7;
            margin-bottom: 24px;
            white-space: pre-wrap;
            /* preserves code formatting */
        }

        .et-q-text.is-code {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 0.9rem;
            overflow-x: auto;
        }

        /* Options */
        .et-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .et-option {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.15s;
            background: var(--bg-card);
            user-select: none;
        }

        .et-option:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(99, 102, 241, 0.04);
        }

        .et-option.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }

        .et-option-letter {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--bg);
            border: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            font-family: 'Poppins', sans-serif;
            color: var(--text-muted);
            flex-shrink: 0;
            transition: all 0.15s;
        }

        .et-option.selected .et-option-letter {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .et-option-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.5;
            padding-top: 3px;
        }

        /* ── Navigation bar ──────────────────────────────────────────── */
        .et-nav-bar {
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }

        .et-nav-btn {
            padding: 9px 20px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            font-family: 'Poppins', sans-serif;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .et-nav-btn:hover:not(:disabled) {
            border-color: var(--primary);
            color: var(--primary);
        }

        .et-nav-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .et-q-progress {
            font-size: 0.84rem;
            color: var(--text-muted);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        /* ── Overlays & Modals ───────────────────────────────────────── */
        .et-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .et-dialog {
            background: var(--bg-card);
            border-radius: 22px;
            padding: 36px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: dialogIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes dialogIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .et-dialog-icon {
            font-size: 54px;
            margin-bottom: 16px;
        }

        .et-dialog-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--text);
            margin-bottom: 10px;
        }

        .et-dialog-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .et-dialog-text strong {
            color: var(--text-secondary);
        }

        .et-dialog-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .et-dialog-btn {
            width: 100%;
            padding: 12px 20px;
            border-radius: 12px;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
        }

        .et-dialog-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .et-btn-primary {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: #fff;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
        }

        .et-btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35);
        }

        .et-btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.35);
        }

        .et-btn-ghost {
            background: var(--bg);
            color: var(--text-muted);
            border: 1.5px solid var(--border);
        }

        /* Result: circular score */
        .result-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 8px solid;
            position: relative;
        }

        .result-score {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            line-height: 1;
        }

        .result-pct {
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.7;
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
        }

        .result-stat {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
        }

        .result-stat-val {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
        }

        .result-stat-lbl {
            font-size: 0.68rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 2px;
        }

        /* Rules list */
        .rules-list {
            text-align: left;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        .rules-list li {
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.7;
            padding: 4px 0;
            border-bottom: 1px solid var(--border);
            list-style: none;
            padding-left: 20px;
            position: relative;
        }

        .rules-list li:last-child {
            border-bottom: none;
        }

        .rules-list li::before {
            content: '•';
            position: absolute;
            left: 4px;
            color: var(--primary);
            font-weight: 900;
        }

        /* Spinner */
        .spin-sm {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            animation: spinAnim 0.7s linear infinite;
            display: inline-block;
        }

        @keyframes spinAnim {
            to {
                transform: rotate(360deg);
            }
        }

        /* Loading overlay */
        .et-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 16px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .et-body {
                grid-template-columns: 1fr;
            }

            .et-sidebar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php if ($showResult && isset($result)): ?>
        <!-- ═══════════ RESULT-ONLY VIEW ════════════ -->
        <div style="min-height:100svh;display:flex;align-items:center;justify-content:center;padding:20px;background:var(--bg);">
            <div style="max-width:420px;width:100%;background:var(--bg-card);border-radius:24px;padding:36px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
                <?php
                $pct   = (float)$result['percentage'];
                $band  = $pct >= 90 ? 'Superb' : ($pct >= 75 ? 'Excellent' : ($pct >= 60 ? 'Good' : ($pct >= 40 ? 'Average' : 'Weak')));
                $color = $pct >= 90 ? '#10b981' : ($pct >= 75 ? '#6366f1' : ($pct >= 60 ? '#06b6d4' : ($pct >= 40 ? '#f59e0b' : '#ef4444')));
                $emoji = $pct >= 75 ? '🏆' : ($pct >= 60 ? '👏' : ($pct >= 40 ? '📖' : '💪'));
                ?>
                <div style="font-size:54px;margin-bottom:16px;"><?= $emoji ?></div>
                <div style="font-family:'Poppins',sans-serif;font-weight:800;font-size:1.3rem;color:var(--text);margin-bottom:6px;">Test Submitted!</div>
                <div style="font-size:0.84rem;color:var(--text-muted);margin-bottom:24px;">Your result has been recorded.</div>

                <div class="result-circle" style="border-color:<?= $color ?>;color:<?= $color ?>;">
                    <div class="result-score"><?= number_format($pct, 1) ?>%</div>
                    <div class="result-pct"><?= $band ?></div>
                </div>

                <div class="result-stats">
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:#10b981;"><?= $result['correct_count'] ?></div>
                        <div class="result-stat-lbl">Correct</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:#ef4444;"><?= $result['wrong_count'] ?></div>
                        <div class="result-stat-lbl">Wrong</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:var(--text-muted);"><?= $result['unanswered_count'] ?></div>
                        <div class="result-stat-lbl">Skipped</div>
                    </div>
                </div>

                <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:12px;padding:14px;font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;line-height:1.6;">
                    ⏳ Admin will review your result and notify you by email with the decision.
                </div>

                <a href="<?= BASE_PATH ?>/auth/login.php" style="display:block;padding:12px;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;border-radius:12px;font-family:'Poppins',sans-serif;font-weight:700;font-size:0.9rem;text-decoration:none;">
                    Back to Login
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══════════ FULL TEST UI ════════════ -->
        <div class="et-page" id="et-page">

            <!-- Top Bar -->
            <div class="et-topbar">
                <div class="et-topbar-brand">🎓 EduFlow</div>
                <div style="width:1px;height:20px;background:var(--border);flex-shrink:0;"></div>
                <div class="et-topbar-title" id="topbar-title"><?= e($etSession['test_title'] ?? 'Entry Test') ?></div>
                <div class="et-topbar-student">
                    <div style="font-weight:600;font-size:0.82rem;color:var(--text);"><?= e($etSession['student_name'] ?? '') ?></div>
                    <div>ID: <?= e($etSession['student_uid'] ?? '') ?></div>
                </div>
            </div>

            <!-- Body -->
            <div class="et-body">

                <!-- Sidebar -->
                <div class="et-sidebar">
                    <!-- Timer -->
                    <div class="et-timer-wrap">
                        <div class="et-timer-label">⏱ Time Remaining</div>
                        <div class="et-timer" id="et-timer">--:--</div>
                    </div>

                    <!-- Stats -->
                    <div class="et-stats">
                        <div class="et-stat">
                            <div class="et-stat-val" id="stat-answered">0</div>
                            <div class="et-stat-lbl">Answered</div>
                        </div>
                        <div class="et-stat">
                            <div class="et-stat-val" id="stat-remaining">0</div>
                            <div class="et-stat-lbl">Remaining</div>
                        </div>
                    </div>

                    <!-- Question Navigator -->
                    <div>
                        <div class="et-nav-title">Questions</div>
                        <div class="et-qnav" id="et-qnav">
                            <!-- filled by JS -->
                        </div>
                    </div>

                    <!-- Submit -->
                    <button class="et-submit-side" onclick="confirmSubmit()">
                        ✓ Submit Test
                    </button>
                </div>

                <!-- Main -->
                <div class="et-main">
                    <div class="et-qarea" id="et-qarea">
                        <div class="et-loading">
                            <div class="spin-sm" style="border-color:rgba(99,102,241,0.3);border-top-color:var(--primary);width:28px;height:28px;border-width:3px;"></div>
                            <div>Loading questions…</div>
                        </div>
                    </div>

                    <!-- Navigation bar -->
                    <div class="et-nav-bar">
                        <button class="et-nav-btn" id="btn-prev" onclick="navigate(-1)" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                            Previous
                        </button>
                        <div class="et-q-progress" id="q-progress">— / —</div>
                        <button class="et-nav-btn" id="btn-next" onclick="navigate(1)">
                            Next
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </button>
                    </div>
                </div>

            </div>
        </div><!-- /.et-page -->

        <!-- Rules Modal -->
        <div class="et-overlay" id="rules-overlay" style="display:none;">
            <div class="et-dialog">
                <div class="et-dialog-icon">📋</div>
                <div class="et-dialog-title">Test Rules & Instructions</div>
                <ul class="rules-list">
                    <li>This test has a strict time limit. It will <strong>auto-submit</strong> when time runs out.</li>
                    <li>Each question carries <strong>1 mark</strong>. Total marks = total questions.</li>
                    <li><strong>Negative marking</strong> applies: −0.25 for each wrong answer.</li>
                    <li>Unanswered questions score <strong>0</strong> (no penalty).</li>
                    <li>Only <strong>one attempt</strong> is allowed. Once submitted, you cannot go back.</li>
                    <li>You can navigate between questions using Previous / Next buttons.</li>
                    <li>Do not close or refresh the browser tab during the test.</li>
                </ul>
                <div class="et-dialog-actions">
                    <button class="et-dialog-btn et-btn-primary" onclick="startTest()">
                        ✏️ Start Test Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Submit Confirm Modal -->
        <div class="et-overlay" id="confirm-overlay" style="display:none;">
            <div class="et-dialog">
                <div class="et-dialog-icon">⚠️</div>
                <div class="et-dialog-title">Submit Test?</div>
                <div class="et-dialog-text" id="confirm-text">
                    You are about to submit the test. This action <strong>cannot be undone</strong>.
                </div>
                <div class="et-dialog-actions">
                    <button class="et-dialog-btn et-btn-danger" id="confirm-submit-btn" onclick="doSubmit()">
                        Yes, Submit Test
                    </button>
                    <button class="et-dialog-btn et-btn-ghost" onclick="document.getElementById('confirm-overlay').style.display='none';">
                        Continue Answering
                    </button>
                </div>
            </div>
        </div>

        <!-- Result Modal -->
        <div class="et-overlay" id="result-overlay" style="display:none;">
            <div class="et-dialog" style="max-width:440px;">
                <div id="result-emoji" class="et-dialog-icon">🏆</div>
                <div class="et-dialog-title">Test Submitted!</div>

                <div id="result-circle-wrap" style="margin-bottom:16px;"></div>

                <div class="result-stats">
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:#10b981;" id="res-correct">0</div>
                        <div class="result-stat-lbl">Correct</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:#ef4444;" id="res-wrong">0</div>
                        <div class="result-stat-lbl">Wrong</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-val" style="color:var(--text-muted);" id="res-skip">0</div>
                        <div class="result-stat-lbl">Skipped</div>
                    </div>
                </div>

                <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:12px;padding:14px;font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;line-height:1.6;">
                    ⏳ Your result has been submitted. Admin will review and notify you by email.
                </div>

                <div class="et-dialog-actions">
                    <a href="<?= BASE_PATH ?>/auth/login.php" class="et-dialog-btn et-btn-primary" style="display:block;text-decoration:none;">
                        Back to Login
                    </a>
                </div>
            </div>
        </div>

        <!-- Timeout Modal -->
        <div class="et-overlay" id="timeout-overlay" style="display:none;">
            <div class="et-dialog">
                <div class="et-dialog-icon">⏰</div>
                <div class="et-dialog-title">Time's Up!</div>
                <div class="et-dialog-text">Your time has expired. The test is being submitted automatically…</div>
                <div style="margin:0 auto 16px;">
                    <div class="spin-sm" style="border-color:rgba(99,102,241,0.3);border-top-color:var(--primary);width:32px;height:32px;border-width:3px;margin:0 auto;"></div>
                </div>
            </div>
        </div>

        <script>
            'use strict';

            const ET_AJAX = window.LMS_BASE + '/ajax/entry-test.ajax.php';
            const TIME_LIMIT = <?= (int)($etSession['time_limit'] ?? 1800) ?>;
            const STARTED_AT = <?= (int)($etSession['started_at'] ?? time()) ?>;

            let questions = [];
            let currentIndex = 0;
            let answers = {}; // { question_id: option_id | null }
            let timerInterval = null;
            let testStarted = false;
            let isSubmitting = false;

            // ── Init ──────────────────────────────────────────────────────
            document.addEventListener('DOMContentLoaded', () => {
                // Show rules modal first
                document.getElementById('rules-overlay').style.display = 'flex';
            });

            // ── Start test ────────────────────────────────────────────────
            async function startTest() {
                document.getElementById('rules-overlay').style.display = 'none';
                testStarted = true;
                await loadQuestions();
                startTimer();
            }

            // ── Load questions ────────────────────────────────────────────
            async function loadQuestions() {
                const res = await post(ET_AJAX, {
                    action: 'get_questions'
                });
                if (!res || res.status !== 'success') {
                    document.getElementById('et-qarea').innerHTML =
                        `<div style="color:var(--danger);padding:32px;text-align:center;">${esc(res?.message || 'Failed to load questions')}</div>`;
                    return;
                }

                questions = res.data.questions;

                // Pre-populate answers from saved state
                questions.forEach(q => {
                    answers[q.question_id] = q.selected_option_id || null;
                });

                // Sync timer if resuming
                const serverRemaining = res.data.time_remaining;
                // (We'll use server time for accuracy — recalculate local start)

                buildQNav();
                renderQuestion(0);
                updateStats();
            }

            // ── Render question ───────────────────────────────────────────
            function renderQuestion(idx) {
                if (!questions.length) return;
                currentIndex = Math.max(0, Math.min(idx, questions.length - 1));
                const q = questions[currentIndex];
                const letters = ['A', 'B', 'C', 'D'];

                const qTextClass = q.is_code ? 'et-q-text is-code' : 'et-q-text';

                document.getElementById('et-qarea').innerHTML = `
        <div class="et-q-header">
            <div class="et-q-num">${currentIndex + 1}</div>
            <div class="et-q-badge">Question ${currentIndex + 1} of ${questions.length}</div>
        </div>
        <div class="${qTextClass}">${escHtml(q.question_text)}</div>
        <div class="et-options" id="et-options">
            ${q.options.map((opt, i) => `
                <div class="et-option${answers[q.question_id] == opt.option_id ? ' selected' : ''}"
                     id="opt-${opt.option_id}"
                     onclick="selectOption(${q.question_id}, ${opt.option_id})">
                    <div class="et-option-letter">${letters[i]}</div>
                    <div class="et-option-text">${escHtml(opt.option_text)}</div>
                </div>
            `).join('')}
        </div>
    `;

                // Update nav buttons
                document.getElementById('btn-prev').disabled = currentIndex === 0;
                document.getElementById('btn-next').disabled = currentIndex === questions.length - 1;
                document.getElementById('q-progress').textContent = `${currentIndex + 1} / ${questions.length}`;

                // Update q-nav highlight
                buildQNav();
            }

            // ── Select option ─────────────────────────────────────────────
            async function selectOption(questionId, optionId) {
                if (!testStarted) return;

                // Toggle: click same option = deselect
                const prev = answers[questionId];
                const newOpt = (prev == optionId) ? null : optionId;
                answers[questionId] = newOpt;

                // Update UI
                document.querySelectorAll('.et-option').forEach(el => el.classList.remove('selected'));
                if (newOpt) {
                    const el = document.getElementById(`opt-${newOpt}`);
                    if (el) el.classList.add('selected');
                }

                updateStats();
                buildQNav();

                // Save to server
                await post(ET_AJAX, {
                    action: 'save_answer',
                    question_id: questionId,
                    option_id: newOpt || ''
                });
            }

            // ── Navigate ──────────────────────────────────────────────────
            function navigate(dir) {
                renderQuestion(currentIndex + dir);
            }

            // ── Question navigator ────────────────────────────────────────
            function buildQNav() {
                const nav = document.getElementById('et-qnav');
                if (!nav || !questions.length) return;
                nav.innerHTML = questions.map((q, i) => {
                    const isAnswered = answers[q.question_id] != null;
                    const isCurrent = i === currentIndex;
                    let cls = 'et-qbtn';
                    if (isAnswered) cls += ' answered';
                    if (isCurrent) cls += ' current';
                    return `<button class="${cls}" onclick="renderQuestion(${i})">${i + 1}</button>`;
                }).join('');
            }

            // ── Stats ─────────────────────────────────────────────────────
            function updateStats() {
                const answered = Object.values(answers).filter(v => v != null).length;
                const remaining = questions.length - answered;
                document.getElementById('stat-answered').textContent = answered;
                document.getElementById('stat-remaining').textContent = remaining;
            }

            // ── Timer ─────────────────────────────────────────────────────
            function startTimer() {
                updateTimerDisplay();
                timerInterval = setInterval(() => {
                    updateTimerDisplay();
                }, 1000);
            }

            function updateTimerDisplay() {
                const elapsed = Math.floor((Date.now() / 1000) - STARTED_AT);
                const remaining = Math.max(0, TIME_LIMIT - elapsed);

                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                const display = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

                const el = document.getElementById('et-timer');
                el.textContent = display;

                if (remaining <= 60) {
                    el.className = 'et-timer danger';
                } else if (remaining <= 300) {
                    el.className = 'et-timer warning';
                } else {
                    el.className = 'et-timer';
                }

                if (remaining === 0) {
                    clearInterval(timerInterval);
                    autoSubmit();
                }
            }

            // ── Confirm submit ────────────────────────────────────────────
            function confirmSubmit() {
                const answered = Object.values(answers).filter(v => v != null).length;
                const remaining = questions.length - answered;
                document.getElementById('confirm-text').innerHTML =
                    `You have answered <strong>${answered}</strong> of <strong>${questions.length}</strong> questions.` +
                    (remaining > 0 ? ` <strong style="color:var(--danger)">${remaining} question(s) are unanswered</strong>. ` : ' ') +
                    `This action <strong>cannot be undone</strong>.`;
                document.getElementById('confirm-overlay').style.display = 'flex';
            }

            async function doSubmit() {
                if (isSubmitting) return;
                isSubmitting = true;
                clearInterval(timerInterval);
                document.getElementById('confirm-overlay').style.display = 'none';
                document.getElementById('confirm-submit-btn').disabled = true;

                const res = await post(ET_AJAX, {
                    action: 'submit_test'
                });
                showResult(res);
            }

            async function autoSubmit() {
                if (isSubmitting) return;
                isSubmitting = true;
                clearInterval(timerInterval);
                document.getElementById('timeout-overlay').style.display = 'flex';

                await new Promise(r => setTimeout(r, 2000)); // show timeout for 2s

                const res = await post(ET_AJAX, {
                    action: 'submit_test'
                });
                document.getElementById('timeout-overlay').style.display = 'none';
                showResult(res);
            }

            // ── Show result ───────────────────────────────────────────────
            function showResult(res) {
                if (!res || res.status !== 'success') {
                    alert('Test submitted. Please wait for admin review.');
                    window.location.href = window.LMS_BASE + '/auth/login.php';
                    return;
                }

                const d = res.data;
                const pct = parseFloat(d.percentage);
                const band = pct >= 90 ? 'Superb' : pct >= 75 ? 'Excellent' : pct >= 60 ? 'Good' : pct >= 40 ? 'Average' : 'Weak';
                const color = pct >= 90 ? '#10b981' : pct >= 75 ? '#6366f1' : pct >= 60 ? '#06b6d4' : pct >= 40 ? '#f59e0b' : '#ef4444';
                const emoji = pct >= 75 ? '🏆' : pct >= 60 ? '👏' : pct >= 40 ? '📖' : '💪';

                document.getElementById('result-emoji').textContent = emoji;
                document.getElementById('res-correct').textContent = d.correct_count;
                document.getElementById('res-wrong').textContent = d.wrong_count;
                document.getElementById('res-skip').textContent = d.unanswered_count;

                document.getElementById('result-circle-wrap').innerHTML = `
        <div class="result-circle" style="border-color:${color};color:${color};">
            <div class="result-score">${pct.toFixed(1)}%</div>
            <div class="result-pct" style="font-family:'Poppins',sans-serif;font-weight:700;">${band}</div>
        </div>
    `;

                document.getElementById('result-overlay').style.display = 'flex';
            }

            // ── Utilities ─────────────────────────────────────────────────
            async function post(url, data) {
                try {
                    const body = new URLSearchParams(data);
                    const res = await fetch(url, {
                        method: 'POST',
                        body
                    });
                    return await res.json();
                } catch (e) {
                    return null;
                }
            }

            function esc(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            function escHtml(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            // Prevent accidental page unload during test
            window.addEventListener('beforeunload', e => {
                if (testStarted && !isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'You are in the middle of an entry test. If you leave, your progress will be saved but the timer will continue.';
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>