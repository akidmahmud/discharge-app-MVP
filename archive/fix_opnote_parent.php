<?php namespace ProcessWire;

$rootPath = 'C:/laragon/www/discharge-app';
if (!class_exists("ProcessWire\\ProcessWire", false)) {
    require_once("$rootPath/wire/core/ProcessWire.php");
}
$config = ProcessWire::buildConfig($rootPath);
$config->internal = false;
$wire = new ProcessWire($config);

$templates = $wire->templates;
$wire->users->setCurrentUser($wire->users->get('admin'));

$procTpl   = $templates->get('procedure');
$opNoteTpl = $templates->get('operation-note');

if (!$procTpl || !$opNoteTpl) {
    echo "FAIL: Could not load templates\n";
    exit(1);
}

echo "procedure template id: {$procTpl->id}\n";
echo "operation-note template id: {$opNoteTpl->id}\n";

// Check current state
$current = $opNoteTpl->parentTemplates;
echo "Current parentTemplates count: " . count($current) . "\n";

// Set restriction: operation-note can only be child of procedure
$opNoteTpl->parentTemplates = [$procTpl->id];
$templates->save($opNoteTpl);

// Verify
$opNoteTplFresh = $templates->get('operation-note');
$fresh = $opNoteTplFresh->parentTemplates;
echo "After save parentTemplates count: " . count($fresh) . "\n";
foreach ($fresh as $ptId) {
    $pt = $templates->get($ptId);
    echo "  Allowed parent: " . ($pt ? $pt->name : "id=$ptId") . "\n";
}

$ok = count($fresh) === 1 && in_array($procTpl->id, (array)$fresh);
echo "RESULT: " . ($ok ? "PASS — operation-note restricted to procedure parent only" : "FAIL") . "\n";
