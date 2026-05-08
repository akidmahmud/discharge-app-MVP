<?php namespace ProcessWire;

/**
 * admin-panel.php — Custom admin panel entry point.
 * Superuser access only. Module routing via ?module= param.
 */

if (!$user->isSuperuser()) {
    $session->redirect('/dashboard/');
}

$noShell = true;

// ── Module routing ────────────────────────────────────────────────────────────
$module = $sanitizer->name($input->get->module ?? 'overview');
$validModules = [
    'overview', 'users', 'consultants', 'templates',
    'workflow', 'rules', 'discharge-settings', 'dashboard-permissions',
    // Phase 3 stubs
    'login-settings', 'analytics', 'search',
    'media', 'security', 'backup', 'archive', 'audit-logs', 'system-settings',
];
if (!in_array($module, $validModules)) $module = 'overview';

$moduleFile = $config->paths->templates . "admin/module-{$module}.php";
$isStub     = !file_exists($moduleFile);

// ── Audit log helper (available to all module files) ─────────────────────────
function adminLog($db, $user, $mod, $action, $recordId = null, $field = null, $old = null, $new = null) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO admin_audit_logs
             (user_id, username, module, action, record_id, field_name, old_value, new_value, ip_address)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $user->id, $user->name, $mod, $action, $recordId,
            $field, $old ? substr($old, 0, 500) : null,
            $new  ? substr($new, 0, 500) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (\Exception $e) {}
}

// ── CSRF tokens (passed to all modules via scope) ─────────────────────────────
$csrfName = $session->CSRF->getTokenName();
$csrfVal  = $session->CSRF->getTokenValue();

// ── Module labels (for breadcrumb) ────────────────────────────────────────────
$moduleLabels = [
    'overview'           => 'Overview',
    'users'              => 'Users & Roles',
    'consultants'        => 'Consultants',
    'templates'          => 'Templates',
    'workflow'           => 'Workflow Config',
    'rules'              => 'Rule Engine',
    'discharge-settings'     => 'Discharge Settings',
    'dashboard-permissions'  => 'Dashboard Permissions',
    'login-settings'         => 'Login Page Settings',
    'analytics'          => 'Analytics',
    'search'             => 'Global Search',
    'media'              => 'Media Storage',
    'security'           => 'Security',
    'backup'             => 'Backup',
    'archive'            => 'Archive',
    'audit-logs'         => 'Audit Logs',
    'system-settings'    => 'System Settings',
];
$moduleLabel = $moduleLabels[$module] ?? ucfirst($module);

$tplUrl = $config->urls->templates;
?>
<div id="content">
<div class="admin-wrap">

  <?php include $config->paths->templates . '_admin-shell.php'; ?>

  <div class="admin-body">
    <!-- ── Sidebar ──────────────────────────────────────── -->
    <aside class="admin-sidebar" id="admin-sidebar">
      <nav class="admin-nav">

        <div class="admin-nav__group">
          <span class="admin-nav__label">Main</span>
          <a href="/admin-panel/?module=overview" class="admin-nav__item<?= $module==='overview'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Overview</span>
          </a>
        </div>

        <div class="admin-nav__group">
          <span class="admin-nav__label">Management</span>
          <a href="/admin-panel/?module=users" class="admin-nav__item<?= $module==='users'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Users &amp; Roles</span>
          </a>
          <a href="/admin-panel/?module=consultants" class="admin-nav__item<?= $module==='consultants'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Consultants</span>
          </a>
        </div>

        <div class="admin-nav__group">
          <span class="admin-nav__label">Configuration</span>
          <a href="/admin-panel/?module=templates" class="admin-nav__item<?= $module==='templates'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>Templates</span>
          </a>
          <a href="/admin-panel/?module=workflow" class="admin-nav__item<?= $module==='workflow'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span>Workflow</span>
          </a>
          <a href="/admin-panel/?module=rules" class="admin-nav__item<?= $module==='rules'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            <span>Rule Engine</span>
          </a>
          <a href="/admin-panel/?module=discharge-settings" class="admin-nav__item<?= $module==='discharge-settings'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Discharge Settings</span>
          </a>
          <a href="/admin-panel/?module=dashboard-permissions" class="admin-nav__item<?= $module==='dashboard-permissions'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>Dashboard Permissions</span>
          </a>
          <a href="/admin-panel/?module=login-settings" class="admin-nav__item<?= $module==='login-settings'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M7 21v-1a5 5 0 0 1 10 0v1"/></svg>
            <span>Login Settings</span>
          </a>
        </div>

        <div class="admin-nav__group">
          <span class="admin-nav__label">Data</span>
          <a href="/admin-panel/?module=analytics" class="admin-nav__item<?= $module==='analytics'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Analytics</span>
          </a>
          <a href="/admin-panel/?module=search" class="admin-nav__item<?= $module==='search'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <span>Global Search</span>
          </a>
          <a href="/admin-panel/?module=media" class="admin-nav__item<?= $module==='media'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            <span>Media</span>
          </a>
        </div>

        <div class="admin-nav__group">
          <span class="admin-nav__label">System</span>
          <a href="/admin-panel/?module=security" class="admin-nav__item<?= $module==='security'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Security</span>
          </a>
          <a href="/admin-panel/?module=backup" class="admin-nav__item<?= $module==='backup'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Backup</span>
          </a>
          <a href="/admin-panel/?module=archive" class="admin-nav__item<?= $module==='archive'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
            <span>Archive</span>
          </a>
          <a href="/admin-panel/?module=audit-logs" class="admin-nav__item<?= $module==='audit-logs'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span>Audit Logs</span>
          </a>
          <a href="/admin-panel/?module=system-settings" class="admin-nav__item<?= $module==='system-settings'?' is-active':'' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            <span>System Settings</span>
          </a>
        </div>

      </nav>
    </aside>

    <!-- ── Workspace ─────────────────────────────────────── -->
    <main class="admin-workspace" id="admin-workspace">

      <!-- Breadcrumb -->
      <div class="admin-breadcrumb">
        <a href="/admin-panel/">Admin</a>
        <span class="admin-breadcrumb__sep">›</span>
        <span><?= htmlspecialchars($moduleLabel) ?></span>
      </div>

      <?php if ($isStub): ?>
      <!-- Phase 3 stub -->
      <div class="admin-stub">
        <div class="admin-stub__icon">
          <svg viewBox="0 0 24 24" width="40" height="40" stroke="#2563EB" fill="none" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h2 class="admin-stub__title"><?= htmlspecialchars($moduleLabel) ?></h2>
        <p class="admin-stub__text">This module is part of Phase 3 and will be available soon.</p>
      </div>
      <?php else: ?>
      <?php include $moduleFile; ?>
      <?php endif; ?>

    </main>
  </div><!-- /.admin-body -->

</div><!-- /.admin-wrap -->
</div><!-- #content -->
