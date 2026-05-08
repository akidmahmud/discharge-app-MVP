<?php namespace ProcessWire;
include 'index.php';

foreach($pages->find('template=patient-record|admission-record') as $p) {
    $p->of(false);
    $p->save();
    echo "Updated {$p->title} (ID: {$p->patient_id}, IP: {$p->ip_number})\n";
}
