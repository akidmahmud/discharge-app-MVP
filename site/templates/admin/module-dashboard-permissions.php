<?php namespace ProcessWire;
/**
 * module-dashboard-permissions.php — Control what each role sees on the dashboard
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── Ensure table ──────────────────────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS admin_dashboard_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        element_key VARCHAR(50) NOT NULL,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_role_element (role_name, element_key)
    )");
} catch (\Exception $e) {}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action = $sanitizer->name($input->post->action ?? '');

    if ($action === 'save_dashboard_permissions') {
        $elements = [
            // Pages
            'page_dashboard','page_all_patients','page_pending_discharge',
            // Stat cards
            'stat_total_patients','stat_admitted_patients','stat_pending_discharge','stat_total_procedures',
            // Quick actions
            'action_new_patient','action_all_patients','action_export_registry','action_consultants','action_diagnoses',
            // Table
            'table_recent_admissions','table_export_csv',
        ];
        $allRoles = $roles->find("name!=guest,name!=superuser");
        foreach ($allRoles as $r) {
            foreach ($elements as $el) {
                $key = $r->name . '_' . $el;
                $visible = (int)(bool)$input->post->{$key};
                $stmt = $db->prepare("INSERT INTO admin_dashboard_permissions (role_name, element_key, is_visible)
                    VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_visible=VALUES(is_visible)");
                $stmt->execute([$r->name, $el, $visible]);
            }
        }
        adminLog($db, $user, 'dashboard-permissions', 'save', null, null, null, 'updated');
        $session->redirect('/admin-panel/?module=dashboard-permissions&saved=1');
    }
}

// ── Load current permissions ──────────────────────────────────────────────────
$permData = [];
try {
    $stmt = $db->query("SELECT role_name, element_key, is_visible FROM admin_dashboard_permissions");
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $permData[$row['role_name']][$row['element_key']] = (bool)$row['is_visible'];
    }
} catch (\Exception $e) {}

$allRoles = $roles->find("name!=guest,name!=superuser, sort=name");

// Default visibility: everything visible
$defaultVisible = array_fill_keys([
    'page_dashboard','page_all_patients','page_pending_discharge',
    'stat_total_patients','stat_admitted_patients','stat_pending_discharge','stat_total_procedures',
    'action_new_patient','action_all_patients','action_export_registry','action_consultants','action_diagnoses',
    'table_recent_admissions','table_export_csv',
], true);

$saved = $input->get->int('saved') === 1;
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Dashboard Permissions</h1>
      <p class="admin-module__subtitle">Control which pages and dashboard elements each role can see</p>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="admin-alert admin-alert--success">Dashboard permissions saved successfully.</div>
  <?php endif; ?>

  <form method="post" action="/admin-panel/?module=dashboard-permissions">
    <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
    <input type="hidden" name="action" value="save_dashboard_permissions">

    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">Visibility Matrix</h2>
        <span style="font-size:12px;color:#94A3B8;">Checked = visible / accessible</span>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="min-width:200px;">Element</th>
              <?php foreach ($allRoles as $r): ?>
              <th style="text-align:center;"><?= htmlspecialchars($r->title ?: $r->name) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <!-- Pages -->
            <tr style="background:#F8FAFF;">
              <td colspan="<?= count($allRoles)+1 ?>" style="font-weight:600;color:#2563EB;padding:10px 16px;">Pages</td>
            </tr>
            <?php foreach (['page_dashboard'=>'Dashboard','page_all_patients'=>'All Patients','page_pending_discharge'=>'Pending Discharge'] as $key => $label): ?>
            <tr>
              <td><?= $label ?></td>
              <?php foreach ($allRoles as $r): ?>
              <?php $checked = ($permData[$r->name][$key] ?? $defaultVisible[$key]) ? 'checked' : ''; ?>
              <td style="text-align:center;">
                <input type="checkbox" name="<?= $r->name ?>_<?= $key ?>" value="1" <?= $checked ?> style="width:18px;height:18px;cursor:pointer;">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            <!-- Stat Cards -->
            <tr style="background:#F8FAFF;">
              <td colspan="<?= count($allRoles)+1 ?>" style="font-weight:600;color:#2563EB;padding:10px 16px;">Statistic Cards</td>
            </tr>
            <?php foreach (['stat_total_patients'=>'Total Patients','stat_admitted_patients'=>'Admitted Patients','stat_pending_discharge'=>'Pending Discharge','stat_total_procedures'=>'Total Procedures'] as $key => $label): ?>
            <tr>
              <td><?= $label ?></td>
              <?php foreach ($allRoles as $r): ?>
              <?php $checked = ($permData[$r->name][$key] ?? $defaultVisible[$key]) ? 'checked' : ''; ?>
              <td style="text-align:center;">
                <input type="checkbox" name="<?= $r->name ?>_<?= $key ?>" value="1" <?= $checked ?> style="width:18px;height:18px;cursor:pointer;">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            <!-- Quick Actions -->
            <tr style="background:#F8FAFF;">
              <td colspan="<?= count($allRoles)+1 ?>" style="font-weight:600;color:#2563EB;padding:10px 16px;">Quick Actions</td>
            </tr>
            <?php foreach (['action_new_patient'=>'New Patient','action_all_patients'=>'All Patients','action_export_registry'=>'Export Registry','action_consultants'=>'Consultants','action_diagnoses'=>'Diagnoses'] as $key => $label): ?>
            <tr>
              <td><?= $label ?></td>
              <?php foreach ($allRoles as $r): ?>
              <?php $checked = ($permData[$r->name][$key] ?? $defaultVisible[$key]) ? 'checked' : ''; ?>
              <td style="text-align:center;">
                <input type="checkbox" name="<?= $r->name ?>_<?= $key ?>" value="1" <?= $checked ?> style="width:18px;height:18px;cursor:pointer;">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            <!-- Table -->
            <tr style="background:#F8FAFF;">
              <td colspan="<?= count($allRoles)+1 ?>" style="font-weight:600;color:#2563EB;padding:10px 16px;">Admissions Table</td>
            </tr>
            <?php foreach (['table_recent_admissions'=>'Recent Admissions Table','table_export_csv'=>'Export CSV Button'] as $key => $label): ?>
            <tr>
              <td><?= $label ?></td>
              <?php foreach ($allRoles as $r): ?>
              <?php $checked = ($permData[$r->name][$key] ?? $defaultVisible[$key]) ? 'checked' : ''; ?>
              <td style="text-align:center;">
                <input type="checkbox" name="<?= $r->name ?>_<?= $key ?>" value="1" <?= $checked ?> style="width:18px;height:18px;cursor:pointer;">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:20px;">
      <button type="submit" class="admin-btn admin-btn--primary">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Save Dashboard Permissions
      </button>
    </div>
  </form>
</div>
