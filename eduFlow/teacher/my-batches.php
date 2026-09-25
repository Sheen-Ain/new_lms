<?php
$requiredRole = 'teacher';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'My Batches';
$breadcrumbs = [['label' => 'Teacher'], ['label' => 'My Batches']];
$uid = (int)$currentUser['id'];

$r = $conn->prepare("
    SELECT
        b.*,
        c.title  AS course_title,
        (SELECT COUNT(*)    FROM batch_students  WHERE batch_id = b.id)                         AS student_count,
        (SELECT COUNT(*)    FROM topics          WHERE batch_id = b.id AND status = 'active')   AS topic_count,
        (SELECT COUNT(*)    FROM assignments     WHERE batch_id = b.id AND status = 'active')   AS assignment_count,
        (SELECT COUNT(*)    FROM submissions s
             JOIN assignments a ON s.assignment_id = a.id
             WHERE a.batch_id = b.id AND s.status = 'submitted')                                AS pending_reviews
    FROM batch_teachers bt
    JOIN batches b  ON bt.batch_id  = b.id
    JOIN courses  c ON b.course_id  = c.id
    WHERE bt.teacher_id = ?
    ORDER BY b.status = 'active' DESC, c.title, b.name
");
$r->bind_param('i', $uid);
$r->execute();
$batches = $r->get_result()->fetch_all(MYSQLI_ASSOC);
$r->close();

// Summary counts for stat cards
$totalStudents  = array_sum(array_column($batches, 'student_count'));
$totalActive    = count(array_filter($batches, function($b){ return $b['status'] === 'active'; }));
$totalPending   = array_sum(array_column($batches, 'pending_reviews'));
$totalBatches   = count($batches);

// Collect unique courses for filter
$courses = [];
foreach ($batches as $b) {
  $courses[$b['course_id']] = $b['course_title'];
}

// ── Active live sessions (keyed by batch_id) ─────────────────
$activeLiveSessions = [];
if ($batches) {
    $batchIds = array_column($batches, 'id');
    $ph = implode(',', array_fill(0, count($batchIds), '?'));
    $tp = str_repeat('i', count($batchIds));
    $rs = $conn->prepare("SELECT id, batch_id, title, started_by FROM live_sessions WHERE status='active' AND batch_id IN ($ph)");
    $rs->bind_param($tp, ...$batchIds);
    $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $live) {
        $activeLiveSessions[$live['batch_id']] = $live;
    }
    $rs->close();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">

      <!-- Page header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">My Batches</h1>
          <p class="page-subtitle">Batches you are assigned to teach</p>
        </div>
      </div>

      <?php if (empty($batches)): ?>
        <div class="empty-state card">
          <div class="empty-state-icon"><i data-lucide="layers" style="width:40px;height:40px;opacity:0.35;"></i></div>
          <div class="empty-state-title">No batches assigned</div>
          <div class="empty-state-text">Contact an admin to be assigned to a batch</div>
        </div>
      <?php else: ?>

        <!-- Stat cards -->
        <div class="stats-grid" style="margin-bottom:24px;">
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(99,102,241,0.12);color:var(--primary);">
              <i data-lucide="layers" style="width:20px;height:20px;"></i>
            </div>
            <div class="stat-card-value"><?= $totalBatches ?></div>
            <div class="stat-card-label">Total Batches</div>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(16,185,129,0.12);color:var(--success);">
              <i data-lucide="play-circle" style="width:20px;height:20px;"></i>
            </div>
            <div class="stat-card-value"><?= $totalActive ?></div>
            <div class="stat-card-label">Active Batches</div>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(6,182,212,0.12);color:var(--accent);">
              <i data-lucide="users" style="width:20px;height:20px;"></i>
            </div>
            <div class="stat-card-value"><?= $totalStudents ?></div>
            <div class="stat-card-label">Total Students</div>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(245,158,11,0.12);color:var(--warning);">
              <i data-lucide="clock" style="width:20px;height:20px;"></i>
            </div>
            <div class="stat-card-value"><?= $totalPending ?></div>
            <div class="stat-card-label">Pending Reviews</div>
          </div>
        </div>

        <!-- Search + filter bar -->
        <div class="card" style="margin-bottom:16px;padding:12px 16px;">
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <div class="search-box" style="flex:1;min-width:200px;">
              <i data-lucide="search" class="search-box-icon"></i>
              <input type="text" id="b-search" class="form-control" placeholder="Search batches…" oninput="filterBatches()" autocomplete="off">
            </div>
            <?php if (count($courses) > 1): ?>
              <select id="b-course" class="form-control" style="width:200px;" onchange="filterBatches()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $cid => $ctitle): ?>
                  <option value="<?= (int)$cid ?>"><?= e($ctitle) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <div style="display:flex;gap:4px;background:var(--bg);padding:4px;border-radius:var(--radius);border:1px solid var(--border);">
              <button class="btn btn-primary btn-sm status-btn active" data-status="">All</button>
              <button class="btn btn-ghost btn-sm status-btn" data-status="active">
                <i data-lucide="play-circle" style="width:13px;height:13px;margin-right:3px;"></i>Active
              </button>
              <button class="btn btn-ghost btn-sm status-btn" data-status="completed">
                <i data-lucide="check-circle" style="width:13px;height:13px;margin-right:3px;"></i>Completed
              </button>
              <button class="btn btn-ghost btn-sm status-btn" data-status="inactive">
                <i data-lucide="pause-circle" style="width:13px;height:13px;margin-right:3px;"></i>Inactive
              </button>
            </div>
            <button class="btn btn-ghost btn-sm" id="clear-btn" style="display:none;" onclick="clearFilters()">
              <i data-lucide="x" style="width:14px;height:14px;"></i> Clear
            </button>
          </div>
        </div>

        <!-- Batch cards grid -->
        <div id="batches-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
          <?php
          $palette = ['var(--primary)', 'var(--secondary)', 'var(--accent)', 'var(--success)', 'var(--warning)'];
          foreach ($batches as $idx => $b):
            $pct       = $b['max_students'] ? min(100, round(($b['student_count'] / $b['max_students']) * 100)) : 0;
            $barColor  = $pct > 80 ? '#ef4444' : ($pct > 60 ? '#f59e0b' : '#10b981');
            $topColor  = $palette[$idx % count($palette)];
            $statusMap = ['active' => 'badge-success', 'completed' => 'badge-info', 'inactive' => 'badge-secondary', 'pending' => 'badge-warning'];
            $statusClass = $statusMap[$b['status']] ?? 'badge-secondary';
            $statusIcon  = ['active' => 'play-circle', 'completed' => 'check-circle', 'inactive' => 'pause-circle', 'pending' => 'clock'][$b['status']] ?? 'circle';

            $startFmt = $b['start_date'] ? date('M j, Y', strtotime($b['start_date'])) : null;
            $endFmt   = $b['end_date']   ? date('M j, Y', strtotime($b['end_date']))   : null;

            // Days remaining / overdue
            $daysNote = '';
            if ($b['status'] === 'active' && $b['end_date']) {
              $diff = (strtotime($b['end_date']) - time()) / 86400;
              if ($diff < 0)       $daysNote = '<span style="color:var(--danger);font-weight:600;">Ended</span>';
              elseif ($diff < 7)   $daysNote = '<span style="color:var(--warning);font-weight:600;">' . ceil($diff) . ' days left</span>';
              else                 $daysNote = '<span style="color:var(--text-muted);">' . ceil($diff) . ' days left</span>';
            }
          ?>
            <div class="card batch-card"
              data-name="<?= strtolower(e($b['name'])) ?>"
              data-course="<?= (int)$b['course_id'] ?>"
              data-status="<?= e($b['status']) ?>"
              style="border-top:3px solid <?= $topColor ?>;transition:transform 0.15s,box-shadow 0.15s;padding:18px 20px;"
              onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-lg)'"
              onmouseout="this.style.transform='';this.style.boxShadow=''">

              <!-- Title + status badge -->
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px;">
                <h3 style="font-family:Poppins,sans-serif;font-size:0.95rem;font-weight:700;margin:0;line-height:1.3;">
                  <?= e($b['name']) ?>
                </h3>
                <span class="badge <?= $statusClass ?>" style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex-shrink:0;">
                  <i data-lucide="<?= $statusIcon ?>" style="width:11px;height:11px;"></i><?= ucfirst(e($b['status'])) ?>
                </span>
              </div>

              <!-- Course -->
              <div style="display:inline-flex;align-items:center;gap:5px;font-size:0.78rem;color:var(--primary);background:rgba(99,102,241,0.08);padding:3px 10px;border-radius:20px;border:1px solid rgba(99,102,241,0.2);margin-bottom:14px;">
                <i data-lucide="book-open" style="width:11px;height:11px;"></i><?= e($b['course_title']) ?>
              </div>

              <!-- Description -->
              <?php if ($b['description']): ?>
                <p style="font-size:0.8rem;color:var(--text-secondary);margin:0 0 14px;line-height:1.5;">
                  <?= e(truncateText($b['description'], 80)) ?>
                </p>
              <?php endif; ?>

              <!-- Stats row -->
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
                <div style="text-align:center;padding:8px 4px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
                  <div style="font-weight:700;font-size:1rem;color:var(--text-primary);"><?= $b['student_count'] ?></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:3px;margin-top:2px;">
                    <i data-lucide="users" style="width:10px;height:10px;"></i>Students
                  </div>
                </div>
                <div style="text-align:center;padding:8px 4px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
                  <div style="font-weight:700;font-size:1rem;color:var(--text-primary);"><?= $b['topic_count'] ?></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:3px;margin-top:2px;">
                    <i data-lucide="file-text" style="width:10px;height:10px;"></i>Topics
                  </div>
                </div>
                <div style="text-align:center;padding:8px 4px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
                  <div style="font-weight:700;font-size:1rem;color:<?= $b['pending_reviews'] > 0 ? 'var(--warning)' : 'var(--text-primary)' ?>;"><?= $b['pending_reviews'] ?></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:3px;margin-top:2px;">
                    <i data-lucide="clock" style="width:10px;height:10px;"></i>Reviews
                  </div>
                </div>
              </div>

              <!-- Enrollment progress -->
              <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted);margin-bottom:5px;">
                  <span style="display:flex;align-items:center;gap:4px;"><i data-lucide="user-check" style="width:11px;height:11px;"></i>Enrollment</span>
                  <span style="font-weight:600;color:var(--text-primary);"><?= $b['student_count'] ?>/<?= $b['max_students'] ?></span>
                </div>
                <div class="progress">
                  <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
              </div>

              <!-- Date range -->
              <?php if ($startFmt || $endFmt): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:14px;padding:8px 10px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border);">
                  <span style="display:flex;align-items:center;gap:4px;"><i data-lucide="calendar" style="width:12px;height:12px;"></i><?= $startFmt ?? '—' ?></span>
                  <i data-lucide="arrow-right" style="width:12px;height:12px;opacity:0.4;"></i>
                  <span><?= $endFmt ?? '—' ?><?= $daysNote ? ' · ' . $daysNote : '' ?></span>
                </div>
              <?php endif; ?>

              <!-- Action buttons -->
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php
                $liveSession = $activeLiveSessions[$b['id']] ?? null;
                $iMySession  = $liveSession && (int)$liveSession['started_by'] === $uid;
                ?>
                <?php if ($liveSession): ?>
                  <!-- Active session for this batch -->
                  <a href="<?= BASE_PATH ?>/teacher/live.php?session_id=<?= $liveSession['id'] ?>"
                     class="btn btn-sm"
                     style="flex:1;justify-content:center;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;box-shadow:0 3px 10px rgba(239,68,68,0.35);">
                    <span style="display:inline-block;width:7px;height:7px;background:#fff;border-radius:50%;margin-right:6px;animation:liveDotPulse 1.2s ease-in-out infinite;"></span>
                    <?= $iMySession ? 'Rejoin My Session' : 'View Live' ?>
                  </a>
                  <?php if ($iMySession): ?>
                    <button class="btn btn-ghost btn-sm"
                            style="flex-shrink:0;color:var(--danger);border-color:rgba(239,68,68,0.3);"
                            onclick="endLiveSession(<?= $liveSession['id'] ?>, this)">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                      End
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <!-- No active session -->
                  <button class="btn btn-ghost btn-sm"
                          style="flex-shrink:0;color:var(--danger);border:1.5px dashed rgba(239,68,68,0.4);white-space:nowrap;"
                          onclick="openStartLive(<?= $b['id'] ?>, '<?= e(addslashes($b['name'])) ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin-right:4px;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    Go Live
                  </button>
                <?php endif; ?>

                <a href="<?= BASE_PATH ?>/teacher/topics.php?batch=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                  <i data-lucide="file-text" style="width:13px;height:13px;"></i> Topics
                </a>
                <a href="<?= BASE_PATH ?>/teacher/assignments.php?batch=<?= $b['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                  <i data-lucide="clipboard-list" style="width:13px;height:13px;"></i> Assign
                </a>
                <a href="<?= BASE_PATH ?>/teacher/submissions.php?batch=<?= $b['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;<?= $b['pending_reviews'] > 0 ? 'position:relative;' : '' ?>">
                  <i data-lucide="inbox" style="width:13px;height:13px;"></i> Subs
                  <?php if ($b['pending_reviews'] > 0): ?>
                    <span style="position:absolute;top:-6px;right:-6px;background:var(--danger);color:#fff;font-size:0.65rem;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?= $b['pending_reviews'] ?></span>
                  <?php endif; ?>
                </a>
              </div>

            </div>
          <?php endforeach; ?>
        </div>

        <!-- Empty search state -->
        <div id="no-results" style="display:none;">
          <div class="empty-state card">
            <div class="empty-state-icon"><i data-lucide="search-x" style="width:40px;height:40px;opacity:0.35;"></i></div>
            <div class="empty-state-title">No batches match</div>
            <div class="empty-state-text">Try adjusting your search or filters</div>
            <button class="btn btn-secondary btn-sm" style="margin-top:12px;" onclick="clearFilters()">
              <i data-lucide="x" style="width:13px;height:13px;"></i> Clear Filters
            </button>
          </div>
        </div>

      <?php endif; ?>

    </main>
  </div>
</div>

<script>
  let bSearch = '',
    bCourse = '',
    bStatus = '';

  function filterBatches() {
    bSearch = document.getElementById('b-search').value.trim().toLowerCase();
    const cs = document.getElementById('b-course');
    bCourse = cs ? cs.value : '';
    const btn = document.getElementById('clear-btn');
    if (btn) btn.style.display = (bSearch || bCourse) ? '' : 'none';
    applyFilter();
  }

  function applyFilter() {
    const cards = document.querySelectorAll('.batch-card');
    let visible = 0;
    cards.forEach(c => {
      const nameMatch = !bSearch || c.dataset.name.includes(bSearch);
      const courseMatch = !bCourse || c.dataset.course === bCourse;
      const statusMatch = !bStatus || c.dataset.status === bStatus;
      const show = nameMatch && courseMatch && statusMatch;
      c.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    document.getElementById('no-results').style.display = visible === 0 ? '' : 'none';
    document.getElementById('batches-grid').style.display = visible === 0 ? 'none' : '';
  }

  function clearFilters() {
    bSearch = '';
    bCourse = '';
    bStatus = '';
    document.getElementById('b-search').value = '';
    const cs = document.getElementById('b-course');
    if (cs) cs.value = '';
    const btn = document.getElementById('clear-btn');
    if (btn) btn.style.display = 'none';
    document.querySelectorAll('.status-btn').forEach(b => {
      b.classList.remove('active', 'btn-primary');
      b.classList.add('btn-ghost');
    });
    document.querySelector('.status-btn[data-status=""]').classList.add('active', 'btn-primary');
    document.querySelector('.status-btn[data-status=""]').classList.remove('btn-ghost');
    applyFilter();
  }

  document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.status-btn').forEach(b => {
        b.classList.remove('active', 'btn-primary');
        b.classList.add('btn-ghost');
      });
      btn.classList.add('active', 'btn-primary');
      btn.classList.remove('btn-ghost');
      bStatus = btn.dataset.status;
      applyFilter();
    });
  });
</script>

<!-- ── START LIVE MODAL ──────────────────────────────────── -->
<div class="modal-overlay" id="start-live-overlay">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <div class="modal-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;">
        <i data-lucide="video" style="width:20px;height:20px;"></i>
      </div>
      <h3 class="modal-title">Start Live Class</h3>
      <button class="modal-close" data-modal-close="start-live">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="live-batch-id">

      <!-- Batch info chip -->
      <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15);border-radius:var(--radius);margin-bottom:20px;">
        <span style="width:8px;height:8px;background:#ef4444;border-radius:50%;flex-shrink:0;animation:liveDotPulse 1.2s ease-in-out infinite;"></span>
        <span style="font-size:0.84rem;font-weight:600;color:var(--text);">
          Going live for: <strong id="live-batch-display" style="color:#ef4444;">—</strong>
        </span>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Session Title <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
        <input type="text" id="live-title-input" class="form-control"
               placeholder="e.g., Arrays and Loops — Week 3"
               maxlength="200">
        <div style="font-size:0.74rem;color:var(--text-muted);margin-top:6px;">
          Students will see this title on the live banner and join screen.
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close="start-live">Cancel</button>
      <button class="btn btn-danger" id="go-live-btn" onclick="confirmStartLive()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin-right:6px;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
        <span class="btn-text">🔴 Go Live</span>
      </button>
    </div>
  </div>
</div>

<style>
@keyframes liveDotPulse {
  0%,100% { opacity:1; transform:scale(1); }
  50%      { opacity:0.5; transform:scale(0.7); }
}
</style>

<script>
// ── Open start-live modal ────────────────────────────────────
function openStartLive(batchId, batchName) {
  document.getElementById('live-batch-id').value    = batchId;
  document.getElementById('live-batch-display').textContent = batchName;
  document.getElementById('live-title-input').value = '';
  document.getElementById('go-live-btn').disabled   = false;
  document.getElementById('go-live-btn').querySelector('.btn-text').textContent = '🔴 Go Live';
  Modal.open('start-live');
  setTimeout(() => document.getElementById('live-title-input').focus(), 220);
}

// ── Confirm + start session ──────────────────────────────────
async function confirmStartLive() {
  const batchId = document.getElementById('live-batch-id').value;
  const title   = document.getElementById('live-title-input').value.trim() || 'Live Class';
  const btn     = document.getElementById('go-live-btn');

  btn.disabled = true;
  btn.querySelector('.btn-text').textContent = 'Starting…';

  try {
    const res = await ajax(window.LMS_BASE+'/ajax/live.ajax.php', {
      action:   'start',
      batch_id: batchId,
      title:    title,
    });

    if (res.status === 'success') {
      Modal.close('start-live');
      Toast.success('Live session started! Redirecting…');
      setTimeout(() => {
        window.location.href = window.LMS_BASE+'/teacher/live.php?session_id=' + res.data.session_id;
      }, 600);
    } else {
      Toast.error(res.message || 'Failed to start session');
      btn.disabled = false;
      btn.querySelector('.btn-text').textContent = '🔴 Go Live';
    }
  } catch(e) {
    Toast.error('Network error. Please try again.');
    btn.disabled = false;
    btn.querySelector('.btn-text').textContent = '🔴 Go Live';
  }
}

// ── End session from batch card ──────────────────────────────
async function endLiveSession(sessionId, btn) {
  if (!confirm('End this live session? All students will be disconnected.')) return;
  btn.disabled = true;
  btn.textContent = 'Ending…';

  try {
    const res = await ajax(window.LMS_BASE+'/ajax/live.ajax.php', {
      action:     'end',
      session_id: sessionId,
    });
    if (res.status === 'success') {
      Toast.success('Session ended');
      setTimeout(() => location.reload(), 800);
    } else {
      Toast.error(res.message);
      btn.disabled = false;
      btn.textContent = 'End';
    }
  } catch(e) {
    Toast.error('Failed');
    btn.disabled = false;
  }
}

// Allow Enter key in modal title input
document.getElementById('live-title-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') confirmStartLive();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>