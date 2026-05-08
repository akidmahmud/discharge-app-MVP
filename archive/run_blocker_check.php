<?php namespace ProcessWire;
include(__DIR__ . '/index.php');
$output = "";

$output .= "--- TEMPLATES ---\n";
$checkT = ['procedure', 'operation-note', 'investigation', 'search-results'];
foreach($checkT as $t) {
    $tpl = wire('templates')->get($t);
    $output .= "$t: " . ($tpl ? "EXISTS" : "MISSING") . "\n";
}

$output .= "\n--- PAGES ---\n";
$checkP = ['/patients/', '/search/'];
foreach($checkP as $p) {
    $pg = wire('pages')->get($p);
    $output .= "$p: " . ($pg->id ? "EXISTS (ID: {$pg->id})" : "MISSING") . "\n";
}

$output .= "\n--- FILES ---\n";
$checkF = ['site/templates/search-results.php'];
foreach($checkF as $f) {
    $path = wire('config')->paths->root . $f;
    $output .= "$f: " . (file_exists($path) ? "EXISTS" : "MISSING") . "\n";
}

file_put_contents(__DIR__ . '/blocker_check.txt', $output);
echo "Check complete. Read blocker_check.txt\n";
