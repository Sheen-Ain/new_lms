<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Announcements';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Announcements']];
$batches = [];
$r = $conn->query("SELECT id,name FROM batches WHERE status='active' ORDER BY name");
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
          <h1 class="page-title">Announcements</h1>
          <p class="page-subtitle">Post updates to students and teachers</p>
        </div>
        <button class="btn btn-primary" onclick="openAdd()"><i data-lucide="megaphone" style="width:16px;height:16px;"></i> New Announcement</button>
      </div>

      <!-- Bulk action bar -->
      <div class="bulk-action-bar" id="bulk-action-bar">
        <span class="bulk-action-count" id="bulk-count">0 selected</span>
        <span class="bulk-action-sep">|</span>
        <button class="bulk-btn bulk-btn-danger" onclick="bulkDeleteAnn()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
      </div>

      <div class="card">
        <div class="table-controls">
          <select id="filter-batch" class="form-control" style="width:170px;">
            <option value="">All Batches</option>
            <option value="global">Global Only</option>
            <?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
          </select>
          <select id="filter-priority" class="form-control" style="width:140px;">
            <option value="">All Priority</option>
            <option value="normal">Normal</option>
            <option value="important">Important</option>
            <option value="urgent">Urgent</option>
          </select>
          <select id="filter-status" class="form-control" style="width:130px;">
            <option value="">All Status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div style="overflow-x:auto;">
          <table id="ann-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="select-all-cb" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></th>
                <th>Title</th>
                <th>Batch</th>
                <th>Priority</th>
                <th>Pinned</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Date</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="ann-tbody">
              <?php for ($i = 0; $i < 4; $i++): ?><tr>
                  <td colspan="9">
                    <div class="skeleton skeleton-text" style="margin:10px 0;"></div>
                  </td>
                </tr><?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div id="ann-pagination"></div>
      </div>
    </main>
  </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="add-ann-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary"><i data-lucide="megaphone" style="width:20px;height:20px;"></i></div>
      <h3 class="modal-title" id="ann-modal-title">New Announcement</h3>
      <button class="modal-close" data-modal-close="add-ann"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
    </div>
    <form id="ann-form" onsubmit="submitAnn(event)">
      <input type="hidden" name="action" id="ann-action" value="create">
      <input type="hidden" name="announcement_id" id="ann-id">
      <div class="modal-body" style="max-height:72vh;overflow-y:auto;">
        <div class="form-group"><label class="form-label">Title <span class="required">*</span></label><input type="text" name="title" id="ann-title" class="form-control" required placeholder="Announcement title…"></div>
        <div class="form-group"><label class="form-label">Content <span class="required">*</span></label><textarea name="content" id="ann-content" class="form-control" rows="4" required placeholder="Write your announcement…"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label class="form-label">Batch <span style="color:var(--text-muted);font-weight:400;">(blank = global)</span></label>
            <select name="batch_id" id="ann-batch" class="form-control">
              <option value="">Global (All)</option><?php foreach ($batches as $b): ?><option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Priority</label>
            <select name="priority" id="ann-priority" class="form-control">
              <option value="normal">Normal</option>
              <option value="important">Important</option>
              <option value="urgent">🔴 Urgent</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Status</label>
            <select name="status" id="ann-status" class="form-control">
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Pin to Top</label>
            <label class="toggle-wrapper" style="margin-top:8px;">
              <div class="toggle"><input type="checkbox" name="is_pinned" id="ann-pin" value="1"><span class="toggle-slider"></span></div><span style="font-size:0.875rem;color:var(--text-secondary);margin-left:8px;">Pinned</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-ann">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ann-submit"><span class="btn-text">Publish</span></button>
      </div>
    </form>
  </div>
</div>

<script>
  let annPage = 1,
    annBatch = '',
    annPriority = '',
    annStatus = '';

  async function loadAnn() {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', {
        action: 'list',
        page: annPage,
        per_page: 15,
        batch_id: annBatch === 'global' ? -1 : annBatch,
        priority: annPriority,
        status: annStatus
      });
      if (res.status === 'success') {
        renderAnn(res.data.announcements);
        renderAnnPag(res.data.total, res.data.page, res.data.per_page);
      } else Toast.error(res.message);
    } catch (e) {
      Toast.error('Network error');
    }
  }

  function renderAnn(items) {
    const tb = document.getElementById('ann-tbody');
    if (!items || !items.length) {
      tb.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i data-lucide="megaphone" style="width:40px;height:40px;opacity:0.3;"></i></div><div class="empty-state-title">No announcements</div></div></td></tr>`;
      return;
    }
    tb.innerHTML = items.map(a => {
      const priBadge = a.priority === 'urgent' ? '<span class="badge badge-danger badge-pulse">🔴 Urgent</span>' : a.priority === 'important' ? '<span class="badge badge-warning">⚠️ Important</span>' : '<span class="badge badge-secondary">Normal</span>';
      const stBadge = a.status === 'published' ? '<span class="badge badge-success">Published</span>' : '<span class="badge badge-secondary">Draft</span>';
      return `<tr>
      <td><input type="checkbox" class="row-cb" value="${a.id}" style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary);"></td>
      <td><div style="font-weight:600;font-size:0.875rem;">${a.is_pinned==1?'📌 ':''}${escapeHtml(a.title)}</div></td>
      <td style="font-size:0.82rem;color:var(--text-muted);">${escapeHtml(a.batch_name||'Global')}</td>
      <td>${priBadge}</td>
      <td style="font-size:0.82rem;">${a.is_pinned==1?'<i data-lucide="pin" style="width:11px;height:11px;margin-right:3px;"></i>Yes':'—'}</td>
      <td>${stBadge}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${escapeHtml(a.creator_name||'—')}</td>
      <td style="font-size:0.78rem;color:var(--text-muted);">${formatDateJS(a.created_at)}</td>
      <td><div class="table-actions" style="justify-content:flex-end;">
        <button class="action-btn" style="background:rgba(245,158,11,0.1);color:#d97706;" onclick="togglePin(${a.id})" data-tooltip="${a.is_pinned==1?'Unpin':'Pin'}"><i data-lucide="${a.is_pinned==1?'pin-off':'pin'}" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-edit" onclick="editAnn(${a.id})" data-tooltip="Edit"><i data-lucide="edit-2" style="width:13px;height:13px;"></i></button>
        <button class="action-btn action-btn-delete" onclick="deleteAnn(${a.id},'${escapeHtml(a.title)}')" data-tooltip="Delete"><i data-lucide="trash-2" style="width:13px;height:13px;"></i></button>
      </div></td>
    </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons({
      nodes: [document.getElementById('ann-tbody')]
    });
    BulkSelect.init('ann-table');
  }

  function renderAnnPag(total, page, perPage) {
    const el = document.getElementById('ann-pagination');
    if (!total) {
      el.innerHTML = '';
      return;
    }
    const tp = Math.ceil(total / perPage),
      s = (page - 1) * perPage + 1,
      e = Math.min(page * perPage, total);
    let pgs = '';
    for (let i = 1; i <= tp; i++) {
      if (i === 1 || i === tp || (i >= page - 2 && i <= page + 2)) pgs += `<a href="#" class="page-btn ${i===page?'active':''}" onclick="annPage=${i};loadAnn();return false;">${i}</a>`;
      else if (i === page - 3 || i === page + 3) pgs += '<span class="page-ellipsis">…</span>';
    }
    el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total}</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="annPage=${page-1};loadAnn();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="annPage=${page+1};loadAnn();return false;">›</a></div></div>`;
  }

  function openAdd() {
    document.getElementById('ann-modal-title').textContent = 'New Announcement';
    document.getElementById('ann-action').value = 'create';
    document.getElementById('ann-id').value = '';
    document.getElementById('ann-form').reset();
    Modal.open('add-ann');
  }

  async function editAnn(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', {
      action: 'get_one',
      announcement_id: id
    });
    if (res.status !== 'success') {
      Toast.error(res.message);
      return;
    }
    const a = res.data;
    document.getElementById('ann-modal-title').textContent = 'Edit Announcement';
    document.getElementById('ann-action').value = 'update';
    document.getElementById('ann-id').value = a.id;
    document.getElementById('ann-title').value = a.title || '';
    document.getElementById('ann-content').value = a.content || '';
    document.getElementById('ann-batch').value = a.batch_id || '';
    document.getElementById('ann-priority').value = a.priority || 'normal';
    document.getElementById('ann-status').value = a.status || 'published';
    document.getElementById('ann-pin').checked = a.is_pinned == 1;
    Modal.open('add-ann');
  }

  async function submitAnn(e) {
    e.preventDefault();
    const form = e.target,
      btn = document.getElementById('ann-submit');
    const data = {
      csrf_token: window.CSRF_TOKEN
    };
    new FormData(form).forEach((v, k) => data[k] = v);
    data.is_pinned = form.querySelector('[name=is_pinned]')?.checked ? 1 : 0;
    btn.disabled = true;
    btn.classList.add('btn-loading');
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', data);
      if (res.status === 'success') {
        Toast.success(res.message);
        Modal.close('add-ann');
        loadAnn();
      } else Toast.error(res.message);
    } finally {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  async function togglePin(id) {
    const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', {
      action: 'toggle_pin',
      announcement_id: id
    });
    if (res.status === 'success') {
      Toast.info(res.message);
      loadAnn();
    } else Toast.error(res.message);
  }

  function deleteAnn(id, title) {
    Modal.confirm({
      title: 'Delete Announcement',
      message: `Delete <strong>${escapeHtml(title)}</strong>?`,
      confirmText: 'Delete',
      confirmClass: 'btn-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', {
          action: 'delete',
          announcement_id: id
        });
        if (res.status === 'success') {
          Toast.success('Deleted');
          loadAnn();
        } else throw new Error(res.message);
      }
    });
  }

  function bulkDeleteAnn() {
    const ids = BulkSelect.getSelected('ann-table');
    if (!ids.length) {
      Toast.warning('No announcements selected');
      return;
    }
    Modal.confirm({
      title: 'Delete Announcements',
      message: `Delete <strong>${ids.length}</strong> announcement(s)?`,
      confirmText: 'Delete All',
      confirmClass: 'btn-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const res = await ajax(window.LMS_BASE + '/ajax/announcements.ajax.php', {
          action: 'bulk_delete',
          ids: ids.join(',')
        });
        if (res.status === 'success') {
          Toast.success(res.message);
          BulkSelect.reset('ann-table');
          loadAnn();
        } else throw new Error(res.message);
      }
    });
  }

  document.getElementById('filter-batch').addEventListener('change', e => {
    annBatch = e.target.value;
    annPage = 1;
    loadAnn();
  });
  document.getElementById('filter-priority').addEventListener('change', e => {
    annPriority = e.target.value;
    annPage = 1;
    loadAnn();
  });
  document.getElementById('filter-status').addEventListener('change', e => {
    annStatus = e.target.value;
    annPage = 1;
    loadAnn();
  });

  function escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function formatDateJS(dt) {
    return dt ? new Date(dt).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }) : '—';
  }
  document.addEventListener('DOMContentLoaded', loadAnn);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>