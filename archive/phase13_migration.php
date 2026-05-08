<?php namespace ProcessWire;

/**
 * Phase 13 Migration - Comorbidity Toggle Buttons + Structured Drug Rows
 *
 * WHAT THIS DOES:
 * 1. Creates comorbidity_none checkbox and comorbidity_flags multi-select fields
 * 2. Creates comorb_condition_flag, drug_name, drug_dose fields
 * 3. Creates comorbidity-drug child template
 * 4. Allows comorbidity-drug under admission-record
 * 5. Migrates legacy comorbidities JSON into flags + child pages
 *
 * Run via browser: http://discharge-app.test/phase13_migration.php
 * Requires superuser login.
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

function step13($msg) {
    echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>&#9881; $msg</div>\n";
    flush();
}
function ok13($msg) {
    echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10003; $msg</div>\n";
    flush();
}
function warn13($msg) {
    echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9888; $msg</div>\n";
    flush();
}
function fail13($msg) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10007; $msg</div>\n";
    flush();
}
function info13($msg) {
    echo "<div style='background:#0c1a2e;color:#93c5fd;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9432; $msg</div>\n";
    flush();
}

function createField13($name, $type, $label, $cfg = []) {
    $field = wire('fields')->get($name);
    if ($field && $field->id) {
        warn13("Field '$name' already exists - skipping");
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
    ok13("Created field: $name ($type)");
    return $field;
}

function attachField13($fieldName, $templateName) {
    $template = wire('templates')->get($templateName);
    $field = wire('fields')->get($fieldName);
    if (!$template || !$template->id || !$field || !$field->id) {
        fail13("Cannot attach '$fieldName' to '$templateName' - missing field or template");
        return;
    }
    if ($template->fieldgroup->has($field)) {
        warn13("'$fieldName' already attached to '$templateName'");
        return;
    }
    $template->fieldgroup->add($field);
    $template->fieldgroup->save();
    ok13("Attached '$fieldName' -> '$templateName'");
}

function normalizeComorbidityFlag13($condition) {
    $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) $condition)));
    $map = [
        'dm' => 'DM',
        'diabetes' => 'DM',
        'diabetes mellitus' => 'DM',
        'diabetic mellitus' => 'DM',
        't2dm' => 'DM',
        'type 2 diabetes' => 'DM',
        'type ii diabetes' => 'DM',
        'type 2 dm' => 'DM',
        'htn' => 'HTN',
        'hypertension' => 'HTN',
        'high blood pressure' => 'HTN',
        'ckd' => 'CKD',
        'chronic kidney disease' => 'CKD',
        'chronic renal disease' => 'CKD',
        'chronic renal failure' => 'CKD',
        'asthma' => 'Asthma',
        'bronchial asthma' => 'Asthma',
        'ihd' => 'IHD',
        'ischemic heart disease' => 'IHD',
        'ischaemic heart disease' => 'IHD',
        'coronary artery disease' => 'IHD',
        'cad' => 'IHD',
    ];
    return $map[$normalized] ?? '';
}

function splitTreatment13($treatment) {
    $treatment = trim((string) $treatment);
    if ($treatment === '') {
        return ['', ''];
    }
    if (preg_match('/^(.+?)(\s+(?:\d+(?:\.\d+)?\s*(?:mg|mcg|g|ml|iu|units?)\b.*))$/i', $treatment, $matches)) {
        return [trim($matches[1]), trim($matches[2])];
    }
    if (preg_match('/^(.+?)(\s+(?:od|bd|tid|qid|hs|stat|prn)\b.*)$/i', $treatment, $matches)) {
        return [trim($matches[1]), trim($matches[2])];
    }
    return [$treatment, ''];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 13 - Comorbidity Restructure</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; max-width:900px; }
h1   { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2   { color:#7dd3fc; margin-top:30px; }
pre  { background:#0c1a2e; border:1px solid #1e3a5f; padding:14px; border-radius:6px; color:#86efac; font-size:12px; overflow-x:auto; }
</style>
</head>
<body>
<h1>&#127973; Phase 13 Migration - Comorbidity Restructure</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?> &nbsp;|&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<?php
echo '<h2>SECTION 1 - Fields</h2>';

createField13('comorbidity_none', 'FieldtypeCheckbox', 'No relevant comorbidities');

step13('Creating comorbidity_flags Options field...');
$comorbidityFlagsField = wire('fields')->get('comorbidity_flags');
if (!$comorbidityFlagsField || !$comorbidityFlagsField->id) {
    $comorbidityFlagsField = new Field();
    $comorbidityFlagsField->type = wire('modules')->get('FieldtypeOptions');
    $comorbidityFlagsField->name = 'comorbidity_flags';
    $comorbidityFlagsField->label = 'Comorbidity Flags';
    wire('fields')->save($comorbidityFlagsField);

    $optionManager = new SelectableOptionManager();
    $optionManager->setOptionsString($comorbidityFlagsField, "1=DM\n2=HTN\n3=CKD\n4=Asthma\n5=IHD\n6=Custom", false);
    wire('fields')->save($comorbidityFlagsField);
    ok13('Created comorbidity_flags with options: DM, HTN, CKD, Asthma, IHD, Custom');
} else {
    warn13('comorbidity_flags already exists - skipping');
}

createField13('comorb_condition_flag', 'FieldtypeText', 'Comorbidity Condition');
createField13('drug_name', 'FieldtypeText', 'Drug Name');
createField13('drug_dose', 'FieldtypeText', 'Drug Dose');

echo '<h2>SECTION 2 - Attach Fields To Templates</h2>';
attachField13('comorbidity_none', 'admission-record');
attachField13('comorbidity_flags', 'admission-record');

echo '<h2>SECTION 3 - Create Template</h2>';
$template = wire('templates')->get('comorbidity-drug');
if (!$template || !$template->id) {
    $fieldgroup = new Fieldgroup();
    $fieldgroup->name = 'comorbidity-drug';
    $fieldgroup->add(wire('fields')->get('title'));
    $fieldgroup->save();

    $template = new Template();
    $template->name = 'comorbidity-drug';
    $template->label = 'Comorbidity Drug';
    $template->fieldgroup = $fieldgroup;
    $template->noChildren = 1;
    $template->tags = 'clinical';
    wire('templates')->save($template);
    ok13('Created template: comorbidity-drug');
} else {
    warn13('Template comorbidity-drug already exists - skipping');
}

attachField13('comorb_condition_flag', 'comorbidity-drug');
attachField13('drug_name', 'comorbidity-drug');
attachField13('drug_dose', 'comorbidity-drug');
if (wire('fields')->get('created_by_user')) {
    attachField13('created_by_user', 'comorbidity-drug');
}

echo '<h2>SECTION 4 - Template Relationships</h2>';
$admissionTemplate = wire('templates')->get('admission-record');
$drugTemplate = wire('templates')->get('comorbidity-drug');
if ($admissionTemplate && $admissionTemplate->id && $drugTemplate && $drugTemplate->id) {
    $childTemplates = $admissionTemplate->childTemplates ?: [];
    if (!in_array($drugTemplate->id, $childTemplates)) {
        $childTemplates[] = $drugTemplate->id;
        $admissionTemplate->childTemplates = $childTemplates;
        wire('templates')->save($admissionTemplate);
        ok13('Added comorbidity-drug as allowed child of admission-record');
    } else {
        warn13('comorbidity-drug already allowed under admission-record');
    }

    $parentTemplates = $drugTemplate->parentTemplates ?: [];
    if (!in_array($admissionTemplate->id, $parentTemplates)) {
        $parentTemplates[] = $admissionTemplate->id;
        $drugTemplate->parentTemplates = $parentTemplates;
        wire('templates')->save($drugTemplate);
        ok13('Set admission-record as allowed parent of comorbidity-drug');
    } else {
        warn13('admission-record already allowed as parent of comorbidity-drug');
    }
} else {
    fail13('Could not configure template relationships');
}

echo '<h2>SECTION 5 - Data Migration</h2>';
$admissions = wire('pages')->find('template=admission-record, limit=1000');
$migratedCases = 0;
$migratedDrugs = 0;
$skippedCases = 0;
$noneCases = 0;

foreach ($admissions as $admission) {
    $legacyText = trim((string) $admission->comorbidities);
    if ($legacyText === '') {
        if (wire('fields')->get('comorbidity_none') && !wire('pages')->count("template=comorbidity-drug, parent={$admission->id}")) {
            $admission->of(false);
            $admission->set('comorbidity_none', 1);
            if (wire('fields')->get('comorbidity_flags')) {
                $admission->set('comorbidity_flags', []);
            }
            $admission->save();
            $noneCases++;
        }
        continue;
    }

    $existingDrugs = wire('pages')->count("template=comorbidity-drug, parent={$admission->id}");
    $existingFlags = [];
    if (wire('fields')->get('comorbidity_flags') && is_iterable($admission->getUnformatted('comorbidity_flags'))) {
        foreach ($admission->getUnformatted('comorbidity_flags') as $existingFlag) {
            $existingFlags[] = $existingFlag;
        }
    }
    if ($existingDrugs > 0 || (wire('fields')->get('comorbidity_none') && (int) $admission->getUnformatted('comorbidity_none') === 1) || $existingFlags) {
        warn13("Case #{$admission->id} already appears migrated - skipping");
        $skippedCases++;
        continue;
    }

    $decoded = json_decode($legacyText, true);
    if (!is_array($decoded)) {
        warn13("Case #{$admission->id} has invalid comorbidities JSON - skipping");
        $skippedCases++;
        continue;
    }

    $flagSet = [];
    $createdForCase = 0;
    $meaningfulRows = 0;

    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $condition = trim((string) ($row['condition'] ?? ''));
        $treatment = trim((string) ($row['treatment'] ?? ''));
        if ($condition === '' && $treatment === '') {
            continue;
        }

        $meaningfulRows++;
        $flag = normalizeComorbidityFlag13($condition);
        $resolvedCondition = $flag !== '' ? $flag : ($condition !== '' ? $condition : 'Custom');

        if ($flag !== '') {
            $flagSet[$flag] = $flag;
        } else {
            $flagSet['Custom'] = 'Custom';
        }

        if ($treatment === '') {
            continue;
        }

        [$drugName, $drugDose] = splitTreatment13($treatment);
        if ($drugName === '') {
            continue;
        }

        $drugPage = new Page();
        $drugPage->template = 'comorbidity-drug';
        $drugPage->parent = $admission;
        $drugPage->name = $sanitizer->pageName(($resolvedCondition ?: 'comorbidity') . '-drug-' . $drugName, true) ?: ('comorbidity-drug-' . time() . '-' . $createdForCase);
        $drugPage->title = trim(($resolvedCondition ?: 'Comorbidity') . ' Drug - ' . $drugName);
        $drugPage->of(false);
        if (wire('fields')->get('comorb_condition_flag')) {
            $drugPage->set('comorb_condition_flag', $resolvedCondition);
        }
        if (wire('fields')->get('drug_name')) {
            $drugPage->set('drug_name', $drugName);
        }
        if (wire('fields')->get('drug_dose')) {
            $drugPage->set('drug_dose', $drugDose);
        }
        $drugPage->save();

        $createdForCase++;
        $migratedDrugs++;
    }

    $admission->of(false);
    if ($meaningfulRows === 0) {
        if (wire('fields')->get('comorbidity_none')) {
            $admission->set('comorbidity_none', 1);
        }
        if (wire('fields')->get('comorbidity_flags')) {
            $admission->set('comorbidity_flags', []);
        }
        $admission->save();
        $noneCases++;
        ok13("Case #{$admission->id}: marked comorbidity_none = 1");
        continue;
    }

    if (wire('fields')->get('comorbidity_none')) {
        $admission->set('comorbidity_none', 0);
    }
    if (wire('fields')->get('comorbidity_flags')) {
        $admission->set('comorbidity_flags', array_values($flagSet));
    }
    $admission->save();

    $migratedCases++;
    ok13("Case #{$admission->id}: migrated " . count($flagSet) . " flags and $createdForCase drug rows");
}

echo '<h2>SECTION 6 - Summary</h2>';
ok13("Migrated cases: $migratedCases");
ok13("Migrated drug rows: $migratedDrugs");
info13("Cases marked as none: $noneCases");
info13("Skipped cases: $skippedCases");
info13('Legacy comorbidities field retained as backup; not deleted by this migration.');
?>
</body>
</html>
