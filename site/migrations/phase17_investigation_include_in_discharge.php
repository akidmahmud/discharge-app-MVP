<?php namespace ProcessWire;
/**
 * Phase 17 Migration - Investigation Discharge Toggle
 *
 * Adds include_in_discharge to investigation entries so the Investigation
 * module can persist the discharge-summary inclusion flag introduced in the
 * manual audit fixes.
 */

require_once dirname(__DIR__, 2) . '/index.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

function out17(string $message): void {
    echo $message . "<br>\n";
    flush();
}

function ok17(string $message): void {
    out17('<span style="color:#166534;">OK</span> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

function warn17(string $message): void {
    out17('<span style="color:#92400e;">WARN</span> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

function fail17(string $message): void {
    out17('<span style="color:#991b1b;">FAIL</span> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Phase 17 Migration</title></head><body style="font-family:Arial,sans-serif;">';
echo '<h1>Phase 17 Migration - Investigation Discharge Toggle</h1>';

$fields = wire('fields');
$templates = wire('templates');
$pages = wire('pages');

$field = $fields->get('include_in_discharge');
if (!$field || !$field->id) {
    $field = new Field();
    $field->type = $fields->getFieldtype('FieldtypeCheckbox');
    $field->name = 'include_in_discharge';
    $field->label = 'Include in discharge';
    $field->save();
    ok17('Created field include_in_discharge');
} else {
    ok17('Field include_in_discharge already exists');
}

$investigationTemplate = $templates->get('investigation');
if (!$investigationTemplate || !$investigationTemplate->id) {
    fail17('Template investigation was not found.');
    echo '</body></html>';
    return;
}

$fieldgroup = $investigationTemplate->fieldgroup;
if (!$fieldgroup->hasField($field)) {
    $fieldgroup->add($field);
    $fieldgroup->save();
    ok17('Attached include_in_discharge to template investigation');
} else {
    ok17('Template investigation already contains include_in_discharge');
}

$updatedCount = 0;
foreach ($pages->find("template=investigation, include=all") as $investigationPage) {
    $currentValue = (int) $investigationPage->getUnformatted('include_in_discharge');
    if ($currentValue !== 1) {
        $investigationPage->of(false);
        $investigationPage->include_in_discharge = 1;
        $investigationPage->save('include_in_discharge');
        $updatedCount++;
    }
}

if ($updatedCount) {
    ok17("Defaulted include_in_discharge = 1 on {$updatedCount} investigation entries");
} else {
    warn17('No investigation entries needed backfill');
}

echo '</body></html>';
