<?php namespace ProcessWire;

// ── Page-level permission checks ──────────────────────────────────────────────
$canSee = wire('dashPerm');
if ($canSee && !$canSee('page_all_patients')) {
    $session->redirect('/dashboard/?unauthorized=1');
}
$viewParam = $sanitizer->text($input->get->view);
if ($viewParam === 'pending' && $canSee && !$canSee('page_pending_discharge')) {
    $session->redirect('/dashboard/?unauthorized=1');
}

$patientPostAction = $sanitizer->text($input->post->action);
if (in_array($patientPostAction, ['add_patient', 'add_patient_module'], true)) {
    $redirectUrl = $pages->get('/patients/')->url ?: '/patients/';
    $useNewAddModule = $patientPostAction === 'add_patient_module';
    $errorRedirect = $redirectUrl . ($useNewAddModule ? '?new_add=1&error=1' : '?error=1');
    $errors = [];
    $fixedConsultantName = 'Dr. Md. Tawfiq Alam Siddique';

    if (!$session->CSRF->validate()) {
        $session->setFor('patient', 'errors', ['Your session expired. Please try submitting the form again.']);
        $session->setFor('patient', 'form', []);
        $session->redirect($errorRedirect);
    }

    $name = $sanitizer->text($input->post->name);
    $age = (int) $sanitizer->int($input->post->age);
    $ageUnit = $sanitizer->text($input->post->age_unit);
    $gender = $sanitizer->text($input->post->gender);
    $phone = $sanitizer->text($input->post->phone);
    $phoneSecondary = $sanitizer->text($input->post->phone_secondary);
    $guardian = $sanitizer->text($input->post->guardian);
    $address = $sanitizer->textarea($input->post->address);
    $bed = $sanitizer->text($input->post->bed);
    $consultant = $fixedConsultantName;
    $admissionDate = $sanitizer->date($input->post->admission_date, 'Y-m-d');

    $formData = [
        'name' => $name,
        'age' => $age,
        'age_unit' => $ageUnit,
        'gender' => $gender,
        'phone' => $phone,
        'phone_secondary' => $phoneSecondary,
        'guardian' => $guardian,
        'address' => $address,
        'bed' => $bed,
        'consultant' => $consultant,
        'admission_date' => $admissionDate,
    ];

    if ($name === '') {
        $errors[] = 'Patient name is required.';
    }

    if ($admissionDate === '') {
        $errors[] = 'Admission date is required.';
    }

    $patientsParent = $pages->get('/patients/');
    if (!$patientsParent->id) {
        $errors[] = 'The patients container page could not be found.';
    }

    if (count($errors)) {
        $session->setFor('patient', 'errors', $errors);
        $session->setFor('patient', 'form', $formData);
        $session->redirect($errorRedirect);
    }

    // ── Ensure all non-guest users can create/edit/view patient + admission pages ──
    //     This runs on every add-patient POST so newly added roles are covered too.
    $allAppRoles = $roles->find('name!=guest,name!=superuser');
    $createPerm = $permissions->get('page-create');
    $editPerm = $permissions->get('page-edit');
    $viewPerm = $permissions->get('page-view');
    if ($createPerm && $createPerm->id) {
        foreach ($allAppRoles as $r) {
            if (!$r || !$r->id) continue;
            $r->of(false);
            if (!$r->hasPermission('page-create')) {
                $r->addPermission('page-create');
            }
            if ($editPerm && $editPerm->id && !$r->hasPermission('page-edit')) {
                $r->addPermission('page-edit');
            }
            if ($viewPerm && $viewPerm->id && !$r->hasPermission('page-view')) {
                $r->addPermission('page-view');
            }
            $r->save();
        }
        $patientTpl = $templates->get('patient-record');
        $admissionTpl = $templates->get('admission-record');
        foreach ([$patientTpl, $admissionTpl] as $tpl) {
            if (!$tpl || !$tpl->id) continue;
            $existingPerms = [];
            foreach ($tpl->permissions as $p) $existingPerms[] = $p->id;
            if (!in_array($createPerm->id, $existingPerms)) {
                $tpl->permissions->add($createPerm);
                $tpl->save();
            }
            if ($editPerm && $editPerm->id && !in_array($editPerm->id, $existingPerms)) {
                $tpl->permissions->add($editPerm);
                $tpl->save();
            }
            if ($viewPerm && $viewPerm->id && !in_array($viewPerm->id, $existingPerms)) {
                $tpl->permissions->add($viewPerm);
                $tpl->save();
            }
        }
        $patientsTpl = $templates->get('patients');
        if ($patientsTpl && $patientsTpl->id) {
            $existingPerms = [];
            foreach ($patientsTpl->permissions as $p) $existingPerms[] = $p->id;
            if (!in_array($createPerm->id, $existingPerms)) {
                $patientsTpl->permissions->add($createPerm);
                $patientsTpl->save();
            }
            if ($editPerm && $editPerm->id && !in_array($editPerm->id, $existingPerms)) {
                $patientsTpl->permissions->add($editPerm);
                $patientsTpl->save();
            }
            if ($viewPerm && $viewPerm->id && !in_array($viewPerm->id, $existingPerms)) {
                $patientsTpl->permissions->add($viewPerm);
                $patientsTpl->save();
            }
        }
    }

    $setOptionField = function (Page $page, string $fieldName, string $value): void {
        if ($value === '') {
            return;
        }

        try {
            $page->set($fieldName, $value);
        } catch (\Throwable $e) {
            // Ignore option assignment mismatches so required saves can still proceed.
        }
    };

    try {
        $patient = new Page();
        $patient->template = 'patient-record';
        $patient->parent = $patientsParent;
        $patient->name = $sanitizer->pageName($name, true) ?: ('patient-' . time());
        $patient->title = $name;
        $patient->of(false);
        $patient->guardian_name = $guardian;
        $patient->phone = $phone;
        if (wire('fields')->get('secondary_phone')) {
            $patient->secondary_phone = $phoneSecondary;
        }
        $patient->address = $address;
        $setOptionField($patient, 'gender', $gender);
        $pages->save($patient);

        $patient->patient_id = 'REG-' . date('Y') . '-' . str_pad((string) $patient->id, 4, '0', STR_PAD_LEFT);
        $pages->save($patient, 'patient_id');

        $admission = new Page();
        $admission->template = 'admission-record';
        $admission->parent = $patient;
        $admission->name = 'new';
        $admission->title = 'Admission for ' . $name;
        $admission->of(false);
        $admission->admitted_on = strtotime($admissionDate);
        $admission->patient_age = $age;
        $setOptionField($admission, 'age_unit', $ageUnit);
        if ($bed !== '') {
            $admission->room_bed = $bed;
        }

        if ($consultant !== '') {
            $consultantMatch = $pages->get("template=consultant, title=" . $sanitizer->selectorValue($consultant));
            if ($consultantMatch && $consultantMatch->id) {
                $admission->consultant_ref = $consultantMatch;
            }
            $admission->discharge_consultant = $consultant;
        }

        $pages->save($admission);

        $admission->ip_number = 'IP-' . date('Ymd') . '-' . str_pad((string) $admission->id, 3, '0', STR_PAD_LEFT);
        $admission->name = $sanitizer->pageName(strtolower($admission->ip_number), true) ?: ('ip-' . $admission->id);
        $pages->save($admission, ['ip_number', 'name']);

        $session->remove('patient');
        $session->redirect('/case-view/?id=' . (int) $admission->id);
    } catch (\Throwable $e) {
        $session->setFor('patient', 'errors', ['Unable to save the patient right now. ' . $e->getMessage()]);
        $session->setFor('patient', 'form', $formData);
        $session->redirect($errorRedirect);
    }
}

$page->title = $page->title ?: 'Admitted Patients';

$patientErrors = $session->getFor('patient', 'errors');
$patientFormData = $session->getFor('patient', 'form');
if (!is_array($patientErrors)) {
    $patientErrors = [];
}
if (!is_array($patientFormData)) {
    $patientFormData = [];
}
$showNewAddModule = $sanitizer->int($input->get->new_add) === 1 || count($patientErrors);

$page->of(false);

$q = $sanitizer->text($input->get->q);
$status = (int) $input->get->status;
$operationDate = $sanitizer->date($input->get->operation_date, 'Y-m-d');
$view = $sanitizer->text($input->get->view);

$sort = $sanitizer->text($input->get->sort);
$dir = strtolower($sanitizer->text($input->get->dir)) === 'asc' ? 'asc' : 'desc';
$sortMap = [
    'patient' => 'parent.title',
    'ip' => 'ip_number',
    'admitted' => 'admitted_on',
];

if (!isset($sortMap[$sort])) {
    $sort = 'admitted';
    $dir = 'desc';
}

$sortSelector = ($dir === 'asc' ? '' : '-') . $sortMap[$sort];
$allAdmissions = $pages->find("template=admission-record, sort=$sortSelector, limit=9999");

$fieldExists = static function (string $fieldName): bool {
    $field = wire('fields')->get($fieldName);
    return $field && $field->id;
};

$toTimestamp = static function ($value): int {
    if (is_int($value)) return $value;
    if (is_numeric($value) && (string) (int) $value === (string) $value) return (int) $value;
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') return 0;
        $parsed = strtotime($value);
        return $parsed ?: 0;
    }
    return 0;
};

$hasCaseStatusField = $fieldExists('case_status');
$hasDischargedOnField = $fieldExists('discharged_on');

$resolveAdmissionStatus = static function (Page $admission) use ($hasCaseStatusField, $hasDischargedOnField, $toTimestamp): int {
    if ($hasCaseStatusField) {
        $statusRaw = $admission->getUnformatted('case_status');
        if (is_object($statusRaw) && method_exists($statusRaw, 'first')) {
            $firstStatus = $statusRaw->first();
            if ($firstStatus && $firstStatus->id) {
                return (int) $firstStatus->id;
            }
        } else {
            $statusId = (int) $statusRaw;
            if ($statusId > 0) {
                return $statusId;
            }
        }
    }

    if ($hasDischargedOnField) {
        return $toTimestamp($admission->getUnformatted('discharged_on')) > 0 ? 2 : 1;
    }

    return 1;
};

$statusLabels = [
    1 => 'Active',
    2 => 'Discharged',
    3 => 'Follow-up',
    4 => 'Cancelled',
];

$statusBadgeClasses = [
    1 => 'badge badge--case-active',
    2 => 'badge badge--dc-finalized',
    3 => 'badge badge--patient-followup',
    4 => 'badge badge--proc-complication',
];

$adminBase = $config->urls->admin;
$tableRows = [];
$filteredAdmissions = [];

foreach ($allAdmissions as $adm) {
    $statusId = $resolveAdmissionStatus($adm);

    $diagnosis = 'Diagnosis pending';
    if ($adm->primary_diagnosis_ref && $adm->primary_diagnosis_ref->id) {
        $diagnosis = $adm->primary_diagnosis_ref->title;
    } elseif (trim(strip_tags((string) $adm->diagnosis)) !== '') {
        $diagnosis = trim(strip_tags((string) $adm->diagnosis));
    }

    $primaryProcedure = $pages->get("template=procedure, parent={$adm->id}, sort=proc_date");
    $operationTimestamp = $primaryProcedure && $primaryProcedure->id
        ? $toTimestamp($primaryProcedure->getUnformatted('proc_date'))
        : 0;

    $readyForDischarge = $statusId === 1
        && $diagnosis !== 'Diagnosis pending'
        && trim((string) $adm->medications_on_discharge) !== ''
        && trim((string) ($adm->follow_up_instructions ?: '')) !== ''
        && trim((string) (is_object($adm->general_condition) && isset($adm->general_condition->title) ? $adm->general_condition->title : ($adm->general_condition ?: $adm->condition_at_discharge))) !== '';

    if ($status && $statusId !== $status) {
        continue;
    }
    if ($view === 'admitted' && $statusId !== 1) {
        continue;
    }
    if ($view === 'pending' && !($statusId === 1 && !$readyForDischarge)) {
        continue;
    }
    if ($view === 'ready' && !$readyForDischarge) {
        continue;
    }
    if ($operationDate && (!$operationTimestamp || date('Y-m-d', $operationTimestamp) !== $operationDate)) {
        continue;
    }

    $filteredAdmissions[] = [
        'admission' => $adm,
        'status_id' => $statusId,
        'diagnosis' => $diagnosis,
        'operation_date' => $operationTimestamp ? date('d M Y', $operationTimestamp) : '-',
    ];
}

$fuzzyDataset = [];
foreach ($filteredAdmissions as $entry) {
    $adm = $entry['admission'];
    $fuzzyDataset[] = [
        'name'   => $adm->parent ? (string) $adm->parent->title : '',
        'pid'    => ($adm->parent && $adm->parent->patient_id) ? (string) $adm->parent->patient_id : '',
        'ip'     => (string) ($adm->ip_number ?: ''),
        'diag'   => $entry['diagnosis'],
        'sl'     => $statusLabels[$entry['status_id']] ?? '',
        'sc'     => $statusBadgeClasses[$entry['status_id']] ?? '',
        'ad'     => (($admittedOnTs = $toTimestamp($adm->getUnformatted('admitted_on'))) ? date('d M Y', $admittedOnTs) : '-'),
        'od'     => $entry['operation_date'],
        'url'    => '/case-view/?id=' . (int) $adm->id,
        'eurl'   => $adminBase . 'page/edit/?id=' . $adm->id,
    ];
}

$total = count($filteredAdmissions);

foreach ($filteredAdmissions as $entry) {
    $adm = $entry['admission'];
    $statusId = $entry['status_id'];
    $caseViewUrl = '/case-view/?id=' . (int) $adm->id;
    $tableRows[] = [
        'patient' => $adm->parent ? $adm->parent->title : 'Unknown patient',
        'patient_id' => ($adm->parent && $adm->parent->patient_id) ? $adm->parent->patient_id : '',
        'ip_number' => $adm->ip_number ?: '-',
        'diagnosis' => $entry['diagnosis'],
        'status_id' => $statusId,
        'status_label' => $statusLabels[$statusId] ?? 'Unknown',
        'status_class' => $statusBadgeClasses[$statusId] ?? 'badge badge--dc-draft',
        'admission_date' => ($admittedOnTs = $toTimestamp($adm->getUnformatted('admitted_on'))) ? date('d M Y', $admittedOnTs) : '-',
        'operation_date' => $entry['operation_date'],
        'url' => $caseViewUrl,
        'edit_url' => $adminBase . 'page/edit/?id=' . $adm->id,
    ];
}

$patientsParent = $pages->get('/patients/');
if (!$patientsParent->id) {
    $samplePatient = $pages->get("template=patient-record");
    $patientsParent = $samplePatient->id ? $samplePatient->parent : $pages->get('/');
}
$patientTemplateId = $templates->get('patient-record') ? $templates->get('patient-record')->id : 0;
$addPatientUrl = $patientTemplateId
    ? $adminBase . 'page/add/?parent_id=' . $patientsParent->id . '&template_id=' . $patientTemplateId
    : '/patients/';

$queryParams = [];
if ($status) {
    $queryParams['status'] = $status;
}
if ($operationDate) {
    $queryParams['operation_date'] = $operationDate;
}
if ($view !== '') {
    $queryParams['view'] = $view;
}
if ($sort !== 'admitted') {
    $queryParams['sort'] = $sort;
}
if ($dir !== 'desc' || $sort !== 'admitted') {
    $queryParams['dir'] = $dir;
}

$baseUrl = $page->url;

$buildSortUrl = function (string $column) use ($baseUrl, $queryParams, $sort, $dir): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    $params = $queryParams;
    $params['sort'] = $column;
    $params['dir'] = $nextDir;
    $queryString = http_build_query($params);
    return $baseUrl . ($queryString ? '?' . $queryString : '');
};

$tableColumns = [
    [
        'label'    => 'Patient',
        'sortable' => true,
        'href'     => $buildSortUrl('patient'),
        'active'   => $sort === 'patient',
        'dir'      => $sort === 'patient' ? $dir : null,
    ],
    [
        'label'    => 'IP Number',
        'sortable' => true,
        'href'     => $buildSortUrl('ip'),
        'active'   => $sort === 'ip',
        'dir'      => $sort === 'ip' ? $dir : null,
    ],
    [
        'label'    => 'Diagnosis',
        'sortable' => false,
    ],
    [
        'label'    => 'Operation Date',
        'sortable' => false,
    ],
    [
        'label'    => 'Status',
        'sortable' => false,
    ],
    [
        'label'    => 'Admission Date',
        'sortable' => true,
        'href'     => $buildSortUrl('admitted'),
        'active'   => $sort === 'admitted',
        'dir'      => $sort === 'admitted' ? $dir : null,
    ],
    [
        'label'    => 'Actions',
        'sortable' => false,
        'action'   => true,
    ],
];
?>
<div id="content" class="patients-page">
  <div class="page-header">
    <div class="page-header__title-group">
      <h1 class="t-page-heading">Admitted Patients</h1>
      <p class="t-body"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> <span id="patients-fuzzy-count"></span></p>
    </div>
    <div class="page-header__actions">
      <a class="btn btn--neutral" href="<?= $page->url ?>?new_add=1#new-add-patient-module">
        <span>Add New Patient</span>
      </a>
    </div>
  </div>

  <div class="page-body layout-stack layout-stack--gap-4">
    <?php if (count($patientErrors)): ?>
    <div class="card">
      <div class="field__error" style="display:block;">
        <?= $sanitizer->entities(implode(' ', $patientErrors)) ?>
      </div>
    </div>
    <?php endif; ?>

<form method="get" class="card">
      <div class="layout-row layout-row--gap-3">
        <label class="field" style="flex:1;">
          <span class="field__label">Search</span>
          <span class="input-wrap input-wrap--icon-lead">
            <span class="input-wrap__icon-lead" aria-hidden="true"><i data-lucide="search"></i></span>
            <input class="input" type="text" name="q" id="patients-search-q" value="<?= $sanitizer->entities($q) ?>" placeholder="Search patient, IP number, or diagnosis keyword" autocomplete="off" />
          </span>
        </label>

        <label class="field" style="min-width:180px;">
          <span class="field__label">Status</span>
          <select class="select" name="status">
            <option value="">All</option>
            <option value="1" <?= $status === 1 ? 'selected' : '' ?>>Active</option>
            <option value="2" <?= $status === 2 ? 'selected' : '' ?>>Discharged</option>
            <option value="3" <?= $status === 3 ? 'selected' : '' ?>>Follow-up</option>
          </select>
        </label>

        <label class="field" style="min-width:180px;">
          <span class="field__label">Operation Date</span>
          <input class="input" type="date" name="operation_date" value="<?= $sanitizer->entities($operationDate) ?>" />
        </label>

        <input type="hidden" name="sort" value="<?= $sanitizer->entities($sort) ?>" />
        <input type="hidden" name="dir" value="<?= $sanitizer->entities($dir) ?>" />

        <div class="layout-row layout-row--gap-2" style="margin-left:auto;align-self:flex-end;">
          <button class="btn btn--primary" type="submit">
            <span class="btn__icon"><i data-lucide="search" aria-hidden="true"></i></span>
            <span>Filter</span>
          </button>
          <?php if ($status || $operationDate || $view): ?>
          <a class="btn btn--neutral" href="<?= $page->url ?><?= $view ? '?view=' . $sanitizer->entities($view) : '' ?>">Clear Filters</a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <?php if ($showNewAddModule): ?>
    <?php include('./components/module-add-patient-standalone.php'); ?>
    <?php endif; ?>

    <?php include('./components/table.php'); ?>
  </div>
  </div>

  <script>var PATIENTS_DATA = <?= json_encode($fuzzyDataset, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;</script>
</div>
