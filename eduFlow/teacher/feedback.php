<?php
// ============================================================
// TEACHER — FEEDBACK v2 (Read-only + search/filter)
// ============================================================
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Student Feedback';
$breadcrumbs = [
    ['label' => 'Teacher', 'url' => BASE_PATH . '/teacher/'],
    ['label' => 'Feedback'],
];
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Session card ───────────────────────────────────────────── */
.fs-card { background:var(--bg-card); border:1.5px solid var(--border); border-radius:var(--radius-lg); margin-bottom:14px; overflow:hidden; transition:box-shadow .15s; }
.fs-card:hover { box-shadow:0 4px 18px rgba(0,0,0,0.07); }
.fs-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:15px 20px; cursor:pointer; transition:background .12s; }
.fs-header:hover { background:var(--bg); }
.fs-header.open  { border-bottom:1px solid var(--border); }
.fs-title { font-family:'Poppins',sans-serif; font-weight:700; font-size:0.9rem; color:var(--text); }
.fs-sub   { font-size:0.74rem; color:var(--text-muted); margin-top:3px; }
.fs-body  { display:none; padding:18px 20px; }
.fs-body.open { display:block; }

/* ── Badges ─────────────────────────────────────────────────── */
.live-badge { display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:99px;font-size:0.66rem;font-weight:800;background:rgba(239,68,68,0.1);color:#dc2626;border:1px solid rgba(239,68,68,0.25); }
.live-dot   { width:5px;height:5px;border-radius:50%;background:#dc2626;animation:livePulse 1.2s ease infinite; }
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(1.6);}}
.closed-badge { display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:0.66rem;font-weight:700;background:var(--bg);color:var(--text-muted);border:1px solid var(--border); }
.unread-dot   { display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:99px;font-size:0.64rem;font-weight:700;background:rgba(245,158,11,.1);color:#d97706;border:1px solid rgba(245,158,11,.25); }

/* ── Stats strip ────────────────────────────────────────────── */
.stats-strip { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px; }
.ss-item { background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:11px;text-align:center; }
.ss-val  { font-family:'Poppins',sans-serif;font-weight:800;font-size:1.2rem;color:var(--primary); }
.ss-lbl  { font-size:0.64rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-top:2px; }

/* ── Rate bar ────────────────────────────────────────────────── */
.rate-bar { height:7px;background:var(--border);border-radius:99px;overflow:hidden;margin:4px 0; }
.rate-bar-fill { height:100%;border-radius:99px;background:var(--primary);transition:width .8s; }

/* ── Category breakdown ─────────────────────────────────────── */
.cat-pills { display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px; }
.cat-pill  { display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:0.74rem;font-weight:600;cursor:pointer;transition:all .15s;border:1.5px solid; }
.cat-pill:hover { opacity:.85; }
.cat-pill.active { box-shadow:0 0 0 3px rgba(99,102,241,.2); }

/* ── Toolbar ─────────────────────────────────────────────────── */
.entries-toolbar { display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:12px; }
.entries-toolbar .search-box { flex:1;min-width:150px; }

/* ── Entry card ─────────────────────────────────────────────── */
.entry-card { background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:13px 15px;margin-bottom:10px; }
.entry-cats { display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px; }
.entry-cat-tag { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:6px;font-size:0.68rem;font-weight:700; border:1px solid;opacity:.85; }
.entry-content { font-size:0.88rem;color:var(--text-secondary);line-height:1.78;white-space:pre-wrap;word-break:break-word; }
.entry-footer  { display:flex;align-items:center;gap:10px;margin-top:9px;padding-top:9px;border-top:1px solid var(--border);flex-wrap:wrap; }

/* ── Tab filter ─────────────────────────────────────────────── */
.tab-row { display:flex;gap:4px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:4px;width:fit-content;margin-bottom:20px; }
.tab-btn { padding:6px 16px;border-radius:calc(var(--radius) - 2px);font-size:0.82rem;font-weight:600;cursor:pointer;transition:all .15s;border:none;background:transparent;color:var(--text-muted);font-family:'DM Sans',sans-serif; }
.tab-btn.active { background:var(--primary);color:#fff;box-shadow:0 2px 8px rgba(99,102,241,.3); }
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <main class="page-content">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Student Feedback</h1>
                    <p class="page-subtitle">Anonymous feedback from students in your batches</p>
                </div>
            </div>

            <div style="display:flex;align-items:flex-start;gap:12px;background:rgba(99,102,241,.06);border:1.5px solid rgba(99,102,241,.2);border-radius:var(--radius-lg);padding:13px 18px;margin-bottom:22px;">
                <span style="font-size:1.3rem;flex-shrink:0;">🛡️</span>
                <div style="font-size:0.82rem;color:var(--text-muted);line-height:1.65;">
                    <strong style="color:var(--text);">Completely Anonymous.</strong>
                    Student names, IDs, and all identity data are never stored with feedback entries.
                    You see only what students wrote — nothing more.
                </div>
            </div>

            <!-- Tab filter -->
            <div class="tab-row">
                <button class="tab-btn active" data-tab="all"    onclick="setTab('all')">All Sessions</button>
                <button class="tab-btn"        data-tab="active" onclick="setTab('active')">Live</button>
                <button class="tab-btn"        data-tab="closed" onclick="setTab('closed')">Closed</button>
            </div>

            <div id="sessions-container"><div class="chat-loading-row">Loading…</div></div>

        </main>
    </div>
</div>

<script>
'use strict';

const AJAX    = window.LMS_BASE + '/ajax/feedback.ajax.php';
let allSessions  = [];
let ALL_CATS     = {};
let currentTab   = 'all';
// per-session filters
const sessionFilters = {};

async function loadSessions() {
    const res = await ajax(AJAX, { action:'teacher_sessions' });
    if (res.status !== 'success') { Toast.error(res.message); return; }
    allSessions = res.data.sessions || [];
    ALL_CATS    = res.data.categories || {};
    renderSessions();
}

function setTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderSessions();
}

function renderSessions() {
    let list = allSessions;
    if (currentTab === 'active') list = allSessions.filter(s => s.is_active == 1);
    if (currentTab === 'closed') list = allSessions.filter(s => s.is_active == 0);

    const c = document.getElementById('sessions-container');
    if (!list.length) {
        c.innerHTML = '<div class="empty-state"><div class="empty-state-icon">💬</div><div class="empty-state-title">No feedback sessions</div><div class="empty-state-text">'
            + (currentTab === 'active' ? 'No sessions are currently live.' : 'No sessions match this filter.')
            + '</div></div>';
        return;
    }
    c.innerHTML = list.map(s => buildCard(s)).join('');
    if (window.lucide) lucide.createIcons();
}

function buildCard(s) {
    const badge   = s.is_active == 1
        ? '<span class="live-badge"><span class="live-dot"></span> LIVE</span>'
        : '<span class="closed-badge">Closed</span>';
    const unread  = s.unread_count > 0 ? '<span class="unread-dot">● ' + s.unread_count + ' unread</span>' : '';
    const rate    = s.enrolled_count > 0 ? Math.round(s.submitter_count / s.enrolled_count * 100) : 0;
    const actDate = s.activated_at ? new Date(s.activated_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric'}) : '—';

    return '<div class="fs-card">'
        + '<div class="fs-header" id="fsh-' + s.id + '" onclick="toggleCard(' + s.id + ')">'
            + '<div>'
                + '<div class="fs-title">' + esc(s.course_title) + ' — ' + esc(s.batch_name) + ' &nbsp;' + badge + ' ' + unread + '</div>'
                + '<div class="fs-sub">' + esc(s.title) + ' &nbsp;·&nbsp; ' + s.entry_count + ' responses &nbsp;·&nbsp; ' + rate + '% response rate &nbsp;·&nbsp; ' + esc(actDate) + '</div>'
            + '</div>'
            + '<div style="display:flex;align-items:center;gap:8px;">'
                + (s.entry_count > 0 ? '<a class="btn btn-secondary btn-sm" style="gap:5px;" href="' + window.LMS_BASE + '/ajax/feedback.ajax.php?action=export_entries&session_id=' + s.id + '"><i data-lucide="download" style="width:13px;height:13px;"></i> Export</a>' : '')
                + '<i data-lucide="chevron-down" style="width:16px;height:16px;color:var(--text-muted);transition:transform .25s;" id="tchev-' + s.id + '"></i>'
            + '</div>'
        + '</div>'
        + '<div class="fs-body" id="fsb-' + s.id + '"></div>'
        + '</div>';
}

async function toggleCard(sessionId) {
    const body   = document.getElementById('fsb-' + sessionId);
    const header = document.getElementById('fsh-' + sessionId);
    const chev   = document.getElementById('tchev-' + sessionId);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    header.classList.toggle('open', !isOpen);
    if (chev) chev.style.transform = isOpen ? '' : 'rotate(180deg)';

    if (!isOpen && !body.dataset.loaded) {
        body.dataset.loaded = '1';
        if (!sessionFilters[sessionId]) sessionFilters[sessionId] = { search:'', category:'', status:'' };
        await renderCardBody(sessionId, body);
    }
}

async function renderCardBody(sessionId, bodyEl) {
    bodyEl.innerHTML = '<div class="chat-loading-row">Loading…</div>';
    const f = sessionFilters[sessionId] || {};

    const [statsRes, entriesRes] = await Promise.all([
        ajax(AJAX, { action:'get_stats',    session_id:sessionId }),
        ajax(AJAX, { action:'list_entries', session_id:sessionId, search:f.search||'', filter_category:f.category||'', filter_status:f.status||'' }),
    ]);

    let html = '';

    if (statsRes.status === 'success') {
        const s = statsRes.data;
        html += '<div class="stats-strip">'
            + '<div class="ss-item"><div class="ss-val">' + s.entry_count + '</div><div class="ss-lbl">Entries</div></div>'
            + '<div class="ss-item"><div class="ss-val">' + s.submitted + '/' + s.enrolled + '</div><div class="ss-lbl">Submitted / Enrolled</div></div>'
            + '<div class="ss-item"><div class="ss-val">' + s.response_rate + '%</div><div class="ss-lbl">Response Rate</div></div>'
            + '</div>'
            + '<div class="rate-bar"><div class="rate-bar-fill" style="width:' + s.response_rate + '%;"></div></div>'
            + '<div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:14px;">' + s.submitted + ' of ' + s.enrolled + ' students responded</div>';

        // Category pills
        html += '<div class="cat-pills">'
            + '<button class="cat-pill active" style="background:var(--bg);color:var(--text-muted);border-color:var(--border);" onclick="setCatFilter(' + sessionId + ',\'\')" id="tcpill-' + sessionId + '-all">All</button>';
        Object.entries(ALL_CATS).forEach(([key, cat]) => {
            const count = s.categories[key] || 0;
            if (!count) return;
            html += '<button class="cat-pill" style="background:' + cat.color + '22;color:' + cat.color + ';border-color:' + cat.color + '44;" '
                + 'onclick="setCatFilter(' + sessionId + ',\'' + key + '\')" id="tcpill-' + sessionId + '-' + key + '">'
                + cat.emoji + ' ' + cat.label + ' (' + count + ')'
                + '</button>';
        });
        html += '</div>';
    }

    // Toolbar
    html += '<div class="entries-toolbar">'
        + '<div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" class="form-control" placeholder="Search entries…" id="tsearch-' + sessionId + '" oninput="tDebouncedSearch(' + sessionId + ')" value="' + esc(f.search||'') + '"></div>'
        + '<select class="form-control" style="width:140px;" onchange="setStatusFilter(' + sessionId + ',this.value)" id="tstatus-' + sessionId + '">'
            + '<option value="">All entries</option>'
            + '<option value="unread"' + (f.status==='unread'?' selected':'') + '>Unread</option>'
            + '<option value="reviewed"' + (f.status==='reviewed'?' selected':'') + '>Reviewed</option>'
        + '</select>'
        + '</div>';

    // Entries list
    html += '<div id="tentries-' + sessionId + '">';
    if (entriesRes.status === 'success') {
        const entries = entriesRes.data.entries || [];
        if (!entries.length) {
            html += '<div style="text-align:center;padding:24px;color:var(--text-muted);">No entries match your filters.</div>';
        } else {
            html += entries.map(e => buildEntryCard(e, sessionId)).join('');
        }
    }
    html += '</div>';

    bodyEl.innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

function buildEntryCard(entry, sessionId) {
    const cats = entry.categories || ['general'];
    const catTags = cats.map(c => {
        const cat = ALL_CATS[c] || { label:c, emoji:'📋', color:'#6b7280' };
        return '<span class="entry-cat-tag" style="color:' + cat.color + ';border-color:' + cat.color + '44;background:' + cat.color + '11;">' + cat.emoji + ' ' + cat.label + '</span>';
    }).join('');
    const time = new Date(entry.submitted_at).toLocaleDateString('en',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const reviewLabel = entry.is_reviewed == 1
        ? '<span style="color:#059669;font-size:0.7rem;font-weight:700;">✓ Reviewed</span>'
        : '<span style="color:#d97706;font-size:0.7rem;font-weight:700;">● Unread</span>';

    return '<div class="entry-card' + (entry.is_reviewed==1?' is-reviewed':'') + '">'
        + '<div class="entry-cats">' + catTags + '</div>'
        + '<div class="entry-content">' + esc(entry.content) + '</div>'
        + '<div class="entry-footer">'
            + '<span style="font-size:0.7rem;color:var(--text-muted);">🕵️ Anonymous &nbsp;·&nbsp; ' + esc(time) + '</span>'
            + reviewLabel
        + '</div>'
        + '</div>';
}

// ── Filter functions ──────────────────────────────────────────
function setCatFilter(sessionId, cat) {
    if (!sessionFilters[sessionId]) sessionFilters[sessionId] = {};
    sessionFilters[sessionId].category = cat;
    document.querySelectorAll('[id^="tcpill-' + sessionId + '-"]').forEach(p => p.classList.remove('active'));
    const target = document.getElementById('tcpill-' + sessionId + '-' + (cat || 'all'));
    if (target) target.classList.add('active');
    reloadEntries(sessionId);
}

function setStatusFilter(sessionId, val) {
    if (!sessionFilters[sessionId]) sessionFilters[sessionId] = {};
    sessionFilters[sessionId].status = val;
    reloadEntries(sessionId);
}

const tDebounceFns = {};
function tDebouncedSearch(sessionId) {
    if (!tDebounceFns[sessionId]) {
        tDebounceFns[sessionId] = debounce(() => {
            if (!sessionFilters[sessionId]) sessionFilters[sessionId] = {};
            sessionFilters[sessionId].search = document.getElementById('tsearch-' + sessionId)?.value?.trim() || '';
            reloadEntries(sessionId);
        }, 350);
    }
    tDebounceFns[sessionId]();
}

async function reloadEntries(sessionId) {
    const list = document.getElementById('tentries-' + sessionId);
    if (!list) return;
    list.innerHTML = '<div class="chat-loading-row">Loading…</div>';
    const f   = sessionFilters[sessionId] || {};
    const res = await ajax(AJAX, { action:'list_entries', session_id:sessionId, search:f.search||'', filter_category:f.category||'', filter_status:f.status||'' });
    if (res.status !== 'success') { list.innerHTML = '<div style="color:var(--danger);padding:12px;">' + esc(res.message) + '</div>'; return; }
    const entries = res.data.entries || [];
    list.innerHTML = entries.length
        ? entries.map(e => buildEntryCard(e, sessionId)).join('')
        : '<div style="text-align:center;padding:24px;color:var(--text-muted);">No entries match your filters.</div>';
    if (window.lucide) lucide.createIcons();
}

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.addEventListener('DOMContentLoaded', loadSessions);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>