<?php
$isEditingCondition = $editModule === 'condition' || !$hasText($generalConditionLabel);
?>
<section class="card case-module" id="module-10" data-module-step="10">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 9</span>
      <h2 class="card__title">Condition at Discharge</h2>
    </div>
    <div class="card__action">
      <?php if (!$isEditingCondition): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'condition'], 'module-10') ?>" aria-label="Edit condition">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingCondition): ?>
    <div class="layout-2col">
      <div class="card__row">
        <div class="card__row-label">Status of Patient During Discharge</div>
        <div class="card__row-value"><?= $sanitizer->entities($generalConditionLabel ?: 'Not recorded') ?></div>
      </div>
      <div class="card__row">
        <div class="card__row-label">Pain Scale (0-10)</div>
        <div class="card__row-value"><?= $painScoreValue !== '' ? (int) $painScoreValue : 'Not recorded' ?></div>
      </div>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="condition" />

      <label class="field">
        <span class="field__label">Status of Patient During Discharge</span>
        <select class="select" name="general_condition">
          <option value="">Select status</option>
          <option value="Stable" <?= $generalConditionLabel === 'Stable' ? 'selected' : '' ?>>Stable</option>
          <option value="Improved" <?= $generalConditionLabel === 'Improved' ? 'selected' : '' ?>>Improved</option>
          <option value="Fair" <?= $generalConditionLabel === 'Fair' ? 'selected' : '' ?>>Fair</option>
          <option value="Critical" <?= $generalConditionLabel === 'Critical' ? 'selected' : '' ?>>Critical</option>
          <option value="Expired" <?= $generalConditionLabel === 'Expired' ? 'selected' : '' ?>>Expired</option>
        </select>
      </label>

      <label class="field">
        <span class="field__label">Pain Scale (0-10)</span>
        <input class="input" name="pain_scale" type="number" min="0" max="10" value="<?= $painScoreValue !== '' ? (int) $painScoreValue : '' ?>" style="max-width:120px;" />
      </label>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-10') ?>">Cancel</a>
        <button class="btn btn-condition" type="submit">Save Condition</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
