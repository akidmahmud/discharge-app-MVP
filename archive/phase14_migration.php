<?php namespace ProcessWire;

/**
 * Phase 14 Migration - Structured Drug History
 *
 * WHAT THIS DOES:
 * 1. Creates drug_frequency and drug_history_reviewed fields
 * 2. Creates drug-history-entry child template
 * 3. Allows drug-history-entry under admission-record
 * 4. Migrates legacy drug_history textarea into child pages
 *
 * Run via browser: http://discharge-app.test/phase14_migration.php
 * Requires superuser login.
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

function step14($msg) {
    echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>&#9881; $msg</div>\n";
    flush();
}
function ok14($msg) {
    echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10003; $msg</div>\n";
    flush();
}
function warn14($msg) {
    echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9888; $msg</div>\n";
    flush();
}
function fail14($msg) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10007; $msg</div>\n";
    flush();
}
function info14($msg) {
    echo "<div style='background:#0c1a2e;color:#93c5fd;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9432; $msg</div>\n";
    flush();
}

function createField14($name, $type, $label, $cfg = []) {
    $field = wire('fields')->get($name);
    if ($field && $field->id) {
        warn14("Field '$name' already exists - skipping");
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
    ok14("Created field: $name ($type)");
    return $field;
}

function attachField14($fieldName, $templateName) {
    $template = wire('templates')->get($templateName);
    $field = wire('fields')->get($fieldName);
    if (!$template || !$template->id || !$field || !$field->id) {
        fail14("Cannot attach '$fieldName' to '$templateName' - missing field or template");
        return;
    }
    if ($template->fieldgroup->has($field)) {
        warn14("'$fieldName' already attached to '$templateName'");
        return;
    }
    $template->fieldgroup->add($field);
    $template->fieldgroup->save();
    ok14("Attached '$fieldName' -> '$templateName'");
}

function parseDrugHistoryLine14($line) {
    $line = trim((string) $line);
    if ($line === '') {
        return ['', '', ''];
    }

    $parts = preg_split('/\s*[-|,;]\s*/', $line, 3);
    if (count($parts) >= 3) {
        return [trim($parts[0]), trim($parts[1]), trim($parts[2])];
    }
    if (count($parts) === 2) {
        return [trim($parts[0]), trim($parts[1]), ''];
    }

    if (preg_match('/^(.+?)(\s+\d+(?:\.\d+)?\s*(?:mg|mcg|g|ml|iu|units?)\b)(?:\s+(.+))?$/i', $line, $matches)) {
        return [
            trim($matches[1]),
            trim($matches[2]),
            trim($matches[3] ?? ''),
        ];
    }

    return [$line, '', ''];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 14 - Drug History Restructure</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; max-width:900px; }
h1   { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2   { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>&#127973; Phase 14 Migration - Drug History Restructure</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?> &nbsp;|&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<?php
echo '<h2>SECTION 1 - Fields</h2>';
createField14('drug_frequency', 'FieldtypeText', 'Drug Frequency');
createField14('drug_history_reviewed', 'FieldtypeCheckbox', 'Drug history reviewed');

echo '<h2>SECTION 2 - Attach Fields</h2>';
attachField14('drug_history_reviewed', 'admission-record');

echo '<h2>SECTION 3 - Create Template</h2>';
$template = wire('templates')->get('drug-history-entry');
if (!$template || !$template->id) {
    $fieldgroup = new Fieldgroup();
    $fieldgroup->name = 'drug-history-entry';
    $fieldgroup->add(wire('fields')->get('title'));
    $fieldgroup->save();

    $template = new Template();
    $template->name = 'drug-history-entry';
    $template->label = 'Drug History Entry';
    $template->fieldgroup = $fieldgroup;
    $template->noChildren = 1;
    $template->tags = 'clinical';
    wire('templates')->save($template);
    ok14('Created template: drug-history-entry');
} else {
    warn14('Template drug-history-entry already exists - skipping');
}

attachField14('drug_name', 'drug-history-entry');
attachField14('drug_dose', 'drug-history-entry');
attachField14('drug_frequency', 'drug-history-entry');
if (wire('fields')->get('created_by_user')) {
    attachField14('created_by_user', 'drug-history-entry');
}

echo '<h2>SECTION 4 - Template Relationships</h2>';
$admissionTemplate = wire('templates')->get('admission-record');
$drugHistoryTemplate = wire('templates')->get('drug-history-entry');
if ($admissionTemplate && $admissionTemplate->id && $drugHistoryTemplate && $drugHistoryTemplate->id) {
    $childTemplates = $admissionTemplate->childTemplates ?: [];
    if (!in_array($drugHistoryTemplate->id, $childTemplates)) {
        $childTemplates[] = $drugHistoryTemplate->id;
        $admissionTemplate->childTemplates = $childTemplates;
        wire('templates')->save($admissionTemplate);
        ok14('Added drug-history-entry as allowed child of admission-record');
    } else {
        warn14('drug-history-entry already allowed under admission-record');
    }

    $parentTemplates = $drugHistoryTemplate->parentTemplates ?: [];
    if (!in_array($admissionTemplate->id, $parentTemplates)) {
        $parentTemplates[] = $admissionTemplate->id;
        $drugHistoryTemplate->parentTemplates = $parentTemplates;
        wire('templates')->save($drugHistoryTemplate);
        ok14('Set admission-record as allowed parent of drug-history-entry');
    } else {
        warn14('admission-record already allowed as parent of drug-history-entry');
    }
} else {
    fail14('Could not configure template relationships');
}

echo '<h2>SECTION 5 - Data Migration</h2>';
$admissions = wire('pages')->find('template=admission-record, limit=1000');
$migratedCases = 0;
$migratedRows = 0;
$skippedCases = 0;

foreach ($admissions as $admission) {
    $legacyText = trim((string) $admission->drug_history);
    if ($legacyText === '') {
        continue;
    }

    $existingRows = wire('pages')->count("template=drug-history-entry, parent={$admission->id}");
    if ($existingRows > 0) {
        warn14("Case #{$admission->id} already has $existingRows drug-history-entry pages - skipping");
        $skippedCases++;
        continue;
    }

    $lines = preg_split('/\r\n|\r|\n/', $legacyText);
    $createdForCase = 0;

    foreach ($lines as $index => $line) {
        [$drugName, $drugDose, $drugFrequency] = parseDrugHistoryLine14($line);
        if ($drugName === '') {
            continue;
        }

        $drugPage = new Page();
        $drugPage->template = 'drug-history-entry';
        $drugPage->parent = $admission;
        $drugPage->name = $sanitizer->pageName($drugName . '-drug-history-' . $index, true) ?: ('drug-history-' . time() . '-' . $index);
        $drugPage->title = trim($drugName . ' - Drug History');
        $drugPage->of(false);
        if (wire('fields')->get('drug_name')) {
            $drugPage->set('drug_name', $drugName);
        }
        if (wire('fields')->get('drug_dose')) {
            $drugPage->set('drug_dose', $drugDose);
        }
        if (wire('fields')->get('drug_frequency')) {
            $drugPage->set('drug_frequency', $drugFrequency);
        }
        $drugPage->save();

        $createdForCase++;
        $migratedRows++;
    }

    $admission->of(false);
    if (wire('fields')->get('drug_history_reviewed')) {
        $admission->set('drug_history_reviewed', 1);
    }
    $admission->save();

    $migratedCases++;
    ok14("Case #{$admission->id}: migrated $createdForCase drug history rows");
}

echo '<h2>SECTION 6 - Summary</h2>';
ok14("Migrated cases: $migratedCases");
ok14("Migrated drug rows: $migratedRows");
info14("Skipped cases: $skippedCases");
info14('Legacy drug_history field retained as backup; not deleted by this migration.');
?>
</body>
</html>
