<?php namespace ProcessWire;

$rootPath = 'C:/laragon/www/discharge-app';
if (!class_exists("ProcessWire\ProcessWire", false)) {
    require_once("$rootPath/wire/core/ProcessWire.php");
}
$config = ProcessWire::buildConfig($rootPath);
$config->internal = false;
$wire = new ProcessWire($config);
$pages = $wire->pages;
$templates = $wire->templates;
$wire->users->setCurrentUser($wire->users->get('admin'));

// Load ready.php
$wire->wire()->includeFile($rootPath . '/site/ready.php', [
    'wire' => $wire, 'pages' => $pages, 'templates' => $templates,
]);

// Get an admission with case_status=2 (page 1048 from previous test)
$patCon = $pages->get('/patients/');

// Create fresh admission and set status=2
$p = new Page();
$p->template = $templates->get('admission-record');
$p->parent   = $pages->get('/patients/vtest-patient-1776703809/'); // use existing test patient or container
if (!$p->parent || !$p->parent->id) $p->parent = $patCon;
$p->name     = 'status-test-' . time();
$p->title    = 'Status Test Admission';
$pages->save($p);
echo "Created admission id={$p->id}\n";

// Now check what getUnformatted returns for case_status
$p->of(false);
$p->case_status = 2;

$raw = $p->getUnformatted('case_status');
echo "getUnformatted('case_status') type: " . get_class($raw) . "\n";
echo "getUnformatted value: ";
var_dump($raw);
echo "(int) cast: " . (int)$raw . "\n";

// Check if we can get the ID correctly
if ($raw instanceof \ProcessWire\SelectableOptionArray) {
    $opt = $raw->first();
    echo "First option ID: " . ($opt ? $opt->id : "none") . "\n";
    echo "First option value: " . ($opt ? $opt->value : "none") . "\n";
    echo "First option title: " . ($opt ? $opt->title : "none") . "\n";
}

// What about $p->case_status directly?
$direct = $p->case_status;
echo "Direct case_status: ";
var_dump($direct);

// Save and re-read
$pages->save($p);
$fresh = $pages->get($p->id);
$rawFresh = $fresh->getUnformatted('case_status');
echo "\nAfter save - getUnformatted type: " . (is_object($rawFresh) ? get_class($rawFresh) : gettype($rawFresh)) . "\n";
echo "After save - (int) cast: " . (int)$rawFresh . "\n";

$pages->delete($p, true);
echo "Cleanup done.\n";
