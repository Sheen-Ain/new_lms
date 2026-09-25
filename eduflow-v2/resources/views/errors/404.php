<?php
/** 404 — public error screen. */
$hideChrome = true;
?>
<section class="section">
  <div class="container text-center" style="max-width:560px">
    <div class="empty-icon" style="margin:0 auto 16px;width:56px;height:56px"><?= icon('search', 26) ?></div>
    <h1 class="mb-8">Page not found</h1>
    <p class="muted">
      <?= e($message ?? 'The page you are looking for may have been moved, renamed, or is no longer available.') ?>
    </p>
    <div class="row-center gap-10 mt-24" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url(user() ? App\Core\Auth::homeFor() : '/')) ?>">
        <?= icon('arrow-left', 15) ?> Back to <?= user() ? 'dashboard' : 'home' ?>
      </a>
      <?php if (!user()): ?>
        <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</section>
