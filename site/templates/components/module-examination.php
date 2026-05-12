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
      <div class="layout-row layout-row--gap-2">
        <button class="btn btn--secondary btn--sm" type="button" data-toggle-upload="#module-4">
          <i data-lucide="upload" aria-hidden="true" style="width:14px;height:14px;"></i>
          <span>Upload</span>
        </button>
        <?php if (!$isEditingExamination): ?>
        <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'examination'], 'module-4') ?>" aria-label="Edit examination" title="Edit examination">
          <i data-lucide="square-pen" aria-hidden="true"></i>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="case-upload-zone case-upload-zone--compact" data-upload-zone="clinical-photos" style="display:none;margin:0 16px;border-radius:0;border-left:none;border-right:none;border-top:none;">
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
  <div class="card__body">
    <?php if (!$isEditingExamination): ?>
    <div class="layout-stack layout-stack--gap-4">
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

      <label class="field">
        <span class="field__label">General Findings <span class="field__required">*</span></span>
        <div class="field__hint">Mode: Free text. Use template shortcuts for common findings.</div>
        <textarea class="textarea" name="inspection" rows="4"><?= $sanitizer->entities((string) $case->inspection) ?></textarea>
      </label>
      <label class="field">
        <span class="field__label">Local Findings</span>
        <textarea class="textarea" name="examination_findings" rows="4"><?= $sanitizer->entities((string) $case->examination_findings) ?></textarea>
      </label>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-4') ?>">Cancel</a>
        <button class="btn btn-diagnosis" type="submit">Save Changes</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
