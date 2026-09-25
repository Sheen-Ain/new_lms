<?php
// ============================================================
// STUDENT DASHBOARD — Upgraded
// ============================================================
$requiredRole = 'student';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Dashboard';
$uid = (int)$currentUser['id'];

// ── Core Stats ──────────────────────────────────────────────
$r = $conn->prepare("
    SELECT COUNT(DISTINCT b.course_id) as courses, COUNT(DISTINCT bs.batch_id) as batches
    FROM batch_students bs
    JOIN batches b ON bs.batch_id = b.id
    WHERE bs.student_id = ?
");
$r->bind_param('i', $uid);
$r->execute();
$stats = $r->get_result()->fetch_assoc();
$r->close();

// Total assignments assigned to student
$r = $conn->prepare("
    SELECT COUNT(*) as cnt FROM assignments a
    JOIN batch_students bs ON a.batch_id = bs.batch_id
    WHERE bs.student_id = ? AND a.status = 'active'
");
$r->bind_param('i', $uid);
$r->execute();
$totalAssigned = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Pending (not submitted)
$r = $conn->prepare("
    SELECT COUNT(*) as cnt FROM assignments a
    JOIN batch_students bs ON a.batch_id = bs.batch_id
    WHERE bs.student_id = ? AND a.status = 'active'
      AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = ?)
");
$r->bind_param('ii', $uid, $uid);
$r->execute();
$pending = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Submitted (awaiting grade)
$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions WHERE student_id = ? AND status = 'submitted'");
$r->bind_param('i', $uid);
$r->execute();
$submitted = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Graded
$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions WHERE student_id = ? AND status = 'graded'");
$r->bind_param('i', $uid);
$r->execute();
$graded = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Returned
$r = $conn->prepare("SELECT COUNT(*) as cnt FROM submissions WHERE student_id = ? AND status = 'returned'");
$r->bind_param('i', $uid);
$r->execute();
$returned = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Average marks (graded only)
$r = $conn->prepare("
    SELECT AVG(s.marks / a.total_marks * 100) as avg_pct
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.student_id = ? AND s.status = 'graded' AND a.total_marks > 0 AND s.marks IS NOT NULL
");
$r->bind_param('i', $uid);
$r->execute();
$avgPct = round((float)($r->get_result()->fetch_assoc()['avg_pct'] ?? 0), 1);
$r->close();

// Overdue (past due, not submitted)
$r = $conn->prepare("
    SELECT COUNT(*) as cnt FROM assignments a
    JOIN batch_students bs ON a.batch_id = bs.batch_id
    WHERE bs.student_id = ? AND a.status = 'active'
      AND a.due_date IS NOT NULL AND a.due_date < NOW()
      AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = ?)
");
$r->bind_param('ii', $uid, $uid);
$r->execute();
$overdue = (int)$r->get_result()->fetch_assoc()['cnt'];
$r->close();

// Grades for bar chart (last 7 graded, oldest first)
$r = $conn->prepare("
    SELECT a.title, s.marks, a.total_marks,
           ROUND(s.marks / a.total_marks * 100, 1) as pct
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.student_id = ? AND s.status = 'graded'
      AND a.total_marks > 0 AND s.marks IS NOT NULL
    ORDER BY s.graded_at DESC
    LIMIT 7
");
$r->bind_param('i', $uid);
$r->execute();
$gradeRows = array_reverse($r->get_result()->fetch_all(MYSQLI_ASSOC));
$r->close();

// Recent Announcements
$r = $conn->prepare("
    SELECT an.*, b.name as batch_name
    FROM announcements an
    LEFT JOIN batches b ON an.batch_id = b.id
    WHERE an.status = 'published'
      AND (an.batch_id IS NULL OR an.batch_id IN (SELECT batch_id FROM batch_students WHERE student_id = ?))
    ORDER BY an.is_pinned DESC, an.created_at DESC
    LIMIT 6
");
$r->bind_param('i', $uid);
$r->execute();
$announcements = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

// Upcoming Assignments
$r = $conn->prepare("
    SELECT a.*, b.name as batch_name, s.status as sub_status
    FROM assignments a
    JOIN batch_students bs ON a.batch_id = bs.batch_id
    LEFT JOIN batches b ON a.batch_id = b.id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE bs.student_id = ? AND a.status = 'active'
      AND (a.due_date IS NULL OR a.due_date > NOW())
    ORDER BY a.due_date ASC
    LIMIT 5
");
$r->bind_param('ii', $uid, $uid);
$r->execute();
$upcoming = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

// Recent Grades
$r = $conn->prepare("
    SELECT s.marks, s.feedback, s.graded_at, a.title as assignment_title,
           a.total_marks, b.name as batch_name,
           ROUND(s.marks / a.total_marks * 100, 0) as pct
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    LEFT JOIN batches b ON a.batch_id = b.id
    WHERE s.student_id = ? AND s.status = 'graded' AND s.marks IS NOT NULL
    ORDER BY s.graded_at DESC
    LIMIT 5
");
$r->bind_param('i', $uid);
$r->execute();
$recentGrades = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

// Recent Topics
$r = $conn->prepare("
    SELECT t.*, b.name as batch_name,
           (SELECT COUNT(*) FROM topic_files WHERE topic_id = t.id) as file_count
    FROM topics t
    JOIN batches b ON t.batch_id = b.id
    JOIN batch_students bs ON b.id = bs.batch_id
    WHERE bs.student_id = ? AND t.status = 'active'
    ORDER BY t.created_at DESC
    LIMIT 4
");
$r->bind_param('i', $uid);
$r->execute();
$recentTopics = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

// Computed values
$hour           = (int)date('H');
$greeting       = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName      = explode(' ', $currentUser['full_name'])[0];
$completionRate = $totalAssigned > 0 ? round((($submitted + $graded + $returned) / $totalAssigned) * 100) : 0;
$chartLabels    = json_encode(array_map(function ($r) {
  return mb_strimwidth($r['title'], 0, 18, '…');
}, $gradeRows));
$chartMarks     = json_encode(array_map(function ($r) {
  return (float)$r['pct'];
}, $gradeRows));
$pieData        = json_encode([$graded, $submitted, max(0, $pending - $overdue), $overdue]);

include __DIR__ . '/../includes/header.php';
?>
<style>
  /* ── Student Dashboard Responsive ─────────────────────────── */
  .sd-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 28px;
  }

  .sd-page-header-left {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
  }

  .sd-page-header-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .sd-charts-row {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    margin-bottom: 24px;
  }

  .sd-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
  }

  /* Tablet ≤ 1024px */
  @media (max-width: 1024px) {
    .sd-charts-row {
      grid-template-columns: 260px 1fr;
    }
  }

  /* ≤ 900px: collapse charts to single col */
  @media (max-width: 900px) {
    .sd-charts-row {
      grid-template-columns: 1fr;
    }

    .sd-two-col {
      grid-template-columns: 1fr;
    }
  }

  /* ≤ 640px: mobile adjustments */
  @media (max-width: 640px) {
    .sd-page-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .sd-page-header-right {
      width: 100%;
    }

    .sd-page-header-right .btn {
      flex: 1;
      justify-content: center;
    }

    .sd-page-header-left .page-title {
      font-size: 1.2rem !important;
    }

    .stats-grid {
      grid-template-columns: 1fr 1fr !important;
    }
  }

  /* ≤ 420px: single-col stats */
  @media (max-width: 420px) {
    .stats-grid {
      grid-template-columns: 1fr !important;
    }

    .sd-page-header-right .btn {
      font-size: 0.78rem;
      padding: 6px 10px;
    }
  }
</style>

<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- ── LIVE SESSIONS BANNER ──────────────────────── -->
      <div id="live-banner-wrap" style="display:none;margin-bottom:20px;"></div>
      <div class="sd-page-header">
        <div class="sd-page-header-left">
          <?= userAvatar($currentUser, 52) ?>
          <div>
            <h1 class="page-title" style="font-size:1.5rem;margin:0;">
              <?= e($greeting) ?>, <?= e($firstName) ?>! 👋
            </h1>
            <p class="page-subtitle" style="margin:3px 0 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span><?= date('l, F j, Y') ?></span>
              <span style="color:var(--border);">·</span>
              <?php if ($overdue > 0): ?>
                <span style="color:var(--danger);font-weight:600;"><i data-lucide="alert-circle" style="width:13px;height:13px;vertical-align:-2px;"></i> <?= $overdue ?> overdue</span>
              <?php elseif ($pending > 0): ?>
                <span style="color:var(--warning);font-weight:600;"><i data-lucide="clock" style="width:13px;height:13px;vertical-align:-2px;"></i> <?= $pending ?> assignment<?= $pending > 1 ? 's' : '' ?> pending</span>
              <?php else: ?>
                <span style="color:var(--success);font-weight:600;"><i data-lucide="check-circle" style="width:13px;height:13px;vertical-align:-2px;"></i> All caught up!</span>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <div class="sd-page-header-right">
          <a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-primary btn-sm">
            <i data-lucide="clipboard-list" style="width:15px;height:15px;"></i> Assignments
          </a>
          <a href="<?= BASE_PATH ?>/student/topics.php" class="btn btn-secondary btn-sm">
            <i data-lucide="book-open" style="width:15px;height:15px;"></i> Topics
          </a>
          <a href="<?= BASE_PATH ?>/student/announcements.php" class="btn btn-ghost btn-sm">
            <i data-lucide="megaphone" style="width:15px;height:15px;"></i> Announcements
          </a>
        </div>
      </div>

      <!-- STATS GRID -->
      <div class="stats-grid stagger" style="margin-bottom:24px;">

        <div class="stat-card" style="--stat-color:#6366f1;--stat-bg:rgba(99,102,241,0.1);">
          <div class="stat-icon"><i data-lucide="book-open" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number" data-count="<?= $stats['courses'] ?? 0 ?>">0</div>
            <div class="stat-label">Enrolled Courses</div>
            <div class="stat-trend up">
              <i data-lucide="layers" style="width:11px;height:11px;"></i>
              <?= $stats['batches'] ?? 0 ?> active batch<?= ($stats['batches'] ?? 0) != 1 ? 'es' : '' ?>
            </div>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:#10b981;--stat-bg:rgba(16,185,129,0.1);">
          <div class="stat-icon"><i data-lucide="check-circle-2" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number"><?= $completionRate ?>%</div>
            <div class="stat-label">Completion Rate</div>
            <div style="margin-top:9px;height:5px;background:var(--border);border-radius:99px;overflow:hidden;">
              <div style="height:100%;width:<?= $completionRate ?>%;background:linear-gradient(90deg,#10b981,#06b6d4);border-radius:99px;transition:width 1.2s ease 0.3s;"></div>
            </div>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:#8b5cf6;--stat-bg:rgba(139,92,246,0.1);">
          <div class="stat-icon"><i data-lucide="star" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number"><?= $avgPct > 0 ? $avgPct . '%' : '—' ?></div>
            <div class="stat-label">Average Score</div>
            <div class="stat-trend <?= $avgPct >= 70 ? 'up' : ($avgPct > 0 ? 'down' : '') ?>">
              <?php if ($avgPct >= 80): ?><i data-lucide="trending-up" style="width:11px;height:11px;"></i> Excellent
              <?php elseif ($avgPct >= 60): ?><i data-lucide="minus" style="width:11px;height:11px;"></i> Good progress
              <?php elseif ($avgPct > 0): ?><i data-lucide="trending-down" style="width:11px;height:11px;"></i> Needs improvement
                <?php else: ?>No grades yet<?php endif; ?>
            </div>
          </div>
        </div>

        <div class="stat-card" style="--stat-color:<?= $overdue > 0 ? '#ef4444' : '#f59e0b' ?>;--stat-bg:<?= $overdue > 0 ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)' ?>;">
          <div class="stat-icon"><i data-lucide="<?= $overdue > 0 ? 'alert-circle' : 'clock' ?>" style="width:24px;height:24px;"></i></div>
          <div class="stat-content">
            <div class="stat-number" data-count="<?= $pending ?>" style="<?= $overdue > 0 ? 'color:var(--danger);' : '' ?>">0</div>
            <div class="stat-label">Pending Tasks</div>
            <?php if ($overdue > 0): ?>
              <a href="<?= BASE_PATH ?>/student/assignments.php" style="font-size:0.78rem;color:var(--danger);font-weight:600;margin-top:4px;display:block;"><?= $overdue ?> overdue →</a>
            <?php elseif ($pending > 0): ?>
              <a href="<?= BASE_PATH ?>/student/assignments.php" style="font-size:0.78rem;color:var(--warning);font-weight:600;margin-top:4px;display:block;">Submit now →</a>
            <?php else: ?>
              <div class="stat-trend up"><i data-lucide="check" style="width:11px;height:11px;"></i> All done!</div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- CHARTS ROW -->
      <div class="sd-charts-row">

        <!-- Doughnut: Assignment Status -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="pie-chart" style="width:15px;height:15px;color:var(--primary);"></i>
              Assignment Status
            </h3>
            <span style="font-size:0.75rem;color:var(--text-muted);font-weight:500;"><?= $totalAssigned ?> total</span>
          </div>
          <div class="card-body" style="padding:20px 24px;display:flex;flex-direction:column;align-items:center;gap:18px;">
            <?php if ($totalAssigned > 0): ?>
              <div style="position:relative;width:175px;height:175px;flex-shrink:0;">
                <canvas id="pieChart" width="175" height="175"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                  <div style="font-size:1.65rem;font-weight:700;font-family:'Poppins',sans-serif;line-height:1.1;"><?= $completionRate ?>%</div>
                  <div style="font-size:0.68rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.07em;">complete</div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 12px;width:100%;">
                <?php
                $legend = [
                  ['#10b981', 'Graded', $graded],
                  ['#3b82f6', 'Submitted', $submitted],
                  ['#f59e0b', 'Pending', max(0, $pending - $overdue)],
                  ['#ef4444', 'Overdue', $overdue],
                ];
                foreach ($legend as [$c, $l, $n]): ?>
                  <div style="display:flex;align-items:center;gap:7px;">
                    <span style="width:9px;height:9px;border-radius:50%;background:<?= $c ?>;flex-shrink:0;"></span>
                    <span style="font-size:0.77rem;color:var(--text-secondary);"><?= $l ?> <strong style="color:var(--text);"><?= $n ?></strong></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state" style="padding:28px 0;">
                <div class="empty-state-icon">📊</div>
                <div class="empty-state-title" style="font-size:0.95rem;">No data yet</div>
                <div class="empty-state-text">Charts appear once assignments are posted</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Bar: Grade Performance -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="bar-chart-2" style="width:15px;height:15px;color:var(--secondary);"></i>
              Grade Performance
            </h3>
            <?php if (!empty($gradeRows)): ?>
              <a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-ghost btn-sm">View Grades</a>
            <?php endif; ?>
          </div>
          <div class="card-body" style="padding:16px 20px;position:relative;height:220px;display:flex;align-items:center;justify-content:center;">
            <?php if (!empty($gradeRows)): ?>
              <canvas id="gradeBarChart" style="width:100%;height:190px;"></canvas>
            <?php else: ?>
              <div class="empty-state" style="padding:0;">
                <div class="empty-state-icon" style="font-size:2.5rem;">📈</div>
                <div class="empty-state-title">No grades yet</div>
                <div class="empty-state-text">Your score history will appear here</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- MIDDLE ROW: Announcements + Upcoming -->
      <div class="sd-two-col">

        <!-- Announcements -->
        <div class="card" style="display:flex;flex-direction:column;">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="megaphone" style="width:15px;height:15px;color:var(--accent);"></i>
              Announcements
            </h3>
            <a href="<?= BASE_PATH ?>/student/announcements.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;flex:1;">
            <?php if (empty($announcements)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">📢</div>
                <div class="empty-state-title">No announcements</div>
                <div class="empty-state-text">Check back soon</div>
              </div>
            <?php else: ?>
              <?php foreach ($announcements as $ann): ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);transition:background .15s;cursor:default;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="display:flex;align-items:flex-start;gap:11px;">
                    <div style="width:34px;height:34px;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $ann['priority'] === 'urgent' ? 'rgba(239,68,68,0.1)' : ($ann['priority'] === 'important' ? 'rgba(245,158,11,0.1)' : 'rgba(99,102,241,0.1)') ?>;">
                      <i data-lucide="<?= $ann['priority'] === 'urgent' ? 'alert-triangle' : ($ann['priority'] === 'important' ? 'star' : 'bell') ?>"
                        style="width:14px;height:14px;color:<?= $ann['priority'] === 'urgent' ? 'var(--danger)' : ($ann['priority'] === 'important' ? 'var(--warning)' : 'var(--primary)') ?>;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= $ann['is_pinned'] ? '<span style="color:var(--primary);">📌 </span>' : '' ?><?= e($ann['title']) ?>
                      </div>
                      <div style="font-size:0.74rem;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;gap:5px;">
                        <i data-lucide="layers" style="width:10px;height:10px;"></i>
                        <?= e($ann['batch_name'] ?? 'Global') ?> · <?= timeAgo($ann['created_at']) ?>
                      </div>
                    </div>
                    <?php if ($ann['priority'] === 'urgent'): ?>
                      <span class="badge badge-danger badge-pulse" style="flex-shrink:0;font-size:0.7rem;">Urgent</span>
                    <?php elseif ($ann['priority'] === 'important'): ?>
                      <span class="badge badge-warning" style="flex-shrink:0;font-size:0.7rem;">Important</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="card" style="display:flex;flex-direction:column;">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="clock" style="width:15px;height:15px;color:var(--warning);"></i>
              Upcoming Deadlines
            </h3>
            <a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;flex:1;">
            <?php if (empty($upcoming)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">🎉</div>
                <div class="empty-state-title">All caught up!</div>
                <div class="empty-state-text">No upcoming deadlines — great work!</div>
              </div>
            <?php else: ?>
              <?php foreach ($upcoming as $a): ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="display:flex;align-items:flex-start;gap:11px;">
                    <div style="width:34px;height:34px;border-radius:var(--radius);background:rgba(99,102,241,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <i data-lucide="file-text" style="width:14px;height:14px;color:var(--primary);"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($a['title']) ?></div>
                      <div style="font-size:0.74rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:5px;">
                        <i data-lucide="layers" style="width:10px;height:10px;"></i>
                        <?= e($a['batch_name']) ?>
                        <?php if ($a['total_marks']): ?><span>·</span><?= $a['total_marks'] ?> marks<?php endif; ?>
                      </div>
                      <div style="margin-top:5px;">
                        <?php if ($a['sub_status']): ?>
                          <span class="badge badge-success"><span class="badge-dot" style="background:#10b981;"></span>Submitted</span>
                        <?php elseif ($a['due_date']): ?>
                          <?= dueDateBadge($a['due_date'], $a['allow_late']) ?>
                        <?php else: ?>
                          <span class="badge badge-secondary">No due date</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <a href="<?= BASE_PATH ?>/student/assignments.php" class="btn btn-ghost btn-sm" style="padding:5px 10px;font-size:0.74rem;flex-shrink:0;">
                      <?= $a['sub_status'] ? 'View' : 'Submit' ?>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- BOTTOM ROW: Recent Grades + Recent Topics -->
      <div class="sd-two-col" style="margin-bottom:0;">

        <!-- Recent Grades -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="award" style="width:15px;height:15px;color:#f59e0b;"></i>
              Recent Grades
            </h3>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($recentGrades)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">🏅</div>
                <div class="empty-state-title">No grades yet</div>
                <div class="empty-state-text">Scores will appear once assignments are graded</div>
              </div>
            <?php else: ?>
              <?php foreach ($recentGrades as $g):
                $pct = (int)$g['pct'];
                $sc  = $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#ef4444');
              ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:14px;transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="width:42px;height:42px;border-radius:50%;border:2.5px solid <?= $sc ?>;background:<?= $sc ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span style="font-size:0.71rem;font-weight:700;color:<?= $sc ?>;"><?= $pct ?>%</span>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($g['assignment_title']) ?></div>
                    <div style="font-size:0.74rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:6px;">
                      <span><?= e($g['batch_name'] ?? '—') ?></span>
                      <span>·</span>
                      <span><?= $g['marks'] ?> / <?= $g['total_marks'] ?> marks</span>
                    </div>
                    <?php if (!empty($g['feedback'])): ?>
                      <div style="font-size:0.72rem;color:var(--text-muted);margin-top:3px;font-style:italic;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">"<?= e(mb_strimwidth($g['feedback'], 0, 55, '…')) ?>"</div>
                    <?php endif; ?>
                  </div>
                  <span style="font-size:0.71rem;color:var(--text-muted);flex-shrink:0;"><?= formatDate($g['graded_at'], 'M j') ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Study Material -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title" style="font-size:0.93rem;gap:7px;">
              <i data-lucide="book-marked" style="width:15px;height:15px;color:var(--accent);"></i>
              Recent Study Material
            </h3>
            <a href="<?= BASE_PATH ?>/student/topics.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($recentTopics)): ?>
              <div class="empty-state" style="padding:44px 24px;">
                <div class="empty-state-icon">📚</div>
                <div class="empty-state-title">No topics yet</div>
                <div class="empty-state-text">Study materials will appear here once uploaded</div>
              </div>
            <?php else: ?>
              <?php foreach ($recentTopics as $t): ?>
                <div style="padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:11px;transition:background .15s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                  <div style="width:34px;height:34px;border-radius:var(--radius);background:rgba(6,182,212,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="book-open" style="width:14px;height:14px;color:var(--accent);"></i>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($t['title']) ?></div>
                    <div style="font-size:0.74rem;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:5px;">
                      <i data-lucide="layers" style="width:10px;height:10px;"></i>
                      <?= e($t['batch_name']) ?>
                      <?php if ($t['file_count'] > 0): ?>
                        <span>·</span><i data-lucide="paperclip" style="width:10px;height:10px;"></i>
                        <?= $t['file_count'] ?> file<?= $t['file_count'] != 1 ? 's' : '' ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;">
                    <span style="font-size:0.7rem;color:var(--text-muted);"><?= timeAgo($t['created_at']) ?></span>
                    <a href="<?= BASE_PATH ?>/student/topics.php" class="btn btn-ghost btn-sm" style="padding:4px 9px;font-size:0.72rem;">Open</a>
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
  document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    const cardBg = isDark ? '#0f0f23' : '#ffffff';

    // ── Doughnut: Assignment Status ──────────────────────────
    const pieEl = document.getElementById('pieChart');
    if (pieEl) {
      const raw = <?= $pieData ?>;
      const total = raw.reduce((a, b) => a + b, 0);
      new Chart(pieEl, {
        type: 'doughnut',
        data: {
          labels: ['Graded', 'Submitted', 'Pending', 'Overdue'],
          datasets: [{
            data: total > 0 ? raw : [1],
            backgroundColor: total > 0 ?
              ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'] :
              [isDark ? '#1e293b' : '#e2e8f0'],
            borderColor: cardBg,
            borderWidth: 3,
            hoverOffset: 7,
          }]
        },
        options: {
          cutout: '73%',
          responsive: false,
          animation: {
            animateScale: true,
            duration: 1000,
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              enabled: total > 0,
              callbacks: {
                label: ctx => ` ${ctx.label}: ${ctx.parsed}  (${Math.round(ctx.parsed/total*100)}%)`
              }
            }
          }
        }
      });
    }

    // ── Bar: Grade Performance ────────────────────────────────
    const barEl = document.getElementById('gradeBarChart');
    if (barEl) {
      const labels = <?= $chartLabels ?>;
      const marks = <?= $chartMarks ?>;
      const bgs = marks.map(v => v >= 80 ? 'rgba(16,185,129,0.8)' : v >= 60 ? 'rgba(245,158,11,0.8)' : 'rgba(239,68,68,0.8)');
      const bords = marks.map(v => v >= 80 ? '#10b981' : v >= 60 ? '#f59e0b' : '#ef4444');

      new Chart(barEl, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Score %',
            data: marks,
            backgroundColor: bgs,
            borderColor: bords,
            borderWidth: 2,
            borderRadius: 7,
            borderSkipped: false,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: {
            duration: 900,
            easing: 'easeOutQuart'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: ctx => ` Score: ${ctx.parsed.y}%`
              }
            }
          },
          scales: {
            x: {
              ticks: {
                color: textColor,
                font: {
                  size: 11,
                  family: 'DM Sans'
                },
                maxRotation: 30
              },
              grid: {
                display: false
              },
              border: {
                display: false
              }
            },
            y: {
              min: 0,
              max: 100,
              ticks: {
                color: textColor,
                font: {
                  size: 11,
                  family: 'DM Sans'
                },
                callback: v => v + '%',
                stepSize: 25
              },
              grid: {
                color: gridColor
              },
              border: {
                display: false
              }
            }
          }
        }
      });
    }

    // Re-render on theme switch
    // document.addEventListener('themechange', () => setTimeout(() => location.reload(), 400));
  });

  /* ── Live Session Banner ──────────────────────────────────── */
  (function() {
    const wrap = document.getElementById('live-banner-wrap');

    function fmtDuration(mins) {
      mins = parseInt(mins) || 0;
      if (mins < 1) return 'just started';
      if (mins < 60) return mins + 'm ago';
      return Math.floor(mins / 60) + 'h ' + (mins % 60) + 'm ago';
    }

    function renderBanner(sessions) {
      if (!sessions || !sessions.length) {
        wrap.style.display = 'none';
        wrap.innerHTML = '';
        return;
      }
      wrap.style.display = 'block';
      wrap.innerHTML = sessions.map(s => `
      <div style="
        display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        padding:14px 20px;
        background:linear-gradient(135deg,rgba(239,68,68,0.08),rgba(239,68,68,0.04));
        border:1.5px solid rgba(239,68,68,0.25);
        border-radius:14px; position:relative; overflow:hidden;
      ">
        <!-- animated pulse bg -->
        <div style="position:absolute;inset:0;background:radial-gradient(circle at 10% 50%,rgba(239,68,68,0.06),transparent 60%);pointer-events:none;"></div>

        <!-- red pulse dot -->
        <div style="position:relative;flex-shrink:0;">
          <div style="width:12px;height:12px;background:#ef4444;border-radius:50%;"></div>
          <div style="position:absolute;inset:-4px;background:rgba(239,68,68,0.3);border-radius:50%;animation:bannerPulse 1.4s ease-in-out infinite;"></div>
        </div>

        <!-- info -->
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:0.88rem;color:var(--text);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="color:#ef4444;font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;background:rgba(239,68,68,0.12);padding:2px 8px;border-radius:999px;border:1px solid rgba(239,68,68,0.2);">🔴 Live Now</span>
            ${escH(s.title)}
          </div>
          <div style="font-size:0.78rem;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span>${escH(s.course_title)} › ${escH(s.batch_name)}</span>
            <span>·</span>
            <span>👨‍🏫 ${escH(s.teacher_name)}</span>
            <span>·</span>
            <span>Started ${fmtDuration(s.duration_minutes)}</span>
          </div>
        </div>

        <!-- join button -->
        <a href="<?= BASE_PATH ?>/student/live.php?session_id=${s.id}"
           style="
             display:inline-flex; align-items:center; gap:7px;
             padding:9px 20px;
             background:linear-gradient(135deg,#ef4444,#dc2626);
             color:#fff; text-decoration:none; border-radius:10px;
             font-weight:700; font-size:0.84rem;
             box-shadow:0 4px 14px rgba(239,68,68,0.4);
             transition:transform 0.15s, box-shadow 0.15s;
             flex-shrink:0; white-space:nowrap;
           "
           onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 20px rgba(239,68,68,0.55)'"
           onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(239,68,68,0.4)'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          Join Now
        </a>
      </div>
    `).join('<div style="height:8px;"></div>');
    }

    function escH(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    async function checkLive() {
      try {
        const fd = new FormData();
        fd.append('action', 'get_active');
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        const r = await fetch(window.LMS_BASE + '/ajax/live.ajax.php', {
          method: 'POST',
          body: fd
        });
        const d = await r.json();
        if (d.status === 'success') renderBanner(d.data.sessions);
      } catch (e) {}
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', checkLive);

    // Poll every 30 s
    setInterval(checkLive, 10000); // Poll every 10s — faster fallback when Pusher blocked

    // Real-time via Pusher DOM events (dispatched by chat-widget.php)
    document.addEventListener('lms:live-started', () => {
      checkLive();
      if (window.Toast) Toast.info('🔴 A live class just started! Check the banner above.');
    });
    document.addEventListener('lms:live-ended', checkLive);
  })();
</script>

<style>
  @keyframes bannerPulse {

    0%,
    100% {
      transform: scale(1);
      opacity: 1;
    }

    50% {
      transform: scale(1.9);
      opacity: 0;
    }
  }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>