<?php
// ============================================================
// TEACHER — TESTS MODULE
// Weekly & monthly tests for teacher's assigned batches
// Create/Edit wizard (2 steps), view details, copy, bulk actions
// ============================================================
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Tests';
$breadcrumbs = [
    ['label' => 'Teacher', 'url' => BASE_PATH . '/teacher/'],
    ['label' => 'Tests'],
];
$uid = (int)$currentUser['id'];

// Pre-load teacher's assigned batches for dropdowns
$stmt = $conn->prepare("
    SELECT b.id, b.name, c.title AS course_title
    FROM batch_teachers bt
    JOIN batches b ON bt.batch_id  = b.id
    JOIN courses c ON b.course_id  = c.id
    WHERE bt.teacher_id = ? AND b.status = 'active'
    ORDER BY c.title ASC, b.name ASC
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$myBatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Wizard Steps ──────────────────────────────────────────── */
    .wizard-steps {
        display: flex;
        align-items: center;
        gap: 0;
        margin-bottom: 28px;
        padding: 0 4px;
    }

    .wizard-step {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        position: relative;
    }

    .wizard-step:not(:last-child)::after {
        content: '';
        flex: 1;
        height: 2px;
        background: var(--border);
        margin: 0 12px;
        transition: background 0.3s;
    }

    .wizard-step.done:not(:last-child)::after {
        background: var(--primary);
    }

    .step-num {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        font-weight: 700;
        font-family: 'Poppins', sans-serif;
        flex-shrink: 0;
        border: 2px solid var(--border);
        background: var(--bg);
        color: var(--text-muted);
        transition: all 0.3s;
    }

    .wizard-step.active .step-num {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
    }

    .wizard-step.done .step-num {
        border-color: var(--success);
        background: var(--success);
        color: #fff;
    }

    .step-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        white-space: nowrap;
    }

    .wizard-step.active .step-label {
        color: var(--primary);
    }

    .wizard-step.done .step-label {
        color: var(--success);
    }

    /* ── Question Cards ────────────────────────────────────────── */
    .q-card {
        background: var(--bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin-bottom: 14px;
        position: relative;
        transition: border-color 0.15s;
    }

    .q-card:hover {
        border-color: rgba(99, 102, 241, 0.3);
    }

    .q-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
    }

    .q-num {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-family: 'Poppins', sans-serif;
    }

    .q-remove-btn {
        margin-left: auto;
        width: 28px;
        height: 28px;
        border-radius: var(--radius-sm);
        border: none;
        background: rgba(239, 68, 68, 0.08);
        color: var(--danger);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }

    .q-remove-btn:hover {
        background: rgba(239, 68, 68, 0.18);
    }

    .q-options-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 12px;
    }

    .q-option-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .q-option-letter {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--bg-card);
        border: 1.5px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--text-muted);
        flex-shrink: 0;
        transition: all 0.15s;
    }

    .q-correct-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        font-size: 0.78rem;
        color: var(--text-muted);
    }

    /* ── Add Question Button ───────────────────────────────────── */
    .add-q-btn {
        width: 100%;
        padding: 14px;
        border: 2px dashed var(--border);
        border-radius: var(--radius-lg);
        background: transparent;
        color: var(--text-muted);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: 'DM Sans', sans-serif;
    }

    .add-q-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(99, 102, 241, 0.04);
    }

    /* ── Type badges ────────────────────────────────────────────── */
    .badge-weekly {
        background: rgba(6, 182, 212, 0.12);
        color: #0891b2;
    }

    .badge-monthly {
        background: rgba(245, 158, 11, 0.12);
        color: #d97706;
    }

    /* ── Lock chip ─────────────────────────────────────────────── */
    .lock-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 700;
        background: rgba(245, 158, 11, 0.12);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    /* ── Lock warning banner ────────────────────────────────────── */
    .lock-banner {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        background: rgba(245, 158, 11, 0.08);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: var(--radius);
        margin-bottom: 16px;
    }

    .lock-banner-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
        line-height: 1;
    }

    .lock-banner-text {
        font-size: 0.84rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .lock-banner-text strong {
        color: #d97706;
    }

    /* ── Detail modal stats ────────────────────────────────────── */
    .test-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .test-stat-card {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px;
        text-align: center;
    }

    .test-stat-val {
        font-family: 'Poppins', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary);
    }

    .test-stat-lbl {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 4px;
    }

    /* ── Performance bar ───────────────────────────────────────── */
    .perf-bar-wrap {
        margin-bottom: 8px;
    }

    .perf-bar-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-bottom: 5px;
    }

    .perf-bar {
        height: 8px;
        background: var(--border);
        border-radius: 99px;
        overflow: hidden;
    }

    .perf-bar-fill {
        height: 100%;
        border-radius: 99px;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ── No batches state ──────────────────────────────────────── */
    .no-batches-card {
        background: rgba(245, 158, 11, 0.06);
        border: 1.5px solid rgba(245, 158, 11, 0.25);
        border-radius: var(--radius-lg);
        padding: 24px 28px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 24px;
    }

    .no-batches-icon {
        font-size: 1.8rem;
        flex-shrink: 0;
    }

    .no-batches-text h4 {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text);
        margin-bottom: 4px;
    }

    .no-batches-text p {
        font-size: 0.84rem;
        color: var(--text-muted);
        line-height: 1.6;
        margin: 0;
    }
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Tests</h1>
                    <p class="page-subtitle">Manage weekly and monthly tests for your batches</p>
                </div>
                <?php if (!empty($myBatches)): ?>
                    <button class="btn btn-primary" onclick="openCreateWizard()">
                        <i data-lucide="plus" style="width:16px;height:16px;"></i> Create Test
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($myBatches)): ?>
                <div class="no-batches-card">
                    <div class="no-batches-icon">📋</div>
                    <div class="no-batches-text">
                        <h4>No Batches Assigned Yet</h4>
                        <p>You don't have any active batches assigned. Once an admin assigns you to a batch, you'll be able to create weekly and monthly tests here.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
                <div class="stat-card" style="--stat-color:#06b6d4;--stat-bg:rgba(6,182,212,.1);">
                    <div class="stat-icon"><i data-lucide="clipboard-list" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-total">0</div>
                        <div class="stat-label">My Tests</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#f59e0b;--stat-bg:rgba(245,158,11,.1);">
                    <div class="stat-icon"><i data-lucide="calendar-days" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-weekly">0</div>
                        <div class="stat-label">Weekly Tests</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#8b5cf6;--stat-bg:rgba(139,92,246,.1);">
                    <div class="stat-icon"><i data-lucide="calendar" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-monthly">0</div>
                        <div class="stat-label">Monthly Tests</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#10b981;--stat-bg:rgba(16,185,129,.1);">
                    <div class="stat-icon"><i data-lucide="users" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-attempts">0</div>
                        <div class="stat-label">Total Attempts</div>
                    </div>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="card">
                <div class="table-controls">
                    <div class="search-box">
                        <i data-lucide="search" class="search-box-icon"></i>
                        <input type="text" id="test-search" class="form-control" placeholder="Search tests…" autocomplete="off">
                    </div>
                    <select id="filter-type" class="form-control" style="width:140px;">
                        <option value="">All Types</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <select id="filter-batch" class="form-control" style="width:190px;">
                        <option value="">All My Batches</option>
                        <?php foreach ($myBatches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['course_title'] . ' — ' . $b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filter-status" class="form-control" style="width:130px;">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <div class="table-controls-right">
                        <select id="per-page" class="form-control" style="width:110px;">
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
                </div>

                <!-- Bulk action bar -->
                <div class="bulk-action-bar" id="bulk-action-bar">
                    <span class="bulk-action-count" id="bulk-count">0 rows selected</span>
                    <span class="bulk-action-sep">|</span>
                    <button class="bulk-btn" onclick="bulkToggleStatus('active')">Activate</button>
                    <button class="bulk-btn" onclick="bulkToggleStatus('inactive')">Deactivate</button>
                </div>

                <div style="overflow-x:auto;">
                    <table id="tests-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                                <th>Test</th>
                                <th>Type</th>
                                <th>Batch</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Attempts</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tests-tbody">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="skeleton skeleton-text" style="width:100%;margin:8px 0;"></div>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div id="tests-pagination"></div>
            </div>

        </main>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     CREATE / EDIT WIZARD MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="test-wizard-overlay">
    <div class="modal modal-xl" style="max-height:92vh;">
        <div class="modal-header">
            <div class="modal-icon modal-icon-primary">
                <i data-lucide="clipboard-list" style="width:20px;height:20px;"></i>
            </div>
            <h3 class="modal-title" id="wizard-modal-title">Create Test</h3>
            <button class="modal-close" onclick="closeWizard()">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="modal-body" style="overflow-y:auto;">

            <!-- Steps -->
            <div class="wizard-steps">
                <div class="wizard-step active" id="ws-1">
                    <div class="step-num">1</div>
                    <span class="step-label">Test Details</span>
                </div>
                <div class="wizard-step" id="ws-2">
                    <div class="step-num">2</div>
                    <span class="step-label">Questions</span>
                </div>
            </div>

            <!-- Step 1: Details -->
            <div id="wizard-step-1">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Test Title <span class="required">*</span></label>
                        <input type="text" id="w-title" class="form-control" placeholder="e.g. Week 3 — JavaScript Arrays">
                        <div class="form-error" id="err-title"></div>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Description <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                        <textarea id="w-desc" class="form-control" rows="2" placeholder="Topics covered, instructions…"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Test Type <span class="required">*</span></label>
                        <select id="w-type" class="form-control">
                            <option value="">Select type…</option>
                            <option value="weekly">Weekly Test</option>
                            <option value="monthly">Monthly Test</option>
                        </select>
                        <div class="form-error" id="err-type"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (minutes) <span class="required">*</span></label>
                        <input type="number" id="w-time" class="form-control" value="30" min="5" max="180">
                        <div class="form-error" id="err-time"></div>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Batch <span class="required">*</span></label>
                        <select id="w-batch" class="form-control">
                            <option value="">Select your batch…</option>
                            <?php foreach ($myBatches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['course_title'] . ' — ' . $b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-error" id="err-batch"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="w-status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive (draft)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 2: Questions -->
            <div id="wizard-step-2" style="display:none;">
                <!-- Lock banner -->
                <div class="lock-banner" id="lock-banner" style="display:none;">
                    <div class="lock-banner-icon">⚠️</div>
                    <div class="lock-banner-text">
                        <strong>Questions are locked.</strong> Students have already submitted attempts.
                        Saving new questions will <strong>permanently delete all <span id="lock-attempt-count">0</span> attempt(s)</strong> and reset the lock.
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div>
                        <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:0.95rem;color:var(--text);">Questions</div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                            4 options per question. Mark exactly one correct.
                            <strong id="q-count-label">0 questions</strong> added.
                        </div>
                    </div>
                </div>
                <div id="questions-container"></div>
                <button class="add-q-btn" onclick="addQuestion()">
                    <i data-lucide="plus-circle" style="width:18px;height:18px;"></i>
                    Add Question
                </button>
                <div class="form-error" id="err-questions" style="margin-top:10px;"></div>
            </div>

        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="wizard-back-btn" style="display:none;" onclick="wizardBack()">
                <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back
            </button>
            <button class="btn btn-secondary" onclick="closeWizard()">Cancel</button>
            <button class="btn btn-primary" id="wizard-next-btn" onclick="wizardNext()">
                Next: Add Questions <i data-lucide="arrow-right" style="width:15px;height:15px;"></i>
            </button>
            <button class="btn btn-primary" id="wizard-save-btn" style="display:none;" onclick="saveTest()">
                <i data-lucide="save" style="width:15px;height:15px;"></i>
                <span id="wizard-save-text">Save Test</span>
            </button>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     VIEW TEST DETAILS MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="view-test-overlay">
    <div class="modal modal-xl" style="max-height:92vh;">
        <div class="modal-header">
            <div class="modal-icon modal-icon-primary">
                <i data-lucide="bar-chart-2" style="width:20px;height:20px;"></i>
            </div>
            <h3 class="modal-title" id="view-test-title">Test Details</h3>
            <button class="modal-close" onclick="Modal.close('view-test')">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="modal-body" id="view-test-body" style="overflow-y:auto;">
            <div class="chat-loading-row">Loading…</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Modal.close('view-test')">Close</button>
            <button class="btn btn-primary" id="view-export-btn" onclick="exportTestExcel()">
                <i data-lucide="download" style="width:15px;height:15px;"></i> Export Excel
            </button>
        </div>
    </div>
</div>


<script>
    'use strict';

    const AJAX = window.LMS_BASE + '/ajax/tests.ajax.php';
    let currentPage = 1;
    let currentViewTestId = null;
    let editingTestId = null;
    let questionsLocked = false;
    let lockedAttemptCount = 0;

    // ── AJAX helper ───────────────────────────────────────────────
    async function testsAjax(data) {
        return ajax(AJAX, data);
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Load table ────────────────────────────────────────────────
    async function loadTests(page = 1) {
        currentPage = page;
        const search = document.getElementById('test-search').value.trim();
        const type = document.getElementById('filter-type').value;
        const status = document.getElementById('filter-status').value;
        const batchF = document.getElementById('filter-batch').value;
        const perPage = document.getElementById('per-page').value;

        const res = await testsAjax({
            action: 'list',
            page,
            per_page: perPage,
            search,
            type,
            status,
            batch_id: batchF
        });
        if (res.status !== 'success') {
            Toast.error(res.message);
            return;
        }

        const tests = res.data.tests || [];
        const stats = res.data.stats || {};

        // Stats — teacher-specific: only show weekly/monthly from their batches
        document.getElementById('stat-total').textContent = res.data.total || 0;
        document.getElementById('stat-weekly').textContent = tests.filter(t => t.type === 'weekly').length || stats.weekly || 0;
        document.getElementById('stat-monthly').textContent = tests.filter(t => t.type === 'monthly').length || stats.monthly || 0;
        document.getElementById('stat-attempts').textContent = stats.attempts || 0;

        const tbody = document.getElementById('tests-tbody');

        if (!tests.length) {
            tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">' +
                '<div class="empty-state-icon">📋</div>' +
                '<div class="empty-state-title">No tests yet</div>' +
                '<div class="empty-state-text">Click "Create Test" to add a weekly or monthly test for your batch.</div>' +
                '</div></td></tr>';
            document.getElementById('tests-pagination').innerHTML = '';
            return;
        }

        tbody.innerHTML = tests.map(t => {
            const typeBadge = '<span class="badge badge-' + t.type + '">' + t.type.charAt(0).toUpperCase() + t.type.slice(1) + '</span>';
            const scope = [t.course_title, t.batch_name].filter(Boolean).join(' — ') || '—';
            const statusBadge = t.status === 'active' ?
                '<span class="badge badge-success"><span class="badge-dot" style="background:#10b981;"></span>Active</span>' :
                '<span class="badge badge-warning"><span class="badge-dot" style="background:#f59e0b;"></span>Inactive</span>';
            const lockChip = t.questions_locked == 1 ?
                ' <span class="lock-chip">🔒 Locked</span>' : '';

            return '<tr>' +
                '<td><input type="checkbox" class="row-cb" value="' + t.id + '" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>' +
                '<td>' +
                '<div style="font-weight:600;font-size:0.875rem;color:var(--text);">' + esc(t.title) + '</div>' +
                (t.description ? '<div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">' + esc(t.description.substring(0, 55)) + (t.description.length > 55 ? '…' : '') + '</div>' : '') +
                '</td>' +
                '<td>' + typeBadge + '</td>' +
                '<td style="font-size:0.84rem;color:var(--text-secondary);">' + esc(scope) + '</td>' +
                '<td style="font-size:0.84rem;"><strong>' + (t.question_count || 0) + '</strong> Q' + lockChip + '</td>' +
                '<td style="font-size:0.84rem;">' + t.time_minutes + ' min</td>' +
                '<td style="font-size:0.84rem;">' + (t.attempt_count || 0) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                '<div class="table-actions" style="justify-content:flex-end;">' +
                '<button class="action-btn action-btn-view" onclick="viewTest(' + t.id + ')" title="View Results"><i data-lucide="bar-chart-2" style="width:14px;height:14px;"></i></button>' +
                '<button class="action-btn action-btn-edit" onclick="openEditWizard(' + t.id + ')" title="Edit Test"><i data-lucide="edit" style="width:14px;height:14px;"></i></button>' +
                '<button class="action-btn" onclick="copyTestAction(' + t.id + ')" title="Copy Test" style="color:var(--primary);"><i data-lucide="copy" style="width:14px;height:14px;"></i></button>' +
                '<button class="action-btn action-btn-delete" onclick="deleteTest(' + t.id + ')" title="Delete"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>' +
                '</div>' +
                '</td>' +
                '</tr>';
        }).join('');

        document.getElementById('tests-pagination').innerHTML = buildPagination(res.data.total, page, parseInt(perPage));
        document.querySelectorAll('.page-btn').forEach(b => {
            b.addEventListener('click', ev => {
                ev.preventDefault();
                const pg = parseInt(new URL(b.href).searchParams.get('page'));
                if (pg) loadTests(pg);
            });
        });

        BulkSelect.init('tests-table');
        if (window.lucide) lucide.createIcons();
    }

    function buildPagination(total, page, perPage) {
        const tp = Math.max(1, Math.ceil(total / perPage));
        if (tp <= 1) return '';
        const s = (page - 1) * perPage + 1,
            en = Math.min(page * perPage, total);
        const url = p => '?page=' + p;
        let h = '<div class="pagination-wrapper"><span class="pagination-info">Showing ' + s + '–' + en + ' of ' + total + '</span><div class="pagination-controls">';
        h += '<a href="' + url(page - 1) + '" class="page-btn' + (page <= 1 ? ' disabled' : '') + '">‹</a>';
        for (let i = 1; i <= tp; i++) {
            if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) h += '<a href="' + url(i) + '" class="page-btn' + (i === page ? ' active' : '') + '">' + i + '</a>';
            else if (i === page - 3 || i === page + 3) h += '<span class="page-ellipsis">…</span>';
        }
        h += '<a href="' + url(page + 1) + '" class="page-btn' + (page >= tp ? ' disabled' : '') + '">›</a>';
        return h + '</div></div>';
    }

    // ── Filters ───────────────────────────────────────────────────
    const debouncedLoad = debounce(() => loadTests(1), 350);
    document.getElementById('test-search').addEventListener('input', debouncedLoad);
    document.getElementById('filter-type').addEventListener('change', () => loadTests(1));
    document.getElementById('filter-batch').addEventListener('change', () => loadTests(1));
    document.getElementById('filter-status').addEventListener('change', () => loadTests(1));
    document.getElementById('per-page').addEventListener('change', () => loadTests(1));

    // ── Wizard: Open for CREATE ───────────────────────────────────
    function openCreateWizard() {
        editingTestId = null;
        questionsLocked = false;
        document.getElementById('wizard-modal-title').textContent = 'Create Test';
        document.getElementById('wizard-save-text').textContent = 'Save Test';
        document.getElementById('lock-banner').style.display = 'none';
        resetWizardFields();
        addQuestion();
        setWizardStep(1);
        Modal.open('test-wizard');
    }

    // ── Wizard: Open for EDIT ─────────────────────────────────────
    async function openEditWizard(testId) {
        editingTestId = testId;
        questionsLocked = false;
        lockedAttemptCount = 0;
        document.getElementById('wizard-modal-title').textContent = 'Edit Test';
        document.getElementById('wizard-save-text').textContent = 'Update Test';
        document.getElementById('lock-banner').style.display = 'none';
        resetWizardFields();
        setWizardStep(1);
        Modal.open('test-wizard');

        const res = await testsAjax({
            action: 'get_one',
            test_id: testId
        });
        if (res.status !== 'success') {
            Toast.error(res.message);
            return;
        }
        const t = res.data;

        document.getElementById('w-title').value = t.title;
        document.getElementById('w-desc').value = t.description || '';
        document.getElementById('w-type').value = t.type;
        document.getElementById('w-time').value = t.time_minutes;
        document.getElementById('w-status').value = t.status;
        document.getElementById('w-batch').value = t.batch_id || '';

        if (t.questions_locked == 1) {
            questionsLocked = true;
            const dRes = await testsAjax({
                action: 'get_details',
                test_id: testId
            });
            if (dRes.status === 'success') {
                lockedAttemptCount = (dRes.data.attempts || []).length;
            }
        }

        const qRes = await testsAjax({
            action: 'get_questions',
            test_id: testId
        });
        if (qRes.status === 'success' && qRes.data.questions.length) {
            populateEditQuestions(qRes.data.questions);
        } else {
            addQuestion();
        }
    }

    // ── Populate existing questions into wizard ───────────────────
    function populateEditQuestions(questions) {
        const container = document.getElementById('questions-container');
        container.innerHTML = '';
        questions.forEach((q, idx) => {
            qCounter++;
            const n = qCounter;
            const correctIdx = q.options.findIndex(o => o.is_correct == 1);
            const div = document.createElement('div');
            div.className = 'q-card';
            div.id = 'q-card-' + n;
            div.innerHTML = buildQuestionHTML(n, idx + 1);
            container.appendChild(div);

            document.getElementById('q-text-' + n).value = q.question_text || '';
            document.getElementById('q-iscode-' + n).checked = q.is_code == 1;
            q.options.forEach((opt, i) => {
                const inp = document.getElementById('q-opt-' + n + '-' + i);
                if (inp) inp.value = opt.option_text || '';
            });
            if (correctIdx >= 0) {
                const radio = document.querySelector('input[name="q-correct-' + n + '"][value="' + correctIdx + '"]');
                if (radio) radio.checked = true;
            }
        });
        updateQNumbers();
        updateQCountLabel();
        if (window.lucide) lucide.createIcons();
    }

    function resetWizardFields() {
        ['w-title', 'w-desc'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('w-time').value = '30';
        document.getElementById('w-type').value = '';
        document.getElementById('w-status').value = 'active';
        document.getElementById('w-batch').value = '';
        ['err-title', 'err-type', 'err-time', 'err-batch', 'err-questions'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.getElementById('questions-container').innerHTML = '';
        qCounter = 0;
    }

    function closeWizard() {
        Modal.close('test-wizard');
    }

    // ── Wizard steps ──────────────────────────────────────────────
    function wizardNext() {
        const title = document.getElementById('w-title').value.trim();
        const type = document.getElementById('w-type').value;
        const time = parseInt(document.getElementById('w-time').value);
        const batchId = document.getElementById('w-batch').value;
        let ok = true;

        ['err-title', 'err-type', 'err-time', 'err-batch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });

        if (!title) {
            document.getElementById('err-title').textContent = 'Title is required';
            ok = false;
        }
        if (!type) {
            document.getElementById('err-type').textContent = 'Select a test type';
            ok = false;
        }
        if (!time || time < 5) {
            document.getElementById('err-time').textContent = 'Minimum 5 minutes';
            ok = false;
        }
        if (!batchId) {
            document.getElementById('err-batch').textContent = 'Select a batch';
            ok = false;
        }
        if (!ok) return;

        if (editingTestId && questionsLocked) {
            document.getElementById('lock-attempt-count').textContent = lockedAttemptCount;
            document.getElementById('lock-banner').style.display = '';
        }
        setWizardStep(2);
    }

    function wizardBack() {
        setWizardStep(1);
    }

    function setWizardStep(step) {
        document.getElementById('wizard-step-1').style.display = step === 1 ? '' : 'none';
        document.getElementById('wizard-step-2').style.display = step === 2 ? '' : 'none';
        document.getElementById('wizard-back-btn').style.display = step === 2 ? '' : 'none';
        document.getElementById('wizard-next-btn').style.display = step === 1 ? '' : 'none';
        document.getElementById('wizard-save-btn').style.display = step === 2 ? '' : 'none';
        document.getElementById('ws-1').className = 'wizard-step' + (step === 1 ? ' active' : ' done');
        document.getElementById('ws-2').className = 'wizard-step' + (step === 2 ? ' active' : '');
        if (window.lucide) lucide.createIcons();
    }

    // ── Question builder ──────────────────────────────────────────
    let qCounter = 0;

    function buildQuestionHTML(n, num) {
        return '<div class="q-card-header">' +
            '<div class="q-num">' + num + '</div>' +
            '<span style="font-size:0.82rem;font-weight:600;color:var(--text-secondary);">Question</span>' +
            '<label style="display:flex;align-items:center;gap:6px;font-size:0.78rem;color:var(--text-muted);margin-left:8px;cursor:pointer;">' +
            '<input type="checkbox" id="q-iscode-' + n + '" style="accent-color:var(--primary);"> Code snippet' +
            '</label>' +
            '<button class="q-remove-btn" onclick="removeQuestion(' + n + ')" title="Remove">' +
            '<i data-lucide="x" style="width:13px;height:13px;"></i>' +
            '</button>' +
            '</div>' +
            '<textarea id="q-text-' + n + '" class="form-control" rows="3"' +
            ' placeholder="Enter question text…" style="font-family:inherit;white-space:pre-wrap;"></textarea>' +
            '<div class="q-options-grid">' +
            ['A', 'B', 'C', 'D'].map((l, i) =>
                '<div class="q-option-row">' +
                '<span class="q-option-letter">' + l + '</span>' +
                '<input type="text" class="form-control q-opt-input" id="q-opt-' + n + '-' + i + '" placeholder="Option ' + l + '" style="flex:1;">' +
                '</div>'
            ).join('') +
            '</div>' +
            '<div class="q-correct-wrap">' +
            '<span>Correct answer:</span>' +
            ['A', 'B', 'C', 'D'].map((l, i) =>
                '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:0.8rem;font-weight:600;color:var(--text-secondary);">' +
                '<input type="radio" name="q-correct-' + n + '" value="' + i + '" style="accent-color:var(--success);"> ' + l +
                '</label>'
            ).join('') +
            '</div>';
    }

    function addQuestion() {
        qCounter++;
        const n = qCounter;
        const num = document.querySelectorAll('.q-card').length + 1;
        const div = document.createElement('div');
        div.className = 'q-card';
        div.id = 'q-card-' + n;
        div.innerHTML = buildQuestionHTML(n, num);
        document.getElementById('questions-container').appendChild(div);
        updateQNumbers();
        updateQCountLabel();
        if (window.lucide) lucide.createIcons();
    }

    function removeQuestion(n) {
        const card = document.getElementById('q-card-' + n);
        if (card) card.remove();
        updateQNumbers();
        updateQCountLabel();
    }

    function updateQNumbers() {
        document.querySelectorAll('.q-card').forEach((card, idx) => {
            const el = card.querySelector('.q-num');
            if (el) el.textContent = idx + 1;
        });
    }

    function updateQCountLabel() {
        const count = document.querySelectorAll('.q-card').length;
        document.getElementById('q-count-label').textContent = count + ' question' + (count !== 1 ? 's' : '');
    }

    // ── Save / Update test ────────────────────────────────────────
    async function saveTest() {
        const cards = document.querySelectorAll('.q-card');
        const errQ = document.getElementById('err-questions');
        errQ.textContent = '';

        if (!cards.length) {
            errQ.textContent = 'Add at least one question.';
            return;
        }

        const questions = [];
        let valid = true;

        cards.forEach((card, idx) => {
            if (!valid) return;
            const n = card.id.replace('q-card-', '');
            const text = document.getElementById('q-text-' + n)?.value.trim();
            const isCode = document.getElementById('q-iscode-' + n)?.checked ? 1 : 0;
            const opts = [0, 1, 2, 3].map(i => document.getElementById('q-opt-' + n + '-' + i)?.value.trim() || '');
            const correct = card.querySelector('input[name="q-correct-' + n + '"]:checked')?.value;

            if (!text) {
                errQ.textContent = 'Question ' + (idx + 1) + ': text is required';
                valid = false;
                return;
            }
            if (opts.some(o => o === '' || o === null || o === undefined)) {
                errQ.textContent = 'Question ' + (idx + 1) + ': all 4 options are required';
                valid = false;
                return;
            }
            if (correct == null) {
                errQ.textContent = 'Question ' + (idx + 1) + ': mark the correct answer';
                valid = false;
                return;
            }

            questions.push({
                text,
                is_code: isCode,
                options: opts,
                correct: parseInt(correct)
            });
        });

        if (!valid) return;

        if (editingTestId && questionsLocked) {
            const confirmed = await new Promise(resolve => {
                Modal.confirm({
                    title: 'Questions are Locked',
                    message: 'This test has <strong>' + lockedAttemptCount + ' student attempt(s)</strong>. Saving will permanently delete all attempts and reset the lock. Are you sure?',
                    confirmText: 'Yes, Delete & Update',
                    confirmClass: 'btn-danger',
                    icon: '🔓',
                    iconClass: 'modal-icon-warning',
                    onConfirm: () => resolve(true),
                    onCancel: () => resolve(false),
                });
            });
            if (!confirmed) return;
            await _doSave(questions, 1);
        } else {
            await _doSave(questions, 0);
        }
    }

    async function _doSave(questions, force) {
        const btn = document.getElementById('wizard-save-btn');
        const btnText = document.getElementById('wizard-save-text');
        btn.disabled = true;
        btnText.textContent = editingTestId ? 'Updating…' : 'Saving…';

        const payload = {
            action: editingTestId ? 'update' : 'create',
            title: document.getElementById('w-title').value.trim(),
            description: document.getElementById('w-desc').value.trim(),
            type: document.getElementById('w-type').value,
            batch_id: document.getElementById('w-batch').value,
            time_minutes: document.getElementById('w-time').value,
            status: document.getElementById('w-status').value,
            questions: JSON.stringify(questions),
        };
        if (editingTestId) {
            payload.test_id = editingTestId;
            payload.force = force;
        }

        const res = await testsAjax(payload);

        btn.disabled = false;
        btnText.textContent = editingTestId ? 'Update Test' : 'Save Test';

        if (res.status === 'locked') {
            questionsLocked = true;
            lockedAttemptCount = res.data?.attempt_count || 0;
            Toast.error('Questions are locked. Confirm to proceed.');
            return;
        }
        if (res.status !== 'success') {
            Toast.error(res.message);
            return;
        }

        Toast.success(editingTestId ? 'Test updated successfully' : 'Test created successfully');
        closeWizard();
        loadTests(currentPage);
    }

    // ── Copy test ─────────────────────────────────────────────────
    function copyTestAction(testId) {
        Modal.confirm({
            title: 'Copy Test?',
            message: 'A duplicate of this test (with all questions) will be created as Inactive.',
            confirmText: 'Copy',
            confirmClass: 'btn-primary',
            icon: '📋',
            iconClass: 'modal-icon-primary',
            onConfirm: async () => {
                const res = await testsAjax({
                    action: 'copy_test',
                    test_id: testId
                });
                if (res.status !== 'success') {
                    Toast.error(res.message);
                    return;
                }
                Toast.success('Test copied! Edit and activate it when ready.');
                loadTests(currentPage);
            }
        });
    }

    // ── View Test Details ─────────────────────────────────────────
    async function viewTest(testId) {
        currentViewTestId = testId;
        document.getElementById('view-test-body').innerHTML = '<div class="chat-loading-row">Loading…</div>';
        Modal.open('view-test');

        const res = await testsAjax({
            action: 'get_details',
            test_id: testId
        });
        if (res.status !== 'success') {
            document.getElementById('view-test-body').innerHTML = '<div style="color:var(--danger);padding:20px;">' + esc(res.message) + '</div>';
            return;
        }

        const t = res.data.test;
        const attempts = res.data.attempts || [];
        document.getElementById('view-test-title').textContent = t.title;

        const bands = {
            superb: 0,
            excellent: 0,
            good: 0,
            average: 0,
            weak: 0
        };
        attempts.forEach(a => {
            const p = parseFloat(a.percentage || 0);
            if (p >= 90) bands.superb++;
            else if (p >= 75) bands.excellent++;
            else if (p >= 60) bands.good++;
            else if (p >= 40) bands.average++;
            else bands.weak++;
        });
        const totalA = attempts.length || 1;

        let perfHTML = '';
        [
            ['Superb (90–100%)', bands.superb, '#10b981'],
            ['Excellent (75–89%)', bands.excellent, '#6366f1'],
            ['Good (60–74%)', bands.good, '#06b6d4'],
            ['Average (40–59%)', bands.average, '#f59e0b'],
            ['Weak (0–39%)', bands.weak, '#ef4444'],
        ].forEach(([label, count, color]) => {
            const pct = ((count / totalA) * 100).toFixed(1);
            perfHTML += '<div class="perf-bar-wrap">' +
                '<div class="perf-bar-label"><span>' + label + '</span><span>' + count + ' student' + (count !== 1 ? 's' : '') + '</span></div>' +
                '<div class="perf-bar"><div class="perf-bar-fill" style="width:' + pct + '%;background:' + color + ';"></div></div>' +
                '</div>';
        });

        const scope = [t.course_title, t.batch_name].filter(Boolean).join(' — ') || '—';
        const avgScore = attempts.length ?
            (attempts.reduce((s, a) => s + parseFloat(a.percentage || 0), 0) / attempts.length).toFixed(1) + '%' :
            '—';

        let html = '';
        html += '<div class="test-stat-grid">' +
            '<div class="test-stat-card"><div class="test-stat-val">' + (t.question_count || 0) + '</div><div class="test-stat-lbl">Questions</div></div>' +
            '<div class="test-stat-card"><div class="test-stat-val">' + t.time_minutes + 'm</div><div class="test-stat-lbl">Duration</div></div>' +
            '<div class="test-stat-card"><div class="test-stat-val">' + attempts.length + '</div><div class="test-stat-lbl">Attempts</div></div>' +
            '<div class="test-stat-card"><div class="test-stat-val">' + avgScore + '</div><div class="test-stat-lbl">Avg Score</div></div>' +
            '</div>';

        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">';

        html += '<div>' +
            '<div style="font-family:\'Poppins\',sans-serif;font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:12px;">Performance Distribution</div>' +
            perfHTML +
            '</div>';

        const lockBadge = t.questions_locked == 1 ?
            '<span class="lock-chip">🔒 Locked</span>' :
            '<span style="color:var(--success);font-weight:600;">Unlocked</span>';

        html += '<div>' +
            '<div style="font-family:\'Poppins\',sans-serif;font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:12px;">Test Info</div>' +
            '<div style="font-size:0.84rem;display:flex;flex-direction:column;gap:8px;">' +
            '<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Type</span><span class="badge badge-' + esc(t.type) + '">' + esc(t.type) + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Batch</span><span style="font-weight:600;">' + esc(scope) + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Lock</span><span>' + lockBadge + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Created</span><span style="font-weight:600;">' + new Date(t.created_at).toLocaleDateString('en', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }) + '</span></div>' +
            '</div></div>';

        html += '</div>';

        // Student results table
        if (attempts.length) {
            html += '<div style="font-family:\'Poppins\',sans-serif;font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:12px;">Student Results</div>';
            html += '<div style="overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border);">';
            html += '<table style="width:100%;border-collapse:collapse;font-size:0.84rem;">';
            html += '<thead style="background:var(--bg);"><tr>' +
                '<th style="padding:10px 14px;text-align:left;font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;">Student</th>' +
                '<th style="padding:10px 14px;text-align:center;">Score</th>' +
                '<th style="padding:10px 14px;text-align:center;">%</th>' +
                '<th style="padding:10px 14px;text-align:center;">Correct</th>' +
                '<th style="padding:10px 14px;text-align:center;">Wrong</th>' +
                '<th style="padding:10px 14px;text-align:center;">Skipped</th>' +
                '</tr></thead><tbody>';

            attempts.forEach(a => {
                const pct = parseFloat(a.percentage || 0);
                const band = pct >= 90 ? 'Superb' : pct >= 75 ? 'Excellent' : pct >= 60 ? 'Good' : pct >= 40 ? 'Average' : 'Weak';
                const col = pct >= 90 ? '#10b981' : pct >= 75 ? '#6366f1' : pct >= 60 ? '#06b6d4' : pct >= 40 ? '#f59e0b' : '#ef4444';
                html += '<tr style="border-top:1px solid var(--border);">' +
                    '<td style="padding:10px 14px;"><div style="font-weight:600;">' + esc(a.full_name) + '</div><div style="font-size:0.72rem;color:var(--text-muted);">' + esc(a.student_id_number || '') + '</div></td>' +
                    '<td style="padding:10px 14px;text-align:center;font-weight:700;font-family:\'Poppins\',sans-serif;">' + parseFloat(a.score || 0).toFixed(2) + '</td>' +
                    '<td style="padding:10px 14px;text-align:center;"><span style="font-weight:700;color:' + col + ';">' + pct.toFixed(1) + '%</span><div style="font-size:0.65rem;color:' + col + ';">' + band + '</div></td>' +
                    '<td style="padding:10px 14px;text-align:center;color:#10b981;font-weight:600;">' + (a.correct_count || 0) + '</td>' +
                    '<td style="padding:10px 14px;text-align:center;color:#ef4444;font-weight:600;">' + (a.wrong_count || 0) + '</td>' +
                    '<td style="padding:10px 14px;text-align:center;color:var(--text-muted);">' + (a.unanswered_count || 0) + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
        } else {
            html += '<div class="empty-state" style="padding:30px;"><div class="empty-state-icon">📊</div><div class="empty-state-title">No attempts yet</div><div class="empty-state-text">Students haven\'t attempted this test yet.</div></div>';
        }

        document.getElementById('view-test-body').innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }

    // ── Delete test ───────────────────────────────────────────────
    function deleteTest(testId) {
        Modal.confirm({
            title: 'Delete Test?',
            message: 'This will permanently delete the test, all its questions, and all student attempts. This cannot be undone.',
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            icon: '🗑️',
            iconClass: 'modal-icon-danger',
            onConfirm: async () => {
                const res = await testsAjax({
                    action: 'delete',
                    test_id: testId
                });
                if (res.status !== 'success') {
                    Toast.error(res.message);
                    return;
                }
                Toast.success('Test deleted');
                loadTests(currentPage);
            }
        });
    }

    // ── Bulk status toggle ────────────────────────────────────────
    async function bulkToggleStatus(status) {
        const ids = BulkSelect.getSelected('tests-table');
        if (!ids.length) return;
        const res = await testsAjax({
            action: 'bulk_status',
            ids: ids.join(','),
            status
        });
        if (res.status !== 'success') {
            Toast.error(res.message);
            return;
        }
        Toast.success(res.message);
        BulkSelect.reset('tests-table');
        loadTests(currentPage);
    }

    // ── Export Excel ──────────────────────────────────────────────
    function exportTestExcel() {
        if (!currentViewTestId) return;
        const btn = document.getElementById('view-export-btn');
        btn.disabled = true;
        btn.innerHTML = '<div style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;margin-right:6px;"></div> Exporting…';
        window.location.href = window.LMS_BASE + '/ajax/tests.ajax.php?action=export_excel&test_id=' + currentViewTestId;
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="download" style="width:15px;height:15px;"></i> Export Excel';
            if (window.lucide) lucide.createIcons();
        }, 2500);
    }

    // ── Init ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => loadTests(1));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>