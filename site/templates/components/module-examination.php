<?php
$isEditingExamination = $editModule === 'examination' || !$hasText($case->inspection);
?>
<section class="card case-module" id="module-4" data-module-step="4">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 4</span>
      <h2 class="card__title">Examination</h2>
    </div>
    <div class="card__action">
      <?php if ($isEditingExamination): ?>
      <div class="layout-row layout-row--gap-2">
        <button class="btn btn--neutral btn--sm" type="button" data-template-add="examination" data-template-target="textarea[name='examination_findings']" data-template-field="examination_findings">Add Template</button>
        <button class="btn btn--neutral btn--sm" type="button" data-template-create="examination" data-template-field="examination_findings">Create Template</button>
      </div>
      <?php endif; ?>
      <?php if (!$isEditingExamination): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'examination'], 'module-4') ?>" aria-label="Edit examination" title="Edit examination">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingExamination): ?>
    <div class="layout-stack layout-stack--gap-4">
      <?php
      $vitalsExist = $case->vitals_pulse || $case->vitals_bp || $case->vitals_temp || $case->vitals_spo2;
      ?>
      <?php if ($vitalsExist): ?>
      <div class="layout-stack layout-stack--gap-2">
        <div class="field__label">Vitals</div>
        <div class="layout-row layout-row--gap-4">
          <?php if ($case->vitals_pulse): ?><span><strong>Pulse</strong> <?= $sanitizer->entities($case->vitals_pulse) ?></span><?php endif; ?>
          <?php if ($case->vitals_bp): ?><span><strong>BP</strong> <?= $sanitizer->entities($case->vitals_bp) ?></span><?php endif; ?>
          <?php if ($case->vitals_temp): ?><span><strong>Temp</strong> <?= $sanitizer->entities($case->vitals_temp) ?>°C</span><?php endif; ?>
          <?php if ($case->vitals_spo2): ?><span><strong>SpO2</strong> <?= $sanitizer->entities($case->vitals_spo2) ?>%</span><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="card__row">
        <div class="card__row-label">General Findings</div>
        <div class="card__row-value"><?= $hasText($case->inspection) ? nl2br($sanitizer->entities($case->inspection)) : 'Not recorded' ?></div>
      </div>
      <div class="card__row">
        <div class="card__row-label">Local Findings</div>
        <div class="card__row-value"><?= $hasText($case->examination_findings) ? nl2br($sanitizer->entities($case->examination_findings)) : 'Not recorded' ?></div>
      </div>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="examination" />

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Pulse</span>
            <input class="input" name="vitals_pulse" type="text" value="<?= $sanitizer->entities((string) $case->vitals_pulse) ?>" placeholder="e.g. 72 bpm" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">BP</span>
            <input class="input" name="vitals_bp" type="text" value="<?= $sanitizer->entities((string) $case->vitals_bp) ?>" placeholder="e.g. 120/80" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Temp (°C)</span>
            <input class="input" name="vitals_temp" type="text" value="<?= $sanitizer->entities((string) $case->vitals_temp) ?>" placeholder="e.g. 37.2" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">SpO2 (%)</span>
            <input class="input" name="vitals_spo2" type="text" value="<?= $sanitizer->entities((string) $case->vitals_spo2) ?>" placeholder="e.g. 98" />
          </label>
        </div>
      </div>

      <label class="field">
        <span class="field__label">General Findings <span class="field__required">*</span></span>
        <div class="field__hint">Mode: Free text. Use template shortcuts for common findings.</div>
        <textarea class="textarea" name="inspection" rows="4"><?= $sanitizer->entities((string) $case->inspection) ?></textarea>
      </label>
      <label class="field">
        <span class="field__label">Local Findings</span>
        <textarea class="textarea" name="examination_findings" rows="4"><?= $sanitizer->entities((string) $case->examination_findings) ?></textarea>
      </label>

      <div class="case-upload-zone case-upload-zone--compact" data-upload-zone="clinical-photos" style="min-height:48px;padding:8px 16px;">
        <i data-lucide="upload-cloud" aria-hidden="true"></i>
        <div class="case-upload-zone__title">Pre-operative Images</div>
        <div class="t-meta">Upload examination photos or X-ray snapshots</div>
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
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-4') ?>">Cancel</a>
        <button class="btn btn-diagnosis" type="submit">Save Changes</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
