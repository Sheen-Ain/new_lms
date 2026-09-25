<?php
// ============================================================
// STUDENT — FEEDBACK SUBMISSION v2 (Multi-Category)
// ============================================================
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Feedback';
$breadcrumbs = [
    ['label' => 'Student', 'url' => BASE_PATH . '/student/'],
    ['label' => 'Feedback'],
];
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Feedback card ──────────────────────────────────────────── */
.fb-card {
    background: var(--bg-card);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 18px;
    transition: box-shadow 0.15s;
}
.fb-card.is-open {
    border-color: rgba(99,102,241,0.4);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.08);
}
.fb-card-header {
    padding: 18px 22px;
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 12px;
}
.fb-card-title { font-family:'Poppins',sans-serif; font-weight:700; font-size:0.92rem; color:var(--text); }
.fb-card-sub   { font-size:0.76rem; color:var(--text-muted); margin-top:3px; }
.fb-card-desc  { font-size:0.84rem; color:var(--text-secondary); margin-top:8px; line-height:1.65; }

/* ── Status badges ──────────────────────────────────────────── */
.open-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:99px; flex-shrink:0;
    font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em;
    background:rgba(99,102,241,.1); color:var(--primary); border:1px solid rgba(99,102,241,.3);
    animation:openGlow 2.5s ease infinite alternate;
}
@keyframes openGlow{from{box-shadow:0 0 0 0 rgba(99,102,241,0);}to{box-shadow:0 0 0 6px rgba(99,102,241,0.1);}}
.open-dot { width:6px;height:6px;border-radius:50%;background:var(--primary);animation:blink 1.2s ease infinite; }
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}

.done-badge   { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:99px;flex-shrink:0;font-size:0.7rem;font-weight:700;background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.2); }
.closed-badge { padding:4px 12px;border-radius:99px;flex-shrink:0;font-size:0.7rem;font-weight:700;background:var(--bg);color:var(--text-muted);border:1px solid var(--border); }

/* ── Form area ──────────────────────────────────────────────── */
.fb-form { padding:0 22px 22px; border-top:1px solid var(--border); }

/* ── Anon guarantee ─────────────────────────────────────────── */
.anon-box {
    display:flex; align-items:flex-start; gap:10px;
    background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.2);
    border-radius:var(--radius); padding:11px 14px; margin:14px 0;
}
.anon-box p { font-size:0.78rem; color:var(--text-muted); line-height:1.65; margin:0; }
.anon-box strong { color:var(--text-secondary); }

/* ── Multi-select categories ────────────────────────────────── */
.cat-select-label {
    font-size:0.82rem; font-weight:700; color:var(--text-secondary);
    margin-bottom:10px; display:block;
}
.cat-select-hint {
    font-size:0.74rem; color:var(--text-muted); font-weight:400; margin-left:6px;
}
.cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 8px;
    margin-bottom: 18px;
}
.cat-toggle {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 12px; border-radius: var(--radius);
    border: 1.5px solid var(--border); background: var(--bg);
    cursor: pointer; transition: all 0.15s; user-select: none;
    font-size: 0.82rem; font-weight: 600; color: var(--text-muted);
}
.cat-toggle:hover {
    border-color: var(--primary); color: var(--primary);
    background: rgba(99,102,241,0.04);
}
.cat-toggle.selected {
    border-color: currentColor;
    font-weight: 700;
}
.cat-toggle-check {
    width: 18px; height: 18px; border-radius: 5px;
    border: 1.5px solid currentColor; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.cat-toggle.selected .cat-toggle-check {
    background: currentColor;
}
.cat-toggle.selected .cat-toggle-check::after {
    content: '✓'; font-size: 0.7rem; color: #fff; font-weight: 900;
}
.cat-toggle-emoji { font-size: 1rem; }
.cat-toggle-label { flex: 1; min-width: 0; line-height: 1.3; }

.cat-selection-count {
    font-size: 0.76rem; font-weight: 600;
    margin-bottom: 14px;
    padding: 5px 10px; border-radius: 99px; display: inline-block;
    transition: all .15s;
}
.cat-selection-count.none    { background:rgba(239,68,68,.08);  color:#dc2626; border:1px solid rgba(239,68,68,.2); }
.cat-selection-count.some    { background:rgba(99,102,241,.08); color:var(--primary); border:1px solid rgba(99,102,241,.2); }

/* ── Textarea ───────────────────────────────────────────────── */
.fb-textarea {
    width: 100%; min-height: 160px; resize: vertical;
    padding: 14px 16px; border: 1.5px solid var(--border);
    border-radius: var(--radius); background: var(--bg);
    color: var(--text); font-size: 0.9rem;
    font-family: 'DM Sans', sans-serif; line-height: 1.65;
    transition: border-color 0.15s; outline: none;
}
.fb-textarea:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
.fb-textarea::placeholder { color:var(--text-muted); }
.fb-char-row {
    display: flex; align-items: center;
    justify-content: space-between; margin-top: 5px;
    font-size: 0.72rem;
}
.fb-char-min { color:var(--text-muted); }
.fb-char-count.ok  { color:#059669; font-weight:600; }
.fb-char-count.low { color:#dc2626; font-weight:600; }

/* ── Submit ─────────────────────────────────────────────────── */
.fb-submit-btn {
    width: 100%; padding: 13px; margin-top: 14px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: #fff; border: none; border-radius: var(--radius);
    font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px;
    box-shadow: 0 4px 16px rgba(99,102,241,.35);
    transition: opacity .15s, transform .1s;
}
.fb-submit-btn:hover:not(:disabled) { opacity:.92; transform:translateY(-1px); }
.fb-submit-btn:disabled { opacity:.55; cursor:not-allowed; transform:none; box-shadow:none; }

/* ── Thank you ──────────────────────────────────────────────── */
.thankyou { padding:22px; text-align:center; border-top:1px solid var(--border); }
.ty-icon  { font-size:2.5rem; margin-bottom:8px; }
.ty-title { font-family:'Poppins',sans-serif; font-weight:700; font-size:0.95rem; color:var(--text); margin-bottom:4px; }
.ty-sub   { font-size:0.82rem; color:var(--text-muted); line-height:1.65; }

/* ── Closed state ───────────────────────────────────────────── */
.fb-closed-msg { padding:14px 22px; font-size:0.82rem; color:var(--text-muted); border-top:1px solid var(--border); }

@media (max-width:600px) {
    .cat-grid { grid-template-columns: repeat(2,1fr); }
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
                    <p class="page-subtitle">Share your honest, anonymous thoughts about your batches</p>
                </div>
            </div>

            <!-- Main guarantee banner -->
            <div style="display:flex;align-items:flex-start;gap:16px;background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(139,92,246,.05));border:1.5px solid rgba(99,102,241,.2);border-radius:var(--radius-lg);padding:18px 22px;margin-bottom:24px;">
                <div style="font-size:2.2rem;flex-shrink:0;line-height:1;">🛡️</div>
                <div>
                    <div style="font-family:'Poppins',sans-serif;font-weight:800;font-size:0.95rem;color:var(--text);margin-bottom:6px;">Your Feedback is 100% Anonymous — Guaranteed by Design</div>
                    <div style="font-size:0.83rem;color:var(--text-muted);line-height:1.72;">
                        Your name, student ID, and any other identity information are <strong style="color:var(--text-secondary);">never stored</strong> with your feedback.
                        Not the admin, not the teacher, and not even a system administrator with full database access can see who wrote what.
                        The anonymity is enforced at the code and database level, not just by policy.
                        <strong style="color:var(--text-secondary);">Write freely and honestly.</strong>
                    </div>
                </div>
            </div>

            <!-- Feedback list -->
            <div id="fb-list"><div class="chat-loading-row">Loading your feedback sessions…</div></div>

        </main>
    </div>
</div>

<script>
'use strict';

const AJAX    = window.LMS_BASE + '/ajax/feedback.ajax.php';
let ALL_CATS  = {};
// Track selected categories per session
const sessionCats = {};

const MIN_CHARS = 20;

// ── Load batches + feedback status ───────────────────────────
async function loadFeedback() {
    const res = await ajax(AJAX, { action: 'student_batches' });
    if (res.status !== 'success') { Toast.error(res.message); return; }

    ALL_CATS = res.data.categories || {};
    const batches = res.data.batches || [];
    const container = document.getElementById('fb-list');

    const open   = batches.filter(b => b.feedback_active && !b.already_submitted);
    const done   = batches.filter(b => b.feedback_active &&  b.already_submitted);
    const closed = batches.filter(b => !b.feedback_active);

    if (!batches.length) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-title">No batches yet</div><div class="empty-state-text">You are not enrolled in any batches.</div></div>';
        return;
    }

    let html = '';

    if (open.length) {
        html += sectionHeader('✦ Open for Feedback', 'var(--primary)');
        open.forEach(b => { html += buildCard(b, 'open'); });
    }
    if (done.length) {
        html += sectionHeader('✓ Already Submitted', '#059669');
        done.forEach(b => { html += buildCard(b, 'done'); });
    }
    if (closed.length) {
        html += sectionHeader('No Active Session', 'var(--text-muted)');
        closed.forEach(b => { html += buildCard(b, 'closed'); });
    }

    container.innerHTML = html;
}

function sectionHeader(label, color) {
    return '<div style="font-family:\'Poppins\',sans-serif;font-weight:700;font-size:0.85rem;color:' + color + ';margin:20px 0 10px;display:flex;align-items:center;gap:8px;">' + label + '</div>';
}

function buildCard(b, state) {
    const isOpen   = state === 'open';
    const isDone   = state === 'done';
    const isClosed = state === 'closed';

    const badge = isOpen
        ? '<span class="open-badge"><span class="open-dot"></span> Open Now</span>'
        : isDone
            ? '<span class="done-badge">✓ Submitted</span>'
            : '<span class="closed-badge">Not Active</span>';

    let bodyHTML = '';
    if (isOpen) {
        // Init selected cats for this session
        if (!sessionCats[b.session_id]) sessionCats[b.session_id] = ['general'];
        bodyHTML = buildFeedbackForm(b);
    } else if (isDone) {
        bodyHTML = '<div class="thankyou"><div class="ty-icon">🙏</div><div class="ty-title">Thank you for your feedback!</div><div class="ty-sub">Your anonymous response has been recorded. Your identity is protected.</div></div>';
    } else {
        bodyHTML = '<div class="fb-closed-msg">No feedback session is currently active for this batch. Your teacher or admin will activate it when the time comes.</div>';
    }

    return '<div class="fb-card' + (isOpen ? ' is-open' : '') + '">'
        + '<div class="fb-card-header">'
            + '<div>'
                + '<div class="fb-card-title">' + esc(b.course_title) + ' — ' + esc(b.batch_name) + '</div>'
                + (b.session_title ? '<div class="fb-card-sub">' + esc(b.session_title) + '</div>' : '')
                + (isOpen && b.session_description ? '<div class="fb-card-desc">' + esc(b.session_description) + '</div>' : '')
            + '</div>'
            + badge
        + '</div>'
        + bodyHTML
        + '</div>';
}

function buildFeedbackForm(b) {
    const sid = b.session_id;

    // Build category toggles
    let catHTML = '';
    Object.entries(ALL_CATS).forEach(([key, cat]) => {
        const isSelected = (sessionCats[sid] || []).includes(key);
        catHTML += '<div class="cat-toggle' + (isSelected ? ' selected' : '') + '" '
            + 'id="cat-' + sid + '-' + key + '" '
            + 'style="color:' + cat.color + ';" '
            + 'onclick="toggleCat(\'' + sid + '\',\'' + key + '\')">'
            + '<div class="cat-toggle-check"></div>'
            + '<span class="cat-toggle-emoji">' + cat.emoji + '</span>'
            + '<span class="cat-toggle-label">' + cat.label + '</span>'
            + '</div>';
    });

    const selectedCount = (sessionCats[sid] || []).length;
    const countClass    = selectedCount === 0 ? 'none' : 'some';
    const countLabel    = selectedCount === 0 ? 'Select at least one category' : selectedCount + ' categor' + (selectedCount === 1 ? 'y' : 'ies') + ' selected';

    return '<div class="fb-form" id="ff-' + sid + '">'
        + '<div class="anon-box">'
            + '<span style="font-size:1.1rem;flex-shrink:0;">🔒</span>'
            + '<p>Your submission is <strong>fully anonymous</strong>. You can write anything — praise, criticism, harsh feedback — nothing can be traced back to you. Be honest.</p>'
        + '</div>'

        + '<label class="cat-select-label">What is your feedback about? <span class="cat-select-hint">(select all that apply)</span></label>'
        + '<div class="cat-grid" id="catgrid-' + sid + '">' + catHTML + '</div>'
        + '<div class="cat-selection-count ' + countClass + '" id="catcount-' + sid + '">' + countLabel + '</div>'

        + '<div class="form-group">'
            + '<label class="form-label">Your Feedback</label>'
            + '<textarea class="fb-textarea" id="fbtext-' + sid + '" '
                + 'placeholder="Write your honest feedback here. Describe the teaching quality, content, how classes went, anything about the organization, facilities, platform — whatever is on your mind. Nothing you write here can be traced back to you." '
                + 'oninput="updateCharCount(\'' + sid + '\')"'
            + '></textarea>'
            + '<div class="fb-char-row">'
                + '<span class="fb-char-min">Minimum ' + MIN_CHARS + ' characters</span>'
                + '<span class="fb-char-count low" id="charcount-' + sid + '">0 / ' + MIN_CHARS + ' min</span>'
            + '</div>'
        + '</div>'

        + '<button class="fb-submit-btn" id="fbsubmit-' + sid + '" onclick="submitFeedback(\'' + sid + '\')">'
            + '🛡️ Submit Anonymously'
        + '</button>'
        + '</div>';
}

// ── Category toggle ───────────────────────────────────────────
function toggleCat(sessionId, catKey) {
    if (!sessionCats[sessionId]) sessionCats[sessionId] = [];
    const cats = sessionCats[sessionId];
    const idx  = cats.indexOf(catKey);
    if (idx >= 0) {
        cats.splice(idx, 1);
    } else {
        cats.push(catKey);
    }
    sessionCats[sessionId] = cats;

    // Update toggle UI
    const el = document.getElementById('cat-' + sessionId + '-' + catKey);
    if (el) el.classList.toggle('selected', idx < 0);

    // Update count label
    const countEl = document.getElementById('catcount-' + sessionId);
    if (countEl) {
        const n = cats.length;
        countEl.textContent = n === 0 ? 'Select at least one category' : n + ' categor' + (n === 1 ? 'y' : 'ies') + ' selected';
        countEl.className = 'cat-selection-count ' + (n === 0 ? 'none' : 'some');
    }
}

// ── Char count ────────────────────────────────────────────────
function updateCharCount(sessionId) {
    const ta  = document.getElementById('fbtext-' + sessionId);
    const cnt = document.getElementById('charcount-' + sessionId);
    if (!ta || !cnt) return;
    const len = ta.value.length;
    cnt.textContent = len < MIN_CHARS
        ? len + ' / ' + MIN_CHARS + ' min'
        : len + ' characters ✓';
    cnt.className = 'fb-char-count ' + (len >= MIN_CHARS ? 'ok' : 'low');
}

// ── Submit ────────────────────────────────────────────────────
async function submitFeedback(sessionId) {
    const content = (document.getElementById('fbtext-' + sessionId)?.value || '').trim();
    const cats    = sessionCats[sessionId] || [];

    if (cats.length === 0) {
        Toast.error('Please select at least one category.');
        return;
    }
    if (content.length < MIN_CHARS) {
        Toast.error('Please write at least ' + MIN_CHARS + ' characters.');
        return;
    }

    const confirmed = await new Promise(resolve => {
        Modal.confirm({
            title: 'Submit Anonymously?',
            message: 'Once submitted, your feedback <strong>cannot be edited</strong>. '
                + 'Your identity will <strong>never be revealed</strong>. '
                + 'You are submitting feedback for <strong>' + cats.length + ' categor' + (cats.length === 1 ? 'y' : 'ies') + '</strong>. Ready?',
            confirmText: '🛡️ Submit',
            confirmClass: 'btn-primary',
            icon: '🔒', iconClass: 'modal-icon-primary',
            onConfirm: () => resolve(true),
            onCancel:  () => resolve(false),
        });
    });
    if (!confirmed) return;

    const btn = document.getElementById('fbsubmit-' + sessionId);
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

    const res = await ajax(AJAX, {
        action:     'submit_feedback',
        session_id: sessionId,
        content:    content,
        categories: JSON.stringify(cats),
    });

    if (res.status !== 'success') {
        Toast.error(res.message);
        if (btn) { btn.disabled = false; btn.innerHTML = '🛡️ Submit Anonymously'; }
        return;
    }

    Toast.success('✓ ' + res.message);
    setTimeout(() => loadFeedback(), 700);
}

function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.addEventListener('DOMContentLoaded', loadFeedback);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>