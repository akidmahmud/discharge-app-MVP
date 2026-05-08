<?php namespace ProcessWire;

/**
 * Phase 15 Migration - Surgery Plan Fields on Procedure
 *
 * WHAT THIS DOES:
 * 1. Creates procedure-level surgery plan fields
 * 2. Attaches them to the procedure template
 * 3. Creates anesthesiologist_name for operation-note if missing
 * 4. Leaves legacy treatment_decision in place as backup / optional notes
 *
 * Run via browser: http://discharge-app.test/phase15_migration.php
 * Requires superuser login.
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

function step15($msg) {
    echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>&#9881; $msg</div>\n";
    flush();
}
function ok15($msg) {
    echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10003; $msg</div>\n";
    flush();
}
function warn15($msg) {
    echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9888; $msg</div>\n";
    flush();
}
function fail15($msg) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10007; $msg</div>\n";
    flush();
}

function createField15($name, $type, $label, $cfg = []) {
    $field = wire('fields')->get($name);
    if ($field && $field->id) {
        warn15("Field '$name' already exists - skipping");
        return $field;
    }
    $field = new Field();
    $field->type = wire('modules')->get($type);
    $field->name = $name;
    $field->label = $label;
    foreach ($cfg as $key => $value) {
        $field->$key = $value;
    }
    wire('fields')->save($field);
    ok15("Created field: $name ($type)");
    return $field;
}

function attachField15($fieldName, $templateName) {
    $template = wire('templates')->get($templateName);
    $field = wire('fields')->get($fieldName);
    if (!$template || !$template->id || !$field || !$field->id) {
        fail15("Cannot attach '$fieldName' to '$templateName' - missing field or template");
        return;
    }
    if ($template->fieldgroup->has($field)) {
        warn15("'$fieldName' already attached to '$templateName'");
        return;
    }
    $template->fieldgroup->add($field);
    $template->fieldgroup->save();
    ok15("Attached '$fieldName' -> '$templateName'");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 15 - Surgery Plan Fields</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; max-width:900px; }
h1   { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2   { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>&#127973; Phase 15 Migration - Surgery Plan Fields</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?> &nbsp;|&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<?php
echo '<h2>SECTION 1 - Create Fields</h2>';
createField15('proc_time', 'FieldtypeText', 'Scheduled Surgery Time');

step15('Creating c_arm_required Options field...');
$cArmField = wire('fields')->get('c_arm_required');
if (!$cArmField || !$cArmField->id) {
    $cArmField = new Field();
    $cArmField->type = wire('modules')->get('FieldtypeOptions');
    $cArmField->name = 'c_arm_required';
    $cArmField->label = 'C-Arm Required';
    wire('fields')->save($cArmField);
    (new SelectableOptionManager())->setOptionsString($cArmField, "1=Yes\n2=No", false);
    wire('fields')->save($cArmField);
    ok15('Created c_arm_required options field');
} else {
    warn15('c_arm_required already exists - skipping');
}

step15('Creating microscope_required Options field...');
$microscopeField = wire('fields')->get('microscope_required');
if (!$microscopeField || !$microscopeField->id) {
    $microscopeField = new Field();
    $microscopeField->type = wire('modules')->get('FieldtypeOptions');
    $microscopeField->name = 'microscope_required';
    $microscopeField->label = 'Microscope Required';
    wire('fields')->save($microscopeField);
    (new SelectableOptionManager())->setOptionsString($microscopeField, "1=Yes\n2=No", false);
    wire('fields')->save($microscopeField);
    ok15('Created microscope_required options field');
} else {
    warn15('microscope_required already exists - skipping');
}

createField15('implant_details', 'FieldtypeText', 'Implant Details');
createField15('anesthesiologist_name', 'FieldtypeText', 'Anesthesiologist Name');

echo '<h2>SECTION 2 - Attach To Templates</h2>';
foreach (['proc_time', 'c_arm_required', 'microscope_required', 'implant_details', 'anesthesiologist_name'] as $fieldName) {
    attachField15($fieldName, 'procedure');
}

// Used for Operation Note prefills / edits as well.
attachField15('anesthesiologist_name', 'operation-note');

echo '<h2>SECTION 3 - Summary</h2>';
ok15('Procedure template now carries the per-procedure Surgery Plan fields.');
ok15('Operation Note template can store anesthesiologist_name for prefill/editing.');
warn15('Legacy treatment_decision field was left in place and not deleted.');
?>
</body>
</html>
