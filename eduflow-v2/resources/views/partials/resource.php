<?php
/**
 * Resource partial — the standard list screen used by every module.
 *
 * Renders a toolbar (search + filters + actions), a data table fed over AJAX
 * and the pagination footer. Views only describe *what* the columns are and
 * *how* a row is drawn; the markup, filtering and paging behaviour is shared
 * so every module in the product behaves identically.
 *
 * Expected $res keys:
 *   id          unique prefix for DOM ids
 *   endpoint    AJAX list endpoint (POST)
 *   columns     [ ['label' => 'Course', 'class' => 'num'], ... ]
 *   filters     [ ['type' => 'search'|'select'|'date', ... ], ... ]
 *   renderFn    name of the JS function rendering one row
 *   perPage     rows per page
 *   emptyTitle / emptyText
 *   toolbar     HTML placed on the right of the filter bar
 *   autoLoad    set false to delay the first load
 *   extra       PHP array forwarded to the endpoint on every request
 *   rowsLabel   label used by the record counter ("courses")
 */

$res = $res ?? [];
$id = isset($res['id']) ? $res['id'] : 'resource';
$endpoint = isset($res['endpoint']) ? $res['endpoint'] : '/';
$columns = isset($res['columns']) ? $res['columns'] : [];
$filters = isset($res['filters']) ? $res['filters'] : [];
$renderFn = isset($res['renderFn']) ? $res['renderFn'] : 'renderRow';
$bulk = !empty($res['bulk']);
$colspan = count($columns) + ($bulk ? 1 : 0);
$perPage = isset($res['perPage']) ? (int) $res['perPage'] : 12;
$toolbar = isset($res['toolbar']) ? $res['toolbar'] : '';
$autoLoad = array_key_exists('autoLoad', $res) ? (bool) $res['autoLoad'] : true;
$extra = isset($res['extra']) && is_array($res['extra']) ? $res['extra'] : [];
$rowsLabel = isset($res['rowsLabel']) ? $res['rowsLabel'] : 'records';

$search = null;
foreach ($filters as $filter) {
    if (($filter['type'] ?? '') === 'search') {
        $search = $filter;
    }
}
?>
<div class="resource" id="<?= e($id) ?>">

  <form class="filter-bar" id="<?= e($id) ?>-filters" autocomplete="off">
    <?php if ($search !== null): ?>
      <label class="search grow" style="max-width:340px">
        <?= icon('search', 16) ?>
        <input class="input" type="search" name="search"
               placeholder="<?= e($search['placeholder'] ?? 'Search…') ?>"
               aria-label="<?= e($search['placeholder'] ?? 'Search') ?>">
      </label>
    <?php endif; ?>

    <?php foreach ($filters as $filter): ?>
      <?php if (($filter['type'] ?? '') === 'search') { continue; } ?>

      <?php if (($filter['type'] ?? '') === 'select'): ?>
        <label class="field-inline">
          <span class="field-inline-label"><?= e($filter['label'] ?? 'Filter') ?></span>
          <select class="select" name="<?= e($filter['name']) ?>" aria-label="<?= e($filter['label'] ?? 'Filter') ?>">
            <option value=""<?= empty($filter['default']) ? ' selected' : '' ?>><?= e($filter['any'] ?? 'All') ?></option>
            <?php foreach (($filter['options'] ?? []) as $value => $label): ?>
              <option value="<?= e($value) ?>"<?= isset($filter['default']) && (string) $filter['default'] === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php elseif (($filter['type'] ?? '') === 'date'): ?>
        <label class="field-inline">
          <span class="field-inline-label"><?= e($filter['label'] ?? 'Date') ?></span>
          <input class="input" type="date" name="<?= e($filter['name']) ?>">
        </label>
      <?php endif; ?>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-sm"><?= icon('filter', 15) ?> Apply</button>
    <button type="button" class="btn btn-sm btn-quiet" data-filter-reset>Reset</button>

    <span class="filter-spacer"></span>

    <span class="resource-count tiny muted" id="<?= e($id) ?>-count" aria-live="polite"></span>
    <?= $toolbar ?>
  </form>

  <?php if ($bulk): ?>
    <div class="bulk-bar hidden" id="<?= e($id) ?>-bulk">
      <span class="small"><strong data-bulk-count>0</strong> selected</span>
      <span class="filter-spacer"></span>
      <?php foreach (($res['bulkActions'] ?? []) as $action):
          $bulkLabels = [
              'activate' => 'Activate', 'deactivate' => 'Deactivate', 'delete' => 'Delete selected',
              'download' => 'Download selected', 'approve' => 'Approve selected', 'reject' => 'Reject selected',
              'review' => 'Mark reviewed', 'clear' => 'Clear log', 'export' => 'Export CSV',
          ];
          $bulkDanger = in_array($action, ['delete', 'reject', 'clear'], true); ?>
        <button type="button" class="btn btn-sm<?= $bulkDanger ? ' is-danger' : '' ?>" data-bulk-action="<?= e($action) ?>">
          <?= icon($action === 'delete' || $action === 'clear' ? 'trash' : ($action === 'download' || $action === 'export' ? 'download' : 'check'), 14) ?>
          <?= e($bulkLabels[$action] ?? ucfirst($action)) ?>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="table-wrap">
      <table class="table" id="<?= e($id) ?>-table">
        <thead>
          <tr>
            <?php if ($bulk): ?>
              <th class="col-tight">
                <input type="checkbox" class="table-check" data-select-all aria-label="Select all rows">
              </th>
            <?php endif; ?>
            <?php foreach ($columns as $column): ?>
              <th class="<?= e($column['class'] ?? '') ?>"><?= e($column['label'] ?? '') ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="<?= e($id) ?>-rows">
          <tr>
            <td colspan="<?= $colspan ?>" style="padding:22px">
              <div class="row-center gap-10"><span class="spinner"></span>
                <span class="muted small">Loading…</span></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="panel-foot" id="<?= e($id) ?>-pagination"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  window.EF_LISTS = window.EF_LISTS || {};
  var res = <?= json_encode([
      'id' => $id,
      'endpoint' => $endpoint,
      'perPage' => $perPage,
      'colspan' => $colspan,
      'columns' => $columns,
      'renderFn' => $renderFn,
      'emptyTitle' => $res['emptyTitle'] ?? 'Nothing to show yet',
      'emptyText' => $res['emptyText'] ?? 'Records appear here once they are added.',
      'bulk' => $bulk,
      'moduleType' => $res['moduleType'] ?? '',
      'actions' => $res['actions'] ?? [],
      'bulkActions' => $res['bulkActions'] ?? [],
      'createLabel' => $res['createLabel'] ?? null,
      'autoLoad' => $autoLoad,
      'extra' => $extra,
      'rowsLabel' => $rowsLabel,
  ], JSON_UNESCAPED_SLASHES) ?>;

  var EF = window.EF;
  if (!EF || typeof EF.list !== 'function') return;

  var list = EF.list({
      endpoint: res.endpoint,
      form: '#' + res.id + '-filters',
      tbody: '#' + res.id + '-rows',
      pagination: '#' + res.id + '-pagination',
      perPage: res.perPage,
      colspan: res.colspan,
      emptyTitle: res.emptyTitle,
      emptyText: res.emptyText,
      selectAll: res.bulk ? '#' + res.id + '-table [data-select-all]' : null,
      bulkBar: res.bulk ? '#' + res.id + '-bulk' : null,
      extra: res.extra,
      render: function (row, index, number) {
        var fn = window[res.renderFn];
        if (typeof fn !== 'function') {
          return '<tr><td colspan="' + res.colspan + '">Row renderer "' + res.renderFn + '" is missing.</td></tr>';
        }
        return fn(row, index, number);
      },
      onCount: function (total) {
        var el = document.getElementById(res.id + '-count');
        var plural = res.rowsLabel.slice(-1) === 'y'
          ? res.rowsLabel.slice(0, -1) + 'ies'
          : res.rowsLabel + 's';
        if (el) el.textContent = total + ' ' + (total === 1 ? res.rowsLabel : plural);
      }
  });

  window.EF_LISTS[res.id] = list;
  EF.reload = function (name) {
    if (window.EF_LISTS[name]) window.EF_LISTS[name].load();
  };
  });
</script>
