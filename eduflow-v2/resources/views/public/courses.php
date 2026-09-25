<?php
/** Public course catalogue. */
$courses = $courses ?? [];
?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <h1>Courses</h1>
      <p>Every programme is delivered in scheduled batches with assignments, live classes and assessments.</p>
    </div>

    <?php if (!$courses): ?>
      <div class="panel">
        <div class="panel-body">
          <?= App\Support\Icons::render('book', 22) ?>
          <h3 class="mt-12">No courses published yet</h3>
          <p class="muted mb-0">Course listings will appear here once the academic office publishes them.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($courses as $course): ?>
          <article class="feature">
            <div class="tile"><?= icon('book', 18) ?></div>
            <h3><?= e($course['title']) ?></h3>
            <p class="clamp-2"><?= e(($course['description'] ?? '') !== '' ? $course['description'] : 'Curriculum delivered in scheduled batches.') ?></p>
            <div class="row-center gap-8 mt-12">
              <span class="badge badge-<?= (int) $course['batch_count'] > 0 ? 'success' : 'muted' ?>">
                <?= (int) $course['batch_count'] ?> open batch<?= (int) $course['batch_count'] === 1 ? '' : 'es' ?>
              </span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
