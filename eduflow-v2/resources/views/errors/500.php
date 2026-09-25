<?php
/** 500 — public error screen. Details only outside production. */
$hideChrome = true;
?>
<section class="section">
  <div class="container text-center" style="max-width:620px">
    <div class="empty-icon" style="margin:0 auto 16px;width:56px;height:56px"><?= icon('alert', 26) ?></div>
    <h1 class="mb-8">Something went wrong</h1>
    <p class="muted">
      The request could not be completed. The incident has been written to the application log.
    </p>

    <?php if (!empty($detail)): ?>
      <div class="alert alert-danger mt-16" style="text-align:left">
        <?= icon('info', 18) ?>
        <div class="mono small"><?= e($detail) ?></div>
      </div>
    <?php endif; ?>

    <div class="row-center gap-10 mt-24" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(url(user() ? App\Core\Auth::homeFor() : '/')) ?>">
        <?= icon('refresh', 15) ?> Try again
      </a>
    </div>
  </div>
</section>
