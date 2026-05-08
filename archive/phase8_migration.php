<?php
/**
 * Phase 8 Migration — Audit & Safety
 * Creates audit-log template + parent page for granular field-level change tracking
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
function fail($m) { echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $m</div>\n"; flush(); }

function createField8($name, $type, $label, $cfg = []) {
    $f = wire('fields')->get($name);
    if ($f && $f->id) { warn("Field '$name' exists — skipping"); return $f; }
    $f = new Field();
    $f->type  = wire('modules')->get($type);
    $f->name  = $name;
    $f->label = $label;
    foreach ($cfg as $k => $v) $f->$k = $v;
    wire('fields')->save($f);
    ok("Created field: $name ($type)");
    return $f;
}

function addFieldToTemplate8($fieldName, $templateName) {
    $t = wire('templates')->get($templateName);
    $f = wire('fields')->get($fieldName);
    if (!$t || !$f) { fail("Cannot attach '$fieldName' to '$templateName' — not found"); return; }
    $fg = $t->fieldgroup;
    if ($fg->has($f)) { warn("'$fieldName' already in '$templateName'"); return; }
    $fg->add($f);
    $fg->save();
    ok("Attached '$fieldName' → '$templateName'");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 8 — Audit &amp; Safety</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>🔍 Phase 8 Migration — Audit &amp; Safety</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

// ─── SECTION 1: Create Audit Log Fields ──────────────────────────────────────
echo '<h2>SECTION 1 — Audit Log Fields</h2>';

createField8('audit_entity_id',       'FieldtypeInteger', 'Entity Page ID',    ['defaultValue' => 0]);
createField8('audit_entity_template', 'FieldtypeText',    'Entity Template',   ['maxlength' => 64]);
createField8('audit_entity_title',    'FieldtypeText',    'Entity Title',      ['maxlength' => 255]);
createField8('audit_user_id',         'FieldtypeInteger', 'Acting User ID',    ['defaultValue' => 0]);
createField8('audit_user_name',       'FieldtypeText',    'Acting Username',   ['maxlength' => 64]);
createField8('audit_ip_address',      'FieldtypeText',    'Client IP Address', ['maxlength' => 45]);
createField8('audit_timestamp',       'FieldtypeInteger', 'Audit Timestamp',   ['defaultValue' => 0]);
createField8('audit_field_changes',   'FieldtypeTextarea','Field Changes (JSON)', [
    'rows'      => 8,
    'collapsed' => Inputfield::collapsedYes,
]);

// audit_action: Options field  1=Created 2=Updated 3=Cancelled 4=Restored 5=Discharged
step('Creating audit_action Options field...');
$af = wire('fields')->get('audit_action');
if (!$af || !$af->id) {
    $af = new Field();
    $af->type  = wire('modules')->get('FieldtypeOptions');
    $af->name  = 'audit_action';
    $af->label = 'Audit Action';
    wire('fields')->save($af);

    $manager = new \ProcessWire\SelectableOptionManager();
    $optStr   = "1=Created\n2=Updated\n3=Cancelled\n4=Restored\n5=Discharged";
    $manager->setOptionsString($af, $optStr, false);
    wire('fields')->save($af);
    ok("Created audit_action Options field");
} else {
    warn("audit_action already exists — skipping");
}

// ─── SECTION 2: Create Audit Log Template ────────────────────────────────────
echo '<h2>SECTION 2 — Audit Log Template</h2>';

step('Creating audit-log template...');
$tAudit = wire('templates')->get('audit-log');
if (!$tAudit || !$tAudit->id) {
    $fg = new Fieldgroup();
    $fg->name = 'audit-log';
    $fg->add(wire('fields')->get('title'));
    $fg->save();

    $tAudit = new Template();
    $tAudit->name       = 'audit-log';
    $tAudit->label      = 'Audit Log Entry';
    $tAudit->fieldgroup = $fg;
    $tAudit->noChildren = 1;
    $tAudit->noParents  = 0;
    $tAudit->useRoles   = 1;
    wire('templates')->save($tAudit);
    ok("Created template: audit-log");
} else {
    warn("Template 'audit-log' already exists — skipping");
    $tAudit = wire('templates')->get('audit-log');
}

$auditFields = [
    'audit_entity_id', 'audit_entity_template', 'audit_entity_title',
    'audit_action', 'audit_user_id', 'audit_user_name',
    'audit_ip_address', 'audit_timestamp', 'audit_field_changes',
];
foreach ($auditFields as $fn) {
    addFieldToTemplate8($fn, 'audit-log');
}

// ─── SECTION 3: Create Audit Log Container Page ──────────────────────────────
echo '<h2>SECTION 3 — Create Audit Log Container</h2>';

step('Creating template for audit-log-container...');
$tCont = wire('templates')->get('audit-log-container');
if (!$tCont || !$tCont->id) {
    $fgC = new Fieldgroup();
    $fgC->name = 'audit-log-container';
    $fgC->add(wire('fields')->get('title'));
    $fgC->save();

    $tCont = new Template();
    $tCont->name       = 'audit-log-container';
    $tCont->label      = 'Audit Log Container';
    $tCont->fieldgroup = $fgC;
    $tCont->childTemplates = ['audit-log'];
    $tCont->noChildren  = 0;
    $tCont->noParents   = -1; // root only
    wire('templates')->save($tCont);
    ok("Created template: audit-log-container");
} else {
    warn("Template 'audit-log-container' already exists — skipping");
}

// Set allowed parents for audit-log
$tAudit = wire('templates')->get('audit-log');
if ($tAudit) {
    $tAudit->parentTemplates = ['audit-log-container'];
    wire('templates')->save($tAudit);
    ok("audit-log: parent restricted to audit-log-container");
}

step('Creating /audit-log/ container page...');
$auditRoot = wire('pages')->get('/audit-log/');
if (!$auditRoot || !$auditRoot->id) {
    $auditRoot = new Page();
    $auditRoot->template = wire('templates')->get('audit-log-container');
    $auditRoot->parent   = wire('pages')->get('/');
    $auditRoot->name     = 'audit-log';
    $auditRoot->title    = 'Audit Log';
    $auditRoot->addStatus(Page::statusHidden); // hidden from public
    wire('pages')->save($auditRoot);
    ok("Created /audit-log/ container page (hidden)");
} else {
    warn("/audit-log/ already exists — skipping");
}

// ─── SECTION 4: Grant Superuser-only Access to Audit Template ────────────────
echo '<h2>SECTION 4 — Lock Down Audit Log Access</h2>';

step('Restricting audit-log template to superuser only...');
$tAudit = wire('templates')->get('audit-log');
if ($tAudit) {
    $tAudit->useRoles = 1;
    // Only superuser can create/edit audit logs — no role grants needed
    // (superuser bypasses all role checks in ProcessWire)
    wire('templates')->save($tAudit);
    ok("audit-log template locked — superuser access only");
}

$tCont = wire('templates')->get('audit-log-container');
if ($tCont) {
    $tCont->useRoles = 1;
    wire('templates')->save($tCont);
    ok("audit-log-container template locked — superuser access only");
}

// ─── SECTION 5: Verify Soft-Delete Setup ─────────────────────────────────────
echo '<h2>SECTION 5 — Verify Soft-Delete System</h2>';

step('Verifying case_status options include Cancelled (4)...');
$csField = wire('fields')->get('case_status');
if ($csField && $csField->id) {
    $manager = new \ProcessWire\SelectableOptionManager();
    $options  = $manager->getOptions($csField);
    $hasCancelled = false;
    foreach ($options as $opt) {
        if ((int)$opt->value === 4) { $hasCancelled = true; break; }
    }
    if ($hasCancelled) {
        ok("case_status option 4=Cancelled confirmed");
    } else {
        warn("case_status option 4=Cancelled missing — adding now");
        $existing = '';
        foreach ($options as $opt) $existing .= "{$opt->value}={$opt->title}\n";
        $existing .= "4=Cancelled";
        $manager->setOptionsString($csField, trim($existing), false);
        wire('fields')->save($csField);
        ok("Added Cancelled option to case_status");
    }
} else {
    fail("case_status field not found — run phase1_migration.php first");
}

step('Verifying discharged_on field exists on admission-record...');
$doField = wire('fields')->get('discharged_on');
if ($doField && $doField->id) {
    ok("discharged_on field confirmed");
} else {
    // Create it if missing
    $doField = new Field();
    $doField->type  = wire('modules')->get('FieldtypeInteger');
    $doField->name  = 'discharged_on';
    $doField->label = 'Discharged On (Unix Timestamp)';
    wire('fields')->save($doField);

    $tAdm = wire('templates')->get('admission-record');
    if ($tAdm) {
        $fg = $tAdm->fieldgroup;
        $fg->add($doField);
        $fg->save();
        ok("Created and attached discharged_on to admission-record");
    }
}

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 8 SCHEMA COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">What was set up:</h3>';
echo '<ul style="color:#94a3b8;line-height:2;">';
echo '<li>✓ <strong style="color:#7dd3fc;">audit-log</strong> template — stores each save/cancel/discharge event</li>';
echo '<li>✓ <strong style="color:#7dd3fc;">/audit-log/</strong> container page — hidden from public, superuser-only</li>';
echo '<li>✓ Field-level change JSON stored in <code>audit_field_changes</code></li>';
echo '<li>✓ Soft-delete (Cancelled=4) on case_status confirmed</li>';
echo '<li>✓ discharged_on timestamp field confirmed</li>';
echo '</ul>';
echo '<p style="color:#fde68a;">⚠ <strong>IMPORTANT:</strong> Update <code>site/ready.php</code> with the Phase 8 audit hooks to activate field-level change logging. See next step.</p>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase9_migration.php to build the Advanced Search System</p>';
echo '</div>';
?>
</body>
</html>
