<?php namespace ProcessWire;
include(__DIR__ . '/index.php');
$check = ['procedure', 'operation-note', 'investigation'];
foreach($check as $t) {
    $tpl = wire('templates')->get($t);
    echo "$t: " . ($tpl ? "EXISTS" : "MISSING") . "\n";
}
