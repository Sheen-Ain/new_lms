<?php
/**
 * Auth layout — split screen used by every authentication screen.
 * Variables: $content, $pageTitle, $asideHeading, $asideLead
 */
$pageTitle = $pageTitle ?? 'Sign in';
$asideHeading = $asideHeading ?? 'One workspace for teaching, assessment and learner progress.';
$asideLead = $asideLead ?? 'Sign in with your email address or 5-digit student ID.';
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
<link rel="stylesheet" href="<?= e(asset('css/assessment.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">

<script>
  window.EF = {
    base: <?= json_encode(base_path()) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    role: null,
    userId: 0
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

<div class="auth-shell">
  <aside class="auth-aside">
    <a class="site-brand" href="<?= e(url('/')) ?>" style="color:#fff">
      <span class="brand-mark">EF</span>
      <span>
        <span class="brand-name"><?= e(APP_NAME) ?></span>
        <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
      </span>
    </a>

    <div style="margin-top:auto">
      <h2><?= e($asideHeading) ?></h2>
      <p><?= e($asideLead) ?></p>
    </div>

    <div class="auth-points">
      <?php
      $points = [
          ['clipboard', 'Assignments', 'Collect work, grade with feedback and return it to learners.'],
          ['checklist', 'Assessments', 'Timed multiple-choice tests with automatic marking and bands.'],
          ['video', 'Live classes', 'Start a session for a batch and track attendance.'],
      ];
      foreach ($points as $point): ?>
        <div class="auth-point">
          <span class="icon-tile"><?= icon($point[0], 16) ?></span>
          <div>
            <div class="title"><?= e($point[1]) ?></div>
            <div class="text"><?= e($point[2]) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row-center gap-8" style="color:#7d8fa8;font-size:12px">
      <?= icon('shield', 14) ?> Sessions are protected with CSRF tokens and server-side validation.
    </div>
  </aside>

  <main class="auth-main">
    <div class="auth-card">
      <?= $content ?>
    </div>
  </main>
</div>

<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>

<script src="<?= e(asset('js/core.js')) ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
