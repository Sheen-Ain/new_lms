<?php
// ============================================================
// STUDENT — TESTS PAGE
// Lists all available weekly/monthly tests for enrolled batches
// Shows attempt status, score, and link to take test
// ============================================================
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'My Tests';
$breadcrumbs = [
    ['label' => 'Student', 'url' => BASE_PATH . '/student/'],
    ['label' => 'My Tests'],
];
$uid = (int)$currentUser['id'];

// Check if student has any enrolled batches
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM batch_students WHERE student_id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$enrolledCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Test card ─────────────────────────────────────────────── */
    .test-card {
        background: var(--bg-card);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px 22px;
        transition: border-color 0.15s, box-shadow 0.15s;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .test-card:hover {
        border-color: rgba(99, 102, 241, 0.35);
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
    }

    .test-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .test-card-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text);
        line-height: 1.4;
    }

    .test-card-meta {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        font-size: 0.78rem;
        color: var(--text-muted);
    }

    .test-card-meta-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── Score pill ────────────────────────────────────────────── */
    .score-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 99px;
        font-family: 'Poppins', sans-serif;
        font-weight: 800;
        font-size: 0.9rem;
    }

    /* ── Result mini bar ───────────────────────────────────────── */
    .result-mini {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 10px 14px;
    }

    .result-mini-item {
        text-align: center;
    }

    .result-mini-val {
        font-family: 'Poppins', sans-serif;
        font-weight: 800;
        font-size: 1.1rem;
    }

    .result-mini-lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-muted);
        margin-top: 1px;
    }

    /* ── Score bar ─────────────────────────────────────────────── */
    .score-bar-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .score-bar {
        flex: 1;
        height: 7px;
        background: var(--border);
        border-radius: 99px;
        overflow: hidden;
    }

    .score-bar-fill {
        height: 100%;
        border-radius: 99px;
    }

    /* ── Status chips ──────────────────────────────────────────── */
    .chip-available {
        background: rgba(16, 185, 129, .1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, .25);
    }

    .chip-inprogress {
        background: rgba(245, 158, 11, .1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, .25);
        animation: chipPulse 1.5s ease infinite alternate;
    }

    .chip-submitted {
        background: rgba(99, 102, 241, .1);
        color: #4f46e5;
        border: 1px solid rgba(99, 102, 241, .2);
    }

    .chip-reattempt {
        background: rgba(239, 68, 68, .08);
        color: #dc2626;
        border: 1px solid rgba(239, 68, 68, .2);
    }

    @keyframes chipPulse {
        from {
            opacity: 1;
        }

        to {
            opacity: .6;
        }
    }

    .status-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 99px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    /* ── Tabs ──────────────────────────────────────────────────── */
    .test-tabs {
        display: flex;
        gap: 4px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 4px;
        width: fit-content;
        margin-bottom: 22px;
    }

    .test-tab {
        padding: 7px 18px;
        border-radius: calc(var(--radius) - 2px);
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-family: 'DM Sans', sans-serif;
    }

    .test-tab.active {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 2px 8px rgba(99, 102, 241, .3);
    }

    /* ── Empty / No batches states ─────────────────────────────── */
    .info-banner {
        background: rgba(99, 102, 241, .06);
        border: 1.5px solid rgba(99, 102, 241, .2);
        border-radius: var(--radius-lg);
        padding: 22px 26px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 24px;
    }

    .info-banner-icon {
        font-size: 1.8rem;
        flex-shrink: 0;
    }

    .info-banner h4 {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text);
        margin: 0 0 4px;
    }

    .info-banner p {
        font-size: 0.84rem;
        color: var(--text-muted);
        line-height: 1.6;
        margin: 0;
    }

    /* ── Test grid ─────────────────────────────────────────────── */
    .tests-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 16px;
    }

    @media (max-width: 600px) {
        .tests-grid {
            grid-template-columns: 1fr;
        }

        .result-mini {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title">My Tests</h1>
                    <p class="page-subtitle">Weekly and monthly tests assigned to your batches</p>
                </div>
            </div>

            <?php if ($enrolledCount === 0): ?>
                <div class="info-banner">
                    <div class="info-banner-icon">📋</div>
                    <div>
                        <h4>No Enrolled Batches Yet</h4>
                        <p>You haven't been enrolled in any batch yet. Once your application is approved you'll see your tests here.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
                <div class="stat-card" style="--stat-color:#6366f1;--stat-bg:rgba(99,102,241,.1);">
                    <div class="stat-icon"><i data-lucide="clipboard-list" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-total">—</div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#f59e0b;--stat-bg:rgba(245,158,11,.1);">
                    <div class="stat-icon"><i data-lucide="clock" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-pending">—</div>
                        <div class="stat-label">Not Attempted</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#10b981;--stat-bg:rgba(16,185,129,.1);">
                    <div class="stat-icon"><i data-lucide="check-circle" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-done">—</div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#06b6d4;--stat-bg:rgba(6,182,212,.1);">
                    <div class="stat-icon"><i data-lucide="percent" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-avg">—</div>
                        <div class="stat-label">Avg Score</div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div class="test-tabs" id="test-tabs">
                    <button class="test-tab active" data-tab="all" onclick="switchTab('all')">All</button>
                    <button class="test-tab" data-tab="weekly" onclick="switchTab('weekly')">Weekly</button>
                    <button class="test-tab" data-tab="monthly" onclick="switchTab('monthly')">Monthly</button>
                    <button class="test-tab" data-tab="completed" onclick="switchTab('completed')">Completed</button>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <select id="filter-batch" class="form-control" style="width:200px;">
                        <option value="">All Batches</option>
                    </select>
                </div>
            </div>

            <!-- Tests Grid -->
            <div id="tests-grid" class="tests-grid">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="test-card">
                        <div class="skeleton skeleton-text" style="width:70%;"></div>
                        <div class="skeleton skeleton-text" style="width:45%;height:10px;"></div>
                        <div class="skeleton" style="width:100%;height:40px;border-radius:8px;"></div>
                    </div>
                <?php endfor; ?>
            </div>

        </main>
    </div>
</div>

<script>
    'use strict';

    const AJAX_URL = window.LMS_BASE + '/ajax/student-tests.ajax.php';
    let allTests = [];
    let currentTab = 'all';

    // ── Load all tests ────────────────────────────────────────────
    async function loadTests() {
        const batchId = document.getElementById('filter-batch').value;
        const res = await ajax(AJAX_URL, {
            action: 'list_tests',
            batch_id: batchId
        });

        if (res.status !== 'success') {
            Toast.error(res.message);
            return;
        }

        allTests = res.data.tests || [];

        // Populate batch filter (once)
        const sel = document.getElementById('filter-batch');
        if (sel.options.length === 1 && res.data.batches?.length) {
            res.data.batches.forEach(b => {
                const o = document.createElement('option');
                o.value = b.id;
                o.textContent = b.course_title + ' — ' + b.name;
                sel.appendChild(o);
            });
        }

        renderTests();
    }

    // ── Render based on current tab ───────────────────────────────
    function renderTests() {
        let filtered = allTests;
        if (currentTab === 'weekly') filtered = allTests.filter(t => t.type === 'weekly');
        if (currentTab === 'monthly') filtered = allTests.filter(t => t.type === 'monthly');
        if (currentTab === 'completed') filtered = allTests.filter(t => t.attempt_status === 'submitted');

        // Stats
        const total = allTests.length;
        const done = allTests.filter(t => t.attempt_status === 'submitted').length;
        const pending = allTests.filter(t => !t.attempt_status).length;
        const scores = allTests.filter(t => t.percentage !== null && t.attempt_status === 'submitted').map(t => parseFloat(t.percentage));
        const avg = scores.length ? (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(1) + '%' : '—';

        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-pending').textContent = pending;
        document.getElementById('stat-done').textContent = done;
        document.getElementById('stat-avg').textContent = avg;

        const grid = document.getElementById('tests-grid');

        if (!filtered.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;">' +
                '<div class="empty-state"><div class="empty-state-icon">' +
                (currentTab === 'completed' ? '🏆' : '📋') +
                '</div><div class="empty-state-title">' +
                (currentTab === 'completed' ? 'No completed tests yet' : 'No tests available') +
                '</div><div class="empty-state-text">' +
                (currentTab === 'completed' ? 'Complete a test to see your results here.' : 'Your teacher hasn\'t posted any tests yet.') +
                '</div></div></div>';
            return;
        }

        grid.innerHTML = filtered.map(t => buildCard(t)).join('');
        if (window.lucide) lucide.createIcons();
    }

    function buildCard(t) {
        const typeBadge = '<span class="badge badge-' + t.type + '">' + t.type.charAt(0).toUpperCase() + t.type.slice(1) + '</span>';
        const scope = [t.course_title, t.batch_name].filter(Boolean).join(' — ');
        const pct = t.percentage !== null ? parseFloat(t.percentage) : null;
        const col = pct !== null ? (pct >= 90 ? '#10b981' : pct >= 75 ? '#6366f1' : pct >= 60 ? '#06b6d4' : pct >= 40 ? '#f59e0b' : '#ef4444') : '';
        const band = pct !== null ? (pct >= 90 ? 'Superb' : pct >= 75 ? 'Excellent' : pct >= 60 ? 'Good' : pct >= 40 ? 'Average' : 'Weak') : '';

        // Status chip + action button
        let chipHTML = '';
        let actionHTML = '';

        if (!t.attempt_status) {
            chipHTML = '<span class="status-chip chip-available">✦ Available</span>';
            actionHTML = '<a href="' + window.LMS_BASE + '/student/take-test.php?test_id=' + t.id + '" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px;">' +
                '<i data-lucide="pencil" style="width:15px;height:15px;"></i> Start Test' +
                '</a>';
        } else if (t.attempt_status === 'in_progress') {
            const minsLeft = t.time_remaining !== null ? Math.floor(t.time_remaining / 60) : '?';
            chipHTML = '<span class="status-chip chip-inprogress">⏱ In Progress — ' + minsLeft + 'm left</span>';
            actionHTML = '<a href="' + window.LMS_BASE + '/student/take-test.php?test_id=' + t.id + '" class="btn btn-primary" style="width:100%;justify-content:center;background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 14px rgba(245,158,11,.3);">' +
                '<i data-lucide="play" style="width:15px;height:15px;"></i> Resume Test' +
                '</a>';
        } else if (t.attempt_status === 'submitted') {
            if (t.allow_reattempt == 1) {
                chipHTML = '<span class="status-chip chip-reattempt">🔄 Re-attempt Granted</span>';
                actionHTML = '<a href="' + window.LMS_BASE + '/student/take-test.php?test_id=' + t.id + '" class="btn btn-primary" style="width:100%;justify-content:center;background:linear-gradient(135deg,#ef4444,#dc2626);">' +
                    '<i data-lucide="refresh-cw" style="width:15px;height:15px;"></i> Re-attempt Test' +
                    '</a>';
            } else {
                chipHTML = '<span class="status-chip chip-submitted">✓ Submitted</span>';
            }
        }

        // Result section
        let resultHTML = '';
        if (t.attempt_status === 'submitted' && pct !== null) {
            resultHTML = '<div class="score-bar-row" style="margin-bottom:8px;">' +
                '<div class="score-bar"><div class="score-bar-fill" style="width:' + pct.toFixed(1) + '%;background:' + col + ';"></div></div>' +
                '<span style="font-family:\'Poppins\',sans-serif;font-weight:800;font-size:0.88rem;color:' + col + ';min-width:48px;text-align:right;">' + pct.toFixed(1) + '%</span>' +
                '</div>' +
                '<div class="result-mini">' +
                '<div class="result-mini-item"><div class="result-mini-val" style="color:#10b981;">' + (t.correct_count || 0) + '</div><div class="result-mini-lbl">Correct</div></div>' +
                '<div class="result-mini-item"><div class="result-mini-val" style="color:#ef4444;">' + (t.wrong_count || 0) + '</div><div class="result-mini-lbl">Wrong</div></div>' +
                '<div class="result-mini-item"><div class="result-mini-val" style="color:var(--text-muted);">' + (t.unanswered_count || 0) + '</div><div class="result-mini-lbl">Skipped</div></div>' +
                '</div>' +
                '<div style="font-size:0.72rem;color:' + col + ';font-weight:700;text-align:center;margin-top:6px;">' + band +
                (t.submitted_at ? ' · Submitted ' + new Date(t.submitted_at).toLocaleDateString('en', {
                    day: 'numeric',
                    month: 'short'
                }) : '') +
                '</div>';
        }

        return '<div class="test-card">' +
            '<div class="test-card-top">' +
            '<div>' +
            '<div class="test-card-title">' + esc(t.title) + '</div>' +
            '<div style="font-size:0.72rem;color:var(--text-muted);margin-top:3px;">' + esc(scope) + '</div>' +
            '</div>' +
            typeBadge +
            '</div>' +
            '<div class="test-card-meta">' +
            '<div class="test-card-meta-item"><i data-lucide="help-circle" style="width:13px;height:13px;"></i> ' + (t.question_count || 0) + ' questions</div>' +
            '<div class="test-card-meta-item"><i data-lucide="clock" style="width:13px;height:13px;"></i> ' + t.time_minutes + ' min</div>' +
            '</div>' +
            chipHTML +
            (resultHTML ? resultHTML : '') +
            actionHTML +
            '</div>';
    }

    // ── Tab switching ─────────────────────────────────────────────
    function switchTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.test-tab').forEach(el => {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        renderTests();
    }

    // ── Filter ────────────────────────────────────────────────────
    document.getElementById('filter-batch').addEventListener('change', loadTests);

    // ── Helpers ───────────────────────────────────────────────────
    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Init ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => loadTests());
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>