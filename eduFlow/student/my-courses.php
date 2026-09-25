<?php
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'My Courses';
$breadcrumbs = [['label' => 'Student'], ['label' => 'My Courses']];
$uid = (int)$currentUser['id'];

// ── Per-course/batch data ──────────────────────────────────────────────────
// Fetch every batch the student is enrolled in, with course info + dates
$r = $conn->prepare("
    SELECT
        c.id        AS course_id,
        c.title     AS course_title,
        c.description AS course_desc,
        c.thumbnail,
        b.id        AS batch_id,
        b.name      AS batch_name,
        b.description AS batch_desc,
        b.start_date,
        b.end_date,
        b.status    AS batch_status,
        bs.enrolled_at
    FROM batch_students bs
    JOIN batches  b ON bs.batch_id  = b.id
    JOIN courses  c ON b.course_id  = c.id
    WHERE bs.student_id = ?
    ORDER BY c.title, b.start_date DESC
");
$r->bind_param('i', $uid); $r->execute();
$rows = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// Group by course
$courses = [];
foreach ($rows as $row) {
    $cid = $row['course_id'];
    if (!isset($courses[$cid])) {
        $courses[$cid] = [
            'id'          => $cid,
            'title'       => $row['course_title'],
            'description' => $row['course_desc'],
            'thumbnail'   => $row['thumbnail'],
            'batches'     => [],
        ];
    }
    $courses[$cid]['batches'][] = $row;
}

// ── Per-batch stats (topics, assignments done, marks) ──────────────────────
$batchIds = array_column($rows, 'batch_id');
$batchStats = []; // keyed by batch_id

if ($batchIds) {
    $ph = implode(',', array_fill(0, count($batchIds), '?'));
    $tp = str_repeat('i', count($batchIds));

    // Topic count per batch (active only, + global topics)
    $rs = $conn->prepare("
        SELECT batch_id, COUNT(*) AS cnt
        FROM topics WHERE status='active' AND batch_id IN ($ph)
        GROUP BY batch_id
    ");
    $rs->bind_param($tp, ...$batchIds); $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $s)
        $batchStats[$s['batch_id']]['topics'] = (int)$s['cnt'];
    $rs->close();

    // Assignment stats per batch
    $rs = $conn->prepare("
        SELECT
            a.batch_id,
            COUNT(DISTINCT a.id)                                            AS total_assignments,
            COUNT(DISTINCT s.id)                                            AS submitted,
            COUNT(DISTINCT CASE WHEN s.status='graded' THEN s.id END)      AS graded,
            ROUND(AVG(CASE WHEN s.marks IS NOT NULL THEN s.marks END), 1)  AS avg_marks,
            MAX(a.total_marks)                                              AS max_marks
        FROM assignments a
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
        WHERE a.status = 'active' AND a.batch_id IN ($ph)
        GROUP BY a.batch_id
    ");
    // bind: uid (i) + batchIds (i×N)
    $rs->bind_param('i' . $tp, $uid, ...$batchIds); $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $s) {
        $batchStats[$s['batch_id']]['total_assignments'] = (int)$s['total_assignments'];
        $batchStats[$s['batch_id']]['submitted']         = (int)$s['submitted'];
        $batchStats[$s['batch_id']]['graded']            = (int)$s['graded'];
        $batchStats[$s['batch_id']]['avg_marks']         = $s['avg_marks'];
    }
    $rs->close();

    // Teachers per batch
    $rs = $conn->prepare("
        SELECT bt.batch_id, u.full_name, u.profile_picture
        FROM batch_teachers bt
        JOIN users u ON bt.teacher_id = u.id
        WHERE bt.batch_id IN ($ph)
        ORDER BY u.full_name
    ");
    $rs->bind_param($tp, ...$batchIds); $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $t)
        $batchStats[$t['batch_id']]['teachers'][] = $t;
    $rs->close();
}

// ── Active live sessions (keyed by batch_id) ──────────────────────────────
$activeSessions = [];
if ($batchIds) {
    $ph  = implode(',', array_fill(0, count($batchIds), '?'));
    $tp  = str_repeat('i', count($batchIds));
    $rs  = $conn->prepare("
        SELECT ls.id, ls.batch_id, ls.title,
               u.full_name AS teacher_name,
               TIMESTAMPDIFF(MINUTE, ls.started_at, NOW()) AS duration_minutes
        FROM live_sessions ls
        JOIN users u ON ls.started_by = u.id
        WHERE ls.status = 'active' AND ls.batch_id IN ($ph)
    ");
    $rs->bind_param($tp, ...$batchIds);
    $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $live) {
        $activeSessions[$live['batch_id']] = $live;
    }
    $rs->close();
}
function batchStat($batchStats, $bid, $key, $default = 0) {
    return $batchStats[$bid][$key] ?? $default;
}
function progressPct($done, $total) {
    return $total > 0 ? min(100, round($done / $total * 100)) : 0;
}
function batchStatusBadge($status) {
    $map = ['active' => ['badge-success', 'Active'], 'inactive' => ['badge-warning', 'Inactive'], 'completed' => ['badge-info', 'Completed']];
    [$cls, $lbl] = $map[$status] ?? ['badge-secondary', ucfirst($status)];
    return "<span class=\"badge $cls\">$lbl</span>";
}
function coursePalette($idx) {
    $palettes = [
        ['#6366f1','#e0e7ff'],['#10b981','#d1fae5'],['#f59e0b','#fef3c7'],
        ['#8b5cf6','#ede9fe'],['#ec4899','#fce7f3'],['#0ea5e9','#e0f2fe'],
        ['#14b8a6','#ccfbf1'],['#f97316','#ffedd5'],
    ];
    return $palettes[$idx % count($palettes)];
}

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Course grid ──────────────────────────────────────── */
.course-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
.course-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; overflow:hidden; transition:transform .2s,box-shadow .2s; }
.course-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
.course-banner { height:7px; }
.course-card-body { padding:20px 20px 16px; }
.course-card-title { font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:4px; line-height:1.35; }
.course-card-desc { font-size:0.8rem; color:var(--text-muted); margin-bottom:14px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.batch-block { background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:10px; }
.batch-block:last-child { margin-bottom:0; }
.batch-header { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
.batch-name { font-weight:700; font-size:0.875rem; flex:1; min-width:0; }
.stat-pills { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.stat-pill { display:flex; align-items:center; gap:5px; background:var(--bg-card); border:1px solid var(--border); border-radius:20px; padding:3px 10px; font-size:0.75rem; color:var(--text-secondary); font-weight:600; }
.stat-pill svg { opacity:.7; }
.progress-row { margin-bottom:8px; }
.progress-label { display:flex; justify-content:space-between; font-size:0.72rem; color:var(--text-muted); margin-bottom:3px; }
.progress-bar-wrap { height:5px; background:var(--border); border-radius:99px; overflow:hidden; }
.progress-bar-fill { height:100%; border-radius:99px; transition:width .6s ease; }
.teacher-row { display:flex; align-items:center; gap:-4px; margin-top:8px; flex-wrap:wrap; }
.teacher-avatar { width:24px; height:24px; border-radius:50%; background:var(--primary); color:#fff; font-size:0.62rem; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--bg-card); margin-left:-4px; flex-shrink:0; }
.teacher-avatar:first-child { margin-left:0; }
.teacher-names { font-size:0.74rem; color:var(--text-muted); margin-left:8px; }
.batch-dates { font-size:0.72rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; margin-top:6px; flex-wrap:wrap; }
.batch-actions { display:flex; gap:6px; margin-top:10px; }
.empty-courses { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px 20px; text-align:center; }
.empty-courses-icon { font-size:4rem; margin-bottom:16px; opacity:.5; }
.empty-courses-title { font-size:1.25rem; font-weight:700; color:var(--text-primary); margin-bottom:8px; }
.empty-courses-sub { font-size:0.875rem; color:var(--text-muted); max-width:340px; }

/* ── Responsive ────────────────────────────────────────── */
@media (max-width: 768px) {
  .course-grid { grid-template-columns: 1fr; gap: 16px; }
  .course-card-body { padding: 16px 16px 12px; }
  .batch-block { padding: 12px 14px; }
  .batch-actions { flex-wrap: wrap; }
  .batch-actions .btn { flex: 1; justify-content: center; min-width: 100px; }
  .course-card-title { font-size: 0.95rem; }
}

@media (max-width: 480px) {
  .stat-pills { gap: 4px; }
  .stat-pill { font-size: 0.7rem; padding: 2px 8px; }
  .empty-courses { padding: 48px 16px; }
  .empty-courses-icon { font-size: 3rem; }
}

@keyframes liveDotPulse {
  0%,100% { opacity:1; transform:scale(1); }
  50%      { opacity:0.5; transform:scale(0.7); }
}
</style>

<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <div class="page-header">
        <div>
          <h1 class="page-title">My Courses</h1>
          <p class="page-subtitle">
            <?php if ($courses): ?>
              Enrolled in <strong><?= count($courses) ?></strong> course<?= count($courses) !== 1 ? 's' : '' ?>
              across <strong><?= count($rows) ?></strong> batch<?= count($rows) !== 1 ? 'es' : '' ?>
            <?php else: ?>
              Your enrolled courses will appear here
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php if (empty($courses)): ?>
        <div class="card">
          <div class="empty-courses">
            <div class="empty-courses-icon">🎓</div>
            <div class="empty-courses-title">No courses yet</div>
            <div class="empty-courses-sub">You haven't been enrolled in any batch. Contact your teacher or administrator to get enrolled.</div>
          </div>
        </div>

      <?php else: ?>
        <div class="course-grid">
          <?php $ci = 0; foreach ($courses as $course): $palette = coursePalette($ci++); ?>
            <div class="course-card">
              <!-- Coloured top stripe -->
              <div class="course-banner" style="background:<?= $palette[0] ?>;"></div>

              <div class="course-card-body">
                <!-- Course header -->
                <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
                  <div style="width:44px;height:44px;border-radius:10px;background:<?= $palette[1] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="<?= $palette[0] ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div class="course-card-title"><?= e($course['title']) ?></div>
                    <?php if ($course['description']): ?>
                      <div class="course-card-desc"><?= e($course['description']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Batch blocks -->
                <?php foreach ($course['batches'] as $batch):
                  $bid       = $batch['batch_id'];
                  $topics    = (int)batchStat($batchStats, $bid, 'topics');
                  $totalAsn  = (int)batchStat($batchStats, $bid, 'total_assignments');
                  $submitted = (int)batchStat($batchStats, $bid, 'submitted');
                  $graded    = (int)batchStat($batchStats, $bid, 'graded');
                  $avgMarks  = batchStat($batchStats, $bid, 'avg_marks', null);
                  $teachers  = $batchStats[$bid]['teachers'] ?? [];
                  $pctSubmit = progressPct($submitted, $totalAsn);
                ?>
                <div class="batch-block">
                  <!-- Batch name + status -->
                  <div class="batch-header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?= $palette[0] ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                    <span class="batch-name"><?= e($batch['batch_name']) ?></span>
                    <?= batchStatusBadge($batch['batch_status']) ?>
                  </div>

                  <!-- Stat pills -->
                  <div class="stat-pills">
                    <div class="stat-pill">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      <?= $topics ?> Topic<?= $topics !== 1 ? 's' : '' ?>
                    </div>
                    <div class="stat-pill">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                      <?= $submitted ?>/<?= $totalAsn ?> Submitted
                    </div>
                    <?php if ($graded > 0 && $avgMarks !== null): ?>
                    <div class="stat-pill" style="color:<?= $palette[0] ?>;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                      Avg <?= $avgMarks ?> pts
                    </div>
                    <?php endif; ?>
                    <?php
                      $enrolledDate = $batch['enrolled_at'] ? date('M j, Y', strtotime($batch['enrolled_at'])) : null;
                      if ($enrolledDate): ?>
                    <div class="stat-pill">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      Joined <?= $enrolledDate ?>
                    </div>
                    <?php endif; ?>
                  </div>

                  <!-- Assignment progress bar -->
                  <?php if ($totalAsn > 0): ?>
                  <div class="progress-row">
                    <div class="progress-label">
                      <span>Assignment Progress</span>
                      <span style="font-weight:700;color:<?= $palette[0] ?>;"><?= $pctSubmit ?>%</span>
                    </div>
                    <div class="progress-bar-wrap">
                      <div class="progress-bar-fill" style="width:<?= $pctSubmit ?>%;background:<?= $palette[0] ?>;"></div>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- Batch dates -->
                  <?php if ($batch['start_date'] || $batch['end_date']): ?>
                  <div class="batch-dates">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php if ($batch['start_date']): ?><?= date('M j, Y', strtotime($batch['start_date'])) ?><?php endif; ?>
                    <?php if ($batch['start_date'] && $batch['end_date']): ?> → <?php endif; ?>
                    <?php if ($batch['end_date']): ?><?= date('M j, Y', strtotime($batch['end_date'])) ?><?php endif; ?>
                  </div>
                  <?php endif; ?>

                  <!-- Teachers -->
                  <?php if ($teachers): ?>
                  <div class="teacher-row">
                    <?php foreach (array_slice($teachers, 0, 4) as $t):
                      $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $t['full_name']))));
                      $initials = substr($initials, 0, 2); ?>
                      <div class="teacher-avatar" title="<?= e($t['full_name']) ?>" style="background:<?= $palette[0] ?>;"><?= e($initials) ?></div>
                    <?php endforeach; ?>
                    <?php if (count($teachers) > 4): ?>
                      <div class="teacher-avatar" style="background:var(--text-muted);">+<?= count($teachers) - 4 ?></div>
                    <?php endif; ?>
                    <span class="teacher-names">
                      <?= e(count($teachers) === 1 ? $teachers[0]['full_name'] : implode(', ', array_slice(array_column($teachers, 'full_name'), 0, 2)) . (count($teachers) > 2 ? ' +'.( count($teachers)-2).' more' : '')) ?>
                    </span>
                  </div>
                  <?php endif; ?>

                  <!-- Quick-action buttons -->
                  <div class="batch-actions">
                    <?php if (isset($activeSessions[$bid])): ?>
                      <a href="<?= BASE_PATH ?>/student/live.php?session_id=<?= $activeSessions[$bid]['id'] ?>"
                         class="btn btn-sm" style="flex:1;text-align:center;justify-content:center;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;box-shadow:0 3px 10px rgba(239,68,68,0.35);">
                        <span style="display:inline-block;width:7px;height:7px;background:#fff;border-radius:50%;margin-right:5px;animation:liveDotPulse 1.2s ease-in-out infinite;"></span>
                        Join Live
                      </a>
                    <?php endif; ?>
                    <a href="<?= BASE_PATH ?>/student/topics.php?batch_id=<?= $bid ?>" class="btn btn-secondary btn-sm" style="flex:1;text-align:center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      Topics
                    </a>
                    <a href="<?= BASE_PATH ?>/student/assignments.php?batch_id=<?= $bid ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center;">
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                      Assignments
                    </a>
                  </div>
                </div><!-- /.batch-block -->
                <?php endforeach; ?>

              </div><!-- /.course-card-body -->
            </div><!-- /.course-card -->
          <?php endforeach; ?>
        </div><!-- /.course-grid -->
      <?php endif; ?>

    </main>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>