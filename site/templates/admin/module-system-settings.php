<?php namespace ProcessWire;
/**
 * module-system-settings.php — Hospital info, branding, timezone, UI preferences
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── Ensure table ──────────────────────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS admin_system_settings (
        setting_key   VARCHAR(80)  NOT NULL PRIMARY KEY,
        setting_value TEXT         NOT NULL DEFAULT '',
        updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (\Exception $e) {}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action = $sanitizer->name($input->post->action ?? '');

    if ($action === 'save_system_settings') {
        $fields = [
            'hospital_name'    => $sanitizer->text($input->post->hospital_name),
            'hospital_address' => $sanitizer->textarea($input->post->hospital_address),
            'hospital_phone'   => $sanitizer->text($input->post->hospital_phone),
            'hospital_email'   => $sanitizer->email($input->post->hospital_email),
            'department'       => $sanitizer->text($input->post->department),
            'timezone'         => $sanitizer->text($input->post->timezone),
            'date_format'      => in_array($input->post->date_format, ['d M Y','d/m/Y','Y-m-d','m/d/Y']) ? $input->post->date_format : 'd M Y',
            'theme_color'      => preg_match('/^#[0-9a-fA-F]{6}$/', $input->post->theme_color ?? '') ? $input->post->theme_color : '#2563EB',
            'font_size'        => in_array($input->post->font_size, ['small','normal','large']) ? $input->post->font_size : 'normal',
            'items_per_page'   => (string)max(10, min(200, (int)$input->post->items_per_page)),
            'pdf_header_text'  => $sanitizer->text($input->post->pdf_header_text),
            'pdf_footer_text'  => $sanitizer->text($input->post->pdf_footer_text),
            'app_title'        => $sanitizer->text($input->post->app_title),
        ];

        // Logo upload
        $logoUrl = $sanitizer->text($input->post->existing_logo);
        if (!empty($_FILES['hospital_logo']['name']) && $_FILES['hospital_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $config->paths->assets . 'files/branding/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['hospital_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
                $fname = 'logo-' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['hospital_logo']['tmp_name'], $uploadDir . $fname)) {
                    $logoUrl = $config->urls->assets . 'files/branding/' . $fname;
                }
            }
        }
        $fields['hospital_logo'] = $logoUrl;

        $stmt = $db->prepare("INSERT INTO admin_system_settings (setting_key, setting_value)
            VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach ($fields as $k => $v) $stmt->execute([$k, $v]);

        adminLog($db, $user, 'system-settings', 'save', null, null, null, 'updated');
        $session->redirect('/admin-panel/?module=system-settings&saved=1');
    }

    if ($sanitizer->name($input->post->action) === 'remove_logo') {
        $db->prepare("INSERT INTO admin_system_settings (setting_key,setting_value) VALUES ('hospital_logo','')
            ON DUPLICATE KEY UPDATE setting_value=''")->execute();
        $session->redirect('/admin-panel/?module=system-settings&saved=1');
    }
}

// ── Load settings ─────────────────────────────────────────────────────────────
$s = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM admin_system_settings")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $s[$row['setting_key']] = $row['setting_value'];
} catch (\Exception $e) {}

$s += [
    'hospital_name'    => "Dr. Tawfiq's Clinical Registry",
    'hospital_address' => '',
    'hospital_phone'   => '',
    'hospital_email'   => '',
    'department'       => '',
    'timezone'         => 'Asia/Dhaka',
    'date_format'      => 'd M Y',
    'theme_color'      => '#2563EB',
    'font_size'        => 'normal',
    'items_per_page'   => '25',
    'pdf_header_text'  => '',
    'pdf_footer_text'  => '',
    'app_title'        => "Dr. Tawfiq's Clinical Registry",
    'hospital_logo'    => '',
];

$saved = $input->get->int('saved') === 1;

// Available timezones (subset)
$timezones = ['Asia/Dhaka','Asia/Kolkata','Asia/Dubai','Asia/Karachi','Asia/Singapore',
    'Asia/Tokyo','Europe/London','Europe/Berlin','America/New_York','America/Chicago',
    'America/Los_Angeles','America/Sao_Paulo','Africa/Cairo','Africa/Lagos','UTC'];
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">System Settings</h1>
      <p class="admin-module__subtitle">Hospital identity, branding, and application preferences</p>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="admin-alert admin-alert--success">Settings saved successfully.</div>
  <?php endif; ?>

  <form method="post" action="/admin-panel/?module=system-settings" enctype="multipart/form-data">
    <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
    <input type="hidden" name="action" value="save_system_settings">
    <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($s['hospital_logo']) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

      <!-- ── Hospital Identity ───────────────────────────────────── -->
      <div class="admin-card">
        <div class="admin-card__header">
          <h2 class="admin-card__title">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Hospital Identity
          </h2>
        </div>
        <div class="admin-card__body" style="display:flex;flex-direction:column;gap:14px;">

          <div class="admin-field">
            <label class="admin-field__label">Hospital / Clinic Name</label>
            <input class="admin-field__input" type="text" name="hospital_name"
              value="<?= htmlspecialchars($s['hospital_name']) ?>" placeholder="e.g. City General Hospital">
          </div>

          <div class="admin-field">
            <label class="admin-field__label">Department</label>
            <input class="admin-field__input" type="text" name="department"
              value="<?= htmlspecialchars($s['department']) ?>" placeholder="e.g. Internal Medicine">
          </div>

          <div class="admin-field">
            <label class="admin-field__label">Address</label>
            <textarea class="admin-field__textarea" name="hospital_address" rows="2"
              placeholder="Street, City, Country"><?= htmlspecialchars($s['hospital_address']) ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="admin-field">
              <label class="admin-field__label">Phone</label>
              <input class="admin-field__input" type="tel" name="hospital_phone"
                value="<?= htmlspecialchars($s['hospital_phone']) ?>" placeholder="+880 …">
            </div>
            <div class="admin-field">
              <label class="admin-field__label">Email</label>
              <input class="admin-field__input" type="email" name="hospital_email"
                value="<?= htmlspecialchars($s['hospital_email']) ?>" placeholder="admin@hospital.com">
            </div>
          </div>

          <!-- Logo -->
          <div class="admin-field">
            <label class="admin-field__label">Hospital Logo</label>
            <?php if ($s['hospital_logo']): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:10px;">
              <img src="<?= htmlspecialchars($s['hospital_logo']) ?>" style="height:40px;max-width:100px;object-fit:contain;">
              <span style="font-size:13px;color:#374151;flex:1;">Current logo</span>
              <button type="submit" name="action" value="remove_logo" class="admin-btn admin-btn--danger admin-btn--sm"
                data-confirm="Remove logo?">Remove</button>
            </div>
            <?php endif; ?>
            <input type="file" name="hospital_logo" accept="image/jpeg,image/png,image/webp,image/svg+xml"
              style="padding:8px;border:1px dashed #CBD5E1;border-radius:8px;width:100%;font-size:13px;cursor:pointer;">
            <span class="admin-field__hint">PNG or SVG recommended. Max 2MB.</span>
          </div>
        </div>
      </div>

      <!-- ── Application Preferences ────────────────────────────── -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="admin-card">
          <div class="admin-card__header">
            <h2 class="admin-card__title">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 0 0 4.93 19.07M4.93 4.93a10 10 0 0 0 14.14 14.14"/></svg>
              Application Preferences
            </h2>
          </div>
          <div class="admin-card__body" style="display:flex;flex-direction:column;gap:14px;">

            <div class="admin-field">
              <label class="admin-field__label">Application Title</label>
              <input class="admin-field__input" type="text" name="app_title"
                value="<?= htmlspecialchars($s['app_title']) ?>" placeholder="Shown in browser tab">
            </div>

            <div class="admin-field">
              <label class="admin-field__label">Timezone</label>
              <select class="admin-field__select" name="timezone">
                <?php foreach ($timezones as $tz): ?>
                <option value="<?= htmlspecialchars($tz) ?>" <?= $s['timezone']===$tz?'selected':'' ?>><?= htmlspecialchars($tz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="admin-field">
              <label class="admin-field__label">Date Format</label>
              <select class="admin-field__select" name="date_format">
                <option value="d M Y" <?= $s['date_format']==='d M Y'?'selected':'' ?>>12 Apr 2025 (d M Y)</option>
                <option value="d/m/Y" <?= $s['date_format']==='d/m/Y'?'selected':'' ?>>12/04/2025 (d/m/Y)</option>
                <option value="Y-m-d" <?= $s['date_format']==='Y-m-d'?'selected':'' ?>>2025-04-12 (Y-m-d)</option>
                <option value="m/d/Y" <?= $s['date_format']==='m/d/Y'?'selected':'' ?>>04/12/2025 (m/d/Y)</option>
              </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="admin-field">
                <label class="admin-field__label">UI Font Size</label>
                <select class="admin-field__select" name="font_size">
                  <option value="small"  <?= $s['font_size']==='small'? 'selected':'' ?>>Small</option>
                  <option value="normal" <?= $s['font_size']==='normal'?'selected':'' ?>>Normal</option>
                  <option value="large"  <?= $s['font_size']==='large'? 'selected':'' ?>>Large</option>
                </select>
              </div>
              <div class="admin-field">
                <label class="admin-field__label">Accent Color</label>
                <div style="display:flex;align-items:center;gap:8px;">
                  <input type="color" name="theme_color" value="<?= htmlspecialchars($s['theme_color']) ?>"
                    style="width:40px;height:36px;border:1px solid #E2E8F0;border-radius:6px;padding:2px;cursor:pointer;">
                  <input class="admin-field__input" type="text" id="theme-hex"
                    value="<?= htmlspecialchars($s['theme_color']) ?>" readonly
                    style="width:80px;font-family:monospace;font-size:12px;">
                </div>
              </div>
            </div>

            <div class="admin-field">
              <label class="admin-field__label">Items Per Page (lists)</label>
              <input class="admin-field__input" type="number" name="items_per_page"
                value="<?= (int)$s['items_per_page'] ?>" min="10" max="200" style="width:100px;">
            </div>
          </div>
        </div>

        <!-- PDF settings -->
        <div class="admin-card">
          <div class="admin-card__header">
            <h2 class="admin-card__title">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              PDF Header & Footer
            </h2>
          </div>
          <div class="admin-card__body" style="display:flex;flex-direction:column;gap:14px;">
            <div class="admin-field">
              <label class="admin-field__label">PDF Header Text</label>
              <input class="admin-field__input" type="text" name="pdf_header_text"
                value="<?= htmlspecialchars($s['pdf_header_text']) ?>"
                placeholder="e.g. CONFIDENTIAL — FOR CLINICAL USE ONLY">
              <span class="admin-field__hint">Appears at top of every generated PDF</span>
            </div>
            <div class="admin-field">
              <label class="admin-field__label">PDF Footer Text</label>
              <input class="admin-field__input" type="text" name="pdf_footer_text"
                value="<?= htmlspecialchars($s['pdf_footer_text']) ?>"
                placeholder="e.g. This document is auto-generated. Verify with treating physician.">
              <span class="admin-field__hint">Appears at bottom of every generated PDF</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="margin-top:20px;">
      <button type="submit" class="admin-btn admin-btn--primary">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Save System Settings
      </button>
    </div>
  </form>
</div>

<script>
document.querySelector('[name="theme_color"]')?.addEventListener('input', function() {
  document.getElementById('theme-hex').value = this.value;
});
</script>
