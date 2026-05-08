<?php namespace ProcessWire;
/**
 * module-audit-logs.php — Read-only audit log viewer with filters
 */

$db = $database;

// ── Filters ───────────────────────────────────────────────────────────────────
$filterModule = $sanitizer->name($input->get->module_name ?? '');
$filterAction = $sanitizer->name($input->get->action_name ?? '');
$filterUser   = $sanitizer->text($input->get->username ?? '');
$filterFrom   = $sanitizer->date($input->get->date_from ?? '', 'Y-m-d');
$filterTo     = $sanitizer->date($input->get->date_to ?? '', 'Y-m-d');
$page_num     = max(1, (int)($input->get->pg ?? 1));
$perPage      = 50;
$offset       = ($page_num - 1) * $perPage;

// Build WHERE
$where = []; $params = [];
if ($filterModule) { $where[] = "module = ?"; $params[] = $filterModule; }
if ($filterAction) { $where[] = "action = ?"; $params[] = $filterAction; }
if ($filterUser)   { $where[] = "username LIKE ?"; $params[] = '%'.$filterUser.'%'; }
if ($filterFrom)   { $where[] = "created_at >= ?"; $params[] = $filterFrom . ' 00:00:00'; }
if ($filterTo)     { $where[] = "created_at <= ?"; $params[] = $filterTo . ' 23:59:59'; }
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$logs  = [];
$total = 0;
try {
    $total = (int)$db->prepare("SELECT COUNT(*) FROM admin_audit_logs {$whereStr}")
                     ->execute($params) ? (int)$db->prepare("SELECT COUNT(*) FROM admin_audit_logs {$whereStr}")->execute($params) : 0;
    $stmt  = $db->prepare("SELECT COUNT(*) FROM admin_audit_logs {$whereStr}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt2 = $db->prepare("SELECT * FROM admin_audit_logs {$whereStr} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt2->execute($params);
    $logs  = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

$totalPages = $total ? (int)ceil($total / $perPage) : 1;

// Distinct modules/actions for filter dropdowns
$modules = $actions = [];
try {
    $modules = $db->query("SELECT DISTINCT module FROM admin_audit_logs ORDER BY module")->fetchAll(\PDO::FETCH_COLUMN);
    $actions = $db->query("SELECT DISTINCT action  FROM admin_audit_logs ORDER BY action") ->fetchAll(\PDO::FETCH_COLUMN);
} catch (\Exception $e) {}

// ── POST: clear logs ──────────────────────────────────────────────────────────
$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
$notice = '';
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $act = $sanitizer->name($input->post->action);
    if ($act === 'clear_old_logs') {
        $days = max(7, (int)$input->post->keep_days);
        $db->prepare("DELETE FROM admin_audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        $session->redirect('/admin-panel/?module=audit-logs&cleared=1');
    }
}
$cleared = $input->get->int('cleared') === 1;

// Action badge colors
$actionColors = [
    'create'  => 'badge--green',
    'save'    => 'badge--green',
    'update'  => 'badge--blue',
    'restore' => 'badge--blue',
    'delete'  => 'badge--red',
    'delete-permanent' => 'badge--red',
    'login'   => 'badge--gray',
    'logout'  => 'badge--gray',
    'force-logout' => 'badge--amber',
    'logout-all'   => 'badge--amber',
];

// Build pagination URL helper
function pagerUrl(array $get, int $pg): string {
    $get['pg'] = $pg;
    return '/admin-panel/?' . http_build_query($get);
}
$getParams = array_filter([
    'module'      => 'audit-logs',
    'module_name' => $filterModule,
    'action_name' => $filterAction,
    'username'    => $filterUser,
    'date_from'   => $filterFrom,
    'date_to'     => $filterTo,
]);
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Audit Logs</h1>
      <p class="admin-module__subtitle">Complete audit trail of all admin actions</p>
    </div>
    <!-- Clear old logs -->
    <div style="display:flex;align-items:center;gap:8px;">
      <form method="post" action="/admin-panel/?module=audit-logs" style="display:flex;align-items:center;gap:8px;">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="clear_old_logs">
        <label style="font-size:13px;color:#64748B;white-space:nowrap;">Keep last</label>
        <select name="keep_days" class="admin-field__select" style="width:90px;">
          <option value="30">30 days</option>
          <option value="60">60 days</option>
          <option value="90">90 days</option>
          <option value="180">180 days</option>
        </select>
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm"
          data-confirm="Delete audit logs older than the selected period?">Clear Old</button>
      </form>
    </div>
  </div>

  <?php if ($cleared): ?>
  <div class="admin-alert admin-alert--success">Old audit logs cleared.</div>
  <?php endif; ?>

  <!-- Filter bar -->
  <div class="admin-card">
    <div class="admin-card__body">
      <form method="get" action="/admin-panel/" class="admin-filter-bar" style="flex-wrap:wrap;">
        <input type="hidden" name="module" value="audit-logs">
        <select class="admin-field__select" name="module_name" style="width:160px;">
          <option value="">All Modules</option>
          <?php foreach ($modules as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>" <?= $filterModule===$m?'selected':'' ?>><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="admin-field__select" name="action_name" style="width:140px;">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
          <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="admin-field__input" type="text" name="username" value="<?= htmlspecialchars($filterUser) ?>"
          placeholder="Username…" style="width:140px;">
        <input class="admin-field__input" type="date" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>"
          style="width:150px;" title="From date">
        <input class="admin-field__input" type="date" name="date_to"   value="<?= htmlspecialchars($filterTo) ?>"
          style="width:150px;" title="To date">
        <button type="submit" class="admin-btn admin-btn--primary">
          <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <?php if ($filterModule||$filterAction||$filterUser||$filterFrom||$filterTo): ?>
        <a href="/admin-panel/?module=audit-logs" class="admin-btn admin-btn--ghost">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Log Entries
      </h2>
      <span style="font-size:12px;color:#94A3B8;"><?= number_format($total) ?> total entr<?= $total!==1?'ies':'y' ?></span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:140px;">Timestamp</th>
            <th>User</th>
            <th>Module</th>
            <th>Action</th>
            <th>Record</th>
            <th>Field</th>
            <th>Old Value</th>
            <th>New Value</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="8"><div class="admin-empty">No log entries match your filters.</div></td></tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="font-size:11px;color:#64748B;white-space:nowrap;"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
          <td style="font-size:13px;font-weight:500;"><?= htmlspecialchars($log['username'] ?? '—') ?></td>
          <td><span class="badge badge--gray" style="font-size:10px;"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
          <td>
            <?php $ac = $log['action'] ?? ''; ?>
            <span class="badge <?= $actionColors[$ac] ?? 'badge--gray' ?>" style="font-size:10px;"><?= htmlspecialchars($ac) ?></span>
          </td>
          <td style="font-size:12px;color:#64748B;"><?= $log['record_id'] ? '#'.$log['record_id'] : '—' ?></td>
          <td style="font-size:12px;color:#64748B;"><?= htmlspecialchars($log['field_name'] ?? '—') ?></td>
          <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#94A3B8;"
            title="<?= htmlspecialchars($log['old_value'] ?? '') ?>">
            <?= htmlspecialchars(substr($log['old_value'] ?? '', 0, 40)) ?: '—' ?>
          </td>
          <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#374151;"
            title="<?= htmlspecialchars($log['new_value'] ?? '') ?>">
            <?= htmlspecialchars(substr($log['new_value'] ?? '', 0, 40)) ?: '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid #F1F5F9;">
      <span style="font-size:13px;color:#64748B;">
        Page <?= $page_num ?> of <?= $totalPages ?> &mdash; <?= number_format($total) ?> entries
      </span>
      <div style="display:flex;gap:4px;">
        <?php if ($page_num > 1): ?>
        <a href="<?= htmlspecialchars(pagerUrl($getParams, $page_num-1)) ?>" class="admin-btn admin-btn--ghost admin-btn--sm">&laquo; Prev</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page_num-2);
        $end   = min($totalPages, $start+4);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="<?= htmlspecialchars(pagerUrl($getParams, $p)) ?>"
          class="admin-btn admin-btn--<?= $p===$page_num?'primary':'ghost' ?> admin-btn--sm"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page_num < $totalPages): ?>
        <a href="<?= htmlspecialchars(pagerUrl($getParams, $page_num+1)) ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
