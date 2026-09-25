<?php
/** Sign in. */
$asideHeading = 'Welcome back to your learning workspace.';
$asideLead = 'Sign in with your email address or your 5-digit student ID.';
?>
<h1>Sign in</h1>
<p class="lead">Use the credentials issued to you at registration.</p>

<?= App\Core\View::partial('partials/flash', ['flashMessages' => $flashMessages, 'stacked' => false]) ?>

<form method="post" action="<?= e(url('/login')) ?>" data-rules-form novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next ?? '') ?>">

  <div class="field">
    <label class="field-label" for="identifier">Email or student ID <span class="req">*</span></label>
    <div class="input-icon">
      <?= icon('user', 16) ?>
      <input class="input" type="text" id="identifier" name="identifier" autocomplete="username"
             value="<?= e($oldInput['identifier'] ?? '') ?>" placeholder="name@example.com or 16933"
             data-rules="required" data-label="Email or student ID" required autofocus>
    </div>
  </div>

  <div class="field">
    <label class="field-label" for="password">Password <span class="req">*</span></label>
    <div class="input-icon has-suffix">
      <?= icon('lock', 16) ?>
      <input class="input" type="password" id="password" name="password" autocomplete="current-password"
             placeholder="Your password" data-rules="required" data-label="Password" required>
      <button type="button" class="suffix-btn" data-toggle-password="password" aria-label="Show password"><?= icon('eye', 16) ?></button>
    </div>
  </div>

  <div class="row-between mb-16">
    <label class="check">
      <input type="checkbox" name="remember" value="1" checked>
      <span>Keep me signed in</span>
    </label>
    <a class="small" href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg">
    <?= icon('arrow-right', 16) ?> Sign in
  </button>
</form>

<div class="auth-foot">
  <div class="row-between">
    <span>New to <?= e(APP_NAME) ?>?</span>
    <a class="strong" href="<?= e(url('/register')) ?>">Create an account</a>
  </div>
</div>

<div class="panel mt-16">
  <div class="panel-body">
    <div class="row-center gap-10">
      <?= icon('info', 16) ?>
      <div class="small muted">
        Applicants who have not yet completed the entry test should use the
        <a href="<?= e(url('/entry-test')) ?>">entry test</a> screen instead.
      </div>
    </div>
  </div>
</div>
