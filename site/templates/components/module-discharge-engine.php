<section class="card case-module case-module--preview" id="module-11" data-module-step="11">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 11</span>
      <h2 class="card__title">Discharge Engine</h2>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-4">
    <section class="card case-subcard">
      <div class="card__header">
        <div class="card__title-group">
          <h3 class="card__title"><?= $canGeneratePdf ? 'Discharge Readiness: Complete' : 'Conditional Validation' ?></h3>
          <p class="card__subtitle discharge-validation-note">
            <?= $canGeneratePdf ? 'All mandatory fields are present.' : count($dischargeBlockers) . ' blocker' . (count($dischargeBlockers) === 1 ? '' : 's') . ' remain before final PDF generation.' ?>
          </p>
        </div>
        <div class="card__action">
          <?php if ($canGeneratePdf): ?>
          <button class="btn btn-discharge" type="button" data-action="generate_final_pdf" onclick="window.open('/case-view/?id=<?= (int)$case->id ?>&pdf=1', '_blank')">Generate Final PDF</button>
          <?php else: ?>
          <button class="btn btn--neutral" type="button" data-action="generate_final_pdf" disabled title="Resolve the discharge readiness issues first">Generate Final PDF</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card__body layout-stack layout-stack--gap-2">
        <?php foreach ($dischargeReadinessChecks as $check): ?>
        <div class="layout-row layout-row--gap-2">
          <strong><?= $check['ok'] ? '[OK]' : '[!]' ?></strong>
          <span><?= $sanitizer->entities($check['ok'] ? $check['label'] : $check['message']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="t-meta discharge-validation-note">If no surgery was performed, Operation Note is skipped. If surgery exists, Operation Note becomes mandatory.</div>
      </div>
    </section>
  </div>
</section>
