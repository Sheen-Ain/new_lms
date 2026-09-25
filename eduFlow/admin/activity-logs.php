<?php
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Activity Logs';
$breadcrumbs = [['label' => 'Admin', 'url' => BASE_PATH . '/admin/'], ['label' => 'Activity Logs']];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$section = trim($_GET['section'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$where = [];
$params = [];
$types = '';
if ($search) {
  $s = "%$search%";
  $where[] = "(u.full_name LIKE ? OR al.action LIKE ?)";
  $params[] = $s;
  $params[] = $s;
  $types .= 'ss';
}
if ($section) {
  $where[] = "al.section=?";
  $params[] = $section;
  $types .= 's';
}
if ($dateFrom) {
  $where[] = "DATE(al.created_at)>=?";
  $params[] = $dateFrom;
  $types .= 's';
}
if ($dateTo) {
  $where[] = "DATE(al.created_at)<=?";
  $params[] = $dateTo;
  $types .= 's';
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$cs = $conn->prepare("SELECT COUNT(*) as cnt FROM activity_logs al JOIN users u ON al.user_id=u.id $wSQL");
if ($types) $cs->bind_param($types, ...$params);
$cs->execute();
$total = (int)$cs->get_result()->fetch_assoc()['cnt'];
$cs->close();
$sql = "SELECT al.*,u.full_name,u.profile_picture FROM activity_logs al JOIN users u ON al.user_id=u.id $wSQL ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$allT = $types . 'ii';
$allP = array_merge($params, [$perPage, $offset]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$sections = $conn->query("SELECT DISTINCT section FROM activity_logs WHERE section IS NOT NULL ORDER BY section")->fetch_all(MYSQLI_ASSOC);

// Section badge colours (matching XLSX export)
$sectionColors = ['users' => '4472C4', 'topics' => '70AD47', 'assignments' => 'ED7D31', 'submissions' => 'FFC000', 'batches' => '5B9BD5', 'courses' => 'A5A5A5', 'announcements' => 'FF0000', 'activity_logs' => '7030A0'];
function sectionBadge($sec, $colors)
{
  if (!$sec) return '—';
  $hex = $colors[$sec] ?? '94a3b8';
  return '<span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;background:#' . $hex . '22;color:#' . $hex . ';border:1px solid #' . $hex . '44;">' . htmlspecialchars($sec) . '</span>';
}

$exportQS = http_build_query(array_filter(['search' => $search, 'section' => $section, 'date_from' => $dateFrom, 'date_to' => $dateTo]));
include __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <main class="page-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Activity Logs</h1>
          <p class="page-subtitle">Track all system actions and user activity</p>
        </div>
        <div style="display:flex;gap:10px;">
          <a href="../export-logs.php<?= $exportQS ? '?' . $exportQS : '' ?>" class="btn btn-secondary">
            <i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Export XLSX
          </a>
          <button class="btn btn-danger btn-sm" onclick="clearLogs()" id="clear-btn">
            <i data-lucide="trash-2" style="width:15px;height:15px;"></i> Clear All Logs
          </button>
        </div>
      </div>

      <!-- Filters -->
      <form method="GET" class="card" style="padding:16px;margin-bottom:20px;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div style="flex:1;min-width:200px;"><label class="form-label">Search</label>
            <div class="search-box"><i data-lucide="search" class="search-box-icon"></i><input type="text" name="search" class="form-control" placeholder="Search by user or action…" value="<?= e($search) ?>"></div>
          </div>
          <div><label class="form-label">Section</label>
            <select name="section" class="form-control" style="width:150px;">
              <option value="">All Sections</option>
              <?php foreach ($sections as $sec): ?><option value="<?= e($sec['section']) ?>" <?= $section === $sec['section'] ? 'selected' : '' ?>><?= e($sec['section']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>"></div>
          <div><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>"></div>
          <button type="submit" class="btn btn-primary"><i data-lucide="filter" style="width:14px;height:14px;"></i> Filter</button>
          <a href="<?= BASE_PATH ?>/admin/activity-logs.php" class="btn btn-secondary"><i data-lucide="x" style="width:14px;height:14px;"></i> Clear</a>
        </div>
      </form>

      <!-- Stats strip -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <div class="card" style="flex:1;min-width:120px;padding:14px 18px;display:flex;align-items:center;gap:14px;">
          <div style="width:42px;height:42px;border-radius:50%;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;"><i data-lucide="activity" style="width:20px;height:20px;color:var(--primary);"></i></div>
          <div>
            <div style="font-size:1.35rem;font-weight:800;font-family:Poppins,sans-serif;"><?= number_format($total) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Total Entries</div>
          </div>
        </div>
        <?php
        $today = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
        $users = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM activity_logs")->fetch_assoc()['c'];
        ?>
        <div class="card" style="flex:1;min-width:120px;padding:14px 18px;display:flex;align-items:center;gap:14px;">
          <div style="width:42px;height:42px;border-radius:50%;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;"><i data-lucide="calendar" style="width:20px;height:20px;color:var(--success);"></i></div>
          <div>
            <div style="font-size:1.35rem;font-weight:800;font-family:Poppins,sans-serif;"><?= number_format($today) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Today's Actions</div>
          </div>
        </div>
        <div class="card" style="flex:1;min-width:120px;padding:14px 18px;display:flex;align-items:center;gap:14px;">
          <div style="width:42px;height:42px;border-radius:50%;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;"><i data-lucide="users" style="width:20px;height:20px;color:var(--warning);"></i></div>
          <div>
            <div style="font-size:1.35rem;font-weight:800;font-family:Poppins,sans-serif;"><?= number_format($users) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Active Users</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Action</th>
                <th>Section</th>
                <th>IP Address</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="5">
                    <div class="empty-state">
                      <div class="empty-state-icon"><i data-lucide="clipboard-list" style="width:40px;height:40px;opacity:0.3;"></i></div>
                      <div class="empty-state-title">No logs found</div>
                      <div class="empty-state-text">Try adjusting your filters</div>
                    </div>
                  </td>
                </tr>
                <?php else: foreach ($logs as $log): ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;"><?= userAvatar($log, 32) ?><span style="font-weight:600;font-size:0.875rem;"><?= e($log['full_name']) ?></span></div>
                    </td>
                    <td style="max-width:320px;font-size:0.85rem;"><?= e(mb_strimwidth($log['action'], 0, 120, '…')) ?></td>
                    <td><?= sectionBadge($log['section'], $sectionColors) ?></td>
                    <td><code style="font-size:0.75rem;color:var(--text-muted);background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($log['ip_address'] ?? '—') ?></code></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap;" title="<?= e($log['created_at']) ?>"><?= timeAgo($log['created_at']) ?></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
        <?= paginate($total, $page, $perPage, '?page={page}' . ($search ? '&search=' . urlencode($search) : '') . ($section ? '&section=' . urlencode($section) : '') . ($dateFrom ? '&date_from=' . $dateFrom : '') . ($dateTo ? '&date_to=' . $dateTo : '')) ?>
      </div>
    </main>
  </div>
</div>

<script>
  async function clearLogs() {
    Modal.confirm({
      title: 'Clear All Activity Logs',
      message: '<div style="padding:12px 0;"><p style="margin-bottom:10px;">This will <strong>permanently delete all <?= number_format($total) ?> log entries</strong> from the database.</p><p style="color:var(--danger);font-size:0.875rem;">⚠️ This action cannot be undone.</p></div>',
      confirmText: 'Yes, Clear All',
      confirmClass: 'btn-danger',
      icon: '<i data-lucide="trash-2" style="width:20px;height:20px;color:var(--danger);"></i>',
      iconClass: 'modal-icon-danger',
      onConfirm: async () => {
        const btn = document.getElementById('clear-btn');
        btn.disabled = true;
        const res = await ajax(window.LMS_BASE + '/ajax/activity.ajax.php', {
          action: 'clear_all'
        });
        if (res.status === 'success') {
          Toast.success('All activity logs cleared');
          setTimeout(() => window.location.reload(), 900);
        } else throw new Error(res.message);
      }
    });
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>