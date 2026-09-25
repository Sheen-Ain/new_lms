<?php
// ============================================================
// SIDEBAR — Role-based navigation
// ============================================================
// Requires: $currentUser, $userRole, $conn

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null)
{
  global $currentPage, $currentDir;
  if ($dir && $currentDir !== $dir) return '';
  if (is_array($page)) return in_array($currentPage, $page) ? 'active' : '';
  return $currentPage === $page ? 'active' : '';
}

// Get pending submissions count for badge (teachers + admins)
$pendingSubmissions   = 0;
$pendingApplications  = 0;
$unreadAnnouncements  = 0;

if (isset($conn) && isset($currentUser['id'])) {
  if (in_array($userRole, ['admin', 'teacher'])) {
    if ($userRole === 'admin') {
      $r = $conn->query("SELECT COUNT(*) as cnt FROM submissions WHERE status='submitted'");
    } else {
      $r = $conn->query("
                SELECT COUNT(*) as cnt FROM submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN batch_teachers bt ON a.batch_id = bt.batch_id
                WHERE bt.teacher_id = {$currentUser['id']} AND s.status = 'submitted'
            ");
    }
    if ($r) $pendingSubmissions = (int)($r->fetch_assoc()['cnt'] ?? 0);

    if ($userRole === 'admin') {
      $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM course_applications WHERE status IN ('pending','test_submitted')");
      if ($r2) $pendingApplications = (int)($r2->fetch_assoc()['cnt'] ?? 0);
    }
  }
}

$navByRole = [
  'admin' => [
    'main' => [
      ['page' => 'index',        'label' => 'Dashboard',     'icon' => 'layout-dashboard', 'dir' => 'admin'],
      ['page' => 'users',        'label' => 'Users',         'icon' => 'users',            'dir' => 'admin'],
      ['page' => 'courses',      'label' => 'Courses',       'icon' => 'book-open',        'dir' => 'admin'],
      ['page' => 'batches',      'label' => 'Batches',       'icon' => 'layers',           'dir' => 'admin'],
      ['page' => 'topics',       'label' => 'Topics',        'icon' => 'file-text',        'dir' => 'admin'],
      ['page' => 'assignments',  'label' => 'Assignments',   'icon' => 'clipboard-list',   'dir' => 'admin'],
      ['page' => 'submissions',  'label' => 'Submissions',   'icon' => 'send',             'dir' => 'admin', 'badge' => $pendingSubmissions],
      ['page' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone',        'dir' => 'admin'],
      ['page' => 'live',          'label' => 'Live Sessions', 'icon' => 'video',             'dir' => 'admin'],
      ['page' => 'tests',         'label' => 'Tests',         'icon' => 'file-check',        'dir' => 'admin'],
      ['page' => 'applications',  'label' => 'Applications',  'icon' => 'inbox',             'dir' => 'admin', 'badge' => $pendingApplications],
      ['page' => 'feedback',      'label' => 'Feedback',      'icon' => 'message-square',    'dir' => 'admin'],
      ['page' => 'activity-logs', 'label' => 'Activity Logs', 'icon' => 'activity',         'dir' => 'admin'],
    ],
  ],
  'teacher' => [
    'main' => [
      ['page' => 'index',        'label' => 'Dashboard',     'icon' => 'layout-dashboard', 'dir' => 'teacher'],
      ['page' => 'my-batches',   'label' => 'My Batches',    'icon' => 'layers',           'dir' => 'teacher'],
      ['page' => 'topics',       'label' => 'Topics',        'icon' => 'file-text',        'dir' => 'teacher'],
      ['page' => 'assignments',  'label' => 'Assignments',   'icon' => 'clipboard-list',   'dir' => 'teacher'],
      ['page' => 'submissions',  'label' => 'Submissions',   'icon' => 'send',             'dir' => 'teacher', 'badge' => $pendingSubmissions],
      ['page' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone',        'dir' => 'teacher'],
      ['page' => 'live',          'label' => 'Live Sessions', 'icon' => 'video',             'dir' => 'teacher'],
      ['page' => 'tests',         'label' => 'Tests',         'icon' => 'file-check',        'dir' => 'teacher'],
      ['page' => 'feedback',      'label' => 'Feedback',      'icon' => 'message-square',    'dir' => 'teacher'],
    ],
  ],
  'student' => [
    'main' => [
      ['page' => 'index',        'label' => 'Dashboard',     'icon' => 'layout-dashboard', 'dir' => 'student'],
      ['page' => 'my-courses',   'label' => 'My Courses',    'icon' => 'book-open',        'dir' => 'student'],
      ['page' => 'topics',       'label' => 'Topics',        'icon' => 'file-text',        'dir' => 'student'],
      ['page' => 'assignments',  'label' => 'Assignments',   'icon' => 'clipboard-list',   'dir' => 'student'],
      ['page' => 'tests',        'label' => 'My Tests',      'icon' => 'file-check',       'dir' => 'student'],
      ['page' => 'feedback',     'label' => 'Feedback',      'icon' => 'message-square',   'dir' => 'student'],
      ['page' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone',        'dir' => 'student'],
    ],
  ],
];

$navItems  = $navByRole[$userRole]['main'] ?? [];
$baseDir   = '/' . $userRole;

// Get user's all roles for role switching
$allRoles = array_filter(explode(',', $currentUser['all_roles'] ?? ''));

// Profile picture/initials
$avatarHtml = userAvatar($currentUser, 36);
?>

<aside class="sidebar" id="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="sidebar-logo">
      <i data-lucide="graduation-cap" style="width:22px;height:22px;color:#fff;"></i>
    </div>
    <div class="sidebar-brand-text">
      <span class="sidebar-brand-name">EduFlow</span>
      <span class="sidebar-brand-sub">Learning Platform</span>
    </div>
  </div>

  <!-- User Info -->
  <div class="sidebar-user">
    <?= $avatarHtml ?>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= e($currentUser['full_name']) ?></div>
      <div class="sidebar-user-role"><?= ucfirst(e($userRole)) ?></div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav" role="navigation">
    <div class="sidebar-section-label">Navigation</div>

    <?php foreach ($navItems as $item): ?>
      <?php
      $href   = BASE_PATH . "/{$item['dir']}/{$item['page']}.php";
      $active = isActive($item['page'], $item['dir']);
      $badge  = $item['badge'] ?? 0;
      ?>
      <a href="<?= $href ?>"
        class="nav-item <?= $active ?>"
        data-tooltip="<?= e($item['label']) ?>"
        aria-current="<?= $active ? 'page' : 'false' ?>">
        <i data-lucide="<?= e($item['icon']) ?>" class="nav-item-icon"></i>
        <span><?= e($item['label']) ?></span>
        <?php if ($badge > 0): ?>
          <span class="nav-item-badge"><?= $badge > 99 ? '99+' : $badge ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <!-- Role switching if multi-role -->
    <?php if (count($allRoles) > 1): ?>
      <div class="sidebar-section-label" style="margin-top:16px;">Switch Role</div>
      <?php
      $roleMap = [
        'admin'   => ['icon' => 'shield',     'label' => 'Admin'],
        'teacher' => ['icon' => 'presentation', 'label' => 'Teacher'],
        'student' => ['icon' => 'graduation-cap', 'label' => 'Student'],
      ];
      foreach ($allRoles as $role):
        if ($role === $userRole) continue;
        $rm = $roleMap[$role] ?? ['icon' => 'user', 'label' => ucfirst($role)];
      ?>
        <a href="#"
          class="nav-item"
          onclick="switchRole('<?= e($role) ?>')"
          data-tooltip="Switch to <?= e($rm['label']) ?>">
          <i data-lucide="<?= e($rm['icon']) ?>" class="nav-item-icon"></i>
          <span>As <?= e($rm['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <!-- ── Online Users Panel ─────────────────────────────── -->
  <div class="sidebar-presence" id="sidebar-presence">

    <!-- Header / Toggle -->
    <div class="sidebar-presence-header" id="presence-toggle" role="button" aria-expanded="true" tabindex="0">
      <span style="font-size:0.67rem;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.3);flex:1;">Online</span>
      <span class="presence-count-badge" id="presence-badge" style="display:none;"></span>
      <i data-lucide="chevron-down" id="presence-chevron" style="width:12px;height:12px;color:rgba(255,255,255,0.3);transition:transform 0.25s;flex-shrink:0;"></i>
    </div>

    <!-- User List -->
    <div class="presence-list" id="presence-list">
      <!-- Skeleton while loading -->
      <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="presence-skeleton">
          <div class="presence-skel-avatar"></div>
          <div style="flex:1;display:flex;flex-direction:column;gap:5px;">
            <div class="presence-skel-line" style="width:70%;"></div>
            <div class="presence-skel-line" style="width:45%;opacity:0.5;"></div>
          </div>
        </div>
      <?php endfor; ?>
    </div>

  </div>

  <!-- Footer links -->
  <div class="sidebar-footer">
    <a href="<?= BASE_PATH ?>/includes/profile.php" class="nav-item <?= isActive('profile') ?>" data-tooltip="Profile">
      <i data-lucide="user-circle" class="nav-item-icon"></i>
      <span>Profile</span>
    </a>
    <a href="#" class="nav-item" style="color:rgba(239,68,68,0.7);" data-tooltip="Logout"
      onclick="showLogoutConfirm(); return false;">
      <i data-lucide="log-out" class="nav-item-icon"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>

<script>
  async function switchRole(role) {
    try {
      const res = await ajax(window.LMS_BASE + '/ajax/profile.ajax.php', {
        action: 'switch_role',
        role
      });
      if (res.status === 'success') {
        Toast.success('Switched to ' + role + ' panel');
        setTimeout(() => window.location.href = window.LMS_BASE + '/' + role + '/', 800);
      } else {
        Toast.error(res.message);
      }
    } catch (e) {
      Toast.error('Failed to switch role');
    }
  }
</script>

<!-- ── Presence CSS ──────────────────────────────────────── -->
<style>
  /* Presence section container */
  .sidebar-presence {
    margin: 0 0 0 0;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
    flex-shrink: 0;
  }

  /* Clickable header row */
  .sidebar-presence-header {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px 8px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }

  .sidebar-presence-header:hover {
    background: rgba(255, 255, 255, 0.04);
  }

  .sidebar-presence-header:focus-visible {
    outline: 2px solid rgba(99, 102, 241, 0.5);
    outline-offset: -2px;
  }

  /* Green badge showing online count */
  .presence-count-badge {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    font-size: 0.67rem;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 999px;
    min-width: 22px;
    text-align: center;
    flex-shrink: 0;
    transition: all 0.2s;
  }

  /* Scrollable user list */
  .presence-list {
    overflow-y: auto;
    max-height: 220px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
    padding: 2px 0 6px;
  }

  .presence-list::-webkit-scrollbar {
    width: 3px;
  }

  .presence-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 99px;
  }

  /* Individual user row */
  .presence-user-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 5px 20px;
    transition: background 0.15s;
    cursor: default;
  }

  .presence-user-item:hover {
    background: rgba(255, 255, 255, 0.05);
  }

  /* Avatar wrapper with online dot */
  .presence-avatar {
    position: relative;
    flex-shrink: 0;
  }

  .presence-avatar img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }

  .presence-avatar-initials {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.62rem;
    font-weight: 700;
    color: #fff;
    font-family: 'Poppins', sans-serif;
  }

  .presence-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 2px solid var(--bg-sidebar);
  }

  .presence-dot.online {
    background: #10b981;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.4);
  }

  .presence-dot.offline {
    background: #475569;
  }

  /* User info */
  .presence-user-info {
    flex: 1;
    min-width: 0;
  }

  .presence-user-name {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.82);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
  }

  .presence-user-meta {
    font-size: 0.67rem;
    color: rgba(255, 255, 255, 0.3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
  }

  /* Offline state */
  .presence-user-item.is-offline .presence-user-name {
    color: rgba(255, 255, 255, 0.32);
  }

  /* Role mini-badge */
  .presence-role-pill {
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 1px 5px;
    border-radius: 4px;
    flex-shrink: 0;
    line-height: 1.6;
  }

  .presence-role-teacher {
    background: rgba(6, 182, 212, 0.18);
    color: #06b6d4;
  }

  .presence-role-student {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
  }

  .presence-role-admin {
    background: rgba(239, 68, 68, 0.18);
    color: #ef4444;
  }

  /* Empty state inside list */
  .presence-empty {
    padding: 14px 20px;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.22);
    text-align: center;
  }

  /* Skeleton loading rows */
  .presence-skeleton {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px 20px;
  }

  .presence-skel-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.07);
    flex-shrink: 0;
    animation: presencePulse 1.4s ease infinite;
  }

  .presence-skel-line {
    height: 7px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.07);
    animation: presencePulse 1.4s ease infinite;
  }

  @keyframes presencePulse {

    0%,
    100% {
      opacity: 1;
    }

    50% {
      opacity: 0.4;
    }
  }

  /* Collapsed state */
  .presence-list.collapsed {
    display: none;
  }

  /* Sidebar nav should not overflow into presence when collapsed */
  .sidebar-nav {
    min-height: 0;
  }

  /* Responsive: hide presence when sidebar is icon-only */
  @media (max-width: 768px) {
    .sidebar-presence {
      display: none;
    }

    .sidebar.mobile-open .sidebar-presence {
      display: block;
    }
  }
</style>

<!-- ── Presence JS ───────────────────────────────────────────── -->
<script>
  const Presence = (() => {
    const POLL_MS = 30000; // 30 seconds
    const COLORS = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];
    let pollTimer = null;
    let collapsed = false;

    // ── Helpers ────────────────────────────────────────────
    const $ = id => document.getElementById(id);
    const esc = s => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    function nameColor(name) {
      let h = 0;
      for (let i = 0; i < name.length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
      return COLORS[Math.abs(h) % COLORS.length];
    }

    function initials(name) {
      const p = (name || 'U').trim().split(' ');
      return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
    }

    function timeAgo(datetime) {
      if (!datetime) return 'Offline';
      const diff = Math.floor((Date.now() - new Date(datetime.replace(' ', 'T'))) / 1000);
      if (diff < 60) return 'Just now';
      if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
      if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
      return Math.floor(diff / 86400) + 'd ago';
    }

    // ── Render avatar ─────────────────────────────────────
    function renderAvatar(u) {
      const dotClass = u.is_active == 1 ? 'online' : 'offline';
      const dot = `<span class="presence-dot ${dotClass}"></span>`;
      if (u.profile_picture) {
        return `<div class="presence-avatar">
        <img src="<?= BASE_PATH ?>/uploads/profiles/${esc(u.profile_picture)}" alt="${esc(u.full_name)}"
             onerror="this.parentElement.innerHTML=fallbackAvatar('${esc(u.full_name)}','${dotClass}')">
        ${dot}
      </div>`;
      }
      const color = nameColor(u.full_name);
      const ini = initials(u.full_name);
      return `<div class="presence-avatar">
      <div class="presence-avatar-initials" style="background:${color};">${ini}</div>
      ${dot}
    </div>`;
    }

    function fallbackAvatar(name, dotClass) {
      const color = nameColor(name);
      const ini = initials(name);
      return `<div class="presence-avatar-initials" style="background:${color};">${ini}</div>
            <span class="presence-dot ${dotClass}"></span>`;
    }
    window.fallbackAvatar = fallbackAvatar;

    // ── Render role pill ──────────────────────────────────
    function rolePill(role) {
      const map = {
        teacher: 'Teacher',
        student: 'Student',
        admin: 'Admin'
      };
      const cls = `presence-role-${role}`;
      return `<span class="presence-role-pill ${cls}">${map[role]||role}</span>`;
    }

    // ── Render one user row ───────────────────────────────
    // display_role is set server-side:
    //   - Teachers shown to students → always 'teacher' (ignores current_role switching)
    //   - Students → 'student'
    //   - Admin view → current_role
    function renderUser(u) {
      const online = u.is_active == 1;
      const displayRole = u.display_role || u.current_role || 'student';
      const isTeacher = displayRole === 'teacher';
      const offCls = online ? '' : 'is-offline';

      // Teachers: no meta line at all (no batch names, no last-seen)
      // Students offline: show last-seen
      // Students online: show shared_batches if available
      let meta = '';
      if (!isTeacher) {
        if (online && u.shared_batches) {
          meta = `<div class="presence-user-meta">${esc(u.shared_batches)}</div>`;
        } else if (!online) {
          meta = `<div class="presence-user-meta">${timeAgo(u.last_seen)}</div>`;
        }
      }

      return `<div class="presence-user-item ${offCls}" title="${esc(u.full_name)}"
      style="cursor:pointer;"
      onclick="if(window.LMSChat && ${u.id} !== window.USER_ID) LMSChat.openWith(${u.id}, '${(u.gender||'').replace(/'/g,'')}', '${displayRole.replace(/'/g,'')}')">
      ${renderAvatar(u)}
      <div class="presence-user-info">
        <div style="display:flex;align-items:center;gap:4px;min-width:0;">
          <span class="presence-user-name">${esc(u.full_name)}</span>
          ${rolePill(displayRole)}
        </div>
        ${meta}
      </div>
    </div>`;
    }

    // ── Render full list ──────────────────────────────────
    function render(data) {
      const {
        users,
        online_count
      } = data;

      // Badge
      const badge = $('presence-badge');
      if (badge) {
        if (online_count > 0) {
          badge.textContent = online_count;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      }

      // List
      const list = $('presence-list');
      if (!list) return;

      if (!users || users.length === 0) {
        list.innerHTML = '<div class="presence-empty">No classmates yet</div>';
        return;
      }

      // Divider between online and offline
      let html = '';
      let shownOfflineLabel = false;
      let shownOnlineLabel = false;

      users.forEach(u => {
        const isOnline = u.is_active == 1;

        if (isOnline && !shownOnlineLabel) {
          shownOnlineLabel = true;
          // No label needed — presence header already says "Online"
        }
        if (!isOnline && !shownOfflineLabel && shownOnlineLabel) {
          shownOfflineLabel = true;
          html += `<div style="margin:4px 20px 2px;height:1px;background:rgba(255,255,255,0.05);"></div>`;
        }

        html += renderUser(u);
      });

      list.innerHTML = html;
    }

    // ── Fetch presence from server ────────────────────────
    async function fetchPresence() {
      try {
        const fd = new FormData();
        fd.append('action', 'get_presence');
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        const res = await fetch(window.LMS_BASE + '/ajax/presence.ajax.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.status === 'success') render(data.data);
      } catch (e) {
        // Silently ignore network errors
      }
    }

    // ── Collapse / Expand ─────────────────────────────────
    function toggleCollapse() {
      collapsed = !collapsed;
      const list = $('presence-list');
      const chevron = $('presence-chevron');
      const header = $('presence-toggle');
      if (list) list.classList.toggle('collapsed', collapsed);
      if (chevron) chevron.style.transform = collapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
      if (header) header.setAttribute('aria-expanded', !collapsed);
      try {
        localStorage.setItem('presence_collapsed', collapsed ? '1' : '0');
      } catch (e) {}
    }

    // ── Init ──────────────────────────────────────────────
    function init() {
      // Restore collapsed preference
      try {
        if (localStorage.getItem('presence_collapsed') === '1') {
          collapsed = true;
          const list = $('presence-list');
          const chevron = $('presence-chevron');
          const header = $('presence-toggle');
          if (list) list.classList.add('collapsed');
          if (chevron) chevron.style.transform = 'rotate(-90deg)';
          if (header) header.setAttribute('aria-expanded', 'false');
        }
      } catch (e) {}

      // Toggle on header click / Enter key
      const header = $('presence-toggle');
      if (header) {
        header.addEventListener('click', toggleCollapse);
        header.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleCollapse();
          }
        });
      }

      // First fetch immediately
      fetchPresence();

      // Poll every 30 seconds
      pollTimer = setInterval(fetchPresence, POLL_MS);
    }

    return {
      init,
      refresh: fetchPresence
    };
  })();

  // Boot after DOM is ready
  document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('sidebar-presence')) {
      Presence.init();
    }
  });
</script>