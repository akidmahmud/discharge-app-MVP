<?php namespace ProcessWire;
/**
 * module-security.php — Active sessions, failed logins, force logout
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── Ensure tables exist ───────────────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS admin_failed_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(128) NOT NULL,
        ip VARCHAR(64) NOT NULL DEFAULT '',
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (attempted_at)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_active_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(128) NOT NULL,
        ip VARCHAR(64) NOT NULL DEFAULT '',
        user_agent TEXT,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        session_id VARCHAR(128),
        UNIQUE KEY (session_id)
    )");
} catch (\Exception $e) {}

// Register/update current session
try {
    $sid  = session_id();
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $db->prepare("INSERT INTO admin_active_sessions
        (user_id, username, ip, user_agent, session_id, started_at, last_seen_at)
        VALUES (?,?,?,?,?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_seen_at=NOW(), ip=VALUES(ip)")
       ->execute([$user->id, $user->name, $ip, $ua, $sid]);
    // Expire sessions older than 2 hours
    $db->exec("DELETE FROM admin_active_sessions WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
} catch (\Exception $e) {}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action = $sanitizer->name($input->post->action);

    if ($action === 'force_logout') {
        $sid = $sanitizer->text($input->post->session_id);
        $db->prepare("DELETE FROM admin_active_sessions WHERE session_id=?")->execute([$sid]);
        adminLog($db, $user, 'security', 'force-logout', null, 'session_id', $sid, null);
        $session->redirect('/admin-panel/?module=security&saved=1');
    }
    if ($action === 'clear_failed') {
        $db->exec("DELETE FROM admin_failed_logins WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $session->redirect('/admin-panel/?module=security&saved=1');
    }
    if ($action === 'logout_all') {
        // Delete all sessions except current
        $sid = session_id();
        $db->prepare("DELETE FROM admin_active_sessions WHERE session_id != ?")->execute([$sid]);
        adminLog($db, $user, 'security', 'logout-all', null, null, null, 'all-except-current');
        $session->redirect('/admin-panel/?module=security&saved=1');
    }
}

$saved = $input->get->int('saved') === 1;

// ── Load data ─────────────────────────────────────────────────────────────────
$activeSessions = [];
$failedLogins   = [];
try {
    $activeSessions = $db->query("SELECT * FROM admin_active_sessions ORDER BY last_seen_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
    $failedLogins   = $db->query("SELECT * FROM admin_failed_logins ORDER BY attempted_at DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {}

// Brute-force hotspots: group failed logins by IP
$failedByIp = [];
foreach ($failedLogins as $row) {
    $failedByIp[$row['ip']] = ($failedByIp[$row['ip']] ?? 0) + 1;
}
arsort($failedByIp);

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
$currentSid = session_id();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Security</h1>
      <p class="admin-module__subtitle">Active sessions, failed login attempts, and access controls</p>
    </div>
    <form method="post" action="/admin-panel/?module=security">
      <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
      <input type="hidden" name="action" value="logout_all">
      <button type="submit" class="admin-btn admin-btn--danger"
        data-confirm="Force-logout all other sessions?">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout All Others
      </button>
    </form>
  </div>

  <?php if ($saved): ?>
  <div class="admin-alert admin-alert--success">Action completed successfully.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="admin-stats">
    <div class="admin-stat-card admin-stat-card--blue">
      <span class="admin-stat-card__label">Active Sessions</span>
      <span class="admin-stat-card__value"><?= count($activeSessions) ?></span>
      <span class="admin-stat-card__sub">Currently logged in</span>
    </div>
    <div class="admin-stat-card admin-stat-card--red">
      <span class="admin-stat-card__label">Failed Logins</span>
      <span class="admin-stat-card__value"><?= count($failedLogins) ?></span>
      <span class="admin-stat-card__sub">Last 50 recorded</span>
    </div>
    <div class="admin-stat-card admin-stat-card--amber">
      <span class="admin-stat-card__label">Suspicious IPs</span>
      <span class="admin-stat-card__value"><?= count(array_filter($failedByIp, fn($c)=>$c>=3)) ?></span>
      <span class="admin-stat-card__sub">≥3 failed attempts</span>
    </div>
  </div>

  <!-- Active Sessions -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Active Sessions
      </h2>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>User</th><th>IP Address</th><th>Browser / UA</th><th>Started</th><th>Last Seen</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if (empty($activeSessions)): ?>
        <tr><td colspan="6"><div class="admin-empty">No active sessions recorded.</div></td></tr>
        <?php else: ?>
        <?php foreach ($activeSessions as $sess): ?>
        <tr style="<?= $sess['session_id']===$currentSid?'background:#F0FDF4;':'' ?>">
          <td>
            <strong><?= htmlspecialchars($sess['username']) ?></strong>
            <?php if ($sess['session_id']===$currentSid): ?>
            <span class="badge badge--green" style="font-size:10px;margin-left:6px;">You</span>
            <?php endif; ?>
          </td>
          <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($sess['ip']) ?></td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#64748B;"
            title="<?= htmlspecialchars($sess['user_agent']) ?>">
            <?= htmlspecialchars(substr($sess['user_agent'] ?? '', 0, 80)) ?>
          </td>
          <td style="font-size:12px;white-space:nowrap;"><?= date('d M Y H:i', strtotime($sess['started_at'])) ?></td>
          <td style="font-size:12px;white-space:nowrap;color:#16A34A;"><?= date('d M Y H:i', strtotime($sess['last_seen_at'])) ?></td>
          <td>
            <?php if ($sess['session_id'] !== $currentSid): ?>
            <form method="post" action="/admin-panel/?module=security" style="display:inline;">
              <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
              <input type="hidden" name="action" value="force_logout">
              <input type="hidden" name="session_id" value="<?= htmlspecialchars($sess['session_id']) ?>">
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                data-confirm="Force logout <?= htmlspecialchars($sess['username']) ?>?">Force Logout</button>
            </form>
            <?php else: ?>
            <span style="font-size:12px;color:#94A3B8;">Current</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Failed Logins -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Failed Login Attempts
      </h2>
      <form method="post" action="/admin-panel/?module=security" style="display:inline;">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="clear_failed">
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm"
          data-confirm="Clear failed login records older than 30 days?">Clear Old Records</button>
      </form>
    </div>
    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;padding:16px 20px;">

      <!-- Table -->
      <div class="admin-table-wrap" style="margin:0;">
        <table class="admin-table">
          <thead>
            <tr><th>Username</th><th>IP Address</th><th>Time</th></tr>
          </thead>
          <tbody>
          <?php if (empty($failedLogins)): ?>
          <tr><td colspan="3"><div class="admin-empty">No failed login attempts recorded.</div></td></tr>
          <?php else: ?>
          <?php foreach ($failedLogins as $fl): ?>
          <tr>
            <td><?= htmlspecialchars($fl['username']) ?></td>
            <td style="font-family:monospace;font-size:12px;
              color:<?= ($failedByIp[$fl['ip']]??0)>=3?'#DC2626':'#374151' ?>;">
              <?= htmlspecialchars($fl['ip']) ?>
              <?php if (($failedByIp[$fl['ip']]??0)>=5): ?>
              <span class="badge badge--red" style="font-size:9px;margin-left:4px;">High</span>
              <?php elseif (($failedByIp[$fl['ip']]??0)>=3): ?>
              <span class="badge badge--amber" style="font-size:9px;margin-left:4px;">Warn</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#64748B;white-space:nowrap;"><?= date('d M Y H:i', strtotime($fl['attempted_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- IP summary -->
      <div>
        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.06em;">Top Offending IPs</div>
        <?php if (empty($failedByIp)): ?>
        <p style="font-size:13px;color:#94A3B8;">None recorded.</p>
        <?php else: ?>
        <?php $maxF = max($failedByIp) ?: 1; ?>
        <?php foreach (array_slice($failedByIp,0,8,true) as $ip => $cnt): ?>
        <div style="margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
            <span style="font-family:monospace;color:#374151;"><?= htmlspecialchars($ip) ?></span>
            <span style="font-weight:600;color:<?= $cnt>=5?'#DC2626':($cnt>=3?'#D97706':'#374151') ?>;"><?= $cnt ?>x</span>
          </div>
          <div style="height:6px;background:#F1F5F9;border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:<?= round($cnt/$maxF*100) ?>%;background:<?= $cnt>=5?'#DC2626':($cnt>=3?'#F59E0B':'#2563EB') ?>;border-radius:3px;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
