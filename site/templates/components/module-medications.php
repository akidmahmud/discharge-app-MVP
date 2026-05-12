<?php
$isEditingMedications = $editModule === 'medications' || !$hasText($case->medications_on_discharge);
?>
<section class="card case-module" id="module-10a" data-module-step="10A">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 10A</span>
      <h2 class="card__title">Medication on Discharge</h2>
    </div>
    <div class="card__action">
      <?php if (!$isEditingMedications): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'medications'], 'module-10a') ?>" aria-label="Edit medications">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingMedications): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Drug</th>
            <th>Strength</th>
            <th>Frequency</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($medicationRows as $medication): ?>
          <tr>
            <td class="cell" data-label="Drug"><?= $sanitizer->entities($medication['drug']) ?></td>
            <td class="cell" data-label="Strength"><?= $sanitizer->entities($medication['dose']) ?></td>
            <td class="cell" data-label="Frequency"><?= $sanitizer->entities($medication['frequency'] ?? '') ?></td>
            <td class="cell" data-label="Duration"><?= $sanitizer->entities($medication['duration']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="medications" />

      <div class="layout-stack layout-stack--gap-2">
        <div class="layout-row layout-row--between">
          <span class="field__label">Medication Table</span>
          <div class="layout-row layout-row--gap-2">
            <button class="btn btn--neutral btn--sm" type="button" data-medication-add="#medication-list">Add Medication</button>
          </div>
        </div>
        <div class="layout-stack layout-stack--gap-2" id="medication-list">
          <?php foreach ($medicationRows as $index => $medication): ?>
          <div class="case-repeat-row<?= !empty($medication['is_duplicate']) ? ' case-repeat-row--duplicate' : '' ?>"<?= $index === 0 ? ' data-medication-template' : '' ?> data-duplicate-row="<?= !empty($medication['is_duplicate']) ? '1' : '0' ?>">
            <div class="case-repeat-grid case-repeat-grid--medication">
              <div class="field">
                <input class="input" name="med_drug[]" type="text" value="<?= $sanitizer->entities($medication['drug']) ?>" placeholder="Drug" />
                <div class="case-duplicate-note"<?= empty($medication['is_duplicate']) ? ' hidden' : '' ?>>Possible duplicate - verify</div>
              </div>
              <input class="input" name="med_dose[]" type="text" value="<?= $sanitizer->entities($medication['dose']) ?>" placeholder="Strength" />
              <input class="input" name="med_frequency[]" type="text" value="<?= $sanitizer->entities($medication['frequency'] ?? '') ?>" placeholder="Frequency" />
              <input class="input" name="med_duration[]" type="text" value="<?= $sanitizer->entities($medication['duration']) ?>" placeholder="Duration" />
              <button class="btn btn--icon btn--destructive" type="button" data-medication-remove aria-label="Remove medication">
                <i data-lucide="trash-2" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-10a') ?>">Cancel</a>
        <button class="btn btn-medication" type="submit">Save Medications</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
