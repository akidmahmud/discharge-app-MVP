<?php
$isEditingAdvice = $editModule === 'advice' || !$hasText($followUpInstructionsText) || !$hasText($subsequentTreatmentPlanText);
?>
<section class="card case-module" id="module-10b" data-module-step="10B">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 10B</span>
      <h2 class="card__title">Advice &amp; Follow-up</h2>
    </div>
    <div class="card__action">
      <?php if ($isEditingAdvice): ?>
      <div class="layout-row layout-row--gap-2">
        <button class="btn btn--neutral btn--sm" type="button" data-template-add="advice,general" data-template-target="textarea[name='follow_up_instructions']" data-template-field="follow_up_instructions">Add Template</button>
        <button class="btn btn--neutral btn--sm" type="button" data-template-create="advice" data-template-field="follow_up_instructions">Create Template</button>
      </div>
      <?php endif; ?>
      <?php if (!$isEditingAdvice): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'advice'], 'module-10b') ?>" aria-label="Edit advice">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingAdvice): ?>
    <div class="layout-stack layout-stack--gap-4">
      <div class="card__row">
        <div class="card__row-label">Instructions</div>
        <div class="card__row-value"><?= $hasText($followUpInstructionsText) ? nl2br($sanitizer->entities($followUpInstructionsText)) : 'Not recorded' ?></div>
      </div>
      <div class="card__row">
        <div class="card__row-label">Subsequent Treatment Plan</div>
        <div class="card__row-value"><?= $hasText($subsequentTreatmentPlanText) ? nl2br($sanitizer->entities($subsequentTreatmentPlanText)) : 'Not recorded' ?></div>
      </div>
      <div class="card__row">
        <div class="card__row-label">Follow-up Date</div>
        <div class="card__row-value"><?= $case->getUnformatted('review_date') ? date('d M Y', $case->getUnformatted('review_date')) : 'Not recorded' ?></div>
      </div>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="advice" />

      <label class="field">
        <span class="field__label">Instructions</span>
        <textarea class="textarea" name="follow_up_instructions" rows="4"><?= $sanitizer->entities($followUpInstructionsText) ?></textarea>
      </label>

      <label class="field">
        <span class="field__label">Subsequent Treatment Plan <span class="field__required">*</span></span>
        <textarea class="textarea" name="subsequent_treatment_plan" rows="3"><?= $sanitizer->entities($subsequentTreatmentPlanText) ?></textarea>
      </label>

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Follow-up Date</span>
            <input class="input" name="review_date" type="date" value="<?= $case->getUnformatted('review_date') ? date('Y-m-d', $case->getUnformatted('review_date')) : '' ?>" />
          </label>
        </div>
      </div>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-10b') ?>">Cancel</a>
        <button class="btn btn-discharge" type="submit">Save Advice</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
