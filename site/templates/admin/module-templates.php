<?php namespace ProcessWire;
/**
 * module-templates.php — Discharge text templates
 */

$db = $database;
$notice = ''; $noticeType = 'success';
$validTypes = ['diagnosis','history','examination','operation-note','medication','advice','general'];
$typeAliases = ['operation' => 'operation-note', 'operation_note' => 'operation-note'];
$normalizeType = function ($value) use ($typeAliases) {
    $normalized = strtolower(trim((string) $value));
    $normalized = str_replace('_', '-', $normalized);
    return $typeAliases[$normalized] ?? $normalized;
};
$sanitizeFieldKey = function ($value): string {
    return preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $value)));
};

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_template') {
            $id    = $sanitizer->int($input->post->id);
            $submittedType = $normalizeType($input->post->type);
            $type  = in_array($submittedType, $validTypes, true) ? $submittedType : 'diagnosis';
            $fieldKey = $sanitizeFieldKey($input->post->field_key);
            $title = $sanitizer->text($input->post->title);
            $body  = $sanitizer->textarea($input->post->body);

            if (!$title || !$body) {
                $notice = 'Title and body are required.'; $noticeType = 'error';
            } else {
                if ($id) {
                    $stmt = $db->prepare("UPDATE admin_discharge_templates SET type=?,field_key=?,title=?,body=? WHERE id=?");
                    $stmt->execute([$type,$fieldKey,$title,$body,$id]);
                    adminLog($db,$user,'templates','update',$id,'title',null,$title);
                } else {
                    $stmt = $db->prepare("INSERT INTO admin_discharge_templates (type,field_key,title,body,created_by) VALUES (?,?,?,?,?)");
                    $stmt->execute([$type,$fieldKey,$title,$body,$user->id]);
                    adminLog($db,$user,'templates','create',$db->lastInsertId(),'title',null,$title);
                }
                $redirect = '/admin-panel/?module=templates&tab='.$type.'&saved=1';
                if ($fieldKey !== '') {
                    $redirect .= '&field_key=' . rawurlencode($fieldKey);
                }
                $session->redirect($redirect);
            }
        }

        if ($action === 'delete_template') {
            $id = $sanitizer->int($input->post->id);
            $stmt = $db->prepare("DELETE FROM admin_discharge_templates WHERE id=?");
            $stmt->execute([$id]);
            adminLog($db,$user,'templates','delete',$id,null,null,null);
            $session->redirect('/admin-panel/?module=templates&deleted=1');
        }

        if ($action === 'toggle_status') {
            $id = $sanitizer->int($input->post->id);
            $db->prepare("UPDATE admin_discharge_templates SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            $session->redirect('/admin-panel/?module=templates&saved=1');
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$allTemplates = [];
try {
    $stmt = $db->query("SELECT * FROM admin_discharge_templates ORDER BY type, field_key, title");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $row['type'] = $normalizeType($row['type']);
        $allTemplates[$row['type']][] = $row;
    }
} catch (\Exception $e) { $notice = 'DB table not found. Run /api/admin-setup/ first.'; $noticeType = 'error'; }

$activeTab = $normalizeType($input->get->tab ?? 'diagnosis');
if (!in_array($activeTab, $validTypes, true)) $activeTab = 'diagnosis';
$requestedType = $normalizeType($input->get->type ?? $activeTab);
if (!in_array($requestedType, $validTypes, true)) $requestedType = $activeTab;
$requestedFieldKey = $sanitizeFieldKey($input->get->field_key ?? '');
$createRequested = (int) $input->get->create === 1;

$editId = $sanitizer->int($input->get->edit);
$editRow = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM admin_discharge_templates WHERE id=?");
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (is_array($editRow)) {
        $editRow['type'] = $normalizeType($editRow['type']);
        $editRow['field_key'] = $sanitizeFieldKey($editRow['field_key'] ?? '');
    }
}

$typeLabels = [
    'diagnosis' => 'Diagnosis',
    'history' => 'History',
    'examination' => 'Examination',
    'operation-note' => 'Operation Note',
    'medication' => 'Medication',
    'advice' => 'Advice',
    'general' => 'General',
];
$currentFormType = $editRow['type'] ?? $requestedType;
$currentFormFieldKey = $editRow['field_key'] ?? $requestedFieldKey;
$suggestedTitle = ($typeLabels[$currentFormType] ?? ucfirst(str_replace('-', ' ', $currentFormType)))
    . ($currentFormFieldKey !== '' ? (' - ' . ucwords(str_replace('_', ' ', $currentFormFieldKey))) : ' Template');
$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Templates</h1>
      <p class="admin-module__subtitle">Pre-built discharge text templates by section type</p>
    </div>
    <button class="admin-btn admin-btn--primary" data-modal-open="modal-template">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create Template
    </button>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>" data-auto-dismiss>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <div class="admin-tabs">
    <?php foreach ($validTypes as $t): ?>
    <button class="admin-tab <?= $activeTab===$t?'is-active':'' ?>" data-tab="tab-<?= $t ?>">
      <?= $typeLabels[$t] ?>
      <?php if (!empty($allTemplates[$t])): ?>
      <span style="margin-left:5px;background:#EEF4FF;color:#2563EB;font-size:10px;font-weight:700;padding:1px 5px;border-radius:10px;">
        <?= count($allTemplates[$t]) ?>
      </span>
      <?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($validTypes as $t): ?>
  <div class="admin-tab-panel <?= $activeTab===$t?'is-active':'' ?>" id="tab-<?= $t ?>">
    <div class="admin-card">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>Title</th><th>Preview</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php if (empty($allTemplates[$t])): ?>
          <tr><td colspan="5"><div class="admin-empty">No <?= $typeLabels[$t] ?> templates yet. Create your first one.</div></td></tr>
          <?php else: ?>
          <?php foreach ($allTemplates[$t] as $tpl): ?>
          <tr>
            <td style="color:#94A3B8;font-size:12px;"><?= (int)$tpl['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars($tpl['title']) ?></strong>
              <?php if (!empty($tpl['field_key'])): ?>
              <div style="color:#94A3B8;font-size:11px;margin-top:4px;"><?= htmlspecialchars($tpl['field_key']) ?></div>
              <?php endif; ?>
            </td>
            <td style="color:#64748B;font-size:12px;max-width:300px;">
              <?= htmlspecialchars(substr(strip_tags($tpl['body']), 0, 80)) ?>…
            </td>
            <td>
              <span class="badge <?= $tpl['status']==='active'?'badge--green':'badge--gray' ?>">
                <?= ucfirst($tpl['status']) ?>
              </span>
            </td>
            <td>
              <div class="td-actions">
                <a href="/admin-panel/?module=templates&tab=<?= $t ?>&edit=<?= (int)$tpl['id'] ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form method="post" action="/admin-panel/?module=templates" style="display:inline;">
                  <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                  <input type="hidden" name="action" value="delete_template">
                  <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                    data-confirm="Delete this template?">Delete</button>
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
  </div>
  <?php endforeach; ?>

</div>

<!-- Create / Edit Modal -->
<div class="admin-modal-overlay <?= ($editRow || $createRequested) ? 'is-open' : '' ?>" id="modal-template">
  <div class="admin-modal" style="max-width:620px;">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title"><?= $editRow ? 'Edit Template' : 'Create Template' ?></h3>
      <button class="admin-modal__close" data-modal-close="modal-template">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <form class="admin-form" method="post" action="/admin-panel/?module=templates">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="save_template">
        <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">
        <input type="hidden" name="field_key" value="<?= htmlspecialchars($currentFormFieldKey) ?>">
        <div class="admin-form-grid">
          <div class="admin-field">
            <label class="admin-field__label">Type *</label>
            <select class="admin-field__select" name="type" required>
              <?php foreach ($validTypes as $t): ?>
              <option value="<?= $t ?>" <?= $currentFormType===$t?'selected':'' ?>><?= $typeLabels[$t] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Title *</label>
            <input class="admin-field__input" type="text" name="title" required
              value="<?= htmlspecialchars($editRow['title'] ?? '') ?>" placeholder="<?= htmlspecialchars($suggestedTitle) ?>">
          </div>
          <?php if ($currentFormFieldKey !== ''): ?>
          <div class="admin-field admin-field--full">
            <label class="admin-field__label">Target Field</label>
            <input class="admin-field__input" type="text" value="<?= htmlspecialchars($currentFormFieldKey) ?>" readonly>
          </div>
          <?php endif; ?>
          <div class="admin-field admin-field--full">
            <label class="admin-field__label">Body *</label>
            <textarea class="admin-field__textarea" name="body" required rows="8"
              placeholder="Template text content…"><?= htmlspecialchars($editRow['body'] ?? '') ?></textarea>
          </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
          <a href="/admin-panel/?module=templates" class="admin-btn admin-btn--ghost">Cancel</a>
          <button type="submit" class="admin-btn admin-btn--primary"><?= $editRow ? 'Save Changes' : 'Create Template' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
