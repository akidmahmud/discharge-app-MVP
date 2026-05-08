<section class="card case-module" id="module-6" data-module-step="6">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 6</span>
      <h2 class="card__title">Surgery Plan</h2>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-4">
    <?php if (!count($procedures)): ?>
    <div class="case-comorbidity__empty" style="margin-bottom:12px;">No procedures added yet. Add the first procedure below.</div>
    <?php endif; ?>

    <!-- Add Procedure form (always visible) -->
    <details class="case-subcard" id="add-procedure-details" <?= !count($procedures) ? 'open' : '' ?>>
      <summary style="cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px;list-style:none;display:flex;align-items:center;gap:8px;">
        <i data-lucide="plus-circle" style="width:16px;height:16px;" aria-hidden="true"></i>
        Add Procedure
      </summary>
      <form method="post" class="layout-stack layout-stack--gap-4" style="padding:0 16px 16px;">
        <?= $session->CSRF->renderInput() ?>
        <input type="hidden" name="save_module" value="procedure" />
        <input type="hidden" name="proc_id" value="" />

        <label class="field">
          <span class="field__label">Procedure Name <span class="field__required">*</span></span>
          <input class="input" name="proc_name" type="text" placeholder="e.g. Right TKR" required />
        </label>

        <div class="form-row">
          <div class="form-col">
            <label class="field">
              <span class="field__label">OT Date</span>
              <input class="input" name="proc_date" type="date" />
            </label>
          </div>
          <div class="form-col">
            <label class="field">
              <span class="field__label">Surgeon</span>
              <input class="input" name="surgeon_name" type="text" placeholder="Primary surgeon" />
            </label>
          </div>
        </div>

        <div class="layout-row layout-row--gap-2 layout-row--end">
          <button class="btn btn-surgery" type="submit">Add Procedure</button>
        </div>
      </form>
    </details>

    <?php if (count($procedures)): ?>
      <?php foreach ($procedures as $procedure): ?>
      <?php
        $procedureName = trim((string) ($procedure->proc_name ?: $procedure->title));
        $procedureDate = $procedure->getUnformatted('proc_date') ? date('Y-m-d', $procedure->getUnformatted('proc_date')) : '';
        $procedureTime = $fieldsApi->get('proc_time') ? trim((string) $procedure->proc_time) : '';
        $procedureAnesthesia = $getOptionTitle($procedure->anesthesia_type) ?: trim((string) $procedure->anesthesia_type);
        $procedureCArm = $fieldsApi->get('c_arm_required') ? ($getOptionTitle($procedure->c_arm_required) ?: trim((string) $procedure->c_arm_required)) : '';
        $procedureMicroscope = $fieldsApi->get('microscope_required') ? ($getOptionTitle($procedure->microscope_required) ?: trim((string) $procedure->microscope_required)) : '';
        $procedureImplantDetails = $fieldsApi->get('implant_details') ? trim((string) $procedure->implant_details) : '';
        $procedureAnesthesiologist = $fieldsApi->get('anesthesiologist_name') ? trim((string) $procedure->anesthesiologist_name) : '';
        $procedureSurgeon = $fieldsApi->get('surgeon_name') ? trim((string) $procedure->surgeon_name) : '';
      ?>
      <form method="post" class="card case-subcard">
        <div class="card__header">
          <div class="card__title-group">
            <h3 class="card__title"><?= $sanitizer->entities($procedureName ?: 'Procedure') ?></h3>
            <p class="card__subtitle"><?= $procedureHasFilledSurgeryPlan($procedure) ? 'OT plan ready' : 'OT plan incomplete' ?></p>
          </div>
          <div class="card__action">
            <span class="badge<?= $procedureHasFilledSurgeryPlan($procedure) ? ' badge--dc-ready' : '' ?>">
              <?= $procedureHasFilledSurgeryPlan($procedure) ? 'Ready' : 'Needs details' ?>
            </span>
          </div>
        </div>
        <?php
          $previewItems = array_filter([
            'OT Date'        => $procedureDate ? date('d M Y', strtotime($procedureDate)) : '',
            'OT Time'        => $procedureTime,
            'Surgeon'        => $procedureSurgeon,
            'Anesthesia'     => $procedureAnesthesia,
            'Anesthesiologist' => $procedureAnesthesiologist,
            'C-Arm'          => $procedureCArm,
            'Microscope'     => $procedureMicroscope,
            'Implant'        => $procedureImplantDetails,
          ]);
        ?>
        <?php if (!empty($previewItems)): ?>
        <div class="card__body" style="padding-bottom:0;">
          <div style="display:flex;flex-wrap:wrap;gap:6px 16px;">
            <?php foreach ($previewItems as $label => $value): ?>
            <span style="font-size:12px;color:var(--color-text-secondary);"><strong><?= $sanitizer->entities($label) ?>:</strong> <?= $sanitizer->entities($value) ?></span>
            <?php endforeach; ?>
          </div>
          <hr style="border:none;border-top:1px solid var(--color-border);margin:12px -16px 0;">
        </div>
        <?php endif; ?>
        <div class="card__body layout-stack layout-stack--gap-4">
          <?= $session->CSRF->renderInput() ?>
          <input type="hidden" name="save_module" value="procedure_plan" />
          <input type="hidden" name="proc_id" value="<?= (int) $procedure->id ?>" />

          <label class="field">
            <span class="field__label">Procedure Name</span>
            <input class="input" name="proc_name" type="text" value="<?= $sanitizer->entities($procedureName) ?>" />
          </label>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">OT Date</span>
                <input class="input" name="proc_date" type="date" value="<?= $procedureDate ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">OT Time</span>
                <input class="input" name="proc_time" type="text" value="<?= $sanitizer->entities($procedureTime) ?>" placeholder="e.g. 09:30" />
              </label>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">Anesthesia Type</span>
                <select class="select" name="anesthesia_type">
                  <option value="">Select type</option>
                  <?php foreach (['Local', 'BB', 'Spinal', 'GA'] as $option): ?>
                  <option value="<?= $option ?>" <?= $procedureAnesthesia === $option ? 'selected' : '' ?>><?= $option ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Anesthesiologist Name</span>
                <input class="input" name="anesthesiologist_name" type="text" value="<?= $sanitizer->entities($procedureAnesthesiologist) ?>" placeholder="Assigned anesthesiologist" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Surgeon</span>
                <input class="input" name="surgeon_name" type="text" value="<?= $sanitizer->entities($procedureSurgeon) ?>" placeholder="Primary surgeon" />
              </label>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">C-Arm Required</span>
                <select class="select" name="c_arm_required">
                  <option value="">Select</option>
                  <option value="Yes" <?= $procedureCArm === 'Yes' ? 'selected' : '' ?>>Yes</option>
                  <option value="No" <?= $procedureCArm === 'No' ? 'selected' : '' ?>>No</option>
                </select>
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Microscope Required</span>
                <select class="select" name="microscope_required">
                  <option value="">Select</option>
                  <option value="Yes" <?= $procedureMicroscope === 'Yes' ? 'selected' : '' ?>>Yes</option>
                  <option value="No" <?= $procedureMicroscope === 'No' ? 'selected' : '' ?>>No</option>
                </select>
              </label>
            </div>
          </div>

          <label class="field">
            <span class="field__label">Implant Details</span>
            <input class="input" name="implant_details" type="text" value="<?= $sanitizer->entities($procedureImplantDetails) ?>" placeholder="Implant description" />
          </label>

          <div class="layout-row layout-row--gap-2 layout-row--end">
            <button class="btn btn--destructive" type="button" data-confirm-action="delete_ot_plan" data-confirm-title="Delete OT Plan" data-confirm-message="Delete this surgery plan entry?" data-proc-id="<?= (int) $procedure->id ?>">Delete</button>
            <button class="btn btn-surgery" type="submit">Save OT Plan</button>
          </div>
        </div>
      </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>
<script>
// Auto-close the Add Procedure details after a successful add
(function() {
  var details = document.getElementById('add-procedure-details');
  if (details && document.querySelectorAll('[data-module-step="6"] .case-subcard form[action]').length === 0) {
    // already open if no procedures exist
  }
})();
</script>
