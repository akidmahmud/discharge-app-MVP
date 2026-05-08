<?php
$isEditingHistory = $editModule === 'history' || !$hasText($case->chief_complaint);
?>
<section class="card case-module" id="module-3" data-module-step="3">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 3</span>
      <h2 class="card__title">History</h2>
    </div>
    <div class="card__action">
      <?php if ($isEditingHistory): ?>
      <div class="layout-row layout-row--gap-2">
        <button class="btn btn--neutral btn--sm" type="button" data-template-add="history" data-template-target="textarea[name='history_present_illness']" data-template-field="history_present_illness">Add Template</button>
        <button class="btn btn--neutral btn--sm" type="button" data-template-create="history" data-template-field="history_present_illness">Create Template</button>
      </div>
      <?php endif; ?>
      <?php if (!$isEditingHistory): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'history'], 'module-3') ?>" aria-label="Edit history" title="Edit history">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingHistory): ?>
    <div class="layout-stack layout-stack--gap-4">
      <div class="card__row">
        <div class="card__row-label">Chief Complaint</div>
        <div class="card__row-value"><?= $hasText($case->chief_complaint) ? nl2br($sanitizer->entities($case->chief_complaint)) : 'Not recorded' ?></div>
      </div>
      <div class="card__row">
        <div class="card__row-label">History of Present Illness</div>
        <div class="card__row-value"><?= $hasText($historyOfPresentIllness) ? nl2br($sanitizer->entities($historyOfPresentIllness)) : 'Not recorded' ?></div>
      </div>
      <?php if ($hasText($pastMedicalHistory)): ?>
      <div class="card__row">
        <div class="card__row-label">Past Medical History</div>
        <div class="card__row-value"><?= nl2br($sanitizer->entities($pastMedicalHistory)) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($hasText($pastSurgicalHistory)): ?>
      <div class="card__row">
        <div class="card__row-label">Past Surgical History</div>
        <div class="card__row-value"><?= nl2br($sanitizer->entities($pastSurgicalHistory)) ?></div>
      </div>
      <?php endif; ?>

      <?php if ($comorbidityNone): ?>
      <div class="card__row">
        <div class="card__row-label">Comorbidities</div>
        <div class="card__row-value">None</div>
      </div>
      <?php elseif ($comorbidityFlags || $comorbidityDrugRows): ?>
      <div class="layout-stack layout-stack--gap-2">
        <div class="field__label">Comorbidities</div>
        <div class="case-chip-list">
          <?php foreach ($knownComorbidityFlags as $flag): ?>
          <?php if (!in_array($flag, $comorbidityFlags, true)) continue; ?>
          <span class="case-chip is-selected">
            <?= $sanitizer->entities($flag) ?><?= isset($comorbidityDrugCounts[$flag]) ? ' (' . (int) $comorbidityDrugCounts[$flag] . ' drugs)' : '' ?>
          </span>
          <?php endforeach; ?>
          <?php foreach ($comorbidityCustomConditions as $customCondition): ?>
          <span class="case-chip is-selected">
            <?= $sanitizer->entities($customCondition) ?><?= isset($comorbidityDrugCounts[$customCondition]) ? ' (' . (int) $comorbidityDrugCounts[$customCondition] . ' drugs)' : '' ?>
          </span>
          <?php endforeach; ?>
        </div>
        <div class="layout-stack layout-stack--gap-3">
          <?php foreach ($knownComorbidityFlags as $flag): ?>
          <?php if (!in_array($flag, $comorbidityFlags, true)) continue; ?>
          <div class="case-chip-detail">
            <div class="field__label"><?= $sanitizer->entities($flag) ?></div>
            <?php $flagRows = array_values(array_filter($comorbidityDrugRows, function ($row) use ($flag) { return $row['condition'] === $flag; })); ?>
            <?php if ($flagRows): ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Drug Name</th>
                    <th>Dose</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($flagRows as $row): ?>
                  <tr>
                    <td class="cell"><?= $sanitizer->entities($row['drug_name']) ?></td>
                    <td class="cell"><?= $sanitizer->entities($row['drug_dose']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="card__row-value">No drugs recorded</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <?php foreach ($comorbidityCustomConditions as $customCondition): ?>
          <?php $customRows = array_values(array_filter($comorbidityDrugRows, function ($row) use ($customCondition) { return $row['condition'] === $customCondition; })); ?>
          <div class="case-chip-detail">
            <div class="field__label"><?= $sanitizer->entities($customCondition) ?></div>
            <?php if ($customRows): ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Drug Name</th>
                    <th>Dose</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($customRows as $row): ?>
                  <tr>
                    <td class="cell"><?= $sanitizer->entities($row['drug_name']) ?></td>
                    <td class="cell"><?= $sanitizer->entities($row['drug_dose']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="card__row-value">No drugs recorded</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card__row">
        <div class="card__row-label">Comorbidities</div>
        <div class="card__row-value">None recorded</div>
      </div>
      <?php endif; ?>

      <?php
      $hasDrugHistoryData = false;
      foreach ($drugHistoryEntries as $entry) {
          if (($entry['drug_name'] ?? '') !== '') {
              $hasDrugHistoryData = true;
              break;
          }
      }
      ?>
      <?php if ($hasDrugHistoryData): ?>
      <div class="layout-stack layout-stack--gap-2">
        <div class="layout-row layout-row--between">
          <div class="field__label">Drug History (Ongoing Medication)</div>
          <?php if ($drugHistoryReviewed): ?>
          <span class="badge badge--dc-ready">Reviewed</span>
          <?php endif; ?>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Drug</th>
                <th>Dose</th>
                <th>Frequency</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($drugHistoryEntries as $entry): ?>
              <?php if (($entry['drug_name'] ?? '') === '') continue; ?>
              <tr>
                <td class="cell"><?= $sanitizer->entities($entry['drug_name']) ?></td>
                <td class="cell"><?= $sanitizer->entities($entry['drug_dose']) ?></td>
                <td class="cell"><?= $sanitizer->entities($entry['drug_frequency']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="card__row">
        <div class="card__row-label">Drug History</div>
        <div class="card__row-value"><?= $drugHistoryReviewed ? 'Reviewed - no ongoing medications recorded' : 'Not reviewed yet' ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="history" />

      <label class="field">
        <span class="field__label">Chief Complaint <span class="field__required">*</span></span>
        <textarea class="textarea" name="chief_complaint" rows="4"><?= $sanitizer->entities((string) $case->chief_complaint) ?></textarea>
      </label>

      <label class="field">
        <span class="field__label">History of Present Illness</span>
        <textarea class="textarea" name="history_present_illness" rows="4"><?= $sanitizer->entities($historyOfPresentIllness) ?></textarea>
      </label>

      <label class="field">
        <span class="field__label">Past Medical History</span>
        <textarea class="textarea" name="past_medical_history" rows="3"><?= $sanitizer->entities($pastMedicalHistory) ?></textarea>
      </label>

      <label class="field">
        <span class="field__label">Past Surgical History</span>
        <textarea class="textarea" name="past_surgical_history" rows="3"><?= $sanitizer->entities($pastSurgicalHistory) ?></textarea>
      </label>

      <div
        class="layout-stack layout-stack--gap-3 case-comorbidity"
        data-comorbidity-widget
        data-none="<?= $comorbidityNone ? '1' : '0' ?>"
        data-known-flags="<?= $sanitizer->entities(json_encode($knownComorbidityFlags)) ?>"
        data-selected-flags="<?= $sanitizer->entities(json_encode($comorbidityFlags)) ?>"
        data-custom-conditions="<?= $sanitizer->entities(json_encode($comorbidityCustomConditions)) ?>"
        data-drugs="<?= $sanitizer->entities(json_encode($comorbidityDrugRows)) ?>"
      >
        <div class="layout-stack layout-stack--gap-2">
          <div class="field__label">Comorbidities</div>
          <div class="field__hint">Select `None` or one or more conditions. Each selected condition gets its own structured drug rows.</div>
          <input type="hidden" name="comorbidity_none" value="<?= $comorbidityNone ? '1' : '0' ?>" data-comorbidity-none-input />
          <div data-comorbidity-flags-inputs></div>
          <div data-comorbidity-custom-inputs></div>
          <div class="case-chip-list" data-comorbidity-chip-list>
            <button class="case-chip<?= $comorbidityNone ? ' is-selected' : '' ?>" type="button" data-comorbidity-toggle="None">None</button>
            <?php foreach ($knownComorbidityFlags as $flag): ?>
            <button class="case-chip<?= in_array($flag, $comorbidityFlags, true) ? ' is-selected' : '' ?>" type="button" data-comorbidity-toggle="<?= $sanitizer->entities($flag) ?>"><?= $sanitizer->entities($flag) ?></button>
            <?php endforeach; ?>
            <button class="case-chip<?= in_array('Custom', $comorbidityFlags, true) || $comorbidityCustomConditions ? ' is-selected' : '' ?>" type="button" data-comorbidity-custom-trigger>+ Custom</button>
          </div>
          <div class="case-chip-list" data-comorbidity-custom-chip-list></div>
        </div>

        <div class="case-chip-custom-input" data-comorbidity-custom-input hidden>
          <div class="layout-row layout-row--gap-2">
            <input class="input" type="text" placeholder="Enter custom comorbidity" data-comorbidity-custom-text />
            <button class="btn btn--neutral btn--sm" type="button" data-add-custom-comorbidity>Add</button>
          </div>
        </div>

        <div class="layout-stack layout-stack--gap-3" data-comorbidity-detail-list></div>
        <div class="case-comorbidity__empty" data-comorbidity-empty hidden>
          Drug rows stay hidden while `None` is selected.
        </div>
      </div>

      <div class="layout-stack layout-stack--gap-2">
        <div class="layout-row layout-row--between">
          <span class="field__label">Drug History (Ongoing Medication)</span>
          <button class="btn btn--neutral btn--sm" type="button" data-drug-history-add="#drug-history-list">+ Add Drug</button>
        </div>
        <div class="field__hint">Record medicines the patient was already taking before this admission. This is separate from condition-linked comorbidity drugs.</div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Drug</th>
                <th>Dose</th>
                <th>Frequency</th>
                <th class="cell--action"></th>
              </tr>
            </thead>
            <tbody id="drug-history-list">
              <?php foreach ($drugHistoryEntries as $index => $entry): ?>
              <tr class="case-repeat-row"<?= $index === 0 ? ' data-drug-history-template' : '' ?>>
                <td class="cell"><input class="input" name="drug_hist_name[]" type="text" value="<?= $sanitizer->entities($entry['drug_name']) ?>" placeholder="e.g. Metformin" /></td>
                <td class="cell"><input class="input" name="drug_hist_dose[]" type="text" value="<?= $sanitizer->entities($entry['drug_dose']) ?>" placeholder="e.g. 500mg" /></td>
                <td class="cell"><input class="input" name="drug_hist_freq[]" type="text" value="<?= $sanitizer->entities($entry['drug_frequency']) ?>" placeholder="e.g. 1-0-1" /></td>
                <td class="cell cell--action">
                  <button class="btn btn--icon btn--destructive" type="button" data-drug-history-remove aria-label="Remove drug row">
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-3') ?>">Cancel</a>
        <button class="btn btn-diagnosis" type="submit">Save History</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
