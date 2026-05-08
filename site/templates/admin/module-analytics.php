<?php namespace ProcessWire;
/**
 * module-analytics.php — Clinical analytics and charts
 */

// Date range filter
$rangeMap  = ['7'=>7,'30'=>30,'90'=>90,'365'=>365];
$rangeDays = isset($rangeMap[$input->get->range]) ? (int)$input->get->range : 30;
$since     = strtotime("-{$rangeDays} days");
$sinceDate = date('Y-m-d', $since);

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalCases      = $pages->count("template=admission-record");
$activeCases     = $pages->count("template=admission-record, case_status=1");
$dischargedCases = $pages->count("template=admission-record, case_status=2");
$totalPatients   = $pages->count("template=patient-record");

// ── Discharges over time (last N days) ───────────────────────────────────────
$dischargeDays = [];
for ($i = $rangeDays - 1; $i >= 0; $i--) {
    $day   = date('Y-m-d', strtotime("-{$i} days"));
    $start = strtotime($day . ' 00:00:00');
    $end   = strtotime($day . ' 23:59:59');
    $count = $pages->count("template=admission-record, case_status=2, discharged_on>={$start}, discharged_on<={$end}");
    $dischargeDays[] = ['date' => date('d M', strtotime($day)), 'count' => (int)$count];
}

// ── Cases by consultant ───────────────────────────────────────────────────────
$consultantData = [];
try {
    $db   = $database;
    $cons = $db->query("SELECT name FROM admin_consultants WHERE status='active' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($cons as $cname) {
        $count = $pages->count("template=admission-record, consultant_name={$cname}");
        if ($count > 0) $consultantData[$cname] = $count;
    }
} catch (\Exception $e) {}

// Fallback: use PW field if consultant_name exists
if (empty($consultantData)) {
    $allCases = $pages->find("template=admission-record, limit=500");
    foreach ($allCases as $case) {
        $cn = $case->get('consultant_name') ?: 'Unknown';
        $consultantData[$cn] = ($consultantData[$cn] ?? 0) + 1;
    }
}
arsort($consultantData);
$consultantData = array_slice($consultantData, 0, 8, true);

// ── Admissions per month (last 6 months) ──────────────────────────────────────
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month      = date('Y-m', strtotime("-{$i} months"));
    $label      = date('M Y', strtotime("-{$i} months"));
    $monthStart = strtotime($month . '-01 00:00:00');
    $monthEnd   = strtotime(date('Y-m-t', $monthStart) . ' 23:59:59');
    $count      = $pages->count("template=admission-record, created>={$monthStart}, created<={$monthEnd}");
    $monthlyData[] = ['label' => $label, 'count' => (int)$count];
}

// JSON for charts
$dischargeLabels  = json_encode(array_column($dischargeDays, 'date'));
$dischargeCounts  = json_encode(array_column($dischargeDays, 'count'));
$consLabels       = json_encode(array_keys($consultantData));
$consCounts       = json_encode(array_values($consultantData));
$monthLabels      = json_encode(array_column($monthlyData, 'label'));
$monthCounts      = json_encode(array_column($monthlyData, 'count'));
?>

<div class="admin-module">
  <div class="admin-module__header">
    <div>
      <h1 class="admin-module__title">Analytics</h1>
      <p class="admin-module__subtitle">Clinical activity and discharge trends</p>
    </div>
    <div style="display:flex;gap:6px;">
      <?php foreach ([7=>'7 Days',30=>'30 Days',90=>'90 Days',365=>'1 Year'] as $days => $label): ?>
      <a href="/admin-panel/?module=analytics&range=<?= $days ?>"
        class="admin-btn admin-btn--<?= $rangeDays===$days?'primary':'ghost' ?> admin-btn--sm">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Summary stats -->
  <div class="admin-stats">
    <div class="admin-stat-card admin-stat-card--blue">
      <span class="admin-stat-card__label">Total Cases</span>
      <span class="admin-stat-card__value"><?= $totalCases ?></span>
      <span class="admin-stat-card__sub">All time admissions</span>
    </div>
    <div class="admin-stat-card admin-stat-card--green">
      <span class="admin-stat-card__label">Active</span>
      <span class="admin-stat-card__value"><?= $activeCases ?></span>
      <span class="admin-stat-card__sub">Currently admitted</span>
    </div>
    <div class="admin-stat-card admin-stat-card--amber">
      <span class="admin-stat-card__label">Discharged</span>
      <span class="admin-stat-card__value"><?= $dischargedCases ?></span>
      <span class="admin-stat-card__sub">Total discharged</span>
    </div>
    <div class="admin-stat-card admin-stat-card--red">
      <span class="admin-stat-card__label">Patients</span>
      <span class="admin-stat-card__value"><?= $totalPatients ?></span>
      <span class="admin-stat-card__sub">Registered patients</span>
    </div>
  </div>

  <!-- Charts row -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Discharges over time -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Discharges Over Time
        </h2>
        <span style="font-size:12px;color:#94A3B8;">Last <?= $rangeDays ?> days</span>
      </div>
      <div class="admin-card__body">
        <canvas id="chart-discharges" height="200"></canvas>
      </div>
    </div>

    <!-- Monthly admissions -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Monthly Admissions
        </h2>
        <span style="font-size:12px;color:#94A3B8;">Last 6 months</span>
      </div>
      <div class="admin-card__body">
        <canvas id="chart-monthly" height="200"></canvas>
      </div>
    </div>
  </div>

  <!-- Consultant distribution -->
  <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Cases by Consultant
        </h2>
      </div>
      <div class="admin-card__body">
        <?php if (empty($consultantData)): ?>
        <div class="admin-empty">No consultant data available yet.</div>
        <?php else: ?>
        <?php $maxVal = max($consultantData) ?: 1; ?>
        <?php foreach ($consultantData as $name => $count): ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:13px;color:#374151;"><?= htmlspecialchars($name) ?></span>
            <span style="font-size:13px;font-weight:600;color:#0F172A;"><?= $count ?></span>
          </div>
          <div style="height:8px;background:#F1F5F9;border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:<?= round($count/$maxVal*100) ?>%;background:linear-gradient(90deg,#2563EB,#60A5FA);border-radius:4px;transition:width 0.5s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Donut: active vs discharged -->
    <div class="admin-card">
      <div class="admin-card__header">
        <h2 class="admin-card__title">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Case Status
        </h2>
      </div>
      <div class="admin-card__body" style="display:flex;flex-direction:column;align-items:center;">
        <canvas id="chart-status" width="180" height="180" style="max-width:180px;"></canvas>
        <div style="display:flex;gap:16px;margin-top:12px;">
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
            <div style="width:10px;height:10px;border-radius:50%;background:#2563EB;"></div>
            <span>Active (<?= $activeCases ?>)</span>
          </div>
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
            <div style="width:10px;height:10px;border-radius:50%;background:#16A34A;"></div>
            <span>Discharged (<?= $dischargedCases ?>)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#64748B';

// Discharges over time
new Chart(document.getElementById('chart-discharges'), {
  type: 'line',
  data: {
    labels: <?= $dischargeLabels ?>,
    datasets: [{
      label: 'Discharges',
      data: <?= $dischargeCounts ?>,
      borderColor: '#2563EB',
      backgroundColor: 'rgba(37,99,235,0.08)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointBackgroundColor: '#2563EB',
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Monthly admissions
new Chart(document.getElementById('chart-monthly'), {
  type: 'bar',
  data: {
    labels: <?= $monthLabels ?>,
    datasets: [{
      label: 'Admissions',
      data: <?= $monthCounts ?>,
      backgroundColor: 'rgba(37,99,235,0.75)',
      borderRadius: 6,
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Status donut
new Chart(document.getElementById('chart-status'), {
  type: 'doughnut',
  data: {
    labels: ['Active', 'Discharged'],
    datasets: [{
      data: [<?= $activeCases ?>, <?= $dischargedCases ?>],
      backgroundColor: ['#2563EB', '#16A34A'],
      borderWidth: 0,
      hoverOffset: 4,
    }]
  },
  options: { responsive: false, plugins: { legend: { display: false } }, cutout: '65%' }
});
</script>
