<?php namespace ProcessWire;
include 'index.php';

$p = $pages->get(1019);
$out = "ID: {$p->id} | Title: {$p->title}\n";
$out .= "Has ip_number field in template: " . ($p->template->fieldgroup->has('ip_number') ? "YES" : "NO") . "\n";
$out .= "Raw IP value: " . $p->getUnformatted('ip_number') . "\n";
$out .= "Formatted IP value: " . $p->ip_number . "\n";

file_put_contents('final_check.txt', $out);
