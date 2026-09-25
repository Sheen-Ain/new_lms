<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Batches';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Batches']];
$courses = [];
$r = $conn->query("SELECT id,title FROM courses WHERE status='active' ORDER BY title");
while ($row = $r->fetch_assoc()) $courses[] = $row;
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
    color: var(--text);
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
    color: var(--text);
  }

  .flatpickr-current-month input.cur-year,
  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .numInput,
  .flatpickr-time input,
  .flatpickr-time .flatpickr-am-pm {
    color: var(--text);
  }

  .numInputWrapper span {
    color: var(--text);
  }
</style>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Batches</h1>
          <p class="page-subtitle">Manage course batches, teachers &amp; students</p>
        </div>
        <button class="btn btn-primary" onclick="openAddBatch()"><i data-lucide="plus" style="width:16px;height:16px;"></i> New Batch</button>
      </div>
      <div class="card">
        <div class="table-controls">
          <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="batch-search" class="form-control" placeholder="Search batches…"></div>
          <select id="filter-course" class="form-control" style="width:180px;">
            <option value="">All Courses</option><?php foreach ($courses as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
          </select>
          <select id="filter-status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div style="overflow-x:auto;">
          <table id="batches-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Batch</th>
                <th>Course</th>
                <th>Teachers</th>
                <th>Students</th>
                <th>Dates</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="batches-tbody">
              <?php for ($i = 0; $i < 4; $i++): ?><tr>
                  <td colspan="8">
                    <div class="skeleton skeleton-text" style="margin:10px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="batches-pagination"></div>
      </div>
    </main>
  </div>
</div>

<div class="bulk-action-bar" id="bulk-action-bar">
  <span class="bulk-action-count" id="bulk-count">0 selected</span>
  <span class="bulk-action-sep">|</span>
  <button class="bulk-btn bulk-btn-danger" onclick="bulkDeleteBatches()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
</div>

<!-- ADD/EDIT BATCH MODAL -->
<div class="modal-overlay" id="add-batch-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="layers" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="batch-modal-title">New Batch</h3>
      <button class="modal-close" data-modal-close="add-batch"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="batch-form" onsubmit="submitBatch(event)">
      <input type="hidden" name="action" id="b-action" value="create">
      <input type="hidden" name="batch_id" id="b-id">
      <!-- Hidden fields for Flatpickr dates -->
      <input type="hidden" name="start_date" id="b-start-hidden">
      <input type="hidden" name="end_date" id="b-end-hidden">
      <div class="modal-body" style="max-height:72vh;overflow-y:auto;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Batch Name <span class="required">*</span></label><input type="text" name="name" id="b-name" class="form-control" placeholder="e.g., Batch A — Jan 2025" required></div>
          <div class="form-group"><label class="form-label">Course <span class="required">*</span></label>
            <select name="course_id" id="b-course" class="form-control" required>
              <option value="">Select Course</option>
              <?php foreach ($courses as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Max Students</label><input type="number" name="max_students" id="b-max" class="form-control" value="50" min="1" max="1000"></div>
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <div style="position:relative;">
              <i data-lucide="calendar" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;z-index:1;"></i>
              <input type="text" id="b-start-display" class="form-control" placeholder="Select start date…" style="padding-left:36px;cursor:pointer;" readonly autocomplete="off">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <div style="position:relative;">
              <i data-lucide="calendar" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;z-index:1;"></i>
              <input type="text" id="b-end-display" class="form-control" placeholder="Select end date…" style="padding-left:36px;cursor:pointer;" readonly autocomplete="off">
            </div>
          </div>
          <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Description</label><textarea name="description" id="b-desc" class="form-control" rows="2" placeholder="Batch description…"></textarea></div>
          <div class="form-group"><label class="form-label">Status</label><select name="status" id="b-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="completed">Completed</option>
            </select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-batch">Cancel</button>
        <button type="submit" class="btn btn-primary" id="batch-submit"><i data-lucide="save" style="width:14px;height:14px;"></i> <span class="btn-text">Save Batch</span></button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN TEACHERS MODAL -->
<div class="modal-overlay" id="teachers-modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="presentation" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Assign Teachers</h3>
      <button class="modal-close" data-modal-close="teachers-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
      <input type="hidden" id="assign-batch-id">
      <div class="search-box" style="margin-bottom:14px;"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="teacher-search" class="form-control" placeholder="Search teachers…"></div>
      <div id="teachers-list" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close="teachers-modal">Cancel</button>
      <button class="btn btn-primary" onclick="saveTeachers()" id="save-teachers-btn"><i data-lucide="save" style="width:14px;height:14px;"></i> <span class="btn-text">Save</span></button>
    </div>
  </div>
</div>

<!-- ENROLL STUDENTS MODAL -->
<div class="modal-overlay" id="students-modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-icon modal-icon-success"><i data-lucide="graduation-cap" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title">Enroll Students</h3>
      <button class="modal-close" data-modal-close="students-modal"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
      <input type="hidden" id="enroll-batch-id">
      <div class="search-box" style="margin-bottom:14px;"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="student-search" class="form-control" placeholder="Search students…"></div>
      <div id="students-list" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close="students-modal">Cancel</button>
      <button class="btn btn-primary" onclick="saveStudents()" id="save-students-btn"><i data-lucide="save" style="width:14px;height:14px;"></i> <span class="btn-text">Save</span></button>
    </div>
  </div>
</div>

<script>
  let bPage = 1,
    bPerPage = 10,
    bSearch = '',
    bCourse = '',
    bStatus = '';
  let allTeachers = [],
    allStudents = [],
    selectedTeachers = [],
    selectedStudents = [];
  let _startPicker = null,
    _endPicker = null;

  function openAddBatch() {
    document.getElementById('batch-modal-title').textContent = 'New Batch';
    document.getElementById('b-action').value = 'create';
    document.getElementById('b-id').value = '';
    document.getElementById('batch-form').reset();
    if (_startPicker) _startPicker.clear();
    if (_endPicker) _endPicker.clear();
    document.getElementById('b-start-hidden').value = '';
    document.getElementById('b-end-hidden').value = '';
    Modal.open('add-batch');
    setTimeout(() => document.getElementById('b-name').focus(), 200);
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Flatpickr for date fields
    _startPicker = flatpickr('#b-start-display', {
      dateFormat: 'Y-m-d',
      altFormat: 'M j, Y',
      altInput: false,
      onChange: (sel, str) => document.getElementById('b-start-hidden').value = str || ''
    });
    _endPicker = flatpickr('#b-end-display', {
      dateFormat: 'Y-m-d',
      altFormat: 'M j, Y',
      altInput: false,
      onChange: (sel, str) => document.getElementById('b-end-hidden').value = str || ''
    });
    loadBatches();
  });

  async function loadBatches() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
        action: 'list',
        page: bPage,
        per_page: bPerPage,
        search: bSearch,
        course_id: bCourse,
        status: bStatus
      });
      if (res.status === 'success') {
        renderBatches(res.data.batches);
        renderPag(res.data.total, res.data.page, res.data.per_page);
      } else if (window.Toast) Toast.error(res.message);
    } catch (e) {
      if (window.Toast) Toast.error('Network error');
    }
  }

  function renderBatches(batches) {
    const tb = document.getElementById('batches-tbody');
    if (!batches || !batches.length) {
      tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i data-lucide="layers" style="width:40px;height:40px;opacity:0.3;"></i><div class="empty-state-title" style="margin-top:12px;">No batches yet</div></div></td></tr>`;
      lucide.createIcons({
        nodes: [tb]
      });
      return;
    }
    tb.innerHTML = batches.map(b => {
      const stCls = b.status === 'active' ? 'badge-success' : b.status === 'completed' ? 'badge-info' : 'badge-warning';
      const filled = b.student_count || 0,
        max = b.max_students || 50,
        pct = Math.min(100, Math.round((filled / max) * 100));
      const progColor = pct > 80 ? 'var(--danger)' : pct > 60 ? 'var(--warning)' : 'var(--success)';
      return `<tr>
      <td><input type="checkbox" class="row-cb" value="${b.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="font-weight:600;font-size:0.875rem;">${esc(b.name)}</div></td>
      <td style="font-size:0.82rem;color:var(--text-secondary);">${esc(b.course_title||'—')}</td>
      <td style="font-size:0.82rem;">${esc(b.teacher_names||'—')}</td>
      <td style="min-width:120px;"><div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:4px;">${filled}/${max}</div><div class="progress" style="height:5px;"><div class="progress-bar" style="width:${pct}%;background:${progColor};"></div></div></td>
      <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;">
        ${b.start_date?`<div style="display:flex;align-items:center;gap:4px;"><i data-lucide="calendar-check" style="width:11px;height:11px;color:var(--success);"></i>${fmtDate(b.start_date)}</div>`:'—'}
        ${b.end_date?`<div style="display:flex;align-items:center;gap:4px;margin-top:2px;"><i data-lucide="calendar-x" style="width:11px;height:11px;color:var(--danger);"></i>${fmtDate(b.end_date)}</div>`:''}
      </td>
      <td><span class="badge ${stCls}" style="cursor:pointer;" onclick="toggleBatchStatus(${b.id},'${b.status==='active'?'inactive':'active'}')">${b.status}</span></td>
      <td><div class="table-actions" style="justify-content:flex-end;">
        <button class="action-btn" style="background:rgba(139,92,246,0.1);color:#7c3aed;" onclick="openTeachers(${b.id})" data-tooltip="Assign Teachers"><i data-lucide="presentation" style="width:13px;height:13px;"></i></button>
        <button class="action-btn" style="background:rgba(16,185,129,0.1);color:#059669;" onclick="openStudents(${b.id})" data-tooltip="Enroll Students"><i data-lucide="graduation-cap" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-edit" onclick="editBatch(${b.id})" data-tooltip="Edit"><i data-lucide="edit-3" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-delete" onclick="deleteBatch(${b.id},'${esc(b.name)}')" data-tooltip="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [tb]
    });
    BulkSelect.init('batches-table');
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('batches-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="bPage=${i};loadBatches();return false;">${i}</a>`;
      else if (i === page - 3 || i === page + 3) pgs += '<span class="page-ellipsis">…</span>';
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="bPage=${page-1};loadBatches();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="bPage=${page+1};loadBatches();return false;">›</a></div></div>`;
  }

  async function toggleBatchStatus(id, status) {
    const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
      action: 'toggle_status',
      batch_id: id,
      status
    });
    if (res.status === 'success') {
      if (window.Toast) Toast.success('Updated');
      loadBatches();
    } else if (window.Toast) Toast.error(res.message);
  }

  async function submitBatch(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('batch-submit');
    const data = {
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', data);
      if (res.status === 'success') {
        if (window.Toast) Toast.success(res.message);
        Modal.close('add-batch');
        form.reset();
        if (_startPicker) _startPicker.clear();
        if (_endPicker) _endPicker.clear();
        loadBatches();
      } else if (window.Toast) Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function editBatch(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
      action: 'get_one',
      batch_id: id
    });
    if (res.status !== 'success') {
      if (window.Toast) Toast.error(res.message);
      return;
    }
    const b = res.data;
    document.getElementById('batch-modal-title').textContent = 'Edit Batch';
    document.getElementById('b-action').value = 'update';
    document.getElementById('b-id').value = b.id;
    document.getElementById('b-name').value = b.name || '';
    document.getElementById('b-course').value = b.course_id || '';
    document.getElementById('b-max').value = b.max_students || 50;
    document.getElementById('b-desc').value = b.description || '';
    document.getElementById('b-status').value = b.status || 'active';
    if (_startPicker) {
      _startPicker.setDate(b.start_date || null);
      document.getElementById('b-start-hidden').value = b.start_date || '';
    }
    if (_endPicker) {
      _endPicker.setDate(b.end_date || null);
      document.getElementById('b-end-hidden').value = b.end_date || '';
    }
    Modal.open('add-batch');
  }

  function deleteBatch(id, name) {
    Modal.confirm({
      title: 'Delete Batch',
      message: `Delete batch <strong>${esc(name)}</strong>? All enrollments and assignments will be removed.`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      iconClass: 'modal-icon-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
          action: 'delete',
          batch_id: id
        });
        if (res.status === 'success') {
          if (window.Toast) Toast.success('Batch deleted');
          loadBatches();
        } else throw new Error(res.message);
      }
    });
  }

  // Teachers
  async function openTeachers(batchId) {
    document.getElementById('assign-batch-id').value = batchId;
    const listEl = document.getElementById('teachers-list');
    listEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Loading…</div>';
    Modal.open('teachers-modal');
    const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
      action: 'get_teachers',
      batch_id: batchId
    });
    if (res.status !== 'success') {
      listEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);">Error loading</div>';
      return;
    }
    allTeachers = res.data.all;
    selectedTeachers = res.data.assigned.map(t => t.id.toString());
    renderTeacherList(allTeachers, selectedTeachers);
    document.getElementById('teacher-search').oninput = e => {
      const q = e.target.value.toLowerCase();
      renderTeacherList(allTeachers.filter(t => t.full_name.toLowerCase().includes(q) || t.email.toLowerCase().includes(q)), selectedTeachers);
    };
  }

  function renderTeacherList(teachers, selected) {
    const el = document.getElementById('teachers-list');
    if (!teachers.length) {
      el.innerHTML = '<div style="text-align:center;padding:16px;color:var(--text-muted);">No teachers found</div>';
      return;
    }
    el.innerHTML = teachers.map(t => `
    <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--bg);border-radius:var(--radius);cursor:pointer;border:1.5px solid ${selected.includes(t.id.toString())?'var(--primary)':'var(--border)'};transition:all 0.15s;">
      <input type="checkbox" value="${t.id}" ${selected.includes(t.id.toString())?'checked':''} style="accent-color:var(--primary);width:16px;height:16px;" onchange="toggleTeacher('${t.id}',this.checked)">
      <i data-lucide="user" style="width:16px;height:16px;color:var(--text-muted);flex-shrink:0;"></i>
      <div style="min-width:0;flex:1;">
        <div style="font-weight:600;font-size:0.875rem;">${esc(t.full_name)}</div>
        <div style="font-size:0.75rem;color:var(--text-muted);">${esc(t.email)}</div>
      </div>
      ${selected.includes(t.id.toString())?'<i data-lucide="check-circle-2" style="width:15px;height:15px;color:var(--primary);flex-shrink:0;"></i>':''}
    </label>`).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('teachers-list')]
    });
  }

  function toggleTeacher(id, checked) {
    id = id.toString();
    if (checked && !selectedTeachers.includes(id)) selectedTeachers.push(id);
    else selectedTeachers = selectedTeachers.filter(i => i !== id);
  }
  async function saveTeachers() {
    const batchId = document.getElementById('assign-batch-id').value;
    const btn = document.getElementById('save-teachers-btn');
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
        action: 'assign_teachers',
        batch_id: batchId,
        teacher_ids: selectedTeachers.join(',')
      });
      if (res.status === 'success') {
        if (window.Toast) Toast.success('Teachers assigned!');
        Modal.close('teachers-modal');
        loadBatches();
      } else if (window.Toast) Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  // Students
  async function openStudents(batchId) {
    document.getElementById('enroll-batch-id').value = batchId;
    const listEl = document.getElementById('students-list');
    listEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Loading…</div>';
    Modal.open('students-modal');
    const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
      action: 'get_students',
      batch_id: batchId
    });
    if (res.status !== 'success') {
      listEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger);">Error loading</div>';
      return;
    }
    allStudents = res.data.all;
    selectedStudents = res.data.enrolled.map(s => s.id.toString());
    renderStudentList(allStudents, selectedStudents);
    document.getElementById('student-search').oninput = e => {
      const q = e.target.value.toLowerCase();
      renderStudentList(allStudents.filter(s => s.full_name.toLowerCase().includes(q) || s.email.toLowerCase().includes(q)), selectedStudents);
    };
  }

  function renderStudentList(students, selected) {
    const el = document.getElementById('students-list');
    if (!students.length) {
      el.innerHTML = '<div style="text-align:center;padding:16px;color:var(--text-muted);">No students found</div>';
      return;
    }
    el.innerHTML = students.map(s => `
    <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--bg);border-radius:var(--radius);cursor:pointer;border:1.5px solid ${selected.includes(s.id.toString())?'var(--success)':'var(--border)'};transition:all 0.15s;">
      <input type="checkbox" value="${s.id}" ${selected.includes(s.id.toString())?'checked':''} style="accent-color:var(--success);width:16px;height:16px;" onchange="toggleStudent('${s.id}',this.checked)">
      <i data-lucide="user" style="width:16px;height:16px;color:var(--text-muted);flex-shrink:0;"></i>
      <div style="min-width:0;flex:1;">
        <div style="font-weight:600;font-size:0.875rem;">${esc(s.full_name)}</div>
        <div style="font-size:0.75rem;color:var(--text-muted);">${esc(s.email)}</div>
      </div>
      ${selected.includes(s.id.toString())?'<i data-lucide="check-circle-2" style="width:15px;height:15px;color:var(--success);flex-shrink:0;"></i>':''}
    </label>`).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('students-list')]
    });
  }

  function toggleStudent(id, checked) {
    id = id.toString();
    if (checked && !selectedStudents.includes(id)) selectedStudents.push(id);
    else selectedStudents = selectedStudents.filter(i => i !== id);
  }
  async function saveStudents() {
    const batchId = document.getElementById('enroll-batch-id').value;
    const btn = document.getElementById('save-students-btn');
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
        action: 'enroll_students',
        batch_id: batchId,
        student_ids: selectedStudents.join(',')
      });
      if (res.status === 'success') {
        if (window.Toast) Toast.success('Students enrolled!');
        Modal.close('students-modal');
        loadBatches();
      } else if (window.Toast) Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  function bulkDeleteBatches() {
    const ids = BulkSelect.getSelected('batches-table');
    if (!ids.length) {
      if (window.Toast) Toast.warning('No batches selected');
      return;
    }
    Modal.confirm({
      title: 'Delete Batches',
      message: `Delete <strong>${ids.length}</strong> batch(es)?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      iconClass: 'modal-icon-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/batches.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          if (window.Toast) Toast.success(res.message);
          BulkSelect.reset('batches-table');
          loadBatches();
        } else throw new Error(res.message);
      }
    });
  }

  const dbl = debounce(() => {
    bPage = 1;
    loadBatches();
  }, 300);
  document.getElementById('batch-search').addEventListener('input', e => {
    bSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-course').addEventListener('change', e => {
    bCourse = e.target.value;
    bPage = 1;
    loadBatches();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    bStatus = e.target.value;
    bPage = 1;
    loadBatches();
  });

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmtDate(dt) {
    return dt ? new Date(dt + 'T00:00:00').toLocaleDateString('en-PK', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }) : '—';
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>