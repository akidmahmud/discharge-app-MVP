<?php
$secondaryPhone = ($patient && $fieldsApi->get('secondary_phone')) ? (string) $patient->secondary_phone : '';
$isEditingAdmission = $editModule === 'admission' || !$hasText($case->room_bed);
$admissionViewRows = [
  ['label' => 'Patient ID', 'value' => $case->ip_number ?: '-'],
  ['label' => 'Name', 'value' => $patientName],
  ['label' => 'Age / Unit', 'value' => ($ageValue !== '' ? $ageValue . ' ' . $ageUnitLabel : 'Not recorded')],
  ['label' => 'Sex', 'value' => $genderLabel],
  ['label' => 'Phone 1', 'value' => $patient && $patient->phone ? $patient->phone : 'Not recorded'],
  ['label' => 'Phone 2', 'value' => $secondaryPhone !== '' ? $secondaryPhone : 'Not recorded'],
  ['label' => 'Guardian', 'value' => $patient && $patient->guardian_name ? $patient->guardian_name : 'Not recorded'],
  ['label' => 'Address', 'value' => $patient && $patient->address ? $patient->address : 'Not recorded'],
  ['label' => 'Bed', 'value' => $case->room_bed ?: 'Not recorded'],
  ['label' => 'Consultant', 'value' => $consultantName],
  ['label' => 'Admission Date', 'value' => $admittedOn ? date('d M Y', $admittedOn) : 'Not recorded'],
  ['label' => 'Discharge Date', 'value' => $dischargedOn ? date('d M Y', $dischargedOn) : 'Not recorded'],
];
?>
<section class="card case-module" id="module-1" data-module-step="1">
  <div class="card__header">
    <div class="card__title-group case-module__title-group">
      <span class="badge badge--dc-draft">Step 1</span>
      <h2 class="card__title">Admission</h2>
    </div>
    <div class="card__action">
      <?php if (!$isEditingAdmission): ?>
      <a class="btn btn--icon" href="<?= $buildCaseUrl(['edit' => 'admission'], 'module-1') ?>" aria-label="Edit admission" title="Edit admission">
        <i data-lucide="square-pen" aria-hidden="true"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card__body">
    <?php if (!$isEditingAdmission): ?>
    <div class="layout-2col">
      <?php foreach ($admissionViewRows as $row): ?>
      <div class="card__row">
        <div class="card__row-label"><?= $sanitizer->entities($row['label']) ?></div>
        <div class="card__row-value"><?= nl2br($sanitizer->entities($row['value'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <form method="post" class="layout-stack layout-stack--gap-4">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="save_module" value="admission" />

      <div class="layout-2col">
        <label class="field">
          <span class="field__label">Patient ID</span>
          <input class="input" type="text" value="<?= $sanitizer->entities($case->ip_number ?: '-') ?>" readonly />
        </label>
        <label class="field">
          <span class="field__label">Name</span>
          <input class="input" name="patient_name" type="text" value="<?= $sanitizer->entities($patientName) ?>" />
        </label>
      </div>

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Age</span>
            <input class="input" name="patient_age" type="number" min="0" value="<?= $sanitizer->entities((string) $case->patient_age) ?>" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Age Unit</span>
            <select class="select" name="age_unit">
              <option value="Years" <?= $ageUnitLabel === 'Years' ? 'selected' : '' ?>>Years</option>
              <option value="Months" <?= $ageUnitLabel === 'Months' ? 'selected' : '' ?>>Months</option>
              <option value="Days" <?= $ageUnitLabel === 'Days' ? 'selected' : '' ?>>Days</option>
            </select>
          </label>
        </div>
      </div>

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Sex</span>
            <select class="select" name="gender">
              <option value="">Select sex</option>
              <option value="Male" <?= $genderLabel === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= $genderLabel === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= $genderLabel === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Phone 1</span>
            <input class="input" name="phone" type="text" value="<?= $sanitizer->entities((string) ($patient ? $patient->phone : '')) ?>" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Phone 2</span>
            <input class="input" name="phone_secondary" type="text" value="<?= $sanitizer->entities($secondaryPhone) ?>" />
          </label>
        </div>
      </div>

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Guardian</span>
            <input class="input" name="guardian" type="text" value="<?= $sanitizer->entities((string) ($patient ? $patient->guardian_name : '')) ?>" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Consultant</span>
            <input class="input" type="text" value="Dr. Md. Tawfiq Alam Siddique" readonly />
            <input type="hidden" name="consultant_label" value="Dr. Md. Tawfiq Alam Siddique" />
          </label>
        </div>
      </div>

      <label class="field">
        <span class="field__label">Address</span>
        <textarea class="textarea" name="address"><?= $sanitizer->entities((string) ($patient ? $patient->address : '')) ?></textarea>
      </label>

      <label class="field">
        <span class="field__label">Bed</span>
        <input class="input" name="room_bed" type="text" value="<?= $sanitizer->entities((string) $case->room_bed) ?>" />
      </label>

      <div class="form-row">
        <div class="form-col">
          <label class="field">
            <span class="field__label">Admission Date</span>
            <input class="input" name="admission_date" type="date" value="<?= $admittedOn ? date('Y-m-d', $admittedOn) : '' ?>" />
          </label>
        </div>
        <div class="form-col">
          <label class="field">
            <span class="field__label">Discharge Date</span>
            <input class="input" name="discharge_date" type="date" value="<?= $dischargedOn ? date('Y-m-d', $dischargedOn) : '' ?>" />
          </label>
        </div>
      </div>

      <div class="layout-row layout-row--gap-2 layout-row--end">
        <a class="btn btn--neutral" href="<?= $buildCaseUrl([], 'module-1') ?>">Cancel</a>
        <button class="btn btn--primary" type="submit">Save Admission</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
