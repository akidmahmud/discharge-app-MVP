<?php
// Quick test: bootstrap ProcessWire and save chief_complaint to case 1211
$rootPath = __DIR__;
if (!class_exists('ProcessWire\\ProcessWire', false)) {
    require_once $rootPath . '/wire/core/ProcessWire.php';
}
$config = ProcessWire\ProcessWire::buildConfig($rootPath);
$config->external = true;
$wire = new ProcessWire\ProcessWire($config);

$caseId = 1211; // adjust to a real case ID
$case = $wire->pages->get("id=$caseId, template=admission-record");
if (!$case || !$case->id) {
    echo "ERROR: Case $caseId not found\n";
    exit(1);
}

echo "Case found: {$case->id} ({$case->name})\n";
echo "Current chief_complaint: " . (string)$case->chief_complaint . "\n";

$case->of(false);
$case->chief_complaint = 'Test save from script - ' . date('H:i:s');
try {
    $result = $case->save();
    echo "Save result: " . var_export($result, true) . "\n";
} catch (\Throwable $e) {
    echo "Save EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// Reload and verify
$case2 = $wire->pages->get("id=$caseId, template=admission-record");
echo "After save, chief_complaint: " . (string)$case2->chief_complaint . "\n";
