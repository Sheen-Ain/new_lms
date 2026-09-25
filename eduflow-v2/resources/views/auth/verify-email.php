<?php
/** Email verification screen. */
$asideHeading = 'One last step before you can sign in.';
$asideLead = 'Confirm your email address so we can activate your account and batch applications.';
$email = $email ?? '';
$token = $devToken ?? '';
?>
<h1>Verify your email</h1>
<p class="lead">
  We sent a verification link to
  <strong><?= e($email !== '' ? $email : 'your email address') ?></strong>.
</p>

<?= App\Core\View::partial('partials/flash', ['flashMessages' => $flashMessages]) ?>

<?php if ($token !== ''): ?>
  <div class="alert alert-warning mb-16">
    <?= icon('alert', 17) ?>
    <div>
      <div class="alert-title">Email delivery unavailable</div>
      <div class="small">Use this direct link to verify your account in the meantime.</div>
      <a class="btn btn-sm btn-primary mt-8" href="<?= e(url('/verify-email', ['token' => $token])) ?>">
        <?= icon('check', 14) ?> Verify now
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($studentId)): ?>
  <div class="panel mb-16">
    <div class="panel-body">
      <div class="row-between">
        <div>
          <div class="section-title mb-4">Your student ID</div>
          <div class="mono" style="font-size:1.25rem;font-weight:600"><?= e($studentId) ?></div>
        </div>
        <span class="badge badge-primary"><?= icon('id-card', 12) ?> Keep it safe</span>
      </div>
      <p class="small muted mb-0 mt-8">
        You can sign in with either this ID or your email address once verification is complete.
      </p>
    </div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/verify-email/resend')) ?>" data-rules-form novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label class="field-label" for="verify-email">Email address <span class="req">*</span></label>
    <div class="input-icon">
      <?= icon('mail', 16) ?>
      <input class="input" type="email" id="verify-email" name="email" value="<?= e($email) ?>"
             placeholder="name@example.com" data-rules="required|email" data-label="Email address" required>
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-block">
    <?= icon('refresh', 15) ?> Resend verification email
  </button>
</form>

<div class="auth-foot">
  <div class="row-between">
    <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    <a href="<?= e(url('/register')) ?>">Create another account</a>
  </div>
</div>
