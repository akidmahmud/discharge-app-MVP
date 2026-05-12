<?php namespace ProcessWire;
/**
 * Admission Record — Phase 7 Upgraded Discharge Engine
 * Routes:
 *   - Default:  → case-view.php (Timeline UI)
 *   - ?pdf=1    → generate mPDF discharge summary
 */
$case    = $page;
$patient = $page->parent;
$caseViewUrl = '/case-view/?id=' . (int) $case->id;

// ── Route: PDF Discharge Summary ──────────────────────────────────────────────
if ($input->get->pdf) {
    // Collect all structured data
    $procedures     = $pages->find("template=procedure, parent=$case, sort=proc_date");
    $investigations = $pages->find("template=investigation, parent=$case, sort=investigation_date");

    // Consultant label
    $consultantLabel = $case->consultant_ref
        ? $case->consultant_ref->title
        : ($case->discharge_consultant ?: $case->admitting_unit ?: '');

    // Diagnosis label
    $diagLabel = $case->primary_diagnosis_ref
        ? $case->primary_diagnosis_ref->title
        : strip_tags($case->diagnosis);

    // Side label
    $sideLabel = $case->diagnosis_side ? $case->diagnosis_side->title : '';

    // Age
    $ageLabel = '';
    if ($patient->getUnformatted('date_of_birth')) {
        $dobValue = $patient->getUnformatted('date_of_birth');
        $dob = is_numeric($dobValue) ? new \DateTime("@{$dobValue}") : new \DateTime($dobValue);
        $ageLabel = $dob->diff(new \DateTime())->y . ' Years';
    } elseif ($case->patient_age) {
        $ageLabel = $case->patient_age . ' ' . ($case->age_unit ? $case->age_unit->title : 'Years');
    }

    // Gender
    $genderLabel = $patient->gender ? $patient->gender->title : '';

    // Status labels
    $mlcLabel = $case->mlc_status ? $case->mlc_status->title : 'No';

    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1a1a1a; }
.lh { width:100%; border-bottom:2px solid #1e3a8a; padding-bottom:10px; margin-bottom:20px; }
.lh td { vertical-align:middle; }
.logo-box { width:70px; height:70px; background:#1e3a8a; color:white; border-radius:6px; text-align:center; line-height:70px; font-weight:bold; font-size:22px; }
.hospital-info { text-align:right; font-size:9.5pt; color:#444; }
.hospital-info h2 { color:#1e3a8a; margin:0; font-size:16pt; }
.doc-title { text-align:center; font-size:13pt; font-weight:700; margin:10px 0 18px; text-transform:uppercase; color:#1e3a8a; }
table.pi { width:100%; border-collapse:collapse; margin-bottom:20px; font-size:9.5pt; border:1px solid #cbd5e1; }
table.pi th, table.pi td { padding:7px 10px; border:1px solid #cbd5e1; vertical-align:top; }
table.pi th { background:#f1f5f9; color:#475569; font-weight:600; text-transform:uppercase; font-size:8.5pt; width:18%; }
table.pi td { width:32%; }
.sec { margin-top:16px; margin-bottom:8px; font-weight:700; font-size:10.5pt; color:#1e3a8a; border-bottom:1px solid #e2e8f0; padding-bottom:3px; text-transform:uppercase; }
.content { font-size:10pt; margin-bottom:14px; text-align:justify; }
.proc-item { padding:6px 0 6px 12px; border-left:3px solid #94a3b8; margin-bottom:10px; }
.proc-date { font-weight:700; color:#334155; }
.sig { width:100%; margin-top:50px; }
.sig td { width:50%; text-align:center; font-size:9pt; }
.footer { position:fixed; bottom:0; width:100%; text-align:center; font-size:8pt; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:4px; }
.meds-table { width:100%; border-collapse:collapse; font-size:9.5pt; margin-bottom:12px; }
.meds-table th { background:#f1f5f9; padding:6px 10px; border:1px solid #cbd5e1; text-align:left; font-size:8.5pt; }
.meds-table td { padding:6px 10px; border:1px solid #cbd5e1; }
</style>
</head>
<body>

<!-- Letterhead -->
<table class="lh">
<tr>
    <td><div class="logo-box">GR</div></td>
    <td class="hospital-info">
        <h2>Ganga Hospital</h2>
        <p>Department of Hand Surgery &amp; Microsurgery<br>
        Structured Clinical Documentation System</p>
    </td>
</tr>
</table>

<div class="doc-title">Discharge Summary</div>

<!-- Patient Info Table -->
<table class="pi">
<tr>
    <th>Patient Name</th><td><?= $patient->title ?></td>
    <th>Patient ID</th><td><?= $patient->patient_id ?></td>
</tr>
<tr>
    <th>Age / Gender</th><td><?= $ageLabel ?> / <?= $genderLabel ?></td>
    <th>MLC</th><td><?= $mlcLabel ?></td>
</tr>
<tr>
    <th>Guardian</th><td><?= $patient->guardian_name ?></td>
    <th>Phone</th><td><?= $patient->phone ?></td>
</tr>
<tr>
    <th>Address</th><td colspan="3"><?= nl2br($patient->address) ?></td>
</tr>
<tr>
    <th>IP Number</th><td><?= $case->ip_number ?></td>
    <th>Ward / Bed</th><td><?= $case->room_bed ?: $case->ward_room ?></td>
</tr>
<tr>
    <th>Admitted On</th><td><?= $case->getUnformatted('admitted_on') ? date('d/m/Y h:i A', $case->getUnformatted('admitted_on')) : '' ?></td>
    <th>Consultant</th><td><?= $consultantLabel ?></td>
</tr>
<tr>
    <th>Discharged On</th><td><?= $case->getUnformatted('discharged_on') ? date('d/m/Y h:i A', $case->getUnformatted('discharged_on')) : '' ?></td>
    <th>Unit</th><td><?= $case->admitting_unit ?></td>
</tr>
</table>

<!-- DIAGNOSIS — only if filled -->
<?php if ($diagLabel): ?>
<div class="sec">Diagnosis</div>
<div class="content">
    <strong><?= $diagLabel ?></strong><?= $sideLabel ? " ({$sideLabel})" : '' ?>
    <?php if ($case->associated_conditions): ?><br><?= nl2br($case->associated_conditions) ?><?php endif; ?>
</div>
<?php endif; ?>

<!-- PROCEDURES — only if filled -->
<?php if (count($procedures)): ?>
<div class="sec">Procedures</div>
<div class="content">
<?php foreach ($procedures as $proc): ?>
    <div class="proc-item">
        <span class="proc-date"><?= $proc->getUnformatted('proc_date') ? date('d-m-Y', $proc->getUnformatted('proc_date')) : '' ?></span>
        &nbsp;: <?= $proc->proc_name ?: $proc->title ?>
        <?php if($proc->anesthesia_type): ?> (<?= $proc->anesthesia_type->title ?>)<?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php elseif ($case->procedures && count($case->procedures)): ?>
<div class="sec">Procedures</div>
<div class="content">
<?php foreach ($case->procedures as $proc): ?>
    <div class="proc-item">
        <span class="proc-date"><?= $proc->getUnformatted('procedure_date') ? date('d-m-Y', $proc->getUnformatted('procedure_date')) : '' ?></span>
        &nbsp;: <?= nl2br($proc->procedure_name) ?>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- HISTORY — only if filled -->
<?php $histText = $case->chief_complaint ?: strip_tags($case->history_complaints); ?>
<?php if ($histText): ?>
<?php
$historyFields = wire('fields');
$knownComorbidityFlags = ['DM', 'HTN', 'CKD', 'Asthma', 'IHD'];
$historyComorbidityBits = [];
$historyComorbidityDrugBits = [];
$historyComorbidityNone = $historyFields->get('comorbidity_none') ? (bool) $case->getUnformatted('comorbidity_none') : false;
$historyComorbidityFlags = [];
if ($historyFields->get('comorbidity_flags') && is_iterable($case->getUnformatted('comorbidity_flags'))) {
    foreach ($case->getUnformatted('comorbidity_flags') as $item) {
        $title = is_object($item) && isset($item->title) ? trim((string) $item->title) : trim((string) $item);
        if ($title !== '') {
            $historyComorbidityFlags[] = $title;
        }
    }
    $historyComorbidityFlags = array_values(array_unique($historyComorbidityFlags));
}
$historyComorbidityDrugs = wire('templates')->get('comorbidity-drug')
    ? wire('pages')->find("template=comorbidity-drug, parent={$case->id}, sort=sort")
    : [];
$historyDrugHistoryBits = [];
$historyDrugHistoryPages = wire('templates')->get('drug-history-entry')
    ? wire('pages')->find("template=drug-history-entry, parent={$case->id}, sort=sort")
    : [];

if ($historyComorbidityNone) {
    $historyComorbidityBits[] = 'None';
} else {
    foreach ($knownComorbidityFlags as $flag) {
        if (in_array($flag, $historyComorbidityFlags, true)) {
            $historyComorbidityBits[] = $flag;
        }
    }
    foreach ($historyComorbidityDrugs as $drugPage) {
        $condition = trim((string) $drugPage->get('comorb_condition_flag'));
        $drugName = trim((string) $drugPage->get('drug_name'));
        $drugDose = trim((string) $drugPage->get('drug_dose'));
        if ($condition !== '' && !in_array($condition, $knownComorbidityFlags, true) && !in_array($condition, $historyComorbidityBits, true)) {
            $historyComorbidityBits[] = $condition;
        }
        if ($drugName !== '') {
            $historyComorbidityDrugBits[] = trim(($condition !== '' ? $condition . ': ' : '') . $drugName . ($drugDose !== '' ? ' ' . $drugDose : ''));
        }
    }
}

foreach ($historyDrugHistoryPages as $drugHistoryPage) {
    $drugName = trim((string) $drugHistoryPage->get('drug_name'));
    $drugDose = trim((string) $drugHistoryPage->get('drug_dose'));
    $drugFrequency = trim((string) $drugHistoryPage->get('drug_frequency'));
    if ($drugName === '') {
        continue;
    }
    $historyDrugHistoryBits[] = trim($drugName . ($drugDose !== '' ? ' ' . $drugDose : '') . ($drugFrequency !== '' ? ' ' . $drugFrequency : ''));
}
?>
<div class="sec">History &amp; Presenting Complaints</div>
<div class="content"><?= nl2br($histText) ?>
<?php if ($historyComorbidityBits): ?><br><strong>Comorbidities:</strong> <?= $sanitizer->entities(implode(', ', $historyComorbidityBits)) ?><?php elseif ($case->comorbidities): ?><br><strong>Comorbidities:</strong> <?= $case->comorbidities ?><?php endif; ?>
<?php if ($historyComorbidityDrugBits): ?><br><strong>Comorbidity Drugs:</strong> <?= $sanitizer->entities(implode('; ', $historyComorbidityDrugBits)) ?><?php endif; ?>
<?php if ($historyDrugHistoryBits): ?><br><strong>Drug History:</strong> <?= $sanitizer->entities(implode('; ', $historyDrugHistoryBits)) ?><?php elseif ($case->drug_history): ?><br><strong>Drug History:</strong> <?= $case->drug_history ?><?php endif; ?>
</div>
<?php endif; ?>

<!-- EXAMINATION — only if filled -->
<?php $examText = $case->inspection ?: strip_tags($case->examination_findings); ?>
<?php if ($examText): ?>
<div class="sec">Examination Findings</div>
<div class="content">
<?php if ($examText): ?><?= nl2br($examText) ?><?php endif; ?>
<?php
// Movement fields — only print if NOT "Full" and not empty
$movFields = [
    'shoulder_abduction_active'=>'Shoulder Abduction (Active)', 'shoulder_abduction_passive'=>'Shoulder Abduction (Passive)',
    'shoulder_external_rotation'=>'Shoulder External Rotation', 'shoulder_internal_rotation'=>'Shoulder Internal Rotation',
    'elbow_flexion'=>'Elbow Flexion', 'elbow_extension'=>'Elbow Extension',
    'forearm_supination'=>'Forearm Supination', 'forearm_pronation'=>'Forearm Pronation',
    'wrist_flexion'=>'Wrist Flexion', 'wrist_extension'=>'Wrist Extension',
    'finger_flexion'=>'Finger Flexion', 'finger_extension'=>'Finger Extension',
];
$hasMovement = false;
foreach ($movFields as $fn => $lbl) {
    $val = trim($case->$fn ?? '');
    if ($val && strtolower($val) !== 'full') { $hasMovement = true; break; }
}
if ($hasMovement): ?>
<br><strong>Movement &amp; Function:</strong><br>
<table style="font-size:9pt;width:100%;border-collapse:collapse;">
<?php foreach ($movFields as $fn => $lbl): ?>
<?php $val = trim($case->$fn ?? ''); ?>
<tr>
    <td style="width:200px;color:#475569;"><?= $lbl ?></td>
    <td><?= $val ?: 'Full' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- INVESTIGATIONS — only if filled -->
<?php if (count($investigations)): ?>
<div class="sec">Investigations</div>
<div class="content">
<?php foreach ($investigations as $inv): ?>
    <div class="proc-item">
        <strong><?= $inv->investigation_name ?: $inv->title ?></strong>
        <?php if($inv->investigation_type): ?> [<?= $inv->investigation_type->title ?>]<?php endif; ?>
        <?php if($inv->getUnformatted('investigation_date')): ?> — <?= date('d-m-Y', $inv->getUnformatted('investigation_date')) ?><?php endif; ?><br>
        <?php if($inv->investigation_findings): ?><?= nl2br($inv->investigation_findings) ?><?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php elseif ($case->radiology_report || $case->report_and_investigation): ?>
<div class="sec">Investigations</div>
<div class="content"><?= nl2br($case->radiology_report ?: $case->report_and_investigation) ?></div>
<?php endif; ?>

<!-- OPERATION NOTES — pulled from child pages -->
<?php if (count($procedures)): ?>
<?php
$opNotesByProcedure = [];
$standaloneOpNotes = [];
foreach ($pages->find("template=operation-note, parent={$page->id}, sort=sort, sort=created") as $op) {
    $procedureRef = wire('fields')->get('procedure_ref_id') ? $op->getUnformatted('procedure_ref_id') : null;
    $procedureRefId = $procedureRef instanceof Page ? (int) $procedureRef->id : (int) $procedureRef;
    if ($procedureRefId > 0) {
        $opNotesByProcedure[$procedureRefId] = $op;
    } else {
        $standaloneOpNotes[] = $op;
    }
}
$hasOpNotes = count($standaloneOpNotes) > 0;
if (!$hasOpNotes) {
    foreach($procedures as $proc) {
        $op = $opNotesByProcedure[$proc->id] ?? $pages->get("template=operation-note, parent=$proc");
        if($op && $op->id) { $hasOpNotes=true; break; }
    }
}
?>
<?php if ($hasOpNotes): ?>
<div class="sec">Operation Notes</div>
<div class="content">
<?php foreach ($procedures as $proc): ?>
<?php $opNote = $opNotesByProcedure[$proc->id] ?? $pages->get("template=operation-note, parent=$proc"); ?>
<?php if (!$opNote || !$opNote->id) continue; ?>
<div class="proc-item">
    <strong>Date:</strong> <?= $opNote->getUnformatted('surgery_date') ? date('d-m-Y', $opNote->getUnformatted('surgery_date')) : ($proc->getUnformatted('proc_date') ? date('d-m-Y', $proc->getUnformatted('proc_date')) : '') ?><br>
    <strong>Procedure:</strong> <?= $proc->proc_name ?: $proc->title ?><br>
    <?php if($opNote->anesthesia_type): ?><strong>Anesthesia:</strong> <?= $opNote->anesthesia_type->title ?><br><?php endif; ?>
    <?php if($opNote->patient_position): ?><strong>Position:</strong> <?= $opNote->patient_position ?><br><?php endif; ?>
    <?php if($opNote->surgical_approach): ?><strong>Approach:</strong> <?= nl2br($opNote->surgical_approach) ?><br><?php endif; ?>
    <?php if($opNote->intraoperative_findings): ?><strong>Findings:</strong> <?= nl2br($opNote->intraoperative_findings) ?><br><?php endif; ?>
    <?php if($opNote->procedure_steps): ?><?= nl2br($opNote->procedure_steps) ?><?php endif; ?>
    <?php if($opNote->implants_used): ?><br><strong>Implants:</strong> <?= $opNote->implants_used ?><?php endif; ?>
    <?php if($opNote->hemostasis): ?><br><strong>Hemostasis:</strong> <?= $opNote->hemostasis ?><?php endif; ?>
    <?php if($opNote->closure_details): ?><br><strong>Closure:</strong> <?= nl2br($opNote->closure_details) ?><?php endif; ?>
    <?php if($opNote->drains_used): ?><br><strong>Drains:</strong> <?= $opNote->drains_used ?><?php endif; ?>
</div>
<?php endforeach; ?>
<?php foreach ($standaloneOpNotes as $opNote): ?>
<div class="proc-item">
    <strong>Date:</strong> <?= $opNote->getUnformatted('surgery_date') ? date('d-m-Y', $opNote->getUnformatted('surgery_date')) : '' ?><br>
    <strong>Procedure:</strong> <?= preg_replace('/\s+Note$/i', '', $opNote->title ?: 'Operation Note') ?><br>
    <?php if($opNote->anesthesia_type): ?><strong>Anesthesia:</strong> <?= is_object($opNote->anesthesia_type) && isset($opNote->anesthesia_type->title) ? $opNote->anesthesia_type->title : $opNote->anesthesia_type ?><br><?php endif; ?>
    <?php if($opNote->patient_position): ?><strong>Position:</strong> <?= $opNote->patient_position ?><br><?php endif; ?>
    <?php if($opNote->surgical_approach): ?><strong>Approach:</strong> <?= nl2br($opNote->surgical_approach) ?><br><?php endif; ?>
    <?php if($opNote->intraoperative_findings): ?><strong>Findings:</strong> <?= nl2br($opNote->intraoperative_findings) ?><br><?php endif; ?>
    <?php if($opNote->procedure_steps): ?><?= nl2br($opNote->procedure_steps) ?><?php endif; ?>
    <?php if($opNote->implants_used): ?><br><strong>Implants:</strong> <?= $opNote->implants_used ?><?php endif; ?>
    <?php if($opNote->closure_details): ?><br><strong>Closure:</strong> <?= nl2br($opNote->closure_details) ?><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php elseif ($case->procedures && count($case->procedures)): ?>
<?php $hasOldNotes = false; foreach($case->procedures as $p) if($p->operation_notes) { $hasOldNotes=true; break; } ?>
<?php if($hasOldNotes): ?>
<div class="sec">Operation Notes</div>
<div class="content">
<?php foreach ($case->procedures as $proc): ?>
<?php if(!$proc->operation_notes) continue; ?>
<div class="proc-item">
    <strong>Date:</strong> <?= $proc->getUnformatted('procedure_date') ? date('d-m-Y',$proc->getUnformatted('procedure_date')) : '' ?><br>
    <strong>Procedure:</strong> <?= nl2br($proc->procedure_name) ?><br>
    <?= nl2br($proc->operation_notes) ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- HOSPITAL COURSE — only if filled -->
<?php $courseText = $case->post_op_course ?: strip_tags($case->course_in_hospital); ?>
<?php if ($courseText): ?>
<div class="sec">Course in the Hospital</div>
<div class="content"><?= nl2br($courseText) ?>
<?php if($case->complications_postop): ?><br><strong>Complications:</strong> <?= nl2br($case->complications_postop) ?><?php endif; ?>
<?php if($case->wound_status): ?><br><strong>Wound Status:</strong> <?= $case->wound_status ?><?php endif; ?>
</div>
<?php endif; ?>

<!-- CONDITION AT DISCHARGE — only if filled -->
<?php $condText = $case->general_condition ? $case->general_condition->title : strip_tags($case->condition_at_discharge); ?>
<?php if ($condText): ?>
<div class="sec">Condition at Discharge</div>
<div class="content">
<strong>Status of Patient During Discharge:</strong> <?= $condText ?><br>
</div>
<?php endif; ?>

<!-- MEDICATIONS ON DISCHARGE -->
<?php if ($case->medications_on_discharge): ?>
<div class="sec">Medications on Discharge</div>
<div class="content">
<?php $meds = json_decode((string) $case->medications_on_discharge, true); ?>
<?php if (is_array($meds)): ?>
<table class="meds-table">
  <thead>
    <tr>
      <th>Drug</th>
      <th>Dose</th>
      <th>Duration</th>
      <th>Frequency</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($meds as $med): ?>
    <tr>
      <td><?= htmlspecialchars((string) ($med['drug'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string) ($med['dose'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string) ($med['duration'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string) ($med['frequency'] ?? '')) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<?= nl2br($case->medications_on_discharge) ?>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- FOLLOW UP -->
<?php if ($case->follow_up_instructions || $case->getUnformatted('review_date')): ?>
<div class="sec">Follow-up Instructions</div>
<div class="content">
<?= $case->follow_up_instructions ? nl2br($case->follow_up_instructions) . '<br>' : '' ?>
<?= $case->getUnformatted('review_date') ? "<strong>Review Date:</strong> " . date('d/m/Y', $case->getUnformatted('review_date')) : '' ?>
</div>
<?php endif; ?>

<!-- Signature -->
<table class="sig">
<tr>
    <td><br><br><br><strong><?= $consultantLabel ?></strong><br>Consultant</td>
    <td><br><br><br><strong>Prepared by</strong><br>Medical Officer</td>
</tr>
</table>

<div class="footer">
    GangaReg Clinical Registry · Printed: <?= date('d/m/Y H:i') ?> · IP: <?= $case->ip_number ?>
</div>
</body>
</html>
<?php
    $html = ob_get_clean();
    // Close any remaining output buffers (e.g. ProcessWire's outer buffer)
    // so the PDF binary goes directly to stdout instead of being trapped.
    while (ob_get_level()) { ob_end_clean(); }
    $vendorAutoload = $config->paths->root . 'vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 15, 'margin_right' => 15,
            'margin_top' => 20, 'margin_bottom' => 25,
            'default_font' => 'arial',
        ]);
        $statusRaw = $case->getUnformatted('case_status');
        if (is_object($statusRaw) && method_exists($statusRaw, 'first')) {
            $statusRaw = $statusRaw->first() ? $statusRaw->first()->id : 0;
        } elseif (is_object($statusRaw) && isset($statusRaw->id)) {
            $statusRaw = $statusRaw->id;
        }
        if ((int) $statusRaw !== 2) {
            $mpdf->SetWatermarkText('DRAFT');
            $mpdf->showWatermarkText = true;
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output('Discharge_' . $case->ip_number . '_' . date('Ymd') . '.pdf', 'I');
        exit;
    }
    echo $html;
    exit;
}

// ── Route: Default → Timeline Case View ──────────────────────────────────────
$session->redirect($caseViewUrl);
