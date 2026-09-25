<?php
// ============================================================
// ADMIN — APPLICATIONS MODULE
// List, review, approve & reject course applications
// ============================================================
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Applications';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => BASE_PATH . '/admin/'],
    ['label' => 'Applications'],
];
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Status badges ─────────────────────────────────────────── */
.badge-pending        { background:rgba(245,158,11,.12); color:#d97706; }
.badge-test_submitted { background:rgba(99,102,241,.12);  color:#4f46e5; }
.badge-approved       { background:rgba(16,185,129,.12);  color:#059669; }
.badge-rejected       { background:rgba(239,68,68,.12);   color:#dc2626; }

/* ── Score bar ─────────────────────────────────────────────── */
.score-bar-wrap { display:flex; align-items:center; gap:8px; }
.score-bar      { flex:1; height:6px; background:var(--border); border-radius:99px; overflow:hidden; }
.score-bar-fill { height:100%; border-radius:99px; transition:width .6s cubic-bezier(.4,0,.2,1); }

/* ── Detail modal layout ───────────────────────────────────── */
.app-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 18px;
}
.app-detail-field {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 14px;
}
.app-detail-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-muted);
    margin-bottom: 4px;
}
.app-detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text);
}

/* ── Action quick buttons ──────────────────────────────────── */
.app-action-bar {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

/* ── Result strip ──────────────────────────────────────────── */
.result-strip {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 8px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    margin-bottom: 16px;
}
.result-strip-item { text-align: center; }
.result-strip-val  { font-family:'Poppins',sans-serif; font-weight:800; font-size:1.2rem; color:var(--primary); }
.result-strip-lbl  { font-size:0.65rem; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-top:2px; }

/* ── Empty state inside table ──────────────────────────────── */
.inline-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 20px;
    gap: 10px;
}
.inline-empty-icon  { font-size: 2.5rem; }
.inline-empty-title { font-family:'Poppins',sans-serif; font-weight:700; font-size:1rem; color:var(--text); }
.inline-empty-sub   { font-size:0.84rem; color:var(--text-muted); text-align:center; }
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Applications</h1>
                    <p class="page-subtitle">Review and manage course applications from students</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-primary" onclick="openEnrollModal()">
                        <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Enroll Student
                    </button>
                    <button class="btn btn-secondary" onclick="exportApplications()" id="export-apps-btn">
                        <i data-lucide="download" style="width:16px;height:16px;"></i> Export Excel
                    </button>
                </div>
            </div>

            <!-- ── Stats row ──────────────────────────────── -->
            <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px;">
                <div class="stat-card" style="--stat-color:#6366f1;--stat-bg:rgba(99,102,241,.1);">
                    <div class="stat-icon"><i data-lucide="inbox" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-total">—</div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#f59e0b;--stat-bg:rgba(245,158,11,.1);">
                    <div class="stat-icon"><i data-lucide="clock" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-pending">—</div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#8b5cf6;--stat-bg:rgba(139,92,246,.1);">
                    <div class="stat-icon"><i data-lucide="file-check" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-test-submitted">—</div>
                        <div class="stat-label">Test Submitted</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#10b981;--stat-bg:rgba(16,185,129,.1);">
                    <div class="stat-icon"><i data-lucide="check-circle" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-approved">—</div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="stat-card" style="--stat-color:#ef4444;--stat-bg:rgba(239,68,68,.1);">
                    <div class="stat-icon"><i data-lucide="x-circle" style="width:22px;height:22px;"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" id="stat-rejected">—</div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- ── Main table card ────────────────────────── -->
            <div class="card">
                <div class="table-controls">
                    <div class="search-box">
                        <i data-lucide="search" class="search-box-icon"></i>
                        <input type="text" id="app-search" class="form-control" placeholder="Search name, ID or email…" autocomplete="off">
                    </div>
                    <select id="filter-status" class="form-control" style="width:160px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="test_submitted">Test Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select id="filter-batch" class="form-control" style="width:190px;">
                        <option value="">All Batches</option>
                    </select>
                    <div class="table-controls-right">
                        <select id="per-page" class="form-control" style="width:110px;">
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
                </div>

                <!-- Bulk action bar -->
                <div class="bulk-action-bar" id="bulk-action-bar">
                    <span class="bulk-action-count" id="bulk-count">0 selected</span>
                    <span class="bulk-action-sep">|</span>
                    <button class="bulk-btn" onclick="bulkApproveAction()" style="color:#10b981;">✓ Approve All</button>
                    <button class="bulk-btn bulk-btn-danger" onclick="bulkRejectAction()">✗ Reject All</button>
                </div>

                <div style="overflow-x:auto;">
                    <table id="apps-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" class="select-all-cb"
                                        style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);">
                                </th>
                                <th>Student</th>
                                <th>Course / Batch</th>
                                <th>Applied</th>
                                <th>Test Score</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="apps-tbody">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <tr>
                                <td><div class="skeleton" style="width:16px;height:16px;border-radius:3px;"></div></td>
                                <td><div class="skeleton skeleton-text" style="width:140px;"></div></td>
                                <td><div class="skeleton skeleton-text" style="width:170px;"></div></td>
                                <td><div class="skeleton skeleton-text" style="width:80px;"></div></td>
                                <td><div class="skeleton skeleton-text" style="width:100px;"></div></td>
                                <td><div class="skeleton" style="width:80px;height:22px;border-radius:20px;"></div></td>
                                <td><div class="skeleton" style="width:100px;height:28px;border-radius:6px;float:right;"></div></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div id="apps-pagination"></div>
            </div>

        </main>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     APPLICATION DETAIL MODAL
═══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="app-detail-overlay">
    <div class="modal modal-lg" style="max-height:90vh;">
        <div class="modal-header">
            <div class="modal-icon modal-icon-primary">
                <i data-lucide="file-text" style="width:20px;height:20px;"></i>
            </div>
            <h3 class="modal-title">Application Detail</h3>
            <button class="modal-close" onclick="Modal.close('app-detail')">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="modal-body" id="app-detail-body" style="overflow-y:auto;">
            <div class="chat-loading-row">Loading…</div>
        </div>
        <div class="modal-footer" id="app-detail-footer"></div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     REJECT REASON MODAL
═══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="reject-modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-icon modal-icon-danger">
                <i data-lucide="x-circle" style="width:20px;height:20px;"></i>
            </div>
            <h3 class="modal-title" id="reject-modal-title">Reject Application</h3>
            <button class="modal-close" onclick="closeRejectModal()">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="reject-app-ids">
            <div style="margin-bottom:14px;font-size:0.875rem;color:var(--text-secondary);line-height:1.6;"
                 id="reject-modal-desc">
                The student will receive an email with this rejection notice.
            </div>
            <div class="form-group">
                <label class="form-label">
                    Reason
                    <span style="font-weight:400;color:var(--text-muted);">(optional — will be emailed to student)</span>
                </label>
                <textarea id="reject-reason" class="form-control" rows="4"
                    placeholder="e.g. Your entry test score did not meet the minimum threshold for this batch. You are welcome to apply for the next intake."
                    style="resize:vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
            <button class="btn btn-danger" onclick="submitRejection()" id="reject-confirm-btn">
                <i data-lucide="x-circle" style="width:15px;height:15px;"></i>
                <span id="reject-confirm-text">Reject</span>
            </button>
        </div>
    </div>
</div>


<script>
'use strict';

const AJAX_URL = window.LMS_BASE + '/ajax/applications.ajax.php';
let currentPage = 1;
let currentDetailAppId = null;

// ── Load applications ─────────────────────────────────────────
async function loadApplications(page = 1) {
    currentPage = page;
    const search  = document.getElementById('app-search').value.trim();
    const status  = document.getElementById('filter-status').value;
    const batchId = document.getElementById('filter-batch').value;
    const perPage = document.getElementById('per-page').value;

    const res = await ajax(AJAX_URL, {
        action: 'list_applications',
        page, per_page: perPage, search, status, batch_id: batchId
    });

    if (res.status !== 'success') { Toast.error(res.message); return; }

    // Stats
    const s = res.data.stats;
    document.getElementById('stat-total').textContent          = s.total          || 0;
    document.getElementById('stat-pending').textContent        = s.pending         || 0;
    document.getElementById('stat-test-submitted').textContent = s.test_submitted  || 0;
    document.getElementById('stat-approved').textContent       = s.approved        || 0;
    document.getElementById('stat-rejected').textContent       = s.rejected        || 0;

    // Populate batch filter (first load only)
    const batchSel = document.getElementById('filter-batch');
    if (batchSel.options.length === 1 && res.data.batches?.length) {
        res.data.batches.forEach(b => {
            const opt = document.createElement('option');
            opt.value       = b.id;
            opt.textContent = b.course_title + ' — ' + b.name;
            batchSel.appendChild(opt);
        });
    }

    const apps  = res.data.applications || [];
    const tbody = document.getElementById('apps-tbody');

    if (!apps.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="inline-empty">'
            + '<div class="inline-empty-icon">📭</div>'
            + '<div class="inline-empty-title">No applications found</div>'
            + '<div class="inline-empty-sub">Try adjusting the filters above.</div>'
            + '</div></td></tr>';
        document.getElementById('apps-pagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = apps.map(a => buildRow(a)).join('');
    document.getElementById('apps-pagination').innerHTML = buildPagination(
        res.data.total, page, parseInt(perPage)
    );

    // Wire pagination clicks
    document.querySelectorAll('.page-btn').forEach(b => {
        b.addEventListener('click', ev => {
            ev.preventDefault();
            const pg = parseInt(new URL(b.href).searchParams.get('page'));
            if (pg) loadApplications(pg);
        });
    });

    BulkSelect.init('apps-table');
    if (window.lucide) lucide.createIcons();
}

function buildRow(a) {
    const statusLabel = {
        pending:        'Pending',
        test_submitted: 'Test Submitted',
        approved:       'Approved',
        rejected:       'Rejected',
    };

    const statusBadge = '<span class="badge badge-' + a.status + '">'
        + (statusLabel[a.status] || a.status) + '</span>';

    // Score display
    let scoreCell = '<span style="color:var(--text-muted);font-size:0.78rem;">No test yet</span>';
    if (a.percentage !== null && a.percentage !== undefined && a.percentage !== '') {
        const pct   = parseFloat(a.percentage);
        const col   = pct>=90?'#10b981':pct>=75?'#6366f1':pct>=60?'#06b6d4':pct>=40?'#f59e0b':'#ef4444';
        const band  = pct>=90?'Superb':pct>=75?'Excellent':pct>=60?'Good':pct>=40?'Average':'Weak';
        scoreCell = '<div class="score-bar-wrap">'
            + '<div class="score-bar"><div class="score-bar-fill" style="width:' + pct.toFixed(1) + '%;background:' + col + ';"></div></div>'
            + '<span style="font-size:0.8rem;font-weight:700;color:' + col + ';min-width:38px;">' + pct.toFixed(0) + '%</span>'
            + '</div>'
            + '<div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;">' + band + '</div>';
    }

    // Action buttons depend on status
    let actions = '';
    if (a.status === 'pending' || a.status === 'test_submitted') {
        actions = '<button class="btn btn-secondary btn-sm" style="color:#10b981;border-color:rgba(16,185,129,.4);" '
            + 'data-action="approve" data-id="' + a.id + '">✓ Approve</button>'
            + '<button class="btn btn-secondary btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,.4);" '
            + 'data-action="reject" data-id="' + a.id + '">✗ Reject</button>';
    } else if (a.status === 'approved') {
        actions = '<button class="btn btn-secondary btn-sm" style="color:#ef4444;border-color:rgba(239,68,68,.4);" '
            + 'data-action="reject" data-id="' + a.id + '">✗ Reject</button>';
    } else {
        actions = '<button class="btn btn-secondary btn-sm" style="color:#10b981;border-color:rgba(16,185,129,.4);" '
            + 'data-action="approve" data-id="' + a.id + '">✓ Re-Approve</button>';
    }

    const applied = a.applied_at ? new Date(a.applied_at).toLocaleDateString('en', {day:'numeric',month:'short',year:'numeric'}) : '—';

    return '<tr>'
        + '<td><input type="checkbox" class="row-cb" value="' + a.id + '" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>'
        + '<td>'
            + '<div style="font-weight:600;font-size:0.875rem;">' + e(a.full_name) + '</div>'
            + '<div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">ID: ' + e(a.user_id_number) + ' · ' + e(a.email) + '</div>'
        + '</td>'
        + '<td>'
            + '<div style="font-weight:600;font-size:0.84rem;">' + e(a.course_title) + '</div>'
            + '<div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">' + e(a.batch_name) + '</div>'
        + '</td>'
        + '<td style="font-size:0.82rem;color:var(--text-secondary);">' + applied + '</td>'
        + '<td style="min-width:130px;">' + scoreCell + '</td>'
        + '<td>' + statusBadge + '</td>'
        + '<td>'
            + '<div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">'
                + '<button class="action-btn action-btn-view" data-action="view" data-id="' + a.id + '" title="View Details">'
                    + '<i data-lucide="eye" style="width:14px;height:14px;"></i>'
                + '</button>'
                + actions
            + '</div>'
        + '</td>'
        + '</tr>';
}

// ── Event delegation — table clicks ──────────────────────────
document.getElementById('apps-tbody').addEventListener('click', async function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const id     = parseInt(btn.dataset.id);

    if (action === 'view')    openDetail(id);
    if (action === 'approve') quickApprove(id);
    if (action === 'reject')  openRejectModal([id]);
});

// ── View Detail Modal ─────────────────────────────────────────
async function openDetail(appId) {
    currentDetailAppId = appId;
    document.getElementById('app-detail-body').innerHTML = '<div class="chat-loading-row">Loading…</div>';
    document.getElementById('app-detail-footer').innerHTML = '';
    Modal.open('app-detail');

    const res = await ajax(AJAX_URL, { action: 'get_application', application_id: appId });
    if (res.status !== 'success') {
        document.getElementById('app-detail-body').innerHTML =
            '<div style="color:var(--danger);padding:20px;">' + e(res.message) + '</div>';
        return;
    }

    const a   = res.data;
    const pct = a.percentage !== null ? parseFloat(a.percentage) : null;
    const col = pct !== null ? (pct>=90?'#10b981':pct>=75?'#6366f1':pct>=60?'#06b6d4':pct>=40?'#f59e0b':'#ef4444') : 'var(--text-muted)';
    const band = pct !== null ? (pct>=90?'Superb':pct>=75?'Excellent':pct>=60?'Good':pct>=40?'Average':'Weak') : '—';

    let html = '';

    // Student info grid
    html += '<div class="app-detail-grid">'
        + detailField('Full Name',  a.full_name)
        + detailField('Student ID', a.user_id_number)
        + detailField('Email',      a.email)
        + detailField('CNIC',       a.cnic || '—')
        + detailField('Course',     a.course_title)
        + detailField('Batch',      a.batch_name)
        + detailField('Applied On', a.applied_at ? new Date(a.applied_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—')
        + detailField('Status', '<span class="badge badge-' + a.status + '">' + a.status.replace('_',' ') + '</span>', true)
        + '</div>';

    // Test result
    if (pct !== null) {
        html += '<div style="font-family:\'Poppins\',sans-serif;font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:10px;">Entry Test Result</div>';
        html += '<div class="result-strip">'
            + '<div class="result-strip-item"><div class="result-strip-val" style="color:' + col + ';">' + pct.toFixed(1) + '%</div><div class="result-strip-lbl">' + band + '</div></div>'
            + '<div class="result-strip-item"><div class="result-strip-val" style="color:#10b981;">' + (a.correct_count || 0) + '</div><div class="result-strip-lbl">Correct</div></div>'
            + '<div class="result-strip-item"><div class="result-strip-val" style="color:#ef4444;">' + (a.wrong_count || 0) + '</div><div class="result-strip-lbl">Wrong</div></div>'
            + '<div class="result-strip-item"><div class="result-strip-val" style="color:var(--text-muted);">' + (a.unanswered_count || 0) + '</div><div class="result-strip-lbl">Skipped</div></div>'
            + '</div>';

        // Score bar
        html += '<div class="score-bar" style="height:10px;margin-bottom:16px;">'
            + '<div class="score-bar-fill" style="width:' + pct.toFixed(1) + '%;background:' + col + ';"></div>'
            + '</div>';
    } else {
        html += '<div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius);padding:12px 16px;font-size:0.84rem;color:var(--text-secondary);margin-bottom:16px;">'
            + '⏳ Student has not attempted the entry test yet.'
            + '</div>';
    }

    // Rejection reason (if rejected)
    if (a.status === 'rejected' && a.rejection_reason) {
        html += '<div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;">'
            + '<div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ef4444;margin-bottom:6px;">Rejection Reason</div>'
            + '<div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;">' + e(a.rejection_reason) + '</div>'
            + (a.reviewer_name ? '<div style="font-size:0.72rem;color:var(--text-muted);margin-top:8px;">Reviewed by ' + e(a.reviewer_name) + ' on ' + new Date(a.reviewed_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric'}) + '</div>' : '')
            + '</div>';
    }

    if (a.status === 'approved' && a.reviewer_name) {
        html += '<div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;font-size:0.82rem;color:var(--text-secondary);">'
            + '✓ Approved by <strong>' + e(a.reviewer_name) + '</strong> on '
            + new Date(a.reviewed_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric'})
            + '</div>';
    }

    document.getElementById('app-detail-body').innerHTML = html;

    // Footer action buttons
    let footerHTML = '<button class="btn btn-secondary" onclick="Modal.close(\'app-detail\')">Close</button>';
    if (a.status !== 'approved') {
        footerHTML += '<button class="btn btn-primary" onclick="quickApprove(' + appId + ', true)" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(16,185,129,.3);">'
            + '<i data-lucide="check-circle" style="width:15px;height:15px;"></i> Approve'
            + '</button>';
    }
    if (a.status !== 'rejected') {
        footerHTML += '<button class="btn btn-danger" onclick="openRejectModal([' + appId + '], true)">'
            + '<i data-lucide="x-circle" style="width:15px;height:15px;"></i> Reject'
            + '</button>';
    }
    document.getElementById('app-detail-footer').innerHTML = footerHTML;
    if (window.lucide) lucide.createIcons();
}

function detailField(label, value, isHTML) {
    return '<div class="app-detail-field">'
        + '<div class="app-detail-label">' + label + '</div>'
        + '<div class="app-detail-value">' + (isHTML ? value : e(value || '—')) + '</div>'
        + '</div>';
}

// ── Quick Approve ─────────────────────────────────────────────
async function quickApprove(appId, fromDetail) {
    Modal.confirm({
        title: 'Approve Application?',
        message: 'The student will receive an approval email and will be able to log in to the LMS.',
        confirmText: 'Approve',
        confirmClass: 'btn-primary',
        icon: '✓',
        iconClass: 'modal-icon-primary',
        onConfirm: async () => {
            const res = await ajax(AJAX_URL, {
                action: 'approve_application',
                application_id: appId
            });
            if (res.status !== 'success') { Toast.error(res.message); return; }
            Toast.success('✓ Application approved — student notified by email!');
            if (fromDetail) Modal.close('app-detail');
            loadApplications(currentPage);
        }
    });
}

// ── Reject Modal ──────────────────────────────────────────────
let rejectIds     = [];
let rejectFromDetail = false;

function openRejectModal(ids, fromDetail) {
    rejectIds        = ids;
    rejectFromDetail = !!fromDetail;
    const isBulk = ids.length > 1;

    document.getElementById('reject-modal-title').textContent =
        isBulk ? 'Reject ' + ids.length + ' Applications' : 'Reject Application';
    document.getElementById('reject-modal-desc').textContent =
        isBulk
            ? ids.length + ' students will each receive a rejection email.'
            : 'The student will receive an email with this rejection notice.';
    document.getElementById('reject-app-ids').value = ids.join(',');
    document.getElementById('reject-reason').value  = '';
    document.getElementById('reject-confirm-text').textContent = isBulk ? 'Reject All' : 'Reject';

    Modal.open('reject-modal');
}

function closeRejectModal() {
    Modal.close('reject-modal');
    rejectIds = [];
}

async function submitRejection() {
    const reason = document.getElementById('reject-reason').value.trim();
    const btn    = document.getElementById('reject-confirm-btn');
    const txt    = document.getElementById('reject-confirm-text');

    btn.disabled = true;
    txt.textContent = 'Rejecting…';

    let res;
    if (rejectIds.length === 1) {
        res = await ajax(AJAX_URL, {
            action: 'reject_application',
            application_id: rejectIds[0],
            reason
        });
    } else {
        res = await ajax(AJAX_URL, {
            action: 'bulk_reject',
            ids:    rejectIds.join(','),
            reason
        });
    }

    btn.disabled = false;
    txt.textContent = rejectIds.length > 1 ? 'Reject All' : 'Reject';

    if (res.status !== 'success') { Toast.error(res.message); return; }
    Toast.success(res.message);
    closeRejectModal();
    if (rejectFromDetail) Modal.close('app-detail');
    loadApplications(currentPage);
}

// ── Bulk actions ──────────────────────────────────────────────
async function bulkApproveAction() {
    const ids = BulkSelect.getSelected('apps-table');
    if (!ids.length) return;

    Modal.confirm({
        title: 'Approve ' + ids.length + ' Application' + (ids.length > 1 ? 's' : '') + '?',
        message: 'Each student will receive an approval email. They will be able to log in to the LMS.',
        confirmText: 'Approve All',
        confirmClass: 'btn-primary',
        icon: '✓',
        iconClass: 'modal-icon-primary',
        onConfirm: async () => {
            const res = await ajax(AJAX_URL, { action: 'bulk_approve', ids: ids.join(',') });
            if (res.status !== 'success') { Toast.error(res.message); return; }
            Toast.success(res.message);
            BulkSelect.reset('apps-table');
            loadApplications(currentPage);
        }
    });
}

function bulkRejectAction() {
    const ids = BulkSelect.getSelected('apps-table');
    if (!ids.length) return;
    openRejectModal(ids.map(Number));
}

// ── Filters ───────────────────────────────────────────────────
const debouncedLoad = debounce(() => loadApplications(1), 350);
document.getElementById('app-search').addEventListener('input', debouncedLoad);
document.getElementById('filter-status').addEventListener('change', () => loadApplications(1));
document.getElementById('filter-batch').addEventListener('change',  () => loadApplications(1));
document.getElementById('per-page').addEventListener('change',      () => loadApplications(1));

// ── Pagination builder ────────────────────────────────────────
function buildPagination(total, page, perPage) {
    const tp = Math.max(1, Math.ceil(total / perPage));
    if (tp <= 1) return '';
    const s = (page - 1) * perPage + 1;
    const en = Math.min(page * perPage, total);
    const url = p => '?page=' + p;
    let h = '<div class="pagination-wrapper">'
        + '<span class="pagination-info">Showing ' + s + '–' + en + ' of ' + total + '</span>'
        + '<div class="pagination-controls">';
    h += '<a href="' + url(page - 1) + '" class="page-btn' + (page <= 1 ? ' disabled' : '') + '">‹</a>';
    for (let i = 1; i <= tp; i++) {
        if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) {
            h += '<a href="' + url(i) + '" class="page-btn' + (i === page ? ' active' : '') + '">' + i + '</a>';
        } else if (i === page - 3 || i === page + 3) {
            h += '<span class="page-ellipsis">…</span>';
        }
    }
    h += '<a href="' + url(page + 1) + '" class="page-btn' + (page >= tp ? ' disabled' : '') + '">›</a>';
    return h + '</div></div>';
}

// ── Safe HTML escape ─────────────────────────────────────────
function e(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Export Applications ───────────────────────────────────────
function exportApplications() {
    const status  = document.getElementById('filter-status').value;
    const batchId = document.getElementById('filter-batch').value;
    const search  = document.getElementById('app-search').value.trim();
    const params  = new URLSearchParams({ action: 'export_applications' });
    if (status)  params.set('status',   status);
    if (batchId) params.set('batch_id', batchId);
    if (search)  params.set('search',   search);
    window.location.href = window.LMS_BASE + '/ajax/applications.ajax.php?' + params.toString();
}

// ── Enroll Student Modal ──────────────────────────────────────
let enrollSelectedStudentId   = null;
let enrollSelectedStudentName = '';

async function openEnrollModal() {
    enrollSelectedStudentId   = null;
    enrollSelectedStudentName = '';
    document.getElementById('enroll-search').value = '';
    document.getElementById('enroll-batch').value  = '';
    document.getElementById('enroll-students-list').innerHTML = '<div class="chat-loading-row">Loading students…</div>';
    document.getElementById('enroll-selection-info').innerHTML = '';
    Modal.open('enroll-student-modal');
    await loadEnrollStudents();
}

async function loadEnrollStudents() {
    const search  = document.getElementById('enroll-search').value.trim();
    const batchId = document.getElementById('enroll-batch').value;

    const res = await ajax(AJAX_URL, {
        action:   'get_eligible_students',
        search:   search,
        batch_id: batchId,
    });
    if (res.status !== 'success') { Toast.error(res.message); return; }

    // Populate batch dropdown once
    const batchSel = document.getElementById('enroll-batch');
    if (batchSel.options.length <= 1 && res.data.batches?.length) {
        res.data.batches.forEach(b => {
            const o = document.createElement('option');
            o.value       = b.id;
            o.textContent = b.course_title + ' — ' + b.name;
            batchSel.appendChild(o);
        });
    }

    const students = res.data.students || [];
    const list     = document.getElementById('enroll-students-list');

    if (!students.length) {
        list.innerHTML = '<div style="text-align:center;padding:36px 20px;color:var(--text-muted);">'
            + '<i data-lucide="users" style="width:36px;height:36px;opacity:.25;display:block;margin:0 auto 10px;"></i>'
            + (batchId ? 'All registered students are already enrolled in this batch.' : 'No students found. Try a different search.')
            + '</div>';
        if (window.lucide) lucide.createIcons({ nodes: [list] });
        return;
    }

    list.innerHTML = students.map(s => {
        const initials = s.full_name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
        const colors   = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899'];
        const bg       = colors[Math.abs(s.full_name.split('').reduce((a,c) => a + c.charCodeAt(0), 0)) % colors.length];

        const tags = [];
        if (s.bypass_gate == 1) tags.push('<span style="font-size:0.62rem;background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.2);padding:1px 6px;border-radius:4px;font-weight:700;">Bypass ON</span>');
        if (s.user_status !== 'active') tags.push('<span style="font-size:0.62rem;background:rgba(245,158,11,.1);color:#d97706;border:1px solid rgba(245,158,11,.2);padding:1px 6px;border-radius:4px;font-weight:700;">Inactive</span>');

        return '<div class="enroll-student-card" id="esc-' + s.id + '" onclick="selectEnrollStudent(' + s.id + ',\'' + e(s.full_name) + '\')">'
            + '<div class="enroll-avatar" style="background:' + bg + ';">' + initials + '</div>'
            + '<div class="enroll-student-info">'
                + '<div class="enroll-student-name">' + e(s.full_name) + (tags.length ? ' ' + tags.join(' ') : '') + '</div>'
                + '<div class="enroll-student-meta">'
                    + 'ID: ' + e(s.user_id_number || '—') + ' &nbsp;·&nbsp; ' + e(s.email)
                    + (s.cnic ? ' &nbsp;·&nbsp; ' + e(s.cnic) : '')
                    + (s.gender ? ' &nbsp;·&nbsp; ' + s.gender.charAt(0).toUpperCase() + s.gender.slice(1) : '')
                + '</div>'
            + '</div>'
            + '<i data-lucide="circle" class="enroll-check" id="echeck-' + s.id + '" style="width:18px;height:18px;color:var(--border);flex-shrink:0;"></i>'
            + '</div>';
    }).join('');

    if (window.lucide) lucide.createIcons({ nodes: [list] });
}

function selectEnrollStudent(id, name) {
    // Deselect previous
    if (enrollSelectedStudentId) {
        const prev = document.getElementById('esc-' + enrollSelectedStudentId);
        if (prev) prev.classList.remove('selected');
        const prevCheck = document.getElementById('echeck-' + enrollSelectedStudentId);
        if (prevCheck) { prevCheck.setAttribute('data-lucide','circle'); prevCheck.style.color = 'var(--border)'; }
    }

    enrollSelectedStudentId   = id;
    enrollSelectedStudentName = name;

    const card = document.getElementById('esc-' + id);
    if (card) card.classList.add('selected');

    const check = document.getElementById('echeck-' + id);
    if (check) {
        check.setAttribute('data-lucide', 'check-circle');
        check.style.color = 'var(--primary)';
    }

    if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('enroll-students-list')] });

    document.getElementById('enroll-selection-info').innerHTML =
        '<div style="display:flex;align-items:center;gap:7px;font-size:0.82rem;color:var(--primary);font-weight:600;margin-top:10px;">'
        + '<i data-lucide="check-circle" style="width:15px;height:15px;"></i>'
        + e(name) + ' selected'
        + '</div>';
    if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('enroll-selection-info')] });
}

async function submitEnrollStudent() {
    const batchId = document.getElementById('enroll-batch').value;
    if (!enrollSelectedStudentId) { Toast.error('Please select a student from the list.'); return; }
    if (!batchId)                 { Toast.error('Please select a batch.'); return; }

    const btn = document.getElementById('enroll-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<div style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;margin-right:6px;"></div> Enrolling…';

    const res = await ajax(AJAX_URL, {
        action:     'admin_enroll_student',
        student_id: enrollSelectedStudentId,
        batch_id:   batchId,
    });

    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="user-check" style="width:15px;height:15px;"></i> Enroll Student';
    if (window.lucide) lucide.createIcons({ nodes: [btn] });

    if (res.status !== 'success') { Toast.error(res.message); return; }
    Toast.success(res.message);
    Modal.close('enroll-student-modal');
    loadApplications(currentPage);
}

const debouncedEnrollSearch = debounce(() => loadEnrollStudents(), 350);

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => loadApplications(1));
</script>

<!-- ═══════════════════════════════════════════════════════════
     ENROLL STUDENT MODAL
═══════════════════════════════════════════════════════════ -->
<style>
/* ── Student card ───────────────────────────────────────────── */
.enroll-student-card {
    display: flex; align-items: center; gap: 13px;
    padding: 12px 14px; border-radius: var(--radius);
    border: 1.5px solid var(--border); background: var(--bg-card);
    cursor: pointer; transition: all 0.15s; margin-bottom: 8px;
    user-select: none;
}
.enroll-student-card:hover {
    border-color: rgba(99,102,241,.4);
    background: rgba(99,102,241,.03);
    box-shadow: 0 2px 10px rgba(99,102,241,.07);
}
.enroll-student-card.selected {
    border-color: var(--primary);
    background: rgba(99,102,241,.06);
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.enroll-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.84rem; color: #fff;
    font-family: 'Poppins', sans-serif; flex-shrink: 0;
}
.enroll-student-info { flex: 1; min-width: 0; }
.enroll-student-name {
    font-weight: 700; font-size: 0.875rem; color: var(--text);
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.enroll-student-meta {
    font-size: 0.72rem; color: var(--text-muted); margin-top: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Batch select highlight ─────────────────────────────────── */
#enroll-batch:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }

/* ── Spin animation for loading button ──────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="modal-overlay" id="enroll-student-modal-overlay">
    <div class="modal modal-lg" style="max-height:92vh;">
        <div class="modal-header">
            <div class="modal-icon modal-icon-primary">
                <i data-lucide="user-plus" style="width:20px;height:20px;"></i>
            </div>
            <h3 class="modal-title">Enroll Student in Batch</h3>
            <button class="modal-close" onclick="Modal.close('enroll-student-modal')">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>

        <div class="modal-body" style="overflow-y:auto;">

            <!-- Info notice -->
            <div style="display:flex;align-items:flex-start;gap:12px;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.2);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;">
                <i data-lucide="info" style="width:17px;height:17px;color:var(--primary);flex-shrink:0;margin-top:1px;"></i>
                <div style="font-size:0.82rem;color:var(--text-muted);line-height:1.65;">
                    This creates an <strong style="color:var(--text);">approved application</strong> directly — no entry test, no email gate.
                    The student is enrolled immediately, their account is activated, and they receive an approval notification email.
                    Only students without an existing application for the chosen batch are shown.
                </div>
            </div>

            <!-- Step 1: Select Batch -->
            <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:0.82rem;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:7px;">
                <span style="width:22px;height:22px;background:var(--primary);color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:0.7rem;flex-shrink:0;">1</span>
                Select Batch
            </div>
            <select id="enroll-batch" class="form-control" style="margin-bottom:20px;" onchange="loadEnrollStudents()">
                <option value="">— All batches (show all unenrolled students) —</option>
            </select>

            <!-- Step 2: Search + Pick Student -->
            <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:0.82rem;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:7px;">
                <span style="width:22px;height:22px;background:var(--primary);color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:0.7rem;flex-shrink:0;">2</span>
                Select Student
            </div>

            <div class="search-box" style="margin-bottom:12px;">
                <i data-lucide="search" class="search-box-icon"></i>
                <input type="text" id="enroll-search" class="form-control"
                    placeholder="Search by name, student ID, email or CNIC…"
                    oninput="debouncedEnrollSearch()">
            </div>

            <div id="enroll-students-list" style="max-height:340px;overflow-y:auto;padding-right:2px;">
                <div class="chat-loading-row">Loading…</div>
            </div>

            <div id="enroll-selection-info"></div>

        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="Modal.close('enroll-student-modal')">Cancel</button>
            <button class="btn btn-primary" id="enroll-submit-btn" onclick="submitEnrollStudent()">
                <i data-lucide="user-check" style="width:15px;height:15px;"></i>
                Enroll Student
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>