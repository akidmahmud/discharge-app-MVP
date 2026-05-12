<?php
$modalDefaults = [
  'name' => '',
  'age' => '',
  'age_unit' => 'Years',
  'gender' => '',
  'phone' => '',
  'phone_secondary' => '',
  'guardian' => '',
  'address' => '',
  'bed' => '',
  'consultant' => 'Dr. Md. Tawfiq Alam Siddique',
  'admission_date' => date('Y-m-d'),
];

$modalForm = array_merge($modalDefaults, $patientFormData);
?>
<div class="modal modal--md" data-modal="add-patient-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="add-patient-modal-title">
  <div class="modal__header">
    <div class="card__title-group">
      <h2 class="card__title" id="add-patient-modal-title">Add New Patient</h2>
      <p class="card__subtitle">Create a patient record and first admission.</p>
    </div>
    <button class="btn btn--icon" type="button" data-modal-close aria-label="Close modal">
      <i data-lucide="x" aria-hidden="true"></i>
    </button>
  </div>

  <div class="modal__body">
    <?php if (count($patientErrors)): ?>
    <div class="field__error" style="display:block;margin-bottom:12px;">
      <?= $sanitizer->entities(implode(' ', $patientErrors)) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/patients/" id="add-patient-form">
      <?= $session->CSRF->renderInput() ?>
      <input type="hidden" name="action" value="add_patient" />

      <div class="layout-stack layout-stack--gap-4">
        <div class="form-row">
          <div class="form-col form-col--full">
            <div class="field" id="add-patient-name-field">
              <label class="field__label" for="add-patient-name">Patient Name <span class="field__required">*</span></label>
              <input class="input" id="add-patient-name" name="name" type="text" value="<?= $sanitizer->entities($modalForm['name']) ?>" required />
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--quarter">
            <div class="field" id="add-patient-age-field">
              <label class="field__label" for="add-patient-age">Age</label>
              <input class="input" id="add-patient-age" name="age" type="number" min="0" value="<?= $sanitizer->entities((string) $modalForm['age']) ?>" />
            </div>
          </div>
          <div class="form-col form-col--quarter">
            <div class="field" id="add-patient-age-unit-field">
              <label class="field__label" for="add-patient-age-unit">Age Unit</label>
              <select class="select" id="add-patient-age-unit" name="age_unit">
                <option value="Years" <?= $modalForm['age_unit'] === 'Years' ? 'selected' : '' ?>>Years</option>
                <option value="Months" <?= $modalForm['age_unit'] === 'Months' ? 'selected' : '' ?>>Months</option>
              </select>
            </div>
          </div>
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-gender-field">
              <label class="field__label" for="add-patient-gender">Sex</label>
              <select class="select" id="add-patient-gender" name="gender">
                <option value="">Select sex</option>
                <option value="Male" <?= $modalForm['gender'] === 'Male' ? 'selected' : '' ?>>M</option>
                <option value="Female" <?= $modalForm['gender'] === 'Female' ? 'selected' : '' ?>>F</option>
                <option value="Other" <?= $modalForm['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-phone-field">
              <label class="field__label" for="add-patient-phone">Phone 1</label>
              <input class="input" id="add-patient-phone" name="phone" type="text" value="<?= $sanitizer->entities($modalForm['phone']) ?>" />
            </div>
          </div>
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-phone-secondary-field">
              <label class="field__label" for="add-patient-phone-secondary">Phone 2</label>
              <input class="input" id="add-patient-phone-secondary" name="phone_secondary" type="text" value="<?= $sanitizer->entities($modalForm['phone_secondary']) ?>" />
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-guardian-field">
              <label class="field__label" for="add-patient-guardian">Guardian</label>
              <input class="input" id="add-patient-guardian" name="guardian" type="text" value="<?= $sanitizer->entities($modalForm['guardian']) ?>" />
            </div>
          </div>
          <div class="form-col form-col--half"></div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--full">
            <div class="field" id="add-patient-address-field">
              <label class="field__label" for="add-patient-address">Address</label>
              <textarea class="textarea" id="add-patient-address" name="address"><?= $sanitizer->entities($modalForm['address']) ?></textarea>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-bed-field">
              <label class="field__label" for="add-patient-bed">Bed No</label>
              <input class="input" id="add-patient-bed" name="bed" type="text" value="<?= $sanitizer->entities($modalForm['bed']) ?>" />
            </div>
          </div>
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-consultant-field">
              <label class="field__label" for="add-patient-consultant">Consultant</label>
              <input class="input" id="add-patient-consultant" type="text" value="<?= $sanitizer->entities($modalForm['consultant']) ?>" readonly />
              <input type="hidden" name="consultant" value="<?= $sanitizer->entities($modalForm['consultant']) ?>" />
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-col--half">
            <div class="field" id="add-patient-admission-date-field">
              <label class="field__label" for="add-patient-admission-date">Admission Date <span class="field__required">*</span></label>
              <input class="input" id="add-patient-admission-date" name="admission_date" type="date" value="<?= $sanitizer->entities($modalForm['admission_date']) ?>" required />
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="modal__footer">
    <button class="btn btn--neutral" type="button" data-modal-close>Cancel</button>
    <button class="btn btn--primary" type="submit" form="add-patient-form">Save Patient</button>
  </div>
</div>
<script>
  (function () {
    function byId(id) { return document.getElementById(id); }
    function fieldHostByFor(id) {
      var label = document.querySelector('label[for="' + id + '"]');
      return label ? label.parentElement : null;
    }
    function ensureFieldInput(cfg) {
      var host = fieldHostByFor(cfg.id) || byId(cfg.hostId);
      if (!host || byId(cfg.id)) return;
      var input = document.createElement('input');
      input.className = 'input';
      input.id = cfg.id;
      input.name = cfg.name;
      input.type = cfg.type || 'text';
      if (cfg.min !== undefined) input.min = cfg.min;
      if (cfg.required) input.required = true;
      input.value = cfg.value || '';
      host.appendChild(input);
    }
    function ensureFieldTextarea(cfg) {
      var host = fieldHostByFor(cfg.id) || byId(cfg.hostId);
      if (!host || byId(cfg.id)) return;
      var textarea = document.createElement('textarea');
      textarea.className = 'textarea';
      textarea.id = cfg.id;
      textarea.name = cfg.name;
      textarea.textContent = cfg.value || '';
      host.appendChild(textarea);
    }
    function ensureFieldSelect(cfg) {
      var host = fieldHostByFor(cfg.id) || byId(cfg.hostId);
      if (!host || byId(cfg.id)) return;
      var select = document.createElement('select');
      select.className = 'select';
      select.id = cfg.id;
      select.name = cfg.name;
      (cfg.options || []).forEach(function (opt) {
        var option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        if ((cfg.value || '') === opt.value) option.selected = true;
        select.appendChild(option);
      });
      host.appendChild(select);
    }
    function ensureHiddenInput(name, value) {
      var form = document.querySelector('[data-modal="add-patient-modal"] form');
      if (!form) return null;
      var el = form.querySelector('input[type="hidden"][name="' + name + '"]');
      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = name;
        form.appendChild(el);
      }
      if (typeof value !== 'undefined') el.value = value;
      return el;
    }
    function ensureEditableFallback(cfg) {
      var host = fieldHostByFor(cfg.id) || byId(cfg.hostId);
      if (!host) return;
      if (byId(cfg.id)) return; // Native control exists.
      var fallbackId = cfg.id + '-fallback';
      if (byId(fallbackId)) return;
      var proxy = document.createElement('div');
      proxy.id = fallbackId;
      proxy.className = 'input';
      proxy.setAttribute('contenteditable', 'true');
      proxy.setAttribute('data-fallback-name', cfg.name);
      proxy.style.minHeight = cfg.multiline ? '80px' : '';
      proxy.style.height = cfg.multiline ? 'auto' : '';
      proxy.style.paddingTop = cfg.multiline ? '8px' : '';
      proxy.style.lineHeight = cfg.multiline ? '1.4' : '';
      proxy.textContent = cfg.value || '';
      host.appendChild(proxy);
      ensureHiddenInput(cfg.name, cfg.value || '');
      proxy.addEventListener('input', function () {
        var hidden = ensureHiddenInput(cfg.name);
        if (hidden) hidden.value = (proxy.textContent || '').trim();
      });
    }

    var values = <?= json_encode([
        'name' => (string) ($modalForm['name'] ?? ''),
        'age' => (string) ($modalForm['age'] ?? ''),
        'age_unit' => (string) ($modalForm['age_unit'] ?? 'Years'),
        'gender' => (string) ($modalForm['gender'] ?? ''),
        'phone' => (string) ($modalForm['phone'] ?? ''),
        'phone_secondary' => (string) ($modalForm['phone_secondary'] ?? ''),
        'guardian' => (string) ($modalForm['guardian'] ?? ''),
        'address' => (string) ($modalForm['address'] ?? ''),
        'bed' => (string) ($modalForm['bed'] ?? ''),
        'admission_date' => (string) ($modalForm['admission_date'] ?? date('Y-m-d')),
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function ensureAllAddPatientFields() {
      ensureFieldInput({ hostId: 'add-patient-name-field', id: 'add-patient-name', name: 'name', type: 'text', required: true, value: values.name });
      ensureFieldInput({ hostId: 'add-patient-age-field', id: 'add-patient-age', name: 'age', type: 'number', min: '0', value: values.age });
      ensureFieldSelect({
        hostId: 'add-patient-age-unit-field',
        id: 'add-patient-age-unit',
        name: 'age_unit',
        value: values.age_unit,
        options: [{ value: 'Years', label: 'Years' }, { value: 'Months', label: 'Months' }]
      });
      ensureFieldSelect({
        hostId: 'add-patient-gender-field',
        id: 'add-patient-gender',
        name: 'gender',
        value: values.gender,
        options: [
          { value: '', label: 'Select sex' },
          { value: 'Male', label: 'M' },
          { value: 'Female', label: 'F' },
          { value: 'Other', label: 'Other' }
        ]
      });
      ensureFieldInput({ hostId: 'add-patient-phone-field', id: 'add-patient-phone', name: 'phone', type: 'text', value: values.phone });
      ensureFieldInput({ hostId: 'add-patient-phone-secondary-field', id: 'add-patient-phone-secondary', name: 'phone_secondary', type: 'text', value: values.phone_secondary });
      ensureFieldInput({ hostId: 'add-patient-guardian-field', id: 'add-patient-guardian', name: 'guardian', type: 'text', value: values.guardian });
      ensureFieldTextarea({ hostId: 'add-patient-address-field', id: 'add-patient-address', name: 'address', value: values.address });
      ensureFieldInput({ hostId: 'add-patient-bed-field', id: 'add-patient-bed', name: 'bed', type: 'text', value: values.bed });
      ensureFieldInput({ hostId: 'add-patient-admission-date-field', id: 'add-patient-admission-date', name: 'admission_date', type: 'date', required: true, value: values.admission_date });
    }
    function ensureEditableFallbacks() {
      ensureEditableFallback({ hostId: 'add-patient-name-field', id: 'add-patient-name', name: 'name', value: values.name });
      ensureEditableFallback({ hostId: 'add-patient-age-field', id: 'add-patient-age', name: 'age', value: values.age });
      ensureEditableFallback({ hostId: 'add-patient-age-unit-field', id: 'add-patient-age-unit', name: 'age_unit', value: values.age_unit });
      ensureEditableFallback({ hostId: 'add-patient-gender-field', id: 'add-patient-gender', name: 'gender', value: values.gender });
      ensureEditableFallback({ hostId: 'add-patient-phone-field', id: 'add-patient-phone', name: 'phone', value: values.phone });
      ensureEditableFallback({ hostId: 'add-patient-phone-secondary-field', id: 'add-patient-phone-secondary', name: 'phone_secondary', value: values.phone_secondary });
      ensureEditableFallback({ hostId: 'add-patient-guardian-field', id: 'add-patient-guardian', name: 'guardian', value: values.guardian });
      ensureEditableFallback({ hostId: 'add-patient-address-field', id: 'add-patient-address', name: 'address', value: values.address, multiline: true });
      ensureEditableFallback({ hostId: 'add-patient-bed-field', id: 'add-patient-bed', name: 'bed', value: values.bed });
      ensureEditableFallback({ hostId: 'add-patient-admission-date-field', id: 'add-patient-admission-date', name: 'admission_date', value: values.admission_date });
    }
    function syncFallbackValuesBeforeSubmit() {
      var form = document.querySelector('[data-modal="add-patient-modal"] form');
      if (!form) return;
      form.addEventListener('submit', function () {
        form.querySelectorAll('[data-fallback-name]').forEach(function (node) {
          var name = node.getAttribute('data-fallback-name');
          var hidden = ensureHiddenInput(name);
          if (hidden) hidden.value = (node.textContent || '').trim();
        });
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      ensureAllAddPatientFields();
      ensureEditableFallbacks();
      syncFallbackValuesBeforeSubmit();
      // Re-check after all global scripts run.
      setTimeout(ensureAllAddPatientFields, 100);
      setTimeout(ensureAllAddPatientFields, 400);
      setTimeout(ensureEditableFallbacks, 100);
      setTimeout(ensureEditableFallbacks, 400);

      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-modal-trigger="add-patient-modal"]');
        if (!trigger) return;
        setTimeout(ensureAllAddPatientFields, 10);
        setTimeout(ensureAllAddPatientFields, 120);
        setTimeout(ensureEditableFallbacks, 10);
        setTimeout(ensureEditableFallbacks, 120);
      });
    });
  })();
</script>
