<?php
// $courseEntries, $courseEntryCount, $editCourseEntryId, $case, $sanitizer,
// $buildCaseUrl, $getOptionTitle, $hasText, $session, $fieldsApi — all from case-view.php scope.

$typeTagMap = [
    'Routine'    => 'badge--dc-draft',
    'Important'  => 'badge--case-pending',
    'Discharge'  => 'badge--dc-ready',
];

// Resolve the entry being edited (if any)
$editingHce = null;
if ($editCourseEntryId) {
    $candidate = wire('pages')->get($editCourseEntryId);
    if ($candidate && $candidate->id
        && $candidate->template->name === 'hospital-course-entry'
        && (int) $candidate->parent_id === (int) $case->id) {
        $editingHce = $candidate;
    }
}

$hceFormDate = $editingHce && $editingHce->getUnformatted('hce_date')
    ? date('Y-m-d', $editingHce->getUnformatted('hce_date'))
    : date('Y-m-d');

$hceFormType = $editingHce
    ? ($getOptionTitle($editingHce->hce_type) ?: 'Routine')
    : 'Routine';

$hceFormNote = $editingHce
    ? (string) $editingHce->hce_note
    : '';
?>
<section class="card case-module" id="module-9" data-module-step="9">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 8</span>
      <h2 class="card__title">Hospital Course</h2>
    </div>
    <div class="card__action">
      <span class="t-meta"><?= $courseEntryCount ?> <?= $courseEntryCount === 1 ? 'entry' : 'entries' ?></span>
    </div>
  </div>

  <div class="card__body layout-stack layout-stack--gap-4">

    <?php if ($courseEntryCount > 0): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Tag</th>
            <th>Note</th>
            <th class="cell--action">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courseEntries as $hce): ?>
          <?php
            $hceDate    = $hce->getUnformatted('hce_date');
            $hceTypeStr = $getOptionTitle($hce->hce_type) ?: 'Routine';
            $hceBadge   = $typeTagMap[$hceTypeStr] ?? 'badge--dc-draft';
            $hceNote    = (string) $hce->hce_note;
          ?>
          <tr>
            <td class="cell" data-label="Date" style="white-space:nowrap;">
              <?= $hceDate ? date('d M Y', $hceDate) : '—' ?>
            </td>
            <td class="cell" data-label="Tag">
              <span class="badge <?= $hceBadge ?>"><?= $sanitizer->entities($hceTypeStr) ?></span>
            </td>
            <td class="cell" data-label="Note">
              <?= $hasText($hceNote) ? nl2br($sanitizer->entities($hceNote)) : '<span class="t-meta">No note</span>' ?>
            </td>
            <td class="cell cell--action" data-label="Actions">
              <a class="btn btn--icon"
                 href="<?= $buildCaseUrl(['edit_hce' => $hce->id], 'module-9') ?>"
                 aria-label="Edit entry"
                 title="Edit entry">
                <i data-lucide="square-pen" aria-hidden="true"></i>
              </a>
              <button class="btn btn--icon btn--destructive"
                      type="button"
                      data-confirm-action="delete_hce"
                      data-hce-id="<?= (int) $hce->id ?>"
                      data-confirm-title="Delete course entry"
                      data-confirm-message="Delete this hospital course entry? This cannot be undone."
                      aria-label="Delete entry">
                <i data-lucide="trash-2" aria-hidden="true"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="t-meta">No hospital course entries recorded yet.</p>
    <?php endif; ?>

    <?php
    // Show add form when not editing, show edit form when editing
    $formTitle  = $editingHce ? 'Edit Entry' : 'Add Entry';
    $formHidden = $editingHce ? '' : '';
    ?>
    <form method="post" class="card case-inline-form">
      <div class="card__header" style="padding-bottom:0;">
        <h3 class="card__title" style="font-size:var(--font-size-14);"><?= $formTitle ?></h3>
      </div>
      <div class="card__body layout-stack layout-stack--gap-4">
        <?= $session->CSRF->renderInput() ?>
        <input type="hidden" name="save_module" value="hospital_course" />
        <input type="hidden" name="course_entry_id" value="<?= $editingHce ? (int) $editingHce->id : '' ?>" />

        <div class="form-row">
          <div class="form-col">
            <label class="field">
              <span class="field__label">Date <span class="field__required">*</span></span>
              <input class="input" name="course_entry_date" type="date" value="<?= $hceFormDate ?>" required />
            </label>
          </div>
          <div class="form-col">
            <label class="field">
              <span class="field__label">Tag</span>
              <select class="select" name="course_entry_type">
                <option value="Routine"   <?= $hceFormType === 'Routine'   ? 'selected' : '' ?>>Routine</option>
                <option value="Important" <?= $hceFormType === 'Important' ? 'selected' : '' ?>>Important</option>
                <option value="Discharge" <?= $hceFormType === 'Discharge' ? 'selected' : '' ?>>Discharge</option>
              </select>
            </label>
          </div>
        </div>

        <label class="field">
          <span class="field__label">Note <span class="field__required">*</span></span>
          <textarea class="textarea" name="course_entry_note" rows="3" required><?= $sanitizer->entities($hceFormNote) ?></textarea>
        </label>

        <div class="layout-row layout-row--gap-2 layout-row--end">
          <?php if ($editingHce): ?>
          <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-9') ?>">Cancel</a>
          <?php endif; ?>
          <button class="btn btn-condition" type="submit">
            <?= $editingHce ? 'Update Entry' : 'Add Entry' ?>
          </button>
        </div>
      </div>
    </form>

  </div>
</section>

<script>
// Wire the delete_hce confirm action into the existing confirm-form pattern.
// The case-view confirm modal dispatches the action value and any data-* attrs
// into the hidden .case-confirm-form. We need to pass hce_id as well.
document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm-action="delete_hce"]');
    if (!btn) return;
    var hceId = btn.getAttribute('data-hce-id');
    var form  = document.querySelector('.case-confirm-form');
    if (!form) return;
    // Set hidden fields before the modal opens
    var hceInput = form.querySelector('[name="hce_id"]');
    if (!hceInput) {
      hceInput      = document.createElement('input');
      hceInput.type = 'hidden';
      hceInput.name = 'hce_id';
      form.appendChild(hceInput);
    }
    hceInput.value = hceId;
  });
});
</script>
