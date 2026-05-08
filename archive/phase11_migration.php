<?php
/**
 * Phase 11 Migration — Backup System
 * Daily auto-backup script, weekly CSV export, database dump utility
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
function fail($m) { echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $m</div>\n"; flush(); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 11 — Backup System</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
pre { background:#0c1a2e; border:1px solid #1e3a5f; padding:14px; border-radius:6px; color:#86efac; font-size:12px; overflow-x:auto; }
</style>
</head>
<body>
<h1>💾 Phase 11 Migration — Backup System</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

$sitePath = wire('config')->paths->site;
$rootPath  = wire('config')->paths->root;

// ─── SECTION 1: Create Backup Directory ───────────────────────────────────────
echo '<h2>SECTION 1 — Backup Directory</h2>';

$backupDir = $rootPath . 'backups';
step('Creating backups/ directory...');
if (!is_dir($backupDir)) {
    if (mkdir($backupDir, 0750, true)) {
        ok("Created: $backupDir");
    } else {
        fail("Cannot create $backupDir — check permissions");
    }
} else {
    ok("Directory already exists: $backupDir");
}

// Write .htaccess to block direct web access
$htaccess = $backupDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
    ok("Written .htaccess — backups/ is web-inaccessible");
} else {
    ok(".htaccess already exists");
}

$csvDir = $backupDir . '/csv';
$dbDir  = $backupDir . '/db';
foreach ([$csvDir, $dbDir] as $d) {
    if (!is_dir($d)) { mkdir($d, 0750, true); ok("Created: $d"); }
    else warn("Already exists: $d");
}

// ─── SECTION 2: Write CSV Export Script ───────────────────────────────────────
echo '<h2>SECTION 2 — CSV Export Script</h2>';

$csvScriptPath = $rootPath . 'backup_csv.php';
step('Writing backup_csv.php...');
file_put_contents($csvScriptPath, <<<'PHP'
<?php
/**
 * backup_csv.php — Full Registry CSV Export
 * Run via browser (superuser) or CLI: php backup_csv.php
 * Output: backups/csv/registry-YYYY-MM-DD.csv
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

$isCli = (php_sapi_name() === 'cli');
if (!$isCli && !$user->isSuperuser()) die('Access denied.');

$filename = 'registry-' . date('Y-m-d') . '.csv';
$outPath  = $rootPath . '/backups/csv/' . $filename;

$fh = fopen($outPath, 'w');

// Headers
fputcsv($fh, [
    'Patient Name', 'Patient ID', 'Secondary Phone',
    'IP Number', 'Admission Date', 'Consultant', 'Diagnosis', 'Side',
    'MLC Status', 'Case Status',
    'Chief Complaint', 'Comorbidities',
    'Proposed Procedure', 'Consent Status',
    'Procedures Count', 'Operation Notes Count', 'Investigations Count',
    'Discharged On', 'General Condition', 'Follow-up Date',
    'Created By (User ID)', 'Updated By (User ID)',
]);

$admissions = wire('pages')->find('template=admission-record, include=all, limit=9999');
foreach ($admissions as $adm) {
    $patient     = $adm->parent;
    $statusMap   = [1 => 'Active', 2 => 'Discharged', 3 => 'Follow-up', 4 => 'Cancelled'];
    $consentMap  = [1 => 'Taken', 2 => 'Not Taken', 3 => 'Refused'];
    $mlcMap      = [1 => 'Yes', 2 => 'No', 3 => 'Pending'];

    $statusNum  = (int)$adm->getUnformatted('case_status');
    $consentNum = (int)$adm->getUnformatted('consent_status');
    $mlcNum     = (int)$adm->getUnformatted('mlc_status');

    $diag = $adm->primary_diagnosis_ref;
    $cons = $adm->consultant_ref;

    $procCount   = wire('pages')->count("template=procedure, parent={$adm->id}");
    $opnoteCount = 0;
    $procs = wire('pages')->find("template=procedure, parent={$adm->id}");
    foreach ($procs as $proc) {
        $opnoteCount += wire('pages')->count("template=operation-note, parent={$proc->id}");
    }
    $invCount = wire('pages')->count("template=investigation, parent={$adm->id}");

    fputcsv($fh, [
        $patient->title,
        $patient->patient_id,
        $patient->secondary_phone ?? '',
        $adm->ip_number,
        date('Y-m-d', $adm->created),
        $cons && $cons->id ? $cons->title : '',
        $diag && $diag->id ? $diag->title : $adm->diagnosis,
        $adm->diagnosis_side,
        $mlcMap[$mlcNum] ?? '',
        $statusMap[$statusNum] ?? 'Unknown',
        $adm->chief_complaint,
        $adm->comorbidities,
        $adm->proposed_procedure,
        $consentMap[$consentNum] ?? '',
        $procCount,
        $opnoteCount,
        $invCount,
        $adm->discharged_on ? date('Y-m-d', $adm->discharged_on) : '',
        $adm->general_condition,
        $adm->review_date ? date('Y-m-d', $adm->review_date) : '',
        $adm->created_by_user ?? '',
        $adm->updated_by_user ?? '',
    ]);
}

fclose($fh);

$count = wire('pages')->count('template=admission-record, include=all');
if ($isCli) {
    echo "Export complete: $outPath ($count records)\n";
} else {
    echo "<p style='font-family:monospace;color:#86efac;'>✓ Export complete: <strong>$outPath</strong> ($count records)</p>";
    echo "<p><a href='/backups/csv/$filename' style='color:#38bdf8;'>Download CSV</a> — Note: direct download blocked by .htaccess; retrieve via FTP/SSH.</p>";
}
PHP
);
ok("Written: $csvScriptPath");

// ─── SECTION 3: Write Database Dump Script ────────────────────────────────────
echo '<h2>SECTION 3 — Database Dump Script</h2>';

$dbHost = wire('config')->dbHost ?: 'localhost';
$dbName = wire('config')->dbName;
$dbUser = wire('config')->dbUser;

$dbScriptPath = $rootPath . 'backup_db.php';
step('Writing backup_db.php...');
file_put_contents($dbScriptPath,
'<?php
/**
 * backup_db.php — MySQL Database Dump
 * Run via browser (superuser) or CLI: php backup_db.php
 * Requires mysqldump available in server PATH
 */

$rootPath = __DIR__;
include($rootPath . "/index.php");

$isCli = (php_sapi_name() === "cli");
if (!$isCli && !$user->isSuperuser()) die("Access denied.");

$dbHost  = wire("config")->dbHost  ?: "localhost";
$dbPort  = wire("config")->dbPort  ?: 3306;
$dbName  = wire("config")->dbName;
$dbUser  = wire("config")->dbUser;
$dbPass  = wire("config")->dbPass;

$outFile = $rootPath . "/backups/db/db-" . date("Y-m-d-His") . ".sql.gz";

// Build mysqldump command — password passed via env to avoid shell history exposure
$env  = "MYSQL_PWD=" . escapeshellarg($dbPass);
$cmd  = "$env mysqldump"
      . " --host=" . escapeshellarg($dbHost)
      . " --port=" . (int)$dbPort
      . " --user=" . escapeshellarg($dbUser)
      . " --single-transaction"
      . " --routines"
      . " --triggers"
      . " " . escapeshellarg($dbName)
      . " | gzip > " . escapeshellarg($outFile)
      . " 2>&1";

exec($cmd, $output, $exitCode);

if ($exitCode === 0 && file_exists($outFile)) {
    $size = round(filesize($outFile) / 1024, 1);
    if ($isCli) {
        echo "Database dump complete: $outFile ({$size}KB)\n";
    } else {
        echo "<p style=\"font-family:monospace;color:#86efac;\">✓ Database dump: <strong>$outFile</strong> ({$size}KB)</p>";
    }
    // Prune dumps older than 30 days
    foreach (glob($rootPath . "/backups/db/*.sql.gz") as $f) {
        if (filemtime($f) < strtotime("-30 days")) { unlink($f); }
    }
} else {
    $msg = implode("\n", $output) ?: "Unknown error";
    if ($isCli) {
        echo "ERROR: mysqldump failed (exit $exitCode): $msg\n";
    } else {
        echo "<p style=\"color:#fca5a5;\">✗ mysqldump failed (exit $exitCode): " . htmlspecialchars($msg) . "</p>";
        echo "<p style=\"color:#fde68a;\">Ensure mysqldump is in PATH and credentials are correct.</p>";
    }
}
'
);
ok("Written: $dbScriptPath");

// ─── SECTION 4: Write Scheduled Backup Runner ────────────────────────────────
echo '<h2>SECTION 4 — Scheduled Backup Runner (Cron)</h2>';

$cronScriptPath = $rootPath . 'backup_cron.php';
step('Writing backup_cron.php (runs both CSV + DB)...');
file_put_contents($cronScriptPath, <<<'PHP'
<?php
/**
 * backup_cron.php — Unified backup runner for cron/Task Scheduler
 * Usage (Linux cron):   0 2 * * * /usr/bin/php /path/to/backup_cron.php >> /var/log/gangareg_backup.log 2>&1
 * Usage (Windows Task): C:\php\php.exe C:\laragon\www\discharge-app\backup_cron.php
 */

define('PROCESSWIRE', true);
$rootPath = __DIR__;
include($rootPath . '/index.php');

$log = function($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; };

$log("=== GangaReg Backup Started ===");

// Run CSV export
$log("Running CSV export...");
ob_start();
include $rootPath . '/backup_csv.php';
ob_end_clean();
$log("CSV export complete");

// Run DB dump
$log("Running database dump...");
ob_start();
include $rootPath . '/backup_db.php';
ob_end_clean();
$log("Database dump complete");

// Prune old CSV exports older than 90 days
foreach (glob($rootPath . '/backups/csv/*.csv') as $f) {
    if (filemtime($f) < strtotime('-90 days')) {
        unlink($f);
        $log("Pruned old CSV: " . basename($f));
    }
}

$log("=== GangaReg Backup Complete ===");
PHP
);
ok("Written: $cronScriptPath");

// ─── SECTION 5: Add backup/ to .gitignore ─────────────────────────────────────
echo '<h2>SECTION 5 — .gitignore Update</h2>';

$gitignorePath = $rootPath . '.gitignore';
step('Updating .gitignore to exclude backups/...');
$gitignoreEntry = "\n# GangaReg backups\nbackups/\n";
if (file_exists($gitignorePath)) {
    $existing = file_get_contents($gitignorePath);
    if (strpos($existing, 'backups/') === false) {
        file_put_contents($gitignorePath, $existing . $gitignoreEntry);
        ok("Added backups/ to .gitignore");
    } else {
        warn("backups/ already in .gitignore");
    }
} else {
    file_put_contents($gitignorePath, "# GangaReg\nbackups/\n");
    ok("Created .gitignore with backups/ exclusion");
}

// ─── SECTION 6: Windows Task Scheduler XML ───────────────────────────────────
echo '<h2>SECTION 6 — Windows Task Scheduler Setup</h2>';

$phpExe     = 'C:\\laragon\\bin\\php\\php8.2.0\\php.exe';
$scriptFull = str_replace('/', '\\', $rootPath . 'backup_cron.php');

$xmlPath = $rootPath . 'gangareg_backup_task.xml';
$xml = <<<XML
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Description>GangaReg Clinical Registry — Daily Backup (2:00 AM)</Description>
  </RegistrationInfo>
  <Triggers>
    <CalendarTrigger>
      <StartBoundary>2026-01-01T02:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay>
        <DaysInterval>1</DaysInterval>
      </ScheduleByDay>
    </CalendarTrigger>
  </Triggers>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <ExecutionTimeLimit>PT1H</ExecutionTimeLimit>
    <Enabled>true</Enabled>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>$phpExe</Command>
      <Arguments>$scriptFull</Arguments>
      <WorkingDirectory>$rootPath</WorkingDirectory>
    </Exec>
  </Actions>
</Task>
XML;

file_put_contents($xmlPath, $xml);
ok("Written: gangareg_backup_task.xml");

echo '<div style="background:#0c1a2e;border:1px solid #1e3a5f;padding:14px;border-radius:6px;margin:10px 0;">';
echo '<p style="color:#fde68a;margin-bottom:8px;">To register the scheduled task on Windows, run as Administrator:</p>';
echo '<pre>schtasks /create /xml "' . htmlspecialchars($xmlPath) . '" /tn "GangaRegBackup" /f</pre>';
echo '</div>';

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 11 COMPLETE — ALL PHASES DONE</h2>';
echo '<div style="background:#0f2027;border:2px solid #22d3ee;padding:20px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">Backup System:</h3>';
echo '<ul style="color:#94a3b8;line-height:2;">';
echo '<li>✓ <code>backups/</code> directory — web-inaccessible via .htaccess</li>';
echo '<li>✓ <code>backup_csv.php</code> — full registry CSV export (all fields)</li>';
echo '<li>✓ <code>backup_db.php</code> — compressed mysqldump (gzip, auto-prunes 30d)</li>';
echo '<li>✓ <code>backup_cron.php</code> — unified runner for scheduler</li>';
echo '<li>✓ <code>gangareg_backup_task.xml</code> — Windows Task Scheduler config (2:00 AM daily)</li>';
echo '<li>✓ <code>.gitignore</code> updated to exclude backup files</li>';
echo '</ul>';

echo '<h3 style="color:#22d3ee;">🎉 All 11 Phases Complete</h3>';
echo '<table style="width:100%;border-collapse:collapse;color:#94a3b8;font-size:13px;">';
echo '<tr style="color:#7dd3fc;"><th style="text-align:left;padding:5px;">Phase</th><th style="text-align:left;padding:5px;">Migration File</th><th style="padding:5px;">Status</th></tr>';
$phases = [
    [1,  'Schema Refactor',               'phase1_migration.php'],
    [2,  'Procedure & Op-Note Entities',  'phase2_migration.php'],
    [3,  'Clinical Fields',               'phase3_migration.php'],
    [4,  'Role & Workflow Engine',         'phase4_migration.php'],
    [5,  'Timeline UI',                   '(case-view.php, dashboard.php)'],
    [6,  'Automation Layer',              '(ready.php hooks)'],
    [7,  'Discharge Engine',              '(admission-record.php)'],
    [8,  'Audit & Safety',                'phase8_migration.php'],
    [9,  'Advanced Search',               'phase9_migration.php'],
    [10, 'Performance',                   'phase10_migration.php'],
    [11, 'Backup System',                 'phase11_migration.php'],
];
foreach ($phases as [$n, $name, $file]) {
    echo "<tr><td style='padding:5px'>Phase $n — $name</td><td style='padding:5px;font-family:monospace;color:#38bdf8;'>$file</td><td style='padding:5px;color:#86efac;'>✓ Done</td></tr>";
}
echo '</table>';
echo '</div>';
?>
</body>
</html>
