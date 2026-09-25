<?php
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Topics';
$breadcrumbs = [['label' => 'Teacher'], ['label' => 'Topics']];

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

/* FIX: use bind_result instead of get_result */
$stmt->bind_result($batchId, $batchName, $courseTitle);

while ($stmt->fetch()) {
  $batches[] = [
    'id'   => $batchId,
    'name' => $batchName,
    'ct'   => $courseTitle
  ];
}

$stmt->close();

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
          <h1 class="page-title">Topics</h1>
          <p class="page-subtitle">Manage learning materials for your batches</p>
        </div>
        <button class="btn btn-primary" onclick="openAdd()"><i data-lucide="plus" style="width:16px;height:16px;"></i> New Topic</button>
      </div>

      <!-- Bulk action bar -->
      <div class="bulk-action-bar" id="bulk-action-bar">
        <span class="bulk-action-count" id="bulk-count">0 selected</span>
        <span class="bulk-action-sep">|</span>
        <button class="bulk-btn bulk-btn-danger" onclick="bulkDeleteTopics()">🗑 Delete Selected</button>
      </div>

      <div class="card">
        <div class="table-controls">
          <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="t-search" class="form-control" placeholder="Search topics…"></div>
          <select id="filter-batch" class="form-control" style="width:210px;">
            <option value="">All My Batches</option>
            <?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['ct'] . ' – ' . $b['name']) ?></option><?php endforeach; ?>
          </select>
          <select id="filter-status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div style="overflow-x:auto;">
          <table id="t-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Title</th>
                <th>Batch</th>
                <th>Sort</th>
                <th>Files</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="t-tbody">
              <?php for ($i = 0; $i < 4; $i++): ?><tr>
                  <td colspan="7">
                    <div class="skeleton skeleton-text" style="margin:10px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="t-pagination"></div>
      </div>
    </main>
  </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="add-t-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="file-text" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="t-modal-title">New Topic</h3>
      <button class="modal-close" data-modal-close="add-t"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="t-form" onsubmit="submitTopic(event)">
      <input type="hidden" name="action" id="t-action" value="create">
      <input type="hidden" name="topic_id" id="t-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Title <span class="required">*</span></label><input type="text" name="title" id="t-title" class="form-control" required placeholder="Topic title…"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="t-desc" class="form-control" rows="3" placeholder="Optional description…"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Batch</label>
            <select name="batch_id" id="t-batch" class="form-control">
              <option value="">🌐 Global</option>
              <?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="t-sort" class="form-control" value="0" min="0"></div>
          <div class="form-group"><label class="form-label">Status</label><select name="status" id="t-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-t">Cancel</button>
        <button type="submit" class="btn btn-primary" id="t-submit"><span class="btn-text">Save Topic</span></button>
      </div>
    </form>
  </div>
</div>

<!-- FILES MANAGER MODAL -->
<div class="modal-overlay" id="files-modal-overlay">
  <div class="modal modal-lg" style="max-width:720px;">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="paperclip" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="fm-title">Topic Files</h3>
      <button class="modal-close" data-modal-close="files-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="file-topic-id">
      <!-- Toolbar -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.82rem;font-weight:600;color:var(--text-secondary);">
          <input type="checkbox" id="fm-select-all" style="width:15px;height:15px;accent-color:var(--primary);" onchange="fmToggleAll(this.checked)"> Select All
        </label>
        <div style="flex:1;"></div>
        <button class="btn btn-secondary btn-sm" id="fm-dl-all-btn" onclick="fmDownloadAll()"><i data-lucide="download-cloud" style="width:13px;height:13px;"></i> Download All</button>
        <button class="btn btn-primary btn-sm" id="fm-dl-sel-btn" style="display:none;" onclick="fmDownloadSelected()"><i data-lucide="archive" style="width:13px;height:13px;"></i> Download Selected (<span id="fm-sel-count">0</span>)</button>
        <button class="btn btn-danger btn-sm" id="fm-del-sel-btn" style="display:none;" onclick="fmDeleteSelected()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
      </div>
      <!-- Upload zone -->
      <div id="file-drop-zone" class="file-drop-zone" style="margin-bottom:12px;">
        <div class="file-drop-zone-icon">📁</div>
        <div class="file-drop-zone-text">Drop files here or <strong>click to browse</strong><br>
          <small style="color:var(--text-muted);">PDF, DOC, PPT, XLS, ZIP, Images, PHP, JS, HTML, SQL, CSV — max 20MB</small>
        </div>
      </div>
      <input type="file" id="file-input" multiple style="display:none;" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png,.gif,.txt,.mp4,.mp3,.php,.js,.html,.sql,.csv">
      <div id="upload-progress" style="display:none;margin-bottom:10px;">
        <div class="progress">
          <div class="progress-bar" id="upload-bar" style="width:0%;"></div>
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;" id="upload-text">Uploading…</div>
      </div>
      <div id="files-list"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-modal-close="files-modal">Close</button></div>
  </div>
</div>

<script>
  let tPage = 1,
    tBatch = '',
    tStatus = '',
    tSearch = '';
  let fmSelectedIds = new Set(),
    _fmAllFileIds = [];

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

  /* ── Topics table ── */
  async function loadTopics() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
        action: 'list',
        page: tPage,
        per_page: 15,
        batch_id: tBatch,
        status: tStatus,
        search: tSearch
      });
      if (res.status === 'success') {
        renderT(res.data.topics);
        renderPag(res.data.total, res.data.page, res.data.per_page);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function renderT(items) {
    const tb = document.getElementById('t-tbody');
    if (!items?.length) {
      tb.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📄</div><div class="empty-state-title">No topics yet</div><button class="btn btn-primary" style="margin-top:10px" onclick="openAdd()">New Topic</button></div></td></tr>`;
      return;
    }
    tb.innerHTML = items.map(t => `<tr>
    <td><input type="checkbox" class="row-cb" value="${t.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
    <td><div style="font-weight:600;font-size:0.875rem;">${escapeHtml(t.title)}</div>${t.description?`<div style="font-size:0.72rem;color:var(--text-muted);">${escapeHtml(t.description.slice(0,60))}</div>`:''}</td>
    <td style="font-size:0.82rem;color:var(--text-muted);">${t.batch_id?escapeHtml(t.batch_name||'—'):'🌐 Global'}</td>
    <td><span class="badge badge-secondary">#${t.sort_order}</span></td>
    <td><button class="btn btn-secondary btn-sm" onclick="openFiles(${t.id},'${escapeHtml(t.title).replace(/'/g,"\\'")}')" >
      <i data-lucide="paperclip" style="width:12px;height:12px;"></i> ${t.file_count}
    </button></td>
    <td><span class="badge ${t.status==='active'?'badge-success':'badge-warning'}" style="cursor:pointer;"
      onclick="toggleStatus(${t.id},'${t.status==='active'?'inactive':'active'}')">${t.status}</span></td>
    <td><div class="table-actions" style="justify-content:flex-end;">
      <button class="action-btn action-btn-edit" onclick="editTopic(${t.id})"><i data-lucide="edit-2" style="width:13px;height:13px;"></i></button>
      <button class="action-btn action-btn-delete" onclick="deleteTopic(${t.id},'${escapeHtml(t.title).replace(/'/g,"\\'")}')"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
    </div></td>
  </tr>`).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('t-tbody')]
    });
    BulkSelect.init('t-table');
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('t-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="tPage=${i};loadTopics();return false;">${i}</a>`;
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="tPage=${page-1};loadTopics();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="tPage=${page+1};loadTopics();return false;">›</a></div></div>`;
  }

  async function toggleStatus(id, status) {
    const r = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'get_one',
      topic_id: id
    });
    if (r.status !== 'success') {
      Toast.error(r.message);
      return;
    }
    const t = r.data;
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'update',
      topic_id: id,
      title: t.title,
      description: t.description || '',
      batch_id: t.batch_id || '',
      sort_order: t.sort_order || 0,
      status
    });
    if (res.status === 'success') {
      Toast.success('Status updated');
      loadTopics();
    } else Toast.error(res.message);
  }

  function openAdd() {
    document.getElementById('t-modal-title').textContent = 'New Topic';
    document.getElementById('t-action').value = 'create';
    document.getElementById('t-id').value = '';
    document.getElementById('t-form').reset();
    Modal.open('add-t');
  }

  async function editTopic(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'get_one',
      topic_id: id
    });
    if (res.status !== 'success') {
      Toast.error(res.message);
      return;
    }
    const t = res.data;
    document.getElementById('t-modal-title').textContent = 'Edit Topic';
    document.getElementById('t-action').value = 'update';
    document.getElementById('t-id').value = t.id;
    document.getElementById('t-title').value = t.title || '';
    document.getElementById('t-desc').value = t.description || '';
    document.getElementById('t-batch').value = t.batch_id || '';
    document.getElementById('t-sort').value = t.sort_order || 0;
    document.getElementById('t-status').value = t.status || 'active';
    Modal.open('add-t');
  }

  async function submitTopic(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('t-submit');
    const data = {
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', data);
      if (res.status === 'success') {
        Toast.success(res.message);
        Modal.close('add-t');
        loadTopics();
      } else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  function deleteTopic(id, title) {
    Modal.confirm({
      title: 'Delete Topic',
      message: `Delete <strong>${escapeHtml(title)}</strong> and all its files?`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
          action: 'delete',
          topic_id: id
        });
        if (res.status === 'success') {
          Toast.success('Deleted');
          loadTopics();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDeleteTopics() {
    const ids = BulkSelect.getSelected('t-table');
    if (!ids.length) {
      Toast.warning('No topics selected');
      return;
    }
    Modal.confirm({
      title: 'Delete Topics',
      message: `Delete <strong>${ids.length}</strong> topic(s) and all their files?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          Toast.success(res.message);
          BulkSelect.reset('t-table');
          loadTopics();
        } else throw new Error(res.message);
      }
    });
  }

  /* ── File Manager ── */
  async function openFiles(topicId, title) {
    document.getElementById('file-topic-id').value = topicId;
    document.getElementById('fm-title').textContent = 'Files: ' + title;
    fmSelectedIds = new Set();
    _fmAllFileIds = [];
    fmUpdateBulkBar();
    Modal.open('files-modal');
    await loadFiles(topicId);
  }

  async function loadFiles(topicId) {
    const list = document.getElementById('files-list');
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'get_one',
      topic_id: topicId
    });
    if (res.status !== 'success') {
      list.innerHTML = '<div style="color:var(--danger);padding:16px;">Error loading files</div>';
      return;
    }
    const files = res.data.files || [];
    _fmAllFileIds = files.map(f => String(f.id));
    document.getElementById('fm-select-all').checked = false;
    fmUpdateBulkBar();
    if (!files.length) {
      list.innerHTML = '<div style="text-align:center;padding:24px;color:var(--text-muted);">No files yet — upload above</div>';
      return;
    }
    list.innerHTML = '<div style="display:flex;flex-direction:column;gap:6px;">' + files.map(f => {
      const sz = f.file_size > 1048576 ? (f.file_size / 1048576).toFixed(1) + ' MB' : (f.file_size >> 10) + ' KB';
      const active = f.status !== 'inactive';
      return `<div class="fm-row" id="fmrow-${f.id}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);border:1.5px solid var(--border);background:var(--bg);opacity:${active?1:0.6};transition:border-color .15s,opacity .15s;">
      <input type="checkbox" class="fm-cb" value="${f.id}" style="width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;" onchange="fmToggleCb('${f.id}',this.checked)">
      <div style="flex-shrink:0;">${fileIcon(f.file_name)}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:0.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(f.file_name)}</div>
        <div style="font-size:0.72rem;color:var(--text-muted);">${sz}</div>
      </div>
      <span class="badge ${active?'badge-success':'badge-warning'}" style="cursor:pointer;flex-shrink:0;font-size:0.68rem;"
        onclick="fmToggleStatus(${f.id},'${active?'inactive':'active'}')">${active?'Active':'Inactive'}</span>
      <button class="btn btn-secondary btn-sm lms-view-btn" style="flex-shrink:0;padding:4px 10px;"
        data-fid="${f.id}" data-ftype="topic" data-fname="${escapeHtml(f.file_name)}" title="View">
        <i data-lucide="eye" style="width:13px;height:13px;"></i></button>
      <button class="btn btn-primary btn-sm" style="flex-shrink:0;padding:4px 10px;" onclick="LMSDownload.single('topic',${f.id})"><i data-lucide="download" style="width:13px;height:13px;"></i></button>
      <button class="btn btn-danger btn-sm" style="flex-shrink:0;padding:4px 10px;" onclick="fmDeleteOne(${f.id})"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
    </div>`;
    }).join('') + '</div>';
    if (window.lucide) lucide.createIcons({
      nodes: [list]
    });
  }

  function fmToggleCb(id, checked) {
    if (checked) fmSelectedIds.add(String(id));
    else fmSelectedIds.delete(String(id));
    fmUpdateBulkBar();
    const row = document.getElementById('fmrow-' + id);
    if (row) row.style.borderColor = checked ? 'var(--primary)' : 'var(--border)';
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
    document.getElementById('fm-del-sel-btn').style.display = n > 0 ? '' : 'none';
    document.getElementById('fm-dl-all-btn').style.display = _fmAllFileIds.length > 0 ? '' : 'none';
  }
  async function fmToggleStatus(id, status) {
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'toggle_file_status',
      file_id: id,
      status
    });
    if (res.status === 'success') {
      Toast.success('Updated');
      await loadFiles(document.getElementById('file-topic-id').value);
    } else Toast.error(res.message);
  }
  async function fmDeleteOne(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'delete_file',
      file_id: id
    });
    if (res.status === 'success') {
      fmSelectedIds.delete(String(id));
      fmUpdateBulkBar();
      Toast.success('Deleted');
      await loadFiles(document.getElementById('file-topic-id').value);
    } else Toast.error(res.message);
  }
  async function fmDeleteSelected() {
    if (!fmSelectedIds.size) {
      Toast.warning('None selected');
      return;
    }
    const res = await ajax(window.LMS_BASE + '/ajax/topics.ajax.php', {
      action: 'bulk_delete_files',
      file_ids: [...fmSelectedIds].join(',')
    });
    if (res.status === 'success') {
      Toast.success(res.message);
      fmSelectedIds = new Set();
      fmUpdateBulkBar();
      await loadFiles(document.getElementById('file-topic-id').value);
    } else Toast.error(res.message);
  }

  function fmDownloadSelected() {
    if (!fmSelectedIds.size) {
      Toast.warning('None selected');
      return;
    }
    LMSDownload.zip('topic_files', [...fmSelectedIds]);
  }

  function fmDownloadAll() {
    if (!_fmAllFileIds.length) {
      Toast.warning('No files');
      return;
    }
    LMSDownload.zip('topic_files', _fmAllFileIds);
  }

  /* ── Upload ── */
  const dz = document.getElementById('file-drop-zone'),
    fi = document.getElementById('file-input');
  dz.addEventListener('click', () => fi.click());
  dz.addEventListener('dragover', e => {
    e.preventDefault();
    dz.classList.add('dragover');
  });
  dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
  dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('dragover');
    if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
  });
  fi.addEventListener('change', e => {
    if (e.target.files.length) uploadFiles(e.target.files);
  });

  async function uploadFiles(fileList) {
    const topicId = document.getElementById('file-topic-id').value;
    if (!topicId) return;
    const prog = document.getElementById('upload-progress'),
      bar = document.getElementById('upload-bar'),
      txt = document.getElementById('upload-text');
    prog.style.display = 'block';
    let uploaded = 0,
      failed = 0;
    for (const file of fileList) {
      txt.textContent = `Uploading ${file.name}…`;
      const fd = new FormData();
      fd.append('action', 'upload_file');
      fd.append('topic_id', topicId);
      fd.append('file', file);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      try {
        const res = await fetch(window.LMS_BASE + '/ajax/topics.ajax.php', {
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
    fi.value = '';
    if (uploaded) {
      Toast.success(uploaded + ' file(s) uploaded');
      await loadFiles(topicId);
    }
  }

  /* ── Filters ── */
  const dbl = debounce(() => {
    tPage = 1;
    loadTopics();
  }, 300);
  document.getElementById('t-search').addEventListener('input', e => {
    tSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-batch').addEventListener('change', e => {
    tBatch = e.target.value;
    tPage = 1;
    loadTopics();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    tStatus = e.target.value;
    tPage = 1;
    loadTopics();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  document.addEventListener('DOMContentLoaded', loadTopics);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>