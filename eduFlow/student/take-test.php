<?php
// ============================================================
// STUDENT — TAKE TEST (LMS authenticated)
// Weekly / Monthly test-taking page
// Uses LMS session — student must be logged in
// ============================================================
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$uid = (int)$currentUser['id'];

$testId = (int)($_GET['test_id'] ?? 0);
if (!$testId) {
    header('Location: ' . BASE_PATH . '/student/tests.php');
    exit;
}

// Verify the test exists and belongs to student's enrolled batch
$stmt = $conn->prepare("
    SELECT t.*, b.name AS batch_name, c.title AS course_title,
           (SELECT COUNT(*) FROM test_question_map WHERE test_id = t.id) AS question_count
    FROM tests t
    JOIN batches b ON t.batch_id  = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE t.id = ? AND t.status = 'active'
      AND t.type IN ('weekly','monthly')
      AND t.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?)
");
$stmt->bind_param('ii', $testId, $uid);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$test) {
    header('Location: ' . BASE_PATH . '/student/tests.php?error=notfound');
    exit;
}

// Check existing attempt
$attempt = $conn->query("SELECT * FROM test_attempts WHERE test_id=$testId AND student_id=$uid LIMIT 1")->fetch_assoc();

$pageTitle = $test['title'];
?>
<!DOCTYPE html>
<html lang="en" class="<?= ($_SESSION['theme'] ?? '') === 'dark' ? 'dark' : '' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($test['title']) ?> — EduFlow</title>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>/assets/eduflow_icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app.css">
    <script>
        (function() {
            try {
                const t = localStorage.getItem('lms_theme') || '<?= htmlspecialchars($_SESSION['theme'] ?? 'system') ?>';
                if (t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme:dark)').matches))
                    document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
        window.LMS_BASE = '<?= BASE_PATH ?>';
    </script>
    <style>
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

        /* ── Page grid ───────────────────────────────────────────── */
        .et-page {
            display: grid;
            grid-template-rows: 56px 1fr;
            height: 100svh;
            overflow: hidden;
        }

        /* ── Top bar ─────────────────────────────────────────────── */
        .et-topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 14px;
            flex-shrink: 0;
        }

        .et-back-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .15s;
        }

        .et-back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .et-topbar-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .et-topbar-right {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: right;
            flex-shrink: 0;
        }

        .et-topbar-right strong {
            display: block;
            font-size: 0.84rem;
            color: var(--text);
            font-weight: 700;
        }

        /* ── Body ────────────────────────────────────────────────── */
        .et-body {
            display: grid;
            grid-template-columns: 220px 1fr;
            overflow: hidden;
            height: 100%;
        }

        /* ── Sidebar ─────────────────────────────────────────────── */
        .et-sidebar {
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 18px 14px;
            overflow-y: auto;
            gap: 18px;
        }

        .et-timer-wrap {
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            text-align: center;
        }

        .et-timer-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .et-timer {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 2px;
            line-height: 1;
            transition: color .3s;
        }

        .et-timer.warning {
            color: #f59e0b;
        }

        .et-timer.danger {
            color: #ef4444;
            animation: timerPulse .5s ease infinite alternate;
        }

        @keyframes timerPulse {
            from {
                opacity: 1;
            }

            to {
                opacity: .45;
            }
        }

        .et-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .et-stat {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 9px;
            text-align: center;
        }

        .et-stat-val {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--primary);
        }

        .et-stat-lbl {
            font-size: 0.64rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 2px;
        }

        .et-nav-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .et-qnav {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
        }

        .et-qbtn {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text-muted);
            font-size: 0.68rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
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
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .2);
        }

        .et-qbtn.answered {
            border-color: var(--success);
            background: rgba(16, 185, 129, .1);
            color: var(--success);
        }

        .et-qbtn.answered.current {
            background: var(--success);
            color: #fff;
            border-color: var(--success);
        }

        .et-submit-side {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
            box-shadow: 0 4px 14px rgba(239, 68, 68, .3);
            margin-top: auto;
        }

        .et-submit-side:hover {
            opacity: .9;
            transform: translateY(-1px);
        }

        /* ── Main question area ───────────────────────────────────── */
        .et-main {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .et-qarea {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
        }

        .et-q-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .et-q-num {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary, #8b5cf6));
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .et-q-badge {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
        }

        .et-q-text {
            font-size: 0.975rem;
            font-weight: 600;
            color: var(--text);
            line-height: 1.72;
            margin-bottom: 22px;
            white-space: pre-wrap;
        }

        .et-q-text.is-code {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            font-size: 0.88rem;
            overflow-x: auto;
        }

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
            transition: all .15s;
            background: var(--bg-card);
            user-select: none;
        }

        .et-option:hover {
            border-color: rgba(99, 102, 241, .4);
            background: rgba(99, 102, 241, .04);
        }

        .et-option.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, .08);
        }

        .et-option-letter {
            width: 27px;
            height: 27px;
            border-radius: 50%;
            background: var(--bg);
            border: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.74rem;
            font-weight: 800;
            font-family: 'Poppins', sans-serif;
            color: var(--text-muted);
            flex-shrink: 0;
            transition: all .15s;
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
            padding-top: 2px;
        }

        /* ── Nav bar ─────────────────────────────────────────────── */
        .et-nav-bar {
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-shrink: 0;
        }

        .et-nav-btn {
            padding: 8px 18px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .et-nav-btn:hover:not(:disabled) {
            border-color: var(--primary);
            color: var(--primary);
        }

        .et-nav-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .et-q-progress {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        /* ── Overlays ────────────────────────────────────────────── */
        .et-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
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
            padding: 34px;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .28);
            animation: dialogIn .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes dialogIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .et-dialog-icon {
            font-size: 52px;
            margin-bottom: 14px;
        }

        .et-dialog-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--text);
            margin-bottom: 8px;
        }

        .et-dialog-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 22px;
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
            padding: 11px 20px;
            border-radius: 12px;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
        }

        .et-dialog-btn:hover {
            opacity: .9;
            transform: translateY(-1px);
        }

        .et-btn-primary {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: #fff;
            box-shadow: 0 4px 16px rgba(99, 102, 241, .35);
        }

        .et-btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            box-shadow: 0 4px 16px rgba(239, 68, 68, .35);
        }

        .et-btn-ghost {
            background: var(--bg);
            color: var(--text-muted);
            border: 1.5px solid var(--border);
        }

        /* ── Result ───────────────────────────────────────────────── */
        .result-circle {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 8px solid;
        }

        .result-score {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 1.9rem;
            line-height: 1;
        }

        .result-band {
            font-size: 0.72rem;
            font-weight: 700;
            opacity: .75;
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 18px 0;
        }

        .result-stat {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 6px;
            text-align: center;
        }

        .result-stat-val {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
        }

        .result-stat-lbl {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-top: 2px;
        }

        /* ── Rules list ───────────────────────────────────────────── */
        .rules-list {
            text-align: left;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .rules-list li {
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.7;
            padding: 3px 0;
            border-bottom: 1px solid var(--border);
            list-style: none;
            padding-left: 18px;
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

        /* ── Loading spinner ──────────────────────────────────────── */
        .spin-md {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid rgba(99, 102, 241, .2);
            border-top-color: var(--primary);
            animation: spinAnim .7s linear infinite;
            margin: 0 auto;
        }

        @keyframes spinAnim {
            to {
                transform: rotate(360deg);
            }
        }

        .et-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 14px;
            color: var(--text-muted);
            font-size: .9rem;
        }

        @media(max-width:768px) {
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

    <div class="et-page">

        <!-- Top Bar -->
        <div class="et-topbar">
            <a href="<?= BASE_PATH ?>/student/tests.php" class="et-back-btn" id="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
                Tests
            </a>
            <div class="et-topbar-title" id="topbar-title"><?= htmlspecialchars($test['title']) ?></div>
            <div class="et-topbar-right">
                <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong>
                ID: <?= htmlspecialchars($currentUser['user_id_number']) ?>
            </div>
        </div>

        <!-- Body -->
        <div class="et-body">

            <!-- Sidebar -->
            <div class="et-sidebar">
                <div class="et-timer-wrap">
                    <div class="et-timer-label">⏱ Time Remaining</div>
                    <div class="et-timer" id="et-timer">--:--</div>
                </div>
                <div class="et-stats">
                    <div class="et-stat">
                        <div class="et-stat-val" id="stat-answered">0</div>
                        <div class="et-stat-lbl">Answered</div>
                    </div>
                    <div class="et-stat">
                        <div class="et-stat-val" id="stat-remaining">0</div>
                        <div class="et-stat-lbl">Left</div>
                    </div>
                </div>
                <div>
                    <div class="et-nav-title">Questions</div>
                    <div class="et-qnav" id="et-qnav"></div>
                </div>
                <button class="et-submit-side" onclick="confirmSubmit()">✓ Submit Test</button>
            </div>

            <!-- Main -->
            <div class="et-main">
                <div class="et-qarea" id="et-qarea">
                    <div class="et-loading">
                        <div class="spin-md"></div>
                        <div>Loading test…</div>
                    </div>
                </div>
                <div class="et-nav-bar">
                    <button class="et-nav-btn" id="btn-prev" onclick="navigate(-1)" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                        Previous
                    </button>
                    <div class="et-q-progress" id="q-progress">— / —</div>
                    <button class="et-nav-btn" id="btn-next" onclick="navigate(1)">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Rules Modal -->
    <div class="et-overlay" id="rules-overlay" style="display:none;">
        <div class="et-dialog">
            <div class="et-dialog-icon">📋</div>
            <div class="et-dialog-title">Test Rules</div>
            <ul class="rules-list">
                <li>The timer starts now. The test <strong>auto-submits</strong> when time runs out.</li>
                <li>Each correct answer = <strong>+1 mark</strong>. Wrong answer = <strong>−0.25 marks</strong>.</li>
                <li>Unanswered questions score <strong>0</strong> (no penalty for skipping).</li>
                <li>You can navigate freely between questions before submitting.</li>
                <li>Click an option to select it. Click again to deselect.</li>
                <li>Do <strong>not</strong> close or refresh the browser during the test.</li>
                <li>Only <strong>one attempt</strong> is allowed unless your teacher grants a re-attempt.</li>
            </ul>
            <div class="et-dialog-actions">
                <button class="et-dialog-btn et-btn-primary" onclick="startTest()">✏️ Start Test Now</button>
            </div>
        </div>
    </div>

    <!-- Submit Confirm Modal -->
    <div class="et-overlay" id="confirm-overlay" style="display:none;">
        <div class="et-dialog">
            <div class="et-dialog-icon">⚠️</div>
            <div class="et-dialog-title">Submit Test?</div>
            <div class="et-dialog-text" id="confirm-text">Are you sure you want to submit?</div>
            <div class="et-dialog-actions">
                <button class="et-dialog-btn et-btn-danger" onclick="doSubmit()" id="confirm-submit-btn">Yes, Submit</button>
                <button class="et-dialog-btn et-btn-ghost" onclick="document.getElementById('confirm-overlay').style.display='none';">Keep Answering</button>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="et-overlay" id="result-overlay" style="display:none;">
        <div class="et-dialog" style="max-width:420px;">
            <div id="result-emoji" class="et-dialog-icon">🏆</div>
            <div class="et-dialog-title">Test Submitted!</div>
            <div id="result-circle-wrap" style="margin-bottom:14px;"></div>
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
            <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:12px;padding:12px;font-size:0.82rem;color:var(--text-muted);margin-bottom:18px;line-height:1.6;">
                Your result has been recorded. Your teacher will see your score.
            </div>
            <div class="et-dialog-actions">
                <a href="<?= BASE_PATH ?>/student/tests.php" class="et-dialog-btn et-btn-primary" style="display:block;text-decoration:none;">Back to Tests</a>
            </div>
        </div>
    </div>

    <!-- Timeout Modal -->
    <div class="et-overlay" id="timeout-overlay" style="display:none;">
        <div class="et-dialog">
            <div class="et-dialog-icon">⏰</div>
            <div class="et-dialog-title">Time's Up!</div>
            <div class="et-dialog-text">Your test is being submitted automatically…</div>
            <div class="spin-md" style="margin:0 auto;"></div>
        </div>
    </div>

    <script>
        'use strict';

        const AJAX_URL = window.LMS_BASE + '/ajax/student-tests.ajax.php';
        const TEST_ID = <?= (int)$testId ?>;
        const TIME_LIMIT = <?= (int)$test['time_minutes'] ?> * 60;

        let questions = [];
        let answers = {};
        let currentIndex = 0;
        let timerInterval = null;
        let startedAt = null; // Date.now() ms when attempt started
        let attemptId = null;
        let isSubmitting = false;
        let testStarted = false;

        // ── Show rules on load ────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            <?php if ($attempt && $attempt['status'] === 'submitted' && ($attempt['allow_reattempt'] ?? 0) != 1): ?>
                // Already submitted and no re-attempt — go back to list
                window.location.href = window.LMS_BASE + '/student/tests.php';
            <?php else: ?>
                document.getElementById('rules-overlay').style.display = 'flex';
            <?php endif; ?>
        });

        // ── Start test ────────────────────────────────────────────────
        async function startTest() {
            document.getElementById('rules-overlay').style.display = 'none';
            testStarted = true;

            const res = await post(AJAX_URL, {
                action: 'start_test',
                test_id: TEST_ID
            });
            if (!res || res.status === 'error') {
                document.getElementById('et-qarea').innerHTML = '<div style="color:var(--danger);padding:32px;text-align:center;">' + (res?.message || 'Failed to start test.') + '<br><br><a href="' + window.LMS_BASE + '/student/tests.php" style="color:var(--primary);">← Back to Tests</a></div>';
                return;
            }

            attemptId = res.data.attempt_id;
            const timeRemaining = res.data.time_remaining;

            // startedAt calculated from remaining time
            startedAt = Date.now() - ((TIME_LIMIT - timeRemaining) * 1000);

            await loadQuestions();
            startTimer(timeRemaining);
        }

        // ── Load questions ────────────────────────────────────────────
        async function loadQuestions() {
            const res = await post(AJAX_URL, {
                action: 'get_questions',
                attempt_id: attemptId
            });
            if (!res || res.status !== 'success') {
                document.getElementById('et-qarea').innerHTML = '<div style="color:var(--danger);padding:32px;text-align:center;">' + (res?.message || 'Failed to load questions.') + '</div>';
                return;
            }
            questions = res.data.questions;

            // Pre-populate saved answers
            questions.forEach(q => {
                answers[q.question_id] = q.selected_option_id !== null ? q.selected_option_id : null;
            });

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
            const qCls = q.is_code ? 'et-q-text is-code' : 'et-q-text';

            let html = '<div class="et-q-header">' +
                '<div class="et-q-num">' + (currentIndex + 1) + '</div>' +
                '<div class="et-q-badge">Question ' + (currentIndex + 1) + ' of ' + questions.length + '</div>' +
                '</div>' +
                '<div class="' + qCls + '">' + escHtml(q.question_text) + '</div>' +
                '<div class="et-options">';

            q.options.forEach((opt, i) => {
                const selected = answers[q.question_id] == opt.option_id;
                html += '<div class="et-option' + (selected ? ' selected' : '') + '" id="opt-' + opt.option_id + '" onclick="selectOption(' + q.question_id + ',' + opt.option_id + ')">' +
                    '<div class="et-option-letter">' + letters[i] + '</div>' +
                    '<div class="et-option-text">' + escHtml(opt.option_text) + '</div>' +
                    '</div>';
            });

            html += '</div>';
            document.getElementById('et-qarea').innerHTML = html;
            document.getElementById('btn-prev').disabled = currentIndex === 0;
            document.getElementById('btn-next').disabled = currentIndex === questions.length - 1;
            document.getElementById('q-progress').textContent = (currentIndex + 1) + ' / ' + questions.length;
            buildQNav();
        }

        // ── Select option ─────────────────────────────────────────────
        async function selectOption(questionId, optionId) {
            if (!testStarted) return;
            const prev = answers[questionId];
            const newOpt = (prev == optionId) ? null : optionId;
            answers[questionId] = newOpt;

            // Update UI
            document.querySelectorAll('.et-option').forEach(el => el.classList.remove('selected'));
            if (newOpt !== null) {
                const el = document.getElementById('opt-' + newOpt);
                if (el) el.classList.add('selected');
            }

            updateStats();
            buildQNav();

            // Save to server
            await post(AJAX_URL, {
                action: 'save_answer',
                attempt_id: attemptId,
                question_id: questionId,
                option_id: newOpt !== null ? newOpt : ''
            });
        }

        // ── Navigate ──────────────────────────────────────────────────
        function navigate(dir) {
            renderQuestion(currentIndex + dir);
        }

        // ── Question nav grid ─────────────────────────────────────────
        function buildQNav() {
            const nav = document.getElementById('et-qnav');
            if (!nav || !questions.length) return;
            nav.innerHTML = questions.map((q, i) => {
                const answered = answers[q.question_id] != null;
                const isCurrent = i === currentIndex;
                let cls = 'et-qbtn';
                if (answered) cls += ' answered';
                if (isCurrent) cls += ' current';
                return '<button class="' + cls + '" onclick="renderQuestion(' + i + ')">' + (i + 1) + '</button>';
            }).join('');
        }

        // ── Stats ─────────────────────────────────────────────────────
        function updateStats() {
            const answered = Object.values(answers).filter(v => v !== null).length;
            const remaining = questions.length - answered;
            document.getElementById('stat-answered').textContent = answered;
            document.getElementById('stat-remaining').textContent = remaining;
        }

        // ── Timer ─────────────────────────────────────────────────────
        function startTimer(initialRemaining) {
            // We use the server-provided remaining time as start reference
            const endAt = Date.now() + initialRemaining * 1000;

            updateTimerDisplay(initialRemaining);
            timerInterval = setInterval(() => {
                const rem = Math.max(0, Math.floor((endAt - Date.now()) / 1000));
                updateTimerDisplay(rem);
                if (rem <= 0) {
                    clearInterval(timerInterval);
                    autoSubmit();
                }
            }, 1000);
        }

        function updateTimerDisplay(remaining) {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            const el = document.getElementById('et-timer');
            el.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            if (remaining <= 60) el.className = 'et-timer danger';
            else if (remaining <= 300) el.className = 'et-timer warning';
            else el.className = 'et-timer';
        }

        // ── Confirm + submit ──────────────────────────────────────────
        function confirmSubmit() {
            const answered = Object.values(answers).filter(v => v !== null).length;
            const remaining = questions.length - answered;
            document.getElementById('confirm-text').innerHTML =
                'You have answered <strong>' + answered + '</strong> of <strong>' + questions.length + '</strong> questions.' +
                (remaining > 0 ? ' <strong style="color:var(--danger)">' + remaining + ' unanswered</strong>.' : '') +
                ' This action <strong>cannot be undone</strong>.';
            document.getElementById('confirm-overlay').style.display = 'flex';
        }

        async function doSubmit() {
            if (isSubmitting) return;
            isSubmitting = true;
            clearInterval(timerInterval);
            document.getElementById('confirm-overlay').style.display = 'none';
            document.getElementById('confirm-submit-btn').disabled = true;

            const res = await post(AJAX_URL, {
                action: 'submit_test',
                attempt_id: attemptId
            });
            showResult(res);
        }

        async function autoSubmit() {
            if (isSubmitting) return;
            isSubmitting = true;
            clearInterval(timerInterval);
            document.getElementById('timeout-overlay').style.display = 'flex';
            await new Promise(r => setTimeout(r, 2000));

            const res = await post(AJAX_URL, {
                action: 'submit_test',
                attempt_id: attemptId
            });
            document.getElementById('timeout-overlay').style.display = 'none';
            showResult(res);
        }

        // ── Show result ───────────────────────────────────────────────
        function showResult(res) {
            if (!res || res.status !== 'success') {
                alert('Test submitted. Redirecting…');
                window.location.href = window.LMS_BASE + '/student/tests.php';
                return;
            }
            const d = res.data;
            const pct = parseFloat(d.percentage);
            const col = pct >= 90 ? '#10b981' : pct >= 75 ? '#6366f1' : pct >= 60 ? '#06b6d4' : pct >= 40 ? '#f59e0b' : '#ef4444';
            const band = pct >= 90 ? 'Superb' : pct >= 75 ? 'Excellent' : pct >= 60 ? 'Good' : pct >= 40 ? 'Average' : 'Weak';
            const emoji = pct >= 75 ? '🏆' : pct >= 60 ? '👏' : pct >= 40 ? '📖' : '💪';

            document.getElementById('result-emoji').textContent = emoji;
            document.getElementById('res-correct').textContent = d.correct_count;
            document.getElementById('res-wrong').textContent = d.wrong_count;
            document.getElementById('res-skip').textContent = d.unanswered_count;
            document.getElementById('result-circle-wrap').innerHTML =
                '<div class="result-circle" style="border-color:' + col + ';color:' + col + ';">' +
                '<div class="result-score">' + pct.toFixed(1) + '%</div>' +
                '<div class="result-band">' + band + '</div>' +
                '</div>';

            document.getElementById('result-overlay').style.display = 'flex';

            // Remove back-button link warning
            testStarted = false;
        }

        // ── Helpers ───────────────────────────────────────────────────
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

        function escHtml(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // Warn before unload
        window.addEventListener('beforeunload', e => {
            if (testStarted && !isSubmitting) {
                e.preventDefault();
                e.returnValue = 'You are in the middle of a test. The timer will keep running if you leave.';
            }
        });
    </script>
</body>

</html>