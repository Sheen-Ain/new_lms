<?php
// ============================================================
// TOP NAVBAR
// ============================================================
// Requires: $pageTitle, $currentUser, $breadcrumbs (optional array)

$breadcrumbs = $breadcrumbs ?? [];
$avatarHtml  = userAvatar($currentUser ?? [], 32);

// Get recent activity notifications
$recentActivity = [];
if (isset($conn) && isset($currentUser['id'])) {
  $uid = (int)$currentUser['id'];
  $stmt = $conn->prepare("
        SELECT al.*, u.full_name, u.profile_picture
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.user_id != ?
        ORDER BY al.created_at DESC
        LIMIT 8
    ");
  if ($stmt) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
  }
}
?>

<nav class="navbar" id="navbar" role="navigation" aria-label="Top navigation">
  <!-- Hamburger (mobile) -->
  <button class="navbar-btn" id="hamburger-btn" aria-label="Toggle menu" style="display:none;">
    <i data-lucide="menu" style="width:18px;height:18px;"></i>
  </button>

  <!-- Page title / breadcrumb -->
  <div class="navbar-title">
    <?php if (!empty($breadcrumbs)): ?>
      <div class="navbar-breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><span class="separator">›</span><?php endif; ?>
          <?php if ($i < count($breadcrumbs) - 1): ?>
            <a href="<?= e($crumb['url'] ?? '#') ?>"><?= e($crumb['label']) ?></a>
          <?php else: ?>
            <span class="current"><?= e($crumb['label']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <span style="font-size:1rem;"><?= e($pageTitle ?? 'Dashboard') ?></span>
    <?php endif; ?>
  </div>

  <!-- Global search -->
  <div class="search-box" style="max-width:260px;display:none;" id="global-search-wrap">
    <i data-lucide="search" class="search-box-icon"></i>
    <input type="text" id="global-search" class="form-control"
      placeholder="Search... (Ctrl+K)" style="padding-left:36px;">
  </div>

  <div class="navbar-actions">
    <!-- Theme toggle -->
    <button class="navbar-btn theme-toggle" id="theme-toggle" aria-label="Toggle dark mode"
      data-tooltip="Toggle theme">
      <div class="theme-toggle-sun">
        <i data-lucide="sun" style="width:18px;height:18px;"></i>
      </div>
      <div class="theme-toggle-moon">
        <i data-lucide="moon" style="width:18px;height:18px;"></i>
      </div>
    </button>

    <!-- Notifications -->
    <div class="user-menu" style="position:relative;">
      <?php if ($_SESSION['role'] != "student") : ?>
        <button class="navbar-btn" id="notif-btn" aria-label="Notifications"
          data-tooltip="Notifications">
          <i data-lucide="bell" style="width:18px;height:18px;"></i>
          <?php if (!empty($recentActivity)): ?>
            <span class="notification-dot"></span>
          <?php endif; ?>
        </button>
      <?php endif; ?>

      <div class="dropdown-menu notifications-panel" id="notif-dropdown" style="width:340px;padding:0;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;font-size:0.9rem;display:flex;align-items:center;justify-content:space-between;">
          <span>Activity</span>
          <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400;"><?= count($recentActivity) ?> recent</span>
        </div>

        <?php if (empty($recentActivity)): ?>
          <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:0.85rem;">
            <div style="font-size:2rem;margin-bottom:8px;">🔔</div>
            No recent activity
          </div>
        <?php else: ?>
          <?php foreach ($recentActivity as $log): ?>
            <div class="notification-item">
              <div style="flex-shrink:0;"><?= userAvatar($log, 34) ?></div>
              <div class="notification-text">
                <div class="notification-title"><?= e($log['full_name']) ?></div>
                <div class="notification-meta"><?= truncateText($log['action'], 60) ?> · <?= timeAgo($log['created_at']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- User menu -->
    <div class="user-menu">
      <button class="user-menu-trigger" id="user-menu-trigger" aria-label="User menu" aria-expanded="false">
        <?= $avatarHtml ?>
        <span class="user-menu-name"><?= e($currentUser['full_name'] ?? 'User') ?></span>
        <i data-lucide="chevron-down" style="width:14px;height:14px;color:var(--text-muted);"></i>
      </button>

      <div class="dropdown-menu" id="user-dropdown">
        <div class="dropdown-header">
          <div class="dropdown-header-name"><?= e($currentUser['full_name'] ?? '') ?></div>
          <div class="dropdown-header-email"><?= e($currentUser['email'] ?? '') ?></div>
          <div style="margin-top:6px;"><?= roleBadge($userRole) ?></div>
        </div>

        <a href="<?= BASE_PATH ?>/includes/profile.php" class="dropdown-item">
          <i data-lucide="user" style="width:16px;height:16px;"></i>
          My Profile
        </a>

        <a href="<?= BASE_PATH ?>/includes/profile.php#password" class="dropdown-item">
          <i data-lucide="lock" style="width:16px;height:16px;"></i>
          Change Password
        </a>

        <a href="<?= BASE_PATH ?>/includes/profile.php#theme" class="dropdown-item">
          <i data-lucide="palette" style="width:16px;height:16px;"></i>
          Theme Settings
        </a>

        <?php
        $allRoles = array_filter(explode(',', $currentUser['all_roles'] ?? ''));
        if (count($allRoles) > 1):
        ?>
          <div class="dropdown-divider"></div>
          <div style="padding:6px 12px;font-size:0.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;">Switch Role</div>
          <?php
          $roleIcons = ['admin' => 'shield', 'teacher' => 'presentation', 'student' => 'graduation-cap'];
          foreach ($allRoles as $role):
            if ($role === $userRole) continue;
          ?>
            <a href="#" class="dropdown-item" onclick="switchRole('<?= e($role) ?>');return false;">
              <i data-lucide="<?= e($roleIcons[$role] ?? 'user') ?>" style="width:16px;height:16px;"></i>
              Switch to <?= ucfirst(e($role)) ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="dropdown-divider"></div>

        <a href="#" class="dropdown-item danger"
          onclick="showLogoutConfirm(); return false;">
          <i data-lucide="log-out" style="width:16px;height:16px;"></i>
          Logout
        </a>
      </div>
    </div>
  </div>
</nav>

<style>
  @media (max-width: 768px) {
    #hamburger-btn {
      display: flex !important;
    }
  }
</style>

<!-- Logout Confirmation Modal -->
<div class="modal-overlay" id="logout-modal-overlay">
  <div class="modal" style="max-width:380px;text-align:center;">
    <div class="modal-body" style="padding:28px 24px 8px;">
      <div style="width:52px;height:52px;background:rgba(239,68,68,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <polyline points="16 17 21 12 16 7" />
          <line x1="21" y1="12" x2="9" y2="12" />
        </svg>
      </div>
      <div style="font-family:Poppins,sans-serif;font-weight:700;font-size:1rem;color:var(--text);margin-bottom:8px;">Sign out?</div>
      <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:24px;">You will be signed out of your account.</div>
    </div>
    <div class="modal-footer" style="justify-content:center;gap:10px;padding-bottom:24px;">
      <button class="btn btn-secondary" onclick="closeLogoutModal()">Cancel</button>
      <a href="<?= BASE_PATH ?>/auth/logout.php" class="btn btn-danger">Sign Out</a>
    </div>
  </div>
</div>
<script>
  function showLogoutConfirm() {
    const m = document.getElementById('logout-modal-overlay');
    if (m) m.classList.add('open');
  }

  function closeLogoutModal() {
    const m = document.getElementById('logout-modal-overlay');
    if (m) m.classList.remove('open');
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLogoutModal();
  });
  document.getElementById('logout-modal-overlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
  });
</script>