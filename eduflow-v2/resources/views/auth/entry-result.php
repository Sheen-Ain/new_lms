<?php
/** Entry assessment result view. */
$asideHeading = 'Assessment complete.';
$asideLead = 'Your answers have been marked and your application has been forwarded to the academic office.';
$band = $band ?? band_for($attempt['percentage']);
?>
<div class="panel">
  <div class="panel-head">
    <div>
      <h2><?= e($attempt['test_title']) ?></h2>
      <div class="sub"><?= e($attempt['batch_name'] ?? 'Batch assessment') ?> · Result breakdown</div>
    </div>
    <span class="badge badge-<?= e($band['tone']) ?>"><?= e($band['label']) ?> band</span>
  </div>

  <div class="panel-body">
    <div class="score-ring mb-24">
      <div class="ring" id="result-ring">
        <div class="ring-inner">
          <div>
            <div class="ring-value"><?= number_format((float) $attempt['percentage'], 1) ?>%</div>
            <div class="ring-label">Score</div>
          </div>
        </div>
      </div>
      <div>
        <div class="small muted">Marks awarded</div>
        <div style="font-size:1.75rem;font-weight:700;color:var(--ink-900)">
          <?= number_format((float) $attempt['score'], 2) ?> <span class="small muted">/ <?= (int) $attempt['total_questions'] ?></span>
        </div>
        <div class="small muted mt-4">Negative marking: <?= (float) NEGATIVE_MARKING ?> per incorrect answer.</div>
      </div>
    </div>

    <div class="score-grid">
      <div class="score-box">
        <div class="value text-success"><?= (int) $attempt['correct_count'] ?></div>
        <div class="label">Correct</div>
      </div>
      <div class="score-box">
        <div class="value text-danger"><?= (int) $attempt['wrong_count'] ?></div>
        <div class="label">Incorrect</div>
      </div>
      <div class="score-box">
        <div class="value"><?= (int) $attempt['unanswered_count'] ?></div>
        <div class="label">Skipped</div>
      </div>
      <div class="score-box">
        <div class="value"><?= (int) $attempt['total_questions'] ?></div>
        <div class="label">Total</div>
      </div>
    </div>

    <div class="alert alert-info mt-20">
      <?= icon('info', 18) ?>
      <div>
        <div class="alert-title">Application under review</div>
        <div class="small">
          The academic office reviews results daily. When your application is approved you will receive an email
          confirmation and your account will be unlocked for portal access.
        </div>
      </div>
    </div>
  </div>

  <div class="panel-foot">
    <span class="small muted">Submitted <?= e(format_datetime($attempt['submitted_at'])) ?></span>
    <a class="btn btn-primary" href="<?= e(url('/entry-test/exit')) ?>">
      <?= icon('logout', 15) ?> Finish &amp; sign out
    </a>
  </div>
</div>

<script src="<?= e(asset('js/assessment.js')) ?>"></script>
<script>
  EF.paintRing(document.getElementById('result-ring'), <?= (float) $attempt['percentage'] ?>, <?= json_encode($band['tone']) ?>);
</script>
