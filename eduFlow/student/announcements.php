<?php
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle   = 'Announcements';
$breadcrumbs = [['label'=>'Student','url'=>BASE_PATH . '/student/'],['label'=>'Announcements']];
$uid = (int)$currentUser['id'];

// Summary counts for header
$r = $conn->prepare("
    SELECT
      COUNT(*) as total,
      SUM(priority='urgent') as urgent,
      SUM(priority='important') as important,
      SUM(is_pinned=1) as pinned
    FROM announcements
    WHERE status='published'
      AND (batch_id IS NULL OR batch_id IN (SELECT batch_id FROM batch_students WHERE student_id=?))
");
$r->bind_param('i',$uid); $r->execute();
$summary = $r->get_result()->fetch_assoc(); $r->close();

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Announcements page responsive ──────────────────────── */
.ann-filter-card {
  margin-bottom: 20px;
}
.ann-filter-row {
  padding: 14px 20px;
  display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
}
.ann-filter-row .search-box { flex: 1; min-width: 180px; max-width: 340px; }
.ann-filter-row select      { width: 155px; flex-shrink: 0; }
.ann-filter-row .ann-pinned-label {
  display: flex; align-items: center; gap: 7px;
  font-size: 0.86rem; color: var(--text-secondary);
  cursor: pointer; white-space: nowrap;
}
.ann-filter-row .btn-clear { margin-left: auto; }

/* Announcement card */
.ann-card-inner { padding: 18px 20px; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 768px) {
  .ann-filter-row { padding: 12px 14px; gap: 8px; }
  .ann-filter-row .search-box { max-width: 100%; }
  .ann-filter-row select { width: 100%; }
  .ann-filter-row .btn-clear { margin-left: 0; }
  .ann-card-inner { padding: 14px 16px; }
}

@media (max-width: 540px) {
  .ann-filter-row { flex-direction: column; align-items: stretch; }
  .ann-filter-row .search-box { max-width: 100%; }
  .ann-filter-row .ann-pinned-label { justify-content: flex-start; }
  /* Page header chips row */
  .ann-header-chips { justify-content: flex-start !important; }
  #view-ann-overlay .modal-body { padding: 16px 16px; }
}
</style>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- PAGE HEADER -->
      <div class="page-header" style="margin-bottom:24px;">
        <div>
          <h1 class="page-title">Announcements</h1>
          <p class="page-subtitle">Updates and notices from your teachers &amp; admins</p>
        </div>
        <!-- Summary chips -->
        <div class="ann-header-chips" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <?php if ((int)$summary['urgent'] > 0): ?>
            <span class="badge badge-danger badge-pulse" style="font-size:0.78rem;padding:5px 12px;">
              <span class="badge-dot" style="background:#ef4444;"></span>
              <?= $summary['urgent'] ?> Urgent
            </span>
          <?php endif; ?>
          <?php if ((int)$summary['important'] > 0): ?>
            <span class="badge badge-warning" style="font-size:0.78rem;padding:5px 12px;">
              <span class="badge-dot" style="background:#f59e0b;"></span>
              <?= $summary['important'] ?> Important
            </span>
          <?php endif; ?>
          <span class="badge badge-secondary" style="font-size:0.78rem;padding:5px 12px;">
            <?= $summary['total'] ?> total
          </span>
        </div>
      </div>

      <!-- FILTERS -->
      <div class="card ann-filter-card">
        <div class="ann-filter-row">
          <div class="search-box">
            <i data-lucide="search" class="search-box-icon"></i>
            <input type="text" id="ann-search" class="form-control" placeholder="Search announcements…" style="padding-left:36px;" autocomplete="off">
          </div>
          <select id="filter-priority" class="form-control">
            <option value="">All Priorities</option>
            <option value="urgent">🔴 Urgent</option>
            <option value="important">⚠️ Important</option>
            <option value="normal">Normal</option>
          </select>
          <select id="filter-batch" class="form-control">
            <option value="">All Batches</option>
            <option value="0">🌐 Global Only</option>
          </select>
          <label class="ann-pinned-label">
            <input type="checkbox" id="filter-pinned" style="accent-color:var(--primary);width:15px;height:15px;">
            Pinned only
          </label>
          <button class="btn btn-ghost btn-sm btn-clear" onclick="resetFilters()">
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

<!-- ANNOUNCEMENT DETAIL MODAL -->
<div class="modal-overlay" id="view-ann-overlay">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-icon modal-icon-primary" id="view-modal-icon">
        <i data-lucide="megaphone" style="width:20px;height:20px;"></i>
      </div>
      <h3 class="modal-title" id="view-ann-title">Announcement</h3>
      <button class="modal-close" onclick="Modal.close('view-ann')">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <div class="modal-body" id="view-ann-body" style="padding:24px 28px;"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="Modal.close('view-ann')">Close</button>
    </div>
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
          <div class="skeleton skeleton-text" style="width:55%;margin-bottom:10px;"></div>
          <div class="skeleton skeleton-text" style="width:90%;margin-bottom:6px;"></div>
          <div class="skeleton skeleton-text" style="width:75%;"></div>
        </div>
      </div>
    </div>`).join('');

  try {
    const res = await ajax(window.LMS_BASE+'/ajax/announcements.ajax.php', {
      action: 'list', page: annPage, per_page: 10,
      priority: annPriority, batch_id: annBatch,
      search: annSearch
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
          <div class="empty-state-title">No announcements found</div>
          <div class="empty-state-text">Try adjusting your filters or check back later</div>
        </div>
      </div>`;
    return;
  }

  list.innerHTML = items.map(a => {
    const isUrgent    = a.priority === 'urgent';
    const isImportant = a.priority === 'important';
    const isPinned    = a.is_pinned == 1;

    const accentColor = isUrgent ? '#ef4444' : isImportant ? '#f59e0b' : 'var(--primary)';
    const iconBg      = isUrgent ? 'rgba(239,68,68,0.1)' : isImportant ? 'rgba(245,158,11,0.1)' : 'rgba(99,102,241,0.1)';
    const iconName    = isUrgent ? 'alert-triangle' : isImportant ? 'star' : 'bell';

    const badge = isUrgent
      ? `<span class="badge badge-danger badge-pulse" style="font-size:0.7rem;"><span class="badge-dot" style="background:#ef4444;"></span>Urgent</span>`
      : isImportant
      ? `<span class="badge badge-warning" style="font-size:0.7rem;"><span class="badge-dot" style="background:#f59e0b;"></span>Important</span>`
      : '';

    const pinTag = isPinned
      ? `<span class="badge badge-secondary" style="font-size:0.7rem;gap:4px;"><i data-lucide="pin" style="width:10px;height:10px;"></i>Pinned</span>`
      : '';

    const snippet = a.content.length > 140 ? escapeHtml(a.content.slice(0,140)) + '…' : escapeHtml(a.content);

    return `
      <div class="card" style="margin-bottom:12px;border-left:3px solid ${accentColor};cursor:pointer;transition:box-shadow .15s,transform .15s;"
           onmouseover="this.style.boxShadow='var(--shadow-lg)';this.style.transform='translateY(-1px)'"
           onmouseout="this.style.boxShadow='';this.style.transform=''"
           onclick="viewAnn(${JSON.stringify(a).replace(/"/g,'&quot;')})">
        <div class="ann-card-inner">
          <div style="display:flex;align-items:flex-start;gap:14px;">
            <div style="width:40px;height:40px;border-radius:var(--radius);background:${iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i data-lucide="${iconName}" style="width:18px;height:18px;color:${accentColor};"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <h3 style="font-family:'Poppins',sans-serif;font-size:0.95rem;font-weight:700;margin:0;color:var(--text);">${escapeHtml(a.title)}</h3>
                ${badge}${pinTag}
              </div>
              <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.65;margin:0 0 10px;">${snippet}</p>
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <div style="display:flex;align-items:center;gap:10px;font-size:0.74rem;color:var(--text-muted);">
                  <span style="display:flex;align-items:center;gap:4px;">
                    <i data-lucide="layers" style="width:11px;height:11px;"></i>
                    ${escapeHtml(a.batch_name || 'Global')}
                  </span>
                  <span>·</span>
                  <span style="display:flex;align-items:center;gap:4px;">
                    <i data-lucide="user" style="width:11px;height:11px;"></i>
                    ${escapeHtml(a.creator_name || '—')}
                  </span>
                </div>
                <span style="font-size:0.73rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;">
                  <i data-lucide="clock" style="width:11px;height:11px;"></i>
                  ${timeAgoJS(a.created_at)}
                </span>
              </div>
            </div>
            <div style="flex-shrink:0;color:var(--text-muted);">
              <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
            </div>
          </div>
        </div>
      </div>`;
  }).join('');

  if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('ann-list')] });
}

function viewAnn(a) {
  const isUrgent    = a.priority === 'urgent';
  const isImportant = a.priority === 'important';
  const accentColor = isUrgent ? '#ef4444' : isImportant ? '#f59e0b' : 'var(--primary)';
  const iconName    = isUrgent ? 'alert-triangle' : isImportant ? 'star' : 'megaphone';

  const badge = isUrgent
    ? `<span class="badge badge-danger badge-pulse"><span class="badge-dot" style="background:#ef4444;"></span>Urgent</span>`
    : isImportant
    ? `<span class="badge badge-warning"><span class="badge-dot" style="background:#f59e0b;"></span>Important</span>`
    : `<span class="badge badge-secondary">Normal</span>`;

  document.getElementById('view-ann-title').textContent = a.title;
  document.getElementById('view-modal-icon').style.background = `${accentColor}18`;
  document.getElementById('view-modal-icon').style.color = accentColor;
  document.getElementById('view-modal-icon').innerHTML = `<i data-lucide="${iconName}" style="width:20px;height:20px;"></i>`;

  document.getElementById('view-ann-body').innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
      ${badge}
      ${a.is_pinned==1 ? `<span class="badge badge-secondary" style="gap:4px;"><i data-lucide="pin" style="width:11px;height:11px;"></i>Pinned</span>` : ''}
    </div>
    <div style="font-size:0.92rem;color:var(--text-secondary);line-height:1.8;white-space:pre-wrap;margin-bottom:22px;">${escapeHtml(a.content)}</div>
    <div style="border-top:1px solid var(--border);padding-top:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;align-items:center;gap:16px;font-size:0.78rem;color:var(--text-muted);">
        <span style="display:flex;align-items:center;gap:5px;"><i data-lucide="layers" style="width:12px;height:12px;"></i>${escapeHtml(a.batch_name||'Global')}</span>
        <span style="display:flex;align-items:center;gap:5px;"><i data-lucide="user" style="width:12px;height:12px;"></i>${escapeHtml(a.creator_name||'—')}</span>
      </div>
      <span style="font-size:0.78rem;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
        <i data-lucide="clock" style="width:12px;height:12px;"></i>${timeAgoJS(a.created_at)}
      </span>
    </div>`;

  Modal.open('view-ann');
  if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('view-ann-body'), document.getElementById('view-modal-icon')] });
}

function renderPag(total, page, perPage) {
  const el = document.getElementById('ann-pagination');
  if (!total || total <= perPage) { el.innerHTML = ''; return; }
  const tp = Math.ceil(total/perPage), s=(page-1)*perPage+1, e=Math.min(page*perPage,total);
  let pgs = '';
  for (let i=1; i<=tp; i++) {
    if (i===1||i===tp||(i>=page-2&&i<=page+2)) pgs += `<a href="#" class="page-btn${i===page?' active':''}" onclick="annPage=${i};loadAnn();return false;">${i}</a>`;
    else if (i===page-3||i===page+3) pgs += `<span class="page-ellipsis">…</span>`;
  }
  el.innerHTML = `<div class="pagination-wrapper"><span class="pagination-info">Showing ${s}–${e} of ${total} announcements</span><div class="pagination-controls"><a href="#" class="page-btn${page<=1?' disabled':''}" onclick="annPage=${page-1};loadAnn();return false;">‹</a>${pgs}<a href="#" class="page-btn${page>=tp?' disabled':''}" onclick="annPage=${page+1};loadAnn();return false;">›</a></div></div>`;
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