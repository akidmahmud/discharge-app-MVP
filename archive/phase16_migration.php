<?php namespace ProcessWire;
/**
 * Phase 16 Migration — Audit Action Extension for PDF Generation
 *
 * Adds a dedicated "Generated PDF" option to the audit_action field so
 * discharge PDF downloads can be logged with a semantically correct action.
 */

require_once __DIR__ . '/site/index.php';

header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

function out16(string $message): void {
    echo $message . "<br>\n";
    flush();
}

function ok16(string $message): void {
    out16("<span style=\"color:#166534;\">OK</span> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

function warn16(string $message): void {
    out16("<span style=\"color:#92400e;\">WARN</span> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

function fail16(string $message): void {
    out16("<span style=\"color:#991b1b;\">FAIL</span> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Phase 16 Migration</title></head><body style="font-family:Arial,sans-serif;">';
echo '<h1>Phase 16 Migration — Audit Action Extension</h1>';

$auditAction = wire('fields')->get('audit_action');
if (!$auditAction || !$auditAction->id) {
    fail16('Field audit_action was not found. Run the earlier audit-log migration first.');
    echo '</body></html>';
    return;
}

if ($auditAction->type->className() !== 'FieldtypeOptions') {
    fail16('Field audit_action exists but is not a FieldtypeOptions field.');
    echo '</body></html>';
    return;
}

$manager = new \ProcessWire\SelectableOptionManager();
$existingOptions = $manager->getOptions($auditAction);
$hasGeneratedPdf = false;

foreach ($existingOptions as $option) {
    $title = isset($option->title) ? trim((string) $option->title) : '';
    $value = isset($option->value) ? (int) $option->value : 0;
    if ($value === 6 || strcasecmp($title, 'Generated PDF') === 0) {
        $hasGeneratedPdf = true;
        break;
    }
}

if ($hasGeneratedPdf) {
    warn16('audit_action already contains Generated PDF — nothing to change.');
} else {
    $optionString = trim($manager->getOptionsString($auditAction));
    $append = "6=Generated PDF";
    $optionString = $optionString !== '' ? ($optionString . "\n" . $append) : $append;
    $manager->setOptionsString($auditAction, $optionString, false);
    wire('fields')->save($auditAction);
    ok16('Added audit_action option: 6 = Generated PDF');
}

echo '</body></html>';
