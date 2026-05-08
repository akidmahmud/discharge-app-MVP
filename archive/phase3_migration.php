<?php
/**
 * Phase 3 Migration — Data Structure Fix
 * Adds all structured clinical fields per the blueprint specification:
 * - Section 4: History & Complaints fields
 * - Section 5: Examination fields (vitals, local exam, movement & function)
 * - Section 6: Investigation fields
 * - Section 7: Treatment Plan fields
 * - Section 9: Hospital Course fields
 * - Section 10: Condition at Discharge fields
 * - Reduces CKEditor usage for structured data
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
function fail($m) { echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $m</div>\n"; flush(); }

function createField($name, $type, $label, $options = []) {
    $f = wire('fields')->get($name);
    if ($f && $f->id) { warn("Field '$name' already exists — skipping"); return $f; }
    $f = new Field();
    $f->type  = wire('modules')->get($type);
    $f->name  = $name;
    $f->label = $label;
    foreach ($options as $k => $v) $f->$k = $v;
    wire('fields')->save($f);
    ok("Created field: $name");
    return $f;
}

function addToTemplate($tName, $fName) {
    $t = wire('templates')->get($tName);
    if (!$t) { fail("Template '$tName' not found"); return; }
    $f = wire('fields')->get($fName);
    if (!$f) { fail("Field '$fName' not found"); return; }
    if ($t->fieldgroup->has($f)) { warn("'$fName' already in '$tName'"); return; }
    $t->fieldgroup->add($f);
    $t->fieldgroup->save();
    ok("  → '$fName' added to '$tName'");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 3 Migration — Structured Clinical Fields</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
h3 { color:#a78bfa; margin-top:15px; }
</style>
</head>
<body>
<h1>🧬 Phase 3 Migration — Structured Clinical Fields</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 4: HISTORY & PRESENTING COMPLAINTS (blueprint Section 4)
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION A — History & Presenting Complaints Fields</h2>';
step('Creating structured history fields...');

createField('chief_complaint',    'FieldtypeTextarea', 'Chief Complaint');
createField('comorbidities',      'FieldtypeTextarea', 'Comorbidities');
createField('drug_history',       'FieldtypeTextarea', 'Drug History');
createField('family_history',     'FieldtypeTextarea', 'Family History');
createField('past_treatment',     'FieldtypeTextarea', 'Past Treatment');
// previous_surgeries = repeater (date + procedure text) — kept as textarea for MVP, structured in Phase 5
createField('previous_surgeries', 'FieldtypeTextarea', 'Previous Surgeries (Date + Procedure)');

$historyFields = ['chief_complaint','comorbidities','drug_history','family_history','past_treatment','previous_surgeries'];
echo '<h3>Attaching to admission-record...</h3>';
foreach ($historyFields as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 5A: GENERAL EXAMINATION (Vitals — structured)
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION B — General Examination (Vitals)</h2>';
step('Creating vitals fields...');

createField('vitals_pulse',       'FieldtypeText', 'Pulse (bpm)');
createField('vitals_bp',          'FieldtypeText', 'Blood Pressure (mmHg)');
createField('vitals_temp',        'FieldtypeText', 'Temperature (°C)');
createField('vitals_spo2',        'FieldtypeText', 'SpO2 (%)');
createField('vitals_rr',          'FieldtypeText', 'Respiratory Rate');
createField('vitals_weight',      'FieldtypeText', 'Weight (kg)');

$vitalsFields = ['vitals_pulse','vitals_bp','vitals_temp','vitals_spo2','vitals_rr','vitals_weight'];
foreach ($vitalsFields as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 5B: LOCAL EXAMINATION
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION C — Local Examination</h2>';
step('Creating local examination fields...');

createField('limb_side',     'FieldtypeOptions',  'Limb Side');
$limbSide = wire('fields')->get('limb_side');
if ($limbSide) {
    $limbSide->type->setOptionsString($limbSide, "1=Right\n2=Left\n3=Bilateral");
    wire('fields')->save($limbSide);
    ok("Set limb_side options");
}

createField('inspection',    'FieldtypeTextarea', 'Inspection');
createField('palpation',     'FieldtypeTextarea', 'Palpation');

createField('distal_pulse',  'FieldtypeOptions',  'Distal Pulse');
$distalPulse = wire('fields')->get('distal_pulse');
if ($distalPulse) {
    $distalPulse->type->setOptionsString($distalPulse, "1=Present\n2=Absent\n3=Diminished");
    wire('fields')->save($distalPulse);
    ok("Set distal_pulse options");
}

createField('sensation',     'FieldtypeText',     'Sensation');

$localExamFields = ['limb_side','inspection','palpation','distal_pulse','sensation'];
foreach ($localExamFields as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 5C: MOVEMENT & FUNCTION (auto-suggest "Full" = default)
// All 12 movement fields per blueprint
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION D — Movement & Function Fields</h2>';
step('Creating movement fields (default: Full)...');

$movementFields = [
    'shoulder_abduction_active'  => 'Shoulder Abduction (Active)',
    'shoulder_abduction_passive' => 'Shoulder Abduction (Passive)',
    'shoulder_external_rotation' => 'Shoulder External Rotation',
    'shoulder_internal_rotation' => 'Shoulder Internal Rotation',
    'elbow_flexion'              => 'Elbow Flexion',
    'elbow_extension'            => 'Elbow Extension',
    'forearm_supination'         => 'Forearm Supination',
    'forearm_pronation'          => 'Forearm Pronation',
    'wrist_flexion'              => 'Wrist Flexion',
    'wrist_extension'            => 'Wrist Extension',
    'finger_flexion'             => 'Finger Flexion',
    'finger_extension'           => 'Finger Extension',
];

foreach ($movementFields as $fname => $flabel) {
    $f = wire('fields')->get($fname);
    if (!$f || !$f->id) {
        $f = new Field();
        $f->type  = wire('modules')->get('FieldtypeText');
        $f->name  = $fname;
        $f->label = $flabel;
        $f->placeholder = 'Full';  // Auto-suggest placeholder
        $f->notes = 'Default: Full (leave blank = Full)';
        wire('fields')->save($f);
        ok("Created movement field: $fname (placeholder: Full)");
    } else {
        warn("Movement field '$fname' already exists");
    }
    addToTemplate('admission-record', $fname);
}

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 5D: SPECIAL TESTS / SCORES
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION E — Special Tests & Scores</h2>';
createField('special_tests', 'FieldtypeTextarea', 'Special Tests & Scores');
addToTemplate('admission-record', 'special_tests');

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 6: INVESTIGATIONS (entity-based via investigation template)
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION F — Investigation Template</h2>';
step('Creating investigation template...');

$tInv = wire('templates')->get('investigation');
if (!$tInv || !$tInv->id) {
    $fgInv = wire('fieldgroups')->get('investigation');
    if (!$fgInv) {
        $fgInv = new Fieldgroup();
        $fgInv->name = 'investigation';
        $fgInv->add(wire('fields')->get('title'));
        wire('fieldgroups')->save($fgInv);
    }
    $tInv = new Template();
    $tInv->name       = 'investigation';
    $tInv->label      = 'Investigation';
    $tInv->fieldgroup = $fgInv;
    $tInv->noChildren = 1;
    wire('templates')->save($tInv);
    ok("Created template: investigation");
} else {
    warn("Template 'investigation' already exists");
}

step('Creating investigation fields (blueprint Section 6)...');
createField('investigation_date', 'FieldtypeDatetime', 'Investigation Date');
createField('investigation_type', 'FieldtypeOptions',  'Investigation Type');
$invTypeField = wire('fields')->get('investigation_type');
if ($invTypeField) {
    $invTypeField->type->setOptionsString($invTypeField, "1=MRI\n2=X-Ray\n3=CT Scan\n4=Ultrasound\n5=Lab\n6=EMG/NCS\n7=Other");
    wire('fields')->save($invTypeField);
    ok("Set investigation_type options");
}
createField('investigation_name',     'FieldtypeText',      'Investigation Name');
createField('investigation_findings', 'FieldtypeTextarea',  'Findings');
createField('investigation_files',    'FieldtypeFile',      'Report Files');
createField('investigation_images',   'FieldtypeImage',     'Images');

$invFields = [
    'investigation_date','investigation_type','investigation_name',
    'investigation_findings','investigation_files','investigation_images',
    'created_by_user','updated_by_user',
];
foreach ($invFields as $fn) addToTemplate('investigation', $fn);

// Add investigation as allowed child of admission-record
step('Allowing investigation as child of admission-record...');
$tAdm = wire('templates')->get('admission-record');
if ($tAdm) {
    $existingChildren = $tAdm->childTemplates;
    $invId = wire('templates')->get('investigation')->id;
    $procId = wire('templates')->get('procedure') ? wire('templates')->get('procedure')->id : 0;
    if (!in_array($invId, $existingChildren)) {
        $existingChildren[] = $invId;
        $tAdm->childTemplates = $existingChildren;
        wire('templates')->save($tAdm);
        ok("investigation added as allowed child of admission-record");
    } else {
        warn("investigation already allowed as child of admission-record");
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 7: TREATMENT PLAN
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION G — Treatment Plan Fields</h2>';
step('Creating treatment plan fields (blueprint Section 7)...');

createField('proposed_procedure',      'FieldtypeTextarea', 'Proposed Procedure');
createField('staging_plan',            'FieldtypeTextarea', 'Staging Plan');
createField('alternatives_discussed',  'FieldtypeTextarea', 'Alternatives Discussed');
createField('risks_explained',         'FieldtypeTextarea', 'Risks Explained');
createField('consent_status',          'FieldtypeOptions',  'Consent Status');
$consentField = wire('fields')->get('consent_status');
if ($consentField) {
    $consentField->type->setOptionsString($consentField, "1=Taken\n2=Not Taken\n3=Refused");
    wire('fields')->save($consentField);
    ok("Set consent_status options");
}
createField('counseling_notes',        'FieldtypeTextarea', 'Counseling Notes');
createField('treatment_plan_files',    'FieldtypeFile',     'Treatment Plan Files');

$treatmentFields = [
    'proposed_procedure','staging_plan','alternatives_discussed',
    'risks_explained','consent_status','counseling_notes','treatment_plan_files',
];
foreach ($treatmentFields as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 9: HOSPITAL COURSE
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION H — Hospital Course Fields</h2>';
step('Creating hospital course fields (blueprint Section 9)...');

createField('post_op_course',       'FieldtypeTextarea', 'Post-Op Course');
createField('complications_postop', 'FieldtypeTextarea', 'Post-Op Complications');
createField('wound_status',         'FieldtypeText',     'Wound Status');
createField('overall_progress',     'FieldtypeTextarea', 'Overall Progress');

$hospitalCourseFields = ['post_op_course','complications_postop','wound_status','overall_progress'];
foreach ($hospitalCourseFields as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// SECTION 10: CONDITION AT DISCHARGE (auto-suggest defaults per blueprint)
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION I — Condition at Discharge Fields</h2>';
step('Creating discharge condition fields (blueprint Section 10)...');

// general_condition dropdown with auto-suggested default "Stable"
createField('general_condition', 'FieldtypeOptions', 'General Condition');
$gcField = wire('fields')->get('general_condition');
if ($gcField) {
    $gcField->type->setOptionsString($gcField, "1=Stable\n2=Improving\n3=Critical\n4=Discharged Against Advice");
    $gcField->defaultValue = 1; // Stable
    wire('fields')->save($gcField);
    ok("Set general_condition options (default: Stable)");
}

// Structured text fields with default placeholder values (auto-suggest per blueprint)
$dischargeTextFields = [
    'pain_status'        => ['Pain Status',        'Minimal'],
    'wound_condition'    => ['Wound Condition',    'Healthy, healing well'],
    'dressing_status'    => ['Dressing Status',    'Intact'],
    'limb_status'        => ['Limb Status',        'Good vascularity, sensation improving'],
    'mobility_status'    => ['Mobility Status',    'Acceptable'],
];

foreach ($dischargeTextFields as $fname => [$flabel, $placeholder]) {
    $f = wire('fields')->get($fname);
    if (!$f || !$f->id) {
        $f = new Field();
        $f->type  = wire('modules')->get('FieldtypeText');
        $f->name  = $fname;
        $f->label = $flabel;
        $f->placeholder = $placeholder;
        $f->notes = "Default suggestion: $placeholder";
        wire('fields')->save($f);
        ok("Created discharge field: $fname (default: $placeholder)");
    } else {
        warn("Field '$fname' already exists");
    }
    addToTemplate('admission-record', $fname);
}
addToTemplate('admission-record', 'general_condition');

// Medications on discharge — kept as structured textarea (table format in UI)
createField('medications_on_discharge', 'FieldtypeTextarea', 'Medications on Discharge');
createField('splint_cast_details',      'FieldtypeText',     'Splint / Cast Details');
createField('follow_up_instructions',   'FieldtypeTextarea', 'Follow-up Instructions');
createField('review_date',              'FieldtypeDatetime', 'Review Date');

$dischargeExtra = ['medications_on_discharge','splint_cast_details','follow_up_instructions','review_date'];
foreach ($dischargeExtra as $fn) addToTemplate('admission-record', $fn);

// ═════════════════════════════════════════════════════════════════════════════
// LEGACY FIELD CLEANUP NOTICE (do NOT remove fields — just note for migration)
// ═════════════════════════════════════════════════════════════════════════════
echo '<h2>SECTION J — Legacy Field Mapping</h2>';
echo '<div style="background:#1e293b;padding:12px;border-radius:6px;color:#94a3b8;">';
echo '<p>The following existing fields are being <strong>superseded by structured alternatives</strong>.
They remain active for backward compatibility with existing data. Future new entries should use the
new structured fields. A data migration to populate new fields from existing CKEditor blobs
is in Phase 3 optional cleanup.</p>';
echo '<ul>';
$legacy = [
    'diagnosis' => 'Superseded by primary_diagnosis_ref (Page ref) + associated_conditions',
    'history_complaints' => 'Superseded by chief_complaint, comorbidities, drug_history, family_history',
    'examination_findings' => 'Superseded by inspection, palpation, limb_side, movement fields',
    'course_in_hospital' => 'Superseded by post_op_course, complications_postop, wound_status, overall_progress',
    'condition_at_discharge' => 'Superseded by general_condition, pain_status, wound_condition, etc.',
    'treatment_consideration' => 'Superseded by proposed_procedure, staging_plan, alternatives_discussed',
];
foreach ($legacy as $old => $note) {
    echo "<li><code>$old</code> → $note</li>";
}
echo '</ul></div>';

// ─── FINAL SUMMARY ───────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 3 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">Structured fields added:</h3>';
echo '<ul style="color:#94a3b8;">';
echo '<li>History & Complaints: 6 fields</li>';
echo '<li>Vitals: 6 structured fields (pulse, BP, temp, SpO2, RR, weight)</li>';
echo '<li>Local Examination: limb_side, inspection, palpation, distal_pulse, sensation</li>';
echo '<li>Movement & Function: <strong>12 fields</strong> (all joints per blueprint) with placeholder "Full"</li>';
echo '<li>Special Tests: 1 field</li>';
echo '<li>Investigation template: 6 fields + image/file upload</li>';
echo '<li>Treatment Plan: 6 fields + file upload</li>';
echo '<li>Hospital Course: 4 structured fields</li>';
echo '<li>Condition at Discharge: 8 fields with auto-suggest defaults</li>';
echo '</ul>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase4_migration.php to implement role system + workflow engine</p>';
echo '</div>';
?>
</body>
</html>
