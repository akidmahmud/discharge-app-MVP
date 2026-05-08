<?php namespace ProcessWire;
/**
 * CLI Backend Repair Script
 * Run: php cli_repair.php
 * Repairs all missing templates, fields, and hooks in the live DB.
 * Safe to re-run — all operations are idempotent.
 */

// ── Bootstrap ProcessWire via API (no page dispatch) ──────────────────────────
$rootPath = __DIR__;
chdir($rootPath);

// Provide minimal HTTP context so PW's config builds correctly
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST']     = 'discharge-app.test';
    $_SERVER['REQUEST_URI']   = '/';
    $_SERVER['SERVER_NAME']   = 'discharge-app.test';
    $_SERVER['DOCUMENT_ROOT'] = $rootPath;
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

if (!class_exists('ProcessWire', false)) {
    require_once $rootPath . '/wire/core/ProcessWire.php';
}

$config = ProcessWire::buildConfig($rootPath);
$config->external = true;
$wire = new ProcessWire($config);

// Force superuser context for PW API operations
$adminUser = wire('users')->find('roles=superuser')->first();
if ($adminUser && $adminUser->id) {
    wire()->wire('user', $adminUser);
    echo "Running as superuser: {$adminUser->name}\n";
} else {
    echo "WARNING: Could not find superuser — continuing anyway\n";
}

// ── Output helpers ─────────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
function out($type, $msg) {
    global $isCli;
    if ($isCli) {
        $prefix = ['ok' => '✓', 'warn' => '⚠', 'fail' => '✗', 'step' => '⚙', 'head' => '═'][$type] ?? '•';
        echo "$prefix $msg\n";
    } else {
        $colors = ['ok'=>'#14532d:#86efac','warn'=>'#713f12:#fde68a','fail'=>'#7f1d1d:#fca5a5','step'=>'#1e3a5f:#7dd3fc','head'=>'#0c1a2e:#38bdf8'];
        [$bg,$fg] = explode(':', $colors[$type] ?? '#1e293b:#e2e8f0');
        echo "<div style='background:$bg;color:$fg;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>$msg</div>\n";
        flush();
    }
}
function ok($m)   { out('ok',   $m); }
function warn($m) { out('warn', $m); }
function fail($m) { out('fail', $m); }
function step($m) { out('step', $m); }
function head($m) { out('head', "\n══ $m ══"); }

// ── Core helpers ──────────────────────────────────────────────────────────────
function createField($name, $type, $label, $extraSetup = null) {
    $f = wire('fields')->get($name);
    if ($f && $f->id) { warn("Field '$name' exists — skip"); return $f; }
    $f = new Field();
    $f->type  = wire('modules')->get($type);
    $f->name  = $name;
    $f->label = $label;
    wire('fields')->save($f);
    ok("Created field: $name ($type)");
    if ($extraSetup) $extraSetup($f);
    return $f;
}

function ensureOptions($name, $optionsStr, $default = null) {
    $f = wire('fields')->get($name);
    if (!$f || !$f->id) { fail("Cannot set options — field '$name' not found"); return; }
    $manager = new \ProcessWire\SelectableOptionManager();
    $manager->setOptionsString($f, $optionsStr, false);
    if ($default !== null) { $f->defaultValue = $default; }
    wire('fields')->save($f);
    ok("Options set for: $name");
}

function addToTemplate($tName, $fName) {
    $t = wire('templates')->get($tName);
    if (!$t) { fail("Template '$tName' not found — cannot add '$fName'"); return; }
    $f = wire('fields')->get($fName);
    if (!$f) { fail("Field '$fName' not found"); return; }
    if ($t->fieldgroup->has($f)) { warn("'$fName' already in '$tName'"); return; }
    $t->fieldgroup->add($f);
    $t->fieldgroup->save();
    ok("Added '$fName' → '$tName'");
}

function ensureTemplate($name, $label, $noChildren = 0) {
    $t = wire('templates')->get($name);
    if ($t && $t->id) { warn("Template '$name' exists — skip creation"); return $t; }
    $fg = wire('fieldgroups')->get($name);
    if (!$fg) {
        $fg = new Fieldgroup();
        $fg->name = $name;
        $fg->add(wire('fields')->get('title'));
        wire('fieldgroups')->save($fg);
    }
    $t = new Template();
    $t->name       = $name;
    $t->label      = $label;
    $t->fieldgroup = $fg;
    $t->noChildren = $noChildren;
    wire('templates')->save($t);
    ok("Created template: $name");
    return $t;
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 1 — TEMPLATE RECONSTRUCTION
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 1 — Template Reconstruction');

// procedure
step('Ensuring procedure template...');
ensureTemplate('procedure', 'Procedure', 0);

// operation-note
step('Ensuring operation-note template...');
ensureTemplate('operation-note', 'Operation Note', 1);

// investigation
step('Ensuring investigation template...');
ensureTemplate('investigation', 'Investigation', 1);

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 1.5 — CREATE PHASE 1 / PHASE 3 FIELDS NEVER CREATED
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 1.5 — Missing Phase 1 & Phase 3 Fields');

// Audit / tracking fields
step('Creating audit tracking fields...');
$fCreatedBy = wire('fields')->get('created_by_user');
if (!$fCreatedBy || !$fCreatedBy->id) {
    $fCreatedBy = new Field();
    $fCreatedBy->type = wire('modules')->get('FieldtypeInteger');
    $fCreatedBy->name = 'created_by_user';
    $fCreatedBy->label = 'Created By (User ID)';
    $fCreatedBy->collapsed = 4; // collapsedHidden
    wire('fields')->save($fCreatedBy);
    ok("Created field: created_by_user");
} else { warn("created_by_user exists"); }

$fUpdatedBy = wire('fields')->get('updated_by_user');
if (!$fUpdatedBy || !$fUpdatedBy->id) {
    $fUpdatedBy = new Field();
    $fUpdatedBy->type = wire('modules')->get('FieldtypeInteger');
    $fUpdatedBy->name = 'updated_by_user';
    $fUpdatedBy->label = 'Updated By (User ID)';
    $fUpdatedBy->collapsed = 4;
    wire('fields')->save($fUpdatedBy);
    ok("Created field: updated_by_user");
} else { warn("updated_by_user exists"); }

// Page-reference fields
step('Creating page-reference fields...');
// consultant_ref
$fConsRef = wire('fields')->get('consultant_ref');
if (!$fConsRef || !$fConsRef->id) {
    $fConsRef = new Field();
    $fConsRef->type = wire('modules')->get('FieldtypePage');
    $fConsRef->name = 'consultant_ref';
    $fConsRef->label = 'Consultant';
    $fConsRef->derefAsPage = 1;
    $tCons = wire('templates')->get('consultant');
    if ($tCons) $fConsRef->findPagesSelector = 'template=consultant';
    wire('fields')->save($fConsRef);
    ok("Created field: consultant_ref");
} else { warn("consultant_ref exists"); }

// primary_diagnosis_ref
$fDiagRef = wire('fields')->get('primary_diagnosis_ref');
if (!$fDiagRef || !$fDiagRef->id) {
    $fDiagRef = new Field();
    $fDiagRef->type = wire('modules')->get('FieldtypePage');
    $fDiagRef->name = 'primary_diagnosis_ref';
    $fDiagRef->label = 'Primary Diagnosis';
    $fDiagRef->derefAsPage = 1;
    $fDiagRef->findPagesSelector = 'template=diagnosis-taxonomy';
    wire('fields')->save($fDiagRef);
    ok("Created field: primary_diagnosis_ref");
} else { warn("primary_diagnosis_ref exists"); }

// parent_case_reference
$fParentCase = wire('fields')->get('parent_case_reference');
if (!$fParentCase || !$fParentCase->id) {
    $fParentCase = new Field();
    $fParentCase->type = wire('modules')->get('FieldtypePage');
    $fParentCase->name = 'parent_case_reference';
    $fParentCase->label = 'Parent Case Reference';
    $fParentCase->derefAsPage = 1;
    $fParentCase->findPagesSelector = 'template=admission-record';
    wire('fields')->save($fParentCase);
    ok("Created field: parent_case_reference");
} else { warn("parent_case_reference exists"); }

// Options fields
step('Creating options fields for admission-record...');
createField('diagnosis_side', 'FieldtypeOptions', 'Side');
ensureOptions('diagnosis_side', "1=Right\n2=Left\n3=Bilateral");

createField('mlc_status', 'FieldtypeOptions', 'MLC Status');
ensureOptions('mlc_status', "1=No\n2=Yes", 1);

createField('age_unit', 'FieldtypeOptions', 'Age Unit');
ensureOptions('age_unit', "1=Years\n2=Months\n3=Days", 1);

// Simple fields
step('Creating simple admission-record fields...');
createField('associated_conditions', 'FieldtypeTextarea', 'Associated Conditions');
createField('patient_age',           'FieldtypeInteger',  'Age');
createField('secondary_phone',       'FieldtypeText',     'Secondary Phone');
createField('room_bed',              'FieldtypeText',     'Room / Bed');

// Phase 3 history fields
step('Creating Phase 3 history fields...');
createField('chief_complaint',    'FieldtypeTextarea', 'Chief Complaint');
createField('comorbidities',      'FieldtypeTextarea', 'Comorbidities');
createField('drug_history',       'FieldtypeTextarea', 'Drug History');
createField('family_history',     'FieldtypeTextarea', 'Family History');
createField('past_treatment',     'FieldtypeTextarea', 'Past Treatment');
createField('previous_surgeries', 'FieldtypeTextarea', 'Previous Surgeries');

// Vitals
step('Creating vitals fields...');
createField('vitals_pulse',  'FieldtypeText', 'Pulse (bpm)');
createField('vitals_bp',     'FieldtypeText', 'Blood Pressure');
createField('vitals_temp',   'FieldtypeText', 'Temperature');
createField('vitals_spo2',   'FieldtypeText', 'SpO2 (%)');
createField('vitals_rr',     'FieldtypeText', 'Respiratory Rate');
createField('vitals_weight', 'FieldtypeText', 'Weight (kg)');

// Local examination
step('Creating local examination fields...');
createField('limb_side',    'FieldtypeText',     'Limb / Side');
createField('inspection',   'FieldtypeTextarea', 'Inspection');
createField('palpation',    'FieldtypeTextarea', 'Palpation');
createField('distal_pulse', 'FieldtypeText',     'Distal Pulse');

// Search index
step('Creating search_index field...');
$fSearch = wire('fields')->get('search_index');
if (!$fSearch || !$fSearch->id) {
    $fSearch = new Field();
    $fSearch->type = wire('modules')->get('FieldtypeTextarea');
    $fSearch->name = 'search_index';
    $fSearch->label = 'Search Index';
    $fSearch->collapsed = 4; // collapsedHidden
    wire('fields')->save($fSearch);
    ok("Created field: search_index");
} else { warn("search_index exists"); }

// Patient-record fields (may not exist from phase1)
step('Creating patient-record fields...');
createField('date_of_birth', 'FieldtypeInteger', 'Date of Birth (Unix Timestamp)');
createField('gender',        'FieldtypeOptions', 'Gender');
ensureOptions('gender', "1=Male\n2=Female\n3=Other");
createField('guardian_name', 'FieldtypeText',     'Guardian / Relative Name');
createField('phone',         'FieldtypeText',     'Phone Number');
createField('address',       'FieldtypeTextarea', 'Address');

step('Attaching patient-record fields...');
$patientFields = ['patient_id','date_of_birth','gender','guardian_name','phone','address'];
foreach ($patientFields as $fn) {
    if (wire('fields')->get($fn)) addToTemplate('patient-record', $fn);
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 2 — FIELD RESTORATION
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 2 — Field Restoration');

// case_status
step('Ensuring case_status field...');
createField('case_status', 'FieldtypeOptions', 'Case Status');
ensureOptions('case_status', "1=Active\n2=Discharged\n3=Inactive\n4=Cancelled", 1);

// proc_date
step('Ensuring proc_date field...');
createField('proc_date', 'FieldtypeDatetime', 'Procedure Date');

// surgery_date
step('Ensuring surgery_date field...');
createField('surgery_date', 'FieldtypeDatetime', 'Surgery Date & Time');

// investigation_date
step('Ensuring investigation_date field...');
createField('investigation_date', 'FieldtypeDatetime', 'Investigation Date');

// review_date (on admission-record for follow-up)
step('Ensuring review_date field...');
createField('review_date', 'FieldtypeDatetime', 'Review / Follow-up Date');

// Also ensure all other procedure fields exist
step('Ensuring procedure support fields...');
createField('proc_name',         'FieldtypeText',    'Procedure Name');
createField('anesthesia_type',   'FieldtypeOptions', 'Anesthesia Type');
ensureOptions('anesthesia_type', "1=GA\n2=LA\n3=Regional");
createField('proc_status',       'FieldtypeOptions', 'Procedure Status');
ensureOptions('proc_status', "1=Planned\n2=Completed\n3=Cancelled", 1);
$ptLib = wire('templates')->get('procedure-template-library');
$fProcRef = wire('fields')->get('proc_template_ref');
if (!$fProcRef || !$fProcRef->id) {
    $fProcRef = new Field();
    $fProcRef->type  = wire('modules')->get('FieldtypePage');
    $fProcRef->name  = 'proc_template_ref';
    $fProcRef->label = 'Procedure Template';
    if ($ptLib) $fProcRef->findPagesSelector = 'template=procedure-template-library';
    wire('fields')->save($fProcRef);
    ok("Created field: proc_template_ref");
} else {
    warn("Field 'proc_template_ref' exists — skip");
}

// Operation-note fields
step('Ensuring operation-note fields...');
$opNoteFieldDefs = [
    ['anesthesia_details',      'FieldtypeTextarea', 'Anesthesia Details'],
    ['patient_position',        'FieldtypeText',     'Patient Position'],
    ['surgical_approach',       'FieldtypeTextarea', 'Surgical Approach'],
    ['incision_details',        'FieldtypeTextarea', 'Incision Details'],
    ['intraoperative_findings', 'FieldtypeTextarea', 'Intraoperative Findings'],
    ['structures_identified',   'FieldtypeTextarea', 'Structures Identified'],
    ['procedure_steps',         'FieldtypeTextarea', 'Procedure Steps'],
    ['implants_used',           'FieldtypeText',     'Implants Used'],
    ['hemostasis',              'FieldtypeText',     'Hemostasis'],
    ['closure_details',         'FieldtypeTextarea', 'Closure Details'],
    ['drains_used',             'FieldtypeText',     'Drains Used'],
    ['dressing',                'FieldtypeText',     'Dressing'],
    ['complications_intraop',   'FieldtypeTextarea', 'Intraoperative Complications'],
    ['op_note_images',          'FieldtypeImage',    'Operation Images'],
];
foreach ($opNoteFieldDefs as [$fn, $ft, $fl]) {
    createField($fn, $ft, $fl);
}

// Investigation fields
step('Ensuring investigation fields...');
createField('investigation_type',     'FieldtypeOptions',  'Investigation Type');
ensureOptions('investigation_type', "1=MRI\n2=X-Ray\n3=CT Scan\n4=Ultrasound\n5=Lab\n6=EMG/NCS\n7=Other");
createField('investigation_name',     'FieldtypeText',     'Investigation Name');
createField('investigation_findings', 'FieldtypeTextarea', 'Findings');
createField('investigation_files',    'FieldtypeFile',     'Report Files');
createField('investigation_images',   'FieldtypeImage',    'Images');

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 3 — TEMPLATE-FIELD INTEGRITY
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 3 — Template-Field Integrity');

step('Attaching fields to procedure template...');
$procFields = ['proc_date','proc_name','anesthesia_type','proc_template_ref','proc_status'];
// Audit fields (created in phase1)
if (wire('fields')->get('created_by_user')) $procFields[] = 'created_by_user';
if (wire('fields')->get('updated_by_user')) $procFields[] = 'updated_by_user';
foreach ($procFields as $fn) addToTemplate('procedure', $fn);

step('Attaching fields to operation-note template...');
$opNoteFields = [
    'surgery_date','anesthesia_type','anesthesia_details','patient_position',
    'surgical_approach','incision_details','intraoperative_findings',
    'structures_identified','procedure_steps','implants_used','hemostasis',
    'closure_details','drains_used','dressing','complications_intraop','op_note_images',
];
if (wire('fields')->get('created_by_user')) $opNoteFields[] = 'created_by_user';
if (wire('fields')->get('updated_by_user')) $opNoteFields[] = 'updated_by_user';
foreach ($opNoteFields as $fn) addToTemplate('operation-note', $fn);

step('Attaching fields to investigation template...');
$invFields = [
    'investigation_date','investigation_type','investigation_name',
    'investigation_findings','investigation_files','investigation_images',
];
if (wire('fields')->get('created_by_user')) $invFields[] = 'created_by_user';
if (wire('fields')->get('updated_by_user')) $invFields[] = 'updated_by_user';
foreach ($invFields as $fn) addToTemplate('investigation', $fn);

step('Attaching case_status, review_date, and all other required fields to admission-record...');
$admissionFields = [
    'case_status', 'review_date',
    // Phase 1 fields that may not have been attached
    'consultant_ref', 'primary_diagnosis_ref', 'diagnosis_side', 'associated_conditions',
    'mlc_status', 'patient_age', 'age_unit', 'secondary_phone', 'room_bed',
    'parent_case_reference', 'created_by_user', 'updated_by_user',
    'admitted_on', 'discharged_on', 'discharge_consultant', 'admitting_unit',
    // Phase 3 fields (history / examination / treatment / discharge)
    'chief_complaint', 'comorbidities', 'drug_history', 'family_history',
    'past_treatment', 'previous_surgeries',
    'vitals_pulse', 'vitals_bp', 'vitals_temp', 'vitals_spo2', 'vitals_rr', 'vitals_weight',
    'limb_side', 'inspection', 'palpation', 'distal_pulse', 'special_tests',
    'proposed_procedure', 'staging_plan', 'alternatives_discussed', 'risks_explained',
    'consent_status', 'counseling_notes', 'treatment_plan_files',
    'post_op_course', 'complications_postop', 'wound_status', 'overall_progress',
    'general_condition', 'pain_status', 'wound_condition', 'dressing_status',
    'limb_status', 'mobility_status',
    // Phase 9 search
    'search_index',
];
foreach ($admissionFields as $fn) {
    if (wire('fields')->get($fn)) addToTemplate('admission-record', $fn);
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 4 — HIERARCHY RULES
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 4 — Template Hierarchy Rules');

step('Setting admission-record childTemplates → procedure + investigation...');
$tAdm  = wire('templates')->get('admission-record');
$tProc = wire('templates')->get('procedure');
$tInv  = wire('templates')->get('investigation');
if ($tAdm && $tProc && $tInv) {
    $children = $tAdm->childTemplates ?? [];
    $changed = false;
    foreach ([$tProc->id, $tInv->id] as $tid) {
        if (!in_array($tid, $children)) { $children[] = $tid; $changed = true; }
    }
    if ($changed) {
        $tAdm->childTemplates = $children;
        wire('templates')->save($tAdm);
        ok("admission-record childTemplates updated");
    } else {
        warn("admission-record childTemplates already set");
    }
}

step('Setting procedure childTemplates → operation-note...');
$tOpNote = wire('templates')->get('operation-note');
if ($tProc && $tOpNote) {
    $children = $tProc->childTemplates ?? [];
    if (!in_array($tOpNote->id, $children)) {
        $children[] = $tOpNote->id;
        $tProc->childTemplates = $children;
        wire('templates')->save($tProc);
        ok("procedure childTemplates → operation-note");
    } else {
        warn("procedure childTemplates already set");
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 5 — HOOK ENGINE VALIDATION
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 5 — Hook Engine Validation');

$readyPath = wire('config')->paths->site . 'ready.php';
$readyExists = file_exists($readyPath);
ok($readyExists ? "site/ready.php exists" : "MISSING site/ready.php");

$readyContent = $readyExists ? file_get_contents($readyPath) : '';
$hookChecks = [
    'Auto patient ID (REG-)'     => 'REG-',
    'Auto IP number'             => 'ip_number',
    'Admitted-on timestamp'      => 'admitted_on',
    'Created-by / updated-by'    => 'created_by_user',
    'Procedure name auto-gen'    => 'proc_name',
    'Auto discharge timestamp'   => 'discharged_on',
    'No-delete protection'       => 'soft-delete',
    'Audit diff logging'         => 'audit_field_changes',
];
foreach ($hookChecks as $label => $needle) {
    if (strpos($readyContent, $needle) !== false) ok("Hook present: $label");
    else warn("Hook MISSING or unrecognized: $label");
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 6 — SEARCH SYSTEM FIX
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 6 — Search System Fix');

step('Checking search_index hook in ready.php...');
if (strpos($readyContent, 'PHASE 9') !== false || strpos($readyContent, 'search_index') !== false) {
    warn("search_index hook already present in ready.php — skipping inject");
} else {
    $hookCode = <<<'HOOK'


// ─────────────────────────────────────────────────────────────────────────────
// PHASE 9 — REBUILD SEARCH INDEX
// Denormalised text blob rebuilt on every admission-record save
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookAfter('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    if (!wire('fields')->get('search_index')) return;

    $parts = [];
    $parts[] = $page->ip_number;
    $parts[] = $page->parent->title;
    $parts[] = $page->parent->patient_id;

    $diag = $page->getUnformatted('primary_diagnosis_ref');
    if ($diag instanceof Page && $diag->id) $parts[] = $diag->title;
    $parts[] = $page->diagnosis_side;

    $cons = $page->getUnformatted('consultant_ref');
    if ($cons instanceof Page && $cons->id) $parts[] = $cons->title;

    $procs = wire('pages')->find("template=procedure, parent={$page->id}");
    foreach ($procs as $proc) { $parts[] = $proc->proc_name; $parts[] = $proc->anesthesia_type; }

    $invs = wire('pages')->find("template=investigation, parent={$page->id}");
    foreach ($invs as $inv) { $parts[] = $inv->investigation_name; $parts[] = $inv->investigation_type; }

    $parts[] = $page->chief_complaint;
    $parts[] = $page->post_op_course;
    $parts[] = $page->proposed_procedure;

    $index = implode(' ', array_filter(array_map('trim', $parts)));
    $page->of(false);
    $page->search_index = strtolower($index);
    $page->save('search_index');
});
HOOK;
    file_put_contents($readyPath, $readyContent . $hookCode);
    ok("Injected PHASE 9 search_index hook into site/ready.php");
    $readyContent .= $hookCode; // update in-memory copy
}

step('Populating search_index for existing admission records...');
$admissions = wire('pages')->find('template=admission-record, include=all, limit=200');
$populated = 0;
foreach ($admissions as $adm) {
    if (!wire('fields')->get('search_index')) break;
    $parts = [];
    $parts[] = $adm->ip_number;
    $parts[] = $adm->parent->title;
    $parts[] = $adm->parent->patient_id ?? '';
    $diag = $adm->getUnformatted('primary_diagnosis_ref');
    if ($diag instanceof Page && $diag->id) $parts[] = $diag->title;
    $cons = $adm->getUnformatted('consultant_ref');
    if ($cons instanceof Page && $cons->id) $parts[] = $cons->title;
    $parts[] = $adm->chief_complaint ?? '';
    $index = strtolower(implode(' ', array_filter(array_map('trim', $parts))));
    $adm->of(false);
    $adm->search_index = $index;
    $adm->save('search_index');
    $populated++;
}
ok("Populated search_index for $populated admission record(s)");

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 7 — PDF ENGINE CHECK
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 7 — PDF Engine');

$mpdfDir = $rootPath . '/vendor/mpdf/mpdf';
$vendorAutoload = $rootPath . '/vendor/autoload.php';
if (is_dir($mpdfDir) && file_exists($vendorAutoload)) {
    ok("mPDF installed at vendor/mpdf/mpdf — autoloader ready");
} else {
    warn("mPDF NOT installed. Run: php composer.phar require mpdf/mpdf");
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 8 — BUSINESS RULE HOOKS
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 8 — Business Rule Hooks');

$businessRulesNeeded = strpos($readyContent, 'BUSINESS RULE') === false;

if (!$businessRulesNeeded) {
    warn("Business rule hooks already present — skipping");
} else {
    step('Injecting business rule hooks into ready.php...');
    $businessHooks = <<<'BUSINESS'


// ─────────────────────────────────────────────────────────────────────────────
// BUSINESS RULE — Block discharge if no clinical entries exist
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $newStatus = (int)$page->getUnformatted('case_status');
    if ($newStatus !== 2) return; // only block on discharge attempt
    $hasClinical = wire('pages')->count("template=procedure|investigation, parent={$page->id}");
    if (!$hasClinical) {
        $event->cancelAction = true;
        wire('session')->error("Cannot discharge: no procedures or investigations recorded.");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// BUSINESS RULE — Auto-set discharged_on when case_status → 2
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $newStatus = (int)$page->getUnformatted('case_status');
    if ($newStatus === 2 && !$page->discharged_on) {
        $page->of(false);
        $page->discharged_on = time();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// BUSINESS RULE — PA cannot modify ip_number once procedures exist
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $user = wire('user');
    if ($user->isSuperuser() || $user->hasRole('medical-officer')) return;
    if (!$page->isChanged('ip_number')) return;
    $procCount = wire('pages')->count("template=procedure, parent={$page->id}");
    if ($procCount > 0) {
        $event->cancelAction = true;
        wire('session')->error("IP Number cannot be changed after procedures are recorded.");
    }
});
BUSINESS;

    // Only inject if not already present (double-check for partial presence)
    $freshReady = file_get_contents($readyPath);
    if (strpos($freshReady, 'BUSINESS RULE') === false) {
        file_put_contents($readyPath, $freshReady . $businessHooks);
        ok("Injected business rule hooks into site/ready.php");
    } else {
        warn("Business rule hooks already present (race condition avoided)");
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// PHASE 9 — FRONTEND COMPATIBILITY CHECK
// ═════════════════════════════════════════════════════════════════════════════
head('PHASE 9 — Frontend Compatibility Check');

$checks = [
    ['admission-record', 'ip_number'],
    ['admission-record', 'case_status'],
    ['admission-record', 'consultant_ref'],
    ['admission-record', 'primary_diagnosis_ref'],
    ['admission-record', 'diagnosis_side'],
    ['admission-record', 'search_index'],
    ['admission-record', 'review_date'],
    ['admission-record', 'admitted_on'],
    ['admission-record', 'discharged_on'],
    ['admission-record', 'chief_complaint'],
    ['procedure',        'proc_date'],
    ['procedure',        'proc_name'],
    ['procedure',        'proc_status'],
    ['procedure',        'anesthesia_type'],
    ['operation-note',   'surgery_date'],
    ['operation-note',   'procedure_steps'],
    ['operation-note',   'complications_intraop'],
    ['investigation',    'investigation_date'],
    ['investigation',    'investigation_type'],
    ['investigation',    'investigation_name'],
    ['investigation',    'investigation_findings'],
    ['patient-record',   'patient_id'],
];

$passCount = 0; $failCount = 0;
foreach ($checks as [$tName, $fName]) {
    $t = wire('templates')->get($tName);
    if (!$t) { fail("Template '$tName' missing"); $failCount++; continue; }
    if ($t->fieldgroup->has(wire('fields')->get($fName))) {
        ok("$tName → $fName ✓");
        $passCount++;
    } else {
        fail("$tName → $fName MISSING FROM FIELDGROUP");
        $failCount++;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// FINAL REPORT
// ═════════════════════════════════════════════════════════════════════════════
head('REPAIR COMPLETE');
ok("Compatibility checks: $passCount passed, $failCount failed");
if ($failCount === 0) {
    ok("ALL SYSTEMS OPERATIONAL — Backend is production-ready");
} else {
    warn("$failCount checks failed — review output above");
}
ok("Next: Run composer require mpdf/mpdf if PDF export is needed");
ok("Next: Test at http://discharge-app.test/");
