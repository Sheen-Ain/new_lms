<?php
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Topics';
$breadcrumbs = [['label' => 'Student'], ['label' => 'Topics']];

$uid = (int)$currentUser['id'];

$batches = [];

$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.name,
        c.title AS course_title
    FROM batch_students bs
    JOIN batches b ON bs.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE bs.student_id = ?
    ORDER BY c.title, b.name
");

$stmt->bind_param('i', $uid);
$stmt->execute();

/* FIX: bind results instead of get_result() */
$stmt->bind_result($batchId, $batchName, $courseTitle);

while ($stmt->fetch()) {
  $batches[] = [
    'id' => $batchId,
    'name' => $batchName,
    'course_title' => $courseTitle
  ];
}

$stmt->close();

include __DIR__ . '/../includes/header.php';
?>
<style>
  /* ── Topic card ─────────────────────────────────────── */
  .topic-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 14px;
    border-left: 3px solid var(--primary);
    transition: transform .15s, box-shadow .15s;
  }

  .topic-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
  }

  .topic-icon {
    width: 42px;
    height: 42px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
  }

  .topic-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    margin-top: 10px;
  }

  .topic-actions {
    display: flex;
    gap: 8px;
    margin-top: 14px;
    flex-wrap: wrap;
  }

  .topic-actions .btn {
    flex-shrink: 0;
  }

  /* ── Filter bar ─────────────────────────────────────── */
  .topic-filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
  }

  .topic-filter-bar .search-box {
    flex: 1;
    min-width: 180px;
  }

  .topic-filter-bar select {
    width: 220px;
    flex-shrink: 0;
  }

  /* ── Modals ─────────────────────────────────────────── */
  #files-modal-overlay .modal,
  #tasn-modal-overlay .modal {
    max-width: min(700px, 96vw);
  }

  /* FM file row */
  .fm-file-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--radius);
    border: 1.5px solid var(--border);
    background: var(--bg);
    transition: border-color .15s;
  }

  .fm-file-row.selected {
    border-color: var(--primary);
  }

  .asn-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--bg);
    transition: box-shadow .15s;
  }

  .asn-row:hover {
    box-shadow: var(--shadow);
  }

  /* ── Responsive ─────────────────────────────────────── */
  @media (max-width: 768px) {
    .topic-filter-bar select {
      width: 100%;
    }

    .topic-card {
      padding: 16px 16px;
    }

    .topic-actions .btn {
      font-size: 0.78rem;
      padding: 6px 10px;
    }

    .asn-row {
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
    }

    .asn-row>div:last-child {
      align-self: flex-end;
    }
  }

  @media (max-width: 540px) {
    .topic-icon {
      width: 36px;
      height: 36px;
    }

    .fm-file-row {
      flex-wrap: wrap;
    }

    .fm-file-row .btn {
      width: 100%;
      justify-content: center;
      margin-top: 4px;
    }

    #fm-toolbar {
      gap: 6px;
    }

    #fm-toolbar button {
      font-size: 0.78rem;
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
          <h1 class="page-title">Learning Materials</h1>
          <p class="page-subtitle">Topics, files and related assignments from your batches</p>
        </div>
      </div>

      <div class="topic-filter-bar">
        <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="topic-search" class="form-control" placeholder="Search topics…"></div>
        <select id="filter-batch" class="form-control">
          <option value="">All Batches</option>
          <option value="-1">🌐 Global Only</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?= e($b['id']) ?>"><?= e($b['course_title'] . ' – ' . $b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="topics-container">
        <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="card" style="margin-bottom:16px;height:100px;">
            <div class="skeleton" style="width:50%;height:18px;border-radius:6px;margin-bottom:10px;"></div>
            <div class="skeleton" style="width:80%;height:13px;border-radius:6px;margin-bottom:8px;"></div>
            <div class="skeleton" style="width:30%;height:28px;border-radius:6px;"></div>
          </div>
        <?php endfor; ?>
      </div>
      <div id="tp-pagination"></div>
    </main>
  </div>
</div>

<!-- ── TOPIC FILES MODAL ── -->
<div class="modal-overlay" id="files-modal-overlay">
  <div class="modal modal-lg" style="max-width:700px;">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="paperclip" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="files-modal-title">Topic Files</h3>
      <button class="modal-close" data-modal-close="files-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body">
      <!-- Bulk toolbar -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;" id="fm-toolbar">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
          <input type="checkbox" id="fm-select-all" style="width:15px;height:15px;accent-color:var(--primary);" onchange="fmToggleAll(this.checked)"> Select All
        </label>
        <div style="flex:1;"></div>
        <button class="btn btn-secondary btn-sm" id="fm-dl-all-btn" style="display:none;" onclick="fmDownloadAll()">
          <i data-lucide="download-cloud" style="width:13px;height:13px;"></i> Download All
        </button>
        <button class="btn btn-primary btn-sm" id="fm-dl-sel-btn" style="display:none;" onclick="fmDownloadSelected()">
          <i data-lucide="archive" style="width:13px;height:13px;"></i> Download Selected (<span id="fm-sel-count">0</span>)
        </button>
      </div>
      <div id="files-modal-body" style="min-height:60px;"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="files-modal">Close</button></div>
  </div>
</div>

<!-- ── TOPIC ASSIGNMENTS MODAL ── -->
<div class="modal-overlay" id="tasn-modal-overlay">
  <div class="modal modal-lg" style="max-width:680px;">
    <div class="modal-header">
      <div class="modal-icon" style="background:rgba(245,158,11,0.12);color:#d97706;"><i data-lucide="clipboard-list" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="tasn-modal-title">Assignments</h3>
      <button class="modal-close" data-modal-close="tasn-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" id="tasn-modal-body" style="min-height:80px;"></div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="tasn-modal">Close</button></div>
  </div>
</div>

<script>
  let tpPage = 1,
    tpBatch = '',
    tpSearch = '';
  let fmSelectedIds = new Set(),
    _fmAllFileIds = [];

  /* ── SVG file icon ── */

  /* ── Load topics ── */
  async function loadTopics() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
        action: 'list',
        page: tpPage,
        per_page: 10,
        batch_id: tpBatch,
        status: 'active',
        search: tpSearch
      });
      if (res.status === 'success') {
        renderTopics(res.data.topics);
        renderPag(res.data.total, res.data.page, res.data.per_page);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function renderTopics(items) {
    const c = document.getElementById('topics-container');
    if (!items?.length) {
      c.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📚</div><div class="empty-state-title">No topics found</div><div class="empty-state-text">Topics will appear here when your teacher adds them</div></div>`;
      return;
    }
    c.innerHTML = items.map(t => {
      const hasFiles = t.file_count > 0;
      const hasAsn = t.assignment_count > 0;
      // Palette based on batch
      const palette = t.batch_id ? 'var(--primary)' : '#10b981';
      return `<div class="topic-card">
      <div style="display:flex;align-items:flex-start;gap:14px;">
        <div class="topic-icon" style="background:${t.batch_id?'rgba(99,102,241,0.1)':'rgba(16,185,129,0.1)'};">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${palette}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div style="flex:1;min-width:0;">
          <h3 style="font-family:Poppins,sans-serif;font-size:0.95rem;font-weight:700;margin:0 0 3px 0;line-height:1.35;">${escapeHtml(t.title)}</h3>
          <div class="topic-meta">
            <span style="font-size:0.74rem;color:var(--text-muted);">${t.batch_id?'📚 '+escapeHtml(t.batch_name||'—'):'🌐 Global'}</span>
            <span style="color:var(--border);">·</span>
            <span style="font-size:0.74rem;color:var(--text-muted);">Sort #${t.sort_order}</span>
          </div>
          ${t.description?`<p style="font-size:0.82rem;color:var(--text-secondary);margin:8px 0 0;line-height:1.6;">${escapeHtml(t.description)}</p>`:''}
          <div class="topic-actions">
            <!-- Files button -->
            <button class="btn btn-secondary btn-sm ${!hasFiles?'':'btn-primary'}"
              ${!hasFiles?'disabled style="opacity:0.5;cursor:not-allowed;"':'onclick="openFiles('+t.id+',\''+escapeHtml(t.title).replace(/'/g,"\\'")+'\')"'}>
              <i data-lucide="paperclip" style="width:13px;height:13px;"></i>
              ${hasFiles?t.file_count+' File'+(t.file_count>1?'s':''):'No Files'}
            </button>
            <!-- Assignments button — interactive only when assignments exist -->
            <button class="btn btn-sm ${hasAsn?'':'btn-ghost'}"
              style="${hasAsn?'background:rgba(245,158,11,0.12);color:#d97706;border:1px solid rgba(245,158,11,0.3);':'opacity:0.45;cursor:not-allowed;'}"
              ${hasAsn?'onclick="openTopicAssignments('+t.id+',\''+escapeHtml(t.title).replace(/'/g,"\\'")+'\')"':'disabled'}>
              <i data-lucide="clipboard-list" style="width:13px;height:13px;"></i>
              ${hasAsn?t.assignment_count+' Assignment'+(t.assignment_count>1?'s':''):'No Assignments'}
            </button>
          </div>
        </div>
      </div>
    </div>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('topics-container')]
    });
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('tp-pagination');
    if (!total || total <= perPage) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="tpPage=${i};loadTopics();return false;">${i}</a>`;
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="tpPage=${page-1};loadTopics();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="tpPage=${page+1};loadTopics();return false;">›</a></div></div>`;
  }

  /* ── File manager modal ── */
  async function openFiles(topicId, title) {
    document.getElementById('files-modal-title').textContent = title;
    fmSelectedIds = new Set();
    _fmAllFileIds = [];
    fmUpdateBulkBar();
    Modal.open('files-modal');
    await loadFilesModal(topicId);
  }

  async function loadFilesModal(topicId) {
    const body = document.getElementById('files-modal-body');
    body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'get_one',
      topic_id: topicId
    });
    if (res.status !== 'success') {
      body.innerHTML = '<div style="color:var(--danger);padding:16px;">Failed to load</div>';
      return;
    }
    const files = (res.data.files || []).filter(f => f.status !== 'inactive');
    _fmAllFileIds = files.map(f => String(f.id));
    document.getElementById('fm-select-all').checked = false;
    fmUpdateBulkBar();
    if (!files.length) {
      body.innerHTML = '<div style="text-align:center;padding:28px;color:var(--text-muted);">No files attached to this topic yet</div>';
      return;
    }
    body.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;">' + files.map(f => {
      const sz = f.file_size > 1048576 ? (f.file_size / 1048576).toFixed(1) + ' MB' : (f.file_size >> 10) + ' KB';
      return `<div class="fm-file-row" id="fmrow-${f.id}">
      <input type="checkbox" class="fm-cb" value="${f.id}" style="width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;" onchange="fmToggleCb('${f.id}',this.checked)">
      <div style="flex-shrink:0;">${fileIcon(f.file_name)}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:0.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>
        <div style="font-size:0.72rem;color:var(--text-muted);">${sz}</div>
      </div>
      <button class="btn btn-secondary btn-sm lms-view-btn" style="flex-shrink:0;padding:5px 12px;"
        data-fid="${f.id}" data-ftype="topic" data-fname="${escapeHtml(f.file_name)}" title="View">
        <i data-lucide="eye" style="width:13px;height:13px;"></i> View
      </button>
      <button class="btn btn-primary btn-sm" style="flex-shrink:0;padding:5px 12px;" onclick="LMSDownload.single('topic',${f.id})">
        <i data-lucide="download" style="width:13px;height:13px;"></i> Download
      </button>
    </div>`;
    }).join('') + '</div>';
    if (window.lucide) lucide.createIcons({
      nodes: [body]
    });
  }

  function fmToggleCb(id, checked) {
    if (checked) fmSelectedIds.add(String(id));
    else fmSelectedIds.delete(String(id));
    fmUpdateBulkBar();
    const row = document.getElementById('fmrow-' + id);
    if (row) {
      row.style.borderColor = checked ? 'var(--primary)' : 'var(--border)';
      row.classList.toggle('selected', checked);
    }
    document.getElementById('fm-select-all').checked = [...document.querySelectorAll('.fm-cb')].every(c => c.checked);
  }

  function fmToggleAll(checked) {
    document.querySelectorAll('.fm-cb').forEach(c => {
      c.checked = checked;
      fmToggleCb(c.value, checked);
    });
  }

  function fmUpdateBulkBar() {
    const n = fmSelectedIds.size;
    document.getElementById('fm-sel-count').textContent = n;
    document.getElementById('fm-dl-sel-btn').style.display = n > 0 ? '' : 'none';
    document.getElementById('fm-dl-all-btn').style.display = _fmAllFileIds.length > 0 ? '' : 'none';
  }

  function fmDownloadSelected() {
    if (!fmSelectedIds.size) {
      Toast.warning('Select at least one file');
      return;
    }
    LMSDownload.zip('topic_files', [...fmSelectedIds]);
  }

  function fmDownloadAll() {
    if (!_fmAllFileIds.length) {
      Toast.warning('No files available');
      return;
    }
    if (_fmAllFileIds.length === 1) {
      LMSDownload.single('topic', _fmAllFileIds[0]);
      return;
    }
    LMSDownload.zip('topic_files', _fmAllFileIds);
  }

  /* ── Topic assignments modal ── */
  async function openTopicAssignments(topicId, title) {
    document.getElementById('tasn-modal-title').textContent = 'Assignments: ' + title;
    Modal.open('tasn-modal');
    const body = document.getElementById('tasn-modal-body');
    body.innerHTML = '<div style="text-align:center;padding:28px;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'get_topic_assignments',
      topic_id: topicId
    });
    if (res.status !== 'success') {
      body.innerHTML = '<div style="color:var(--danger);padding:16px;">Failed to load</div>';
      return;
    }
    const items = res.data || [];
    if (!items.length) {
      body.innerHTML = '<div style="text-align:center;padding:28px;color:var(--text-muted);">No active assignments for this topic</div>';
      return;
    }
    const now = Date.now();
    body.innerHTML = '<div style="display:flex;flex-direction:column;gap:10px;">' + items.map(a => {
      const diff = a.due_date ? new Date(a.due_date.replace(' ', 'T')) - now : null;
      const over = diff !== null && diff < 0;
      const sub = a.sub_status;
      const allowLate = a.allow_late == 1;
      const locked = over && !allowLate && !sub;

      // Status badge
      let badge = '';
      if (sub === 'graded') badge = `<span class="badge badge-success" style="font-size:0.7rem;">🏆 Graded</span>`;
      else if (sub === 'submitted') badge = `<span class="badge badge-info" style="font-size:0.7rem;">📤 Submitted</span>`;
      else if (locked) badge = `<span class="badge badge-danger" style="font-size:0.7rem;">🔒 Closed</span>`;
      else if (over && allowLate) badge = `<span class="badge badge-warning" style="font-size:0.7rem;">⚠️ Late OK</span>`;
      else if (diff !== null && diff < 86400000) badge = `<span class="badge badge-warning" style="font-size:0.7rem;">⏰ Due Soon</span>`;
      else badge = `<span class="badge badge-secondary" style="font-size:0.7rem;">📋 Pending</span>`;

      // Due display
      let due = 'No due date';
      if (a.due_date) {
        if (over) due = `<span style="color:var(--danger);font-weight:600;">Overdue</span>`;
        else if (diff < 3600000) due = `<span style="color:var(--danger);">Due in ${Math.ceil(diff/60000)}m</span>`;
        else if (diff < 86400000) due = `<span style="color:var(--warning);">Due in ${Math.ceil(diff/3600000)}h</span>`;
        else due = new Date(a.due_date.replace(' ', 'T')).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        });
      }

      // Score bar if graded
      let scoreBar = '';
      if (sub === 'graded' && a.sub_marks != null) {
        const pct = Math.min(100, Math.round(a.sub_marks / (a.total_marks || 100) * 100));
        const gc = pct >= 70 ? 'var(--success)' : pct >= 40 ? 'var(--warning)' : 'var(--danger)';
        scoreBar = `<div style="margin-top:8px;">
        <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted);margin-bottom:3px;">
          <span>Score</span><span style="font-weight:700;color:${gc};">${a.sub_marks}/${a.total_marks||100} (${pct}%)</span>
        </div>
        <div style="height:4px;background:var(--border);border-radius:99px;overflow:hidden;">
          <div style="height:100%;width:${pct}%;background:${gc};border-radius:99px;"></div>
        </div>
      </div>`;
      }

      // Action button
      let actionBtn = '';
      if (!sub && !locked) {
        actionBtn = `<a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-primary btn-sm" style="flex-shrink:0;white-space:nowrap;"><i data-lucide="upload" style="width:12px;height:12px;"></i> Submit</a>`;
      } else if (sub === 'submitted') {
        actionBtn = `<span class="btn btn-secondary btn-sm" style="flex-shrink:0;opacity:0.7;cursor:default;">Awaiting Grade</span>`;
      } else if (sub === 'graded') {
        actionBtn = `<a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-secondary btn-sm" style="flex-shrink:0;">View Feedback</a>`;
      } else if (locked) {
        actionBtn = `<span class="btn btn-danger btn-sm" style="flex-shrink:0;opacity:0.7;cursor:default;">🔒 Closed</span>`;
      }
      const hasFiles = a.file_count > 0;

      return `<div class="asn-row">
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
          <span style="font-weight:700;font-size:0.875rem;">${escapeHtml(a.title)}</span>
          ${badge}
          ${a.is_late?'<span class="badge badge-danger" style="font-size:0.65rem;">Late</span>':''}
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:0.76rem;color:var(--text-muted);margin-bottom:4px;">
          <span>📅 ${due}</span>
          <span>🏆 ${a.total_marks||100} pts</span>
          <span>📚 ${escapeHtml(a.batch_name||'—')}</span>
          ${hasFiles?`<span style="cursor:pointer;color:var(--primary);" onclick="viewAFilesInline(${a.id},'${escapeHtml(a.title).replace(/'/g,"\\'")}')">📎 ${a.file_count} file${a.file_count>1?'s':''}</span>`:''}
        </div>
        ${scoreBar}
      </div>
      <div style="flex-shrink:0;">${actionBtn}</div>
    </div>`;
    }).join('') + '</div>';
    if (window.lucide) lucide.createIcons({
      nodes: [body]
    });
  }

  /* ── Inline assignment files viewer (from within topic-assignments modal) ── */
  async function viewAFilesInline(assignId, title) {
    // Close topic assignments modal, open files modal re-used
    Modal.close('tasn-modal');
    setTimeout(async () => {
      document.getElementById('files-modal-title').textContent = 'Files: ' + title;
      fmSelectedIds = new Set();
      _fmAllFileIds = [];
      fmUpdateBulkBar();
      Modal.open('files-modal');
      const body = document.getElementById('files-modal-body');
      body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">Loading…</div>';
      const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
        action: 'get_one',
        assignment_id: assignId
      });
      if (res.status !== 'success') {
        body.innerHTML = '<div style="color:var(--danger);">Error</div>';
        return;
      }
      const files = (res.data.files || []).filter(f => f.status !== 'inactive');
      _fmAllFileIds = files.map(f => String(f.id));
      fmUpdateBulkBar();
      if (!files.length) {
        body.innerHTML = '<div style="text-align:center;padding:28px;color:var(--text-muted);">No files available</div>';
        return;
      }
      // Reuse same file list UI but download uses 'assignment' type
      body.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;">' + files.map(f => {
        const sz = f.file_size > 1048576 ? (f.file_size / 1048576).toFixed(1) + ' MB' : (f.file_size >> 10) + ' KB';
        return `<div class="fm-file-row" id="fmrow-${f.id}">
        <input type="checkbox" class="fm-cb" value="${f.id}" data-dltype="assignment" style="width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;" onchange="fmToggleCb('${f.id}',this.checked)">
        <div style="flex-shrink:0;">${fileIcon(f.file_name)}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:0.84rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>
          <div style="font-size:0.72rem;color:var(--text-muted);">${sz}</div>
        </div>
        <button class="btn btn-secondary btn-sm lms-view-btn" style="flex-shrink:0;padding:5px 12px;"
          data-fid="${f.id}" data-ftype="assignment" data-fname="${escapeHtml(f.file_name)}" title="View">
          <i data-lucide="eye" style="width:13px;height:13px;"></i> View
        </button>
        <button class="btn btn-primary btn-sm" style="flex-shrink:0;padding:5px 12px;" onclick="LMSDownload.single('assignment',${f.id})">
          <i data-lucide="download" style="width:13px;height:13px;"></i> Download
        </button>
      </div>`;
      }).join('') + '</div>';
      // Override download to use assignment_files zip type
      window._fmZipType = 'assignment_files';
      if (window.lucide) lucide.createIcons({
        nodes: [body]
      });
    }, 250);
  }

  // Override fmDownloadAll/Selected when type is assignment_files
  window._fmZipType = 'topic_files';

  const _origFmDownloadAll = fmDownloadAll,
    _origFmDownloadSelected = fmDownloadSelected;
  window.fmDownloadAll = function() {
    if (!_fmAllFileIds.length) {
      Toast.warning('No files');
      return;
    }
    if (_fmAllFileIds.length === 1) {
      LMSDownload.single(window._fmZipType === 'assignment_files' ? 'assignment' : 'topic', _fmAllFileIds[0]);
      return;
    }
    LMSDownload.zip(window._fmZipType, _fmAllFileIds);
  };
  window.fmDownloadSelected = function() {
    if (!fmSelectedIds.size) {
      Toast.warning('Select at least one file');
      return;
    }
    LMSDownload.zip(window._fmZipType, [...fmSelectedIds]);
  };

  // Reset zip type when main files modal opens
  document.querySelector('[data-modal-close="files-modal"]')?.addEventListener('click', () => {
    window._fmZipType = 'topic_files';
  });

  /* ── Filters ── */
  const dbl = debounce(() => {
    tpPage = 1;
    loadTopics();
  }, 300);
  document.getElementById('topic-search').addEventListener('input', e => {
    tpSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-batch').addEventListener('change', e => {
    tpBatch = e.target.value;
    tpPage = 1;
    loadTopics();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  document.addEventListener('DOMContentLoaded', loadTopics);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>