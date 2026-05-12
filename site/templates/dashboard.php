<?php namespace ProcessWire;
/**
 * Case Management Hub dashboard
 */
$roleFlags = wire('authRoleFlags') ?: [];
$isAdminUser = !empty($roleFlags['is_admin']);
$isConsultantUser = !empty($roleFlags['is_consultant']);
$isPhysicianAssistantUser = !empty($roleFlags['is_physician_assistant']);
$isMedicalOfficerUser = !empty($roleFlags['is_medical_officer']);

// ── Page-level permission check ───────────────────────────────────────────────
$canSee = wire('dashPerm');
if ($canSee && !$canSee('page_dashboard')) {
    $session->redirect('/?unauthorized=1');
}

if ($isAdminUser) {
    $session->redirect('/admin-panel/');
}

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

$allAdmissions = $pages->find("template=admission-record, sort=-created, limit=500");

// Stats
$totalPatients = $pages->count("template=patient-record");
$totalProcedures = $pages->count("template=procedure");
$activeAdm = 0;
$dischargedMTD = 0;
$pendingDischargeCount = 0;
$monthStart = strtotime('first day of this month');

foreach ($allAdmissions as $adm) {
    $statusId = $resolveAdmissionStatus($adm);
    $dischargedOnTs = $toTimestamp($adm->getUnformatted('discharged_on'));

    if ($statusId === 1) {
        $activeAdm++;
        $pendingDischargeCount++;
    }

    if ($statusId === 2 && $dischargedOnTs >= $monthStart) {
        $dischargedMTD++;
    }
}

// Search
$q          = $sanitizer->text($input->get->q);
$filterDate = $sanitizer->date($input->get->operation_date, 'Y-m-d');
$view = $sanitizer->text($input->get->view);
$searchIndex = $sanitizer->text($input->get->search_index ?: 'all');

$recentAdmissions = new PageArray();
foreach ($allAdmissions as $adm) {
    $recentAdmissions->add($adm);
    if (count($recentAdmissions) >= 15) {
        break;
    }
}

$adminBase = $config->urls->admin;

// Get patient ID for new admission URL
$patientsParent = $pages->get('/patients/');
if (!$patientsParent->id) {
    // Fallback: try to find any patient record and use its parent
    $samplePatient = $pages->get("template=patient-record");
    $patientsParent = $samplePatient->id ? $samplePatient->parent : $pages->get('/');
}
$patientTemplateId = $templates->get('patient-record') ? $templates->get('patient-record')->id : 0;
$admissionTemplateId = $templates->get('admission-record') ? $templates->get('admission-record')->id : 0;

$statusLabels = [1 => 'Active', 2 => 'Discharged', 3 => 'Inactive', 4 => 'Cancelled'];
$statusBadgeClasses = [
    1 => 'badge badge--case-active',
    2 => 'badge badge--dc-finalized',
    3 => 'badge badge--dc-draft',
    4 => 'badge badge--proc-complication',
];

// CSV export handler
if ($input->get->export === 'csv' && $canSee && $canSee('table_export_csv')) {
    $allAdm = $allAdmissions;
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="registry_export_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['IP Number', 'Patient Name', 'Patient ID', 'Admission Date', 'Discharge Date', 'Diagnosis', 'Consultant', 'Status', 'Procedures Count']);
    foreach ($allAdm as $a) {
        $diagLabel = $a->primary_diagnosis_ref ? $a->primary_diagnosis_ref->title : strip_tags((string) $a->diagnosis);
        $cons = $a->consultant_ref ? $a->consultant_ref->title : $a->discharge_consultant;
        $status = $statusLabels[$resolveAdmissionStatus($a)] ?? 'Active';
        $procCount = $pages->count("template=procedure, parent=$a");
        $admittedOnTs = $toTimestamp($a->getUnformatted('admitted_on'));
        $dischargedOnTs = $toTimestamp($a->getUnformatted('discharged_on'));
        fputcsv($out, [
            $a->ip_number,
            $a->parent->title,
            $a->parent->patient_id,
            $admittedOnTs ? date('d/m/Y', $admittedOnTs) : '',
            $dischargedOnTs ? date('d/m/Y', $dischargedOnTs) : '',
            $diagLabel,
            $cons,
            $status,
            $procCount,
        ]);
    }
    fclose($out);
    exit;
}

$tableTitle = ($q || $filterDate || $view) ? 'Search Results' : 'Recent Admissions';
$displayList = $recentAdmissions;

if ($q || $filterDate || $view) {
    $displayList = new PageArray();
    $queryNeedle = strtolower(trim((string) $q));
    foreach ($allAdmissions as $adm) {
        $statusId = $resolveAdmissionStatus($adm);

        $primaryProcedure = $pages->get("template=procedure, parent={$adm->id}, sort=proc_date");
        $operationTimestamp = $primaryProcedure && $primaryProcedure->id
            ? $toTimestamp($primaryProcedure->getUnformatted('proc_date'))
            : 0;
        $diagnosisText = $adm->primary_diagnosis_ref && $adm->primary_diagnosis_ref->id
            ? trim((string) $adm->primary_diagnosis_ref->title)
            : trim(strip_tags((string) $adm->diagnosis));
        $readyForDischarge = $statusId === 1
            && $diagnosisText !== ''
            && trim((string) $adm->medications_on_discharge) !== ''
            && trim((string) ($adm->follow_up_instructions ?: '')) !== ''
            && trim((string) (is_object($adm->general_condition) && isset($adm->general_condition->title) ? $adm->general_condition->title : ($adm->general_condition ?: $adm->condition_at_discharge))) !== '';

        if ($view === 'admitted' && $statusId !== 1) {
            continue;
        }
        if ($view === 'pending' && !($statusId === 1 && !$readyForDischarge)) {
            continue;
        }
        if ($view === 'ready' && !$readyForDischarge) {
            continue;
        }
        if ($filterDate && (!$operationTimestamp || date('Y-m-d', $operationTimestamp) !== $filterDate)) {
            continue;
        }
        if ($queryNeedle !== '') {
            $haystack = strtolower(implode(' ', array_filter([
                $adm->parent ? (string) $adm->parent->title : '',
                $adm->parent ? (string) $adm->parent->patient_id : '',
                (string) $adm->ip_number,
                $diagnosisText,
            ])));
            if (strpos($haystack, $queryNeedle) === false) {
                continue;
            }
        }

        $adm->setQuietly('dashboard_operation_date', $operationTimestamp ? date('d M Y', $operationTimestamp) : '—');
        $displayList->add($adm);
    }
}

$missingNotes = new PageArray();
$readyForDischarge = new PageArray();
$activeAdmissionsList = new PageArray();

foreach ($allAdmissions as $adm) {
    if ($resolveAdmissionStatus($adm) === 1) {
        $activeAdmissionsList->add($adm);
    }
    if (count($activeAdmissionsList) >= 200) {
        break;
    }
}

foreach ($activeAdmissionsList as $adm) {
    $chiefComplaint = trim(strip_tags((string) $adm->chief_complaint));
    $diagnosis = trim(strip_tags((string) $adm->diagnosis));
    $postOpCourse = trim(strip_tags((string) $adm->post_op_course));
    $conditionAtDischarge = trim(strip_tags((string) $adm->condition_at_discharge));
    $generalCondition = trim(strip_tags((string) $adm->general_condition));
    $dischargedOn = $toTimestamp($adm->getUnformatted('discharged_on'));

    if ($chiefComplaint === '' && $diagnosis === '' && $postOpCourse === '') {
        $missingNotes->add($adm);
    }

    if (($conditionAtDischarge !== '' || $generalCondition !== '') && !$dischargedOn) {
        $readyForDischarge->add($adm);
    }
}

$consultantsPage = wire('pages')->get('name=consultants');
$diagnosesPage = wire('pages')->get('name=diagnoses');
$quickActions = [
    [
        'url' => '#',
        'icon' => 'user-plus',
        'title' => 'New Patient',
        'subtitle' => 'Register a new patient record',
        'perm' => 'action_new_patient',
        'modal' => 'add-patient-modal',
    ],
    [
        'url' => '/patients/',
        'icon' => 'users',
        'title' => 'All Patients',
        'subtitle' => 'Browse the full registry',
        'perm' => 'action_all_patients',
    ],
    [
        'url' => $page->url . '?export=csv',
        'icon' => 'download',
        'title' => 'Export Registry',
        'subtitle' => 'Download the current registry CSV',
        'perm' => 'action_export_registry',
    ],
    [
        'url' => $consultantsPage && $consultantsPage->id ? $adminBase . 'page/list/?open=' . $consultantsPage->id : '',
        'icon' => 'stethoscope',
        'title' => 'Consultants',
        'subtitle' => 'Maintain consultant references',
        'perm' => 'action_consultants',
    ],
    [
        'url' => $diagnosesPage && $diagnosesPage->id ? $adminBase . 'page/list/?open=' . $diagnosesPage->id : '',
        'icon' => 'notebook-pen',
        'title' => 'Diagnoses',
        'subtitle' => 'Manage diagnosis taxonomy',
        'perm' => 'action_diagnoses',
    ],
];

// Role label for display
$dashboardRoleLabel = 'Clinical User';
if ($isMedicalOfficerUser) {
    $dashboardRoleLabel = 'Medical Officer';
} elseif ($isConsultantUser) {
    $dashboardRoleLabel = 'Consultant';
} elseif ($isPhysicianAssistantUser) {
    $dashboardRoleLabel = 'Physician Assistant';
}
?>

<div id="content" class="dashboard-page">
    <div class="page-header">
        <div class="page-header__title-group">
            <h1 class="t-page-heading">Dr Tawfiq Clinical Registry</h1>
            <p class="t-body dashboard-page__subtitle">Track active cases, incomplete notes, and discharge readiness from a single baseline shell.</p>
        </div>
        <div class="page-header__actions">
            <span class="dashboard-role-indicator">
                <i data-lucide="user" aria-hidden="true"></i>
                <span><?= $sanitizer->entities($user->name) ?></span>
                <strong class="dashboard-role-indicator__role"><?= $dashboardRoleLabel ?></strong>
            </span>
        </div>
    </div>

    <div class="page-body layout-stack layout-stack--gap-4">
        <section class="stat-card-row">
            <?php if ($canSee('stat_total_patients')): ?>
            <article class="stat-card stat-card--brand">
                <div class="stat-card__top">
                    <span class="stat-card__icon-wrap"><i data-lucide="users" aria-hidden="true"></i></span>
                </div>
                <div class="stat-card__value"><?= $totalPatients ?></div>
                <div class="stat-card__label">Total Patients</div>
            </article>
            <?php endif; ?>
            <?php if ($canSee('stat_admitted_patients')): ?>
            <article class="stat-card stat-card--success">
                <div class="stat-card__top">
                    <span class="stat-card__icon-wrap"><i data-lucide="activity" aria-hidden="true"></i></span>
                </div>
                <div class="stat-card__value"><?= $activeAdm ?></div>
                <div class="stat-card__label">Admitted Patients</div>
            </article>
            <?php endif; ?>
            <?php if ($canSee('stat_pending_discharge')): ?>
            <article class="stat-card stat-card--warning">
                <div class="stat-card__top">
                    <span class="stat-card__icon-wrap"><i data-lucide="clipboard-list" aria-hidden="true"></i></span>
                </div>
                <div class="stat-card__value"><?= $pendingDischargeCount ?></div>
                <div class="stat-card__label">Pending Discharge</div>
            </article>
            <?php endif; ?>
            <?php if ($canSee('stat_total_procedures')): ?>
            <article class="stat-card stat-card--purple">
                <div class="stat-card__top">
                    <span class="stat-card__icon-wrap"><i data-lucide="syringe" aria-hidden="true"></i></span>
                </div>
                <div class="stat-card__value"><?= $totalProcedures ?></div>
                <div class="stat-card__label">Total Procedures</div>
            </article>
            <?php endif; ?>
        </section>

        <section class="dashboard-quick-actions">
            <?php foreach ($quickActions as $action): ?>
                <?php if (!$canSee($action['perm'])) continue; ?>
                <a href="<?= $action['url'] ?>" class="card dashboard-quick-action" <?= !empty($action['modal']) ? 'data-modal-trigger="' . $action['modal'] . '"' : '' ?>>
                    <span class="dashboard-quick-action__icon"><i data-lucide="<?= $action['icon'] ?>" aria-hidden="true"></i></span>
                    <span class="dashboard-quick-action__content">
                        <span class="dashboard-quick-action__title"><?= $action['title'] ?></span>
                        <span class="dashboard-quick-action__subtitle"><?= $action['subtitle'] ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </section>

        <?php if ($canSee('table_recent_admissions')): ?>
        <section class="card card--flush dashboard-admissions-section">
            <div class="card__header">
                <div class="card__title-group">
                    <h2 class="card__title"><?= $tableTitle ?></h2>
                    <p class="card__subtitle"><?= count($displayList) ?> admission<?= count($displayList) === 1 ? '' : 's' ?> in view</p>
                </div>
            </div>
            <div class="card__body">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>IP Number</th>
                                <th>Admitted</th>
                                <th>Diagnosis</th>
                                <th>Operation Date</th>
                                <th>Procedures</th>
                                <th>Status</th>
                                <th class="cell--action">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($displayList as $adm): ?>
                            <?php
                            $statusId = $resolveAdmissionStatus($adm);
                            $statusLbl = $statusLabels[$statusId] ?? 'Active';
                            $statusClass = $statusBadgeClasses[$statusId] ?? 'badge badge--case-active';
                            $diagLabel = $adm->primary_diagnosis_ref ? $adm->primary_diagnosis_ref->title : substr(strip_tags((string) $adm->diagnosis), 0, 80);
                            $procCount = $pages->count("template=procedure, parent=$adm");
                            $admittedOnTs = $toTimestamp($adm->getUnformatted('admitted_on'));
                            $adm->setQuietly('admitted_on', $admittedOnTs);
                            $operationDateDisplay = $adm->get('dashboard_operation_date') ?: '—';
                            $cons = $operationDateDisplay;
                            $caseViewUrl = '/case-view/?id=' . (int) $adm->id;
                            ?>
                            <tr>
                                <td class="cell">
                                    <div class="dashboard-patient-cell">
                                        <span class="dashboard-patient-cell__name"><?= $sanitizer->entities($adm->parent->title) ?></span>
                                        <span class="dashboard-patient-cell__meta">ID: <?= $sanitizer->entities($adm->parent->patient_id) ?: 'N/A' ?></span>
                                    </div>
                                </td>
                                <td class="cell"><strong><?= $sanitizer->entities($adm->ip_number) ?: '—' ?></strong></td>
                                <td class="cell"><?= $adm->getUnformatted('admitted_on') ? date('d M Y', $adm->getUnformatted('admitted_on')) : '—' ?></td>
                                <td class="cell"><?= $sanitizer->entities($cons) ?: '—' ?></td>
                                <td class="cell dashboard-diagnosis" title="<?= $sanitizer->entities($diagLabel) ?>"><?= $sanitizer->entities($diagLabel) ?: '—' ?></td>
                                <td class="cell">
                                    <?php if ($procCount > 0): ?>
                                    <span class="dashboard-procedure-count"><?= $procCount ?></span>
                                    <?php else: ?>
                                    <span class="dashboard-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell"><span class="<?= $statusClass ?>"><?= $statusLbl ?></span></td>
                                <td class="cell cell--action">
                                    <a href="<?= $caseViewUrl ?>" class="btn btn--icon" title="Timeline view" aria-label="Timeline view">
                                        <i data-lucide="clock-3" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= $caseViewUrl ?>&pdf=1" target="_blank" class="btn btn--icon" title="Open PDF" aria-label="Open PDF">
                                        <i data-lucide="file-text" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($isConsultantUser || $isMedicalOfficerUser): ?>
                                    <a href="<?= $adminBase ?>page/edit/?id=<?= $adm->id ?>" class="btn btn--icon" title="Edit admission" aria-label="Edit admission">
                                        <i data-lucide="square-pen" aria-hidden="true"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!count($displayList)): ?>
                            <tr>
                                <td colspan="8" class="table-empty">No records found<?= $q ? ' for "' . $sanitizer->entities($q) . '"' : '' ?>.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <div class="dashboard-mobile-admissions">
            <?php if (!count($displayList)): ?>
                <p class="mobile-patient-list__empty">No recent admissions.</p>
            <?php else: foreach ($displayList as $adm):
                $mStatusId    = $resolveAdmissionStatus($adm);
                $mStatusLbl   = $statusLabels[$mStatusId] ?? 'Active';
                $mStatusClass = $statusBadgeClasses[$mStatusId] ?? 'badge badge--case-active';
                $mDiag        = $adm->primary_diagnosis_ref ? $adm->primary_diagnosis_ref->title : substr(strip_tags((string) $adm->diagnosis), 0, 80);
                $mProcCount   = $pages->count("template=procedure, parent=$adm");
                $mAdmittedTs  = $toTimestamp($adm->getUnformatted('admitted_on'));
                $mAdmDate     = $mAdmittedTs ? date('d M Y', $mAdmittedTs) : '—';
                $mOpDate      = $adm->get('dashboard_operation_date') ?: '—';
                $mUrl         = '/case-view/?id=' . (int) $adm->id;
                $mGenderRaw   = ($adm->parent && $adm->parent->gender) ? $adm->parent->gender : null;
                $mGender      = $mGenderRaw ? (is_object($mGenderRaw) && isset($mGenderRaw->title) ? (string)$mGenderRaw->title : (string)$mGenderRaw) : '';
                $mFemale      = stripos($mGender, 'female') !== false;
                $mPending     = !$mDiag;
            ?>
            <a class="mpc" href="<?= $mUrl ?>">
                <div class="mpc__avatar<?= $mFemale ? ' mpc__avatar--female' : '' ?>" aria-hidden="true">
                    <?php if ($mFemale): ?>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#db2777" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php else: ?>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php endif; ?>
                </div>
                <div class="mpc__body">
                    <div class="mpc__name"><?= $sanitizer->entities($adm->parent->title) ?></div>
                    <div class="mpc__ip"><?= $sanitizer->entities($adm->ip_number ?: '—') ?></div>
                    <?php if ($adm->parent->patient_id): ?><div class="mpc__reg">ID: <?= $sanitizer->entities($adm->parent->patient_id) ?></div><?php endif; ?>
                    <div class="mpc__diag<?= (!$mDiag || $mPending) ? ' mpc__diag--pending' : '' ?>">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?= $mDiag ? $sanitizer->entities($mDiag) : 'Diagnosis pending' ?>
                    </div>
                </div>
                <div class="mpc__side">
                    <span class="<?= $sanitizer->entities($mStatusClass) ?>"><?= $sanitizer->entities($mStatusLbl) ?></span>
                    <div class="mpc__dates">
                        <p>Op: <?= $mOpDate !== '—' ? '<span>' . $sanitizer->entities($mOpDate) . '</span>' : '—' ?></p>
                        <p>Adm: <span><?= $sanitizer->entities($mAdmDate) ?></span></p>
                        <?php if ($mProcCount > 0): ?><p><span><?= $mProcCount ?> proc<?= $mProcCount > 1 ? 's' : '' ?></span></p><?php endif; ?>
                    </div>
                </div>
                <svg class="mpc__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>
