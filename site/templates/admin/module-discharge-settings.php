<?php namespace ProcessWire;
/**
 * module-discharge-settings.php — PDF and section settings
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_discharge_settings') {
            $settings = [
                'show_diagnosis'       => $sanitizer->int($input->post->show_diagnosis)       ? '1' : '0',
                'show_history'         => $sanitizer->int($input->post->show_history)         ? '1' : '0',
                'show_examination'     => $sanitizer->int($input->post->show_examination)     ? '1' : '0',
                'show_investigations'  => $sanitizer->int($input->post->show_investigations)  ? '1' : '0',
                'show_operation_note'  => $sanitizer->int($input->post->show_operation_note)  ? '1' : '0',
                'show_hospital_course' => $sanitizer->int($input->post->show_hospital_course) ? '1' : '0',
                'show_medications'     => $sanitizer->int($input->post->show_medications)     ? '1' : '0',
                'show_advice'          => $sanitizer->int($input->post->show_advice)          ? '1' : '0',
                'show_admission'       => $sanitizer->int($input->post->show_admission)       ? '1' : '0',
                'show_ot_plan'         => $sanitizer->int($input->post->show_ot_plan)         ? '1' : '0',
                'show_condition'       => $sanitizer->int($input->post->show_condition)       ? '1' : '0',
                'pdf_header'           => $sanitizer->text($input->post->pdf_header),
                'pdf_footer'           => $sanitizer->text($input->post->pdf_footer),
                'pdf_font_size'        => (string)(int)$sanitizer->int($input->post->pdf_font_size),
                'pdf_margin'           => (string)(int)$sanitizer->int($input->post->pdf_margin),
            ];

            $stmt = $db->prepare("INSERT INTO admin_discharge_settings (setting_key, setting_value)
                VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            adminLog($db,$user,'discharge-settings','save',null,null,null,'settings updated');
            $session->redirect('/admin-panel/?module=discharge-settings&saved=1');
        }
    }
}

// ── Load settings ─────────────────────────────────────────────────────────────
$s = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM admin_discharge_settings")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $s[$row['setting_key']] = $row['setting_value'];
} catch (\Exception $e) { $notice = 'DB not set up. Run /api/admin-setup/ first.'; $noticeType = 'error'; }

// Defaults
$s += [
    'show_diagnosis'       => '1', 'show_history'      => '1',
    'show_examination'     => '1', 'show_investigations'=> '1',
    'show_operation_note'  => '1', 'show_hospital_course'=>'1',
    'show_medications'     => '1', 'show_advice'        => '1',
    'show_admission'       => '1', 'show_ot_plan'       => '1',
    'show_condition'       => '1',
    'pdf_header'           => 'Dr. Md. Tawfiq Alam Siddique',
    'pdf_footer'           => 'Clinical Registry — Confidential',
    'pdf_font_size'        => '12',
    'pdf_margin'           => '16',
];

$sections = [
    'show_diagnosis'       => 'Diagnosis',
    'show_history'         => 'History',
    'show_examination'     => 'Examination',
    'show_investigations'  => 'Investigations',
    'show_operation_note'  => 'Operation Note',
    'show_hospital_course' => 'Hospital Course',
    'show_medications'     => 'Medications',
    'show_advice'          => 'Advice &amp; Follow-up',
    'show_admission'       => 'Admission',
    'show_ot_plan'         => 'OT Plan / Surgery Plan',
    'show_condition'       => 'Condition at Discharge',
];

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Discharge Settings</h1>
      <p class="admin-module__subtitle">Configure which sections appear and control PDF output options</p>
    </div>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>" data-auto-dismiss>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <form method="post" action="/admin-panel/?module=discharge-settings" class="admin-form">
    <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
    <input type="hidden" name="action" value="save_discharge_settings">

    <!-- Section toggles -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><polyline points="22 6 9 17 4 12"/></svg>
          Discharge Sections
        </h2>
        <span style="font-size:12px;color:#94A3B8;">Toggle which sections appear in discharge summaries</span>
      </div>
      <div class="admin-card__body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <?php foreach ($sections as $key => $label): ?>
          <label class="admin-toggle" style="padding:12px 16px;border:1px solid #E2E8F0;border-radius:8px;background:#FAFBFF;">
            <input type="hidden" name="<?= $key ?>" value="0">
            <input type="checkbox" name="<?= $key ?>" value="1" <?= $s[$key]==='1'?'checked':'' ?>>
            <span class="admin-toggle__track"></span>
            <span class="admin-toggle__label"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- PDF options -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          PDF Output
        </h2>
      </div>
      <div class="admin-card__body">
        <div class="admin-form-grid">
          <div class="admin-field admin-field--full">
            <label class="admin-field__label">PDF Header Text</label>
            <input class="admin-field__input" type="text" name="pdf_header"
              value="<?= htmlspecialchars($s['pdf_header']) ?>" placeholder="Hospital / Doctor name">
            <span class="admin-field__hint">Appears at the top of every discharge PDF page</span>
          </div>
          <div class="admin-field admin-field--full">
            <label class="admin-field__label">PDF Footer Text</label>
            <input class="admin-field__input" type="text" name="pdf_footer"
              value="<?= htmlspecialchars($s['pdf_footer']) ?>" placeholder="Confidentiality notice…">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Font Size (pt)</label>
            <select class="admin-field__select" name="pdf_font_size">
              <?php foreach ([10,11,12,13,14] as $fs): ?>
              <option value="<?= $fs ?>" <?= $s['pdf_font_size']==(string)$fs?'selected':'' ?>><?= $fs ?>pt</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Page Margin (mm)</label>
            <select class="admin-field__select" name="pdf_margin">
              <?php foreach ([10,12,14,16,18,20] as $mg): ?>
              <option value="<?= $mg ?>" <?= $s['pdf_margin']==(string)$mg?'selected':'' ?>><?= $mg ?>mm</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview strip -->
    <div class="admin-card" style="border:2px dashed #E2E8F0;">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          PDF Preview
        </h2>
      </div>
      <div class="admin-card__body">
        <div id="pdf-preview" style="border:1px solid #E2E8F0;border-radius:8px;padding:20px;background:#fff;font-family:serif;">
          <div style="text-align:center;border-bottom:2px solid #1E3A5F;padding-bottom:10px;margin-bottom:12px;">
            <strong id="prev-header" style="font-size:14px;color:#1E3A5F;"><?= htmlspecialchars($s['pdf_header']) ?></strong>
          </div>
          <div style="color:#374151;font-size:12px;line-height:1.7;">
            <strong>DISCHARGE SUMMARY</strong><br>
            <em>Sections shown: </em>
            <span id="prev-sections"><?= implode(', ', array_map(fn($k) => str_replace('show_','',$k), array_keys(array_filter($s, fn($v) => $v==='1')))) ?></span>
          </div>
          <div style="text-align:center;border-top:1px solid #E2E8F0;padding-top:8px;margin-top:12px;font-size:10px;color:#94A3B8;">
            <span id="prev-footer"><?= htmlspecialchars($s['pdf_footer']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Save -->
    <div style="display:flex;gap:12px;align-items:center;">
      <button type="submit" class="admin-btn admin-btn--primary" style="min-width:140px;">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Save Settings
      </button>
      <span style="font-size:12px;color:#94A3B8;">These settings apply to all generated discharge documents.</span>
    </div>
  </form>
</div>

<script>
// Live preview update
(function() {
  var headerInput = document.querySelector('[name="pdf_header"]');
  var footerInput = document.querySelector('[name="pdf_footer"]');
  var prevHeader  = document.getElementById('prev-header');
  var prevFooter  = document.getElementById('prev-footer');
  var prevSections= document.getElementById('prev-sections');

  function updatePreview() {
    if (headerInput && prevHeader) prevHeader.textContent = headerInput.value || 'Header';
    if (footerInput && prevFooter) prevFooter.textContent = footerInput.value || '';
    // Update sections
    if (prevSections) {
      var shown = [];
      document.querySelectorAll('[name^="show_"]:checked').forEach(function(cb) {
        shown.push(cb.name.replace('show_', ''));
      });
      prevSections.textContent = shown.length ? shown.join(', ') : 'none';
    }
  }

  if (headerInput) headerInput.addEventListener('input', updatePreview);
  if (footerInput) footerInput.addEventListener('input', updatePreview);
  document.querySelectorAll('[name^="show_"]').forEach(function(cb) {
    cb.addEventListener('change', updatePreview);
  });
})();
</script>
