<?php
/**
 * Phase 9 Migration — Advanced Search System
 * Creates search index fields, dedicated search results page, and full-text
 * indexing support for the clinical registry
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
function fail($m) { echo "<div style='background:#7f1d1d;color:#fca5a5;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✗ $m</div>\n"; flush(); }

function createField9($name, $type, $label, $cfg = []) {
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

function addFieldToTemplate9($fieldName, $templateName) {
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
<title>Phase 9 — Advanced Search System</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>🔎 Phase 9 Migration — Advanced Search System</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

// ─── SECTION 1: Search Index Field on Admission Record ───────────────────────
echo '<h2>SECTION 1 — Search Index Field</h2>';

// search_index stores a denormalised text blob rebuilt on every save
// enables fast full-text search without joining multiple tables
createField9('search_index', 'FieldtypeTextarea', 'Search Index (auto-generated)', [
    'rows'      => 4,
    'collapsed' => Inputfield::collapsedHidden,
    'description' => 'Auto-populated on save. Do not edit manually.',
]);

addFieldToTemplate9('search_index', 'admission-record');

// ─── SECTION 2: Create Search Results Page Template ──────────────────────────
echo '<h2>SECTION 2 — Search Results Template</h2>';

step('Creating search-results template...');
$tSearch = wire('templates')->get('search-results');
if (!$tSearch || !$tSearch->id) {
    $fg = new Fieldgroup();
    $fg->name = 'search-results';
    $fg->add(wire('fields')->get('title'));
    $fg->save();

    $tSearch = new Template();
    $tSearch->name       = 'search-results';
    $tSearch->label      = 'Search Results';
    $tSearch->fieldgroup = $fg;
    $tSearch->noChildren = 1;
    wire('templates')->save($tSearch);
    ok("Created template: search-results");
} else {
    warn("Template 'search-results' already exists — skipping");
}

// ─── SECTION 3: Create /search/ Page ─────────────────────────────────────────
echo '<h2>SECTION 3 — Create Search Page</h2>';

step('Creating /search/ page...');
$searchPage = wire('pages')->get('/search/');
if (!$searchPage || !$searchPage->id) {
    $searchPage = new Page();
    $searchPage->template = wire('templates')->get('search-results');
    $searchPage->parent   = wire('pages')->get('/');
    $searchPage->name     = 'search';
    $searchPage->title    = 'Clinical Search';
    wire('pages')->save($searchPage);
    ok("Created /search/ page");
} else {
    warn("/search/ already exists — skipping");
}

// ─── SECTION 4: Hook to Rebuild search_index on Admission Save ───────────────
echo '<h2>SECTION 4 — Search Index Hook (ready.php)</h2>';

step('Verifying search_index field exists and is attached...');
$sf = wire('fields')->get('search_index');
$tAdm = wire('templates')->get('admission-record');
if ($sf && $tAdm && $tAdm->fieldgroup->has($sf)) {
    ok("search_index attached to admission-record — hook will activate in ready.php");
} else {
    fail("search_index not attached — check above for errors");
}

echo '<div style="background:#0c1a2e;border:1px solid #1e3a5f;padding:14px;border-radius:6px;margin:10px 0;">';
echo '<p style="color:#fde68a;margin:0 0 8px;">⚠ Add this hook to <code>site/ready.php</code> to auto-rebuild search_index on save:</p>';
echo '<pre style="color:#86efac;font-size:12px;overflow-x:auto;">';
echo htmlspecialchars(
'// PHASE 9 — REBUILD SEARCH INDEX
$pages->addHookAfter(\'save\', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== \'admission-record\') return;
    if (!wire(\'fields\')->get(\'search_index\')) return;

    $parts = [];
    $parts[] = $page->ip_number;
    $parts[] = $page->parent->title;         // patient name
    $parts[] = $page->parent->patient_id;

    $diag = $page->primary_diagnosis_ref;
    if ($diag && $diag->id) $parts[] = $diag->title;
    $parts[] = $page->diagnosis_side;

    $cons = $page->consultant_ref;
    if ($cons && $cons->id) $parts[] = $cons->title;

    $procs = wire(\'pages\')->find("template=procedure, parent={$page->id}");
    foreach ($procs as $proc) $parts[] = $proc->proc_name;

    $invs = wire(\'pages\')->find("template=investigation, parent={$page->id}");
    foreach ($invs as $inv) $parts[] = $inv->investigation_name;

    $parts[] = $page->chief_complaint;
    $parts[] = $page->post_op_course;

    $index = implode(\' \', array_filter($parts));
    $page->of(false);
    $page->search_index = strtolower($index);
    $page->save(\'search_index\');
});'
);
echo '</pre></div>';

// Actually write the hook into ready.php automatically
step('Appending search_index hook to site/ready.php...');
$readyPath = wire('config')->paths->site . 'ready.php';
$readyContent = file_get_contents($readyPath);
if (strpos($readyContent, 'PHASE 9') === false) {
    $hookCode = '

// ─────────────────────────────────────────────────────────────────────────────
// PHASE 9 — REBUILD SEARCH INDEX
// Denormalised text blob rebuilt on every admission-record save
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookAfter(\'save\', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== \'admission-record\') return;
    if (!wire(\'fields\')->get(\'search_index\')) return;

    $parts = [];
    $parts[] = $page->ip_number;
    $parts[] = $page->parent->title;
    $parts[] = $page->parent->patient_id;

    $diag = $page->getUnformatted(\'primary_diagnosis_ref\');
    if ($diag instanceof Page && $diag->id) $parts[] = $diag->title;
    $parts[] = $page->diagnosis_side;

    $cons = $page->getUnformatted(\'consultant_ref\');
    if ($cons instanceof Page && $cons->id) $parts[] = $cons->title;

    $procs = wire(\'pages\')->find("template=procedure, parent={$page->id}");
    foreach ($procs as $proc) { $parts[] = $proc->proc_name; $parts[] = $proc->anesthesia_type; }

    $invs = wire(\'pages\')->find("template=investigation, parent={$page->id}");
    foreach ($invs as $inv) { $parts[] = $inv->investigation_name; $parts[] = $inv->investigation_type; }

    $parts[] = $page->chief_complaint;
    $parts[] = $page->post_op_course;
    $parts[] = $page->proposed_procedure;

    $index = implode(\' \', array_filter(array_map(\'trim\', $parts)));
    $page->of(false);
    $page->search_index = strtolower($index);
    $page->save(\'search_index\');
});
';
    file_put_contents($readyPath, $readyContent . $hookCode);
    ok("Appended PHASE 9 search_index hook to site/ready.php");
} else {
    warn("PHASE 9 hook already present in site/ready.php — skipping");
}

// ─── SECTION 5: Create search-results.php Template File ─────────────────────
echo '<h2>SECTION 5 — Search Results Template File</h2>';

$searchTplPath = wire('config')->paths->templates . 'search-results.php';
if (!file_exists($searchTplPath)) {
    step('Writing site/templates/search-results.php...');
    $searchTplContent = file_put_contents($searchTplPath, <<<'PHP'
<?php namespace ProcessWire;
/**
 * search-results.php — Clinical Registry Advanced Search
 * Phase 9
 */

$q            = wire('sanitizer')->text(wire('input')->get('q'));
$filterSide   = (int)wire('input')->get('side'); // 1=Right 2=Left 3=Bilateral
if (!in_array($filterSide, [1, 2, 3])) $filterSide = 0;
$filterStatus = (int)wire('input')->get('status');
$filterFrom   = wire('sanitizer')->date(wire('input')->get('date_from'), 'Y-m-d');
$filterTo     = wire('sanitizer')->date(wire('input')->get('date_to'),   'Y-m-d');
$filterConsultant = (int)wire('input')->get('consultant');
$filterDiagnosis  = (int)wire('input')->get('diagnosis');

$results = new PageArray();
$total   = 0;

if ($q || $filterSide || $filterStatus || $filterFrom || $filterConsultant || $filterDiagnosis) {
    $selector = 'template=admission-record, limit=50';

    if ($q) {
        $qSafe = wire('sanitizer')->selectorValue(strtolower($q));
        $selector .= ", search_index%=$qSafe";
    }
    if ($filterSide)      $selector .= ", diagnosis_side=$filterSide";
    if ($filterStatus)    $selector .= ", case_status=$filterStatus";
    if ($filterFrom)      $selector .= ", created>=" . strtotime($filterFrom);
    if ($filterTo)        $selector .= ", created<=" . strtotime($filterTo . ' 23:59:59');
    if ($filterConsultant) $selector .= ", consultant_ref=$filterConsultant";
    if ($filterDiagnosis)  $selector .= ", primary_diagnosis_ref=$filterDiagnosis";

    $results = wire('pages')->find($selector);
    $total   = $results->getTotal();
}

$consultants = wire('pages')->find('template=consultant, sort=title');
$diagnoses   = wire('pages')->find('template=diagnosis-taxonomy, sort=title');

$statusLabels = [1 => 'Active', 2 => 'Discharged', 3 => 'Follow-up', 4 => 'Cancelled'];
$sideOpts     = [1 => 'Right', 2 => 'Left', 3 => 'Bilateral'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clinical Search — GangaReg</title>
<link rel="stylesheet" href="<?= wire('config')->urls->templates ?>styles/main.css">
<style>
:root{--bg:#0f172a;--surface:#1e293b;--border:#334155;--accent:#38bdf8;--text:#e2e8f0;--muted:#94a3b8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;min-height:100vh}
.top-bar{background:var(--surface);border-bottom:1px solid var(--border);padding:12px 20px;display:flex;align-items:center;gap:12px}
.top-bar a{color:var(--muted);text-decoration:none;font-size:13px}
.top-bar a:hover{color:var(--accent)}
.top-bar h1{font-size:18px;color:var(--accent);margin-left:auto;margin-right:auto}
.container{max-width:1200px;margin:0 auto;padding:20px}
.search-box{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:20px;margin-bottom:20px}
.search-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.search-row input,.search-row select{background:#0f172a;border:1px solid var(--border);color:var(--text);
    padding:9px 14px;border-radius:6px;font-size:14px;min-width:160px}
.search-row input[type=text]{flex:1;min-width:240px}
.btn{background:var(--accent);color:#0f172a;border:none;padding:9px 20px;border-radius:6px;
    font-weight:600;cursor:pointer;font-size:14px}
.btn:hover{background:#7dd3fc}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--muted)}
.result-count{color:var(--muted);font-size:13px;margin-bottom:12px}
.result-count strong{color:var(--accent)}
table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:10px;overflow:hidden}
thead{background:#162032}
th{padding:11px 14px;text-align:left;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
td{padding:11px 14px;border-top:1px solid var(--border);font-size:14px;vertical-align:middle}
tr:hover td{background:#162032}
.pill{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600}
.pill-1{background:#164e63;color:#67e8f9}
.pill-2{background:#14532d;color:#86efac}
.pill-3{background:#1e3a5f;color:#93c5fd}
.pill-4{background:#3f1515;color:#fca5a5}
.badge{background:#1e293b;border:1px solid var(--border);color:var(--muted);padding:2px 7px;border-radius:999px;font-size:11px}
.action-btns{display:flex;gap:6px}
.action-btns a{background:#1e293b;border:1px solid var(--border);color:var(--muted);padding:4px 10px;
    border-radius:5px;font-size:12px;text-decoration:none}
.action-btns a:hover{color:var(--accent);border-color:var(--accent)}
.no-results{text-align:center;padding:48px 20px;color:var(--muted)}
.no-results span{font-size:48px;display:block;margin-bottom:12px}
</style>
</head>
<body>

<div class="top-bar">
    <a href="<?= wire('pages')->get('/')->url ?>">← Dashboard</a>
    <h1>🔎 Clinical Search</h1>
</div>

<div class="container">

<div class="search-box">
<form method="get" action="">
<div class="search-row">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
        placeholder="Search patient name, IP number, diagnosis, procedure...">
    <select name="side">
        <option value="">All Sides</option>
        <?php foreach ($sideOpts as $v => $l): ?>
        <option value="<?= $v ?>" <?= $filterSide === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All Statuses</option>
        <?php foreach ($statusLabels as $v => $l): ?>
        <option value="<?= $v ?>" <?= $filterStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="search-row">
    <select name="consultant">
        <option value="">All Consultants</option>
        <?php foreach ($consultants as $c): ?>
        <option value="<?= $c->id ?>" <?= $filterConsultant === $c->id ? 'selected' : '' ?>><?= htmlspecialchars($c->title) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="diagnosis">
        <option value="">All Diagnoses</option>
        <?php foreach ($diagnoses as $d): ?>
        <option value="<?= $d->id ?>" <?= $filterDiagnosis === $d->id ? 'selected' : '' ?>><?= htmlspecialchars($d->title) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>" title="Admission from">
    <input type="date" name="date_to"   value="<?= htmlspecialchars($filterTo) ?>"   title="Admission to">
    <button type="submit" class="btn">Search</button>
    <a href="?" class="btn btn-outline">Clear</a>
</div>
</form>
</div>

<?php if ($q || $filterSide || $filterStatus || $filterFrom || $filterConsultant || $filterDiagnosis): ?>

<div class="result-count">
    Found <strong><?= $total ?></strong> record<?= $total !== 1 ? 's' : '' ?>
    <?= $q ? ' matching "<strong>' . htmlspecialchars($q) . '</strong>"' : '' ?>
</div>

<?php if ($results->count()): ?>
<table>
<thead>
<tr>
    <th>Patient</th>
    <th>IP Number</th>
    <th>Admitted</th>
    <th>Consultant</th>
    <th>Diagnosis</th>
    <th>Procedures</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($results as $adm):
    $patient    = $adm->parent;
    $statusNum  = (int)$adm->getUnformatted('case_status');
    $statusLabel = $statusLabels[$statusNum] ?? 'Unknown';
    $diag       = $adm->primary_diagnosis_ref;
    $cons       = $adm->consultant_ref;
    $procCount  = wire('pages')->count("template=procedure, parent={$adm->id}");
?>
<tr>
    <td>
        <div style="font-weight:600"><?= htmlspecialchars($patient->title) ?></div>
        <div style="color:var(--muted);font-size:12px"><?= htmlspecialchars($patient->patient_id) ?></div>
    </td>
    <td style="font-family:monospace;color:var(--accent)"><?= htmlspecialchars($adm->ip_number) ?></td>
    <td style="color:var(--muted)"><?= date('d/m/Y', $adm->created) ?></td>
    <td><?= $cons && $cons->id ? htmlspecialchars($cons->title) : '<span style="color:var(--muted)">—</span>' ?></td>
    <td>
        <?php if ($diag && $diag->id): ?>
        <?= htmlspecialchars(substr($diag->title, 0, 28)) ?><?= strlen($diag->title) > 28 ? '…' : '' ?>
        <?php if ($adm->diagnosis_side): ?>
        <span class="badge"><?= htmlspecialchars($adm->diagnosis_side) ?></span>
        <?php endif; ?>
        <?php else: ?>
        <span style="color:var(--muted)">—</span>
        <?php endif; ?>
    </td>
    <td><?= $procCount ? '<span class="badge">' . $procCount . '</span>' : '<span style="color:var(--muted)">0</span>' ?></td>
    <td><span class="pill pill-<?= $statusNum ?>"><?= $statusLabel ?></span></td>
    <td>
        <div class="action-btns">
            <a href="<?= $adm->url ?>">🕐 Timeline</a>
            <a href="<?= $adm->url ?>?pdf=1">📄 PDF</a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php else: ?>
<div class="no-results">
    <span>🔍</span>
    No records found matching your criteria.
    <br><a href="?" style="color:var(--accent)">Clear filters and start over</a>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-results">
    <span>🔎</span>
    Enter a search term or apply filters above to find patient records.
</div>
<?php endif; ?>

</div><!-- /container -->
</body>
</html>
PHP
    );
    ok("Wrote site/templates/search-results.php");
} else {
    warn("site/templates/search-results.php already exists — skipping");
}

// ─── SECTION 6: Rebuild search_index for all existing admissions ─────────────
echo '<h2>SECTION 6 — Rebuild search_index for Existing Records</h2>';

step('Rebuilding search_index for all existing admission-records...');
$admissions = wire('pages')->find('template=admission-record, limit=500');
$rebuilt = 0;

foreach ($admissions as $adm) {
    $parts = [];
    $parts[] = $adm->ip_number;
    $parts[] = $adm->parent->title;
    $parts[] = $adm->parent->patient_id;

    $diag = $adm->getUnformatted('primary_diagnosis_ref');
    if ($diag instanceof Page && $diag->id) $parts[] = $diag->title;
    $parts[] = $adm->diagnosis_side;

    $cons = $adm->getUnformatted('consultant_ref');
    if ($cons instanceof Page && $cons->id) $parts[] = $cons->title;

    $procs = wire('pages')->find("template=procedure, parent={$adm->id}");
    foreach ($procs as $proc) { $parts[] = $proc->proc_name; $parts[] = $proc->anesthesia_type; }

    $invs = wire('pages')->find("template=investigation, parent={$adm->id}");
    foreach ($invs as $inv) { $parts[] = $inv->investigation_name; $parts[] = $inv->investigation_type; }

    $parts[] = $adm->chief_complaint;
    $parts[] = $adm->post_op_course;
    $parts[] = $adm->proposed_procedure;

    $index = implode(' ', array_filter(array_map('trim', $parts)));

    $adm->of(false);
    $adm->search_index = strtolower($index);
    $adm->save('search_index');
    $rebuilt++;
}

ok("Rebuilt search_index for $rebuilt admission records");

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 9 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">Search System:</h3>';
echo '<ul style="color:#94a3b8;line-height:2;">';
echo '<li>✓ <code>search_index</code> field — denormalised text blob on admission-record</li>';
echo '<li>✓ Auto-rebuild hook appended to <code>site/ready.php</code></li>';
echo '<li>✓ <code>site/templates/search-results.php</code> — full search UI with 6 filters</li>';
echo '<li>✓ <code>/search/</code> page created</li>';
echo "<li>✓ Rebuilt search_index for $rebuilt existing records</li>";
echo '</ul>';
echo '<p style="color:#86efac;font-weight:bold;">▶ Next: Run phase10_migration.php for Performance Optimization</p>';
echo '</div>';
?>
</body>
</html>
