<?php
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Announcements';
$breadcrumbs = [['label'=>'Teacher','url'=>BASE_PATH . '/teacher/'],['label'=>'Announcements']];
$uid = (int)$currentUser['id'];

// Fetch teacher's batches for filter + modal
$stmt = $conn->prepare("SELECT b.id, b.name FROM batch_teachers bt JOIN batches b ON bt.batch_id=b.id WHERE bt.teacher_id=? ORDER BY b.name");
$stmt->bind_param('i',$uid); $stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// Summary counts
$r = $conn->prepare("
    SELECT
      COUNT(*) as total,
      SUM(priority='urgent') as urgent,
      SUM(priority='important') as important,
      SUM(is_pinned=1) as pinned,
      SUM(status='draft') as drafts
    FROM announcements
    WHERE batch_id IS NULL OR batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)
");
$r->bind_param('i',$uid); $r->execute();
$summary = $r->get_result()->fetch_assoc(); $r->close();

include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- PAGE HEADER -->
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title">Announcements</h1>
          <p class="page-subtitle">Post updates and notices to your batch students</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <!-- Summary chips -->
          <?php if ((int)$summary['urgent'] > 0): ?>
            <span class="badge badge-danger badge-pulse" style="font-size:0.78rem;padding:5px 12px;">
              <span class="badge-dot" style="background:#ef4444;"></span><?= $summary['urgent'] ?> Urgent
            </span>
          <?php endif; ?>
          <?php if ((int)$summary['drafts'] > 0): ?>
            <span class="badge badge-secondary" style="font-size:0.78rem;padding:5px 12px;">
              <?= $summary['drafts'] ?> Draft<?= $summary['drafts']>1?'s':'' ?>
            </span>
          <?php endif; ?>
          <button class="btn btn-primary" onclick="openAdd()">
            <i data-lucide="plus" style="width:16px;height:16px;"></i> New Announcement
          </button>
        </div>
      </div>

      <!-- STATS ROW -->
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
        <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
          <div style="width:38px;height:38px;border-radius:var(--radius);background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="megaphone" style="width:17px;height:17px;color:var(--primary);"></i>
          </div>
          <div>
            <div style="font-size:1.3rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;"><?= $summary['total'] ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted);margin-top:1px;">Total</div>
          </div>
        </div>
        <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
          <div style="width:38px;height:38px;border-radius:var(--radius);background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="alert-triangle" style="width:17px;height:17px;color:var(--danger);"></i>
          </div>
          <div>
            <div style="font-size:1.3rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;color:var(--danger);"><?= $summary['urgent'] ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted);margin-top:1px;">Urgent</div>
          </div>
        </div>
        <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
          <div style="width:38px;height:38px;border-radius:var(--radius);background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="pin" style="width:17px;height:17px;color:var(--primary);"></i>
          </div>
          <div>
            <div style="font-size:1.3rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;"><?= $summary['pinned'] ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted);margin-top:1px;">Pinned</div>
          </div>
        </div>
        <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
          <div style="width:38px;height:38px;border-radius:var(--radius);background:rgba(100,116,139,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="file-text" style="width:17px;height:17px;color:var(--text-muted);"></i>
          </div>
          <div>
            <div style="font-size:1.3rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;"><?= $summary['drafts'] ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted);margin-top:1px;">Drafts</div>
          </div>
        </div>
      </div>

      <!-- FILTERS -->
      <div class="card" style="margin-bottom:20px;">
        <div style="padding:14px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <div class="search-box" style="flex:1;min-width:200px;max-width:320px;">
            <i data-lucide="search" class="search-box-icon"></i>
            <input type="text" id="ann-search" class="form-control" placeholder="Search announcements…" style="padding-left:36px;" autocomplete="off">
          </div>
          <select id="filter-priority" class="form-control" style="width:155px;">
            <option value="">All Priorities</option>
            <option value="urgent">🔴 Urgent</option>
            <option value="important">⚠️ Important</option>
            <option value="normal">Normal</option>
          </select>
          <select id="filter-batch" class="form-control" style="width:175px;">
            <option value="">All Batches</option>
            <?php foreach ($batches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label style="display:flex;align-items:center;gap:7px;font-size:0.85rem;color:var(--text-secondary);cursor:pointer;white-space:nowrap;">
            <input type="checkbox" id="filter-pinned" style="accent-color:var(--primary);width:15px;height:15px;">
            Pinned only
          </label>
          <button class="btn btn-ghost btn-sm" onclick="resetFilters()" style="margin-left:auto;">
            <i data-lucide="x" style="width:13px;height:13px;"></i> Clear
          </button>
        </div>
      </div>

      <!-- LIST -->
      <div id="ann-list"></div>
      <div id="ann-pagination" style="margin-top:4px;"></div>

    </main>
  </div>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="add-ann-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary">
        <i data-lucide="megaphone" style="width:20px;height:20px;"></i>
      </div>
      <h3 class="modal-title" id="ann-modal-title">New Announcement</h3>
      <button class="modal-close" data-modal-close="add-ann">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <form id="ann-form" onsubmit="submitAnn(event)">
      <input type="hidden" name="action" id="ann-action" value="create">
      <input type="hidden" name="announcement_id" id="ann-id">
      <div class="modal-body" style="padding:24px 28px;">

        <div class="form-group">
          <label class="form-label">Title <span class="required">*</span></label>
          <input type="text" name="title" id="ann-title" class="form-control" required placeholder="Write a clear, descriptive title…">
        </div>

        <div class="form-group">
          <label class="form-label">Content <span class="required">*</span></label>
          <textarea name="content" id="ann-content" class="form-control" rows="5" required placeholder="Write your announcement details here…" style="resize:vertical;"></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label">
              Batch
              <span style="color:var(--text-muted);font-weight:400;font-size:0.8rem;"> — leave blank for all your batches</span>
            </label>
            <select name="batch_id" id="ann-batch" class="form-control">
              <option value="">All My Batches</option>
              <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" id="ann-priority" class="form-control" onchange="updatePriorityPreview(this.value)">
              <option value="normal">Normal</option>
              <option value="important">⚠️ Important</option>
              <option value="urgent">🔴 Urgent</option>
            </select>
          </div>
        </div>

        <!-- Priority Preview -->
        <div id="priority-preview" style="display:none;padding:12px 16px;border-radius:var(--radius);border-left:3px solid var(--danger);background:rgba(239,68,68,0.06);margin-bottom:16px;font-size:0.84rem;color:var(--text-secondary);">
          <strong style="color:var(--danger);">⚠ Urgent</strong> — this will be highlighted prominently for all recipients.
        </div>

        <div style="display:flex;align-items:center;gap:24px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.875rem;color:var(--text-secondary);">
            <div class="toggle">
              <input type="checkbox" name="is_pinned" id="ann-pin" value="1">
              <span class="toggle-slider"></span>
            </div>
            <span>📌 Pin to top</span>
          </label>

          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.875rem;color:var(--text-secondary);">
            <div class="toggle">
              <input type="checkbox" name="save_as_draft" id="ann-draft" value="1">
              <span class="toggle-slider"></span>
            </div>
            <span>Save as draft</span>
          </label>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close="add-ann">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ann-submit">
          <i data-lucide="send" style="width:15px;height:15px;"></i>
          <span class="btn-text">Publish</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let annPage = 1, annSearch = '', annPriority = '', annBatch = '', annPinned = false;

async function loadAnn() {
  const list = document.getElementById('ann-list');
  list.innerHTML = Array(4).fill(0).map(() => `
    <div class="card" style="margin-bottom:12px;padding:20px 24px;">
      <div style="display:flex;gap:14px;align-items:flex-start;">
        <div class="skeleton" style="width:40px;height:40px;border-radius:var(--radius);flex-shrink:0;"></div>
        <div style="flex:1;">
          <div class="skeleton skeleton-text" style="width:50%;margin-bottom:10px;"></div>
          <div class="skeleton skeleton-text" style="width:88%;margin-bottom:6px;"></div>
          <div class="skeleton skeleton-text" style="width:65%;"></div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;">
          <div class="skeleton" style="width:28px;height:28px;border-radius:var(--radius-sm);"></div>
          <div class="skeleton" style="width:28px;height:28px;border-radius:var(--radius-sm);"></div>
        </div>
      </div>
    </div>`).join('');

  try {
    const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', {
      action: 'list', page: annPage, per_page: 10,
      priority: annPriority, batch_id: annBatch, search: annSearch
    });
    if (res.status === 'success') {
      let items = res.data.announcements;
      if (annPinned) items = items.filter(a => a.is_pinned == 1);
      renderAnn(items);
      renderPag(annPinned ? items.length : res.data.total, res.data.page, res.data.per_page);
    } else {
      Toast.error(res.message);
    }
  } catch(e) {
    Toast.error('Network error');
  }
}

function renderAnn(items) {
  const list = document.getElementById('ann-list');
  if (!items || !items.length) {
    list.innerHTML = `
      <div class="card">
        <div class="empty-state" style="padding:60px 24px;">
          <div class="empty-state-icon">📢</div>
          <div class="empty-state-title">No announcements yet</div>
          <div class="empty-state-text">Post your first announcement to your students</div>
          <button class="btn btn-primary" style="margin-top:16px;" onclick="openAdd()">
            <i data-lucide="plus" style="width:15px;height:15px;"></i> New Announcement
          </button>
        </div>
      </div>`;
    if(window.lucide) lucide.createIcons({nodes:[list]});
    return;
  }

  list.innerHTML = items.map(a => {
    const isUrgent    = a.priority === 'urgent';
    const isImportant = a.priority === 'important';
    const isDraft     = a.status === 'draft';
    const isPinned    = a.is_pinned == 1;

    const accentColor = isUrgent ? '#ef4444' : isImportant ? '#f59e0b' : 'var(--primary)';
    const iconBg      = isUrgent ? 'rgba(239,68,68,0.1)' : isImportant ? 'rgba(245,158,11,0.1)' : 'rgba(99,102,241,0.1)';
    const iconName    = isUrgent ? 'alert-triangle' : isImportant ? 'star' : 'megaphone';

    const priorityBadge = isUrgent
      ? `<span class="badge badge-danger badge-pulse" style="font-size:0.7rem;"><span class="badge-dot" style="background:#ef4444;"></span>Urgent</span>`
      : isImportant
      ? `<span class="badge badge-warning" style="font-size:0.7rem;"><span class="badge-dot" style="background:#f59e0b;"></span>Important</span>`
      : '';

    const draftBadge = isDraft
      ? `<span class="badge badge-secondary" style="font-size:0.7rem;">Draft</span>`
      : '';

    const pinTag = isPinned
      ? `<span class="badge badge-secondary" style="font-size:0.7rem;gap:4px;"><i data-lucide="pin" style="width:10px;height:10px;"></i>Pinned</span>`
      : '';

    const snippet = a.content.length > 130 ? escapeHtml(a.content.slice(0,130)) + '…' : escapeHtml(a.content);

    return `
      <div class="card" style="margin-bottom:12px;border-left:3px solid ${accentColor};${isDraft?'opacity:0.75;':''}" id="ann-card-${a.id}">
        <div style="padding:18px 22px;">
          <div style="display:flex;align-items:flex-start;gap:14px;">
            <div style="width:40px;height:40px;border-radius:var(--radius);background:${iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i data-lucide="${iconName}" style="width:18px;height:18px;color:${accentColor};"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <h3 style="font-family:'Poppins',sans-serif;font-size:0.95rem;font-weight:700;margin:0;color:var(--text);">${escapeHtml(a.title)}</h3>
                ${priorityBadge}${pinTag}${draftBadge}
              </div>
              <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.65;margin:0 0 10px;">${snippet}</p>
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <div style="display:flex;align-items:center;gap:10px;font-size:0.74rem;color:var(--text-muted);">
                  <span style="display:flex;align-items:center;gap:4px;">
                    <i data-lucide="layers" style="width:11px;height:11px;"></i>
                    ${escapeHtml(a.batch_name || 'All My Batches')}
                  </span>
                </div>
                <span style="font-size:0.73rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;">
                  <i data-lucide="clock" style="width:11px;height:11px;"></i>
                  ${timeAgoJS(a.created_at)}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;align-items:center;gap:5px;flex-shrink:0;">
              <button class="action-btn" onclick="togglePin(${a.id},${a.is_pinned})"
                style="background:${isPinned?'rgba(99,102,241,0.12)':''};"
                data-tooltip="${isPinned?'Unpin':'Pin'}">
                <i data-lucide="pin" style="width:13px;height:13px;color:${isPinned?'var(--primary)':'inherit'};"></i>
              </button>
              <button class="action-btn action-btn-edit" onclick="editAnn(${a.id})" data-tooltip="Edit">
                <i data-lucide="edit-2" style="width:13px;height:13px;"></i>
              </button>
              <button class="action-btn action-btn-delete" onclick="deleteAnn(${a.id},'${escapeHtml(a.title)}')" data-tooltip="Delete">
                <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
              </button>
            </div>
          </div>
        </div>
      </div>`;
  }).join('');

  if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('ann-list')] });
}

function renderPag(total, page, perPage) {
  const el = document.getElementById('ann-pagination');
  if (!total || total <= perPage) { el.innerHTML = ''; return; }
  const tp = Math.ceil(total/perPage), s=(page-1)*perPage+1, e=Math.min(page*perPage,total);
  let pgs = '';
  for (let i=1; i<=tp; i++) {
    if(i===1||i===tp||(i>=page-2&&i<=page+2)) pgs+=`<a href="#" class="page-btn${i===page?' active':''}" onclick="annPage=${i};loadAnn();return false;">${i}</a>`;
    else if(i===page-3||i===page+3) pgs+=`<span class="page-ellipsis">…</span>`;
  }
  el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total} announcements</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="annPage=${page-1};loadAnn();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="annPage=${page+1};loadAnn();return false;">›</a></div></div>`;
}

// ── Modal helpers ─────────────────────────────────────────
function openAdd() {
  document.getElementById('ann-modal-title').textContent = 'New Announcement';
  document.getElementById('ann-action').value = 'create';
  document.getElementById('ann-id').value = '';
  document.getElementById('ann-form').reset();
  document.getElementById('priority-preview').style.display = 'none';
  document.getElementById('ann-submit').querySelector('.btn-text').textContent = 'Publish';
  Modal.open('add-ann');
}

async function editAnn(id) {
  const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', { action: 'get_one', announcement_id: id });
  if (res.status !== 'success') { Toast.error(res.message); return; }
  const a = res.data;
  document.getElementById('ann-modal-title').textContent = 'Edit Announcement';
  document.getElementById('ann-action').value = 'update';
  document.getElementById('ann-id').value = a.id;
  document.getElementById('ann-title').value   = a.title || '';
  document.getElementById('ann-content').value = a.content || '';
  document.getElementById('ann-batch').value    = a.batch_id || '';
  document.getElementById('ann-priority').value = a.priority || 'normal';
  document.getElementById('ann-pin').checked    = a.is_pinned == 1;
  document.getElementById('ann-draft').checked  = a.status === 'draft';
  document.getElementById('ann-submit').querySelector('.btn-text').textContent = 'Save Changes';
  updatePriorityPreview(a.priority || 'normal');
  Modal.open('add-ann');
}

async function submitAnn(e) {
  e.preventDefault();
  const form = e.target, btn = document.getElementById('ann-submit');
  const data = { csrf_token: window.CSRF_TOKEN };
  new FormData(form).forEach((v,k) => data[k] = v);
  data.is_pinned = form.querySelector('[name=is_pinned]')?.checked ? 1 : 0;
  data.status    = form.querySelector('[name=save_as_draft]')?.checked ? 'draft' : 'published';
  delete data.save_as_draft;
  btn.disabled = true; btn.classList.add('btn-loading');
  try {
    const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', data);
    if (res.status === 'success') {
      Toast.success(data.status === 'draft' ? 'Saved as draft' : (data.action === 'create' ? 'Announcement published!' : 'Announcement updated!'));
      Modal.close('add-ann');
      loadAnn();
    } else {
      Toast.error(res.message);
    }
  } finally {
    btn.disabled = false; btn.classList.remove('btn-loading');
  }
}

async function deleteAnn(id, title) {
  Modal.confirm({
    title: 'Delete Announcement',
    message: `Are you sure you want to delete <strong>${escapeHtml(title)}</strong>? This cannot be undone.`,
    confirmText: 'Delete', confirmClass: 'btn-danger',
    icon: '🗑️', iconClass: 'modal-icon-danger',
    onConfirm: async () => {
      const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', { action: 'delete', announcement_id: id });
      if (res.status === 'success') { Toast.success('Deleted successfully'); loadAnn(); }
      else throw new Error(res.message);
    }
  });
}

async function togglePin(id, currentState) {
  const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', { action: 'toggle_pin', announcement_id: id });
  if (res.status === 'success') {
    Toast.success(res.message);
    loadAnn();
  } else {
    Toast.error(res.message);
  }
}

function updatePriorityPreview(val) {
  const el = document.getElementById('priority-preview');
  if (val === 'urgent') {
    el.style.display = 'block';
    el.style.borderLeftColor = '#ef4444';
    el.style.background = 'rgba(239,68,68,0.06)';
    el.innerHTML = '<strong style="color:var(--danger);">⚠ Urgent</strong> — this will be prominently highlighted for all recipients.';
  } else if (val === 'important') {
    el.style.display = 'block';
    el.style.borderLeftColor = '#f59e0b';
    el.style.background = 'rgba(245,158,11,0.06)';
    el.innerHTML = '<strong style="color:var(--warning);">⚠️ Important</strong> — students will see this flagged as important.';
  } else {
    el.style.display = 'none';
  }
}

function resetFilters() {
  annSearch=''; annPriority=''; annBatch=''; annPinned=false; annPage=1;
  document.getElementById('ann-search').value='';
  document.getElementById('filter-priority').value='';
  document.getElementById('filter-batch').value='';
  document.getElementById('filter-pinned').checked=false;
  loadAnn();
}

const debouncedSearch = debounce(v => { annSearch=v; annPage=1; loadAnn(); }, 350);
document.getElementById('ann-search').addEventListener('input', e => debouncedSearch(e.target.value));
document.getElementById('filter-priority').addEventListener('change', e => { annPriority=e.target.value; annPage=1; loadAnn(); });
document.getElementById('filter-batch').addEventListener('change', e => { annBatch=e.target.value; annPage=1; loadAnn(); });
document.getElementById('filter-pinned').addEventListener('change', e => { annPinned=e.target.checked; annPage=1; loadAnn(); });

function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function timeAgoJS(dt) { const s=Math.floor((Date.now()-new Date(dt))/1000); if(s<60)return'Just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';return Math.floor(s/86400)+'d ago'; }

document.addEventListener('DOMContentLoaded', loadAnn);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>