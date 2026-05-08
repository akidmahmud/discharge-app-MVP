<?php namespace ProcessWire;
include 'index.php';

echo "Clearing Caches...\n";
$wire->cache->deleteAll();
$wire->modules->refresh();

echo "Re-saving Fieldgroups...\n";
foreach($fieldgroups as $fg) {
    $fg->save();
}

echo "Re-saving Templates...\n";
foreach($templates as $t) {
    $t->save();
}

echo "System Refresh Complete.\n";
