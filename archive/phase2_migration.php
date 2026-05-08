<?php
/**
 * Phase 2 Migration Script — Procedure System Rebuild
 * Clinical Surgical Registry — Phase 2 Upgrade
 *
 * Run AFTER phase1_migration.php
 * Run via browser: http://discharge-app.test/phase2_migration.php
 *
 * WHAT THIS DOES:
 * 1. Creates procedure template with all fields
 * 2. Creates operation-note template (linked 1:1 to procedure)
 * 3. Migrates any existing repeater_procedures data to child pages
 * 4. Removes repeater field dependency from admission-record
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

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
    ok("Created field: $name ($type)");
    return $f;
}

function addFieldToTemplate($tName, $fName, $afterField = null) {
    $t = wire('templates')->get($tName);
    if (!$t) { fail("Template '$tName' not found"); return; }
    $f = wire('fields')->get($fName);
    if (!$f) { fail("Field '$fName' not found"); return; }
    if ($t->fieldgroup->has($f)) { warn("'$fName' already in '$tName'"); return; }
    if ($afterField) {
        $after = wire('fields')->get($afterField);
        if ($after) $t->fieldgroup->insertAfter($f, $after);
        else $t->fieldgroup->add($f);
    } else {
        $t->fieldgroup->add($f);
    }
    $t->fieldgroup->save();
    ok("Added '$fName' to template '$tName'");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 2 Migration — Procedure System Rebuild</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>🔧 Phase 2 Migration — Procedure System Rebuild</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

// ─── SECTION 1: Create Procedure Template + Fields ────────────────────────────
echo '<h2>SECTION 1 — Procedure Template</h2>';

step('Creating procedure template...');
$tProc = wire('templates')->get('procedure');
if (!$tProc || !$tProc->id) {
    $fg = wire('fieldgroups')->get('procedure');
    if (!$fg) {
        $fg = new Fieldgroup();
        $fg->name = 'procedure';
        $fg->add(wire('fields')->get('title'));
        wire('fieldgroups')->save($fg);
    }
    $tProc = new Template();
    $tProc->name      = 'procedure';
    $tProc->label     = 'Procedure';
    $tProc->fieldgroup = $fg;
    $tProc->noChildren = 0; // operation-note will be child
    wire('templates')->save($tProc);
    ok("Created template: procedure");
} else {
    warn("Template 'procedure' already exists — skipping creation");
}

step('Creating procedure fields...');

// Procedure summary fields (Section 3 of blueprint)
$fProcDate     = createField('proc_date',         'FieldtypeDatetime', 'Procedure Date');
$fProcName     = createField('proc_name',         'FieldtypeText',     'Procedure Name');
$fProcTemplate = createField('proc_template_ref', 'FieldtypePage',     'Procedure Template');
$fAnesthesia   = createField('anesthesia_type',   'FieldtypeOptions',  'Anesthesia Type');

$fAnesthesiaField = wire('fields')->get('anesthesia_type');
if ($fAnesthesiaField) {
    $fAnesthesiaField->type->setOptionsString($fAnesthesiaField, "1=GA\n2=LA\n3=Regional");
    wire('fields')->save($fAnesthesiaField);
    ok("Set anesthesia_type options: GA / LA / Regional");
}

$fProcTemplateField = wire('fields')->get('proc_template_ref');
if ($fProcTemplateField) {
    $fProcTemplateField->findPagesSelector = 'template=procedure-template-library';
    wire('fields')->save($fProcTemplateField);
    ok("Configured proc_template_ref → procedure-template-library");
}

$fProcStatus = createField('proc_status', 'FieldtypeOptions', 'Procedure Status');
$fProcStatusField = wire('fields')->get('proc_status');
if ($fProcStatusField) {
    $fProcStatusField->type->setOptionsString($fProcStatusField, "1=Planned\n2=Completed\n3=Cancelled");
    $fProcStatusField->defaultValue = 1;
    wire('fields')->save($fProcStatusField);
    ok("Set proc_status options: Planned/Completed/Cancelled");
}

// Audit fields already created in Phase 1 — just reference them
step('Attaching fields to procedure template...');
$procFields = [
    'proc_date', 'proc_name', 'anesthesia_type', 'proc_template_ref',
    'proc_status', 'created_by_user', 'updated_by_user',
];
foreach ($procFields as $fn) {
    addFieldToTemplate('procedure', $fn);
}

// ─── SECTION 2: Create Operation Note Template + Fields ───────────────────────
echo '<h2>SECTION 2 — Operation Note Template</h2>';

step('Creating operation-note template...');
$tOpNote = wire('templates')->get('operation-note');
if (!$tOpNote || !$tOpNote->id) {
    $fg2 = wire('fieldgroups')->get('operation-note');
    if (!$fg2) {
        $fg2 = new Fieldgroup();
        $fg2->name = 'operation-note';
        $fg2->add(wire('fields')->get('title'));
        wire('fieldgroups')->save($fg2);
    }
    $tOpNote = new Template();
    $tOpNote->name      = 'operation-note';
    $tOpNote->label     = 'Operation Note';
    $tOpNote->fieldgroup = $fg2;
    $tOpNote->noChildren = 1; // leaf node
    wire('templates')->save($tOpNote);
    ok("Created template: operation-note");
} else {
    warn("Template 'operation-note' already exists — skipping creation");
}

step('Creating operation note fields (Section 8 of blueprint)...');

// Section 8 fields
$fSurgDate         = createField('surgery_date',             'FieldtypeDatetime',  'Surgery Date & Time');
$fAnesthDetails    = createField('anesthesia_details',       'FieldtypeTextarea',  'Anesthesia Details');
$fPatientPosition  = createField('patient_position',         'FieldtypeText',      'Patient Position');
$fSurgApproach     = createField('surgical_approach',        'FieldtypeTextarea',  'Surgical Approach');
$fIncisionDetails  = createField('incision_details',         'FieldtypeTextarea',  'Incision Details');
$fIntraopFindings  = createField('intraoperative_findings',  'FieldtypeTextarea',  'Intraoperative Findings');
$fStructures       = createField('structures_identified',    'FieldtypeTextarea',  'Structures Identified');
$fProcedureSteps   = createField('procedure_steps',          'FieldtypeTextarea',  'Procedure Steps');
$fImplantsUsed     = createField('implants_used',            'FieldtypeText',      'Implants Used');
$fHemostasis       = createField('hemostasis',               'FieldtypeText',      'Hemostasis');
$fClosureDetails   = createField('closure_details',          'FieldtypeTextarea',  'Closure Details');
$fDrainsUsed       = createField('drains_used',              'FieldtypeText',      'Drains Used');
$fDressing         = createField('dressing',                 'FieldtypeText',      'Dressing');
$fComplicationsIO  = createField('complications_intraop',    'FieldtypeTextarea',  'Intraoperative Complications');
$fOpNoteImages     = createField('op_note_images',           'FieldtypeImage',     'Operation Images');

step('Attaching fields to operation-note template...');
$opNoteFields = [
    'surgery_date', 'anesthesia_type', 'anesthesia_details', 'patient_position',
    'surgical_approach', 'incision_details', 'intraoperative_findings',
    'structures_identified', 'procedure_steps', 'implants_used', 'hemostasis',
    'closure_details', 'drains_used', 'dressing', 'complications_intraop',
    'op_note_images', 'created_by_user', 'updated_by_user',
];
foreach ($opNoteFields as $fn) {
    addFieldToTemplate('operation-note', $fn);
}

// ─── SECTION 3: Set Template Hierarchy Rules ──────────────────────────────────
echo '<h2>SECTION 3 — Template Hierarchy Rules</h2>';

step('Configuring admission-record to allow procedure children...');
$tAdm = wire('templates')->get('admission-record');
if ($tAdm) {
    // admission-record can have procedure as child
    $tAdm->childTemplates = [wire('templates')->get('procedure')->id];
    wire('templates')->save($tAdm);
    ok("admission-record → children: procedure");
}

step('Configuring procedure to allow operation-note children...');
$tProc = wire('templates')->get('procedure');
if ($tProc) {
    $tProc->childTemplates = [wire('templates')->get('operation-note')->id];
    wire('templates')->save($tProc);
    ok("procedure → children: operation-note");
}

step('Configuring patient-record to allow admission-record children...');
$tPat = wire('templates')->get('patient-record');
if ($tPat) {
    $tPat->childTemplates = [wire('templates')->get('admission-record')->id];
    wire('templates')->save($tPat);
    ok("patient-record → children: admission-record");
}

// ─── SECTION 4: Migrate Existing Repeater Data → Child Pages ─────────────────
echo '<h2>SECTION 4 — Data Migration: Repeater → Child Pages</h2>';

step('Finding all admission-records with repeater_procedures data...');
$admissions = wire('pages')->find("template=admission-record, limit=9999");
$migrated   = 0;
$skipped    = 0;

foreach ($admissions as $adm) {
    if (!$adm->procedures || !count($adm->procedures)) {
        $skipped++;
        continue;
    }

    foreach ($adm->procedures as $repeaterRow) {
        // Check if already migrated (child procedure page with same date exists)
        $procDate = $repeaterRow->procedure_date;
        $procName = $repeaterRow->procedure_name;
        $opNotes  = $repeaterRow->operation_notes;

        if (!$procName) continue;

        // Check if procedure child page already exists for this case
        $existingCheck = wire('pages')->get("template=procedure, parent={$adm->id}, proc_name=" . wire('sanitizer')->selectorValue($procName));
        if ($existingCheck && $existingCheck->id) {
            warn("Procedure '{$procName}' already migrated for case {$adm->ip_number} — skipping");
            continue;
        }

        // Create procedure child page
        $procPage = new Page();
        $procPage->template = wire('templates')->get('procedure');
        $procPage->parent   = $adm;
        $procPage->title    = $procName;
        $procPage->proc_name = $procName;
        $procPage->proc_date = $procDate;
        $procPage->proc_status = 2; // Completed (historical data)
        $procPage->save();

        ok("Migrated procedure: '{$procName}' → child of {$adm->ip_number}");

        // Create operation-note child page if notes exist
        if ($opNotes) {
            $notePage = new Page();
            $notePage->template          = wire('templates')->get('operation-note');
            $notePage->parent            = $procPage;
            $notePage->title             = 'Operation Note — ' . $procName;
            $notePage->surgery_date      = $procDate;
            $notePage->procedure_steps   = $opNotes;
            $notePage->save();

            ok("  └─ Created operation note for: '{$procName}'");
        }

        $migrated++;
    }
}

ok("Migration complete: $migrated procedures migrated, $skipped admissions had no procedures.");

// ─── SECTION 5: Final Summary ─────────────────────────────────────────────────
echo '<h2>✅ PHASE 2 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">What was done:</h3>';
echo '<ul style="color:#94a3b8;">';
echo '<li>Created template: <strong>procedure</strong> with fields: proc_date, proc_name, anesthesia_type, proc_template_ref, proc_status</li>';
echo '<li>Created template: <strong>operation-note</strong> with all Section 8 fields (surgery_date, surgical_approach, incision_details, etc.)</li>';
echo '<li>Set hierarchy: patient-record → admission-record → procedure → operation-note</li>';
echo "<li>Migrated <strong>$migrated</strong> existing repeater procedures to child page entities</li>";
echo '</ul>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase3_migration.php to add structured movement + clinical fields</p>';
echo '</div>';
?>
</body>
</html>
