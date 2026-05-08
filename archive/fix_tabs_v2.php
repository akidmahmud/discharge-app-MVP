<?php namespace ProcessWire;
include 'index.php';

$tAdm = $templates->get('admission-record');
$fg = $tAdm->fieldgroup;

$order = [
    'title', 'ip_number', 'admitted_on', 'discharged_on',
    'tab_history', 'diagnosis', 'history_complaints', 'examination_findings', 'radiology_report', 'tab_close',
    'tab_operation', 'procedures', 'clinical_images', 'tab_close',
    'tab_discharge', 'course_in_hospital', 'condition_at_discharge', 'tab_close'
];

// Remove all fields except title
foreach($fg as $f) {
    if($f->name == 'title') continue;
    $fg->remove($f);
}

// Add them back in order
foreach($order as $name) {
    if($name == 'title') continue;
    $f = $fields->get($name);
    if($f) $fg->add($f);
}

$fg->save();

echo "Fieldgroup ordering fixed with Clinical Tabs.\n";
