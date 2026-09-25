<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Courses';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Courses']];
include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Courses</h1>
          <p class="page-subtitle">Manage all courses and their batches</p>
        </div>
        <button class="btn btn-primary" onclick="openAdd()"><i data-lucide="plus" style="width:16px;height:16px;"></i> New Course</button>
      </div>
      <div class="card">
        <div class="table-controls">
          <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" id="course-search" class="form-control" placeholder="Search courses…"></div>
          <select id="filter-status" class="form-control" style="width:140px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="archived">Archived</option>
          </select>
          <div class="table-controls-right"><select id="per-page" class="form-control" style="width:110px;">
              <option value="10">10 / page</option>
              <option value="25">25 / page</option>
            </select></div>
        </div>
        <div style="overflow-x:auto;">
          <table id="courses-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Course</th>
                <th>Description</th>
                <th>Batches</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Date</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="courses-tbody">
              <?php for ($i = 0; $i < 4; $i++): ?><tr>
                  <td colspan="8">
                    <div class="skeleton skeleton-text" style="width:100%;margin:8px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="courses-pagination"></div>
      </div>
    </main>
  </div>
</div>

<div class="bulk-action-bar" id="bulk-action-bar">
  <span class="bulk-action-count" id="bulk-count">0 selected</span>
  <span class="bulk-action-sep">|</span>
  <button class="bulk-btn bulk-btn-danger" onclick="bulkDelete()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="add-course-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="book-open" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="course-modal-title">New Course</h3>
      <button class="modal-close" data-modal-close="add-course"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="course-form" onsubmit="submitCourse(event)" enctype="multipart/form-data">
      <input type="hidden" name="action" id="course-action" value="create">
      <input type="hidden" name="course_id" id="course-id" value="">
      <div class="modal-body" style="max-height:72vh;overflow-y:auto;">
        <div class="form-group"><label class="form-label">Title <span class="required">*</span></label><input type="text" name="title" id="c-title" class="form-control" placeholder="e.g., Web Development Bootcamp" required></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="c-desc" class="form-control" rows="3" placeholder="Course description…"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label">Thumbnail</label>
            <!-- Drag & drop thumbnail zone -->
            <div id="thumb-drop" onclick="document.getElementById('c-thumb').click()"
              style="border:2px dashed var(--border);border-radius:var(--radius);padding:0;cursor:pointer;overflow:hidden;position:relative;min-height:120px;display:flex;align-items:center;justify-content:center;transition:border-color .2s,background .2s;"
              ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--bg-hover)'"
              ondragleave="this.style.borderColor='';this.style.background=''"
              ondrop="handleThumbDrop(event)">
              <div id="thumb-placeholder" style="text-align:center;padding:20px;pointer-events:none;">
                <i data-lucide="image-plus" style="width:28px;height:28px;color:var(--text-muted);margin-bottom:8px;display:block;margin:0 auto 8px;"></i>
                <div style="font-size:0.8rem;color:var(--text-muted);">Drop image or click to browse</div>
              </div>
              <img id="thumb-preview-img" src="" alt="Thumbnail preview"
                style="display:none;width:100%;height:160px;object-fit:cover;border-radius:calc(var(--radius) - 2px);">
              <div id="thumb-remove" onclick="removeThumb(event)"
                style="display:none;position:absolute;top:6px;right:6px;width:26px;height:26px;background:rgba(0,0,0,0.6);border-radius:50%;align-items:center;justify-content:center;cursor:pointer;">
                <i data-lucide="x" style="width:14px;height:14px;color:#fff;"></i>
              </div>
            </div>
            <input type="file" name="thumbnail" id="c-thumb" accept="image/*" style="display:none;" onchange="handleThumbFile(this.files[0])">
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:5px;">JPG, PNG, WebP — max 5MB</div>
          </div>
          <div class="form-group"><label class="form-label">Status</label>
            <select name="status" id="c-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-course">Cancel</button>
        <button type="submit" class="btn btn-primary" id="course-submit"><i data-lucide="save" style="width:14px;height:14px;"></i> <span class="btn-text">Save Course</span></button>
      </div>
    </form>
  </div>
</div>

<script>
  let cPage = 1,
    cPerPage = 10,
    cSearch = '',
    cStatus = '';

  function openAdd() {
    document.getElementById('course-modal-title').textContent = 'New Course';
    document.getElementById('course-action').value = 'create';
    document.getElementById('course-id').value = '';
    document.getElementById('course-form').reset();
    removeThumb();
    Modal.open('add-course');
    setTimeout(() => document.getElementById('c-title').focus(), 200);
  }

  // Thumbnail handling
  function handleThumbFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 5 * 1024 * 1024) {
      if (window.Toast) Toast.error('Image must be under 5MB');
      return;
    }
    const r = new FileReader();
    r.onload = e => {
      document.getElementById('thumb-preview-img').src = e.target.result;
      document.getElementById('thumb-preview-img').style.display = 'block';
      document.getElementById('thumb-placeholder').style.display = 'none';
      document.getElementById('thumb-remove').style.display = 'flex';
      document.getElementById('thumb-drop').style.border = '2px solid var(--primary)';
    };
    r.readAsDataURL(file);
  }

  function handleThumbDrop(e) {
    e.preventDefault();
    document.getElementById('thumb-drop').style.borderColor = '';
    document.getElementById('thumb-drop').style.background = '';
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      document.getElementById('c-thumb').files = dt.files;
      handleThumbFile(file);
    }
  }

  function removeThumb(e) {
    if (e) {
      e.stopPropagation();
    }
    document.getElementById('c-thumb').value = '';
    document.getElementById('thumb-preview-img').src = '';
    document.getElementById('thumb-preview-img').style.display = 'none';
    document.getElementById('thumb-placeholder').style.display = 'block';
    document.getElementById('thumb-remove').style.display = 'none';
    document.getElementById('thumb-drop').style.border = '2px dashed var(--border)';
  }

  async function loadCourses() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/courses.ajax.php', {
        action: 'list',
        page: cPage,
        per_page: cPerPage,
        search: cSearch,
        status: cStatus
      });
      if (res.status === 'success') {
        renderCourses(res.data.courses);
        renderPag(res.data.total, res.data.page, res.data.per_page);
      } else if (window.Toast) Toast.error(res.message);
    } catch (e) {
      if (window.Toast) Toast.error('Network error');
    }
  }

  function renderCourses(courses) {
    const tb = document.getElementById('courses-tbody');
    if (!courses || !courses.length) {
      tb.innerHTML = `<tr><td colspan="8"><div class="empty-state">
      <i data-lucide="book-open" style="width:40px;height:40px;opacity:0.3;"></i>
      <div class="empty-state-title" style="margin-top:12px;">No courses yet</div>
      <div class="empty-state-text">Click "New Course" to get started</div>
      <button class="btn btn-primary" style="margin-top:12px;" onclick="openAdd()"><i data-lucide="plus" style="width:14px;height:14px;"></i> New Course</button>
    </div></td></tr>`;
      lucide.createIcons({
        nodes: [tb]
      });
      return;
    }
    tb.innerHTML = courses.map(c => {
      const thumb = c.thumbnail ?
        `<img src="<?= BASE_PATH ?>/uploads/topics/${esc(c.thumbnail)}" style="width:48px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">` :
        `<div style="width:48px;height:36px;background:var(--bg-hover);border-radius:6px;display:flex;align-items:center;justify-content:center;"><i data-lucide="image" style="width:16px;height:16px;color:var(--text-muted);"></i></div>`;
      const stCls = c.status === 'active' ? 'badge-success' : c.status === 'inactive' ? 'badge-warning' : 'badge-secondary';
      return `<tr>
      <td><input type="checkbox" class="row-cb" value="${c.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="display:flex;align-items:center;gap:10px;">${thumb}<div style="font-weight:600;font-size:0.875rem;">${esc(c.title)}</div></div></td>
      <td style="max-width:220px;font-size:0.82rem;color:var(--text-secondary);">${c.description?esc(c.description.slice(0,80))+(c.description.length>80?'…':''):'—'}</td>
      <td><span class="badge badge-info"><i data-lucide="layers" style="width:10px;height:10px;margin-right:4px;"></i>${c.batch_count||0} batches</span></td>
      <td><span class="badge ${stCls} status-toggle" data-id="${c.id}" data-status="${c.status}" style="cursor:pointer;">${c.status}</span></td>
      <td style="font-size:0.82rem;color:var(--text-muted);">${esc(c.creator_name||'—')}</td>
      <td style="font-size:0.82rem;color:var(--text-muted);">${fmtDate(c.created_at)}</td>
      <td><div class="table-actions" style="justify-content:flex-end;">
        <button class="action-btn action-btn-edit" onclick="editCourse(${c.id})" data-tooltip="Edit"><i data-lucide="edit-3" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-delete" onclick="deleteCourse(${c.id},'${esc(c.title)}')" data-tooltip="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [tb]
    });
    document.querySelectorAll('.status-toggle').forEach(el => el.addEventListener('click', () => toggleCourseStatus(el.dataset.id, el.dataset.status === 'active' ? 'inactive' : 'active')));
    BulkSelect.init('courses-table');
  }

  function renderPag(total, page, perPage) {
    const el = document.getElementById('courses-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e2 = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="cPage=${i};loadCourses();return false;">${i}</a>`;
      else if (i === page - 3 || i === page + 3) pgs += '<span class="page-ellipsis">…</span>';
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e2} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="cPage=${page-1};loadCourses();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="cPage=${page+1};loadCourses();return false;">›</a></div></div>`;
  }

  async function toggleCourseStatus(id, status) {
    const res = await ajax(window.LMS_BASE + '/ajax/courses.ajax.php', {
      action: 'toggle_status',
      course_id: id,
      status
    });
    if (res.status === 'success') {
      if (window.Toast) Toast.success('Status updated');
      loadCourses();
    } else if (window.Toast) Toast.error(res.message);
  }

  async function submitCourse(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('course-submit');
    btn.disabled = true;
    btn.classList.add('btn-loading');
    const fd = new FormData(form);
    fd.append('csrf_token', window.CSRF_TOKEN);
    try {
      const res = await fetch(window.LMS_BASE + '/ajax/courses.ajax.php', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (data.status === 'success') {
        if (window.Toast) Toast.success(data.message);
        Modal.close('add-course');
        form.reset();
        removeThumb();
        loadCourses();
      } else if (window.Toast) Toast.error(data.message);
    } catch (err) {
      if (window.Toast) Toast.error('Request failed');
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function editCourse(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/courses.ajax.php', {
      action: 'get_one',
      course_id: id
    });
    if (res.status !== 'success') {
      if (window.Toast) Toast.error(res.message);
      return;
    }
    const c = res.data;
    document.getElementById('course-modal-title').textContent = 'Edit Course';
    document.getElementById('course-action').value = 'update';
    document.getElementById('course-id').value = c.id;
    document.getElementById('c-title').value = c.title || '';
    document.getElementById('c-desc').value = c.description || '';
    document.getElementById('c-status').value = c.status || 'active';
    if (c.thumbnail) {
      document.getElementById('thumb-preview-img').src = '<?= BASE_PATH ?>/uploads/topics/' + c.thumbnail;
      document.getElementById('thumb-preview-img').style.display = 'block';
      document.getElementById('thumb-placeholder').style.display = 'none';
      document.getElementById('thumb-remove').style.display = 'flex';
      document.getElementById('thumb-drop').style.border = '2px solid var(--primary)';
    } else removeThumb();
    Modal.open('add-course');
  }

  function deleteCourse(id, title) {
    Modal.confirm({
      title: 'Delete Course',
      message: `Delete <strong>${esc(title)}</strong>? All batches, topics and assignments will be removed.`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      iconClass: 'modal-icon-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/courses.ajax.php', {
          action: 'delete',
          course_id: id
        });
        if (res.status === 'success') {
          if (window.Toast) Toast.success('Course deleted');
          loadCourses();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDelete() {
    const ids = BulkSelect.getSelected('courses-table');
    if (!ids.length) {
      if (window.Toast) Toast.warning('No courses selected');
      return;
    }
    Modal.confirm({
      title: `Delete ${ids.length} Course${ids.length>1?'s':''}`,
      message: `Permanently delete <strong>${ids.length} course${ids.length>1?'s':''}</strong> and all their data?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      iconClass: 'modal-icon-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/courses.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          if (window.Toast) Toast.success(res.message);
          loadCourses();
        } else throw new Error(res.message);
      }
    });
  }

  const dbl = debounce(() => {
    cPage = 1;
    loadCourses();
  }, 300);
  document.getElementById('course-search').addEventListener('input', e => {
    cSearch = e.target.value;
    dbl();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    cStatus = e.target.value;
    cPage = 1;
    loadCourses();
  });
  document.getElementById('per-page').addEventListener('change', e => {
    cPerPage = e.target.value;
    cPage = 1;
    loadCourses();
  });

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmtDate(dt) {
    return dt ? new Date(dt).toLocaleDateString('en-PK', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }) : '—';
  }

  document.addEventListener('DOMContentLoaded', loadCourses);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>