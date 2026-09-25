<?php
/** Create account. */
$asideHeading = 'Create your learner account in a minute.';
$asideLead = 'Register once, then apply for an open batch and complete its entry assessment.';
$batches = $batches ?? [];
?>
<h1>Create your account</h1>
<p class="lead">A unique 5-digit student ID is generated for you automatically.</p>

<?= App\Core\View::partial('partials/flash', ['flashMessages' => $flashMessages, 'stacked' => true, 'errors' => $errors ?? []]) ?>

<form method="post" action="<?= e(url('/register')) ?>" data-rules-form novalidate>
  <?= csrf_field() ?>

  <div class="field">
    <label class="field-label" for="full_name">Full name <span class="req">*</span></label>
    <div class="input-icon">
      <?= icon('user', 16) ?>
      <input class="input" type="text" id="full_name" name="full_name" autocomplete="name"
             value="<?= e($oldInput['full_name'] ?? '') ?>" placeholder="As written on your documents"
             data-rules="required|min:3" data-label="Full name" required>
    </div>
  </div>

  <div class="form-grid">
    <div class="field">
      <label class="field-label" for="email">Email address <span class="req">*</span></label>
      <div class="input-icon">
        <?= icon('mail', 16) ?>
        <input class="input" type="email" id="email" name="email" autocomplete="email"
               value="<?= e($oldInput['email'] ?? '') ?>" placeholder="name@example.com"
               data-rules="required|email" data-label="Email address" required>
      </div>
    </div>

    <div class="field">
      <label class="field-label" for="cnic">CNIC <span class="req">*</span></label>
      <div class="input-icon">
        <?= icon('id-card', 16) ?>
        <input class="input" type="text" id="cnic" name="cnic" inputmode="numeric"
               value="<?= e($oldInput['cnic'] ?? '') ?>" placeholder="42301-1234567-8"
               data-rules="required|cnic" data-label="CNIC" required>
      </div>
      <div class="field-hint">13 digits. Each CNIC may only be used once.</div>
    </div>

    <div class="field">
      <label class="field-label" for="gender">Gender <span class="req">*</span></label>
      <select class="select" id="gender" name="gender" data-rules="required" data-label="Gender" required>
        <option value="">Select…</option>
        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= ($oldInput['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label class="field-label" for="phone">Phone <span class="muted small">(optional)</span></label>
      <div class="input-icon">
        <?= icon('phone', 16) ?>
        <input class="input" type="text" id="phone" name="phone" inputmode="tel"
               value="<?= e($oldInput['phone'] ?? '') ?>" placeholder="0300 0000000" data-rules="phone" data-label="Phone">
      </div>
    </div>
  </div>

  <div class="form-grid">
    <div class="field">
      <label class="field-label" for="password">Password <span class="req">*</span></label>
      <div class="input-icon has-suffix">
        <?= icon('lock', 16) ?>
        <input class="input" type="password" id="password" name="password" autocomplete="new-password"
               placeholder="At least 8 characters" data-rules="required|min:8" data-label="Password" required>
        <button type="button" class="suffix-btn" data-toggle-password="password" aria-label="Show password"><?= icon('eye', 16) ?></button>
      </div>
      <div class="strength" data-strength="password" data-level="0">
        <div class="strength-bars"><span></span><span></span><span></span><span></span></div>
        <div class="field-hint" data-strength-text></div>
      </div>
    </div>

    <div class="field">
      <label class="field-label" for="password_confirmation">Confirm password <span class="req">*</span></label>
      <div class="input-icon has-suffix">
        <?= icon('lock', 16) ?>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation"
               autocomplete="new-password" placeholder="Repeat password"
               data-rules="required|same:password" data-label="Password confirmation" required>
        <button type="button" class="suffix-btn" data-toggle-password="password_confirmation" aria-label="Show password"><?= icon('eye', 16) ?></button>
      </div>
    </div>
  </div>

  <?php if ($batches): ?>
    <div class="field">
      <label class="field-label" for="batch_id">Apply for a batch <span class="muted small">(optional)</span></label>
      <select class="select" id="batch_id" name="batch_id">
        <option value="">Decide later</option>
        <?php foreach ($batches as $batch): ?>
          <option value="<?= (int) $batch['id'] ?>" <?= (int) ($oldInput['batch_id'] ?? 0) === (int) $batch['id'] ? 'selected' : '' ?>>
            <?= e($batch['course_title']) ?> — <?= e($batch['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="field-hint">Selecting a batch creates a pending application you can complete with the entry test.</div>
    </div>
  <?php endif; ?>

  <label class="check mb-16">
    <input type="checkbox" name="terms" value="1" data-rules="required" data-label="Terms" required>
    <span class="small">I confirm the details above are accurate and I accept the institution's assessment policy.</span>
  </label>

  <button type="submit" class="btn btn-primary btn-block btn-lg">
    <?= icon('user-plus', 16) ?> Create account
  </button>
</form>

<div class="auth-foot">
  <div class="row-between">
    <span>Already registered?</span>
    <a class="strong" href="<?= e(url('/login')) ?>">Sign in instead</a>
  </div>
</div>
