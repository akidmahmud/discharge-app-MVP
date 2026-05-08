<?php namespace ProcessWire;

$rootPath = 'C:/laragon/www/discharge-app';
if (!class_exists("ProcessWire\\ProcessWire", false)) {
    require_once("$rootPath/wire/core/ProcessWire.php");
}
$config = ProcessWire::buildConfig($rootPath);
$config->internal = false;
$wire = new ProcessWire($config);

$pages     = $wire->pages;
$templates = $wire->templates;
$fields    = $wire->fields;
$fieldgroups = $wire->fieldgroups;
$wire->users->setCurrentUser($wire->users->get('admin'));

echo "=== AUDIT LOG INFRASTRUCTURE SETUP ===\n\n";

// ── STEP 1: Create audit fields ───────────────────────────────────────────────
$auditFieldDefs = [
    'audit_entity_id'       => 'FieldtypeInteger',
    'audit_entity_template' => 'FieldtypeText',
    'audit_entity_title'    => 'FieldtypeText',
    'audit_action'          => 'FieldtypeInteger',
    'audit_user_id'         => 'FieldtypeInteger',
    'audit_user_name'       => 'FieldtypeText',
    'audit_timestamp'       => 'FieldtypeDatetime',
    'audit_ip_address'      => 'FieldtypeText',
    'audit_field_changes'   => 'FieldtypeTextarea',
];

echo "STEP 1: Create audit fields\n";
foreach ($auditFieldDefs as $fname => $ftype) {
    $existing = $fields->get($fname);
    if ($existing && $existing->id) {
        echo "  '$fname': already exists (id={$existing->id})\n";
        continue;
    }
    $f = new Field();
    $f->type  = $wire->modules->get($ftype);
    $f->name  = $fname;
    $f->label = ucwords(str_replace('_', ' ', $fname));
    if ($ftype === 'FieldtypeTextarea') {
        $f->inputfieldClass = 'InputfieldTextarea';
    }
    $fields->save($f);
    $check = $fields->get($fname);
    echo "  '$fname': " . ($check && $check->id ? "CREATED id={$check->id}" : "FAIL") . "\n";
}

// ── STEP 2: Create or get audit-log fieldgroup ────────────────────────────────
echo "\nSTEP 2: Create audit-log fieldgroup\n";
$fgName = 'audit-log';
$fg = $fieldgroups->get($fgName);
if (!$fg || !$fg->id) {
    $fg = new Fieldgroup();
    $fg->name = $fgName;

    // Always include title first
    $titleField = $fields->get('title');
    if ($titleField) $fg->add($titleField);

    foreach (array_keys($auditFieldDefs) as $fname) {
        $f = $fields->get($fname);
        if ($f && $f->id) $fg->add($f);
    }
    $fieldgroups->save($fg);
    echo "  Fieldgroup created id={$fg->id}\n";
} else {
    echo "  Fieldgroup already exists id={$fg->id}\n";
    // Make sure all fields are in the fieldgroup
    $changed = false;
    $titleField = $fields->get('title');
    if ($titleField && !$fg->has($titleField)) { $fg->add($titleField); $changed = true; }
    foreach (array_keys($auditFieldDefs) as $fname) {
        $f = $fields->get($fname);
        if ($f && $f->id && !$fg->has($f)) { $fg->add($f); $changed = true; }
    }
    if ($changed) { $fieldgroups->save($fg); echo "  Updated fieldgroup fields\n"; }
}

// ── STEP 3: Create audit-log template ────────────────────────────────────────
echo "\nSTEP 3: Create audit-log template\n";
$auditTpl = $templates->get('audit-log');
if ($auditTpl && $auditTpl->id) {
    echo "  Template already exists id={$auditTpl->id}\n";
} else {
    $auditTpl = new Template();
    $auditTpl->name       = 'audit-log';
    $auditTpl->fieldgroup = $fg;
    $auditTpl->noChildren = 1;    // audit entries have no children
    $auditTpl->noParents  = -1;   // allow new pages (value -1 = allow, but not from admin UI)
    $templates->save($auditTpl);
    $check = $templates->get('audit-log');
    echo "  Template " . ($check && $check->id ? "CREATED id={$check->id}" : "FAIL") . "\n";
}

// ── STEP 4: Create /audit-log/ root page ─────────────────────────────────────
echo "\nSTEP 4: Create /audit-log/ root page\n";
$auditRoot = $pages->get('/audit-log/');
if ($auditRoot && $auditRoot->id) {
    echo "  Root page already exists id={$auditRoot->id} path={$auditRoot->path}\n";
} else {
    // Need a template for the container. Use 'audit-log' template itself or basic-page.
    // We need a container template that ALLOWS children with audit-log template.
    // Check if there's a suitable container template; use admin root or site root.
    $rootPage = $pages->get('/');

    // Create a minimal container template for the audit root if needed
    // Actually, let's use a simple approach: create the audit-log page under site root
    // using the audit-log template (with noChildren temporarily disabled)

    // Temporarily allow the audit-log template to have children and be created
    $auditTpl = $templates->get('audit-log');
    $savedNoChildren = $auditTpl->noChildren;
    $auditTpl->noChildren = 0;
    $auditTpl->noParents  = 0;
    $templates->save($auditTpl);

    $auditRoot = new Page();
    $auditRoot->template = $auditTpl;
    $auditRoot->parent   = $rootPage;
    $auditRoot->name     = 'audit-log';
    $auditRoot->title    = 'Audit Log';
    $auditRoot->addStatus(Page::statusHidden); // hidden from front-end
    $pages->save($auditRoot);

    // Restore noChildren for audit entries
    $auditTpl->noChildren = 0; // audit entries need to be saved under this page
    $auditTpl->noParents  = -1;
    $templates->save($auditTpl);

    $check = $pages->get('/audit-log/');
    echo "  Root page " . ($check && $check->id ? "CREATED id={$check->id} path={$check->path}" : "FAIL") . "\n";
    $auditRoot = $check;
}

// ── STEP 5: Verify by writing a test audit entry ──────────────────────────────
echo "\nSTEP 5: Write test audit entry\n";
$auditTpl = $templates->get('audit-log');
$auditRoot = $pages->get('/audit-log/');

if ($auditRoot && $auditRoot->id && $auditTpl && $auditTpl->id) {
    $testEntry = new Page();
    $testEntry->template = $auditTpl;
    $testEntry->parent   = $auditRoot;
    $testEntry->name     = 'audit-infra-test-' . time();
    $testEntry->title    = 'INFRA TEST — ' . date('Y-m-d H:i:s');
    $testEntry->of(false);
    $testEntry->audit_entity_id       = 9999;
    $testEntry->audit_entity_template = 'admission-record';
    $testEntry->audit_entity_title    = 'Infrastructure Test';
    $testEntry->audit_action          = 1;
    $testEntry->audit_user_id         = $wire->user->id;
    $testEntry->audit_user_name       = $wire->user->name;
    $testEntry->audit_timestamp       = time();
    $testEntry->audit_ip_address      = '127.0.0.1';
    $testEntry->audit_field_changes   = json_encode(['test' => 'infrastructure verification']);
    $pages->save($testEntry);

    $check = $pages->get($testEntry->id);
    if ($check && $check->id) {
        echo "  Test entry CREATED id={$check->id}\n";
        echo "  audit_entity_id: " . $check->audit_entity_id . "\n";
        echo "  audit_action: " . $check->audit_action . "\n";
        echo "  audit_user_name: " . $check->audit_user_name . "\n";
        echo "  audit_field_changes: " . $check->audit_field_changes . "\n";
        // Clean up test entry
        $pages->delete($check, true);
        echo "  Test entry cleaned up.\n";
    } else {
        echo "  FAIL: could not re-read test entry\n";
    }
} else {
    echo "  FAIL: audit root or template missing\n";
}

// ── SUMMARY ───────────────────────────────────────────────────────────────────
echo "\n=== INFRASTRUCTURE SUMMARY ===\n";
$auditTplFinal  = $templates->get('audit-log');
$auditRootFinal = $pages->get('/audit-log/');
$allFields      = true;
foreach (array_keys($auditFieldDefs) as $fn) {
    if (!$fields->get($fn)) { $allFields = false; break; }
}
echo "  audit-log template: " . ($auditTplFinal->id ? "PASS id={$auditTplFinal->id}" : "FAIL") . "\n";
echo "  /audit-log/ page:   " . ($auditRootFinal->id ? "PASS id={$auditRootFinal->id}" : "FAIL") . "\n";
echo "  All audit fields:   " . ($allFields ? "PASS" : "FAIL") . "\n";
echo "  OVERALL: " . ($auditTplFinal->id && $auditRootFinal->id && $allFields ? "PASS" : "FAIL") . "\n";
