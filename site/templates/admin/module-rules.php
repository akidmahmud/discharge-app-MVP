<?php namespace ProcessWire;
/**
 * module-rules.php — Clinical rule engine
 */

$db = $database;
$notice = ''; $noticeType = 'success';

$conditionFields = [
    'diagnosis'       => 'Diagnosis contains',
    'age'             => 'Patient Age',
    'gender'          => 'Gender',
    'days_admitted'   => 'Days Admitted',
    'case_status'     => 'Case Status',
    'consultant'      => 'Consultant',
    'department'      => 'Department',
    'operation_done'  => 'Operation Done',
];
$operators = [
    'contains'    => 'contains',
    'equals'      => 'equals',
    'not_equals'  => 'does not equal',
    'greater_than'=> 'greater than',
    'less_than'   => 'less than',
];
$actionTypes = [
    'require_field'   => 'Make field mandatory',
    'hide_field'      => 'Hide field',
    'flag_alert'      => 'Show alert message',
    'auto_fill'       => 'Auto-fill value',
    'notify_user'     => 'Notify user',
];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_rule') {
            $id    = $sanitizer->int($input->post->id);
            $cf    = $sanitizer->name($input->post->condition_field);
            $op    = $sanitizer->name($input->post->operator);
            $cv    = $sanitizer->text($input->post->condition_value);
            $at    = $sanitizer->name($input->post->action_type);
            $av    = $sanitizer->text($input->post->action_value);
            $actv  = 1;

            if (!$cf || !$op || !$cv || !$at) {
                $notice = 'All condition and action fields are required.'; $noticeType = 'error';
            } else {
                if ($id) {
                    $stmt = $db->prepare("UPDATE admin_rules SET condition_field=?,operator=?,condition_value=?,action_type=?,action_value=?,is_active=? WHERE id=?");
                    $stmt->execute([$cf,$op,$cv,$at,$av,$actv,$id]);
                    adminLog($db,$user,'rules','update',$id,null,null,"IF {$cf} {$op} {$cv} THEN {$at}");
                } else {
                    $stmt = $db->prepare("INSERT INTO admin_rules (condition_field,operator,condition_value,action_type,action_value,is_active) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$cf,$op,$cv,$at,$av,$actv]);
                    adminLog($db,$user,'rules','create',$db->lastInsertId(),null,null,"IF {$cf} {$op} {$cv} THEN {$at}");
                }
                $session->redirect('/admin-panel/?module=rules&saved=1');
            }
        }

        if ($action === 'delete_rule') {
            $id = $sanitizer->int($input->post->id);
            $db->prepare("DELETE FROM admin_rules WHERE id=?")->execute([$id]);
            adminLog($db,$user,'rules','delete',$id,null,null,null);
            $session->redirect('/admin-panel/?module=rules&deleted=1');
        }

        if ($action === 'toggle_rule') {
            $id = $sanitizer->int($input->post->id);
            $db->prepare("UPDATE admin_rules SET is_active=1-is_active WHERE id=?")->execute([$id]);
            $session->redirect('/admin-panel/?module=rules&saved=1');
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$rules = [];
try {
    $rules = $db->query("SELECT * FROM admin_rules ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { $notice = 'DB not set up. Run /api/admin-setup/ first.'; $noticeType = 'error'; }

$editId  = $sanitizer->int($input->get->edit);
$editRow = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM admin_rules WHERE id=?");
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch(\PDO::FETCH_ASSOC);
}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Rule Engine</h1>
      <p class="admin-module__subtitle">Automated IF-THEN rules applied during case documentation</p>
    </div>
    <button class="admin-btn admin-btn--primary" data-modal-open="modal-rule">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Rule
    </button>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>" data-auto-dismiss>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <div class="admin-card">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Condition (IF)</th><th>Action (THEN)</th><th>Status</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rules)): ?>
        <tr><td colspan="6"><div class="admin-empty">No rules defined yet. Add your first rule.</div></td></tr>
        <?php else: ?>
        <?php foreach ($rules as $r): ?>
        <tr>
          <td style="color:#94A3B8;font-size:12px;"><?= (int)$r['id'] ?></td>
          <td>
            <span class="badge badge--blue" style="margin-right:4px;"><?= htmlspecialchars($conditionFields[$r['condition_field']] ?? $r['condition_field']) ?></span>
            <span style="font-size:12px;color:#64748B;"><?= htmlspecialchars($operators[$r['operator']] ?? $r['operator']) ?>
              "<strong><?= htmlspecialchars($r['condition_value']) ?></strong>"</span>
          </td>
          <td>
            <span class="badge badge--amber" style="margin-right:4px;"><?= htmlspecialchars($actionTypes[$r['action_type']] ?? $r['action_type']) ?></span>
            <?php if ($r['action_value']): ?>
            <span style="font-size:12px;color:#64748B;">→ <?= htmlspecialchars($r['action_value']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $r['is_active']?'badge--green':'badge--gray' ?>">
              <?= $r['is_active']?'Active':'Inactive' ?>
            </span>
          </td>
          <td style="color:#94A3B8;font-size:12px;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          <td>
            <div class="td-actions">
              <a href="/admin-panel/?module=rules&edit=<?= (int)$r['id'] ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form method="post" action="/admin-panel/?module=rules" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="toggle_rule">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <?= $r['is_active']?'Disable':'Enable' ?>
                </button>
              </form>
              <form method="post" action="/admin-panel/?module=rules" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="delete_rule">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" data-confirm="Delete this rule?">Delete</button>
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

<!-- Add / Edit Rule Modal -->
<div class="admin-modal-overlay <?= $editRow ? 'is-open' : '' ?>" id="modal-rule">
  <div class="admin-modal" style="max-width:600px;">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title"><?= $editRow ? 'Edit Rule' : 'Add Rule' ?></h3>
      <button class="admin-modal__close" data-modal-close="modal-rule">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <form class="admin-form" method="post" action="/admin-panel/?module=rules">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="save_rule">
        <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">

        <div style="background:#F8FAFF;border:1px solid #E2E8F0;border-radius:8px;padding:16px;margin-bottom:16px;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#64748B;margin-bottom:10px;">IF Condition</div>
          <div class="admin-form-grid" style="gap:10px;">
            <div class="admin-field">
              <label class="admin-field__label">Field</label>
              <select class="admin-field__select" name="condition_field" required>
                <option value="">— Select field —</option>
                <?php foreach ($conditionFields as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($editRow['condition_field']??'')===$val?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field">
              <label class="admin-field__label">Operator</label>
              <select class="admin-field__select" name="operator" required>
                <?php foreach ($operators as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($editRow['operator']??'')===$val?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field admin-field--full">
              <label class="admin-field__label">Value</label>
              <input class="admin-field__input" type="text" name="condition_value" required
                value="<?= htmlspecialchars($editRow['condition_value'] ?? '') ?>" placeholder="e.g. Appendicitis, 60, Male">
            </div>
          </div>
        </div>

        <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:16px;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#92400E;margin-bottom:10px;">THEN Action</div>
          <div class="admin-form-grid" style="gap:10px;">
            <div class="admin-field">
              <label class="admin-field__label">Action</label>
              <select class="admin-field__select" name="action_type" required>
                <option value="">— Select action —</option>
                <?php foreach ($actionTypes as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($editRow['action_type']??'')===$val?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field">
              <label class="admin-field__label">Value <span style="color:#94A3B8;">(optional)</span></label>
              <input class="admin-field__input" type="text" name="action_value"
                value="<?= htmlspecialchars($editRow['action_value'] ?? '') ?>" placeholder="e.g. field name, message…">
            </div>
          </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
          <a href="/admin-panel/?module=rules" class="admin-btn admin-btn--ghost">Cancel</a>
          <button type="submit" class="admin-btn admin-btn--primary"><?= $editRow ? 'Save Rule' : 'Add Rule' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
