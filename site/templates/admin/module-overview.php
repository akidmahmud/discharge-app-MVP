<?php namespace ProcessWire;
/**
 * module-overview.php — Admin dashboard overview
 */

// Stats
$totalUsers     = $users->count("name!=guest");
$activeAdm      = $pages->count("template=admission-record, case_status=1");
$pendingDis     = $pages->count("template=admission-record, case_status=1");
$dischargedMTD  = $pages->count("template=admission-record, case_status=2, discharged_on>=" . strtotime('first day of this month'));

// Recent audit logs
$recentLogs = [];
try {
    $stmt = $database->query("SELECT * FROM admin_audit_logs ORDER BY created_at DESC LIMIT 15");
    $recentLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Pending discharge count
$missingNotes = $pages->count("template=admission-record, case_status=1");
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Overview</h1>
      <p class="admin-module__subtitle">System status at a glance — <?= date('d M Y') ?></p>
    </div>
  </div>

  <!-- Stats -->
  <div class="admin-stats">
    <div class="admin-stat-card admin-stat-card--blue">
      <span class="admin-stat-card__label">Total Users</span>
      <span class="admin-stat-card__value"><?= (int) $totalUsers ?></span>
      <span class="admin-stat-card__sub">Registered accounts</span>
    </div>
    <div class="admin-stat-card admin-stat-card--green">
      <span class="admin-stat-card__label">Active Cases</span>
      <span class="admin-stat-card__value"><?= (int) $activeAdm ?></span>
      <span class="admin-stat-card__sub">Current admissions</span>
    </div>
    <div class="admin-stat-card admin-stat-card--amber">
      <span class="admin-stat-card__label">Pending Discharge</span>
      <span class="admin-stat-card__value"><?= (int) $pendingDis ?></span>
      <span class="admin-stat-card__sub">Awaiting discharge summary</span>
    </div>
    <div class="admin-stat-card admin-stat-card--red">
      <span class="admin-stat-card__label">Discharged (MTD)</span>
      <span class="admin-stat-card__value"><?= (int) $dischargedMTD ?></span>
      <span class="admin-stat-card__sub">This month</span>
    </div>
  </div>

  <!-- Quick links -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
    <a href="/admin-panel/?module=users" class="admin-card" style="text-decoration:none;padding:18px 20px;display:flex;align-items:center;gap:14px;">
      <div style="width:40px;height:40px;border-radius:8px;background:#EEF4FF;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="#2563EB" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0F172A;">Users &amp; Roles</div>
        <div style="font-size:12px;color:#64748B;">Manage access</div>
      </div>
    </a>
    <a href="/admin-panel/?module=discharge-settings" class="admin-card" style="text-decoration:none;padding:18px 20px;display:flex;align-items:center;gap:14px;">
      <div style="width:40px;height:40px;border-radius:8px;background:#F0FDF4;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="#16A34A" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0F172A;">Discharge Settings</div>
        <div style="font-size:12px;color:#64748B;">PDF &amp; sections</div>
      </div>
    </a>
    <a href="/admin-panel/?module=workflow" class="admin-card" style="text-decoration:none;padding:18px 20px;display:flex;align-items:center;gap:14px;">
      <div style="width:40px;height:40px;border-radius:8px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="#D97706" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0F172A;">Workflow</div>
        <div style="font-size:12px;color:#64748B;">Module order &amp; config</div>
      </div>
    </a>
  </div>

  <!-- Recent activity -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Recent Activity
      </h2>
      <a href="/admin-panel/?module=audit-logs" class="admin-btn admin-btn--ghost admin-btn--sm">View all</a>
    </div>
    <div class="admin-table-wrap">
      <?php if (empty($recentLogs)): ?>
      <div class="admin-empty">No activity recorded yet. Activity appears here once admin actions are performed.</div>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>User</th><th>Module</th><th>Action</th><th>Detail</th><th>Time</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td><strong><?= htmlspecialchars($log['username'] ?? '—') ?></strong></td>
            <td><span class="badge badge--blue"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($log['action'] ?? '—') ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748B;font-size:12px;">
              <?= htmlspecialchars($log['new_value'] ?? $log['field_name'] ?? '—') ?>
            </td>
            <td style="color:#94A3B8;font-size:12px;white-space:nowrap;">
              <?= $log['created_at'] ? date('d M, H:i', strtotime($log['created_at'])) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>
