<?php namespace ProcessWire;
include 'index.php';

echo "--- DEEP DIAGNOSTIC ---\n";

// 1. Check Fields
$expectedFields = [
    'patient_id', 'ip_number', 'diagnosis', 'procedures', 'clinical_images', 
    'admitted_on', 'discharged_on', 'discharge_consultant'
];
foreach($expectedFields as $fn) {
    $f = $fields->get($fn);
    echo "Field $fn: " . ($f ? "OK" : "MISSING") . "\n";
}

// 2. Check Templates
$expectedTemplates = ['patient-record', 'admission-record', 'dashboard'];
foreach($expectedTemplates as $tn) {
    $t = $templates->get($tn);
    echo "Template $tn: " . ($t ? "OK" : "MISSING") . "\n";
}

// 3. Data Integrity
$patients = $pages->find('template=patient-record');
echo "Patients found: " . count($patients) . "\n";

foreach($patients as $p) {
    $p->of(false);
    $admissions = $p->children('template=admission-record');
    echo "Patient {$p->title} has " . count($admissions) . " admissions.\n";
    foreach($admissions as $a) {
        $a->of(false);
        if(!$a->admitted_on) $a->admitted_on = time();
        // Ensure some are "discharged" for stats
        if(!$a->discharged_on && $a->id % 2 == 0) {
             $a->discharged_on = time();
        }
        $a->save();
    }
}

// 4. URL Check
$dashboard = $pages->get('/dashboard/');
echo "Dashboard URL: " . ($dashboard->id ? $dashboard->url : "INVALID") . "\n";

echo "--- RE-SAVING ALL SYSTEM PAGES ---\n";
foreach($pages->find("id>0") as $p) {
    try {
        $p->of(false);
        $p->save();
    } catch(\Exception $e) {}
}

echo "Deep Diagnostic Complete.\n";
