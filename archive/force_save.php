<?php namespace ProcessWire;
include 'index.php';

$ids = [1019, 1023];
foreach($ids as $id) {
    $p = $pages->get($id);
    if(!$p->id) continue;
    $p->of(false);
    $p->set('ip_number', 'IP-' . date('Ymd') . '-' . $id);
    $p->set('discharge_consultant', 'Dr. S. Raja Sabapathy');
    $p->save();
    echo "Forced Save for $id: " . $p->ip_number . "\n";
}
