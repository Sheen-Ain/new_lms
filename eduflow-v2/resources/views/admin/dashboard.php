<?php
$stats = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$enrollment = $enrollment ?? [];
$metrics = [
    ['Students', 'students', 'users', 'is-brand'],
    ['Teachers', 'teachers', 'graduation', 'is-info'],
    ['Courses', 'courses', 'book', 'is-success'],
    ['Active batches', 'batches', 'layers', 'is-brand'],
    ['Pending submissions', 'pending_submissions', 'clipboard', 'is-warning'],
    ['Pending applications', 'pending_applications', 'userPlus', 'is-warning'],
    ['Tests', 'tests', 'fileText', 'is-info'],
    ['Live sessions', 'active_sessions', 'video', 'is-danger'],
];
?>
<section class="page-head">
  <div>
    <h1>Admin dashboard</h1>
    <p class="sub">Current activity and learning operations.</p>
  </div>
  <div class="page-head-actions">
    <a class="btn btn-sm" href="<?= e(url('/admin/users')) ?>">Manage users</a>
    <a class="btn btn-sm btn-primary" href="<?= e(url('/admin/courses')) ?>">View courses</a>
  </div>
</section>

<section class="metric-row" aria-label="Platform overview">
  <?php foreach ($metrics as $metric): ?>
    <div class="metric <?= e($metric[3]) ?>">
      <span class="mark"><?= icon($metric[2], 17) ?></span>
      <div>
        <div class="value"><?= number_format((int) ($stats[$metric[1]] ?? 0)) ?></div>
        <div class="label"><?= e($metric[0]) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<div class="split">
  <section class="panel">
    <div class="panel-head">
      <h2>Recent activity</h2>
      <a class="btn btn-sm btn-quiet" href="<?= e(url('/admin/activity-logs')) ?>">View all</a>
    </div>
    <?php if (!$recentActivity): ?>
      <div class="empty"><h3>No recent activity</h3><p>New account and learning activity will appear here.</p></div>
    <?php else: ?>
      <div class="mini-list">
        <?php foreach ($recentActivity as $activity): ?>
          <div class="mini-item">
            <?= user_avatar(['full_name' => $activity['full_name'], 'profile_picture' => $activity['profile_picture']], 34) ?>
            <div class="main">
              <div class="title"><?= e($activity['full_name']) ?></div>
              <div class="meta"><?= e($activity['action']) ?> · <?= e(date('d M, H:i', strtotime($activity['created_at']))) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Course enrollment</h2></div>
    <?php if (!$enrollment): ?>
      <div class="empty"><h3>No courses yet</h3><p>Create courses and batches to see enrollment here.</p></div>
    <?php else: ?>
      <div class="mini-list">
        <?php foreach ($enrollment as $course): ?>
          <div class="mini-item">
            <div class="main"><div class="title"><?= e($course['title']) ?></div></div>
            <strong><?= number_format((int) $course['learners']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>