<?php namespace ProcessWire;
/**
 * module-login-settings.php — Login page appearance settings
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_login_settings') {
            $bgMode       = in_array($input->post->bg_mode, ['gradient','image']) ? $input->post->bg_mode : 'gradient';
            $overlayOp    = max(0, min(100, (int)$input->post->overlay_opacity));
            $blurEnabled  = $sanitizer->int($input->post->blur_enabled) ? '1' : '0';
            $quoteText    = $sanitizer->text($input->post->quote_text);

            // Handle image upload
            $bgImage = $sanitizer->text($input->post->existing_bg_image);
            if (!empty($_FILES['bg_image']['name']) && $_FILES['bg_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $config->paths->assets . 'files/login-bg/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext  = strtolower(pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp'];
                if (in_array($ext, $allowed)) {
                    $filename = 'login-bg-' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $uploadDir . $filename)) {
                        $bgImage = $config->urls->assets . 'files/login-bg/' . $filename;
                    }
                }
            }

            $settings = [
                'bg_mode'         => $bgMode,
                'bg_image'        => $bgImage,
                'overlay_opacity' => (string)$overlayOp,
                'blur_enabled'    => $blurEnabled,
                'quote_text'      => $quoteText ?: 'Precision in documentation is the first step toward precision in care.',
            ];

            $stmt = $db->prepare("INSERT INTO admin_login_settings (setting_key, setting_value)
                VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            foreach ($settings as $k => $v) $stmt->execute([$k, $v]);

            adminLog($db, $user, 'login-settings', 'save', null, null, null, 'updated');
            $session->redirect('/admin-panel/?module=login-settings&saved=1');
        }

        if ($action === 'remove_image') {
            $db->prepare("INSERT INTO admin_login_settings (setting_key, setting_value) VALUES ('bg_image','')
                ON DUPLICATE KEY UPDATE setting_value=''")->execute();
            $db->prepare("INSERT INTO admin_login_settings (setting_key, setting_value) VALUES ('bg_mode','gradient')
                ON DUPLICATE KEY UPDATE setting_value='gradient'")->execute();
            $session->redirect('/admin-panel/?module=login-settings&saved=1');
        }
    }
}

// ── Load settings ─────────────────────────────────────────────────────────────
$s = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM admin_login_settings")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $s[$row['setting_key']] = $row['setting_value'];
} catch (\Exception $e) {}

$s += [
    'bg_mode'         => 'gradient',
    'bg_image'        => '',
    'overlay_opacity' => '30',
    'blur_enabled'    => '0',
    'quote_text'      => 'Precision in documentation is the first step toward precision in care.',
];

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Login Page Settings</h1>
      <p class="admin-module__subtitle">Customize the login page appearance and content</p>
    </div>
    <a href="/" target="_blank" class="admin-btn admin-btn--ghost">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      Preview Login Page
    </a>
  </div>

  <form method="post" action="/admin-panel/?module=login-settings" enctype="multipart/form-data">
    <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
    <input type="hidden" name="action" value="save_login_settings">
    <input type="hidden" name="existing_bg_image" value="<?= htmlspecialchars($s['bg_image']) ?>">

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

      <!-- Left: Settings -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Background mode -->
        <div class="admin-card">
          <div class="admin-card__header">
            <h2 class="admin-card__title">
              <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              Background
            </h2>
          </div>
          <div class="admin-card__body" style="display:flex;flex-direction:column;gap:16px;">
            <div class="admin-field">
              <label class="admin-field__label">Background Mode</label>
              <div style="display:flex;gap:12px;">
                <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:2px solid <?= $s['bg_mode']==='gradient'?'#2563EB':'#E2E8F0' ?>;border-radius:8px;cursor:pointer;flex:1;" id="mode-gradient-wrap">
                  <input type="radio" name="bg_mode" value="gradient" <?= $s['bg_mode']==='gradient'?'checked':'' ?> onchange="updateModeUI()">
                  <span style="font-size:13.5px;font-weight:500;color:#374151;">Gradient (Default)</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:2px solid <?= $s['bg_mode']==='image'?'#2563EB':'#E2E8F0' ?>;border-radius:8px;cursor:pointer;flex:1;" id="mode-image-wrap">
                  <input type="radio" name="bg_mode" value="image" <?= $s['bg_mode']==='image'?'checked':'' ?> onchange="updateModeUI()">
                  <span style="font-size:13.5px;font-weight:500;color:#374151;">Custom Image</span>
                </label>
              </div>
            </div>

            <!-- Image upload (shown when image mode) -->
            <div id="image-upload-section" style="display:<?= $s['bg_mode']==='image'?'block':'none' ?>;">
              <?php if ($s['bg_image']): ?>
              <div style="margin-bottom:12px;display:flex;align-items:center;gap:12px;padding:10px 14px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:8px;">
                <img src="<?= htmlspecialchars($s['bg_image']) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:4px;">
                <span style="font-size:13px;color:#374151;flex:1;">Current background image</span>
                <form method="post" action="/admin-panel/?module=login-settings" style="display:inline;">
                  <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                  <input type="hidden" name="action" value="remove_image">
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" data-confirm="Remove background image?">Remove</button>
                </form>
              </div>
              <?php endif; ?>
              <div class="admin-field">
                <label class="admin-field__label">Upload Image (JPG, PNG, WebP)</label>
                <input type="file" name="bg_image" accept="image/jpeg,image/png,image/webp"
                  style="padding:8px;border:1px dashed #CBD5E1;border-radius:8px;width:100%;font-size:13px;cursor:pointer;">
                <span class="admin-field__hint">Recommended: 1920×1080px or larger</span>
              </div>
            </div>

            <!-- Overlay -->
            <div class="admin-field">
              <label class="admin-field__label">Overlay Opacity: <span id="opacity-val"><?= $s['overlay_opacity'] ?></span>%</label>
              <input type="range" name="overlay_opacity" min="0" max="80" value="<?= (int)$s['overlay_opacity'] ?>"
                oninput="document.getElementById('opacity-val').textContent=this.value;updatePreview()"
                style="width:100%;accent-color:#2563EB;">
              <span class="admin-field__hint">Controls the white overlay on top of the background</span>
            </div>

            <!-- Blur -->
            <label class="admin-toggle">
              <input type="checkbox" name="blur_enabled" value="1" <?= $s['blur_enabled']==='1'?'checked':'' ?> onchange="updatePreview()">
              <span class="admin-toggle__track"></span>
              <span class="admin-toggle__label">Blur Background</span>
            </label>
          </div>
        </div>

        <!-- Quote -->
        <div class="admin-card">
          <div class="admin-card__header">
            <h2 class="admin-card__title">
              <svg viewBox="0 0 24 24"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
              Quote Text
            </h2>
          </div>
          <div class="admin-card__body">
            <div class="admin-field">
              <label class="admin-field__label">Motivational Quote</label>
              <textarea class="admin-field__textarea" name="quote_text" rows="3"
                oninput="document.getElementById('prev-quote').textContent=this.value"
                placeholder="Enter quote text…"><?= htmlspecialchars($s['quote_text']) ?></textarea>
              <span class="admin-field__hint">Displayed in the quote block on the login card</span>
            </div>
          </div>
        </div>

        <button type="submit" class="admin-btn admin-btn--primary" style="width:fit-content;">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save Login Settings
        </button>
      </div>

      <!-- Right: Live preview -->
      <div style="position:sticky;top:20px;">
        <div class="admin-card">
          <div class="admin-card__header">
            <h2 class="admin-card__title">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Live Preview
            </h2>
          </div>
          <div class="admin-card__body" style="padding:0;overflow:hidden;border-radius:0 0 10px 10px;">
            <div id="preview-bg" style="
              position:relative;
              min-height:320px;
              background:linear-gradient(135deg,#eef5ff 0%,#e6f0ff 100%);
              display:flex;align-items:center;justify-content:center;
              padding:16px;transition:all 0.3s;">
              <div style="position:absolute;inset:0;background:rgba(255,255,255,<?= $s['overlay_opacity']/100 ?>);pointer-events:none;" id="preview-overlay"></div>
              <!-- Mini login card preview -->
              <div style="background:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,0.12);padding:16px;width:200px;position:relative;z-index:1;font-family:sans-serif;">
                <div style="text-align:center;margin-bottom:10px;">
                  <div style="width:32px;height:32px;background:#EEF4FF;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" fill="none" stroke-width="2"><path d="M12 2L3 7v6c0 5.25 3.75 10.15 9 11.25C17.25 23.15 21 18.25 21 13V7L12 2z"/></svg>
                  </div>
                  <div style="font-size:9px;font-weight:600;color:#1E3A5F;">Sign in to</div>
                  <div style="font-size:10px;font-weight:700;color:#2563EB;">Clinical Registry</div>
                  <div style="width:24px;height:2px;background:#2563EB;margin:4px auto;"></div>
                </div>
                <div style="height:22px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:4px;margin-bottom:6px;"></div>
                <div style="height:22px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:4px;margin-bottom:8px;"></div>
                <div style="height:24px;background:linear-gradient(135deg,#3B82F6,#1D4ED8);border-radius:4px;margin-bottom:8px;"></div>
                <div style="background:#EEF4FF;border-radius:4px;padding:6px;font-size:7px;color:#1E3A5F;line-height:1.4;">
                  "<span id="prev-quote"><?= htmlspecialchars(substr($s['quote_text'],0,60)) ?></span>"
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </form>
</div>

<script>
function updateModeUI() {
  var isImage = document.querySelector('[name="bg_mode"][value="image"]').checked;
  document.getElementById('image-upload-section').style.display = isImage ? 'block' : 'none';
  document.getElementById('mode-gradient-wrap').style.borderColor = isImage ? '#E2E8F0' : '#2563EB';
  document.getElementById('mode-image-wrap').style.borderColor   = isImage ? '#2563EB' : '#E2E8F0';
}
function updatePreview() {
  var opacity = document.querySelector('[name="overlay_opacity"]').value / 100;
  document.getElementById('preview-overlay').style.background = 'rgba(255,255,255,' + opacity + ')';
}
</script>
