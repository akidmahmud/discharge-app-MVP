<?php namespace ProcessWire;
include 'index.php';

$out = "--- DB RAW CHECK ---\n";
$admissions = $pages->find("template=admission-record");
foreach($admissions as $a) {
    $out .= "ID: {$a->id} | Title: {$a->title} | IP: '{$a->ip_number}' | Consultant: '{$a->discharge_consultant}'\n";
}

$dashboard = $templates->get('dashboard');
$dashboard->appendFile = '';
$dashboard->prependFile = '';
$dashboard->useMarkupRegions = false;
$dashboard->save();

$admRec = $templates->get('admission-record');
$admRec->appendFile = '';
$admRec->prependFile = '';
$admRec->useMarkupRegions = false;
$admRec->save();

file_put_contents('raw_diag.txt', $out);
echo "Raw diagnostics and template settings updated.\n";
