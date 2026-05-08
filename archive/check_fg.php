<?php namespace ProcessWire;
include 'index.php';

$fg = $templates->get('admission-record')->fieldgroup;
echo "Fieldgroup for admission-record: " . $fg->name . "\n";
foreach($fg as $f) {
    echo "  - " . $f->name . " (Type: " . $f->type->name . ")\n";
}
