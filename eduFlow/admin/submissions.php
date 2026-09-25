<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Submissions';
$breadcrumbs = [['label' => 'Admin'], ['label' => 'Submissions']];
$batches = [];
$r = $conn->query("SELECT b.id,b.name FROM batches b WHERE b.status='active' ORDER BY b.name");
while ($row = $r->fetch_assoc()) $batches[] = $row;
include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Submissions</h1>
          <p class="page-subtitle">Grade and manage all student submissions</p>
        </div>
        <div id="pending-wrap" style="display:none;"><span class="badge badge-warning badge-pulse" id="pending-badge"></span></div>
      </div>
      <div class="bulk-action-bar" id="bulk-action-bar">
        <span class="bulk-action-count" id="bulk-count">0 selected</span>
        <span class="bulk-action-sep">|</span>
        <button class="bulk-btn" onclick="bulkDownloadSubs()">⬇ Download ZIP</button>
        <button class="bulk-btn bulk-btn-danger" onclick="bulkDeleteSubs()">🗑 Delete Selected</button>
      </div>
      <div class="card">
        <div class="table-controls">
          <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="s-search" class="form-control" placeholder="Search student or assignment…"></div>
          <select id="filter-batch" class="form-control" style="width:160px;">
            <option value="">All Batches</option><?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
          </select>
          <select id="filter-assign" class="form-control" style="width:200px;">
            <option value="">All Assignments</option>
          </select>
          <select id="filter-status" class="form-control" style="width:155px;">
            <option value="submitted">⏳ Needs Grading</option>
            <option value="">All Status</option>
            <option value="graded">✅ Graded</option>
            <option value="returned">↩️ Returned</option>
          </select>
          <select id="per-page" class="form-control" style="width:110px;">
            <option value="15">15 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
          </select>
        </div>
        <div style="overflow-x:auto;">
          <table id="s-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Student</th>
                <th>Assignment</th>
                <th>Batch</th>
                <th>Submitted</th>
                <th>File</th>
                <th>Marks</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="s-tbody">
              <?php for ($i = 0; $i < 5; $i++): ?><tr>
                  <td colspan="9">
                    <div class="skeleton skeleton-text" style="margin:10px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="s-pagination"></div>
      </div>
    </main>
  </div>
</div>

<!-- GRADE MODAL -->
<div class="modal-overlay" id="grade-modal-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-success"><i data-lucide="check-square" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Grade Submission</h3>
      <button class="modal-close" data-modal-close="grade-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="grade-form" onsubmit="submitGrade(event)">
      <input type="hidden" name="submission_id" id="g-id">
      <div class="modal-body">
        <div id="g-info" style="padding:16px;background:var(--bg);border-radius:var(--radius);margin-bottom:20px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Marks <span class="required">*</span></label>
            <div style="display:flex;align-items:center;gap:8px;"><input type="number" name="marks" id="g-marks" class="form-control" min="0" step="0.01" placeholder="e.g. 8.5" required oninput="validateMarks(this)"><span style="color:var(--text-muted);white-space:nowrap;">/ <span id="g-total">100</span></span></div>
            <div id="g-marks-err" style="font-size:0.72rem;color:var(--danger);margin-top:3px;display:none;"></div>
          </div>
          <div class="form-group"><label class="form-label">Action</label>
            <select name="status" id="g-status" class="form-control">
              <option value="graded">✅ Mark as Graded</option>
              <option value="returned">↩️ Return to Student</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Feedback</label><textarea name="feedback" id="g-feedback" class="form-control" rows="4" placeholder="Detailed feedback for the student…"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="grade-modal">Cancel</button>
        <button type="submit" class="btn btn-success" id="g-btn"><i data-lucide="check" style="width:15px;height:15px;"></i><span class="btn-text"> Save Grade</span></button>
      </div>
    </form>
  </div>
</div>

<script>
  let sPage = 1,
    sPerPage = 15,
    sSearch = '',
    sBatch = '',
    sStatus = 'submitted',
    sAssign = '';

  async function loadBatchAssignments(batchId) {
    const sel = document.getElementById('filter-assign');
    sel.innerHTML = '<option value="">All Assignments</option>';
    if (!batchId) return;
    const res = await ajax(window.LMS_BASE + '/ajax/assignments.ajax.php', {
      action: 'list_simple',
      batch_id: batchId
    });
    if (res.status === 'success') res.data.forEach(a => sel.innerHTML += `<option value="${a.id}">${escapeHtml(a.title)}</option>`);
  }

  async function loadSubs() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', {
        action: 'list',
        page: sPage,
        per_page: sPerPage,
        search: sSearch,
        batch_id: sBatch,
        status: sStatus,
        assignment_id: sAssign
      });
      if (res.status === 'success') {
        renderSubs(res.data.submissions);
        renderPag(res.data.total, res.data.page, res.data.per_page);
        updatePendingBadge(res.data.total);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function updatePendingBadge(total) {
    const wrap = document.getElementById('pending-wrap'),
      badge = document.getElementById('pending-badge');
    if (sStatus === 'submitted' && total > 0) {
      wrap.style.display = 'block';
      badge.textContent = total + ' pending';
    } else wrap.style.display = 'none';
  }

  function renderSubs(subs) {
    const tb = document.getElementById('s-tbody');
    if (!subs || !subs.length) {
      tb.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon">${sStatus==='submitted'?'🎉':'📬'}</div><div class="empty-state-title">${sStatus==='submitted'?'All caught up!':'No submissions found'}</div></div></td></tr>`;
      return;
    }
    tb.innerHTML = subs.map(s => {
      const stCls = s.status === 'graded' ? 'badge-success' : s.status === 'returned' ? 'badge-warning' : 'badge-info';
      const marks = s.marks != null ? `<strong>${s.marks}</strong><span style="color:var(--text-muted);">/${s.total_marks||100}</span>` : '—';
      return `<tr>
      <td><input type="checkbox" class="row-cb" value="${s.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="font-weight:600;font-size:0.875rem;">${escapeHtml(s.student_name)}</div></td>
      <td style="font-size:0.82rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(s.assignment_title||'—')}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${escapeHtml(s.batch_name||'—')}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${s.submitted_at?timeAgoJS(s.submitted_at):'—'}</td>
      <td>${s.file_path?`<div style="display:flex;gap:4px;">
        <button class="btn btn-secondary btn-sm lms-view-btn" style="padding:4px 8px;"
          data-fid="${s.id}" data-ftype="submission" data-fname="${escapeHtml(s.file_name||'submission')}" title="Preview">
          <i data-lucide="eye" style="width:12px;height:12px;"></i></button>
        <a href="${window.LMS_BASE}/download.php?type=submission&id=${s.id}" class="btn btn-ghost btn-sm" style="padding:4px 8px;" download="${escapeHtml(s.file_name||'submission')}" title="Download">
          <i data-lucide="download" style="width:12px;height:12px;"></i></a>
      </div>`:'<span style="color:var(--text-muted);">—</span>'}</td>
      <td style="font-size:0.875rem;">${marks}</td>
      <td><span class="badge ${stCls}">${s.status}</span></td>
      <td><div class="table-actions" style="justify-content:flex-end;">
        <button class="action-btn" style="background:rgba(16,185,129,0.1);color:#059669;" onclick="openGrade(${s.id})" data-tooltip="Grade"><i data-lucide="${s.status==='graded'?'edit-3':'check-square'}" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-delete" onclick="deleteSub(${s.id})" data-tooltip="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('s-tbody')]
    });
    BulkSelect.init('s-table');
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('s-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="sPage=${i};loadSubs();return false;">${i}</a>`;
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="sPage=${page-1};loadSubs();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="sPage=${page+1};loadSubs();return false;">›</a></div></div>`;
  }

  async function openGrade(id) {
    Modal.open('grade-modal');
    const body = document.getElementById('g-info');
    body.innerHTML = '<div style="text-align:center;color:var(--text-muted);">Loading…</div>';
    const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', {
      action: 'get_one',
      submission_id: id
    });
    if (res.status !== 'success') {
      Toast.error(res.message);
      return;
    }
    const s = res.data;
    document.getElementById('g-id').value = s.id;
    document.getElementById('g-marks').value = s.marks != null ? s.marks : '';
    document.getElementById('g-marks').max = s.total_marks || 100;
    document.getElementById('g-total').textContent = s.total_marks || 100;
    document.getElementById('g-feedback').value = s.feedback || '';
    document.getElementById('g-status').value = (s.status === 'returned') ? 'returned' : 'graded';
    document.getElementById('g-marks-err').style.display = 'none';
    body.innerHTML = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;">Student</div><div style="font-weight:700;">${escapeHtml(s.student_name)}</div></div>
    <div><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;">Assignment</div><div style="font-weight:600;font-size:0.875rem;">${escapeHtml(s.assignment_title||'—')}</div></div>
    <div><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;">Submitted</div><div style="font-size:0.82rem;">${s.submitted_at?new Date(s.submitted_at).toLocaleString():'—'}</div></div>
    <div><div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;">File</div>${s.file_path?`<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <button class="btn btn-secondary btn-sm lms-view-btn" style="padding:5px 10px;"
            data-fid="${s.id}" data-ftype="submission" data-fname="${escapeHtml(s.file_name||'submission')}" title="Preview / Run">
            <i data-lucide="eye" style="width:12px;height:12px;margin-right:4px;"></i>Preview</button>
          <a href="${window.LMS_BASE}/download.php?type=submission&id=${s.id}" class="btn btn-primary btn-sm" style="padding:5px 10px;" download="${escapeHtml(s.file_name||'submission')}">
            <i data-lucide="download" style="width:12px;height:12px;margin-right:4px;"></i>Download</a>
        </div>`:'<span style="color:var(--text-muted);">No file</span>'}</div>
  </div>${s.notes?`<div style="margin-top:10px;padding:10px;background:rgba(99,102,241,0.06);border-radius:var(--radius);border-left:3px solid var(--primary);font-size:0.82rem;"><strong>Notes:</strong> ${escapeHtml(s.notes)}</div>`:''}`;
    if (window.lucide) lucide.createIcons({
      nodes: [body]
    });
  }

  function validateMarks(input) {
    const err = document.getElementById('g-marks-err');
    const val = parseFloat(input.value);
    const max = parseFloat(input.max) || 100;
    if (input.value !== '' && (isNaN(val) || val < 0)) {
      err.textContent = 'Marks cannot be negative';
      err.style.display = '';
      input.setCustomValidity('invalid');
    } else if (input.value !== '' && val > max) {
      err.textContent = `Marks cannot exceed ${max}`;
      err.style.display = '';
      input.setCustomValidity('invalid');
    } else {
      err.style.display = 'none';
      input.setCustomValidity('');
    }
  }

  async function submitGrade(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('g-btn');
    const data = {
      action: 'grade',
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', data);
      if (res.status === 'success') {
        Toast.success('Grade saved!');
        Modal.close('grade-modal');
        loadSubs();
      } else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  function deleteSub(id) {
    Modal.confirm({
      title: 'Delete Submission',
      message: 'Delete this submission permanently?',
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', {
          action: 'delete',
          submission_id: id
        });
        if (res.status === 'success') {
          Toast.success('Deleted');
          loadSubs();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDeleteSubs() {
    const ids = BulkSelect.getSelected('s-table');
    if (!ids.length) {
      Toast.warning('No submissions selected');
      return;
    }
    Modal.confirm({
      title: 'Delete Submissions',
      message: `Delete <strong>${ids.length}</strong> submission(s)?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      icon: '🗑️',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/submissions.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          Toast.success(res.message);
          BulkSelect.reset('s-table');
          loadSubs();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDownloadSubs() {
    const ids = BulkSelect.getSelected('s-table');
    if (!ids.length) {
      Toast.warning('No submissions selected');
      return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.LMS_BASE + '/download-zip.php';
    form.innerHTML = `<input name="type" value="submissions"><input name="ids" value="${ids.join(',')}">`;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
  }

  const dbl = debounce(() => {
    sPage = 1;
    loadSubs();
  }, 300);
  document.getElementById('s-search').addEventListener('input', e => {
    sSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-batch').addEventListener('change', e => {
    sBatch = e.target.value;
    sAssign = '';
    sPage = 1;
    loadBatchAssignments(e.target.value);
    loadSubs();
  });
  document.getElementById('filter-assign').addEventListener('change', e => {
    sAssign = e.target.value;
    sPage = 1;
    loadSubs();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    sStatus = e.target.value;
    sPage = 1;
    loadSubs();
  });
  document.getElementById('per-page').addEventListener('change', e => {
    sPerPage = e.target.value;
    sPage = 1;
    loadSubs();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function timeAgoJS(dt) {
    const s = Math.floor((Date.now() - new Date(dt)) / 1000);
    if (s < 60) return 'Just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }
  document.addEventListener('DOMContentLoaded', loadSubs);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>