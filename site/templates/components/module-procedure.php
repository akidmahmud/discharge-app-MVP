<section class="card case-module" id="module-7" data-module-step="7">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Optional</span>
      <h2 class="card__title">Procedure Log</h2>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-4">
    <?php if (!count($procedures)): ?>
    <div class="case-comorbidity__empty">No OT plan summary recorded yet.</div>
    <?php else: ?>
      <?php foreach ($procedures as $procedure): ?>
      <?php
        $summaryProcedureName = trim((string) ($procedure->proc_name ?: $procedure->title));
        $summaryProcedureDate = $procedure->getUnformatted('proc_date') ? date('d M Y', $procedure->getUnformatted('proc_date')) : 'Not recorded';
        $summaryProcedureTime = $fieldsApi->get('proc_time') ? trim((string) $procedure->proc_time) : '';
        $summaryAnaesthesia = $getOptionTitle($procedure->anesthesia_type) ?: 'Not recorded';
        $summaryCArm = $fieldsApi->get('c_arm_required') ? ($getOptionTitle($procedure->c_arm_required) ?: 'Not recorded') : 'Not recorded';
        $summaryMicroscope = $fieldsApi->get('microscope_required') ? ($getOptionTitle($procedure->microscope_required) ?: 'Not recorded') : 'Not recorded';
        $summaryImplant = $fieldsApi->get('implant_details') && trim((string) $procedure->implant_details) !== '' ? trim((string) $procedure->implant_details) : 'Not recorded';
      ?>
      <section class="card case-subcard">
        <div class="card__header">
          <div class="card__title-group">
            <h3 class="card__title"><?= $sanitizer->entities($summaryProcedureName ?: 'Procedure') ?></h3>
            <p class="card__subtitle">Summary from OT Plan</p>
          </div>
          <div class="card__action">
            <span class="badge<?= $procedureHasFilledSurgeryPlan($procedure) ? ' badge--dc-ready' : '' ?>">
              <?= $procedureHasFilledSurgeryPlan($procedure) ? 'Ready' : 'Needs details' ?>
            </span>
          </div>
        </div>
        <div class="card__body layout-stack layout-stack--gap-3">
          <div class="card__row">
            <div class="card__row-label">OT Date</div>
            <div class="card__row-value"><?= $sanitizer->entities($summaryProcedureDate) ?></div>
          </div>
          <div class="card__row">
            <div class="card__row-label">OT Time</div>
            <div class="card__row-value"><?= $sanitizer->entities($summaryProcedureTime !== '' ? $summaryProcedureTime : 'Not recorded') ?></div>
          </div>
          <div class="card__row">
            <div class="card__row-label">Anaesthesia Type</div>
            <div class="card__row-value"><?= $sanitizer->entities($summaryAnaesthesia) ?></div>
          </div>
          <div class="card__row">
            <div class="card__row-label">C-Arm Required</div>
            <div class="card__row-value"><?= $sanitizer->entities($summaryCArm) ?></div>
          </div>
          <div class="card__row">
            <div class="card__row-label">Microscope Required</div>
            <div class="card__row-value"><?= $sanitizer->entities($summaryMicroscope) ?></div>
          </div>
          <div class="card__row">
            <div class="card__row-label">Implant Required</div>
            <div class="card__row-value"><?= nl2br($sanitizer->entities($summaryImplant)) ?></div>
          </div>
        </div>
      </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
