<?php
/**
 * Flash partial — inline server messages (toasts are raised by JS).
 * Renders server validation summaries when a form submission failed.
 */
$messages = $flashMessages ?? [];
$summaryErrors = $errors ?? [];
$stacked = $stacked ?? false;
?>
<?php if ($messages || $stacked): ?>
  <div class="mb-16">
    <?php foreach ($messages as $item):
        $type = in_array($item['type'], ['success', 'error', 'warning', 'info'], true) ? $item['type'] : 'info';
        $icons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info']; ?>
      <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
        <?= icon($icons[$type], 18) ?>
        <div><?= e($item['message']) ?></div>
      </div>
    <?php endforeach; ?>

    <?php if ($stacked && $summaryErrors): ?>
      <div class="alert alert-danger">
        <?= icon('alert', 18) ?>
        <div>
          <div class="alert-title">Please correct the following</div>
          <ul style="margin:6px 0 0 18px;padding:0">
            <?php foreach ($summaryErrors as $field => $list): ?>
              <?php foreach ((array) $list as $message): ?>
                <li class="small"><?= e($message) ?></li>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
