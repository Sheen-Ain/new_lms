<?php
/** Entry test launch / authentication screen. */
$asideHeading = 'Online entry assessment.';
$asideLead = 'Identify yourself with your student ID or email to access your batch assessment.';
$batches = $batches ?? [];
?>
<h1>Entry assessment</h1>
<p class="lead">Select your batch and sign in to launch your timed test.</p>

<?= App\Core\View::partial('partials/flash', ['flashMessages' => $flashMessages]) ?>

<form id="form-entry-start" novalidate>
  <div class="field">
    <label class="field-label" for="entry-batch">Target batch <span class="req">*</span></label>
    <select class="select" id="entry-batch" name="batch_id" data-rules="required" data-label="Batch" required>
      <option value="">Select a batch…</option>
      <?php foreach ($batches as $batch): ?>
        <option value="<?= (int) $batch['id'] ?>">
          <?= e($batch['course_title']) ?> — <?= e($batch['name']) ?>
          <?= !(int) $batch['has_test'] ? ' (no active test)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field">
    <label class="field-label" for="entry-identifier">Student ID or email <span class="req">*</span></label>
    <div class="input-icon">
      <?= icon('user', 16) ?>
      <input class="input" type="text" id="entry-identifier" name="identifier"
             placeholder="16933 or name@example.com" data-rules="required" data-label="Student ID or email" required>
    </div>
  </div>

  <div class="field">
    <label class="field-label" for="entry-password">Password <span class="req">*</span></label>
    <div class="input-icon has-suffix">
      <?= icon('lock', 16) ?>
      <input class="input" type="password" id="entry-password" name="password"
             placeholder="Your password" data-rules="required" data-label="Password" required>
      <button type="button" class="suffix-btn" data-toggle-password="entry-password" aria-label="Show password"><?= icon('eye', 16) ?></button>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg mt-20">
    <?= icon('checklist', 16) ?> Start assessment
  </button>
</form>

<div class="panel mt-20">
  <div class="panel-body">
    <div class="section-title mb-8">Before you begin</div>
    <ul class="small muted" style="margin:0;padding-left:18px;line-height:1.7">
      <li>The assessment is timed; the countdown begins immediately upon entry.</li>
      <li>Each correct answer earns <strong>1.00</strong> mark.</li>
      <li>Each incorrect answer deducts <strong><?= (float) NEGATIVE_MARKING ?></strong> marks.</li>
      <li>Unanswered questions score zero.</li>
      <li>You can move between questions freely before final submission.</li>
    </ul>
  </div>
</div>

<div class="auth-foot">
  <div class="row-between">
    <a href="<?= e(url('/login')) ?>">Back to sign in</a>
    <a href="<?= e(url('/register')) ?>">Register first</a>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('form-entry-start');
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!EF.form.validateForm(form)) return;

    var btn = form.querySelector('[type="submit"]');
    EF.api.submit('/entry-test/auth', new FormData(form), {
      button: btn,
      reload: false,
      onSuccess: function (response) {
        if (response.data && response.data.redirect) {
          window.location.href = response.data.redirect;
        } else {
          window.location.reload();
        }
      }
    });
  });
})();
</script>
