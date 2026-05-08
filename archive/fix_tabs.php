<?php namespace ProcessWire;
include 'index.php';

// Fix fieldgroup ordering without removing 'title'
$tAdm = $templates->get('admission-record');
$fg = $tAdm->fieldgroup;

$titleField = $fields->get('title');
$tab1 = $fields->get('tab_history') ?: new Field();
$tab2 = $fields->get('tab_operation') ?: new Field();
$tab3 = $fields->get('tab_discharge') ?: new Field();
$tabClose = $fields->get('tab_close') ?: new Field();

// Helper to add if missing
function safeAdd($fg, $f) {
    if($f && $f->id && !$fg->has($f)) $fg->add($f);
}

safeAdd($fg, $tab1);
safeAdd($fg, $tab2);
safeAdd($fg, $tab3);
safeAdd($fg, $tabClose);

// Manual ordering
$order = [
    'title', 'ip_number', 'admitted_on', 'discharged_on',
    'tab_history', 'diagnosis', 'history_complaints', 'examination_findings', 'radiology_report', 'tab_close',
    'tab_operation', 'procedures', 'clinical_images', 'tab_close',
    'tab_discharge', 'course_in_hospital', 'condition_at_discharge', 'tab_close'
];

foreach($order as $idx => $name) {
    $f = $fields->get($name);
    if($f) $fg->setFieldSort($f, $idx);
}

$fg->save();

echo "Fieldgroup ordering fixed with Clinical Tabs.\n";
