<?php namespace ProcessWire;
/**
 * module-workflow.php — Clinical workflow module config
 */

$db = $database;
$notice = ''; $noticeType = 'success';
$workflowDefinitions = include $config->paths->templates . 'includes/workflow-definitions.php';
$workflowHasFieldsConfig = false;

try {
    $workflowHasFieldsConfig = (bool) $db->query("SHOW COLUMNS FROM admin_workflow_config LIKE 'fields_config'")->fetch(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $workflowHasFieldsConfig = false;
}
if (!$workflowHasFieldsConfig) {
    try {
        $db->exec("ALTER TABLE `admin_workflow_config` ADD COLUMN `fields_config` JSON NULL AFTER `is_mandatory`");
        $workflowHasFieldsConfig = true;
    } catch (\Throwable $e) {}
}

try {
    $existingWorkflowRows = $db->query("SELECT module_name FROM admin_workflow_config")->fetchAll(\PDO::FETCH_COLUMN);
    $insertWorkflowRow = $db->prepare("INSERT INTO admin_workflow_config (module_name, label, is_enabled, is_mandatory, sort_order" . ($workflowHasFieldsConfig ? ", fields_config" : "") . ") VALUES (?,?,?,?,?" . ($workflowHasFieldsConfig ? ",?" : "") . ")");
    foreach ($workflowDefinitions as $moduleName => $moduleDefinition) {
        if (in_array($moduleName, $existingWorkflowRows, true)) {
            continue;
        }
        $params = [
            $moduleName,
            $moduleDefinition['label'],
            (int) ($moduleDefinition['default_enabled'] ?? 1),
            (int) ($moduleDefinition['default_mandatory'] ?? 0),
            (int) ($moduleDefinition['default_sort_order'] ?? 0),
        ];
        if ($workflowHasFieldsConfig) {
            $params[] = json_encode(['fields' => [], 'actions' => []]);
        }
        $insertWorkflowRow->execute($params);
    }
    $preopWorkflowRow = $db->query("SELECT id, sort_order FROM admin_workflow_config WHERE module_name='preop-print' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $operationWorkflowRow = $db->query("SELECT id, sort_order FROM admin_workflow_config WHERE module_name='operation-note' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    if ($preopWorkflowRow && $operationWorkflowRow && (int) $preopWorkflowRow['sort_order'] > (int) $operationWorkflowRow['sort_order']) {
        $updateWorkflowSort = $db->prepare("UPDATE admin_workflow_config SET sort_order=? WHERE id=?");
        $updateWorkflowSort->execute([(int) $operationWorkflowRow['sort_order'], (int) $preopWorkflowRow['id']]);
        $updateWorkflowSort->execute([(int) $preopWorkflowRow['sort_order'], (int) $operationWorkflowRow['id']]);
    }
} catch (\Throwable $e) {
    $notice = 'Workflow rows could not be initialized.'; $noticeType = 'error';
}

$buildDefaultFieldConfig = function (): array {
    return [
        'visible' => true,
        'editable' => true,
        'mandatory' => false,
    ];
};

$buildDefaultActionConfig = function (): array {
    return [
        'visible' => true,
        'enabled' => true,
    ];
};

$normalizeWorkflowConfig = function ($rawConfig) use ($buildDefaultFieldConfig, $buildDefaultActionConfig): array {
    $decoded = is_array($rawConfig) ? $rawConfig : json_decode((string) $rawConfig, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $fieldConfig = isset($decoded['fields']) && is_array($decoded['fields']) ? $decoded['fields'] : [];
    $actionConfig = isset($decoded['actions']) && is_array($decoded['actions']) ? $decoded['actions'] : [];

    return [
        'fields' => array_map(function ($config) use ($buildDefaultFieldConfig) {
            $defaults = $buildDefaultFieldConfig();
            return [
                'visible' => array_key_exists('visible', (array) $config) ? (bool) $config['visible'] : $defaults['visible'],
                'editable' => array_key_exists('editable', (array) $config) ? (bool) $config['editable'] : $defaults['editable'],
                'mandatory' => array_key_exists('mandatory', (array) $config) ? (bool) $config['mandatory'] : $defaults['mandatory'],
            ];
        }, $fieldConfig),
        'actions' => array_map(function ($config) use ($buildDefaultActionConfig) {
            $defaults = $buildDefaultActionConfig();
            return [
                'visible' => array_key_exists('visible', (array) $config) ? (bool) $config['visible'] : $defaults['visible'],
                'enabled' => array_key_exists('enabled', (array) $config) ? (bool) $config['enabled'] : $defaults['enabled'],
            ];
        }, $actionConfig),
    ];
};

// POST handler
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_workflow') {
            $order  = $sanitizer->text($input->post->order ?? '');
            $ids    = array_filter(array_map('intval', explode(',', $order)));
            $pos    = 1;

            foreach ($ids as $mid) {
                $db->prepare("UPDATE admin_workflow_config SET sort_order=? WHERE id=?")->execute([$pos++, $mid]);
            }

            $allRows = $db->query("SELECT id, module_name FROM admin_workflow_config")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($allRows as $row) {
                $mid = (int) $row['id'];
                $moduleName = (string) $row['module_name'];
                $enabled   = $sanitizer->int($input->post->{"enabled_{$mid}"}) ? 1 : 0;
                $mandatory = $sanitizer->int($input->post->{"mandatory_{$mid}"}) ? 1 : 0;

                $fieldsConfig = ['fields' => [], 'actions' => []];
                $moduleDefinition = $workflowDefinitions[$moduleName] ?? ['fields' => [], 'actions' => []];

                foreach (($moduleDefinition['fields'] ?? []) as $fieldKey => $fieldLabel) {
                    $safeFieldKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $fieldKey));
                    $fieldsConfig['fields'][$fieldKey] = [
                        'visible' => $sanitizer->int($input->post->{"field_visible_{$mid}_{$safeFieldKey}"}) === 1,
                        'editable' => $sanitizer->int($input->post->{"field_editable_{$mid}_{$safeFieldKey}"}) === 1,
                        'mandatory' => $sanitizer->int($input->post->{"field_mandatory_{$mid}_{$safeFieldKey}"}) === 1,
                    ];
                }

                foreach (($moduleDefinition['actions'] ?? []) as $actionKey => $actionLabel) {
                    $safeActionKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $actionKey));
                    $fieldsConfig['actions'][$actionKey] = [
                        'visible' => $sanitizer->int($input->post->{"action_visible_{$mid}_{$safeActionKey}"}) === 1,
                        'enabled' => $sanitizer->int($input->post->{"action_enabled_{$mid}_{$safeActionKey}"}) === 1,
                    ];
                }

                if ($workflowHasFieldsConfig) {
                    $db->prepare("UPDATE admin_workflow_config SET is_enabled=?, is_mandatory=?, fields_config=? WHERE id=?")
                        ->execute([$enabled, $mandatory, json_encode($fieldsConfig), $mid]);
                } else {
                    $db->prepare("UPDATE admin_workflow_config SET is_enabled=?,is_mandatory=? WHERE id=?")
                        ->execute([$enabled, $mandatory, $mid]);
                }
            }

            adminLog($db,$user,'workflow','save',null,null,null,'workflow updated');
            $session->redirect('/admin-panel/?module=workflow&saved=1');
        }
    }
}

// Data
$modules = [];
try {
    $stmt = $db->query("SELECT * FROM admin_workflow_config ORDER BY sort_order ASC");
    $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { $notice = 'DB not set up. Run /api/admin-setup/ first.'; $noticeType = 'error'; }

foreach ($modules as $index => $moduleRow) {
    $normalizedConfig = $normalizeWorkflowConfig($moduleRow['fields_config'] ?? null);
    $modules[$index]['fields_config_normalized'] = $normalizedConfig;
}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Workflow Configuration</h1>
      <p class="admin-module__subtitle">Enable modules, reorder them, and control field/action behavior from one place</p>
    </div>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>" data-auto-dismiss>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <form method="post" action="/admin-panel/?module=workflow" id="workflow-config-form">
    <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
    <input type="hidden" name="action" value="save_workflow">
    <input type="hidden" name="order" id="workflow-order"
      value="<?= htmlspecialchars(implode(',', array_column($modules, 'id'))) ?>">

    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Module Order
        </h2>
        <span style="font-size:12px;color:#94A3B8;">Drag rows to reorder</span>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width:40px;"></th>
              <th>Order</th>
              <th>Module</th>
              <th>Label</th>
              <th style="text-align:center;">Enabled</th>
              <th style="text-align:center;">Mandatory</th>
              <th style="text-align:center;">Configure</th>
            </tr>
          </thead>
          <tbody id="workflow-tbody">
          <?php if (empty($modules)): ?>
          <tr><td colspan="7"><div class="admin-empty">No modules configured. Run /api/admin-setup/ to seed defaults.</div></td></tr>
          <?php else: ?>
          <?php foreach ($modules as $i => $m): ?>
          <?php
            $moduleDefinition = $workflowDefinitions[$m['module_name']] ?? ['fields' => [], 'actions' => []];
            $fieldCount = count($moduleDefinition['fields'] ?? []);
            $actionCount = count($moduleDefinition['actions'] ?? []);
          ?>
          <tr data-draggable data-id="<?= (int)$m['id'] ?>"
              style="background:<?= $m['is_enabled']?'#fff':'#FAFBFF' ?>;">
            <td>
              <span class="drag-handle" title="Drag to reorder">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="5" r="1" fill="currentColor"/><circle cx="15" cy="5" r="1" fill="currentColor"/><circle cx="9" cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/><circle cx="9" cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/></svg>
              </span>
            </td>
            <td style="color:#94A3B8;font-size:13px;"><?= (int)$m['sort_order'] ?></td>
            <td><code style="font-size:12px;background:#F1F5F9;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($m['module_name']) ?></code></td>
            <td><strong><?= htmlspecialchars($m['label']) ?></strong></td>
            <td style="text-align:center;">
              <input type="hidden" name="enabled_<?= (int)$m['id'] ?>" value="0">
              <label class="admin-toggle" style="justify-content:center;">
                <input type="checkbox" name="enabled_<?= (int)$m['id'] ?>" value="1" <?= $m['is_enabled']?'checked':'' ?>>
                <span class="admin-toggle__track"></span>
              </label>
            </td>
            <td style="text-align:center;">
              <input type="hidden" name="mandatory_<?= (int)$m['id'] ?>" value="0">
              <label class="admin-toggle" style="justify-content:center;">
                <input type="checkbox" name="mandatory_<?= (int)$m['id'] ?>" value="1" <?= $m['is_mandatory']?'checked':'' ?>>
                <span class="admin-toggle__track"></span>
              </label>
            </td>
            <td style="text-align:center;">
              <button
                class="admin-btn admin-btn--ghost admin-btn--sm"
                type="button"
                data-modal-open="modal-configure-workflow-<?= (int) $m['id'] ?>">
                Configure Fields
              </button>
              <div style="font-size:11px;color:#94A3B8;margin-top:4px;"><?= $fieldCount ?> fields / <?= $actionCount ?> actions</div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:16px 20px;border-top:1px solid #F1F5F9;display:flex;gap:10px;align-items:center;">
        <button type="submit" class="admin-btn admin-btn--primary">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save Workflow
        </button>
        <span style="font-size:12px;color:#94A3B8;">The case view now reads module order, visibility, mandatory rules, and field/action control from this config.</span>
      </div>
    </div>
  </form>
</div>

<?php foreach ($modules as $m): ?>
<?php
  $moduleDefinition = $workflowDefinitions[$m['module_name']] ?? ['fields' => [], 'actions' => []];
  $currentConfig = $m['fields_config_normalized'] ?? ['fields' => [], 'actions' => []];
?>
<div class="admin-modal-overlay" id="modal-configure-workflow-<?= (int) $m['id'] ?>">
  <div class="admin-modal" style="max-width:760px;">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title">Configure <?= htmlspecialchars($m['label']) ?></h3>
      <button class="admin-modal__close" data-modal-close="modal-configure-workflow-<?= (int) $m['id'] ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <div class="layout-stack layout-stack--gap-4">
        <p style="margin:0;color:#64748B;font-size:13px;">These settings drive case-view rendering, editing, required flags, and action availability.</p>

        <div class="layout-stack layout-stack--gap-2">
          <h4 style="margin:0;">Fields</h4>
          <?php if (!empty($moduleDefinition['fields'])): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Field</th>
                  <th style="text-align:center;">Visible</th>
                  <th style="text-align:center;">Editable</th>
                  <th style="text-align:center;">Mandatory</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($moduleDefinition['fields'] as $fieldKey => $fieldLabel): ?>
                <?php
                  $safeFieldKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $fieldKey));
                  $fieldConfig = $currentConfig['fields'][$fieldKey] ?? ['visible' => true, 'editable' => true, 'mandatory' => false];
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($fieldLabel) ?></strong>
                    <div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars($fieldKey) ?></div>
                  </td>
                  <td style="text-align:center;">
                    <input form="workflow-config-form" type="hidden" name="field_visible_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="0">
                    <input form="workflow-config-form" type="checkbox" name="field_visible_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="1" <?= !empty($fieldConfig['visible']) ? 'checked' : '' ?>>
                  </td>
                  <td style="text-align:center;">
                    <input form="workflow-config-form" type="hidden" name="field_editable_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="0">
                    <input form="workflow-config-form" type="checkbox" name="field_editable_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="1" <?= !empty($fieldConfig['editable']) ? 'checked' : '' ?>>
                  </td>
                  <td style="text-align:center;">
                    <input form="workflow-config-form" type="hidden" name="field_mandatory_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="0">
                    <input form="workflow-config-form" type="checkbox" name="field_mandatory_<?= (int) $m['id'] ?>_<?= $safeFieldKey ?>" value="1" <?= !empty($fieldConfig['mandatory']) ? 'checked' : '' ?>>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="admin-empty">No fields mapped for this module.</div>
          <?php endif; ?>
        </div>

        <div class="layout-stack layout-stack--gap-2">
          <h4 style="margin:0;">Actions</h4>
          <?php if (!empty($moduleDefinition['actions'])): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Action</th>
                  <th style="text-align:center;">Visible</th>
                  <th style="text-align:center;">Enabled</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($moduleDefinition['actions'] as $actionKey => $actionLabel): ?>
                <?php
                  $safeActionKey = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $actionKey));
                  $actionConfig = $currentConfig['actions'][$actionKey] ?? ['visible' => true, 'enabled' => true];
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($actionLabel) ?></strong>
                    <div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars($actionKey) ?></div>
                  </td>
                  <td style="text-align:center;">
                    <input form="workflow-config-form" type="hidden" name="action_visible_<?= (int) $m['id'] ?>_<?= $safeActionKey ?>" value="0">
                    <input form="workflow-config-form" type="checkbox" name="action_visible_<?= (int) $m['id'] ?>_<?= $safeActionKey ?>" value="1" <?= !empty($actionConfig['visible']) ? 'checked' : '' ?>>
                  </td>
                  <td style="text-align:center;">
                    <input form="workflow-config-form" type="hidden" name="action_enabled_<?= (int) $m['id'] ?>_<?= $safeActionKey ?>" value="0">
                    <input form="workflow-config-form" type="checkbox" name="action_enabled_<?= (int) $m['id'] ?>_<?= $safeActionKey ?>" value="1" <?= !empty($actionConfig['enabled']) ? 'checked' : '' ?>>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="admin-empty">No actions mapped for this module.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<style>
[data-draggable].drag-over { background: #EEF4FF !important; outline: 2px dashed #2563EB; }
</style>
