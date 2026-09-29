<?php
$resource = $resource ?? [];
$columns = $resource['columns'] ?? [];
$actions = $resource['actions'] ?? [];
$bulk = !empty($resource['bulk']);
?>
<section class="page-head">
  <div>
    <h1><?= e($pageTitle ?? 'Module') ?></h1>
    <p class="sub">Browse and filter <?= e(strtolower($pageTitle ?? 'records')) ?>.</p>
  </div>
</section>

<script>
window.EF_ADMIN_MODULE = <?= json_encode([
    'id' => $resource['id'] ?? '',
    'type' => $resource['moduleType'] ?? '',
    'actions' => $actions,
    'bulkActions' => $resource['bulkActions'] ?? [],
    'createLabel' => $resource['createLabel'] ?? null,
    'toolbarActions' => $resource['toolbarActions'] ?? [],
    'columns' => $columns,
], JSON_UNESCAPED_SLASHES) ?>;
window.EF_ADMIN_ROW_DATA = window.EF_ADMIN_ROW_DATA || {};
window.EF_ADMIN_ROW_DATA[window.EF_ADMIN_MODULE.id] = {};

window.renderAdminModuleRow = function (row, index, number) {
  window.EF_ADMIN_ROW_DATA[window.EF_ADMIN_MODULE.id][row.id] = row;
  var columns = <?= json_encode($columns, JSON_UNESCAPED_SLASHES) ?>;
  var actions = window.EF_ADMIN_MODULE.actions || [];
  var iconMap = {
    view: ['eye', 'View details'], edit: ['edit', 'Edit'], copy: ['fileText', 'Copy test'], delete: ['trash', 'Delete'],
    status: ['refresh', row.status === 'active' ? 'Deactivate' : 'Activate'],
    teachers: ['user', 'Assign teachers'], students: ['user', 'Enroll students'],
    files: ['fileText', 'Manage files'], grade: ['check', 'Grade'], approve: ['check', 'Approve'],
    reject: ['close', 'Reject'], pin: ['flag', Number(row.is_pinned) ? 'Unpin' : 'Pin'],
    open: ['play', 'Open session'], end: ['close', 'End session'], download: ['download', 'Download'],
    review: [Number(row.is_reviewed) ? 'check' : 'check', Number(row.is_reviewed) ? 'Mark unreviewed' : 'Mark reviewed'],
    bypass: ['user', Number(row.bypass_gate) ? 'Disable bypass' : 'Grant dashboard bypass']
  };
  var actionButtons = actions.filter(function (action) {
    if (action === 'bypass') return String(row.all_roles || '').split(',').indexOf('student') !== -1;
    if (action === 'approve' || action === 'reject') return ['pending', 'test_submitted'].indexOf(row.status) !== -1;
    if (action === 'open') return row.status === 'waiting';
    if (action === 'end') return row.status === 'active' || row.status === 'waiting';
    return true;
  }).map(function (action) {
    var meta = iconMap[action] || ['fileText', action];
    return '<button type="button" class="btn-icon' + (action === 'delete' || action === 'reject' || action === 'end' ? ' is-danger' : '') +
      '" data-admin-action="' + EF.h.esc(action) + '" data-id="' + EF.h.esc(row.id) + '" data-tip="' + EF.h.esc(meta[1]) +
      '" aria-label="' + EF.h.esc(meta[1]) + '">' + EF.icon(meta[0], 15) + '</button>';
  }).join('');
  var selection = <?= $bulk ? 'true' : 'false' ?>
    ? '<td class="col-tight"><input type="checkbox" class="table-check" data-row-check value="' + EF.h.esc(row.id) + '" aria-label="Select row"></td>'
    : '';
  var cells = columns.map(function (column) {
    var value = row[column.field];
    var output;
    if (column.format === 'actions') {
      output = '<div class="row-actions">' + actionButtons + '</div>';
    } else if (column.format === 'user') {
      output = '<span class="row-center gap-10" style="justify-content:flex-start">' + EF.util.avatar(row, 34) + '<span><strong>' + EF.h.esc(value || '') + '</strong><br><span class="muted tiny">' + EF.h.esc(row.user_id_number || '') + '</span></span></span>';
    } else if (column.format === 'course') {
      var thumbnailPath = row.thumbnail ? String(row.thumbnail).split('/').map(encodeURIComponent).join('/') : '';
      var thumbnail = thumbnailPath
        ? '<img src="' + EF.h.esc(EF.config.base + '/uploads/topics/' + thumbnailPath) + '" alt="" width="44" height="34" style="object-fit:cover;border-radius:4px">'
        : '<span class="mark">' + EF.icon('fileText', 16) + '</span>';
      output = '<span class="row-center gap-10" style="justify-content:flex-start">' + thumbnail + '<strong>' + EF.h.esc(value || '') + '</strong></span>';
    } else if (column.format === 'capacity') {
      var filled = Number(value) || 0;
      var maximum = Number(row.max_students) || 50;
      var percent = Math.min(100, Math.round((filled / maximum) * 100));
      output = '<span>' + filled + ' / ' + maximum + '</span><div class="progress" style="height:4px;margin-top:5px"><div class="progress-bar" style="width:' + percent + '%"></div></div>';
    } else if (column.format === 'dateRange') {
      output = EF.h.esc(row.start_date ? EF.h.date(row.start_date) : '—') + '<br>' + EF.h.esc(row.end_date ? EF.h.date(row.end_date) : '—');
    } else if (column.format === 'status') {
      output = EF.h.status(value || 'unknown');
    } else if (column.format === 'date') {
      output = EF.h.esc(EF.h.date(value));
    } else if (column.format === 'datetime') {
      output = EF.h.esc(EF.h.dateTime(value));
    } else if (column.format === 'review') {
      output = EF.h.status(Number(value) === 1 ? 'reviewed' : 'pending');
    } else if (column.format === 'verified') {
      output = EF.h.badge(Number(value) === 1 ? 'Verified' : 'Unverified', Number(value) === 1 ? 'success' : 'muted');
    } else if (Array.isArray(value)) {
      output = EF.h.esc(value.join(', '));
    } else {
      var text = value === null || value === undefined || value === '' ? '—' : String(value);
      output = EF.h.esc(column.field === 'description' && text.length > 100 ? text.slice(0, 100) + '…' : text);
    }
    return '<td>' + output + '</td>';
  });
  return '<tr>' + selection + cells.join('') + '</tr>';
};
</script>

<?= App\Core\View::partial('partials/resource', ['res' => $resource]) ?>
<script src="<?= e(asset('js/admin-modules.js')) ?>"></script>