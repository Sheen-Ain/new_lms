<?php
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Assignments';
$breadcrumbs = [['label' => 'Teacher'], ['label' => 'Assignments']];

$uid = (int)$currentUser['id'];

$batches = [];

$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.name,
        c.title AS ct
    FROM batch_teachers bt
    JOIN batches b ON bt.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE bt.teacher_id = ?
    ORDER BY b.name
");

$stmt->bind_param('i', $uid);
$stmt->execute();

/* FIX: bind results instead of get_result() */
$stmt->bind_result($batchId, $batchName, $courseTitle);

while ($stmt->fetch()) {
  $batches[] = [
    'id'   => $batchId,
    'name' => $batchName,
    'ct'   => $courseTitle
  ];
}

$stmt->close();

/* Prefilter */
$prefilterBatch = (int)($_GET['batch'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>
<style>
  .flatpickr-calendar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
    border-radius: var(--radius);
  }

  .flatpickr-day {
    color: var(--text-primary);
  }

  .flatpickr-day.selected,
  .flatpickr-day.selected:hover {
    background: var(--primary);
    border-color: var(--primary);
  }

  .flatpickr-day:hover {
    background: var(--bg-hover);
  }

  .flatpickr-months .flatpickr-month,
  .flatpickr-weekdays,
  .flatpickr-time {
    background: var(--bg-card);
    color: var(--text-primary);
  }

  .flatpickr-current-month input.cur-year,
  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .numInput,
  .numInputWrapper span,
  .flatpickr-time input,
  .flatpickr-time .flatpickr-am-pm {
    color: var(--text-primary);
  }
</style>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Assignments</h1>
          <p class="page-subtitle">Create and manage assignments for your batches</p>
        </div>
        <button class="btn btn-primary" onclick="openAdd()"><i data-lucide="plus" style="width:16px;height:16px;"></i> New Assignment</button>
      </div>

      <div class="bulk-action-bar" id="bulk-action-bar">
        <span class="bulk-action-count" id="bulk-count">0 selected</span>
        <span class="bulk-action-sep">|</span>
        <button class="bulk-btn bulk-btn-danger" onclick="bulkDeleteAssignments()">🗑 Delete Selected</button>
      </div>

      <div class="card">
        <div class="table-controls">
          <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="a-search" class="form-control" placeholder="Search assignments…"></div>
          <select id="filter-batch" class="form-control" style="width:200px;">
            <option value="">All My Batches</option>
            <?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>" <?= $prefilterBatch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
          </select>
          <select id="filter-status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div style="overflow-x:auto;">
          <table id="a-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Title</th>
                <th>Batch</th>
                <th>Marks</th>
                <th>Due Date</th>
                <th>Files</th>
                <th>Submissions</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="a-tbody">
              <?php for ($i = 0; $i < 4; $i++): ?><tr>
                  <td colspan="9">
                    <div class="skeleton skeleton-text" style="margin:10px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="a-pagination"></div>
      </div>
    </main>
  </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="add-a-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="clipboard-list" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="a-modal-title">New Assignment</h3>
      <button class="modal-close" data-modal-close="add-a"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="a-form" onsubmit="submitA(event)">
      <input type="hidden" name="action" id="a-action" value="create">
      <input type="hidden" name="assignment_id" id="a-id">
      <input type="hidden" name="due_date" id="a-due-hidden">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Title <span class="required">*</span></label>
          <input type="text" name="title" id="a-title" class="form-control" required placeholder="Assignment title…">
        </div>
        <div class="form-group"><label class="form-label">Description</label>
          <textarea name="description" id="a-desc" class="form-control" rows="3" placeholder="Describe the assignment…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Batch <span class="required">*</span></label>
            <select name="batch_id" id="a-batch" class="form-control" required onchange="loadTopicOpts(this.value)">
              <option value="">Select Batch</option>
              <?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Topic (optional)</label>
            <select name="topic_id" id="a-topic" class="form-control">
              <option value="">None</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Total Marks</label>
            <input type="number" name="total_marks" id="a-marks" class="form-control" value="100" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Due Date &amp; Time</label>
            <input type="text" id="a-due-display" class="form-control" placeholder="Select date &amp; time…" autocomplete="off" readonly style="background:var(--bg-card);cursor:pointer;">
            <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
              <button type="button" class="btn btn-ghost btn-sm" onclick="clearDueDate()" style="font-size:0.75rem;padding:3px 8px;">✕ Clear</button>
              <span id="a-due-preview" style="font-size:0.78rem;color:var(--text-muted);"></span>
            </div>
          </div>
          <div class="form-group"><label class="form-label">Status</label>
            <select name="status" id="a-status" class="form-control">
              <option value="active">Active</option>
              <option value="draft">Draft</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="margin-bottom:8px;">Allow Late Submissions</label>
            <label class="toggle-wrapper" style="margin-top:6px;">
              <div class="toggle">
                <input type="checkbox" name="allow_late" id="a-late" value="1">
                <span class="toggle-slider"></span>
              </div><span style="font-size:0.875rem;color:var(--text-secondary);margin-left:8px;">Allow late</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-a">Cancel</button>
        <button type="submit" class="btn btn-primary" id="a-submit"><span class="btn-text">Save Assignment</span></button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGNMENT FILES MODAL -->
<div class="modal-overlay" id="af-modal-overlay">
  <div class="modal modal-lg" style="max-width:720px;">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="paperclip" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="af-title">Assignment Files</h3>
      <button class="modal-close" data-modal-close="af-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="af-assign-id">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
          <input type="checkbox" id="af-select-all" style="width:15px;height:15px;accent-color:var(--primary);" onchange="afToggleAll(this.checked)"> Select All
        </label>
        <div style="flex:1;"></div>
        <button class="btn btn-secondary btn-sm" id="af-dl-all-btn" onclick="afDownloadAll()"><i data-lucide="download-cloud" style="width:13px;height:13px;"></i> Download All</button>
        <button class="btn btn-primary btn-sm" id="af-dl-sel-btn" style="display:none;" onclick="afDownloadSelected()"><i data-lucide="archive" style="width:13px;height:13px;"></i> Download Selected (<span id="af-sel-count">0</span>)</button>
        <button class="btn btn-danger btn-sm" id="af-del-sel-btn" style="display:none;" onclick="afDeleteSelected()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
      </div>
      <div id="af-drop-zone" class="file-drop-zone" style="margin-bottom:12px;">
        <div class="file-drop-zone-icon">📎</div>
        <div class="file-drop-zone-text">Upload reference files for students<br>
          <small style="color:var(--text-muted);">PDF, DOC, ZIP, Images, PHP, JS, HTML, SQL, CSV — max 20MB</small>
        </div>
      </div>
      <input type="file" id="af-file-input" multiple style="display:none;" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.jpg,.jpeg,.png,.txt,.php,.js,.html,.sql,.csv">
      <div id="af-upload-progress" style="display:none;margin-bottom:10px;">
        <div class="progress">
          <div class="progress-bar" id="af-upload-bar" style="width:0%;"></div>
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;" id="af-upload-text"></div>
      </div>
      <div id="af-files-list"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="af-modal">Close</button></div>
  </div>
</div>

<script>
  let aPage = 1,
    aSearch = '',
    aBatch = '<?= $prefilterBatch ?>',
    aStatus = '';
  let afSelectedIds = new Set(),
    _afAllFileIds = [];
  let _duePicker = null;

  /* ── SVG file icon ── */
  function fileIcon(fname) {
    const ext = (fname || '').split('.').pop().toLowerCase();
    const m = {
      pdf: ['#DC2626', 'PDF'],
      doc: ['#1D4ED8', 'DOC'],
      docx: ['#1D4ED8', 'DOC'],
      xls: ['#15803D', 'XLS'],
      xlsx: ['#15803D', 'XLS'],
      ppt: ['#C2410C', 'PPT'],
      pptx: ['#C2410C', 'PPT'],
      zip: ['#D97706', 'ZIP'],
      rar: ['#D97706', 'RAR'],
      jpg: ['#0369A1', 'IMG'],
      jpeg: ['#0369A1', 'IMG'],
      png: ['#0369A1', 'PNG'],
      gif: ['#0369A1', 'GIF'],
      mp4: ['#7C3AED', 'MP4'],
      mp3: ['#DB2777', 'MP3'],
      txt: ['#475569', 'TXT'],
      php: ['#6D28D9', 'PHP'],
      js: ['#92400E', 'JS'],
      html: ['#9A3412', 'HTML'],
      sql: ['#075985', 'SQL'],
      csv: ['#14532D', 'CSV']
    };
    const [bg, lbl] = m[ext] || ['#475569', 'FILE'];
    const fs = lbl.length > 3 ? 7 : lbl.length === 3 ? 9 : 10;
    return `<svg width="38" height="38" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><rect width="40" height="40" rx="8" fill="${bg}"/><rect width="40" height="40" rx="8" fill="rgba(0,0,0,0.18)"/><text x="20" y="${lbl.length>3?24:25}" text-anchor="middle" fill="#fff" font-family="system-ui,sans-serif" font-weight="800" font-size="${fs}" letter-spacing="0.3">${lbl}</text></svg>`;
  }

  /* ── Flatpickr init ── */
  document.addEventListener('DOMContentLoaded', () => {
    _duePicker = flatpickr('#a-due-display', {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      time_24hr: true,
      minuteIncrement: 1,
      allowInput: false,
      onChange: (sel, dateStr) => {
        document.getElementById('a-due-hidden').value = dateStr || '';
        document.getElementById('a-due-preview').textContent = sel[0] ? '📅 ' + new Date(sel[0]).toLocaleString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '';
      }
    });
    loadAssignments();
  });

  function clearDueDate() {
    if (_duePicker) _duePicker.clear();
    document.getElementById('a-due-hidden').value = '';
    document.getElementById('a-due-preview').textContent = '';
  }

  /* ── Assignments table ── */
  async function loadAssignments() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
        action: 'list',
        page: aPage,
        per_page: 15,
        search: aSearch,
        batch_id: aBatch,
        status: aStatus
      });
      if (res.status === 'success') {
        renderA(res.data.assignments);
        renderPag(res.data.total, res.data.page, res.data.per_page);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function renderA(items) {
    const tb = document.getElementById('a-tbody');
    if (!items?.length) {
      tb.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon">📝</div><div class="empty-state-title">No assignments yet</div><button class="btn btn-primary" style="margin-top:10px;" onclick="openAdd()">New Assignment</button></div></td></tr>`;
      return;
    }
    const now = Date.now();
    tb.innerHTML = items.map(a => {
      const diff = a.due_date ? new Date(a.due_date.replace(' ', 'T')) - now : null;
      let due = '<span style="color:var(--text-muted);">No due date</span>';
      if (a.due_date) {
        if (diff < 0) due = `<span style="color:var(--danger);font-weight:600;">Overdue</span>`;
        else if (diff < 86400000) due = `<span style="color:var(--warning);">Today</span>`;
        else due = new Date(a.due_date.replace(' ', 'T')).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        });
      }
      return `<tr>
      <td><input type="checkbox" class="row-cb" value="${a.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="font-weight:600;font-size:0.875rem;">${escapeHtml(a.title)}</div>${a.description?`<div style="font-size:0.72rem;color:var(--text-muted);">${escapeHtml(a.description.slice(0,55))}</div>`:''}</td>
      <td style="font-size:0.82rem;color:var(--text-muted);">${escapeHtml(a.batch_name||'—')}</td>
      <td><span class="badge badge-info">${a.total_marks} pts</span></td>
      <td style="font-size:0.82rem;">${due}</td>
      <td><button class="btn btn-secondary btn-sm" onclick="openAFiles(${a.id},'${escapeHtml(a.title).replace(/'/g,"\\'")}')">
        <i data-lucide="paperclip" style="width:12px;height:12px;"></i> ${a.file_count||0}
      </button></td>
      <td><a href="<?= BASE_PATH ?>/teacher/submissions.php?assignment=${a.id}" class="badge badge-info" style="text-decoration:none;cursor:pointer;">${a.submission_count||0} subs</a></td>
      <td><span class="badge ${a.status==='active'?'badge-success':a.status==='draft'?'badge-secondary':'badge-warning'}" style="cursor:pointer;"
        onclick="toggleAStatus(${a.id},'${a.status==='active'?'inactive':'active'}')">${a.status}</span></td>
      <td><div class="table-actions" style="justify-content:flex-end;">
        <button class="action-btn action-btn-edit" onclick="editA(${a.id})"><i data-lucide="edit-2" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-delete" onclick="deleteA(${a.id},'${escapeHtml(a.title).replace(/'/g,"\\'")}')"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('a-tbody')]
    });
    BulkSelect.init('a-table');
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('a-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="aPage=${i};loadAssignments();return false;">${i}</a>`;
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="aPage=${page-1};loadAssignments();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="aPage=${page+1};loadAssignments();return false;">›</a></div></div>`;
  }

  async function toggleAStatus(id, status) {
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'toggle_status',
      assignment_id: id,
      status
    });
    if (res.status === 'success') {
      Toast.success('Status updated');
      loadAssignments();
    } else Toast.error(res.message);
  }

  function openAdd() {
    document.getElementById('a-modal-title').textContent = 'New Assignment';
    document.getElementById('a-action').value = 'create';
    document.getElementById('a-id').value = '';
    document.getElementById('a-form').reset();
    document.getElementById('a-due-hidden').value = '';
    document.getElementById('a-due-preview').textContent = '';
    if (_duePicker) _duePicker.clear();
    document.getElementById('a-topic').innerHTML = '<option value="">None</option>';
    Modal.open('add-a');
  }

  async function editA(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'get_one',
      assignment_id: id
    });
    if (res.status !== 'success') {
      Toast.error(res.message);
      return;
    }
    const a = res.data;
    document.getElementById('a-modal-title').textContent = 'Edit Assignment';
    document.getElementById('a-action').value = 'update';
    document.getElementById('a-id').value = a.id;
    document.getElementById('a-title').value = a.title || '';
    document.getElementById('a-desc').value = a.description || '';
    document.getElementById('a-batch').value = a.batch_id || '';
    document.getElementById('a-marks').value = a.total_marks || 100;
    document.getElementById('a-status').value = a.status || 'active';
    document.getElementById('a-late').checked = a.allow_late == 1;
    if (a.due_date && _duePicker) {
      _duePicker.setDate(new Date(a.due_date.replace(' ', 'T')), true);
      document.getElementById('a-due-hidden').value = a.due_date;
    } else {
      if (_duePicker) _duePicker.clear();
      document.getElementById('a-due-hidden').value = '';
      document.getElementById('a-due-preview').textContent = '';
    }
    await loadTopicOpts(a.batch_id);
    document.getElementById('a-topic').value = a.topic_id || '';
    Modal.open('add-a');
  }

  async function loadTopicOpts(batchId) {
    const sel = document.getElementById('a-topic');
    sel.innerHTML = '<option value="">None</option>';
    if (!batchId) return;
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'list_simple',
      batch_id: batchId
    });
    if (res.status === 'success') res.data.forEach(t => sel.innerHTML += `<option value="${t.id}">${escapeHtml(t.title)}</option>`);
  }

  async function submitA(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('a-submit');
    const data = {
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    data.allow_late = form.querySelector('[name=allow_late]')?.checked ? 1 : 0;
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', data);
      if (res.status === 'success') {
        Toast.success(res.message);
        Modal.close('add-a');
        loadAssignments();
      } else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  function deleteA(id, title) {
    Modal.confirm({
      title: 'Delete Assignment',
      message: `Delete <strong>${escapeHtml(title)}</strong> and all related data?`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
          action: 'delete',
          assignment_id: id
        });
        if (res.status === 'success') {
          Toast.success('Deleted');
          loadAssignments();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDeleteAssignments() {
    const ids = BulkSelect.getSelected('a-table');
    if (!ids.length) {
      Toast.warning('No assignments selected');
      return;
    }
    Modal.confirm({
      title: 'Delete Assignments',
      message: `Delete <strong>${ids.length}</strong> assignment(s)?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          Toast.success(res.message);
          BulkSelect.reset('a-table');
          loadAssignments();
        } else throw new Error(res.message);
      }
    });
  }

  /* ── Assignment File Manager ── */
  async function openAFiles(assignId, title) {
    document.getElementById('af-assign-id').value = assignId;
    document.getElementById('af-title').textContent = 'Files: ' + title;
    afSelectedIds = new Set();
    _afAllFileIds = [];
    afUpdateBulkBar();
    Modal.open('af-modal');
    await loadAFiles(assignId);
  }

  async function loadAFiles(assignId) {
    const list = document.getElementById('af-files-list');
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'get_one',
      assignment_id: assignId
    });
    if (res.status !== 'success') {
      list.innerHTML = '<div style="color:var(--danger);padding:16px;">Error</div>';
      return;
    }
    const files = res.data.files || [];
    _afAllFileIds = files.map(f => String(f.id));
    document.getElementById('af-select-all').checked = false;
    afUpdateBulkBar();
    if (!files.length) {
      list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">No files yet — upload above</div>';
      return;
    }
    list.innerHTML = '<div style="display:flex;flex-direction:column;gap:6px;">' + files.map(f => {
      const sz = f.file_size > 1048576 ? (f.file_size / 1048576).toFixed(1) + ' MB' : (f.file_size >> 10) + ' KB';
      const active = f.status !== 'inactive';
      return `<div class="af-row" id="afrow-${f.id}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);border:1.5px solid var(--border);background:var(--bg);opacity:${active?1:0.6};transition:border-color .15s,opacity .15s;">
      <input type="checkbox" class="af-cb" value="${f.id}" style="width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;" onchange="afToggleCb('${f.id}',this.checked)">
      <div style="flex-shrink:0;">${fileIcon(f.file_name)}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:0.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>
        <div style="font-size:0.72rem;color:var(--text-muted);">${sz}</div>
      </div>
      <span class="badge ${active?'badge-success':'badge-warning'}" style="cursor:pointer;flex-shrink:0;font-size:0.68rem;"
        onclick="afToggleStatus(${f.id},'${active?'inactive':'active'}')">${active?'Active':'Inactive'}</span>
      <button class="btn btn-secondary btn-sm lms-view-btn" style="flex-shrink:0;padding:4px 10px;"
        data-fid="${f.id}" data-ftype="assignment" data-fname="${escapeHtml(f.file_name)}" title="View">
        <i data-lucide="eye" style="width:13px;height:13px;"></i></button>
      <button class="btn btn-primary btn-sm" style="flex-shrink:0;padding:4px 10px;" onclick="LMSDownload.single('assignment',${f.id})"><i data-lucide="download" style="width:13px;height:13px;"></i></button>
      <button class="btn btn-danger btn-sm" style="flex-shrink:0;padding:4px 10px;" onclick="afDeleteOne(${f.id})"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
    </div>`;
    }).join('') + '</div>';
    if (window.lucide) lucide.createIcons({
      nodes: [list]
    });
  }

  function afToggleCb(id, checked) {
    if (checked) afSelectedIds.add(String(id));
    else afSelectedIds.delete(String(id));
    afUpdateBulkBar();
    const row = document.getElementById('afrow-' + id);
    if (row) row.style.borderColor = checked ? 'var(--primary)' : 'var(--border)';
    document.getElementById('af-select-all').checked = [...document.querySelectorAll('.af-cb')].every(c => c.checked);
  }

  function afToggleAll(checked) {
    document.querySelectorAll('.af-cb').forEach(c => {
      c.checked = checked;
      afToggleCb(c.value, checked);
    });
  }

  function afUpdateBulkBar() {
    const n = afSelectedIds.size;
    document.getElementById('af-sel-count').textContent = n;
    document.getElementById('af-dl-sel-btn').style.display = n > 0 ? '' : 'none';
    document.getElementById('af-del-sel-btn').style.display = n > 0 ? '' : 'none';
    document.getElementById('af-dl-all-btn').style.display = _afAllFileIds.length > 0 ? '' : 'none';
  }
  async function afToggleStatus(id, status) {
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'toggle_file_status',
      file_id: id,
      status
    });
    if (res.status === 'success') {
      Toast.success('Updated');
      await loadAFiles(document.getElementById('af-assign-id').value);
    } else Toast.error(res.message);
  }
  async function afDeleteOne(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'delete_file',
      file_id: id
    });
    if (res.status === 'success') {
      afSelectedIds.delete(String(id));
      afUpdateBulkBar();
      Toast.success('Deleted');
      await loadAFiles(document.getElementById('af-assign-id').value);
    } else Toast.error(res.message);
  }
  async function afDeleteSelected() {
    if (!afSelectedIds.size) {
      Toast.warning('None selected');
      return;
    }
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'bulk_delete_files',
      file_ids: [...afSelectedIds].join(',')
    });
    if (res.status === 'success') {
      Toast.success(res.message);
      afSelectedIds = new Set();
      afUpdateBulkBar();
      await loadAFiles(document.getElementById('af-assign-id').value);
    } else Toast.error(res.message);
  }

  function afDownloadSelected() {
    if (!afSelectedIds.size) {
      Toast.warning('None selected');
      return;
    }
    LMSDownload.zip('assignment_files', [...afSelectedIds]);
  }

  function afDownloadAll() {
    if (!_afAllFileIds.length) {
      Toast.warning('No files');
      return;
    }
    LMSDownload.zip('assignment_files', _afAllFileIds);
  }

  /* ── Assignment file upload ── */
  const afdz = document.getElementById('af-drop-zone'),
    affi = document.getElementById('af-file-input');
  afdz.addEventListener('click', () => affi.click());
  afdz.addEventListener('dragover', e => {
    e.preventDefault();
    afdz.classList.add('dragover');
  });
  afdz.addEventListener('dragleave', () => afdz.classList.remove('dragover'));
  afdz.addEventListener('drop', e => {
    e.preventDefault();
    afdz.classList.remove('dragover');
    if (e.dataTransfer.files.length) uploadAFiles(e.dataTransfer.files);
  });
  affi.addEventListener('change', e => {
    if (e.target.files.length) uploadAFiles(e.target.files);
  });

  async function uploadAFiles(fileList) {
    const assignId = document.getElementById('af-assign-id').value;
    if (!assignId) return;
    const prog = document.getElementById('af-upload-progress'),
      bar = document.getElementById('af-upload-bar'),
      txt = document.getElementById('af-upload-text');
    prog.style.display = 'block';
    let uploaded = 0,
      failed = 0;
    for (const file of fileList) {
      txt.textContent = `Uploading ${file.name}…`;
      const fd = new FormData();
      fd.append('action', 'upload_file');
      fd.append('assignment_id', assignId);
      fd.append('file', file);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      try {
        const res = await fetch(window.LMS_BASE + '/ajax/assignments.ajax.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          const t = await res.text();
          Toast.error('Server error: ' + t.replace(/<[^>]+>/g, '').trim().slice(0, 100));
          failed++;
          continue;
        }
        const data = await res.json();
        if (data.status === 'success') {
          uploaded++;
        } else {
          Toast.error(data.message || 'Upload failed');
          failed++;
        }
      } catch (err) {
        Toast.error('Upload failed: ' + err.message);
        failed++;
      }
      bar.style.width = Math.round(((uploaded + failed) / fileList.length) * 100) + '%';
    }
    prog.style.display = 'none';
    bar.style.width = '0%';
    affi.value = '';
    if (uploaded) {
      Toast.success(uploaded + ' file(s) uploaded');
      await loadAFiles(assignId);
    }
  }

  /* ── Filters ── */
  const dbl = debounce(() => {
    aPage = 1;
    loadAssignments();
  }, 300);
  document.getElementById('a-search').addEventListener('input', e => {
    aSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-batch').addEventListener('change', e => {
    aBatch = e.target.value;
    aPage = 1;
    loadAssignments();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    aStatus = e.target.value;
    aPage = 1;
    loadAssignments();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>