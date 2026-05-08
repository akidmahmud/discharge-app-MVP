<?php namespace ProcessWire;
include 'index.php';

$db = $wire->database;
$pages_id = 1019;
$val = 'IP-MANUAL-FIX-1';

$db->query("DELETE FROM field_ip_number WHERE pages_id = $pages_id");
$q = $db->prepare("INSERT INTO field_ip_number (pages_id, data) VALUES (:pid, :val)");
$q->execute([':pid' => $pages_id, ':val' => $val]);

$res = $db->query("SELECT * FROM field_ip_number WHERE pages_id = $pages_id");
$row = $res->fetch();
echo "Manual SQL Insert check: " . ($row ? $row['data'] : "FAILED") . "\n";
