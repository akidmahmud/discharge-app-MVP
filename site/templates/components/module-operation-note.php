<section class="card case-module" id="module-8" data-module-step="8">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 7</span>
      <h2 class="card__title">Operation Note</h2>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-4">
    <?php foreach ($operationNoteEntries as $operationNoteEntry): ?>
    <?php
      $procedure = $operationNoteEntry['procedure'] ?? null;
      $opNote = $operationNoteEntry['note'] ?? null;
      $opNoteMeta = ($opNote && $opNote->id) ? ($operationNoteMetaById[$opNote->id] ?? []) : [];
      $stepBlocks = [];
      if ($opNote && $opNote->id && trim((string) $opNote->procedure_steps) !== '') {
        $stepBlocks = preg_split('/\n\s*\n/', trim((string) $opNote->procedure_steps));
        $stepBlocks = array_values(array_filter(array_map('trim', $stepBlocks)));
      }
      if (!$stepBlocks) {
        $stepBlocks = [''];
      }
      $operationName = trim((string) (
        ($opNote && $opNote->id)
          ? preg_replace('/\s+Note$/i', '', (string) $opNote->title)
          : ($procedure ? ($procedure->proc_name ?: $procedure->title) : '')
      ));
      $operationDate = ($opNote && $opNote->id && $opNote->getUnformatted('surgery_date'))
        ? date('Y-m-d', $opNote->getUnformatted('surgery_date'))
        : (($procedure && $procedure->getUnformatted('proc_date')) ? date('Y-m-d', $procedure->getUnformatted('proc_date')) : '');
      $opNoteAnesthesia = $getOptionTitle($opNote ? $opNote->anesthesia_type : null) ?: ($procedure ? $getOptionTitle($procedure->anesthesia_type) : '');
      $surgeonValue = (string) ($opNoteMeta['surgeon_name'] ?? ($procedure ? $procedure->surgeon_name : ''));
      $anesthesiologistValue = (string) (($opNote && $opNote->anesthesiologist_name) ? $opNote->anesthesiologist_name : ($procedure ? $procedure->anesthesiologist_name : ''));
      $implantsUsedValue = (string) (($opNote && $opNote->implants_used) ? $opNote->implants_used : ($procedure ? $procedure->implant_details : ''));
    ?>
    <section class="card case-subcard">
      <div class="card__body">
        <form method="post" class="layout-stack layout-stack--gap-4">
          <?= $session->CSRF->renderInput() ?>
          <input type="hidden" name="save_module" value="operation_note" />
          <input type="hidden" name="opnote_id" value="<?= (int) ($opNote ? $opNote->id : 0) ?>" />
          <input type="hidden" name="procedure_id" value="<?= (int) ($procedure ? $procedure->id : 0) ?>" />
          <input type="hidden" name="opnote_template_name" value="<?= $sanitizer->entities((string) ($opNoteMeta['template_name'] ?? '')) ?>" />

          <div class="layout-row layout-row--between layout-row--align-center">
            <span class="field__label">Operation Note Templates</span>
            <div class="layout-row layout-row--gap-2">
              <button class="btn btn--neutral btn--sm" type="button"
                data-template-add="operation-note"
                data-template-target="textarea[name='surgical_approach']"
                data-template-field="surgical_approach">Add Template</button>
              <button class="btn btn--neutral btn--sm" type="button"
                data-template-create="operation-note"
                data-template-field="surgical_approach">Create Template</button>
            </div>
          </div>

          <label class="field">
            <span class="field__label">Operation Name</span>
            <input class="input" name="procedure_name" type="text" value="<?= $sanitizer->entities($operationName) ?>" />
          </label>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">Surgery Date</span>
                <input class="input" name="surgery_date" type="date" value="<?= $operationDate ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Start Time</span>
                <input class="input" name="start_time" type="text" value="<?= $sanitizer->entities((string) ($opNoteMeta['start_time'] ?? '')) ?>" placeholder="e.g. 09:30 AM" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">End Time</span>
                <input class="input" name="end_time" type="text" value="<?= $sanitizer->entities((string) ($opNoteMeta['end_time'] ?? '')) ?>" placeholder="e.g. 11:10 AM" />
              </label>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">Anesthesia Type</span>
                <select class="select" name="opnote_anesthesia_type">
                  <option value="">Select type</option>
                  <?php foreach (['Local', 'BB', 'Spinal', 'GA'] as $option): ?>
                  <option value="<?= $option ?>" <?= $opNoteAnesthesia === $option ? 'selected' : '' ?>><?= $option ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Surgeon</span>
                <input class="input" name="surgeon_name" type="text" value="<?= $sanitizer->entities($surgeonValue) ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Anesthesiologist</span>
                <input class="input" name="anesthesiologist_name" type="text" value="<?= $sanitizer->entities($anesthesiologistValue) ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Assistant</span>
                <input class="input" name="assistant_name" type="text" value="<?= $sanitizer->entities((string) ($opNoteMeta['assistant_name'] ?? '')) ?>" />
              </label>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="field">
                <span class="field__label">Implants Used</span>
                <input class="input" name="implants_used" type="text" value="<?= $sanitizer->entities($implantsUsedValue) ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Patient Position</span>
                <input class="input" name="patient_position" type="text" value="<?= $sanitizer->entities((string) ($opNote ? $opNote->patient_position : '')) ?>" />
              </label>
            </div>
            <div class="form-col">
              <label class="field">
                <span class="field__label">Incision</span>
                <input class="input" name="incision" type="text" value="<?= $sanitizer->entities((string) ($opNoteMeta['incision'] ?? '')) ?>" />
              </label>
            </div>
          </div>

          <label class="field">
            <span class="field__label">Operative Description</span>
            <textarea class="textarea" name="surgical_approach" rows="5"><?= $sanitizer->entities((string) ($opNote ? $opNote->surgical_approach : '')) ?></textarea>
          </label>

          <label class="field">
            <span class="field__label">Tourniquet Time</span>
            <input class="input" name="tourniquet_time" type="text" value="<?= $sanitizer->entities((string) ($opNoteMeta['tourniquet_time'] ?? '')) ?>" placeholder="e.g. 65 min" />
          </label>

          <label class="field">
            <span class="field__label">Closure</span>
            <textarea class="textarea" name="closure_details"><?= $sanitizer->entities((string) ($opNote ? $opNote->closure_details : '')) ?></textarea>
          </label>

          <div class="case-upload-zone case-upload-zone--compact" data-upload-zone="clinical-photos">
            <i data-lucide="upload-cloud" aria-hidden="true"></i>
            <div class="case-upload-zone__title">Intra-operative Media</div>
            <div class="t-meta">Upload photo or video snapshot for this case media bucket</div>
            <input type="file" accept="image/*" multiple />
            <div class="case-upload-zone__results">
              <?php foreach ($case->clinical_images as $file): ?>
              <?php $isImg = $file->ext && in_array(strtolower($file->ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']); ?>
              <div class="case-upload-result">
                <?php if ($isImg && method_exists($file, 'size')): ?>
                <img src="<?= $file->size(96, 96)->url ?>" alt="">
                <?php else: ?>
                <span class="case-upload-result__icon"><i data-lucide="file-text" aria-hidden="true" style="width:24px;height:24px"></i></span>
                <?php endif; ?>
                <a href="<?= $file->url ?>" target="_blank" class="case-upload-result__link"><?= $sanitizer->entities($file->basename) ?></a>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="layout-row layout-row--gap-2 layout-row--end">
            <button class="btn btn-surgery" type="submit">Save Operation Note</button>
          </div>
        </form>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
</section>
