<?php
/** Timed entry test runner view. */
$asideHeading = 'Assessment in progress.';
$asideLead = 'Keep this window open until you have submitted your answers. Closing the tab will not pause the timer.';
$questions = $questions ?? [];
$savedAnswers = $savedAnswers ?? [];
$remaining = $remaining ?? 0;
?>
<div class="runner-head">
  <div>
    <div class="title"><?= e($attempt['test_title']) ?></div>
    <div class="meta"><?= e($attempt['batch_name']) ?> · <span id="answered-count"><?= count($savedAnswers) ?></span> of <?= count($questions) ?> answered</div>
  </div>
  <div class="timer" id="attempt-timer" aria-live="polite">
    <?= icon('clock', 16) ?> <span>--:--</span>
  </div>
</div>

<div class="progress mb-16">
  <span id="attempt-progress" style="width:0%"></span>
</div>

<div class="runner">
  <div class="palette" id="question-palette"></div>

  <div>
    <div class="question-card" id="question-stage">
      <div class="spinner" style="margin:40px auto"></div>
    </div>

    <div class="runner-foot">
      <div class="row-center gap-8">
        <button type="button" class="btn" id="btn-prev" disabled>
          <?= icon('chevron-left', 15) ?> Previous
        </button>
        <button type="button" class="btn" id="btn-next">
          Next <?= icon('chevron-right', 15) ?>
        </button>
      </div>

      <div class="row-center gap-8">
        <button type="button" class="btn" id="btn-flag">
          <?= icon('flag', 15) ?> Flag for review
        </button>
        <button type="button" class="btn btn-primary" id="btn-submit">
          <?= icon('send', 15) ?> Submit answers
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= e(asset('js/assessment.js')) ?>"></script>
<script>
(function () {
  var runner = EF.runner({
    questions: <?= json_encode($questions, JSON_UNESCAPED_UNICODE) ?>,
    answers: <?= json_encode($savedAnswers, JSON_FORCE_OBJECT) ?>,
    timeLimit: <?= (int) $remaining ?>,
    attemptId: <?= (int) $attempt['id'] ?>,
    saveUrl: EF.util.url('/entry-test/answer'),
    submitUrl: EF.util.url('/entry-test/submit'),
    onSubmitted: function (response) {
      if (response.data && response.data.redirect) {
        window.location.href = response.data.redirect;
      } else {
        window.location.reload();
      }
    }
  });
})();
</script>
