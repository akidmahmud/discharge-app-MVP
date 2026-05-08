<?php namespace ProcessWire;

/**
 * admin-pw-setup.php — One-time script that creates the admin-panel
 * template and page in ProcessWire's database.
 * Visit: /api/admin-pw-setup/  (as superuser, once only)
 */

if (!$user->isSuperuser()) {
    header('HTTP/1.0 403 Forbidden');
    die('<p style="color:red;font-family:sans-serif;">403 — Superuser access required.</p>');
}

$log = [];
$err = [];

// ── 1. Create the template if it doesn't exist ────────────────────────────────
$tplName = 'admin-panel';
$existing = $templates->get($tplName);

if ($existing && $existing->id) {
    $log[] = "✅ Template <strong>{$tplName}</strong> already exists (id {$existing->id}).";
} else {
    try {
        $t = new Template();
        $t->name  = $tplName;
        $t->flags = 0;
        $t->save();
        $log[] = "✅ Template <strong>{$tplName}</strong> created (id {$t->id}).";
    } catch (\Exception $e) {
        $err[] = "❌ Could not create template: " . $e->getMessage();
    }
}

// ── 2. Create the page if it doesn't exist ────────────────────────────────────
$existingPage = $pages->get("name={$tplName}, template={$tplName}");

if ($existingPage && $existingPage->id) {
    $log[] = "✅ Page <strong>/{$tplName}/</strong> already exists (id {$existingPage->id}).";
} else {
    try {
        $tpl = $templates->get($tplName);
        if (!$tpl || !$tpl->id) throw new \Exception("Template not found after creation.");

        $p = new Page();
        $p->template = $tpl;
        $p->parent   = $pages->get('/');
        $p->name     = $tplName;
        $p->title    = 'Admin Panel';
        $p->addStatus(Page::statusHidden); // hidden from front-end lists
        $p->save();
        $log[] = "✅ Page <strong>/{$tplName}/</strong> created (id {$p->id}).";
    } catch (\Exception $e) {
        $err[] = "❌ Could not create page: " . $e->getMessage();
    }
}

// ── 3. Output result ──────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Panel Setup</title>
  <style>
    body { font-family: -apple-system, sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; color: #1E293B; }
    h1   { font-size: 20px; margin-bottom: 20px; }
    .log { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
    .err { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; }
    li   { margin: 6px 0; font-size: 14px; }
    .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #2563EB; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
    .btn:hover { background: #1D4ED8; }
  </style>
</head>
<body>
  <h1>Admin Panel — ProcessWire Setup</h1>

  <?php if (!empty($log)): ?>
  <div class="log">
    <ul style="margin:0;padding-left:18px;">
      <?php foreach ($log as $l): ?><li><?= $l ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (!empty($err)): ?>
  <div class="err">
    <ul style="margin:0;padding-left:18px;">
      <?php foreach ($err as $e): ?><li><?= $e ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (empty($err)): ?>
    <p style="color:#16A34A;font-weight:600;">Setup complete.</p>
    <a href="/admin-panel/" class="btn">→ Open Admin Panel</a>
  <?php else: ?>
    <p style="color:#DC2626;">Setup had errors. Check above and try again.</p>
  <?php endif; ?>
</body>
</html>
<?php exit; ?>
