<?php
/**
 * Public layout — marketing pages, authentication screens, error pages.
 * Variables: $content, $pageTitle, $pageDescription (optional)
 */
$pageTitle = $pageTitle ?? 'Welcome';
$pageDescription = $pageDescription ?? 'EduFlow is a learning and assessment platform for courses, assignments, live classes and online testing.';
$hideChrome = $hideChrome ?? false;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-theme-mode="system">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($pageDescription) ?>">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">

<script>
  window.EF = {
    base: <?= json_encode(base_path()) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    role: <?= json_encode(current_role()) ?>,
    userId: <?= (int) auth_id() ?>
  };
  window.EF_FLASH = <?= json_encode($flashMessages ?? [], JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    try {
      var mode = localStorage.getItem('eduflow-theme') || 'system';
      var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme-mode', mode);
    } catch (error) { /* ignore */ }
  })();
</script>
</head>
<body>

<?php if (!$hideChrome): ?>
<header class="site-header">
  <div class="container site-header-inner">
    <a class="site-brand" href="<?= e(url('/')) ?>">
      <span class="brand-mark">EF</span>
      <span>
        <span class="brand-name"><?= e(APP_NAME) ?></span>
        <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
      </span>
    </a>

    <button type="button" class="topbar-btn site-nav-toggle" id="site-nav-toggle" aria-label="Toggle navigation">
      <?= icon('menu', 18) ?>
    </button>

    <nav class="site-nav" id="site-nav" aria-label="Primary">
      <a href="<?= e(url('/')) ?>#capabilities">Capabilities</a>
      <a href="<?= e(url('/')) ?>#workflow">How it works</a>
      <a href="<?= e(url('/')) ?>#assessment">Assessment</a>
      <a href="<?= e(url('/entry-test')) ?>">Entry test</a>
    </nav>

    <div class="site-actions">
      <div class="dropdown">
        <button type="button" class="topbar-btn" data-dropdown="theme-menu" data-tip="Appearance" aria-label="Appearance">
          <?= icon('palette', 17) ?>
        </button>
        <div class="dropdown-menu align-left" id="theme-menu">
          <div class="dropdown-label">Appearance</div>
          <button type="button" class="dropdown-item" data-theme-set="system"><?= icon('settings', 15) ?> System</button>
          <button type="button" class="dropdown-item" data-theme-set="light"><?= icon('sun', 15) ?> Light</button>
          <button type="button" class="dropdown-item" data-theme-set="dark"><?= icon('moon', 15) ?> Dark</button>
        </div>
      </div>

      <?php if (user()): ?>
        <a class="btn btn-primary" href="<?= e(url(App\Core\Auth::homeFor())) ?>">
          <?= icon('dashboard', 15) ?> Go to portal
        </a>
      <?php else: ?>
        <a class="btn btn-ghost" href="<?= e(url('/login')) ?>">Sign in</a>
        <a class="btn btn-primary" href="<?= e(url('/register')) ?>">Create account</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php endif; ?>

<main id="main">
  <?= $content ?>
</main>

<?php if (!$hideChrome): ?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="site-brand mb-12">
          <span class="brand-mark">EF</span>
          <span>
            <span class="brand-name"><?= e(APP_NAME) ?></span>
            <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
          </span>
        </div>
        <p class="small muted" style="max-width:34ch">
          A single place for course delivery, assignments, live classes, online assessments and learner progress.
        </p>
      </div>

      <div>
        <h4>Platform</h4>
        <div class="footer-links">
          <a href="<?= e(url('/')) ?>#capabilities">Capabilities</a>
          <a href="<?= e(url('/')) ?>#workflow">Workflow</a>
          <a href="<?= e(url('/entry-test')) ?>">Entry test</a>
          <a href="<?= e(url('/login')) ?>">Sign in</a>
        </div>
      </div>

      <div>
        <h4>Learners</h4>
        <div class="footer-links">
          <a href="<?= e(url('/register')) ?>">Create account</a>
          <a href="<?= e(url('/forgot-password')) ?>">Reset password</a>
        </div>
      </div>

      <div>
        <h4>Assurance</h4>
        <div class="footer-links">
          <span class="muted small">CSRF protected forms</span>
          <span class="muted small">Parameterised database access</span>
          <span class="muted small">Role based access control</span>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
      <span class="mono">v<?= e(APP_VERSION) ?></span>
    </div>
  </div>
</footer>
<?php endif; ?>

<script src="<?= e(asset('js/core.js')) ?>"></script>
<script src="<?= e(asset('js/list.js')) ?>"></script>
<script>
  (function () {
    var toggle = document.getElementById('site-nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', function () { nav.classList.toggle('is-open'); });
    }
  })();
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
