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
            <a href="/case-view/?id=<?= (int)$adm->id ?>&pdf=1" target="_blank">📄 PDF</a>
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
