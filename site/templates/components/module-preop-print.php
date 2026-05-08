<section class="card case-module" id="module-preop-print">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Optional</span>
      <h2 class="card__title">Pre-operative Print Engine</h2>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-3">
    <p class="card__subtitle">Admission information, diagnosis, comorbidity, drug history, and OT plan only.</p>
    <?php if ($canGeneratePreopPdf): ?>
    <div class="case-chip-detail">
      All required fields are present for pre-op document generation.
    </div>
    <?php else: ?>
    <div class="case-chip-detail">
      <div class="field__label">Blocked until the following are completed:</div>
      <ul class="t-meta" style="margin:8px 0 0 18px;">
        <?php foreach ($preopMissingRequirements as $missingRequirement): ?>
        <li><?= $sanitizer->entities($missingRequirement) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="post" target="_blank" class="layout-row layout-row--end">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="preop_print" />
      <button class="btn btn-surgery" type="submit" data-action="generate_preop_pdf"<?= $canGeneratePreopPdf ? '' : ' disabled' ?>>Generate Pre-op Document</button>
    </form>
  </div>
</section>
