<?php namespace ProcessWire;
/**
 * module-backup.php — Database backup create / list / download / delete
 */

$db     = $database;
$notice = ''; $noticeType = 'success';

$backupDir = $config->paths->assets . 'backups/';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action = $sanitizer->name($input->post->action);

    // ── Create backup ─────────────────────────────────────────────────────────
    if ($action === 'create_backup') {
        $label    = $sanitizer->text($input->post->label) ?: 'manual';
        $label    = preg_replace('/[^a-z0-9_-]/i', '-', $label);
        $filename = 'backup-' . date('Ymd-His') . '-' . substr($label,0,30) . '.sql';
        $filepath = $backupDir . $filename;

        // Tables to back up (admin tables only for safety — PW tables can be large)
        $adminTables = ['admin_users_meta','admin_consultants','admin_templates',
            'admin_workflow','admin_rules','admin_discharge_settings',
            'admin_login_settings','admin_audit_logs','admin_failed_logins',
            'admin_active_sessions'];

        $sql = "-- Clinical Registry Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- User: " . $user->name . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($adminTables as $table) {
            try {
                // Table structure
                $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                if (!$create) continue;
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $create[1] . ";\n\n";

                // Data
                $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
                    $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n";
                    $vals = [];
                    foreach ($rows as $row) {
                        $escaped = array_map(fn($v) => $v === null ? 'NULL' : $db->quote((string)$v), array_values($row));
                        $vals[] = '(' . implode(',', $escaped) . ')';
                    }
                    $sql .= implode(",\n", $vals) . ";\n\n";
                }
            } catch (\Exception $e) {}
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (file_put_contents($filepath, $sql) !== false) {
            adminLog($db, $user, 'backup', 'create', null, 'file', null, $filename);
            $notice = "Backup created: {$filename}";
        } else {
            $notice = 'Failed to write backup file. Check directory permissions.'; $noticeType = 'error';
        }
    }

    // ── Delete backup ─────────────────────────────────────────────────────────
    if ($action === 'delete_backup') {
        $fname = basename($sanitizer->text($input->post->filename));
        $fpath = $backupDir . $fname;
        if (is_file($fpath) && strpos(realpath($fpath), realpath($backupDir)) === 0) {
            unlink($fpath);
            adminLog($db, $user, 'backup', 'delete', null, 'file', $fname, null);
            $session->redirect('/admin-panel/?module=backup&saved=1');
        }
    }
}

// ── Handle download (GET) ─────────────────────────────────────────────────────
if ($input->get->action === 'download') {
    $fname = basename($sanitizer->text($input->get->file));
    $fpath = $backupDir . $fname;
    if (is_file($fpath) && strpos(realpath($fpath), realpath($backupDir)) === 0
        && preg_match('/^backup-[\w-]+\.sql$/', $fname)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . filesize($fpath));
        readfile($fpath);
        exit;
    }
}

$saved = $input->get->int('saved') === 1;

// ── List backups ──────────────────────────────────────────────────────────────
$backups = [];
foreach (glob($backupDir . 'backup-*.sql') as $f) {
    $backups[] = [
        'name'    => basename($f),
        'size'    => filesize($f),
        'sizeStr' => filesize($f) > 1048576 ? round(filesize($f)/1048576,2).' MB' : round(filesize($f)/1024,1).' KB',
        'mtime'   => filemtime($f),
    ];
}
usort($backups, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Backup & Restore</h1>
      <p class="admin-module__subtitle">Create and download database backups of admin data</p>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="admin-alert admin-alert--success">Backup deleted.</div>
  <?php endif; ?>
  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

    <!-- Backup list -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><polyline points="21 15 21 21 3 21 3 15"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Backup Files
        </h2>
        <span style="font-size:12px;color:#94A3B8;"><?= count($backups) ?> backup<?= count($backups)!==1?'s':'' ?></span>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if (empty($backups)): ?>
          <tr><td colspan="4"><div class="admin-empty">No backups yet. Create one using the panel on the right.</div></td></tr>
          <?php else: ?>
          <?php foreach ($backups as $bk): ?>
          <tr>
            <td>
              <span style="font-family:monospace;font-size:12px;color:#1E293B;"><?= htmlspecialchars($bk['name']) ?></span>
            </td>
            <td style="font-size:12px;color:#64748B;"><?= $bk['sizeStr'] ?></td>
            <td style="font-size:12px;color:#64748B;white-space:nowrap;"><?= date('d M Y H:i', $bk['mtime']) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="/admin-panel/?module=backup&action=download&file=<?= urlencode($bk['name']) ?>"
                  class="admin-btn admin-btn--ghost admin-btn--sm">Download</a>
                <form method="post" action="/admin-panel/?module=backup" style="display:inline;">
                  <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                  <input type="hidden" name="action" value="delete_backup">
                  <input type="hidden" name="filename" value="<?= htmlspecialchars($bk['name']) ?>">
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                    data-confirm="Delete backup '<?= htmlspecialchars($bk['name']) ?>'?">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create backup panel -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="admin-card">
        <div class="admin-card__header">
          <h2 class="admin-card__title">
            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Create Backup
          </h2>
        </div>
        <div class="admin-card__body">
          <form method="post" action="/admin-panel/?module=backup">
            <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
            <input type="hidden" name="action" value="create_backup">
            <div class="admin-field" style="margin-bottom:14px;">
              <label class="admin-field__label">Label (optional)</label>
              <input class="admin-field__input" type="text" name="label" placeholder="e.g. pre-upgrade"
                pattern="[a-zA-Z0-9_\-]*" title="Letters, numbers, dashes, underscores only">
              <span class="admin-field__hint">Used in the filename for easy identification</span>
            </div>
            <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">
              <svg viewBox="0 0 24 24"><polyline points="21 15 21 21 3 21 3 15"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Create Backup Now
            </button>
          </form>
        </div>
      </div>

      <!-- Info card -->
      <div class="admin-card">
        <div class="admin-card__body" style="font-size:13px;color:#64748B;line-height:1.65;">
          <div style="font-weight:600;color:#374151;margin-bottom:8px;">What's included</div>
          <ul style="margin:0;padding-left:18px;">
            <li>Users meta & roles</li>
            <li>Consultants</li>
            <li>Discharge templates</li>
            <li>Workflow configuration</li>
            <li>Rule engine rules</li>
            <li>Discharge & login settings</li>
            <li>Audit logs</li>
            <li>Security logs</li>
          </ul>
          <div style="margin-top:12px;padding:10px 12px;background:#FEF9ED;border:1px solid #FDE68A;border-radius:6px;font-size:12px;color:#92400E;">
            <strong>Note:</strong> ProcessWire core pages and fields are not included. Back up your full database via phpMyAdmin for a complete snapshot.
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
