<?php namespace ProcessWire;
/**
 * module-media.php — File and asset browser
 */

$notice = ''; $noticeType = 'success';

// ── POST: delete file ─────────────────────────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action = $sanitizer->name($input->post->action);
    if ($action === 'delete_file') {
        $rel = $sanitizer->text($input->post->file_path);
        // Restrict to assets directory only
        $assetsBase = rtrim($config->paths->assets, '/');
        $fullPath   = realpath($assetsBase . '/' . ltrim($rel, '/'));
        if ($fullPath && strpos($fullPath, $assetsBase) === 0 && is_file($fullPath)) {
            unlink($fullPath);
            adminLog($database, $user, 'media', 'delete', null, 'file', $rel, null);
            $notice = 'File deleted.';
        } else {
            $notice = 'Invalid or missing file.'; $noticeType = 'error';
        }
    }
}

// ── Browse directory ──────────────────────────────────────────────────────────
$assetsPath = $config->paths->assets . 'files/';
$assetsUrl  = $config->urls->assets  . 'files/';

// Sub-folder navigation (restricted to files/ inside assets)
$subDir = $sanitizer->path($input->get->dir ?? '');
$subDir = preg_replace('/\.\.[\\/]/', '', $subDir); // strip traversal
$currentDir  = rtrim($assetsPath . ltrim($subDir, '/'), '/') . '/';
$currentUrl  = rtrim($assetsUrl  . ltrim($subDir, '/'), '/') . '/';

if (!is_dir($currentDir)) { $currentDir = $assetsPath; $currentUrl = $assetsUrl; $subDir = ''; }

// Read directory
$items = []; $dirs = [];
if (is_dir($currentDir)) {
    foreach (scandir($currentDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = $currentDir . $f;
        if (is_dir($fp)) {
            $dirs[] = ['name' => $f, 'dir' => ltrim($subDir . '/' . $f, '/')];
        } elseif (is_file($fp)) {
            $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $size = filesize($fp);
            $items[] = [
                'name'    => $f,
                'ext'     => $ext,
                'size'    => $size,
                'sizeStr' => $size > 1048576 ? round($size/1048576,1).'MB' : round($size/1024,1).'KB',
                'url'     => $currentUrl . $f,
                'rel'     => ltrim($subDir . '/' . $f, '/'),
                'mtime'   => filemtime($fp),
                'isImage' => in_array($ext, ['jpg','jpeg','png','gif','webp','svg']),
            ];
        }
    }
    usort($items, fn($a,$b) => $b['mtime'] <=> $a['mtime']);
}

$imageExts = ['jpg','jpeg','png','gif','webp','svg'];
$docExts   = ['pdf','doc','docx','xls','xlsx','csv','txt'];

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();

// Breadcrumbs
$crumbs = [['label'=>'files', 'dir'=>'']];
if ($subDir) {
    $parts = explode('/', trim($subDir,'/'));
    $built = '';
    foreach ($parts as $part) {
        $built .= ($built?'/':'') . $part;
        $crumbs[] = ['label'=>$part,'dir'=>$built];
    }
}
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Media & Files</h1>
      <p class="admin-module__subtitle">Browse and manage uploaded files in the assets directory</p>
    </div>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <!-- Breadcrumb -->
  <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:#64748B;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ($crumbs as $i => $crumb): ?>
    <?php if ($i): ?><span style="color:#CBD5E1;">/</span><?php endif; ?>
    <?php if ($i < count($crumbs)-1): ?>
    <a href="/admin-panel/?module=media&dir=<?= urlencode($crumb['dir']) ?>" style="color:#2563EB;text-decoration:none;"><?= htmlspecialchars($crumb['label']) ?></a>
    <?php else: ?>
    <span style="color:#1E293B;font-weight:500;"><?= htmlspecialchars($crumb['label']) ?></span>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- Stats bar -->
  <div style="display:flex;gap:16px;margin-bottom:20px;">
    <div class="admin-stat-card admin-stat-card--blue" style="flex:1;padding:14px 18px;">
      <span class="admin-stat-card__label">Total Files</span>
      <span class="admin-stat-card__value" style="font-size:22px;"><?= count($items) ?></span>
    </div>
    <div class="admin-stat-card admin-stat-card--green" style="flex:1;padding:14px 18px;">
      <span class="admin-stat-card__label">Images</span>
      <span class="admin-stat-card__value" style="font-size:22px;"><?= count(array_filter($items, fn($f)=>$f['isImage'])) ?></span>
    </div>
    <div class="admin-stat-card admin-stat-card--amber" style="flex:1;padding:14px 18px;">
      <span class="admin-stat-card__label">Subfolders</span>
      <span class="admin-stat-card__value" style="font-size:22px;"><?= count($dirs) ?></span>
    </div>
    <div class="admin-stat-card admin-stat-card--red" style="flex:1;padding:14px 18px;">
      <span class="admin-stat-card__label">Total Size</span>
      <span class="admin-stat-card__value" style="font-size:22px;"><?= count($items) ? (array_sum(array_column($items,'size')) > 1048576 ? round(array_sum(array_column($items,'size'))/1048576,1).'MB' : round(array_sum(array_column($items,'size'))/1024,1).'KB') : '0KB' ?></span>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-card__body" style="padding:0;">

      <!-- Subfolders -->
      <?php if (!empty($dirs)): ?>
      <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#94A3B8;margin-bottom:10px;font-weight:600;">Folders</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach ($dirs as $d): ?>
          <a href="/admin-panel/?module=media&dir=<?= urlencode($d['dir']) ?>"
            style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:8px;text-decoration:none;color:#374151;font-size:13px;font-weight:500;transition:all 0.15s;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" fill="none" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <?= htmlspecialchars($d['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- File list -->
      <?php if (empty($items)): ?>
      <div class="admin-empty" style="padding:48px;">No files in this directory.</div>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width:40px;"></th>
              <th>Filename</th>
              <th>Type</th>
              <th>Size</th>
              <th>Modified</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $f): ?>
          <tr>
            <td style="padding:8px 12px;text-align:center;">
              <?php if ($f['isImage']): ?>
              <img src="<?= htmlspecialchars($f['url']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #E2E8F0;"
                onerror="this.style.display='none'">
              <?php else: ?>
              <div style="width:36px;height:36px;background:#F1F5F9;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#64748B;text-transform:uppercase;">
                <?= htmlspecialchars($f['ext']) ?>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank"
                style="color:#1E293B;font-weight:500;text-decoration:none;font-size:13px;"
                title="<?= htmlspecialchars($f['name']) ?>">
                <?= htmlspecialchars(strlen($f['name'])>50 ? substr($f['name'],0,47).'...' : $f['name']) ?>
              </a>
            </td>
            <td><span class="badge badge--gray" style="font-size:10px;"><?= strtoupper(htmlspecialchars($f['ext'])) ?></span></td>
            <td style="font-size:12px;color:#64748B;"><?= $f['sizeStr'] ?></td>
            <td style="font-size:12px;color:#64748B;white-space:nowrap;"><?= date('d M Y H:i', $f['mtime']) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" class="admin-btn admin-btn--ghost admin-btn--sm">View</a>
                <a href="<?= htmlspecialchars($f['url']) ?>" download class="admin-btn admin-btn--ghost admin-btn--sm">Download</a>
                <form method="post" action="/admin-panel/?module=media&dir=<?= urlencode($subDir) ?>" style="display:inline;">
                  <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                  <input type="hidden" name="action" value="delete_file">
                  <input type="hidden" name="file_path" value="<?= htmlspecialchars($f['rel']) ?>">
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                    data-confirm="Delete '<?= htmlspecialchars($f['name']) ?>'? This cannot be undone.">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
