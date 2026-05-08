<?php namespace ProcessWire;
include('./index.php');

$templates_to_check = ['procedure', 'operation-note', 'investigation'];
$missing_templates = [];

foreach ($templates_to_check as $t_name) {
    if (!$templates->get($t_name)) {
        $missing_templates[] = $t_name;
    }
}

$search_page = $pages->get('/search/');
$search_page_status = $search_page->id ? "Exists (Template: {$search_page->template->name})" : "Missing";

$patient_record_parent = "Unknown";
$p_sample = $pages->get("template=patient-record");
if ($p_sample->id) {
    $patient_record_parent = $p_sample->parent->path;
}

echo "TEMPLATES_CHECK:\n";
if (empty($missing_templates)) {
    echo "All templates exist.\n";
} else {
    echo "Missing templates: " . implode(', ', $missing_templates) . "\n";
}

echo "\nSEARCH_PAGE_CHECK:\n";
echo "Search Page: $search_page_status\n";
$search_template_file = file_exists("./site/templates/search-results.php") ? "Exists" : "Missing";
echo "Search Template File (search-results.php): $search_template_file\n";

echo "\nPATIENT_PARENT_CHECK:\n";
echo "Patient Record Parent Path: $patient_record_parent\n";
