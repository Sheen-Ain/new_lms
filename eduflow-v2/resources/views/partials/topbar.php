<?php
/**
 * Topbar partial — breadcrumbs, notifications and the account menu.
 */
use App\Core\Activity;
use App\Core\Auth;

$user = $currentUser ?? Auth::user();
$activity = [];
if ($user && ($user['current_role'] ?? '') !== 'student') {
    try {
        $activity = Activity::feed((int) $user['id'], 8);
    } catch (\Throwable $e) {
        $activity = [];
    }
}
?>
<header class="topbar">
  <button type="button" class="topbar-toggle" id="sidebar-toggle" aria-label="Toggle navigation">
    <?= icon('menu', 18) ?>
  </button>

  <div class="goto grow" style="min-width:0">
    <?php if (!empty($breadcrumbs) && count($breadcrumbs) > 1): ?>
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
          <?php if ($index > 0): ?><span class="sep"><?= icon('chevron-right', 13) ?></span><?php endif; ?>
          <?php if (!empty($crumb['url'])): ?>
            <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
          <?php else: ?>
            <span class="current"><?= e($crumb['label']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
    <?php endif; ?>
  </div>

  <div class="topbar-actions">
    <button type="button" class="topbar-btn" data-theme-cycle data-tip="Appearance" aria-label="Switch appearance">
      <?= icon('palette', 17) ?>
    </button>

    <?php if ($user && ($user['current_role'] ?? '') !== 'student'): ?>
      <div class="dropdown">
        <button type="button" class="topbar-btn" data-dropdown="notif-menu" data-tip="Activity" aria-label="Activity">
          <?= icon('bell', 17) ?>
          <?php if ($activity): ?><span class="topbar-badge"></span><?php endif; ?>
        </button>
        <div class="dropdown-menu notif-panel" id="notif-menu">
          <div class="dropdown-head row-between">
            <span class="strong small">Recent activity</span>
            <a class="tiny" href="<?= e(url('/' . ($user['current_role'] ?? 'student') . ($user['current_role'] === 'admin' ? '/activity-logs' : ''))) ?>">View all</a>
          </div>
          <div class="notif-list">
            <?php if (!$activity): ?>
              <div class="dropdown-item muted">No recent activity to display.</div>
            <?php else: ?>
              <?php foreach ($activity as $entry): ?>
                <div class="notif-item">
                  <?= user_avatar($entry, 30) ?>
                  <div>
                    <div class="text">
                      <span class="strong"><?= e($entry['full_name']) ?></span>
                      <?= e($entry['action']) ?>
                    </div>
                    <div class="when"><?= e(time_ago($entry['created_at'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <span class="topbar-divider"></span>

    <div class="dropdown">
      <button type="button" class="user-chip" data-dropdown="user-menu" aria-haspopup="true">
        <?= user_avatar($user ?? [], 30) ?>
        <span class="meta">
          <span class="name"><?= e($user['full_name'] ?? 'Account') ?></span>
          <span class="role"><?= e($user['current_role'] ?? '') ?></span>
        </span>
        <?= icon('chevron-down', 15) ?>
      </button>

      <div class="dropdown-menu" id="user-menu">
        <div class="dropdown-head">
          <div class="strong small"><?= e($user['full_name'] ?? '') ?></div>
          <div class="tiny muted"><?= e($user['email'] ?? '') ?></div>
        </div>

        <a class="dropdown-item" href="<?= e(url('/profile')) ?>"><?= icon('user', 16) ?> My profile</a>
        <a class="dropdown-item" href="<?= e(url('/profile') . '#password') ?>"><?= icon('lock', 16) ?> Change password</a>
        <a class="dropdown-item" href="<?= e(url('/profile') . '#appearance') ?>"><?= icon('palette', 16) ?> Appearance</a>

        <?php $extraRoles = array_values(array_diff($user['roles'] ?? [], [$user['current_role'] ?? ''])); ?>
        <?php if ($extraRoles): ?>
          <div class="dropdown-divider"></div>
          <div class="dropdown-label">Switch role</div>
          <?php foreach ($extraRoles as $role): ?>
            <button type="button" class="dropdown-item" data-switch-role="<?= e($role) ?>">
              <?= icon($role === 'admin' ? 'shield' : ($role === 'teacher' ? 'presentation' : 'graduation'), 16) ?>
              Continue as <?= e(ucfirst($role)) ?>
            </button>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="dropdown-divider"></div>
        <a class="dropdown-item is-danger" href="<?= e(url('/logout')) ?>"
           data-confirm="You will be signed out of your account." data-confirm-title="Sign out?"
           data-confirm-ok="Sign out">
          <?= icon('logout', 16) ?> Sign out
        </a>
      </div>
    </div>
  </div>
</header>

<script>
  (function () {
    document.querySelectorAll('[data-switch-role]').forEach(function (button) {
      button.addEventListener('click', function () {
        EF.api.submit('/profile/switch-role', { role: button.getAttribute('data-switch-role') }, {
          button: button,
          onSuccess: function (response) {
            window.location.href = response.data && response.data.redirect
              ? response.data.redirect
              : EF.util.url('/');
          }
        });
      });
    });
  })();
</script>
