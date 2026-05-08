<?php namespace ProcessWire;
/**
 * module-archive.php — View and restore archived admission records
 */

$db     = $database;
$notice = ''; $noticeType = 'success';
$csrfN  = $session->CSRF->getTokenName();
$csrfV  = $session->CSRF->getTokenValue();

// ── POST: restore or permanently delete ───────────────────────────────────────
if ($input->requestMethod('POST') && $session->CSRF->validate()) {
    $action   = $sanitizer->name($input->post->action);
    $caseId   = $sanitizer->int($input->post->case_id);

    if ($action === 'restore' && $caseId) {
        $casePage = $pages->get($caseId);
        if ($casePage && $casePage->id && (int)($casePage->get('case_status')) === 3) {
            $casePage->of(false);
            $casePage->set('case_status', 1);
            $casePage->save('case_status');
            adminLog($db, $user, 'archive', 'restore', $caseId, 'case_status', '3', '1');
            $session->redirect('/admin-panel/?module=archive&saved=restore');
        }
    }

    if ($action === 'delete_permanent' && $caseId) {
        $casePage = $pages->get($caseId);
        if ($casePage && $casePage->id && (int)($casePage->get('case_status')) === 3) {
            adminLog($db, $user, 'archive', 'delete-permanent', $caseId, 'title', $casePage->title, null);
            $pages->delete($casePage, true);
            $session->redirect('/admin-panel/?module=archive&saved=deleted');
        }
    }
}

$savedMsg = '';
if ($input->get->saved === 'restore') $savedMsg = 'Case restored to Active status.';
if ($input->get->saved === 'deleted') $savedMsg = 'Case permanently deleted.';

// ── Filters ───────────────────────────────────────────────────────────────────
$q          = $sanitizer->text($input->get->q);
$filterDate = $sanitizer->date($input->get->date_from, 'Y-m-d');
$filterTo   = $sanitizer->date($input->get->date_to, 'Y-m-d');

$selectorParts = ["template=admission-record", "case_status=3"];
if ($q) {
    $qEsc = $sanitizer->selectorValue($q);
    $selectorParts[] = "title|patient_name|diagnosis%={$qEsc}";
}
if ($filterDate) $selectorParts[] = "created>=" . strtotime($filterDate . ' 00:00:00');
if ($filterTo)   $selectorParts[] = "created<=" . strtotime($filterTo . ' 23:59:59');
$selectorParts[] = "sort=-created";
$selectorParts[] = "limit=100";

$archivedCases = $pages->find(implode(', ', $selectorParts));
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Archive</h1>
      <p class="admin-module__subtitle">View and restore archived admission records</p>
    </div>
  </div>

  <?php if ($savedMsg): ?>
  <div class="admin-alert admin-alert--success"><?= htmlspecialchars($savedMsg) ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="admin-card">
    <div class="admin-card__body">
      <form method="get" action="/admin-panel/" class="admin-filter-bar">
        <input type="hidden" name="module" value="archive">
        <input class="admin-field__input" type="text" name="q" value="<?= htmlspecialchars($q) ?>"
          placeholder="Search patient name, MRN, diagnosis…" style="flex:1;min-width:200px;">
        <input class="admin-field__input" type="date" name="date_from" value="<?= htmlspecialchars($filterDate) ?>"
          style="width:150px;" title="Archived from">
        <input class="admin-field__input" type="date" name="date_to" value="<?= htmlspecialchars($filterTo) ?>"
          style="width:150px;" title="Archived to">
        <button type="submit" class="admin-btn admin-btn--primary">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Filter
        </button>
        <?php if ($q || $filterDate || $filterTo): ?>
        <a href="/admin-panel/?module=archive" class="admin-btn admin-btn--ghost">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Results -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
        Archived Cases
      </h2>
      <span style="font-size:12px;color:#94A3B8;"><?= $archivedCases->count() ?> record<?= $archivedCases->count()!==1?'s':'' ?></span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Patient ID</th><th>Name</th><th>Diagnosis</th>
            <th>Consultant</th><th>Admitted</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($archivedCases->count() === 0): ?>
        <tr><td colspan="6"><div class="admin-empty">No archived records match your search.</div></td></tr>
        <?php else: ?>
        <?php foreach ($archivedCases as $case): ?>
        <?php
          $admDate    = $case->get('admission_date') ?: $case->created;
          $diagnosis  = $case->get('diagnosis') ?: '—';
          $consultant = $case->get('consultant_name') ?: '—';
          $patName    = $case->title ?: $case->get('patient_name') ?: '—';
        ?>
        <tr>
          <td style="color:#94A3B8;font-size:12px;"><?= htmlspecialchars($case->name) ?></td>
          <td><strong><?= htmlspecialchars($patName) ?></strong></td>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars(substr(strip_tags($diagnosis), 0, 55)) ?>
          </td>
          <td><?= htmlspecialchars($consultant) ?></td>
          <td style="white-space:nowrap;font-size:12px;color:#64748B;">
            <?= $admDate ? date('d M Y', is_numeric($admDate)?$admDate:strtotime($admDate)) : '—' ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="<?= $case->url ?>" target="_blank" class="admin-btn admin-btn--ghost admin-btn--sm">View</a>

              <form method="post" action="/admin-panel/?module=archive" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="case_id" value="<?= $case->id ?>">
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" style="color:#16A34A;border-color:#16A34A;"
                  data-confirm="Restore this case to Active status?">
                  Restore
                </button>
              </form>

              <form method="post" action="/admin-panel/?module=archive" style="display:inline;">
                <input type="hidden" name="<?= $csrfN ?>" value="<?= $csrfV ?>">
                <input type="hidden" name="action" value="delete_permanent">
                <input type="hidden" name="case_id" value="<?= $case->id ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                  data-confirm="PERMANENTLY delete this case? This cannot be undone.">Delete</button>
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

  <!-- Info box -->
  <div style="padding:12px 16px;background:#F8FAFF;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#64748B;display:flex;gap:10px;align-items:flex-start;">
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" fill="none" stroke-width="2" style="flex-shrink:0;margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Cases are moved to Archive when their status is set to <strong>Archived (3)</strong> in the discharge workflow. Restored cases return to <strong>Active</strong> status. Permanent deletion removes the ProcessWire page and all associated data.</span>
  </div>
</div>
