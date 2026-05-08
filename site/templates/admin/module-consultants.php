<?php namespace ProcessWire;
/**
 * module-consultants.php — Consultant management
 */

$db = $database;
$notice = ''; $noticeType = 'success';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($input->requestMethod('POST')) {
    if (!$session->CSRF->validate()) {
        $notice = 'Security token invalid.'; $noticeType = 'error';
    } else {
        $action = $sanitizer->name($input->post->action);

        if ($action === 'save_consultant') {
            $id    = $sanitizer->int($input->post->id);
            $name  = $sanitizer->text($input->post->name);
            $dept  = $sanitizer->text($input->post->department);
            $isDef = $sanitizer->int($input->post->is_default) ? 1 : 0;
            $status= in_array($input->post->status, ['active','inactive']) ? $input->post->status : 'active';

            if (!$name) { $notice = 'Name is required.'; $noticeType = 'error'; }
            else {
                if ($isDef) {
                    $db->exec("UPDATE admin_consultants SET is_default=0");
                }
                if ($id) {
                    $stmt = $db->prepare("UPDATE admin_consultants SET name=?,department=?,is_default=?,status=? WHERE id=?");
                    $stmt->execute([$name,$dept,$isDef,$status,$id]);
                    adminLog($db,$user,'consultants','update',$id,'name',null,$name);
                } else {
                    $stmt = $db->prepare("INSERT INTO admin_consultants (name,department,is_default,status) VALUES (?,?,?,?)");
                    $stmt->execute([$name,$dept,$isDef,$status]);
                    adminLog($db,$user,'consultants','create',$db->lastInsertId(),'name',null,$name);
                }
                $session->redirect('/admin-panel/?module=consultants&saved=1');
            }
        }

        if ($action === 'delete_consultant') {
            $id = $sanitizer->int($input->post->id);
            $stmt = $db->prepare("DELETE FROM admin_consultants WHERE id=?");
            $stmt->execute([$id]);
            adminLog($db,$user,'consultants','delete',$id,null,null,null);
            $session->redirect('/admin-panel/?module=consultants&deleted=1');
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$consultants = [];
try {
    $stmt = $db->query("SELECT * FROM admin_consultants ORDER BY is_default DESC, name ASC");
    $consultants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { $notice = 'DB table not found. Run /api/admin-setup/ first.'; $noticeType = 'error'; }

$editId = $sanitizer->int($input->get->edit);
$editRow = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM admin_consultants WHERE id=?");
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch(\PDO::FETCH_ASSOC);
}

$csrfN = $session->CSRF->getTokenName();
$csrfV = $session->CSRF->getTokenValue();
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Consultants</h1>
      <p class="admin-module__subtitle">Manage consultant profiles and default assignments</p>
    </div>
    <button class="admin-btn admin-btn--primary" data-modal-open="modal-consultant">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Consultant
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
          <tr><th>#</th><th>Name</th><th>Department</th><th>Default</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($consultants)): ?>
        <tr><td colspan="6"><div class="admin-empty">No consultants added yet.</div></td></tr>
        <?php else: ?>
        <?php foreach ($consultants as $c): ?>
        <tr>
          <td style="color:#94A3B8;font-size:12px;"><?= (int)$c['id'] ?></td>
          <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
          <td><?= htmlspecialchars($c['department'] ?: '—') ?></td>
          <td>
            <?php if ($c['is_default']): ?>
            <span class="badge badge--green">Default</span>
            <?php else: ?>
            <span style="color:#CBD5E1;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $c['status']==='active'?'badge--green':'badge--red' ?>">
              <?= ucfirst($c['status']) ?>
            </span>
          </td>
          <td>
            <div class="td-actions">
              <a href="/admin-panel/?module=consultants&edit=<?= (int)$c['id'] ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form method="post" action="/admin-panel/?module=consultants" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="delete_consultant">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                  data-confirm="Delete consultant '<?= htmlspecialchars($c['name']) ?>'?">Delete</button>
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

<!-- Add / Edit Modal -->
<div class="admin-modal-overlay <?= $editRow ? 'is-open' : '' ?>" id="modal-consultant">
  <div class="admin-modal">
    <div class="admin-modal__header">
      <h3 class="admin-modal__title"><?= $editRow ? 'Edit Consultant' : 'Add Consultant' ?></h3>
      <button class="admin-modal__close" data-modal-close="modal-consultant">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="admin-modal__body">
      <form class="admin-form" method="post" action="/admin-panel/?module=consultants">
        <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
        <input type="hidden" name="action" value="save_consultant">
        <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">
        <div class="admin-form-grid">
          <div class="admin-field admin-field--full">
            <label class="admin-field__label">Full Name *</label>
            <input class="admin-field__input" type="text" name="name" required
              value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" placeholder="Dr. John Smith">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Department</label>
            <input class="admin-field__input" type="text" name="department"
              value="<?= htmlspecialchars($editRow['department'] ?? '') ?>" placeholder="Surgery, Medicine…">
          </div>
          <div class="admin-field">
            <label class="admin-field__label">Status</label>
            <select class="admin-field__select" name="status">
              <option value="active" <?= ($editRow['status']??'active')==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= ($editRow['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="admin-field admin-field--full">
            <label class="admin-toggle">
              <input type="checkbox" name="is_default" value="1" <?= !empty($editRow['is_default'])?'checked':'' ?>>
              <span class="admin-toggle__track"></span>
              <span class="admin-toggle__label">Set as default consultant</span>
            </label>
          </div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
          <a href="/admin-panel/?module=consultants" class="admin-btn admin-btn--ghost">Cancel</a>
          <button type="submit" class="admin-btn admin-btn--primary">
            <?= $editRow ? 'Save Changes' : 'Add Consultant' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
