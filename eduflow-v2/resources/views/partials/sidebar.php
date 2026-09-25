<?php
/**
 * Sidebar partial — role aware navigation with badge counters.
 */
use App\Core\Auth;
use App\Support\Navigation;

$user = $currentUser ?? Auth::user();
$role = $user['current_role'] ?? 'student';
$groups = Navigation::forRole($role);
$badges = Navigation::badges($role, (int) ($user['id'] ?? 0));
$currentPath = $path ?? App\Core\Request::path();
?>
<aside class="sidebar" id="sidebar">
  <a class="sidebar-brand" href="<?= e(url('/' . $role)) ?>">
    <span class="brand-mark">EF</span>
    <span class="brand-text">
      <span class="brand-name"><?= e(APP_NAME) ?></span>
      <span class="brand-sub"><?= e(ucfirst($role)) ?> portal</span>
    </span>
  </a>

  <div class="sidebar-scroll">
    <?php foreach ($groups as $group): ?>
      <div class="sidebar-group">
        <div class="sidebar-group-title"><?= e($group['title']) ?></div>
        <nav class="side-nav">
          <?php foreach ($group['items'] as $item):
              $isActive = $currentPath === $item['path']
                  || ($item['path'] !== '/' && strpos($currentPath, $item['path'] . '/') === 0);
              $count = (!empty($item['badge']) && !empty($badges[$item['badge']])) ? (int) $badges[$item['badge']] : 0; ?>
            <a class="side-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
              <?= icon($item['icon'], 17) ?>
              <span class="label"><?= e($item['label']) ?></span>
              <?php if ($count > 0): ?>
                <span class="count"><?= $count > 99 ? '99+' : $count ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>
    <?php endforeach; ?>

    <div class="sidebar-group">
      <div class="sidebar-group-title">Account</div>
      <nav class="side-nav">
        <a class="side-link<?= $currentPath === '/profile' ? ' is-active' : '' ?>" href="<?= e(url('/profile')) ?>">
          <?= icon('user', 17) ?>
          <span class="label">My profile</span>
        </a>
        <?php if (count($user['roles'] ?? []) > 1): ?>
          <a class="side-link" href="<?= e(url('/profile') ) ?>#role">
            <?= icon('refresh', 17) ?>
            <span class="label">Switch role</span>
          </a>
        <?php endif; ?>
        <a class="side-link" href="#" data-confirm="You will be signed out of your account."
           data-confirm-title="Sign out?" data-confirm-ok="Sign out"
           data-confirm-action="<?= e(url('/logout')) ?>">
          <?= icon('logout', 17) ?>
          <span class="label">Sign out</span>
        </a>
      </nav>
    </div>
  </div>

  <div class="sidebar-foot">
    <div class="side-user">
      <?= user_avatar($user, 34) ?>
      <div class="meta">
        <div class="name truncate"><?= e($user['full_name'] ?? 'Account') ?></div>
        <div class="role"><?= e($role) ?><?= !empty($user['user_id_number']) ? ' · ' . e($user['user_id_number']) : '' ?></div>
      </div>
    </div>
  </div>
</aside>
