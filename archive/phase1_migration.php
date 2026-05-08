<?php
/**
 * Phase 1 Migration Script — Schema Refactor
 * Clinical Surgical Registry — Phase 2 Upgrade
 *
 * Run via browser: http://discharge-app.test/phase1_migration.php
 * Requires: ProcessWire installed and running
 *
 * WHAT THIS DOES:
 * 1. Creates global structure templates: consultant, diagnosis-taxonomy, procedure-template-library
 * 2. Creates new structured fields for admission-record
 * 3. Adds case_status, parent_case_reference, audit fields
 * 4. Creates parent pages for global lists
 */

// Bootstrap ProcessWire
$rootPath = __DIR__;
include($rootPath . '/index.php');

// Security: Only allow admin users
if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

// ─── Output helpers ───────────────────────────────────────────────────────────
$log = [];
function step($msg) {
    global $log;
    $log[] = ['type' => 'step', 'msg' => $msg];
    echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $msg</div>\n";
    flush();
}
function ok($msg) {
    global $log;
    $log[] = ['type' => 'ok', 'msg' => $msg];
    echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $msg</div>\n";
    flush();
}
function warn($msg) {
    global $log;
    $log[] = ['type' => 'warn', 'msg' => $msg];
    echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $msg</div>\n";
    flush();
}
function fail($msg) {
    global $log;
    $log[] = ['type' => 'fail', 'msg' => $msg];
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $msg</div>\n";
    flush();
}

// ─── Helper: Create field if not exists ──────────────────────────────────────
function createField($name, $type, $label, $options = []) {
    $f = wire('fields')->get($name);
    if ($f && $f->id) {
        warn("Field '$name' already exists — skipping");
        return $f;
    }
    $f = new Field();
    $f->type = wire('modules')->get($type);
    $f->name = $name;
    $f->label = $label;
    foreach ($options as $k => $v) {
        $f->$k = $v;
    }
    wire('fields')->save($f);
    ok("Created field: $name ($type)");
    return $f;
}

// ─── Helper: Add field to template if not already there ──────────────────────
function addFieldToTemplate($templateName, $fieldName) {
    $t = wire('templates')->get($templateName);
    if (!$t) { fail("Template '$templateName' not found"); return; }
    $f = wire('fields')->get($fieldName);
    if (!$f) { fail("Field '$fieldName' not found"); return; }
    if ($t->fieldgroup->has($f)) {
        warn("Field '$fieldName' already in template '$templateName' — skipping");
        return;
    }
    $t->fieldgroup->add($f);
    $t->fieldgroup->save();
    ok("Added field '$fieldName' to template '$templateName'");
}

// ─── Helper: Create template + fieldgroup if not exists ──────────────────────
function createTemplate($name, $label, $noParents = false, $parentTemplates = [], $childTemplates = []) {
    $t = wire('templates')->get($name);
    if ($t && $t->id) {
        warn("Template '$name' already exists — skipping");
        return $t;
    }
    $fg = wire('fieldgroups')->get($name);
    if (!$fg) {
        $fg = new Fieldgroup();
        $fg->name = $name;
        $fg->add(wire('fields')->get('title'));
        wire('fieldgroups')->save($fg);
    }
    $t = new Template();
    $t->name  = $name;
    $t->label = $label;
    $t->fieldgroup = $fg;
    if ($noParents) $t->noParents = 1;
    wire('templates')->save($t);
    ok("Created template: $name");
    return $t;
}

// ─── Helper: Create parent page if not exists ─────────────────────────────────
function createPage($title, $name, $template, $parentPath) {
    $parent = wire('pages')->get($parentPath);
    if (!$parent || !$parent->id) {
        fail("Parent page '$parentPath' not found");
        return null;
    }
    $existing = wire('pages')->get("parent=$parent, name=$name");
    if ($existing && $existing->id) {
        warn("Page '$name' already exists under '$parentPath' — skipping");
        return $existing;
    }
    $p = new Page();
    $p->template = wire('templates')->get($template);
    $p->parent   = $parent;
    $p->name     = $name;
    $p->title    = $title;
    $p->save();
    ok("Created page: $parentPath/$name");
    return $p;
}

// ═════════════════════════════════════════════════════════════════════════════
// BEGIN PHASE 1 EXECUTION
// ═════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 1 Migration</title>
<style>
body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; padding: 20px; }
h1 { color: #38bdf8; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; }
h2 { color: #7dd3fc; margin-top: 30px; }
.section { margin: 20px 0; }
</style>
</head>
<body>
<h1>🏥 Phase 1 Migration — Schema Refactor</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>
<?php

// ─── SECTION 1: Global Structure Templates ────────────────────────────────────
echo '<h2>SECTION 1 — Global Structure Templates</h2>';

step('Creating consultant template...');
$tConsultant = createTemplate('consultant', 'Consultant');

step('Adding fields to consultant template...');
// consultant needs: title (name), specialty text field
$fSpecialty = createField('consultant_specialty', 'FieldtypeText', 'Specialty / Unit');
$fConsultantCode = createField('consultant_code', 'FieldtypeText', 'Consultant Code');
addFieldToTemplate('consultant', 'consultant_specialty');
addFieldToTemplate('consultant', 'consultant_code');

step('Creating diagnosis-taxonomy template...');
$tDiag = createTemplate('diagnosis-taxonomy', 'Diagnosis Taxonomy');

step('Adding fields to diagnosis-taxonomy template...');
$fDiagCode  = createField('diagnosis_code', 'FieldtypeText', 'Diagnosis Code / ICD');
$fDiagDesc  = createField('diagnosis_description', 'FieldtypeTextarea', 'Full Description');
$fDiagSide  = createField('diagnosis_default_side', 'FieldtypeOptions', 'Default Side');
// Set options for diagnosis_default_side
$fDiagSideField = wire('fields')->get('diagnosis_default_side');
if ($fDiagSideField) {
    $fDiagSideField->type->setOptionsString($fDiagSideField, "1=Right\n2=Left\n3=Bilateral\n4=N/A");
    wire('fields')->save($fDiagSideField);
    ok("Set options for diagnosis_default_side");
}
addFieldToTemplate('diagnosis-taxonomy', 'diagnosis_code');
addFieldToTemplate('diagnosis-taxonomy', 'diagnosis_description');
addFieldToTemplate('diagnosis-taxonomy', 'diagnosis_default_side');

step('Creating procedure-template-library template...');
$tProcLib = createTemplate('procedure-template-library', 'Procedure Template Library');

step('Adding fields to procedure-template-library template...');
$fProcLibNotes = createField('default_operation_note_text', 'FieldtypeTextarea', 'Default Operation Note Template Text');
$fProcLibAnesthesia = createField('default_anesthesia_type', 'FieldtypeOptions', 'Default Anesthesia Type');
$fProcLibAnesthesiaField = wire('fields')->get('default_anesthesia_type');
if ($fProcLibAnesthesiaField) {
    $fProcLibAnesthesiaField->type->setOptionsString($fProcLibAnesthesiaField, "1=GA\n2=LA\n3=Regional");
    wire('fields')->save($fProcLibAnesthesiaField);
    ok("Set options for default_anesthesia_type");
}
addFieldToTemplate('procedure-template-library', 'default_operation_note_text');
addFieldToTemplate('procedure-template-library', 'default_anesthesia_type');

// ─── SECTION 2: Create Parent Pages for Global Lists ─────────────────────────
echo '<h2>SECTION 2 — Parent Pages for Global Lists</h2>';

step('Creating /consultants/ parent page...');
createPage('Consultants', 'consultants', 'consultant', '/');

step('Creating /diagnoses/ parent page...');
createPage('Diagnoses', 'diagnoses', 'diagnosis-taxonomy', '/');

step('Creating /procedure-templates/ parent page...');
createPage('Procedure Templates', 'procedure-templates', 'procedure-template-library', '/');

// Allow these templates to have children of same type
$tConsultant = wire('templates')->get('consultant');
if ($tConsultant) {
    $tConsultant->noChildren = 1; // leaf nodes
    wire('templates')->save($tConsultant);
}
$tDiag = wire('templates')->get('diagnosis-taxonomy');
if ($tDiag) {
    $tDiag->noChildren = 1;
    wire('templates')->save($tDiag);
}
$tProcLib = wire('templates')->get('procedure-template-library');
if ($tProcLib) {
    $tProcLib->noChildren = 1;
    wire('templates')->save($tProcLib);
}

// ─── SECTION 3: New Structured Fields for admission-record ────────────────────
echo '<h2>SECTION 3 — Structured Fields for Admission-Record</h2>';

step('Creating case_status options field...');
$fCaseStatus = createField('case_status', 'FieldtypeOptions', 'Case Status');
$fCaseStatusField = wire('fields')->get('case_status');
if ($fCaseStatusField) {
    $fCaseStatusField->type->setOptionsString($fCaseStatusField, "1=Active\n2=Discharged\n3=Inactive\n4=Cancelled");
    $fCaseStatusField->defaultValue = 1; // Active
    wire('fields')->save($fCaseStatusField);
    ok("Set options for case_status (Active/Discharged/Inactive/Cancelled)");
}

step('Creating parent_case_reference page-reference field...');
$fParentCase = createField('parent_case_reference', 'FieldtypePage', 'Parent Case Reference', [
    'derefAsPage' => 1,
    'description' => 'Reference to a previous admission for this patient (re-admission tracking)',
]);
$fParentCaseField = wire('fields')->get('parent_case_reference');
if ($fParentCaseField) {
    $fParentCaseField->findPagesSelector = 'template=admission-record';
    wire('fields')->save($fParentCaseField);
    ok("Configured parent_case_reference to point to admission-record pages");
}

step('Creating audit fields: created_by_user, updated_by_user...');
$fCreatedBy = createField('created_by_user', 'FieldtypeInteger', 'Created By (User ID)', [
    'description' => 'Stores the ProcessWire user ID of the creator',
    'collapsed'   => Inputfield::collapsedHidden,
]);
$fUpdatedBy = createField('updated_by_user', 'FieldtypeInteger', 'Updated By (User ID)', [
    'description' => 'Stores the ProcessWire user ID of the last editor',
    'collapsed'   => Inputfield::collapsedHidden,
]);

step('Creating diagnosis structured fields...');
// Primary diagnosis as page reference (taxonomy)
$fPrimaryDiag = createField('primary_diagnosis_ref', 'FieldtypePage', 'Primary Diagnosis', [
    'derefAsPage' => 1,
    'description' => 'Select from diagnosis taxonomy',
]);
$fPrimaryDiagField = wire('fields')->get('primary_diagnosis_ref');
if ($fPrimaryDiagField) {
    $fPrimaryDiagField->findPagesSelector = 'template=diagnosis-taxonomy';
    wire('fields')->save($fPrimaryDiagField);
    ok("Configured primary_diagnosis_ref to pull from diagnosis-taxonomy");
}

$fDiagSideCase = createField('diagnosis_side', 'FieldtypeOptions', 'Side');
$fDiagSideCaseField = wire('fields')->get('diagnosis_side');
if ($fDiagSideCaseField) {
    $fDiagSideCaseField->type->setOptionsString($fDiagSideCaseField, "1=Right\n2=Left\n3=Bilateral");
    wire('fields')->save($fDiagSideCaseField);
    ok("Set options for diagnosis_side (Right/Left/Bilateral)");
}

$fAssocConditions = createField('associated_conditions', 'FieldtypeTextarea', 'Associated Conditions');

step('Creating consultant reference field for admission...');
$fConsultantRef = createField('consultant_ref', 'FieldtypePage', 'Consultant', [
    'derefAsPage' => 1,
    'description' => 'Select from consultant list',
]);
$fConsultantRefField = wire('fields')->get('consultant_ref');
if ($fConsultantRefField) {
    $fConsultantRefField->findPagesSelector = 'template=consultant';
    wire('fields')->save($fConsultantRefField);
    ok("Configured consultant_ref to pull from consultant pages");
}

step('Creating additional header fields...');
$fMlcStatus = createField('mlc_status', 'FieldtypeOptions', 'MLC Status');
$fMlcField = wire('fields')->get('mlc_status');
if ($fMlcField) {
    $fMlcField->type->setOptionsString($fMlcField, "1=No\n2=Yes");
    $fMlcField->defaultValue = 1;
    wire('fields')->save($fMlcField);
    ok("Set options for mlc_status");
}

$fAge     = createField('patient_age', 'FieldtypeInteger', 'Age');
$fAgeUnit = createField('age_unit', 'FieldtypeOptions', 'Age Unit');
$fAgeUnitField = wire('fields')->get('age_unit');
if ($fAgeUnitField) {
    $fAgeUnitField->type->setOptionsString($fAgeUnitField, "1=Years\n2=Months\n3=Days");
    $fAgeUnitField->defaultValue = 1;
    wire('fields')->save($fAgeUnitField);
    ok("Set options for age_unit");
}

$fSecPhone      = createField('secondary_phone', 'FieldtypeText', 'Secondary Phone');
$fRoomBed       = createField('room_bed', 'FieldtypeText', 'Room / Bed');

step('Creating admission date / discharge date fields...');
// Stored as Unix timestamps (integers) — consistent with discharged_on usage in ready.php
createField('admitted_on',   'FieldtypeInteger', 'Admitted On (Unix Timestamp)',  ['collapsed' => Inputfield::collapsedNo]);
createField('discharged_on', 'FieldtypeInteger', 'Discharged On (Unix Timestamp)', ['collapsed' => Inputfield::collapsedNo]);

step('Creating consultant / unit text fallback fields on admission-record...');
createField('discharge_consultant', 'FieldtypeText', 'Consultant (Text Fallback)', ['maxlength' => 128]);
createField('admitting_unit',       'FieldtypeText', 'Admitting Unit / Ward',       ['maxlength' => 128]);

// ─── SECTION 4: Add New Fields to admission-record Template ──────────────────
echo '<h2>SECTION 4 — Attach New Fields to admission-record</h2>';

$fieldsToAdd = [
    'admitted_on',
    'discharged_on',
    'case_status',
    'parent_case_reference',
    'created_by_user',
    'updated_by_user',
    'primary_diagnosis_ref',
    'diagnosis_side',
    'associated_conditions',
    'consultant_ref',
    'discharge_consultant',
    'admitting_unit',
    'mlc_status',
    'patient_age',
    'age_unit',
    'secondary_phone',
    'room_bed',
];

foreach ($fieldsToAdd as $fn) {
    addFieldToTemplate('admission-record', $fn);
}

// ─── SECTION 5: Patient-Record Fields (demographics) ─────────────────────────
echo '<h2>SECTION 5 — Attach Fields to patient-record</h2>';

step('Creating patient demographic fields (if not already present)...');
createField('patient_id',    'FieldtypeText', 'Patient ID (Auto-generated)', ['maxlength' => 32, 'collapsed' => Inputfield::collapsedNo]);
createField('date_of_birth', 'FieldtypeInteger', 'Date of Birth (Unix Timestamp)', ['collapsed' => Inputfield::collapsedNo]);

$fGender = createField('gender', 'FieldtypeOptions', 'Gender');
$fGenderField = wire('fields')->get('gender');
if ($fGenderField && !$fGenderField->type->getOptions($fGenderField)->count()) {
    $fGenderField->type->setOptionsString($fGenderField, "1=Male\n2=Female\n3=Other");
    wire('fields')->save($fGenderField);
    ok("Set options for gender field");
}

createField('guardian_name', 'FieldtypeText',     'Guardian / Relative Name', ['maxlength' => 128]);
createField('phone',         'FieldtypeText',     'Phone Number',             ['maxlength' => 20]);
createField('address',       'FieldtypeTextarea', 'Address',                  ['rows' => 3]);

$patientFields = [
    'patient_id', 'date_of_birth', 'gender',
    'guardian_name', 'phone', 'address',
    'created_by_user', 'updated_by_user',
];
foreach ($patientFields as $fn) {
    addFieldToTemplate('patient-record', $fn);
}

// ─── SECTION 6: Seed Sample Global Data ─────────────────────────────────────
echo '<h2>SECTION 6 — Seed Sample Global Data</h2>';

step('Seeding consultant entries...');
$consultantsPage = wire('pages')->get('name=consultants');
if ($consultantsPage && $consultantsPage->id) {
    $sampleConsultants = [
        ['name' => 'dr-raja-sabapathy', 'title' => 'Dr Raja Sabapathy', 'specialty' => 'Hand Surgery & Microsurgery'],
        ['name' => 'dr-arun-kumar',     'title' => 'Dr Arun Kumar',     'specialty' => 'Plastic Surgery'],
        ['name' => 'dr-mohan-das',      'title' => 'Dr Mohan Das',      'specialty' => 'Orthopaedics'],
    ];
    foreach ($sampleConsultants as $c) {
        $existing = wire('pages')->get("parent=$consultantsPage, name={$c['name']}");
        if ($existing && $existing->id) {
            warn("Consultant '{$c['title']}' already exists — skipping");
            continue;
        }
        $p = new Page();
        $p->template = wire('templates')->get('consultant');
        $p->parent   = $consultantsPage;
        $p->name     = $c['name'];
        $p->title    = $c['title'];
        $p->consultant_specialty = $c['specialty'];
        $p->save();
        ok("Seeded consultant: {$c['title']}");
    }
}

step('Seeding sample diagnosis taxonomy entries...');
$diagnosesPage = wire('pages')->get('name=diagnoses');
if ($diagnosesPage && $diagnosesPage->id) {
    $sampleDiagnoses = [
        ['name' => 'brachial-plexus-injury',  'title' => 'Brachial Plexus Injury',  'code' => 'S14.3'],
        ['name' => 'flexor-tendon-injury',     'title' => 'Flexor Tendon Injury',    'code' => 'S66.0'],
        ['name' => 'extensor-tendon-injury',   'title' => 'Extensor Tendon Injury',  'code' => 'S66.2'],
        ['name' => 'peripheral-nerve-injury',  'title' => 'Peripheral Nerve Injury', 'code' => 'S54'],
        ['name' => 'crush-injury-hand',        'title' => 'Crush Injury Hand',       'code' => 'S67'],
        ['name' => 'replantation',             'title' => 'Replantation',            'code' => 'Z89'],
        ['name' => 'free-flap-coverage',       'title' => 'Free Flap Coverage',      'code' => 'Z87.39'],
        ['name' => 'radial-nerve-palsy',       'title' => 'Radial Nerve Palsy',      'code' => 'G54.2'],
        ['name' => 'median-nerve-injury',      'title' => 'Median Nerve Injury',     'code' => 'S54.1'],
        ['name' => 'ulnar-nerve-injury',       'title' => 'Ulnar Nerve Injury',      'code' => 'S54.0'],
    ];
    foreach ($sampleDiagnoses as $d) {
        $existing = wire('pages')->get("parent=$diagnosesPage, name={$d['name']}");
        if ($existing && $existing->id) {
            warn("Diagnosis '{$d['title']}' already exists — skipping");
            continue;
        }
        $p = new Page();
        $p->template   = wire('templates')->get('diagnosis-taxonomy');
        $p->parent     = $diagnosesPage;
        $p->name       = $d['name'];
        $p->title      = $d['title'];
        $p->diagnosis_code = $d['code'];
        $p->save();
        ok("Seeded diagnosis: {$d['title']} ({$d['code']})");
    }
}

step('Seeding procedure template library entries...');
$procTemplatesPage = wire('pages')->get('name=procedure-templates');
if ($procTemplatesPage && $procTemplatesPage->id) {
    $sampleProcTemplates = [
        [
            'name'  => 'nerve-repair',
            'title' => 'Nerve Repair',
            'note'  => "NERVE REPAIR\n\nSurgeon: [Name]\nAssistant: [Name]\nAnesthesia: GA / Brachial Block\n\nPositioning: Patient supine, arm on hand table.\n\nApproach: Longitudinal incision over [site].\n\nFindings: [Nerve name] found transected at [level]. Proximal and distal stumps identified.\n\nProcedure:\n- Neuroma excised from proximal stump.\n- Distal stump freshened.\n- Epineural repair performed using 8-0 Nylon sutures under magnification.\n- [If graft]: Sural nerve graft harvested [length] and interposed.\n- Tension-free anastomosis achieved.\n\nHemostasis: Achieved.\nWound Closure: Subcutaneous 3-0 Vicryl, skin 4-0 Nylon.\nDressing: Moist gauze + backslab.\n\nPost-op Instructions: Splint in position of safety. Review at 5 days.",
        ],
        [
            'name'  => 'tendon-repair',
            'title' => 'Flexor Tendon Repair',
            'note'  => "FLEXOR TENDON REPAIR\n\nSurgeon: [Name]\nAssistant: [Name]\nAnesthesia: [GA / LA + Tourniquet]\n\nPositioning: Supine, arm on hand table, tourniquet applied.\n\nApproach: Brunner-type incision over [zone].\n\nFindings: [FDP/FDS] tendon found cut at Zone [II/III/IV]. [Neurovascular status: intact/injured].\n\nProcedure:\n- Proximal tendon retrieved and held with 26G needle.\n- Modified Kessler core suture using 3-0 Prolene (4-strand).\n- Running epitendinous suture with 6-0 Prolene.\n- Full passive ROM confirmed with no gapping.\n\nTourniquet time: [X] minutes.\nHemostasis: Achieved on deflation.\nClosure: 4-0 Nylon.\nDressing: Dorsal blocking splint at 30° wrist flexion.\n\nPost-op: Early active mobilisation protocol at 48hrs.",
        ],
        [
            'name'  => 'free-flap',
            'title' => 'Free Flap Coverage',
            'note'  => "FREE FLAP COVERAGE\n\nSurgeon: [Name]\nAssistant: [Name]\nAnesthesia: GA\n\nDefect: [Size] cm defect over [site] with [bone/tendon/vessel] exposure.\n\nFlap Chosen: [ALT / Gracilis / Latissimus] Flap\n\nDonor Site: [Thigh / Groin / Back]\nRecipient Vessels: [Artery] and [Vein] at [site]\n\nProcedure:\n- Flap raised on perforator vessels.\n- Pedicle length: [X] cm.\n- End-to-end arterial anastomosis with 9-0 Nylon.\n- End-to-end venous anastomosis with 9-0 Nylon (x2 veins).\n- Flap inset and secured.\n- Doppler signal confirmed post-anastomosis.\n\nDonor site: [Primary closure / STSG].\nHemostasis: Achieved.\nDrains: [Suction drain] placed.\nDressing: Window dressing for flap monitoring.",
        ],
        [
            'name'  => 'replantation',
            'title' => 'Digit / Limb Replantation',
            'note'  => "REPLANTATION\n\nSurgeon: [Name]\nAssistant: [Name]\nAnesthesia: GA + Regional\n\nAmputated Part: [Digit/Hand/Forearm] — [Level]\nIschemia Time: Cold: [X]hrs / Warm: [X]hrs\n\nSequence of Repair:\n1. Bone shortening and fixation: K-wire / plate\n2. Extensor tendon: [Repair details]\n3. Flexor tendon: [Repair details]\n4. Arterial anastomosis: [Vessel] end-to-end, 9-0 Nylon\n5. Venous anastomosis: [X] veins, 9-0 Nylon\n6. Nerve repair: [Nerve] epineural repair, 8-0 Nylon\n7. Skin closure: 4-0 Nylon\n\nReperfusion: Achieved at [time]. Capillary refill [X] seconds.\n\nDressing: Bulky non-compressive. Monitoring window.\nPost-op: Anticoagulation protocol. Hourly monitoring for 72hrs.",
        ],
    ];
    foreach ($sampleProcTemplates as $pt) {
        $existing = wire('pages')->get("parent=$procTemplatesPage, name={$pt['name']}");
        if ($existing && $existing->id) {
            warn("Procedure template '{$pt['title']}' already exists — skipping");
            continue;
        }
        $p = new Page();
        $p->template = wire('templates')->get('procedure-template-library');
        $p->parent   = $procTemplatesPage;
        $p->name     = $pt['name'];
        $p->title    = $pt['title'];
        $p->default_operation_note_text = $pt['note'];
        $p->save();
        ok("Seeded procedure template: {$pt['title']}");
    }
}

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 1 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">What was done:</h3>';
echo '<ul style="color:#94a3b8;">';
echo '<li>Created templates: <strong>consultant</strong>, <strong>diagnosis-taxonomy</strong>, <strong>procedure-template-library</strong></li>';
echo '<li>Created parent pages: /consultants/, /diagnoses/, /procedure-templates/</li>';
echo '<li>Created fields: case_status, parent_case_reference, created_by_user, updated_by_user,
    primary_diagnosis_ref, diagnosis_side, associated_conditions, consultant_ref, mlc_status, patient_age, age_unit, secondary_phone, room_bed</li>';
echo '<li>Attached all new fields to admission-record template</li>';
echo '<li>Seeded sample consultants, diagnoses, and procedure templates</li>';
echo '</ul>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase2_migration.php to create Procedure + Operation Note entities</p>';
echo '</div>';
?>
</body>
</html>
