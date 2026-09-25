<?php
// ============================================================
// ADMIN DASHBOARD — Fully Upgraded
// ============================================================
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle   = 'Dashboard';
$breadcrumbs = [['label' => 'Admin'], ['label' => 'Dashboard']];

// ── Core Stats ────────────────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active'");
$totalUsers = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active' AND current_role='student'");
$totalStudents = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active' AND current_role='teacher'");
$totalTeachers = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM courses WHERE status != 'archived'");
$totalCourses = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM batches WHERE status='active'");
$totalBatches = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(DISTINCT bs.student_id) as c FROM batch_students bs JOIN batches b ON bs.batch_id=b.id WHERE b.status='active'");
$enrolledStudents = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM submissions WHERE status='submitted'");
$pendingSubs = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM submissions WHERE status='graded'");
$gradedSubs = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM submissions");
$totalSubs = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM submissions WHERE status='returned'");
$returnedSubs = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at)=CURDATE()");
$todayActivity = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE()");
$newToday = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_online=1");
$onlineNow = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM assignments WHERE status='active'");
$totalAssignments = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM topics WHERE status='active'");
$totalTopics = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM announcements WHERE status='published'");
$totalAnnouncements = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM live_sessions WHERE status IN ('active','waiting')");
$liveSessions = (int)$r->fetch_assoc()['c'];
$gradingRate = $totalSubs > 0 ? round($gradedSubs / $totalSubs * 100) : 0;
$enrolRate   = $totalStudents > 0 ? round($enrolledStudents / $totalStudents * 100) : 0;

// ── Registration trend (last 30 days) ─────────────────────────
$r = $conn->query("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$regMap = [];
while ($row = $r->fetch_assoc()) $regMap[$row['day']] = (int)$row['cnt'];
$registrations = [];
for ($i = 29; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-{$i} days"));
  $registrations[] = ['date' => date('M j', strtotime($d)), 'count' => $regMap[$d] ?? 0];
}

// ── Activity last 14 days ─────────────────────────────────────
$r = $conn->query("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM activity_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$actMap = [];
while ($row = $r->fetch_assoc()) $actMap[$row['day']] = (int)$row['cnt'];
$activityTrend = [];
for ($i = 13; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-{$i} days"));
  $activityTrend[] = ['date' => date('M j', strtotime($d)), 'count' => $actMap[$d] ?? 0];
}

// ── Role distribution ─────────────────────────────────────────
$roleData = [];
$r = $conn->query("SELECT r.label, COUNT(ur.user_id) as cnt FROM roles r LEFT JOIN user_roles ur ON r.id=ur.role_id GROUP BY r.id,r.label");
while ($row = $r->fetch_assoc()) $roleData[] = $row;

// ── Top assignments ───────────────────────────────────────────
$assignmentData = [];
$r = $conn->query("SELECT a.title, COUNT(s.id) as cnt FROM assignments a LEFT JOIN submissions s ON a.id=s.assignment_id GROUP BY a.id ORDER BY cnt DESC LIMIT 6");
while ($row = $r->fetch_assoc()) $assignmentData[] = $row;

// ── Recent users ──────────────────────────────────────────────
$recentUsers = [];
$r = $conn->query("SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.id) as all_roles FROM users u LEFT JOIN user_roles ur ON u.id=ur.user_id LEFT JOIN roles r ON ur.role_id=r.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 5");
while ($row = $r->fetch_assoc()) $recentUsers[] = $row;

// ── Recent activity ───────────────────────────────────────────
$recentLogs = [];
$r = $conn->query("SELECT al.*, u.full_name, u.profile_picture FROM activity_logs al JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 8");
while ($row = $r->fetch_assoc()) $recentLogs[] = $row;

// ── Batch health ──────────────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) as c FROM batches WHERE status='active' AND end_date IS NOT NULL AND end_date < CURDATE()");
$expiredBatches = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM batches WHERE status='active' AND end_date IS NOT NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiringBatches = (int)$r->fetch_assoc()['c'];

// ── Top 5 active batches ──────────────────────────────────────
$topBatches = [];
$r = $conn->query("SELECT b.name, c.title as course_title, b.max_students, (SELECT COUNT(*) FROM batch_students WHERE batch_id=b.id) as enrolled FROM batches b JOIN courses c ON b.course_id=c.id WHERE b.status='active' ORDER BY enrolled DESC LIMIT 5");
while ($row = $r->fetch_assoc()) $topBatches[] = $row;

$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $currentUser['full_name'])[0];

$regJson    = json_encode($registrations);
$actJson    = json_encode($activityTrend);
$roleJson   = json_encode($roleData);
$assignJson = json_encode($assignmentData);
$pieJson    = json_encode([$pendingSubs, $gradedSubs, $returnedSubs]);

include __DIR__ . '/../includes/header.php';
?>

<style>
  /* ── Dashboard-specific styles ─────────────────────────────── */
  @keyframes countUp {
    from {
      opacity: 0;
      transform: translateY(8px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes pulseDot {

    0%,
    100% {
      transform: scale(1);
      opacity: 1
    }

    50% {
      transform: scale(0.7);
      opacity: 0.6
    }
  }

  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(16px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  .dash-stat {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    padding: 20px 22px;
    box-shadow: var(--shadow-card);
    display: flex;
    align-items: flex-start;
    gap: 16px;
    transition: box-shadow 0.2s, transform 0.2s;
    animation: fadeInUp 0.4s ease both;
    text-decoration: none;
    color: inherit;
  }

  .dash-stat:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
  }

  .dash-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .dash-stat-num {
    font-family: 'Poppins', sans-serif;
    font-size: 1.7rem;
    font-weight: 900;
    line-height: 1.1;
  }

  .dash-stat-label {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 2px;
  }

  .dash-stat-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
  }

  .dash-stat-sub .up {
    color: var(--success);
  }

  .dash-stat-sub .warn {
    color: var(--warning);
  }

  .dash-grid-6 {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
  }

  .dash-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  .dash-grid-3 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
  }

  .quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s, border-color 0.15s, transform 0.15s;
    font-weight: 600;
    font-size: 0.85rem;
  }

  .quick-action:hover {
    background: var(--bg-hover);
    border-color: var(--primary);
    transform: translateX(3px);
  }

  .quick-action-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .alert-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    border-radius: var(--radius);
    font-size: 0.875rem;
  }

  .recent-user-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
  }

  .recent-user-row:last-child {
    border-bottom: none;
  }

  .log-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
  }

  .log-row:last-child {
    border-bottom: none;
  }

  .progress-thin {
    height: 5px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-top: 4px;
  }

  .progress-thin-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 1.2s ease 0.3s;
  }

  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .section-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 0.92rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  @media(max-width:1200px) {
    .dash-grid-6 {
      grid-template-columns: repeat(3, 1fr);
    }
  }

  @media(max-width:900px) {
    .dash-grid-6 {
      grid-template-columns: repeat(2, 1fr);
    }

    .dash-grid-3,
    .dash-grid-2 {
      grid-template-columns: 1fr;
    }
  }

  @media(max-width:600px) {
    .dash-grid-6 {
      grid-template-columns: 1fr 1fr;
    }
  }
</style>

<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- ── HEADER ──────────────────────────────────────────── -->
      <div class="page-header" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:16px;">
          <?= userAvatar($currentUser, 52) ?>
          <div>
            <h1 class="page-title" style="font-size:1.45rem;margin:0;">
              <?= e($greeting) ?>, <?= e($firstName) ?>
            </h1>
            <p class="page-subtitle" style="margin:4px 0 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <i data-lucide="calendar" style="width:13px;height:13px;color:var(--text-muted);"></i>
              <span><?= date('l, F j, Y') ?></span>
              <?php if ($onlineNow > 0): ?>
                <span style="color:var(--border);">·</span>
                <span style="color:var(--success);font-weight:600;display:flex;align-items:center;gap:5px;">
                  <span style="width:7px;height:7px;background:var(--success);border-radius:50%;display:inline-block;animation:pulseDot 1.5s ease infinite;"></span>
                  <?= $onlineNow ?> online now
                </span>
              <?php endif; ?>
              <?php if ($liveSessions > 0): ?>
                <span style="color:var(--border);">·</span>
                <a href="<?= BASE_PATH ?>/admin/live.php" style="color:var(--danger);font-weight:700;display:flex;align-items:center;gap:4px;text-decoration:none;">
                  <span style="width:7px;height:7px;background:var(--danger);border-radius:50%;animation:pulseDot 1.2s ease infinite;display:inline-block;"></span>
                  <?= $liveSessions ?> live <?= $liveSessions == 1 ? 'session' : 'sessions' ?>
                </a>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn btn-ghost btn-sm" onclick="location.reload()">
            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Refresh
          </button>
          <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-secondary btn-sm">
            <i data-lucide="user-plus" style="width:14px;height:14px;"></i> Add User
          </a>
          <a href="<?= BASE_PATH ?>/admin/live.php" class="btn btn-danger btn-sm">
            <i data-lucide="video" style="width:14px;height:14px;"></i> Go Live
          </a>
          <a href="<?= BASE_PATH ?>/admin/courses.php" class="btn btn-primary btn-sm">
            <i data-lucide="plus" style="width:14px;height:14px;"></i> New Course
          </a>
        </div>
      </div>

      <!-- ── ALERTS ───────────────────────────────────────────── -->
      <?php if ($expiredBatches > 0 || $expiringBatches > 0): ?>
        <div style="margin-bottom:20px;display:flex;flex-direction:column;gap:8px;">
          <?php if ($expiredBatches > 0): ?>
            <div class="alert-banner" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);">
              <i data-lucide="alert-circle" style="width:16px;height:16px;color:var(--danger);flex-shrink:0;"></i>
              <span style="flex:1;"><strong><?= $expiredBatches ?> batch<?= $expiredBatches > 1 ? 'es' : '' ?></strong> ha<?= $expiredBatches > 1 ? 've' : 's' ?> passed their end date.</span>
              <a href="<?= BASE_PATH ?>/admin/batches.php" class="btn btn-sm btn-ghost" style="color:var(--danger);flex-shrink:0;">Review <i data-lucide="arrow-right" style="width:12px;height:12px;"></i></a>
            </div>
          <?php endif; ?>
          <?php if ($expiringBatches > 0): ?>
            <div class="alert-banner" style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);">
              <i data-lucide="clock" style="width:16px;height:16px;color:var(--warning);flex-shrink:0;"></i>
              <span style="flex:1;"><strong><?= $expiringBatches ?> batch<?= $expiringBatches > 1 ? 'es' : '' ?></strong> will end within 7 days.</span>
              <a href="<?= BASE_PATH ?>/admin/batches.php" class="btn btn-sm btn-ghost" style="color:var(--warning);flex-shrink:0;">View <i data-lucide="arrow-right" style="width:12px;height:12px;"></i></a>
            </div>
          <?php endif; ?>
          <?php if ($pendingSubs > 0): ?>
            <div class="alert-banner" style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.18);">
              <i data-lucide="inbox" style="width:16px;height:16px;color:var(--warning);flex-shrink:0;"></i>
              <span style="flex:1;"><strong><?= $pendingSubs ?> submission<?= $pendingSubs > 1 ? 's' : '' ?></strong> waiting for review.</span>
              <a href="<?= BASE_PATH ?>/admin/submissions.php" class="btn btn-sm btn-ghost" style="color:var(--warning);flex-shrink:0;">Grade now <i data-lucide="arrow-right" style="width:12px;height:12px;"></i></a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- ── STATS GRID ────────────────────────────────────────── -->
      <div class="dash-grid-2" style="margin-bottom:24px;">

        <?php
        $stats = [
          ['users',          $totalUsers,       'Active Users',      "#6366f1", "rgba(99,102,241,0.1)",   "{$totalStudents}S · {$totalTeachers}T" . ($newToday > 0 ? " · <span class='up'>+{$newToday} today</span>" : ''),  BASE_PATH . '/admin/users.php'],
          ['book-open',      $totalCourses,     'Courses',           "#06b6d4", "rgba(6,182,212,0.1)",    "{$totalBatches} active batch" . ($totalBatches != 1 ? 'es' : ''),                                                    BASE_PATH . '/admin/courses.php'],
          ['graduation-cap', $enrolledStudents, 'Enrolled',          "#10b981", "rgba(16,185,129,0.1)",   "{$enrolRate}% of all students",                                                                               BASE_PATH . '/admin/batches.php'],
          ['clipboard-list', $totalAssignments, 'Assignments',       "#8b5cf6", "rgba(139,92,246,0.1)",   "{$totalTopics} topics published",                                                                             BASE_PATH . '/admin/assignments.php'],
          ['send',           $pendingSubs,       'Pending Review',    $pendingSubs > 0 ? "#f59e0b" : "#10b981", $pendingSubs > 0 ? "rgba(245,158,11,0.1)" : "rgba(16,185,129,0.1)", $pendingSubs > 0 ? "<span class='warn'>Needs attention</span>" : "<span class='up'>All graded</span>", BASE_PATH . '/admin/submissions.php'],
          ['activity',       $todayActivity,    "Today's Actions",   "#ef4444", "rgba(239,68,68,0.1)",    "{$onlineNow} user" . ($onlineNow != 1 ? 's' : '') . " online now",                                                    BASE_PATH . '/admin/activity-logs.php'],
        ];
        foreach ($stats as $i => [$icon, $num, $label, $color, $bg, $sub, $href]):
        ?>
          <a href="<?= $href ?>" class="dash-stat" style="animation-delay:<?= $i * 0.06 ?>s;">
            <div class="dash-stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>;">
              <i data-lucide="<?= $icon ?>" style="width:22px;height:22px;"></i>
            </div>
            <div style="min-width:0;">
              <div class="dash-stat-num" style="color:<?= $color ?>;" data-count="<?= $num ?>">0</div>
              <div class="dash-stat-label"><?= $label ?></div>
              <div class="dash-stat-sub"><?= $sub ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ── CHARTS ROW ────────────────────────────────────────── -->
      <div class="dash-grid-2" style="margin-bottom:24px;">

        <!-- Registrations Line Chart -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="section-title">
              <i data-lucide="user-plus" style="width:16px;height:16px;color:var(--primary);"></i>
              Registrations — Last 30 Days
            </h3>
            <span class="badge badge-success" style="font-size:0.7rem;">
              <span class="badge-dot" style="background:var(--success);"></span>Live
            </span>
          </div>
          <div class="card-body" style="padding:16px 20px;">
            <canvas id="regChart" style="width:100%;height:200px;"></canvas>
          </div>
        </div>

        <!-- Activity Line Chart -->
        <div class="card">
          <div class="card-header" style="padding-bottom:0;">
            <h3 class="section-title">
              <i data-lucide="activity" style="width:16px;height:16px;color:var(--danger);"></i>
              Activity — Last 14 Days
            </h3>
          </div>
          <div class="card-body" style="padding:16px 20px;">
            <canvas id="actChart" style="width:100%;height:200px;"></canvas>
          </div>
        </div>
      </div>

      <!-- ── CONTENT ROW ────────────────────────────────────────── -->
      <div class="dash-grid-3" style="margin-bottom:24px;">

        <!-- Left: Recent Users + Activity -->
        <div style="display:flex;flex-direction:column;gap:20px;">

          <!-- Recent Users -->
          <div class="card">
            <div class="card-header">
              <h3 class="section-title">
                <i data-lucide="users" style="width:16px;height:16px;color:var(--primary);"></i>
                Recent Registrations
              </h3>
              <a href="<?= BASE_PATH ?>/admin/users.php" style="font-size:0.78rem;color:var(--primary);font-weight:600;">View all</a>
            </div>
            <div class="card-body" style="padding:0 20px 16px;">
              <?php if (empty($recentUsers)): ?>
                <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:0.85rem;">No users yet</div>
                <?php else: foreach ($recentUsers as $u):
                  $roles = !empty($u['all_roles']) ? array_filter(explode(',', $u['all_roles'])) : [];
                  $parts = explode(' ', $u['full_name']);
                  $ini = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                  $colors = ['#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
                  $bg = $colors[abs(crc32($u['full_name'])) % count($colors)];
                ?>
                  <div class="recent-user-row">
                    <div style="width:36px;height:36px;background:<?= $bg ?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.78rem;font-family:Poppins,sans-serif;flex-shrink:0;"><?= $ini ?></div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-weight:600;font-size:0.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($u['full_name']) ?></div>
                      <div style="font-size:0.72rem;color:var(--text-muted);"><?= e($u['email']) ?></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                      <?php foreach (array_slice($roles, 0, 2) as $role): ?>
                        <span class="badge <?= $role === 'admin' ? 'badge-danger' : ($role === 'teacher' ? 'badge-info' : 'badge-success') ?>" style="font-size:0.65rem;"><?= $role ?></span>
                      <?php endforeach; ?>
                      <span style="font-size:0.7rem;color:var(--text-muted);"><?= timeAgo($u['created_at']) ?></span>
                    </div>
                  </div>
              <?php endforeach;
              endif; ?>
            </div>
          </div>

          <!-- Recent Activity Log -->
          <div class="card">
            <div class="card-header">
              <h3 class="section-title">
                <i data-lucide="activity" style="width:16px;height:16px;color:var(--danger);"></i>
                Recent Activity
              </h3>
              <a href="<?= BASE_PATH ?>/admin/activity-logs.php" style="font-size:0.78rem;color:var(--primary);font-weight:600;">View all</a>
            </div>
            <div class="card-body" style="padding:0 20px 16px;">
              <?php foreach ($recentLogs as $log):
                $sectionColors = ['users' => '#4472C4', 'topics' => '#70AD47', 'assignments' => '#ED7D31', 'submissions' => '#FFC000', 'batches' => '#5B9BD5', 'courses' => '#A5A5A5', 'announcements' => '#FF0000', 'live' => '#EF4444', 'auth' => '#8b5cf6'];
                $sc = $sectionColors[$log['section'] ?? ''] ?? '#94a3b8';
              ?>
                <div class="log-row">
                  <div style="width:32px;height:32px;background:<?= $sc ?>22;color:<?= $sc ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.62rem;font-weight:800;text-transform:uppercase;"><?= substr($log['section'] ?? '?', 0, 2) ?></div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.8rem;"><?= e($log['full_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:240px;"><?= e(mb_strimwidth($log['action'], 0, 60, '…')) ?></div>
                  </div>
                  <div style="font-size:0.7rem;color:var(--text-muted);flex-shrink:0;"><?= timeAgo($log['created_at']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Right: Charts + Quick Actions -->
        <div style="display:flex;flex-direction:column;gap:20px;">

          <!-- Doughnut Charts -->
          <div class="card">
            <div class="card-header" style="padding-bottom:0;">
              <h3 class="section-title">
                <i data-lucide="pie-chart" style="width:16px;height:16px;color:var(--primary);"></i>
                Distribution
              </h3>
            </div>
            <div class="card-body" style="padding:16px 20px;">
              <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
                <div style="text-align:center;">
                  <canvas id="rolesChart" width="110" height="110"></canvas>
                  <div style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;font-weight:600;">Users by Role</div>
                  <div style="display:flex;justify-content:center;gap:8px;margin-top:6px;flex-wrap:wrap;">
                    <?php $roleColors = ['#ef4444', '#06b6d4', '#10b981'];
                    $ri = 0;
                    foreach ($roleData as $rd): ?>
                      <div style="display:flex;align-items:center;gap:4px;font-size:0.68rem;color:var(--text-muted);">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $roleColors[$ri++ % 3] ?>;display:inline-block;"></span>
                        <?= e($rd['label']) ?> (<?= $rd['cnt'] ?>)
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div style="text-align:center;">
                  <canvas id="subsChart" width="110" height="110"></canvas>
                  <div style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;font-weight:600;">Submissions</div>
                  <div style="display:flex;justify-content:center;gap:8px;margin-top:6px;flex-wrap:wrap;">
                    <?php $subColors = ['#f59e0b', '#10b981', '#8b5cf6'];
                    $subLabels = ['Pending', 'Graded', 'Returned'];
                    $subVals = [$pendingSubs, $gradedSubs, $returnedSubs];
                    foreach ($subLabels as $si => $sl): ?>
                      <div style="display:flex;align-items:center;gap:4px;font-size:0.68rem;color:var(--text-muted);">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $subColors[$si] ?>;display:inline-block;"></span>
                        <?= $sl ?> (<?= $subVals[$si] ?>)
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Batches -->
          <div class="card">
            <div class="card-header">
              <h3 class="section-title">
                <i data-lucide="layers" style="width:16px;height:16px;color:var(--primary);"></i>
                Top Batches
              </h3>
              <a href="<?= BASE_PATH ?>/admin/batches.php" style="font-size:0.78rem;color:var(--primary);font-weight:600;">View all</a>
            </div>
            <div class="card-body" style="padding:0 20px 16px;">
              <?php foreach ($topBatches as $tb):
                $pct = $tb['max_students'] > 0 ? min(100, round($tb['enrolled'] / $tb['max_students'] * 100)) : 0;
                $pc = $pct > 80 ? '#ef4444' : ($pct > 60 ? '#f59e0b' : '#10b981');
              ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-weight:600;font-size:0.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;"><?= e($tb['name']) ?></span>
                    <span style="font-size:0.72rem;color:var(--text-muted);flex-shrink:0;"><?= $tb['enrolled'] ?>/<?= $tb['max_students'] ?></span>
                  </div>
                  <div class="progress-thin">
                    <div class="progress-thin-fill" style="width:<?= $pct ?>%;background:<?= $pc ?>;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="card">
            <div class="card-header">
              <h3 class="section-title"><i data-lucide="zap" style="width:16px;height:16px;color:var(--warning);"></i> Quick Actions</h3>
            </div>
            <div class="card-body" style="padding:0 16px 16px;display:flex;flex-direction:column;gap:8px;">
              <?php
              $actions = [
                [BASE_PATH . '/admin/users.php',     'user-plus',       'rgba(99,102,241,0.12)',  '#6366f1', 'Add New User'],
                [BASE_PATH . '/admin/courses.php',   'book-open',       'rgba(6,182,212,0.12)',   '#06b6d4', 'New Course'],
                [BASE_PATH . '/admin/batches.php',   'layers',          'rgba(16,185,129,0.12)',  '#10b981', 'New Batch'],
                [BASE_PATH . '/admin/live.php',      'video',           'rgba(239,68,68,0.12)',   '#ef4444', 'Start Live Session'],
                [BASE_PATH . '/admin/submissions.php', 'send',           'rgba(245,158,11,0.12)',  '#f59e0b', 'Review Submissions' . ($pendingSubs > 0 ? " ({$pendingSubs})" : '')],
                [BASE_PATH . '/export-logs.php',     'file-spreadsheet', 'rgba(139,92,246,0.12)', '#8b5cf6', 'Export Activity Logs'],
              ];
              foreach ($actions as [$href, $icon, $bg, $color, $label]):
              ?>
                <a href="<?= $href ?>" class="quick-action">
                  <div class="quick-action-icon" style="background:<?= $bg ?>;color:<?= $color ?>;"><i data-lucide="<?= $icon ?>" style="width:16px;height:16px;"></i></div>
                  <?= e($label) ?>
                  <i data-lucide="chevron-right" style="width:14px;height:14px;color:var(--text-muted);margin-left:auto;"></i>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ── ASSIGNMENTS BAR CHART ─────────────────────────────── -->
      <?php if (!empty($assignmentData)): ?>
        <div class="card" style="margin-bottom:20px;">
          <div class="card-header">
            <h3 class="section-title">
              <i data-lucide="bar-chart-2" style="width:16px;height:16px;color:var(--primary);"></i>
              Top Assignments by Submissions
            </h3>
          </div>
          <div class="card-body" style="padding:16px 20px;">
            <canvas id="assignChart" style="width:100%;height:180px;"></canvas>
          </div>
        </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // ── Count-up animation ─────────────────────────────────
    document.querySelectorAll('[data-count]').forEach(el => {
      const target = parseInt(el.dataset.count) || 0;
      if (target === 0) {
        el.textContent = '0';
        return;
      }
      let start = 0,
        duration = 900,
        startTime = null;
      const step = ts => {
        if (!startTime) startTime = ts;
        const progress = Math.min((ts - startTime) / duration, 1);
        el.textContent = Math.floor(progress * target).toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString();
      };
      requestAnimationFrame(step);
    });

    // ── Chart colors ────────────────────────────────────────
    const isDark = document.documentElement.classList.contains('dark');
    const cardBg = isDark ? '#0f0f23' : '#ffffff';
    const textColor = isDark ? '#475569' : '#94a3b8';
    const gridColor = isDark ? '#1e293b' : '#f1f5f9';
    const primary = '#6366f1';

    // ── Registrations chart ─────────────────────────────────
    const regData = <?= $regJson ?>;
    new Chart(document.getElementById('regChart'), {
      type: 'line',
      data: {
        labels: regData.map(d => d.date),
        datasets: [{
          data: regData.map(d => d.count),
          borderColor: primary,
          backgroundColor: ctx => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
            g.addColorStop(0, 'rgba(99,102,241,0.18)');
            g.addColorStop(1, 'rgba(99,102,241,0)');
            return g;
          },
          fill: true,
          tension: 0.4,
          borderWidth: 2.5,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: primary,
          pointHoverBorderColor: cardBg,
          pointHoverBorderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: ctx => ` ${ctx.parsed.y} registration${ctx.parsed.y!==1?'s':''}`
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            border: {
              display: false
            },
            ticks: {
              color: textColor,
              font: {
                size: 10
              },
              maxTicksLimit: 8
            }
          },
          y: {
            grid: {
              color: gridColor
            },
            border: {
              display: false
            },
            beginAtZero: true,
            ticks: {
              color: textColor,
              font: {
                size: 10
              },
              precision: 0
            }
          }
        }
      }
    });

    // ── Activity chart ──────────────────────────────────────
    const actData = <?= $actJson ?>;
    new Chart(document.getElementById('actChart'), {
      type: 'line',
      data: {
        labels: actData.map(d => d.date),
        datasets: [{
          data: actData.map(d => d.count),
          borderColor: '#ef4444',
          backgroundColor: ctx => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
            g.addColorStop(0, 'rgba(239,68,68,0.18)');
            g.addColorStop(1, 'rgba(239,68,68,0)');
            return g;
          },
          fill: true,
          tension: 0.4,
          borderWidth: 2.5,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: '#ef4444',
          pointHoverBorderColor: cardBg,
          pointHoverBorderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: ctx => ` ${ctx.parsed.y} action${ctx.parsed.y!==1?'s':''}`
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            border: {
              display: false
            },
            ticks: {
              color: textColor,
              font: {
                size: 10
              },
              maxTicksLimit: 8
            }
          },
          y: {
            grid: {
              color: gridColor
            },
            border: {
              display: false
            },
            beginAtZero: true,
            ticks: {
              color: textColor,
              font: {
                size: 10
              },
              precision: 0
            }
          }
        }
      }
    });

    // ── Roles doughnut ──────────────────────────────────────
    const roleData = <?= $roleJson ?>;
    new Chart(document.getElementById('rolesChart'), {
      type: 'doughnut',
      data: {
        labels: roleData.map(d => d.label),
        datasets: [{
          data: roleData.map(d => parseInt(d.cnt)),
          backgroundColor: ['#ef4444', '#06b6d4', '#10b981'],
          borderColor: cardBg,
          borderWidth: 3,
          hoverOffset: 6,
        }]
      },
      options: {
        cutout: '72%',
        responsive: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed}`
            }
          }
        }
      }
    });

    // ── Submissions doughnut ────────────────────────────────
    const subsData = <?= $pieJson ?>;
    const subsTotal = subsData.reduce((a, b) => a + b, 0);
    new Chart(document.getElementById('subsChart'), {
      type: 'doughnut',
      data: {
        labels: ['Pending', 'Graded', 'Returned'],
        datasets: [{
          data: subsTotal > 0 ? subsData : [1],
          backgroundColor: subsTotal > 0 ? ['#f59e0b', '#10b981', '#8b5cf6'] : [isDark ? '#1e293b' : '#e2e8f0'],
          borderColor: cardBg,
          borderWidth: 3,
          hoverOffset: 6,
        }]
      },
      options: {
        cutout: '72%',
        responsive: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            enabled: subsTotal > 0,
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed}`
            }
          }
        }
      }
    });

    // ── Assignments bar ─────────────────────────────────────
    const assignData = <?= $assignJson ?>;
    if (assignData.length && document.getElementById('assignChart')) {
      const palette = ['rgba(99,102,241,0.85)', 'rgba(139,92,246,0.85)', 'rgba(6,182,212,0.85)', 'rgba(16,185,129,0.85)', 'rgba(245,158,11,0.85)', 'rgba(239,68,68,0.85)'];
      new Chart(document.getElementById('assignChart'), {
        type: 'bar',
        data: {
          labels: assignData.map(d => d.title.length > 20 ? d.title.slice(0, 20) + '…' : d.title),
          datasets: [{
            label: 'Submissions',
            data: assignData.map(d => parseInt(d.cnt)),
            backgroundColor: palette,
            borderRadius: 8,
            borderSkipped: false,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              border: {
                display: false
              },
              ticks: {
                color: textColor,
                font: {
                  size: 11
                },
                maxRotation: 25
              }
            },
            y: {
              grid: {
                color: gridColor
              },
              border: {
                display: false
              },
              beginAtZero: true,
              ticks: {
                color: textColor,
                font: {
                  size: 11
                },
                precision: 0
              }
            }
          }
        }
      });
    }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>