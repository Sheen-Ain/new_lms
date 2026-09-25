<?php
// ============================================================
// ADMIN — FEEDBACK MODULE v2
// ============================================================
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Feedback';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => BASE_PATH . '/admin/'],
    ['label' => 'Feedback'],
];
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Batch card ─────────────────────────────────────────────── */
.batch-card {
    background: var(--bg-card); border: 1.5px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: box-shadow 0.15s; margin-bottom: 12px;
}
.batch-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.07); }
.batch-card-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 15px 20px; cursor: pointer;
    transition: background 0.12s; user-select: none;
}
.batch-card-header:hover { background: var(--bg); }
.batch-card-title { font-family:'Poppins',sans-serif; font-weight:700; font-size:0.92rem; color:var(--text); }
.batch-card-sub   { font-size:0.74rem; color:var(--text-muted); margin-top:2px; }
.batch-card-body  { padding:16px 20px; display:none; }
.batch-card-body.open { display:block; }

/* ── Session row ────────────────────────────────────────────── */
.session-row {
    display:flex; align-items:flex-start; gap:12px;
    padding:13px 16px; background:var(--bg);
    border:1px solid var(--border); border-radius:var(--radius);
    margin-bottom:8px; flex-wrap:wrap;
}
.session-info { flex:1; min-width:0; }
.session-title { font-weight:700; font-size:0.88rem; color:var(--text); }
.session-meta  { font-size:0.72rem; color:var(--text-muted); margin-top:3px; display:flex; gap:10px; flex-wrap:wrap; }
.session-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }

/* ── Live / closed badges ───────────────────────────────────── */
.live-badge {
    display:inline-flex; align-items:center; gap:5px; padding:2px 8px;
    border-radius:99px; font-size:0.66rem; font-weight:800;
    background:rgba(239,68,68,0.1); color:#dc2626; border:1px solid rgba(239,68,68,0.25);
}
.live-dot { width:5px;height:5px;border-radius:50%;background:#dc2626;animation:livePulse 1.2s ease infinite; }
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(1.6);}}
.closed-badge {
    display:inline-flex; align-items:center; padding:2px 8px;
    border-radius:99px; font-size:0.66rem; font-weight:700;
    background:var(--bg); color:var(--text-muted); border:1px solid var(--border);
}

/* ── Unread dot ─────────────────────────────────────────────── */
.unread-dot {
    display:inline-flex; align-items:center; gap:4px; padding:2px 7px;
    border-radius:99px; font-size:0.64rem; font-weight:700;
    background:rgba(245,158,11,0.1); color:#d97706; border:1px solid rgba(245,158,11,0.25);
}

/* ── Create form ────────────────────────────────────────────── */
.create-form { background:var(--bg); border:1.5px dashed var(--border); border-radius:var(--radius); padding:16px; margin-bottom:12px; display:none; }
.create-form.open { display:block; }

/* ── Rate bar ───────────────────────────────────────────────── */
.rate-bar { height:7px; background:var(--border); border-radius:99px; overflow:hidden; }
.rate-bar-fill { height:100%; border-radius:99px; background:var(--primary); transition:width .8s; }

/* ── Modal stats ────────────────────────────────────────────── */
.modal-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:18px; }
.modal-stat  { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:12px; text-align:center; }
.modal-stat-val { font-family:'Poppins',sans-serif; font-weight:800; font-size:1.25rem; color:var(--primary); }
.modal-stat-lbl { font-size:0.64rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-top:2px; }

/* ── Category breakdown ─────────────────────────────────────── */
.cat-breakdown { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.cat-pill {
    display:inline-flex; align-items:center; gap:5px; padding:4px 10px;
    border-radius:99px; font-size:0.76rem; font-weight:600; cursor:pointer;
    transition:all .15s; border:1.5px solid transparent;
}
.cat-pill:hover { opacity:.85; }
.cat-pill.selected { box-shadow:0 0 0 3px rgba(99,102,241,0.2); }

/* ── Entry card ─────────────────────────────────────────────── */
.entry-card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--radius); padding:14px 16px; margin-bottom:10px;
    transition:border-color .15s;
}
.entry-card.is-reviewed { opacity:0.75; }
.entry-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.entry-cats { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:8px; }
.entry-cat-tag {
    display:inline-flex; align-items:center; gap:4px; padding:2px 8px;
    border-radius:6px; font-size:0.68rem; font-weight:700;
    border:1px solid currentColor; opacity:0.85;
}
.entry-content { font-size:0.88rem; color:var(--text-secondary); line-height:1.78; white-space:pre-wrap; word-break:break-word; }
.entry-footer  { display:flex; align-items:center; gap:10px; margin-top:10px; padding-top:10px; border-top:1px solid var(--border); flex-wrap:wrap; }

/* ── Toolbar ─────────────────────────────────────────────────── */
.entries-toolbar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:12px 16px; background:var(--bg); border:1px solid var(--border);
    border-radius:var(--radius); margin-bottom:14px;
}
.entries-toolbar .search-box { flex:1; min-width:160px; }

/* ── Anon shield ────────────────────────────────────────────── */
.anon-shield {
    display:inline-flex; align-items:center; gap:5px; padding:3px 8px;
    border-radius:6px; background:rgba(16,185,129,.08); color:#059669;
    border:1px solid rgba(16,185,129,.2); font-size:0.65rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.06em;
}
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Feedback</h1>
                    <p class="page-subtitle">Manage anonymous batch feedback sessions</p>
                </div>
            </div>

            <!-- Anonymity notice -->
            <div style="display:flex;align-items:flex-start;gap:14px;background:rgba(16,185,129,.06);border:1.5px solid rgba(16,185,129,.2);border-radius:var(--radius-lg);padding:14px 20px;margin-bottom:24px;">
                <div style="font-size:1.5rem;flex-shrink:0;">🛡️</div>
                <div style="font-size:0.82rem;color:var(--text-muted);line-height:1.65;">
                    <strong style="color:var(--text);">100% Anonymous by Design.</strong>
                    Entries contain no student ID, name, IP, or any identifying data.
                    A one-way hash only prevents duplicate submissions — it cannot reveal who wrote what.
                    Even with full database access, identity cannot be determined.
                </div>
            </div>

            <!-- Batch cards -->
            <div id="batches-list"><div class="chat-loading-row">Loading…</div></div>

        </main>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     VIEW FEEDBACK MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="view-feedback-overlay">
    <div class="modal modal-xl" style="max-height:92vh;">
        <div class="modal-header">
            <div class="modal-icon" style="background:rgba(16,185,129,.15);">
                <i data-lucide="message-square" style="width:20px;height:20px;color:#059669;"></i>
            </div>
            <h3 class="modal-title" id="fb-modal-title">Anonymous Feedback</h3>
            <button class="modal-close" onclick="Modal.close('view-feedback')">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="modal-body" id="fb-modal-body" style="overflow-y:auto;"></div>
        <div class="modal-footer" id="fb-modal-footer">
            <button class="btn btn-secondary" onclick="Modal.close('view-feedback')">Close</button>
        </div>
    </div>
</div>


<script>
'use strict';

const AJAX = window.LMS_BASE + '/ajax/feedback.ajax.php';
let ALL_CATS = {};
let currentSessionId = null;
let currentFilter = { search: '', category: '', status: '' };

// ── Load batches ──────────────────────────────────────────────
async function loadBatches() {
    const res = await ajax(AJAX, { action: 'admin_batches' });
    if (res.status !== 'success') { Toast.error(res.message); return; }
    ALL_CATS = res.data.categories || {};

    const batches   = res.data.batches || [];
    const container = document.getElementById('batches-list');

    if (!batches.length) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">No batches found</div></div>';
        return;
    }
    container.innerHTML = batches.map(b => buildBatchCard(b)).join('');
    if (window.lucide) lucide.createIcons();
}

function buildBatchCard(b) {
    const unreadBadge = b.unread_entries > 0
        ? '<span class="unread-dot">● ' + b.unread_entries + ' unread</span>' : '';
    const activeBadge = b.active_sessions > 0
        ? '<span class="live-badge"><span class="live-dot"></span> LIVE</span>' : '';
    const sub = [
        b.session_count + ' session' + (b.session_count != 1 ? 's' : ''),
        b.total_entries > 0 ? b.total_entries + ' responses' : '',
    ].filter(Boolean).join(' · ') || 'No sessions yet';

    return '<div class="batch-card" id="bc-' + b.id + '">'
        + '<div class="batch-card-header" onclick="toggleBatch(' + b.id + ')">'
            + '<div>'
                + '<div class="batch-card-title">' + esc(b.course_title) + ' — ' + esc(b.name) + ' &nbsp;' + activeBadge + ' ' + unreadBadge + '</div>'
                + '<div class="batch-card-sub">' + esc(sub) + '</div>'
            + '</div>'
            + '<div style="display:flex;align-items:center;gap:8px;">'
                + '<button class="btn btn-primary btn-sm" onclick="event.stopPropagation();openCreateForm(' + b.id + ')" style="gap:5px;">'
                    + '<i data-lucide="plus" style="width:13px;height:13px;"></i> New Session'
                + '</button>'
                + '<i data-lucide="chevron-down" style="width:16px;height:16px;color:var(--text-muted);transition:transform .25s;" id="chev-' + b.id + '"></i>'
            + '</div>'
        + '</div>'
        + '<div class="batch-card-body" id="bb-' + b.id + '">'
            + buildCreateForm(b.id)
            + '<div id="sessions-' + b.id + '"><div class="chat-loading-row" style="padding:10px;">Loading…</div></div>'
        + '</div>'
        + '</div>';
}

function buildCreateForm(batchId) {
    return '<div class="create-form" id="cf-' + batchId + '">'
        + '<div style="font-weight:700;font-size:0.88rem;color:var(--text);margin-bottom:12px;">Create Feedback Session</div>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
            + '<div class="form-group" style="grid-column:1/-1;">'
                + '<label class="form-label">Session Title</label>'
                + '<input type="text" id="ct-' + batchId + '" class="form-control" value="End of Batch Feedback">'
            + '</div>'
            + '<div class="form-group" style="grid-column:1/-1;">'
                + '<label class="form-label">Description <span style="font-weight:400;color:var(--text-muted);">(optional — shown to students)</span></label>'
                + '<textarea id="cd-' + batchId + '" class="form-control" rows="2" placeholder="e.g. Please share your honest feedback about the batch…"></textarea>'
            + '</div>'
        + '</div>'
        + '<div style="display:flex;gap:8px;margin-top:4px;">'
            + '<button class="btn btn-primary btn-sm" onclick="createSession(' + batchId + ',false)"><i data-lucide="save" style="width:13px;height:13px;"></i> Save Inactive</button>'
            + '<button class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 3px 10px rgba(16,185,129,.3);" onclick="createSession(' + batchId + ',true)"><i data-lucide="radio" style="width:13px;height:13px;"></i> Save & Activate</button>'
            + '<button class="btn btn-secondary btn-sm" onclick="openCreateForm(' + batchId + ')">Cancel</button>'
        + '</div>'
        + '</div>';
}

function toggleBatch(batchId) {
    const body   = document.getElementById('bb-' + batchId);
    const chev   = document.getElementById('chev-' + batchId);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open');
    if (chev) chev.style.transform = isOpen ? '' : 'rotate(180deg)';
    if (!isOpen) loadSessions(batchId);
}

// ── Sessions ──────────────────────────────────────────────────
async function loadSessions(batchId) {
    const el = document.getElementById('sessions-' + batchId);
    const res = await ajax(AJAX, { action: 'list_sessions', batch_id: batchId });
    if (res.status !== 'success') { el.innerHTML = '<div style="color:var(--danger);padding:10px;">' + esc(res.message) + '</div>'; return; }

    const sessions = res.data.sessions || [];
    if (!sessions.length) {
        el.innerHTML = '<div style="font-size:0.84rem;color:var(--text-muted);padding:12px;text-align:center;">No feedback sessions yet. Click "New Session" to create one.</div>';
        return;
    }
    el.innerHTML = sessions.map(s => buildSessionRow(s)).join('');
    if (window.lucide) lucide.createIcons();
}

function buildSessionRow(s) {
    const badge = s.is_active == 1
        ? '<span class="live-badge"><span class="live-dot"></span> LIVE</span>'
        : '<span class="closed-badge">Closed</span>';
    const unread = s.unread_count > 0
        ? '<span class="unread-dot">● ' + s.unread_count + ' unread</span>' : '';
    const rate = s.enrolled_count > 0 ? Math.round(s.submitter_count / s.enrolled_count * 100) : 0;
    const activated = s.activated_at ? new Date(s.activated_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric'}) : 'Not activated';

    return '<div class="session-row" id="sr-' + s.id + '">'
        + '<div class="session-info">'
            + '<div class="session-title">' + esc(s.title) + ' &nbsp;' + badge + ' ' + unread + '</div>'
            + '<div class="session-meta">'
                + '<span>📝 ' + s.entry_count + ' responses</span>'
                + '<span>👥 ' + s.submitter_count + '/' + s.enrolled_count + ' students</span>'
                + '<span>📅 ' + esc(activated) + '</span>'
                + (s.entry_count > 0 ? '<span>📊 ' + rate + '% response rate</span>' : '')
            + '</div>'
        + '</div>'
        + '<div class="session-actions">'
            + (s.entry_count > 0
                ? '<button class="btn btn-secondary btn-sm" style="gap:5px;" onclick="openFeedbackModal(' + s.id + ',\'' + esc(s.title) + '\',' + s.batch_id + ')"><i data-lucide="eye" style="width:13px;height:13px;"></i> Read</button>'
                + '<a class="btn btn-secondary btn-sm" style="gap:5px;" href="' + window.LMS_BASE + '/ajax/feedback.ajax.php?action=export_entries&session_id=' + s.id + '"><i data-lucide="download" style="width:13px;height:13px;"></i> Export</a>'
                : '')
            + (s.is_active == 1
                ? '<button class="btn btn-secondary btn-sm" style="color:#f59e0b;border-color:rgba(245,158,11,.4);" onclick="toggleSession(' + s.id + ',0,' + s.batch_id + ')"><i data-lucide="pause-circle" style="width:13px;height:13px;"></i></button>'
                : '<button class="btn btn-secondary btn-sm" style="color:#10b981;border-color:rgba(16,185,129,.4);" onclick="toggleSession(' + s.id + ',1,' + s.batch_id + ')"><i data-lucide="radio" style="width:13px;height:13px;"></i></button>'
            )
            + '<button class="action-btn action-btn-delete" onclick="deleteSession(' + s.id + ',' + s.batch_id + ')" title="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>'
        + '</div>'
        + '</div>';
}

// ── Create session ────────────────────────────────────────────
function openCreateForm(batchId) {
    document.getElementById('cf-' + batchId)?.classList.toggle('open');
}

async function createSession(batchId, activateNow) {
    const title = document.getElementById('ct-' + batchId)?.value?.trim();
    const desc  = document.getElementById('cd-' + batchId)?.value?.trim();
    if (!title) { Toast.error('Session title is required'); return; }

    const res = await ajax(AJAX, { action:'create_session', batch_id:batchId, title, description:desc||'', activate: activateNow ? 1 : 0 });
    if (res.status !== 'success') { Toast.error(res.message); return; }
    Toast.success(res.message);
    openCreateForm(batchId);
    loadSessions(batchId);
    loadBatches();
}

// ── Toggle ────────────────────────────────────────────────────
async function toggleSession(sessionId, activate, batchId) {
    Modal.confirm({
        title: activate ? 'Activate Feedback?' : 'Deactivate Feedback?',
        message: activate
            ? 'Students will immediately be able to submit anonymous feedback. Only one session can be active per batch at a time.'
            : 'Students will no longer be able to submit for this session.',
        confirmText: activate ? '🟢 Activate' : '⏸ Deactivate',
        confirmClass: 'btn-primary',
        icon: activate ? '🔓' : '🔒', iconClass: 'modal-icon-primary',
        onConfirm: async () => {
            const res = await ajax(AJAX, { action:'toggle_session', session_id:sessionId, activate });
            if (res.status !== 'success') { Toast.error(res.message); return; }
            Toast.success(res.message);
            loadSessions(batchId); loadBatches();
        }
    });
}

// ── Delete session ────────────────────────────────────────────
function deleteSession(sessionId, batchId) {
    Modal.confirm({
        title: 'Delete Session?',
        message: 'This will permanently delete this session and <strong>all anonymous feedback entries</strong>. Cannot be undone.',
        confirmText: 'Delete', confirmClass: 'btn-danger',
        icon: '🗑️', iconClass: 'modal-icon-danger',
        onConfirm: async () => {
            const res = await ajax(AJAX, { action:'delete_session', session_id:sessionId });
            if (res.status !== 'success') { Toast.error(res.message); return; }
            Toast.success(res.message);
            loadSessions(batchId); loadBatches();
        }
    });
}

// ── View Feedback Modal ───────────────────────────────────────
async function openFeedbackModal(sessionId, title, batchId) {
    currentSessionId = sessionId;
    currentFilter = { search:'', category:'', status:'' };
    document.getElementById('fb-modal-title').textContent = title + ' — Feedback';
    document.getElementById('fb-modal-body').innerHTML = '<div class="chat-loading-row">Loading…</div>';
    document.getElementById('fb-modal-footer').innerHTML =
        '<button class="btn btn-secondary" onclick="Modal.close(\'view-feedback\')">Close</button>'
        + '<button class="btn btn-secondary" onclick="markAllReviewed(' + sessionId + ')"><i data-lucide="check-square" style="width:15px;height:15px;"></i> Mark All Reviewed</button>'
        + '<a class="btn btn-primary" href="' + window.LMS_BASE + '/ajax/feedback.ajax.php?action=export_entries&session_id=' + sessionId + '"><i data-lucide="download" style="width:15px;height:15px;"></i> Export Excel</a>';
    Modal.open('view-feedback');
    if (window.lucide) lucide.createIcons();
    await loadModalContent(sessionId);
}

async function loadModalContent(sessionId) {
    const [statsRes, entriesRes] = await Promise.all([
        ajax(AJAX, { action:'get_stats',    session_id:sessionId }),
        ajax(AJAX, { action:'list_entries', session_id:sessionId, ...currentFilter }),
    ]);

    let html = '';

    // Stats
    if (statsRes.status === 'success') {
        const s = statsRes.data;
        html += '<div class="modal-stats">'
            + modalStat(s.entry_count, 'Total')
            + modalStat(s.unread, 'Unread', s.unread > 0 ? '#d97706' : undefined)
            + modalStat(s.submitted + '/' + s.enrolled, 'Submitted/Enrolled')
            + modalStat(s.response_rate + '%', 'Response Rate')
            + '</div>';

        html += '<div class="rate-bar" style="margin-bottom:4px;"><div class="rate-bar-fill" style="width:' + s.response_rate + '%;"></div></div>';
        html += '<div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:16px;">' + s.submitted + ' of ' + s.enrolled + ' enrolled students responded</div>';

        // Category breakdown pills (clickable filter)
        html += '<div class="cat-breakdown">';
        html += '<button class="cat-pill" style="background:var(--bg);border-color:var(--border);color:var(--text-muted);" onclick="setCatFilter(\'\')" id="cat-pill-all">All categories</button>';
        Object.entries(ALL_CATS).forEach(([key, cat]) => {
            const count = s.categories[key] || 0;
            if (count === 0) return;
            html += '<button class="cat-pill" style="background:' + cat.color + '22;color:' + cat.color + ';border-color:' + cat.color + '44;" '
                + 'onclick="setCatFilter(\'' + key + '\')" id="cat-pill-' + key + '">'
                + cat.emoji + ' ' + cat.label + ' <strong>(' + count + ')</strong>'
                + '</button>';
        });
        html += '</div>';
    }

    // Toolbar
    html += '<div class="entries-toolbar">'
        + '<div class="search-box"><i data-lucide="search" class="search-box-icon"></i>'
            + '<input type="text" class="form-control" placeholder="Search entries…" id="entry-search" oninput="debouncedSearch()" value="' + esc(currentFilter.search) + '">'
        + '</div>'
        + '<select class="form-control" style="width:140px;" id="status-filter" onchange="setStatusFilter(this.value)">'
            + '<option value="">All entries</option>'
            + '<option value="unread"' + (currentFilter.status==='unread'?' selected':'') + '>Unread</option>'
            + '<option value="reviewed"' + (currentFilter.status==='reviewed'?' selected':'') + '>Reviewed</option>'
        + '</select>'
        + '<span class="anon-shield">🛡️ Anonymous</span>'
        + '</div>';

    // Entries
    if (entriesRes.status === 'success') {
        const entries = entriesRes.data.entries || [];
        html += '<div id="entries-list">';
        if (!entries.length) {
            html += '<div style="text-align:center;padding:32px;color:var(--text-muted);">No entries match your filters.</div>';
        } else {
            html += entries.map(e => buildEntryCard(e)).join('');
        }
        html += '</div>';
    }

    document.getElementById('fb-modal-body').innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

function modalStat(val, lbl, color) {
    return '<div class="modal-stat"><div class="modal-stat-val"' + (color ? ' style="color:' + color + ';"' : '') + '>' + val + '</div><div class="modal-stat-lbl">' + lbl + '</div></div>';
}

function buildEntryCard(entry) {
    const cats = entry.categories || ['general'];
    const catTags = cats.map(c => {
        const cat = ALL_CATS[c] || { label: c, emoji: '📋', color: '#6b7280' };
        return '<span class="entry-cat-tag" style="color:' + cat.color + ';border-color:' + cat.color + '44;background:' + cat.color + '11;">' + cat.emoji + ' ' + cat.label + '</span>';
    }).join('');
    const time = new Date(entry.submitted_at).toLocaleDateString('en', {day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const reviewedClass = entry.is_reviewed == 1 ? ' is-reviewed' : '';
    const reviewLabel   = entry.is_reviewed == 1 ? '<span style="color:#059669;font-size:0.7rem;font-weight:700;">✓ Reviewed</span>' : '<span style="color:#d97706;font-size:0.7rem;font-weight:700;">● Unread</span>';
    const reviewToggleText = entry.is_reviewed == 1 ? 'Mark Unread' : 'Mark Reviewed';
    const reviewToggleVal  = entry.is_reviewed == 1 ? 0 : 1;

    return '<div class="entry-card' + reviewedClass + '" id="ec-' + entry.id + '">'
        + '<div class="entry-cats">' + catTags + '</div>'
        + '<div class="entry-content">' + esc(entry.content) + '</div>'
        + '<div class="entry-footer">'
            + '<span style="font-size:0.7rem;color:var(--text-muted);">🕵️ Anonymous &nbsp;·&nbsp; ' + esc(time) + '</span>'
            + reviewLabel
            + '<div style="margin-left:auto;display:flex;gap:6px;">'
                + '<button class="btn btn-secondary btn-sm" style="font-size:0.72rem;padding:3px 8px;" onclick="toggleReviewed(' + entry.id + ',' + reviewToggleVal + ')">' + reviewToggleText + '</button>'
                + '<button class="action-btn action-btn-delete" onclick="deleteEntryFromModal(' + entry.id + ')" title="Remove entry"><i data-lucide="trash-2" style="width:12px;height:12px;"></i></button>'
            + '</div>'
        + '</div>'
        + '</div>';
}

// ── Filter helpers ────────────────────────────────────────────
function setCatFilter(cat) {
    currentFilter.category = cat;
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('selected'));
    const activePill = document.getElementById(cat ? 'cat-pill-' + cat : 'cat-pill-all');
    if (activePill) activePill.classList.add('selected');
    reloadEntries();
}

function setStatusFilter(val) {
    currentFilter.status = val;
    reloadEntries();
}

const debouncedSearch = debounce(() => {
    currentFilter.search = document.getElementById('entry-search')?.value?.trim() || '';
    reloadEntries();
}, 350);

async function reloadEntries() {
    const list = document.getElementById('entries-list');
    if (!list) return;
    list.innerHTML = '<div class="chat-loading-row">Loading…</div>';

    const res = await ajax(AJAX, {
        action: 'list_entries', session_id: currentSessionId,
        search: currentFilter.search,
        filter_category: currentFilter.category,
        filter_status: currentFilter.status,
    });
    if (res.status !== 'success') { list.innerHTML = '<div style="color:var(--danger);padding:12px;">' + esc(res.message) + '</div>'; return; }

    const entries = res.data.entries || [];
    list.innerHTML = entries.length
        ? entries.map(e => buildEntryCard(e)).join('')
        : '<div style="text-align:center;padding:32px;color:var(--text-muted);">No entries match your filters.</div>';
    if (window.lucide) lucide.createIcons();
}

// ── Mark reviewed ─────────────────────────────────────────────
async function toggleReviewed(entryId, reviewedVal) {
    const res = await ajax(AJAX, { action:'mark_reviewed', entry_id:entryId, reviewed:reviewedVal });
    if (res.status !== 'success') { Toast.error(res.message); return; }
    reloadEntries();
}

async function markAllReviewed(sessionId) {
    const res = await ajax(AJAX, { action:'mark_reviewed', session_id:sessionId, mark_all:1 });
    if (res.status !== 'success') { Toast.error(res.message); return; }
    Toast.success('All entries marked as reviewed');
    reloadEntries();
    loadBatches();
}

// ── Delete entry ──────────────────────────────────────────────
function deleteEntryFromModal(entryId) {
    Modal.confirm({
        title: 'Remove Entry?', message: 'This feedback entry will be permanently removed. Use only for policy violations.',
        confirmText: 'Remove', confirmClass: 'btn-danger', icon: '🗑️', iconClass: 'modal-icon-danger',
        onConfirm: async () => {
            const res = await ajax(AJAX, { action:'delete_entry', entry_id:entryId });
            if (res.status !== 'success') { Toast.error(res.message); return; }
            Toast.success('Entry removed');
            const el = document.getElementById('ec-' + entryId);
            if (el) { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
        }
    });
}

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.addEventListener('DOMContentLoaded', loadBatches);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>