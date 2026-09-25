<?php
// ============================================================
// TEACHER DASHBOARD — Upgraded
// ============================================================
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Dashboard';
$uid = (int)$currentUser['id'];

// ── Core Stats ───────────────────────────────────────────────
$r = $conn->prepare("SELECT COUNT(DISTINCT batch_id) as batches FROM batch_teachers WHERE teacher_id=?");
$r->bind_param('i',$uid); $r->execute();
$totalBatches = (int)$r->get_result()->fetch_assoc()['batches']; $r->close();

$r = $conn->prepare("SELECT COUNT(DISTINCT bs.student_id) as students FROM batch_teachers bt JOIN batch_students bs ON bt.batch_id=bs.batch_id WHERE bt.teacher_id=?");
$r->bind_param('i',$uid); $r->execute();
$totalStudents = (int)$r->get_result()->fetch_assoc()['students']; $r->close();

$r = $conn->prepare("SELECT COUNT(*) as cnt FROM assignments WHERE batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)");
$r->bind_param('i',$uid); $r->execute();
$totalAssign = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?) AND s.status='submitted'");
$r->bind_param('i',$uid); $r->execute();
$toGrade = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?) AND s.status='graded'");
$r->bind_param('i',$uid); $r->execute();
$totalGraded = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)");
$r->bind_param('i',$uid); $r->execute();
$totalSubs = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

// Average score of graded submissions
$r = $conn->prepare("SELECT AVG(s.marks / a.total_marks * 100) as avg_pct FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?) AND s.status='graded' AND a.total_marks > 0 AND s.marks IS NOT NULL");
$r->bind_param('i',$uid); $r->execute();
$classAvg = round((float)($r->get_result()->fetch_assoc()['avg_pct'] ?? 0), 1); $r->close();

// Topics created by teacher
$r = $conn->prepare("SELECT COUNT(*) as cnt FROM topics WHERE created_by=?");
$r->bind_param('i',$uid); $r->execute();
$totalTopics = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

// ── Submission Status for Pie Chart ──────────────────────────
$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?) AND s.status='returned'");
$r->bind_param('i',$uid); $r->execute();
$totalReturned = (int)$r->get_result()->fetch_assoc()['cnt']; $r->close();

// ── Per-Batch Grading Progress (bar chart) ───────────────────
$r = $conn->prepare("
    SELECT b.name,
           COUNT(s.id) as total_subs,
           SUM(CASE WHEN s.status='graded' THEN 1 ELSE 0 END) as graded_subs
    FROM batch_teachers bt
    JOIN batches b ON bt.batch_id = b.id
    LEFT JOIN assignments a ON a.batch_id = b.id
    LEFT JOIN submissions s ON s.assignment_id = a.id
    WHERE bt.teacher_id = ?
    GROUP BY b.id, b.name
    ORDER BY b.created_at DESC
    LIMIT 6
");
$r->bind_param('i',$uid); $r->execute();
$batchProgress = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// ── Pending Submissions to Grade ─────────────────────────────
$r = $conn->prepare("
    SELECT s.*, u.full_name as student_name, u.profile_picture,
           a.title as assignment_title, a.total_marks,
           b.name as batch_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    LEFT JOIN batches b ON a.batch_id = b.id
    WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)
      AND s.status = 'submitted'
    ORDER BY s.submitted_at DESC
    LIMIT 7
");
$r->bind_param('i',$uid); $r->execute();
$pendingSubs = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// ── My Batches ────────────────────────────────────────────────
$r = $conn->prepare("
    SELECT b.*, c.title as course_title,
           (SELECT COUNT(*) FROM batch_students WHERE batch_id=b.id) as student_count,
           (SELECT COUNT(*) FROM assignments WHERE batch_id=b.id) as assign_count
    FROM batch_teachers bt
    JOIN batches b ON bt.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE bt.teacher_id = ?
    ORDER BY b.status='active' DESC, b.created_at DESC
    LIMIT 5
");
$r->bind_param('i',$uid); $r->execute();
$myBatches = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// ── Recent Activity (own submissions graded / topics posted) ─
$r = $conn->prepare("
    SELECT s.graded_at as event_at, u.full_name as student_name,
           u.profile_picture, a.title as assignment_title,
           s.marks, a.total_marks,
           ROUND(s.marks / a.total_marks * 100, 0) as pct
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    WHERE a.batch_id IN (SELECT batch_id FROM batch_teachers WHERE teacher_id=?)
      AND s.status = 'graded' AND s.marks IS NOT NULL
    ORDER BY s.graded_at DESC
    LIMIT 5
");
$r->bind_param('i',$uid); $r->execute();
$recentGraded = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// ── Recent Topics ─────────────────────────────────────────────
$r = $conn->prepare("
    SELECT t.*, b.name as batch_name,
           (SELECT COUNT(*) FROM topic_files WHERE topic_id=t.id) as file_count
    FROM topics t
    LEFT JOIN batches b ON t.batch_id = b.id
    WHERE t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 4
");
$r->bind_param('i',$uid); $r->execute();
$recentTopics = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close();

// ── Computed ──────────────────────────────────────────────────
$hour         = (int)date('H');
$greeting     = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName    = explode(' ', $currentUser['full_name'])[0];
$gradingRate  = $totalSubs > 0 ? round(($totalGraded / $totalSubs) * 100) : 0;

// Chart JSON
$pieData         = json_encode([$toGrade, $totalGraded, $totalReturned]);
$batchLabels     = json_encode(array_map(fn($b) => mb_strimwidth($b['name'], 0, 14, '…'), $batchProgress));
$batchTotalSubs  = json_encode(array_map(fn($b) => (int)$b['total_subs'], $batchProgress));
$batchGraded     = json_encode(array_map(fn($b) => (int)$b['graded_subs'], $batchProgress));

include __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- PAGE HEADER -->
      <div class="page-header" style="margin-bottom:28px;align-items:center;">
        <div style="display:flex;align-items:center;gap:16px;">
          <?= userAvatar($currentUser, 52) ?>
          <div>
            <h1 class="page-title" style="font-size:1.5rem;margin:0;">
              <?= e($greeting) ?>, <?= e($firstName) ?>! 👋
            </h1>
            <p class="page-subtitle" style="margin:3px 0 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span><?= date('l, F j, Y') ?></span>
              <span style="color:var(--border);">·</span>
              <?php if ($toGrade > 0): ?>
                <span style="color:var(--warning);font-weight:600;">
                  <i data-lucide="inbox" style="width:13px;height:13px;vertical-align:-2px;"></i>
                  <?= $toGrade ?> submission<?= $toGrade>1?'s':'' ?> waiting to be graded
                </span>
              <?php else: ?>
                <span style="color:var(--success);font-weight:600;">
                  <i data-lucide="check-circle" style="width:13px;height:13px;vertical-align:-2px;"></i>
                  All submissions reviewed
                </span>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="<?= BASE_PATH ?>/teacher/assignments.php" class="btn btn-primary btn-sm">
            <i data-lucide="plus" style="width:15px;height:15px;"></i> New Assignment
          </a>
          <a href="<?= BASE_PATH ?>/teacher/topics.php" class="btn btn-secondary btn-sm">
            <i data-lucide="file-plus" style="width:15px;height:15px;"></i> Add Topic
          </a>
          <?php if ($toGrade > 0): ?>
            <a href="<?= BASE_PATH ?>/teacher/submissions.php" class="btn btn-ghost btn-sm" style="color:var(--warning);">
              <i data-lucide="edit-3" style="width:15px;height:15px;"></i> Grade Now
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- STATS GRID -->
      <div class="stats-grid stagger" style="margin-bottom:24px;">

        <div class="stat-card" style="--stat-color:#6366f1;--stat-bg:rgba(99,102,241,0.1);">
          <div class="stat-icon"><i data-lucide="layers" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number" data-count="<?= $totalBatches ?>">0</div>
            <div class="stat-label">My Batches</div>
            <div class="stat-trend up">
              <i data-lucide="users" style="width:11px;height:11px;"></i>
              <?= $totalStudents ?> student<?= $totalStudents!=1?'s':'' ?> total
            </div>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:#10b981;--stat-bg:rgba(16,185,129,0.1);">
          <div class="stat-icon"><i data-lucide="users" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number" data-count="<?= $totalStudents ?>">0</div>
            <div class="stat-label">Total Students</div>
            <div style="margin-top:9px;height:5px;background:var(--border);border-radius:99px;overflow:hidden;">
              <div style="height:100%;width:<?= $gradingRate ?>%;background:linear-gradient(90deg,#10b981,#06b6d4);border-radius:99px;transition:width 1.2s ease .3s;"></div>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;"><?= $gradingRate ?>% submissions graded</div>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:<?= $toGrade>0?'#f59e0b':'#10b981' ?>;--stat-bg:<?= $toGrade>0?'rgba(245,158,11,0.1)':'rgba(16,185,129,0.1)' ?>;">
          <div class="stat-icon"><i data-lucide="inbox" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number" data-count="<?= $toGrade ?>" style="<?= $toGrade>0?'color:var(--warning);':'' ?>">0</div>
            <div class="stat-label">To Grade</div>
            <?php if ($toGrade > 0): ?>
              <a href="<?= BASE_PATH ?>/teacher/submissions.php" style="font-size:0.78rem;color:var(--warning);font-weight:600;margin-top:4px;display:block;">Grade now →</a>
            <?php else: ?>
              <div class="stat-trend up"><i data-lucide="check" style="width:11px;height:11px;"></i> All caught up!</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:#8b5cf6;--stat-bg:rgba(139,92,246,0.1);">
          <div class="stat-icon"><i data-lucide="star" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number"><?= $classAvg > 0 ? $classAvg.'%' : '—' ?></div>
            <div class="stat-label">Class Average</div>
            <div class="stat-trend <?= $classAvg >= 70 ? 'up' : ($classAvg > 0 ? 'down' : '') ?>">
              <?php if ($classAvg >= 80): ?><i data-lucide="trending-up" style="width:11px;height:11px;"></i> Excellent
              <?php elseif ($classAvg >= 60): ?><i data-lucide="minus" style="width:11px;height:11px;"></i> Good
              <?php elseif ($classAvg > 0): ?><i data-lucide="trending-down" style="width:11px;height:11px;"></i> Needs attention
              <?php else: ?>No grades yet<?php endif; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- CHARTS ROW -->
      <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;margin-bottom:24px;">

        <!-- Doughnut: Submission Status -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="pie-chart" style="width:15px;height:15px;color:var(--primary);"></i>
              Submission Status
            </h3>
            <span style="font-size:0.75rem;color:var(--text-muted);font-weight:500;"><?= $totalSubs ?> total</span>
          </div>
          <div class="card-body" style="padding:20px 24px;display:flex;flex-direction:column;align-items:center;gap:18px;">
            <?php if ($totalSubs > 0): ?>
              <div style="position:relative;width:165px;height:165px;flex-shrink:0;">
                <canvas id="pieChart" width="165" height="165"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                  <div style="font-size:1.6rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;"><?= $gradingRate ?>%</div>
                  <div style="font-size:0.67rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.07em;">graded</div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px 12px;width:100%;">
                <?php
                $leg = [['#f59e0b','Pending',$toGrade],['#10b981','Graded',$totalGraded],['#8b5cf6','Returned',$totalReturned]];
                foreach($leg as [$c,$l,$n]): ?>
                  <div style="display:flex;align-items:center;gap:7px;">
                    <span style="width:9px;height:9px;border-radius:50%;background:<?= $c ?>;flex-shrink:0;"></span>
                    <span style="font-size:0.76rem;color:var(--text-secondary);"><?= $l ?> <strong style="color:var(--text);"><?= $n ?></strong></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state" style="padding:28px 0;">
                <div class="empty-state-icon">📊</div>
                <div class="empty-state-title" style="font-size:0.95rem;">No submissions yet</div>
                <div class="empty-state-text">Chart appears once students submit</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Grouped Bar: Per-Batch Grading Progress -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="bar-chart-2" style="width:15px;height:15px;color:var(--secondary);"></i>
              Grading Progress by Batch
            </h3>
            <a href="<?= BASE_PATH ?>/teacher/submissions.php" class="btn btn-ghost btn-sm">All Submissions</a>
          </div>
          <div class="card-body" style="padding:16px 20px;height:220px;display:flex;align-items:center;justify-content:center;">
            <?php if (!empty($batchProgress)): ?>
              <canvas id="batchBarChart" style="width:100%;height:190px;"></canvas>
            <?php else: ?>
              <div class="empty-state" style="padding:0;">
                <div class="empty-state-icon" style="font-size:2.5rem;">📈</div>
                <div class="empty-state-title">No batch data yet</div>
                <div class="empty-state-text">Assign students to batches to see progress</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- MIDDLE ROW: Pending Submissions + My Batches -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

        <!-- Pending Submissions -->
        <div class="card" style="display:flex;flex-direction:column;">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="inbox" style="width:15px;height:15px;color:var(--warning);"></i>
              Pending Reviews
              <?php if ($toGrade > 0): ?>
                <span class="badge badge-warning" style="font-size:0.7rem;"><?= $toGrade ?></span>
              <?php endif; ?>
            </h3>
            <a href="<?= BASE_PATH ?>/teacher/submissions.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;flex:1;">
            <?php if (empty($pendingSubs)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">🎉</div>
                <div class="empty-state-title">All reviewed!</div>
                <div class="empty-state-text">No pending submissions right now</div>
              </div>
            <?php else: ?>
              <?php foreach ($pendingSubs as $s): ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <?= userAvatar(['full_name'=>$s['student_name'],'profile_picture'=>$s['profile_picture'],'is_online'=>0], 36) ?>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($s['student_name']) ?></div>
                    <div style="font-size:0.74rem;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= e($s['assignment_title']) ?> · <?= e($s['batch_name']) ?>
                    </div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                    <span style="font-size:0.7rem;color:var(--text-muted);"><?= timeAgo($s['submitted_at']) ?></span>
                    <a href="<?= BASE_PATH ?>/teacher/submissions.php" class="btn btn-primary btn-sm" style="padding:4px 10px;font-size:0.72rem;">Grade</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- My Batches -->
        <div class="card" style="display:flex;flex-direction:column;">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="layers" style="width:15px;height:15px;color:var(--primary);"></i>
              My Batches
            </h3>
            <a href="<?= BASE_PATH ?>/teacher/my-batches.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;flex:1;">
            <?php if (empty($myBatches)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">🗂️</div>
                <div class="empty-state-title">No batches assigned</div>
                <div class="empty-state-text">Contact admin to be assigned to a batch</div>
              </div>
            <?php else: ?>
              <?php foreach ($myBatches as $b): ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="display:flex;align-items:flex-start;gap:11px;">
                    <div style="width:36px;height:36px;border-radius:var(--radius);background:rgba(99,102,241,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <i data-lucide="layers" style="width:15px;height:15px;color:var(--primary);"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($b['name']) ?></div>
                      <div style="font-size:0.74rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:5px;">
                        <i data-lucide="book-open" style="width:10px;height:10px;"></i>
                        <?= e($b['course_title']) ?>
                      </div>
                      <div style="margin-top:5px;display:flex;align-items:center;gap:8px;">
                        <span style="font-size:0.72rem;color:var(--text-muted);display:flex;align-items:center;gap:3px;">
                          <i data-lucide="users" style="width:10px;height:10px;"></i> <?= $b['student_count'] ?> students
                        </span>
                        <span style="font-size:0.72rem;color:var(--text-muted);display:flex;align-items:center;gap:3px;">
                          <i data-lucide="clipboard-list" style="width:10px;height:10px;"></i> <?= $b['assign_count'] ?> assignments
                        </span>
                      </div>
                    </div>
                    <?= statusBadge($b['status']) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- BOTTOM ROW: Recently Graded + Recent Topics -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <!-- Recently Graded -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="check-circle-2" style="width:15px;height:15px;color:var(--success);"></i>
              Recently Graded
            </h3>
            <a href="<?= BASE_PATH ?>/teacher/submissions.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($recentGraded)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">📝</div>
                <div class="empty-state-title">No grades given yet</div>
                <div class="empty-state-text">Your grading history will appear here</div>
              </div>
            <?php else: ?>
              <?php foreach ($recentGraded as $g):
                $pct = (int)$g['pct'];
                $sc  = $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#ef4444');
              ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:13px;transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <?= userAvatar(['full_name'=>$g['student_name'],'profile_picture'=>$g['profile_picture'],'is_online'=>0], 34) ?>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($g['student_name']) ?></div>
                    <div style="font-size:0.73rem;color:var(--text-muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($g['assignment_title']) ?></div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;">
                    <div style="width:38px;height:38px;border-radius:50%;border:2.5px solid <?= $sc ?>;background:<?= $sc ?>18;display:flex;align-items:center;justify-content:center;">
                      <span style="font-size:0.68rem;font-weight:700;color:<?= $sc ?>;"><?= $pct ?>%</span>
                    </div>
                    <span style="font-size:0.68rem;color:var(--text-muted);"><?= formatDate($g['event_at'],'M j') ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Topics -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="book-marked" style="width:15px;height:15px;color:var(--accent);"></i>
              My Recent Topics
            </h3>
            <a href="<?= BASE_PATH ?>/teacher/topics.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($recentTopics)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">📚</div>
                <div class="empty-state-title">No topics yet</div>
                <div class="empty-state-text">Topics you create will appear here</div>
              </div>
            <?php else: ?>
              <?php foreach ($recentTopics as $t): ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:11px;transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="width:34px;height:34px;border-radius:var(--radius);background:rgba(6,182,212,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="file-text" style="width:14px;height:14px;color:var(--accent);"></i>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($t['title']) ?></div>
                    <div style="font-size:0.73rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:5px;">
                      <i data-lucide="layers" style="width:10px;height:10px;"></i>
                      <?= e($t['batch_name'] ?? 'No batch') ?>
                      <?php if ($t['file_count'] > 0): ?>
                        <span>·</span>
                        <i data-lucide="paperclip" style="width:10px;height:10px;"></i>
                        <?= $t['file_count'] ?> file<?= $t['file_count']!=1?'s':'' ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                    <span style="font-size:0.7rem;color:var(--text-muted);"><?= timeAgo($t['created_at']) ?></span>
                    <a href="<?= BASE_PATH ?>/teacher/topics.php" class="btn btn-ghost btn-sm" style="padding:4px 9px;font-size:0.72rem;">Edit</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>

    </main>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const isDark    = document.documentElement.classList.contains('dark');
  const textColor = isDark ? '#94a3b8' : '#64748b';
  const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
  const cardBg    = isDark ? '#0f0f23' : '#ffffff';

  // ── Doughnut: Submission Status ───────────────────────────
  const pieEl = document.getElementById('pieChart');
  if (pieEl) {
    const raw   = <?= $pieData ?>;
    const total = raw.reduce((a,b) => a+b, 0);
    new Chart(pieEl, {
      type: 'doughnut',
      data: {
        labels: ['Pending','Graded','Returned'],
        datasets: [{
          data: total > 0 ? raw : [1],
          backgroundColor: total > 0
            ? ['#f59e0b','#10b981','#8b5cf6']
            : [isDark ? '#1e293b' : '#e2e8f0'],
          borderColor: cardBg,
          borderWidth: 3,
          hoverOffset: 7,
        }]
      },
      options: {
        cutout: '72%',
        responsive: false,
        animation: { animateScale: true, duration: 1000, easing: 'easeOutQuart' },
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: total > 0,
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed/total*100)}%)`
            }
          }
        }
      }
    });
  }

  // ── Grouped Bar: Batch Grading Progress ───────────────────
  const barEl = document.getElementById('batchBarChart');
  if (barEl) {
    const labels = <?= $batchLabels ?>;
    const totals = <?= $batchTotalSubs ?>;
    const graded = <?= $batchGraded ?>;

    new Chart(barEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Total Submitted',
            data: totals,
            backgroundColor: isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.15)',
            borderColor: '#6366f1',
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
          },
          {
            label: 'Graded',
            data: graded,
            backgroundColor: 'rgba(16,185,129,0.75)',
            borderColor: '#10b981',
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
              color: textColor,
              font: { size: 11, family: 'DM Sans' },
              boxWidth: 10,
              boxHeight: 10,
              borderRadius: 3,
              useBorderRadius: true,
              padding: 12,
            }
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: textColor, font: { size: 11, family: 'DM Sans' }, maxRotation: 30 },
            grid: { display: false },
            border: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: { color: textColor, font: { size: 11, family: 'DM Sans' }, stepSize: 1, precision: 0 },
            grid: { color: gridColor },
            border: { display: false }
          }
        }
      }
    });
  }

  // document.addEventListener('themechange', () => setTimeout(() => location.reload(), 400));
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>