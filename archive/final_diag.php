<?php namespace ProcessWire;
include 'index.php';

echo "--- ProcessWire Diagnostics ---\n";
$p = $pages->get('/dashboard/');
echo "Dashboard Page: " . ($p->id ? "Found (ID: {$p->id}, Path: {$p->path})" : "NOT FOUND") . "\n";

$home = $pages->get(1);
echo "Home Page Path: " . $home->path . "\n";

echo "--- Apache/Server Diagnostics ---\n";
echo "RewriteBase in .htaccess: ";
$htaccess = file_get_contents('.htaccess');
if(preg_match('/RewriteBase\s+(.*)/', $htaccess, $matches)) {
    echo $matches[1] . "\n";
} else {
    echo "NOT SET\n";
}

echo "mod_rewrite enabled (detected by PHP): " . (isset($_SERVER['HTTP_MOD_REWRITE']) ? "YES" : "NO / UNKNOWN") . "\n";

echo "--- File Integrity ---\n";
$templates = ['dashboard.php', 'admission-record.php', 'patient-record.php', 'home.php'];
foreach($templates as $t) {
    echo "Template site/templates/$t: " . (file_exists("site/templates/$t") ? "EXISTS" : "MISSING") . "\n";
}

echo "Assets writable: " . (is_writable("site/assets") ? "YES" : "NO") . "\n";
