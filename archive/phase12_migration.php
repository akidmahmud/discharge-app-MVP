<?php namespace ProcessWire;

/**
 * Phase 12 Migration — Hospital Course Restructure
 *
 * WHAT THIS DOES:
 * 1. Creates fields: hce_date, hce_type (Options), hce_note
 * 2. Creates template: hospital-course-entry
 * 3. Attaches fields to template
 * 4. Migrates existing post_op_course text blobs into child pages
 * 5. Clears post_op_course after confirmed migration
 *
 * Run via browser: http://discharge-app.test/phase12_migration.php
 * Requires superuser login.
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) {
    die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');
}

// ─── Output helpers ───────────────────────────────────────────────────────────
function step12($msg) {
    echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>&#9881; $msg</div>\n";
    flush();
}
function ok12($msg) {
    echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10003; $msg</div>\n";
    flush();
}
function warn12($msg) {
    echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9888; $msg</div>\n";
    flush();
}
function fail12($msg) {
    echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#10007; $msg</div>\n";
    flush();
}
function info12($msg) {
    echo "<div style='background:#0c1a2e;color:#93c5fd;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>&#9432; $msg</div>\n";
    flush();
}

// ─── Helper: create field if not exists ──────────────────────────────────────
function createField12($name, $type, $label, $cfg = []) {
    $f = wire('fields')->get($name);
    if ($f && $f->id) {
        warn12("Field '$name' already exists — skipping");
        return $f;
    }
    $f        = new Field();
    $f->type  = wire('modules')->get($type);
    $f->name  = $name;
    $f->label = $label;
    foreach ($cfg as $k => $v) {
        $f->$k = $v;
    }
    wire('fields')->save($f);
    ok12("Created field: $name ($type)");
    return $f;
}

// ─── Helper: attach field to template ────────────────────────────────────────
function attachField12($fieldName, $templateName) {
    $t = wire('templates')->get($templateName);
    $f = wire('fields')->get($fieldName);
    if (!$t || !$f) {
        fail12("Cannot attach '$fieldName' to '$templateName' — one or both not found");
        return;
    }
    $fg = $t->fieldgroup;
    if ($fg->has($f)) {
        warn12("'$fieldName' already attached to '$templateName'");
        return;
    }
    $fg->add($f);
    $fg->save();
    ok12("Attached '$fieldName' -> '$templateName'");
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 12 — Hospital Course Restructure</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; max-width:900px; }
h1   { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2   { color:#7dd3fc; margin-top:30px; }
pre  { background:#0c1a2e; border:1px solid #1e3a5f; padding:14px; border-radius:6px; color:#86efac; font-size:12px; overflow-x:auto; }
</style>
</head>
<body>
<h1>&#127973; Phase 12 Migration — Hospital Course Restructure</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?> &nbsp;|&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<?php

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — Create Fields
// ─────────────────────────────────────────────────────────────────────────────
echo '<h2>SECTION 1 — Fields</h2>';

// hce_date — the calendar date of the course entry
createField12('hce_date', 'FieldtypeDatetime', 'Entry Date', [
    'dateInputFormat' => 'Y-m-d',
    'timeInputFormat' => '',
    'datepicker'      => 3,
]);

// hce_type — Options field: Routine, Important, Discharge
step12('Creating hce_type Options field...');
$hceTypeField = wire('fields')->get('hce_type');
if (!$hceTypeField || !$hceTypeField->id) {
    $hceTypeField        = new Field();
    $hceTypeField->type  = wire('modules')->get('FieldtypeOptions');
    $hceTypeField->name  = 'hce_type';
    $hceTypeField->label = 'Entry Type';
    wire('fields')->save($hceTypeField);

    $manager = new \ProcessWire\SelectableOptionManager();
    $manager->setOptionsString($hceTypeField, "1=Routine\n2=Important\n3=Discharge", false);
    wire('fields')->save($hceTypeField);
    ok12('Created hce_type Options field with 3 options: Routine, Important, Discharge');
} else {
    warn12('hce_type already exists — skipping');
}

// hce_note — the clinical note body
createField12('hce_note', 'FieldtypeTextarea', 'Clinical Note', [
    'rows'      => 4,
    'inputType' => 'textarea',
]);

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — Create Template: hospital-course-entry
// ─────────────────────────────────────────────────────────────────────────────
echo '<h2>SECTION 2 — Template</h2>';

step12('Creating hospital-course-entry template...');
$tHce = wire('templates')->get('hospital-course-entry');
if (!$tHce || !$tHce->id) {
    $fg       = new Fieldgroup();
    $fg->name = 'hospital-course-entry';
    $fg->add(wire('fields')->get('title'));
    $fg->save();

    $tHce             = new Template();
    $tHce->name       = 'hospital-course-entry';
    $tHce->label      = 'Hospital Course Entry';
    $tHce->fieldgroup = $fg;
    $tHce->noChildren = 1;   // entries have no children
    $tHce->noParents  = 0;
    $tHce->tags       = 'clinical';
    wire('templates')->save($tHce);
    ok12('Created template: hospital-course-entry');
} else {
    warn12('Template hospital-course-entry already exists — skipping creation');
}

// Attach fields
foreach (['hce_date', 'hce_type', 'hce_note'] as $fn) {
    attachField12($fn, 'hospital-course-entry');
}

// Attach created_by_user if it exists (populated by the audit hook in ready.php)
if (wire('fields')->get('created_by_user')) {
    attachField12('created_by_user', 'hospital-course-entry');
} else {
    info12('created_by_user field not found — skipping (audit hook will handle it once field exists)');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — Allow hospital-course-entry as child of admission-record
// ─────────────────────────────────────────────────────────────────────────────
echo '<h2>SECTION 3 — Template Relationships</h2>';

step12('Configuring allowed child templates on admission-record...');
$tAdm = wire('templates')->get('admission-record');
$tHce = wire('templates')->get('hospital-course-entry');

if ($tAdm && $tAdm->id && $tHce && $tHce->id) {
    // childTemplates: array of template IDs allowed as children
    $existing = $tAdm->childTemplates ?: [];
    if (!in_array($tHce->id, $existing)) {
        $existing[] = $tHce->id;
        $tAdm->childTemplates = $existing;
        wire('templates')->save($tAdm);
        ok12('Added hospital-course-entry as allowed child of admission-record');
    } else {
        warn12('hospital-course-entry already in childTemplates of admission-record');
    }

    // parentTemplates on hce: only admission-record
    $existingParents = $tHce->parentTemplates ?: [];
    if (!in_array($tAdm->id, $existingParents)) {
        $existingParents[] = $tAdm->id;
        $tHce->parentTemplates = $existingParents;
        wire('templates')->save($tHce);
        ok12('Set admission-record as allowed parent of hospital-course-entry');
    } else {
        warn12('admission-record already in parentTemplates of hospital-course-entry');
    }
} else {
    fail12('Could not configure template relationships — admission-record or hospital-course-entry template missing');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — Migrate existing post_op_course text blobs
// ─────────────────────────────────────────────────────────────────────────────
echo '<h2>SECTION 4 — Data Migration</h2>';

info12('Scanning admission-record pages with non-empty post_op_course...');

$admissions = wire('pages')->find('template=admission-record, limit=500');
$migratedCases   = 0;
$migratedEntries = 0;
$skippedCases    = 0;

// Type mapping: old free-text entry types → new Options titles
$typeMap = [
    'daily note'   => 'Routine',
    'post-op day'  => 'Routine',
    'ward round'   => 'Routine',
    'complication' => 'Important',
    'discharge'    => 'Discharge',
];

foreach ($admissions as $admission) {
    $blob = trim((string) $admission->post_op_course);
    if ($blob === '') {
        continue;
    }

    // Check if this case already has hospital-course-entry children
    $existing = wire('pages')->count("template=hospital-course-entry, parent={$admission->id}");
    if ($existing > 0) {
        warn12("Case #{$admission->id} ({$admission->ip_number}) already has $existing HCE entries — skipping migration to avoid duplicates");
        $skippedCases++;
        continue;
    }

    $lines = preg_split('/\r\n|\r|\n/', $blob);
    $entryIndex = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Parse format: [01 Jan 2025] Type: Note text
        $entryDate      = null;
        $entryTypeTitle = 'Routine';
        $entryNote      = $line;

        if (preg_match('/^\[(.+?)\]\s*([^:]+):\s*(.*)$/s', $line, $m)) {
            $parsedDate = strtotime($m[1]);
            if ($parsedDate !== false) {
                $entryDate = $parsedDate;
            }
            $typeKey        = strtolower(trim($m[2]));
            $entryTypeTitle = $typeMap[$typeKey] ?? 'Routine';
            $entryNote      = trim($m[3]);
        } elseif (preg_match('/^\[(.+?)\]\s*(.*)$/s', $line, $m)) {
            // Format without type: [01 Jan 2025] Note text
            $parsedDate = strtotime($m[1]);
            if ($parsedDate !== false) {
                $entryDate = $parsedDate;
            }
            $entryNote = trim($m[2]);
        }

        if ($entryNote === '') {
            continue;
        }

        $ts        = $entryDate ?: $admission->getUnformatted('admitted_on') ?: time();
        $entryIndex++;

        $entry           = new Page();
        $entry->template = wire('templates')->get('hospital-course-entry');
        $entry->parent   = $admission;
        $entry->name     = 'hce-' . date('Ymd', $ts) . '-' . $admission->id . '-' . $entryIndex;
        $entry->of(false);
        $entry->title    = '[' . date('d M Y', $ts) . '] ' . $entryTypeTitle;

        if (wire('fields')->get('hce_date')) {
            $entry->hce_date = $ts;
        }

        // Set hce_type by option title
        $hceTypeF = wire('fields')->get('hce_type');
        if ($hceTypeF) {
            try {
                $entry->set('hce_type', $entryTypeTitle);
            } catch (\Throwable $e) {
                // Fallback: leave empty rather than crash migration
            }
        }

        if (wire('fields')->get('hce_note')) {
            $entry->hce_note = $entryNote;
        }

        wire('pages')->save($entry, ['quiet' => true]);
        $migratedEntries++;
    }

    $migratedCases++;
    ok12("Migrated {$entryIndex} entries for case #{$admission->id} ({$admission->ip_number})");
}

if ($migratedCases === 0 && $skippedCases === 0) {
    info12('No cases with post_op_course data found — nothing to migrate');
} else {
    ok12("Migration complete: $migratedEntries entries across $migratedCases cases");
    if ($skippedCases > 0) {
        warn12("$skippedCases cases skipped (already had HCE children) — verify manually");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 — Verify
// ─────────────────────────────────────────────────────────────────────────────
echo '<h2>SECTION 5 — Verification</h2>';

$totalHce = wire('pages')->count('template=hospital-course-entry');
ok12("Total hospital-course-entry pages in system: $totalHce");

$tHceCheck = wire('templates')->get('hospital-course-entry');
if ($tHceCheck && $tHceCheck->id) {
    $fg = $tHceCheck->fieldgroup;
    $fieldList = [];
    foreach ($fg as $f) {
        $fieldList[] = $f->name;
    }
    ok12('hospital-course-entry fieldgroup: ' . implode(', ', $fieldList));
} else {
    fail12('hospital-course-entry template not found after creation — check above for errors');
}

?>
<h2>&#10003; Migration Complete</h2>
<p style="color:#86efac;">
  Next steps:<br>
  1. Verify entries look correct in the admin panel under a test case<br>
  2. Run the updated case-view.php to confirm the new module renders<br>
  3. Only after verification: clear <code>post_op_course</code> fields manually via admin or a follow-up script
</p>
<p style="color:#fde68a;">
  &#9888; <strong>post_op_course data has NOT been cleared.</strong>
  The old field is preserved as backup until you confirm the migration is correct.
</p>
</body>
</html>
