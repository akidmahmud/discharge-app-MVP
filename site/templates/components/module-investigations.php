<section class="card case-module" id="module-5" data-module-step="5">
  <?php $editingInvestigation = $editInvestigationId ? $pages->get($editInvestigationId) : null; ?>
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 5</span>
      <h2 class="card__title">Investigations</h2>
    </div>
    <div class="card__action">
      <div class="layout-row layout-row--gap-2">
        <button class="btn btn--secondary btn--sm" type="button" data-toggle-upload="#module-5">
          <i data-lucide="upload" aria-hidden="true" style="width:14px;height:14px;"></i>
          <span>Upload</span>
        </button>
        <button class="btn btn-diagnosis btn--sm" type="button" data-inline-investigation-trigger>Add Entry</button>
      </div>
    </div>
  </div>
  <div class="case-upload-zone case-upload-zone--compact" data-upload-zone="investigation-reports" style="display:none;margin:0 16px;border-radius:0;border-left:none;border-right:none;border-top:none;">
    <i data-lucide="upload-cloud" aria-hidden="true"></i>
    <div class="case-upload-zone__title">Click to upload or drag &amp; drop</div>
    <div class="t-meta">Upload Reports / Images</div>
    <input type="file" accept="image/*,.pdf" multiple />
    <div class="case-upload-zone__results">
      <?php if (!empty($investigationReportFiles)): ?>
        <?php foreach ($investigationReportFiles as $file): ?>
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
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body layout-stack layout-stack--gap-4">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th class="cell--action"></th>
            <th>Date</th>
            <th>Name</th>
            <th>Report Summary</th>
            <th>Include in discharge</th>
            <th class="cell--action">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($investigations)): ?>
            <?php foreach ($investigations as $index => $investigation): ?>
            <?php
              $includeInDischarge = $fieldsApi->get('include_in_discharge')
                ? (bool) $investigation->getUnformatted('include_in_discharge')
                : true;
              $detailId = 'investigation-detail-' . $index;
            ?>
            <tr>
              <td class="cell cell--action">
                <button class="btn btn--icon" type="button" data-investigation-toggle="#<?= $detailId ?>" aria-label="Toggle findings">
                  <i data-lucide="chevron-down" aria-hidden="true"></i>
                </button>
              </td>
              <td class="cell" data-label="Date"><?= $investigation->getUnformatted('investigation_date') ? date('d M Y', $investigation->getUnformatted('investigation_date')) : '-' ?></td>
              <td class="cell" data-label="Name"><?= $sanitizer->entities($investigation->investigation_name ?: $investigation->title) ?></td>
              <td class="cell" data-label="Report Summary"><?= $hasText($investigation->investigation_findings) ? $sanitizer->entities($investigation->investigation_findings) : 'No summary recorded' ?></td>
              <td class="cell" data-label="Include in discharge"><?= $includeInDischarge ? 'Yes' : 'No' ?></td>
              <td class="cell cell--action" data-label="Actions">
                <div class="layout-row layout-row--gap-2">
                  <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit_inv' => $investigation->id], 'module-5') ?>" aria-label="Edit investigation">
                    <i data-lucide="square-pen" aria-hidden="true"></i>
                  </a>
                  <button class="btn btn--icon btn--destructive" type="button" data-confirm-action="delete_investigation" data-confirm-title="Delete investigation" data-confirm-message="Remove this investigation entry?" data-investigation-id="<?= (int) $investigation->id ?>">
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                  </button>
                </div>
              </td>
            </tr>
            <tr id="<?= $detailId ?>" class="case-investigation-detail" hidden>
              <td class="cell"></td>
              <td class="cell" colspan="5"><?= $hasText($investigation->investigation_findings) ? nl2br($sanitizer->entities($investigation->investigation_findings)) : 'No findings entered.' ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
          <tr>
            <td class="table-empty" colspan="6">No investigations recorded yet.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <form method="post" class="card case-inline-form" data-inline-investigation-form hidden>
      <div class="card__body layout-stack layout-stack--gap-4">
        <?= $session->CSRF->renderInput() ?>
        <input type="hidden" name="save_module" value="investigation" />

        <div class="form-row">
          <div class="form-col">
            <label class="field">
              <span class="field__label">Date</span>
              <input class="input" name="investigation_date" type="date" value="<?= date('Y-m-d') ?>" />
            </label>
          </div>
          <div class="form-col">
            <label class="field">
              <span class="field__label">Include in discharge</span>
              <select class="select" name="include_in_discharge">
                <option value="1" selected>Yes</option>
                <option value="0">No</option>
              </select>
            </label>
          </div>
        </div>

        <label class="field">
          <span class="field__label">Name</span>
          <input class="input" name="investigation_name" type="text" />
        </label>

        <label class="field">
          <span class="field__label">Report Summary</span>
          <textarea class="textarea" name="investigation_findings"></textarea>
        </label>

        <div class="layout-row layout-row--gap-2 layout-row--end">
          <button class="btn btn-diagnosis" type="submit">Save Investigation</button>
        </div>
      </div>
    </form>

    <?php if ($editingInvestigation && $editingInvestigation->id && $editingInvestigation->template->name === 'investigation' && (int) $editingInvestigation->parent_id === (int) $case->id): ?>
    <form method="post" class="card case-inline-form">
      <div class="card__body layout-stack layout-stack--gap-4">
        <?= $session->CSRF->renderInput() ?>
        <input type="hidden" name="save_module" value="update_investigation" />
        <input type="hidden" name="investigation_id" value="<?= (int) $editingInvestigation->id ?>" />
        <div class="layout-row layout-row--between layout-row--align-center">
          <strong>Edit Investigation</strong>
          <a class="btn btn--neutral btn--sm" href="<?= $buildCaseUrl([], 'module-5') ?>">Cancel</a>
        </div>

        <div class="form-row">
          <div class="form-col">
            <label class="field">
              <span class="field__label">Date</span>
              <input class="input" name="investigation_date" type="date" value="<?= $editingInvestigation->getUnformatted('investigation_date') ? date('Y-m-d', $editingInvestigation->getUnformatted('investigation_date')) : '' ?>" />
            </label>
          </div>
          <div class="form-col">
            <label class="field">
              <span class="field__label">Include in discharge</span>
              <select class="select" name="include_in_discharge">
                <option value="1" <?= !$fieldsApi->get('include_in_discharge') || $editingInvestigation->getUnformatted('include_in_discharge') ? 'selected' : '' ?>>Yes</option>
                <option value="0" <?= $fieldsApi->get('include_in_discharge') && !(bool) $editingInvestigation->getUnformatted('include_in_discharge') ? 'selected' : '' ?>>No</option>
              </select>
            </label>
          </div>
        </div>

        <label class="field">
          <span class="field__label">Name</span>
          <input class="input" name="investigation_name" type="text" value="<?= $sanitizer->entities($editingInvestigation->investigation_name ?: $editingInvestigation->title) ?>" />
        </label>

        <label class="field">
          <span class="field__label">Report Summary</span>
          <textarea class="textarea" name="investigation_findings"><?= $sanitizer->entities((string) $editingInvestigation->investigation_findings) ?></textarea>
        </label>

        <div class="layout-row layout-row--gap-2 layout-row--end">
          <button class="btn btn-diagnosis" type="submit">Update Investigation</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

  </div>
</section>
