<?php
/**
 * Portal layout — the authenticated application shell.
 * Variables: $content, $pageTitle, $breadcrumbs (optional), $bodyClass
 */
use App\Core\Auth;
use App\Core\Request;
use App\Support\Navigation;

$pageTitle = $pageTitle ?? Navigation::titleFromPath(Request::path());
$breadcrumbs = isset($breadcrumbs) ? $breadcrumbs : Navigation::breadcrumbs(Request::path());
$bodyClass = $bodyClass ?? '';
$path = Request::path();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-theme-mode="system">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/assessment.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">

<script>
  window.EF = {
    base: <?= json_encode(base_path()) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    role: <?= json_encode(Auth::role()) ?>,
    userId: <?= (int) Auth::id() ?>,
    urls: {
      presence: <?= json_encode(url('/api/presence')) ?>,
      notifications: <?= json_encode(url('/api/notifications')) ?>
    }
  };
  window.EF_FLASH = <?= json_encode($flashMessages ?? [], JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    try {
      var mode = localStorage.getItem('eduflow-theme') || <?= json_encode(isset($currentUser['theme_preference']) ? $currentUser['theme_preference'] : 'system') ?>;
      var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme-mode', mode);
    } catch (error) { /* ignore */ }
  })();
</script>
<?= $extraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">

<div class="app">
  <?= App\Core\View::partial('partials/sidebar', ['path' => $path]) ?>

  <div class="main">
    <?= App\Core\View::partial('partials/topbar', [
        'pageTitle' => $pageTitle,
        'breadcrumbs' => $breadcrumbs,
    ]) ?>

    <main class="content<?= !empty($flushContent) ? ' content-flush' : '' ?>">
      <?= App\Core\View::partial('partials/flash') ?>
      <?= $content ?>
    </main>
  </div>
</div>

<div class="sidebar-scrim" id="sidebar-scrim"></div>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>

<script src="<?= e(asset('js/core.js')) ?>"></script>
<script src="<?= e(asset('js/list.js')) ?>"></script>
<script src="<?= e(asset('js/assessment.js')) ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
