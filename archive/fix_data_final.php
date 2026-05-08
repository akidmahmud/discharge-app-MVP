<?php namespace ProcessWire;
include 'index.php';

// Forcefully fix all clinical data
foreach($pages->find('template=patient-record') as $p) {
    $p->of(false);
    if(!$p->patient_id) {
        $p->patient_id = 'REG-' . date('Y') . '-' . str_pad($p->id, 4, '0', STR_PAD_LEFT);
    }
    $p->save();
    echo "Fixed Patient: {$p->title} ({$p->patient_id})\n";
}

foreach($pages->find('template=admission-record') as $a) {
    $a->of(false);
    if(!$a->ip_number) {
        $a->ip_number = 'IP-' . date('Ymd') . '-' . str_pad($a->id, 4, '0', STR_PAD_LEFT);
    }
    if(!$a->discharge_consultant) {
        $a->discharge_consultant = 'Dr. S. Raja Sabapathy'; // Default for samples
    }
    $a->save();
    echo "Fixed Admission: {$a->title} ({$a->ip_number})\n";
}
