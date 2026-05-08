<?php namespace ProcessWire;
/**
 * module-users.php — Users, Roles, and Permission Matrix
 */

$db     = $database;
$notice = '';
$noticeType = 'success';

// ── Ensure admin_role_permissions table exists ────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(128) NOT NULL,
        `module` varchar(128) NOT NULL,
        `can_view` tinyint(1) DEFAULT 0,
        `can_edit` tinyint(1) DEFAULT 0,
        `can_delete` tinyint(1) DEFAULT 0,
        `can_approve` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `role_module` (`role_name`,`module`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (\Throwable $e) {}

// ── Module definitions ────────────────────────────────────────────────────────
$caseModules  = ['admission','diagnosis','history','examination','investigations',
                 'ot-plan','operation-note','hospital-course','condition','medications','advice'];
$allPermModules = $caseModules;

$permModuleLabels = [
    'overview'           => 'Overview',
    'users'              => 'Users & Roles',
    'consultants'        => 'Consultants',
    'templates'          => 'Templates',
    'workflow'           => 'Workflow Config',
    'rules'              => 'Rule Engine',
    'discharge-settings' => 'Discharge Settings',
    'admission'          => 'Admission',
    'diagnosis'          => 'Diagnosis',
    'history'            => 'History',
    'examination'        => 'Examination',
    'investigations'     => 'Investigations',
    'ot-plan'            => 'OT Plan',
    'operation-note'     => 'Operation Note',
    'hospital-course'    => 'Hospital Course',
    'condition'          => 'Condition',
    'medications'        => 'Medications',
    'advice'             => 'Advice & Follow-up',
];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid. Please refresh and retry.';
        $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        // Add user
        if ($action === 'add_user') {
            $uname  = $sanitizer->username($input->post->username);
            $upass  = $input->post->password;
            $urole  = $sanitizer->name($input->post->role);
            $uemail = $sanitizer->email($input->post->email);

            if (!$uname || !$upass || strlen($upass) < 6) {
                $notice = 'Username and password (min 6 chars) are required.';
                $noticeType = 'error';
            } elseif ($users->get("name={$uname}")->id) {
                $notice = "Username '{$uname}' is already taken.";
                $noticeType = 'error';
            } else {
                $newUser = $users->add($uname);
                $newUser->pass = $upass;
                if ($uemail) $newUser->email = $uemail;
                $newUser->of(false);
                $newUser->save();
                if ($urole && $roles->get($urole)->id) {
                    $newUser->addRole($urole);
                    $newUser->save();
                }
                adminLog($db, $user, 'users', 'create_user', $newUser->id, 'username', null, $uname);
                $session->redirect('/admin-panel/?module=users&created=1');
            }
        }

        // Delete user
        if ($action === 'delete_user') {
            $uid    = $sanitizer->int($input->post->user_id);
            $target = $users->get("id={$uid}");
            if ($target->id && $target->id !== $user->id && !$target->isSuperuser()) {
                $uname = $target->name;
                $users->delete($target);
                adminLog($db, $user, 'users', 'delete_user', $uid, 'username', $uname, null);
                $session->redirect('/admin-panel/?module=users&deleted=1');
            } else {
                $notice = 'Cannot delete this user.';
                $noticeType = 'error';
            }
        }

        // Edit role title
        if ($action === 'edit_role') {
            $roleId     = $sanitizer->int($input->post->role_id);
            $targetRole = $roleId ? $roles->get("id={$roleId}") : null;
            if ($targetRole && $targetRole->id) {
                $targetRole->of(false);
                $oldTitle = (string) $targetRole->title;
                $targetRole->title = $sanitizer->text($input->post->role_title);
                $targetRole->save();
                adminLog($db, $user, 'users', 'edit_role', $targetRole->id, 'title', $oldTitle, $targetRole->title);
                $session->redirect('/admin-panel/?module=users&tab=roles&saved=1');
            }
            $notice = 'Role not found.';
            $noticeType = 'error';
        }

        // Save permissions for one role at a time
        if ($action === 'save_role_permissions') {
            $roleName = $sanitizer->name($input->post->role_name);
            if ($roleName && $roleName !== 'superuser' && $roleName !== 'guest') {
                $stmt = $db->prepare("INSERT INTO admin_role_permissions
                    (role_name, module, can_view, can_edit, can_delete, can_approve)
                    VALUES (?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                    can_view=VALUES(can_view), can_edit=VALUES(can_edit),
                    can_delete=VALUES(can_delete), can_approve=VALUES(can_approve)");
                foreach ($allPermModules as $mod) {
                    $key = $roleName . '_' . str_replace('-', '_', $mod);
                    $stmt->execute([
                        $roleName, $mod,
                        (int)(bool)$input->post->{"view_{$key}"},
                        (int)(bool)$input->post->{"edit_{$key}"},
                        (int)(bool)$input->post->{"delete_{$key}"},
                        (int)(bool)$input->post->{"approve_{$key}"},
                    ]);
                }
                adminLog($db, $user, 'users', 'save_permissions', null, null, null, "permissions updated for {$roleName}");
                $session->redirect('/admin-panel/?module=users&tab=permissions&saved=1');
            }
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$allUsers = $users->find("name!=guest, sort=name");
$allRoles = $roles->find("name!=guest, sort=name");
$activeTab = $sanitizer->name($input->get->tab ?? 'users');

$permsData = [];
try {
    foreach ($db->query("SELECT * FROM admin_role_permissions")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $permsData[$row['role_name']][$row['module']] = $row;
    }
} catch (\Exception $e) {}

$editableRoles = [];
foreach ($allRoles as $r) {
    if ($r->name !== 'superuser') $editableRoles[] = $r;
}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Users &amp; Roles</h1>
      <p class="admin-module__subtitle">Manage system users, roles, and module permissions</p>
    </div>
    <button class="admin-btn admin-btn--primary" data-modal-open="modal-add-user">
      <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add User
    </button>
  </div>

  <?php if ($notice): ?>
  <div class="admin-alert admin-alert--<?= $noticeType ?>" data-auto-dismiss>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <div class="admin-tabs">
    <button class="admin-tab <?= $activeTab==='users'?'is-active':'' ?>" data-tab="tab-users">Users</button>
    <button class="admin-tab <?= $activeTab==='roles'?'is-active':'' ?>" data-tab="tab-roles">Roles</button>
    <button class="admin-tab <?= $activeTab==='permissions'?'is-active':'' ?>" data-tab="tab-permissions">Permission Matrix</button>
  </div>

  <!-- ── Users tab ────────────────────────────────────────────────────────── -->
  <div class="admin-tab-panel <?= $activeTab==='users'?'is-active':'' ?>" id="tab-users">
    <div class="admin-card">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>Username</th><th>Email</th><th>Roles</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($allUsers as $u): ?>
          <?php
            $roleNames = [];
            foreach ($u->roles as $r) { if ($r->name !== 'guest') $roleNames[] = $r->name; }
          ?>
          <tr>
            <td style="color:#94A3B8;font-size:12px;"><?= (int)$u->id ?></td>
            <td>
              <strong><?= htmlspecialchars($u->name) ?></strong>
              <?php if ($u->isSuperuser()): ?><span class="badge badge--purple" style="margin-left:6px;">SU</span><?php endif; ?>
            </td>
            <td style="color:#64748B;"><?= htmlspecialchars($u->email ?: '—') ?></td>
            <td>
              <?php foreach ($roleNames as $rn): ?>
              <span class="badge badge--blue" style="margin-right:4px;"><?= htmlspecialchars($rn) ?></span>
              <?php endforeach; ?>
              <?php if (empty($roleNames)): ?><span style="color:#94A3B8;">—</span><?php endif; ?>
            </td>
            <td>
              <?php if (!$u->isSuperuser() && $u->id !== $user->id): ?>
              <form method="post" action="/admin-panel/?module=users" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= (int)$u->id ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                  data-confirm="Delete user '<?= htmlspecialchars($u->name) ?>'? This cannot be undone.">Delete</button>
              </form>
              <?php else: ?>
              <span style="color:#CBD5E1;font-size:12px;">Protected</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Roles tab ────────────────────────────────────────────────────────── -->
  <div class="admin-tab-panel <?= $activeTab==='roles'?'is-active':'' ?>" id="tab-roles">
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">System Roles</h2>
        <a href="<?= $config->urls->admin ?>access/roles/add/" target="_blank" class="admin-btn admin-btn--ghost admin-btn--sm">Manage in PW Admin ↗</a>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Role</th><th>Users</th><th>Type</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($allRoles as $r): ?>
          <?php $roleUserCount = $users->count("roles={$r->name}, name!=guest"); ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($r->title ?: $r->name) ?></strong>
              <div style="font-size:12px;color:#94A3B8;"><?= htmlspecialchars($r->name) ?></div>
            </td>
            <td><?= (int)$roleUserCount ?> user<?= $roleUserCount !== 1 ? 's' : '' ?></td>
            <td><span class="badge <?= $r->name==='superuser'?'badge--purple':'badge--gray' ?>"><?= $r->name==='superuser'?'System':'Custom' ?></span></td>
            <td>
              <?php if ($r->name !== 'superuser'): ?>
              <button class="admin-btn admin-btn--ghost admin-btn--sm" type="button" data-modal-open="modal-edit-role-<?= (int)$r->id ?>">Edit</button>
              <?php else: ?>
              <span style="color:#CBD5E1;font-size:12px;">Protected</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Permission Matrix tab ────────────────────────────────────────────── -->
  <div class="admin-tab-panel <?= $activeTab==='permissions'?'is-active':'' ?>" id="tab-permissions">

    <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1E40AF;">
      <strong>Superuser</strong> always has full access to everything. Configure permissions for other roles below. Each role saves independently.
    </div>

    <?php if (empty($editableRoles)): ?>
    <div class="admin-card"><div class="admin-card__body"><div class="admin-empty">No custom roles found. Create roles in ProcessWire admin first.</div></div></div>
    <?php endif; ?>

    <?php foreach ($editableRoles as $r):
      $rn = $r->name;
      $rLabel = $r->title ?: $rn;
    ?>
    <div class="admin-card" style="margin-bottom:20px;">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <?= htmlspecialchars($rLabel) ?>
          <span class="badge badge--gray" style="margin-left:8px;font-weight:400;"><?= htmlspecialchars($rn) ?></span>
        </h2>
        <span style="font-size:12px;color:#94A3B8;">V = View &nbsp; E = Edit &nbsp; D = Delete &nbsp; A = Approve</span>
      </div>

      <form method="post" action="/admin-panel/?module=users&tab=permissions">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="save_role_permissions">
        <input type="hidden" name="role_name" value="<?= htmlspecialchars($rn) ?>">

        <div class="admin-table-wrap">
          <table class="perm-matrix">
            <thead>
              <tr>
                <th style="text-align:left;min-width:180px;">Module</th>
                <th style="text-align:center;width:60px;">View</th>
                <th style="text-align:center;width:60px;">Edit</th>
                <th style="text-align:center;width:60px;">Delete</th>
                <th style="text-align:center;width:60px;">Approve</th>
              </tr>
            </thead>
            <tbody>

            <?php foreach ($caseModules as $mod):
              $key = $rn . '_' . str_replace('-','_',$mod);
              $p   = $permsData[$rn][$mod] ?? [];
            ?>
            <tr>
              <td style="font-weight:500;"><?= htmlspecialchars($permModuleLabels[$mod] ?? $mod) ?></td>
              <?php foreach (['view','edit','delete','approve'] as $perm): ?>
              <td style="text-align:center;">
                <input type="hidden" name="<?= $perm ?>_<?= $key ?>" value="0">
                <input type="checkbox" name="<?= $perm ?>_<?= $key ?>" value="1"
                  <?= !empty($p['can_'.$perm]) ? 'checked' : '' ?>
                  style="width:16px;height:16px;accent-color:#2563EB;cursor:pointer;">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            </tbody>
          </table>
        </div>

        <div style="padding:14px 20px;border-top:1px solid #F1F5F9;display:flex;justify-content:flex-end;">
          <button type="submit" class="admin-btn admin-btn--primary">
            <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Save <?= htmlspecialchars($rLabel) ?> Permissions
          </button>
        </div>
      </form>
    </div>
    <?php endforeach; ?>

  </div><!-- /tab-permissions -->

</div><!-- /admin-module -->

<!-- Add User Modal -->
<div class="admin-modal-overlay" id="modal-add-user">
  <div class="admin-modal">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title">Add New User</h3>
      <button class="admin-modal__close" data-modal-close="modal-add-user">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <form class="admin-form" method="post" action="/admin-panel/?module=users">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="add_user">
        <div class="admin-form-grid">
          <div class="admin-field">
            <label class="admin-field__label">Username *</label>
            <input class="admin-field__input" type="text" name="username" required autocomplete="off" placeholder="e.g. dr_ahmed">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Email</label>
            <input class="admin-field__input" type="email" name="email" placeholder="user@example.com">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Password * (min 6 chars)</label>
            <input class="admin-field__input" type="password" name="password" required autocomplete="new-password" minlength="6">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Role</label>
            <select class="admin-field__select" name="role">
              <option value="">— No role —</option>
              <?php foreach ($allRoles as $r): if ($r->name === 'superuser') continue; ?>
              <option value="<?= htmlspecialchars($r->name) ?>"><?= htmlspecialchars($r->title ?: $r->name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="admin-modal__footer" style="padding:0;border:none;margin-top:8px;">
          <button type="button" class="admin-btn admin-btn--ghost" data-modal-close="modal-add-user">Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Role modals -->
<?php foreach ($allRoles as $r): if ($r->name === 'superuser') continue; ?>
<div class="admin-modal-overlay" id="modal-edit-role-<?= (int)$r->id ?>">
  <div class="admin-modal" style="max-width:520px;">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title">Edit Role</h3>
      <button class="admin-modal__close" data-modal-close="modal-edit-role-<?= (int)$r->id ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <form class="admin-form" method="post" action="/admin-panel/?module=users&tab=roles">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="edit_role">
        <input type="hidden" name="role_id" value="<?= (int)$r->id ?>">
        <div class="admin-form-grid">
          <div class="admin-field">
            <label class="admin-field__label">Internal Name</label>
            <input class="admin-field__input" type="text" value="<?= htmlspecialchars($r->name) ?>" readonly style="opacity:.6;">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Display Title</label>
            <input class="admin-field__input" type="text" name="role_title" value="<?= htmlspecialchars($r->title ?: $r->name) ?>" required>
          </div>
        </div>
        <div class="admin-modal__footer" style="padding:0;border:none;margin-top:12px;">
          <button type="button" class="admin-btn admin-btn--ghost" data-modal-close="modal-edit-role-<?= (int)$r->id ?>">Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary">Save Role</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
(function () {
  var tab = new URLSearchParams(window.location.search).get('tab');
  if (tab) {
    document.querySelectorAll('.admin-tab').forEach(function (t) {
      t.classList.toggle('is-active', t.dataset.tab === 'tab-' + tab);
    });
    document.querySelectorAll('.admin-tab-panel').forEach(function (p) {
      p.classList.toggle('is-active', p.id === 'tab-' + tab);
    });
  }
})();
</script>
