<?php
$isEditingDiagnosis = $editModule === 'diagnosis' || !($hasText($case->diagnosis) || ($case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id));
$diagnosisText = $hasText($case->diagnosis) ? $case->diagnosis : (($case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id) ? $case->primary_diagnosis_ref->title : '');
?>
<section class="card case-module" id="module-2" data-module-step="2">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 2</span>
      <h2 class="card__title">Diagnosis</h2>
    </div>
    <div class="card__action">
      <?php if (!$isEditingDiagnosis): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'diagnosis'], 'module-2') ?>" aria-label="Edit diagnosis" title="Edit diagnosis">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingDiagnosis): ?>
    <div class="card__row">
      <div class="card__row-label">Primary Diagnosis</div>
      <div class="card__row-value"><?= $diagnosisText ? nl2br($sanitizer->entities($diagnosisText)) : '<span class="t-meta">Not recorded</span>' ?></div>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="diagnosis" />

      <label class="field">
        <span class="field__label">Primary Diagnosis <span class="field__required">*</span></span>
        <textarea class="textarea" name="primary_diagnosis_text" rows="4" required placeholder="Enter diagnosis..."><?= $sanitizer->entities($diagnosisText) ?></textarea>
      </label>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-2') ?>">Cancel</a>
        <button class="btn btn-diagnosis" type="submit">Save Diagnosis</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
