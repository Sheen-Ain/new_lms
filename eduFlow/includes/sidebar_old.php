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
$pendingSubmissions = 0;
$unreadAnnouncements = 0;

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
    ],
  ],
  'student' => [
    'main' => [
      ['page' => 'index',        'label' => 'Dashboard',     'icon' => 'layout-dashboard', 'dir' => 'student'],
      ['page' => 'my-courses',   'label' => 'My Courses',    'icon' => 'book-open',        'dir' => 'student'],
      ['page' => 'topics',       'label' => 'Topics',        'icon' => 'file-text',        'dir' => 'student'],
      ['page' => 'assignments',  'label' => 'Assignments',   'icon' => 'clipboard-list',   'dir' => 'student'],
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

  <!-- Footer links -->
  <div class="sidebar-footer">
    <a href="<?= BASE_PATH ?>/includes/profile.php" class="nav-item <?= isActive('profile') ?>" data-tooltip="Profile">
      <i data-lucide="user-circle" class="nav-item-icon"></i>
      <span>Profile</span>
    </a>
    <a href="<?= BASE_PATH ?>/auth/logout.php" class="nav-item" style="color:rgba(239,68,68,0.7);" data-tooltip="Logout"
      onclick="return confirm('Are you sure you want to logout?')">
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