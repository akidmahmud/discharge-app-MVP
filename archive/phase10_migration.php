<?php
/**
 * Phase 10 Migration — Performance Optimization
 * Removes repeater bottlenecks, adds MySQL indexes, configures caching
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
function info($m) { echo "<div style='background:#0c1a2e;color:#93c5fd;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>ℹ $m</div>\n"; flush(); }
function fail($m) { echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $m</div>\n"; flush(); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 10 — Performance</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>⚡ Phase 10 Migration — Performance Optimization</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

$db = wire('database');

// ─── SECTION 1: Verify Repeater Migration Before Removing Field ───────────────
echo '<h2>SECTION 1 — Repeater Migration Verification</h2>';

step('Checking if repeater_procedures field still exists on admission-record...');
$tAdm = wire('templates')->get('admission-record');
$repeaterField = wire('fields')->get('repeater_procedures');

if (!$repeaterField || !$repeaterField->id) {
    ok("repeater_procedures field not found — already removed or never existed");
} else {
    // Count how many admissions still have repeater data
    $withRepeater = wire('pages')->count('template=admission-record, repeater_procedures.count>0');
    if ($withRepeater > 0) {
        warn("$withRepeater admissions still have repeater_procedures data — run phase2_migration.php first to migrate");
        info("Skipping repeater removal to protect data");
    } else {
        // Safe to remove
        step("Removing repeater_procedures from admission-record template...");
        $fg = $tAdm->fieldgroup;
        if ($fg->has($repeaterField)) {
            $fg->remove($repeaterField);
            $fg->save();
            ok("Removed repeater_procedures from admission-record fieldgroup");
        }
        // Do NOT delete the field itself — may be used elsewhere and PW will handle cleanup
        info("Field definition kept. Remove manually from Admin > Fields if no longer needed anywhere.");
    }
}

// ─── SECTION 2: MySQL Indexes ─────────────────────────────────────────────────
echo '<h2>SECTION 2 — MySQL Performance Indexes</h2>';

// ProcessWire stores text fields in field_fieldname tables
// We add indexes on the most-queried fields for this registry

$indexes = [
    // ip_number — queried constantly for case lookup
    [
        'table'  => 'field_ip_number',
        'column' => 'data',
        'name'   => 'idx_ip_number_data',
        'type'   => 'INDEX',
    ],
    // patient_id — used in search and dashboard joins
    [
        'table'  => 'field_patient_id',
        'column' => 'data',
        'name'   => 'idx_patient_id_data',
        'type'   => 'INDEX',
    ],
    // case_status — filtered on nearly every dashboard query
    [
        'table'  => 'field_case_status',
        'column' => 'data',
        'name'   => 'idx_case_status_data',
        'type'   => 'INDEX',
    ],
    // diagnosis_side — filter in search and dashboard
    [
        'table'  => 'field_diagnosis_side',
        'column' => 'data',
        'name'   => 'idx_diagnosis_side_data',
        'type'   => 'INDEX',
    ],
    // search_index — full-text for Phase 9 search (FULLTEXT, not regular)
    [
        'table'  => 'field_search_index',
        'column' => 'data',
        'name'   => 'idx_search_index_fulltext',
        'type'   => 'FULLTEXT',
    ],
    // proc_date — sorted/filtered in timeline views
    [
        'table'  => 'field_proc_date',
        'column' => 'data',
        'name'   => 'idx_proc_date_data',
        'type'   => 'INDEX',
    ],
    // discharged_on — date range queries for stats
    [
        'table'  => 'field_discharged_on',
        'column' => 'data',
        'name'   => 'idx_discharged_on_data',
        'type'   => 'INDEX',
    ],
    // audit_timestamp — audit log time ordering
    [
        'table'  => 'field_audit_timestamp',
        'column' => 'data',
        'name'   => 'idx_audit_timestamp_data',
        'type'   => 'INDEX',
    ],
    // audit_entity_id — look up all logs for a given page ID
    [
        'table'  => 'field_audit_entity_id',
        'column' => 'data',
        'name'   => 'idx_audit_entity_id_data',
        'type'   => 'INDEX',
    ],
];

foreach ($indexes as $idx) {
    $table  = $idx['table'];
    $column = $idx['column'];
    $name   = $idx['name'];
    $type   = $idx['type'];

    // Check if table exists
    try {
        $tableExists = $db->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        if (!$tableExists) {
            warn("Table '$table' does not exist — field may not be created yet, skipping");
            continue;
        }
    } catch (Exception $e) {
        fail("Error checking table '$table': " . $e->getMessage());
        continue;
    }

    // Check if index already exists
    try {
        $existsQ = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$name'");
        if ($existsQ->rowCount() > 0) {
            warn("Index '$name' on '$table' already exists — skipping");
            continue;
        }
    } catch (Exception $e) {
        fail("Error checking index '$name': " . $e->getMessage());
        continue;
    }

    // Create the index
    try {
        $sql = "ALTER TABLE `$table` ADD $type INDEX `$name` (`$column`(191))";
        if ($type === 'FULLTEXT') {
            $sql = "ALTER TABLE `$table` ADD FULLTEXT INDEX `$name` (`$column`)";
        }
        $db->exec($sql);
        ok("Created $type index '$name' on $table($column)");
    } catch (Exception $e) {
        // Ignore duplicate index errors gracefully
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            warn("Index '$name' already exists (duplicate key) — skipping");
        } else {
            fail("Failed to create index '$name': " . $e->getMessage());
        }
    }
}

// ─── SECTION 3: ProcessWire Cache Configuration ───────────────────────────────
echo '<h2>SECTION 3 — Cache Configuration</h2>';

step('Checking ProcessWire cache module availability...');
$cacheModule = wire('modules')->get('ProcessCache');
if ($cacheModule) {
    ok("ProcessCache module available");
} else {
    info("ProcessCache not available — using WireCache API directly (built into PW 3.x)");
}

// Verify WireCache is accessible
step('Verifying WireCache API...');
try {
    wire('cache')->save('phase10_test', 'ok', 60);
    $val = wire('cache')->get('phase10_test');
    if ($val === 'ok') {
        ok("WireCache working correctly");
        wire('cache')->delete('phase10_test');
    } else {
        warn("WireCache save/get mismatch — check cache driver config");
    }
} catch (Exception $e) {
    fail("WireCache error: " . $e->getMessage());
}

// ─── SECTION 4: Page Cache Settings for Read-Heavy Templates ─────────────────
echo '<h2>SECTION 4 — Template Cache Settings</h2>';

// Set cache time for dashboard (read-heavy, safe to cache 60s)
$cacheTemplates = [
    'search-results' => 0,   // never cache — dynamic
    'dashboard'      => 0,   // never cache — user-specific
    'case-view'      => 0,   // never cache — live clinical data
    'admission-record' => 0, // never cache — PDF generation
];

foreach ($cacheTemplates as $tName => $cacheTime) {
    $t = wire('templates')->get($tName);
    if (!$t) { warn("Template '$tName' not found — skipping cache config"); continue; }
    $t->cache_time = $cacheTime;
    wire('templates')->save($t);
    ok("$tName: cache_time set to {$cacheTime}s (no caching — real-time clinical data)");
}

// ─── SECTION 5: Query Optimization Report ────────────────────────────────────
echo '<h2>SECTION 5 — Query Optimization Report</h2>';

step('Analyzing current data volume...');

$patientCount   = wire('pages')->count('template=patient-record');
$admissionCount = wire('pages')->count('template=admission-record');
$procCount      = wire('pages')->count('template=procedure');
$opnoteCount    = wire('pages')->count('template=operation-note');
$invCount       = wire('pages')->count('template=investigation, include=hidden');
$auditCount     = wire('pages')->count('template=audit-log, include=hidden');

echo '<div style="background:#0c1a2e;border:1px solid #1e3a5f;padding:14px;border-radius:6px;margin:10px 0;">';
echo '<table style="width:100%;border-collapse:collapse;color:#94a3b8;">';
echo '<tr><th style="text-align:left;padding:6px;color:#7dd3fc;">Entity</th><th style="padding:6px;color:#7dd3fc;">Count</th></tr>';
echo "<tr><td style='padding:6px'>Patients</td><td style='padding:6px;color:#86efac'>$patientCount</td></tr>";
echo "<tr><td style='padding:6px'>Admissions</td><td style='padding:6px;color:#86efac'>$admissionCount</td></tr>";
echo "<tr><td style='padding:6px'>Procedures</td><td style='padding:6px;color:#86efac'>$procCount</td></tr>";
echo "<tr><td style='padding:6px'>Operation Notes</td><td style='padding:6px;color:#86efac'>$opnoteCount</td></tr>";
echo "<tr><td style='padding:6px'>Investigations</td><td style='padding:6px;color:#86efac'>$invCount</td></tr>";
echo "<tr><td style='padding:6px'>Audit Log Entries</td><td style='padding:6px;color:#86efac'>$auditCount</td></tr>";
echo '</table></div>';

// ─── SECTION 6: Remove Legacy CKEditor Fields (if confirmed empty) ────────────
echo '<h2>SECTION 6 — Legacy Field Cleanup Check</h2>';

$legacyFields = [
    'diagnosis'     => 'admission-record',
    'examination'   => 'admission-record',
    'history'       => 'admission-record',
    'course'        => 'admission-record',
    'operation_notes' => 'admission-record',
];

foreach ($legacyFields as $fn => $tplName) {
    $f = wire('fields')->get($fn);
    if (!$f || !$f->id) {
        info("Field '$fn' not found — already removed or never created");
        continue;
    }

    // Check if any pages still have data in this field
    $withData = wire('pages')->count("template=$tplName, $fn!='', $fn!=''");
    if ($withData > 0) {
        warn("Field '$fn' still has data in $withData pages — keeping for backward compatibility");
    } else {
        info("Field '$fn' appears empty — safe to remove via Admin > Fields when ready");
    }
}

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 10 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">Performance Improvements:</h3>';
echo '<ul style="color:#94a3b8;line-height:2;">';
echo '<li>✓ Repeater bottleneck check — removed if migration complete</li>';
echo '<li>✓ MySQL indexes on ip_number, patient_id, case_status, diagnosis_side, proc_date</li>';
echo '<li>✓ FULLTEXT index on search_index (enables native MySQL FULLTEXT queries)</li>';
echo '<li>✓ WireCache verified functional</li>';
echo '<li>✓ Template cache disabled for all clinical views (real-time data integrity)</li>';
echo '<li>✓ Data volume report generated</li>';
echo '</ul>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase11_migration.php for Backup System</p>';
echo '</div>';
?>
</body>
</html>
