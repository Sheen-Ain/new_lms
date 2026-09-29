<?php
$routes = $routes ?? [];
$schema = $schema ?? [];
$logs = $logs ?? [];
?>
<section class="page-head">
  <div><h1>System health</h1><p class="sub">Runtime, registered routes, database tables, and recent errors.</p></div>
</section>

<section class="metric-row" aria-label="System summary">
  <div class="metric is-brand"><span class="mark"><?= icon('code', 17) ?></span><div><div class="value"><?= e($phpVersion ?? PHP_VERSION) ?></div><div class="label">PHP version</div></div></div>
  <div class="metric is-info"><span class="mark"><?= icon('link', 17) ?></span><div><div class="value"><?= count($routes) ?></div><div class="label">Registered routes</div></div></div>
  <div class="metric is-success"><span class="mark"><?= icon('database', 17) ?></span><div><div class="value"><?= count($schema) ?></div><div class="label">Database tables</div></div></div>
  <div class="metric is-warning"><span class="mark"><?= icon('alert', 17) ?></span><div><div class="value"><?= count($logs) ?></div><div class="label">Recent errors</div></div></div>
</section>

<section class="panel mb-20">
  <div class="panel-head"><h2>Database tables</h2></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Table</th><th>Rows</th></tr></thead><tbody>
    <?php foreach ($schema as $table => $count): ?><tr><td><?= e($table) ?></td><td><?= number_format((int) $count) ?></td></tr><?php endforeach; ?>
    <?php if (!$schema): ?><tr><td colspan="2" class="muted">Schema details are unavailable.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="panel mb-20">
  <div class="panel-head"><h2>Registered routes</h2></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Route</th><th>Handler</th><th>Middleware</th></tr></thead><tbody>
    <?php foreach ($routes as $route): ?><tr><td class="mono"><?= e($route['route']) ?></td><td class="mono"><?= e($route['handler']) ?></td><td><?= e(implode(', ', $route['middleware'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$routes): ?><tr><td colspan="3" class="muted">No route data available.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Recent errors</h2></div>
  <?php if (!$logs): ?><div class="panel-note">No recent errors were reported.</div><?php else: ?>
    <div class="mini-list"><?php foreach ($logs as $log):
        $message = is_array($log) ? ($log['message'] ?? $log['text'] ?? json_encode($log)) : (string) $log;
        $time = is_array($log) ? ($log['time'] ?? '') : ''; ?>
      <div class="mini-item"><div class="main"><div class="title"><?= e($message) ?></div><?php if ($time !== ''): ?><div class="meta"><?= e($time) ?></div><?php endif; ?></div></div>
    <?php endforeach; ?></div>
  <?php endif; ?>
</section>