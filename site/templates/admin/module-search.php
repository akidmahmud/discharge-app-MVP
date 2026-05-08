<?php namespace ProcessWire;
/**
 * module-search.php — Global search with server-side filtering + client-side Fuse.js fuzzy search
 */

$q               = $sanitizer->text($input->get->q ?? '');
$filterFrom      = $sanitizer->date($input->get->date_from ?? '', 'Y-m-d');
$filterTo        = $sanitizer->date($input->get->date_to   ?? '', 'Y-m-d');
$filterStatus    = (int) ($input->get->status     ?? 0);
$filterConsultant = (int) ($input->get->consultant ?? 0);

$hasFilters = $q || $filterFrom || $filterTo || $filterStatus || $filterConsultant;

// ── Helper: extract int from SelectableOption / SelectableOptionArray / plain int ──
$optionInt = function ($raw): int {
    if ($raw === null || $raw === '') return 0;
    if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) return (int) $raw;
    if ($raw instanceof SelectableOptionArray) {
        $first = $raw->first();
        return $first ? (int) $first->value : 0;
    }
    if (is_object($raw) && isset($raw->value)) return (int) $raw->value;
    return (int) $raw;
};

// ── Build selector ────────────────────────────────────────────────────────────
$parts = ['template=admission-record'];

if ($q) {
    $qEsc = $sanitizer->selectorValue($q);
    // parent.title searches the patient page's name; ip_number + diagnosis cover the case itself
    $parts[] = "parent.title|ip_number|diagnosis|search_index%={$qEsc}";
}
if ($filterStatus)      $parts[] = "case_status={$filterStatus}";
if ($filterFrom)        $parts[] = 'created>=' . strtotime($filterFrom . ' 00:00:00');
if ($filterTo)          $parts[] = 'created<=' . strtotime($filterTo   . ' 23:59:59');
if ($filterConsultant)  $parts[] = "consultant_ref={$filterConsultant}";

$parts[] = 'sort=-created';
$parts[] = $hasFilters ? 'limit=200' : 'limit=50';

$results = new PageArray();
try {
    $results = $pages->find(implode(', ', $parts));
} catch (\Throwable $e) {
    // search_index field might not exist — retry without it
    $fallbackParts = array_map(function ($p) {
        return str_replace('|search_index', '', $p);
    }, $parts);
    try {
        $results = $pages->find(implode(', ', $fallbackParts));
    } catch (\Throwable $e2) {}
}

$total = $results->getTotal();

$consultants  = $pages->find('template=consultant, sort=title');

$statusLabels = [1 => 'Active', 2 => 'Discharged', 3 => 'Follow-up', 4 => 'Cancelled'];
$statusBadges = [1 => 'badge--green', 2 => 'badge--blue', 3 => 'badge--amber', 4 => 'badge--gray'];

// ── Build row data (used for both HTML table and Fuse.js JSON) ────────────────
$rows = [];
foreach ($results as $adm) {
    $patient     = ($adm->parent instanceof Page && $adm->parent->id) ? $adm->parent : null;
    $patientName = $patient ? trim((string) $patient->title) : trim((string) $adm->title);
    $patientId   = $patient ? trim((string) $patient->patient_id) : '';
    $ipNumber    = trim((string) $adm->ip_number);
    $statusNum   = $optionInt($adm->getUnformatted('case_status'));
    $admittedTs  = (int) $adm->getUnformatted('admitted_on') ?: (int) $adm->created;
    $diagPage    = $adm->primary_diagnosis_ref;
    $diagText    = ($diagPage && $diagPage->id)
                    ? trim((string) $diagPage->title)
                    : trim(strip_tags((string) $adm->diagnosis));
    $consPage    = $adm->consultant_ref;
    $consName    = ($consPage && $consPage->id) ? trim((string) $consPage->title) : '';
    $rows[] = [
        'id'         => (int) $adm->id,
        'name'       => $patientName,
        'pid'        => $patientId,
        'ip'         => $ipNumber,
        'diag'       => $diagText,
        'cons'       => $consName,
        'statusNum'  => $statusNum,
        'statusLabel'=> $statusLabels[$statusNum] ?? 'Unknown',
        'badgeClass' => $statusBadges[$statusNum] ?? 'badge--gray',
        'admitted'   => $admittedTs ? date('d M Y', $admittedTs) : '',
    ];
}
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Global Search</h1>
      <p class="admin-module__subtitle">Search all admission records by patient name, IP number, diagnosis, or consultant</p>
    </div>
  </div>

  <!-- Filter bar (server-side: date, status, consultant) -->
  <div class="admin-card">
    <div class="admin-card__body">
      <form method="get" action="/admin-panel/" class="admin-filter-bar" style="flex-wrap:wrap;gap:8px;" id="search-form">
        <input type="hidden" name="module" value="search">
        <input class="admin-field__input" type="text" name="q"
          value="<?= htmlspecialchars($q) ?>"
          placeholder="Patient name, IP number, diagnosis…"
          style="flex:1;min-width:220px;" id="server-q">
        <select class="admin-field__select" name="status" style="min-width:140px;">
          <option value="">All Statuses</option>
          <?php foreach ($statusLabels as $v => $l): ?>
          <option value="<?= $v ?>" <?= $filterStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <select class="admin-field__select" name="consultant" style="min-width:160px;">
          <option value="">All Consultants</option>
          <?php foreach ($consultants as $c): ?>
          <option value="<?= $c->id ?>" <?= $filterConsultant === $c->id ? 'selected' : '' ?>><?= htmlspecialchars($c->title) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="admin-field__input" type="date" name="date_from"
          value="<?= htmlspecialchars($filterFrom) ?>" title="Admitted from" style="width:150px;">
        <input class="admin-field__input" type="date" name="date_to"
          value="<?= htmlspecialchars($filterTo) ?>" title="Admitted to" style="width:150px;">
        <button type="submit" class="admin-btn admin-btn--primary">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <?php if ($hasFilters): ?>
        <a href="/admin-panel/?module=search" class="admin-btn admin-btn--ghost">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Results table -->
  <div class="admin-card">
    <div class="admin-card__header">
      <h2 class="admin-card__title">
        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <?= $hasFilters ? 'Search Results' : 'Recent Cases' ?>
      </h2>
      <span style="font-size:12px;color:#94A3B8;" id="total-count"><span id="fuzzy-count"></span>
        <?= $hasFilters
            ? ($total . ' record' . ($total !== 1 ? 's' : '') . ' found')
            : ('Showing last ' . count($rows) . ' cases') ?>
      </span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="search-results-table">
        <thead>
          <tr>
            <th>Patient</th>
            <th>IP Number</th>
            <th>Diagnosis</th>
            <th>Consultant</th>
            <th>Admitted</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="search-tbody">
        <?php if (empty($rows)): ?>
        <tr><td colspan="7"><div class="admin-empty"><?= $hasFilters ? 'No records match your search.' : 'No cases found.' ?></div></td></tr>
        <?php else: ?>
        <?php foreach ($rows as $row): ?>
        <tr class="search-row">
          <td>
            <div style="font-weight:600;color:#1E293B;"><?= htmlspecialchars($row['name']) ?></div>
            <?php if ($row['pid']): ?><div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars($row['pid']) ?></div><?php endif; ?>
          </td>
          <td style="font-family:monospace;font-size:12px;color:#2563EB;"><?= htmlspecialchars($row['ip'] ?: '—') ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151;"><?= htmlspecialchars($row['diag'] !== '' ? $row['diag'] : '—') ?></td>
          <td style="font-size:13px;"><?= htmlspecialchars($row['cons'] ?: '—') ?></td>
          <td style="white-space:nowrap;font-size:12px;color:#64748B;"><?= htmlspecialchars($row['admitted'] ?: '—') ?></td>
          <td><span class="badge <?= $row['badgeClass'] ?>"><?= $row['statusLabel'] ?></span></td>
          <td>
            <div class="td-actions">
              <a href="/case-view/?id=<?= $row['id'] ?>" target="_blank" class="admin-btn admin-btn--ghost admin-btn--sm">View</a>
              <a href="/case-view/?id=<?= $row['id'] ?>&pdf=1" target="_blank" class="admin-btn admin-btn--ghost admin-btn--sm">PDF</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Fuse.js fuzzy search -->
<script src="https://cdn.jsdelivr.net/npm/fuse.js@7/dist/fuse.min.js"></script>
<script>
(function () {
  var data = <?= json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  if (!data.length) return;

  var fuse = new Fuse(data, {
    keys: [
      { name: 'name', weight: 3 },
      { name: 'ip',   weight: 3 },
      { name: 'pid',  weight: 2 },
      { name: 'diag', weight: 2 },
      { name: 'cons', weight: 1 },
    ],
    threshold: 0.35,
    includeScore: true,
    ignoreLocation: true,
    minMatchCharLength: 2,
  });

  var tbody   = document.getElementById('search-tbody');
  var input   = document.getElementById('server-q');
  var counter = document.getElementById('fuzzy-count');
  var rows    = tbody ? Array.from(tbody.querySelectorAll('.search-row')) : [];

  // Build a stable id → row map once (rows never change after page load)
  var idToRow = {};
  rows.forEach(function (r, i) {
    if (data[i]) idToRow[data[i].id] = r;
  });

  function applyFilter(q) {
    if (!rows.length) return;
    q = q.trim();
    if (!q) {
      // Restore original load order
      rows.forEach(function (r) {
        r.style.display = '';
        tbody.appendChild(r);
      });
      if (counter) counter.textContent = '';
      return;
    }

    var results = fuse.search(q);          // already sorted: score 0 = best match
    var matchedSet = new Set(results.map(function (r) { return r.item.id; }));

    // Append matches in score order → best match ends up at the top
    results.forEach(function (result) {
      var row = idToRow[result.item.id];
      if (row) { row.style.display = ''; tbody.appendChild(row); }
    });

    // Append non-matches (hidden) below so they stay in DOM
    rows.forEach(function (r, i) {
      if (data[i] && !matchedSet.has(data[i].id)) {
        r.style.display = 'none';
        tbody.appendChild(r);
      }
    });

    if (counter) counter.textContent = results.length + ' match' + (results.length !== 1 ? 'es' : '');
  }

  if (input) {
    input.addEventListener('input', function () { applyFilter(this.value); });
    // Apply on load if there is a pre-filled value
    if (input.value) applyFilter(input.value);
  }
})();
</script>
