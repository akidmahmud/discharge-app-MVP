<?php namespace ProcessWire;
include 'index.php';

$out = "";
$out .= "--- ProcessWire Diagnostics ---\n";
$p = $pages->get('/dashboard/');
$out .= "Dashboard Page: " . ($p->id ? "Found (ID: {$p->id}, Path: {$p->path})" : "NOT FOUND") . "\n";

$home = $pages->get(1);
$out .= "Home Page Path: " . $home->path . "\n";

$out .= "--- Apache/Server Diagnostics ---\n";
$out .= "RewriteBase in .htaccess: ";
$htaccess = file_get_contents('.htaccess');
if(preg_match('/RewriteBase\s+(.*)/', $htaccess, $matches)) {
    $out .= $matches[1] . "\n";
} else {
    $out .= "NOT SET\n";
}

$out .= "--- File Integrity ---\n";
$tmpls = ['dashboard.php', 'admission-record.php', 'patient-record.php', 'home.php'];
foreach($tmpls as $t) {
    $out .= "Template site/templates/$t: " . (file_exists("site/templates/$t") ? "EXISTS" : "MISSING") . "\n";
}

$out .= "Assets writable: " . (is_writable("site/assets") ? "YES" : "NO") . "\n";

file_put_contents('diag_results.txt', $out);
echo "Diagnostics written to diag_results.txt\n";
