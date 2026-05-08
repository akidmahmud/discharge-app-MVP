<?php namespace ProcessWire;
include 'index.php';

$tAdm = $templates->get('admission-record');
$fg = $tAdm->fieldgroup;

$fNames = [
    'title', 'ip_number', 'admitted_on', 'discharged_on',
    'tab_history', 'diagnosis', 'history_complaints', 'examination_findings', 'radiology_report', 'tab_close',
    'tab_operation', 'procedures', 'clinical_images', 'tab_close',
    'tab_discharge', 'course_in_hospital', 'condition_at_discharge', 'tab_close',
    'discharge_consultant'
];

foreach($fNames as $fn) {
    $f = $fields->get($fn);
    if($f && !$fg->has($f)) {
        $fg->add($f);
        echo "Added $fn to fieldgroup.\n";
    }
}

$fg->save();
echo "Fieldgroup saved. Count: " . count($fg) . " fields.\n";
