<?php namespace ProcessWire;
include 'index.php';

$out = "--- DB TABLE CHECK ---\n";
$db = $wire->database;

$tables = [
    'field_ip_number', 
    'field_proc_date', 
    'field_surgery_date', 
    'field_investigation_date', 
    'field_review_date',
    'field_patient_id',
    'field_case_status'
];
foreach($tables as $t) {
    $res = $db->query("SHOW TABLES LIKE '$t'");
    $out .= "Table $t: " . ($res->rowCount() > 0 ? "EXISTS" : "MISSING") . "\n";
}

$templates_to_check = ['procedure', 'operation-note', 'investigation', 'patient-record', 'admission-record'];
foreach($templates_to_check as $tpl) {
    $res = $db->query("SELECT id FROM templates WHERE name='$tpl'");
    $out .= "Template $tpl: " . ($res->rowCount() > 0 ? "EXISTS" : "MISSING") . "\n";
}

file_put_contents('db_check.txt', $out);
echo "DB check complete.\n";
