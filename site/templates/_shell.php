<?php namespace ProcessWire;

/**
 * _shell.php - Global app shell: sidebar + top bar
 * Included by _main.php on every page (unless $noShell = true).
 */

/** @var Page   $page */
/** @var Config $config */
/** @var User   $user */

$currentUrl = $page->url;
$isDashboard = strpos($currentUrl, '/dashboard/') === 0;
$isPatients = strpos($currentUrl, '/patients/') === 0;
$isPendingDischarge = $isPatients && $input->get->view === 'pending';
$isCaseView = $page->template->name === 'admission-record' || strpos($currentUrl, '/case-view/') === 0;
$roleFlags = wire('authRoleFlags') ?: [];
$isAdminUser = !empty($roleFlags['is_admin']);
$isConsultantUser = !empty($roleFlags['is_consultant']);
$isPhysicianAssistantUser = !empty($roleFlags['is_physician_assistant']);
$isMedicalOfficerUser = !empty($roleFlags['is_medical_officer']);
$doctorName = $user->isLoggedin() ? $user->name : 'admin';

// Role label for topbar chip
if ($isAdminUser) {
    $roleLabel = 'Admin';
} elseif ($isMedicalOfficerUser) {
    $roleLabel = 'MO';
} elseif ($isConsultantUser) {
    $roleLabel = 'Consultant';
} elseif ($isPhysicianAssistantUser) {
    $roleLabel = 'PA';
} else {
    $roleLabel = 'User';
}

$adminBase = $config->urls->admin;
$hideSidebar = $page->template->name === 'admission-record';

// Permission helper for nav links
$canSeeNav = wire('dashPerm');

$patientsParent = $pages->get('/patients/');
if (!$patientsParent->id) {
  $samplePatient = $pages->get("template=patient-record");
  $patientsParent = $samplePatient->id ? $samplePatient->parent : $pages->get('/');
}
$patientTemplateId = $templates->get('patient-record') ? $templates->get('patient-record')->id : 0;
$addPatientUrl = $patientTemplateId
  ? $adminBase . 'page/add/?parent_id=' . $patientsParent->id . '&template_id=' . $patientTemplateId
  : '/patients/';
$fieldExists = static function (string $fieldName): bool {
  $field = wire('fields')->get($fieldName);
  return $field && $field->id;
};

$toTimestamp = static function ($value): int {
  if (is_int($value)) return $value;
  if (is_numeric($value) && (string) (int) $value === (string) $value) return (int) $value;
  if (is_string($value)) {
    $value = trim($value);
    if ($value === '') return 0;
    $parsed = strtotime($value);
    return $parsed ?: 0;
  }
  return 0;
};

$hasCaseStatusField = $fieldExists('case_status');
$hasDischargedOnField = $fieldExists('discharged_on');
$pendingDischargeCount = 0;
$shellAdmissions = $pages->find("template=admission-record, sort=-created, limit=500");

foreach ($shellAdmissions as $admission) {
  if ($hasCaseStatusField) {
    $statusRaw = $admission->getUnformatted('case_status');
    if (is_object($statusRaw) && method_exists($statusRaw, 'first')) {
      $firstStatus = $statusRaw->first();
      $statusId = $firstStatus ? (int) $firstStatus->id : 0;
    } else {
      $statusId = (int) $statusRaw;
    }
  } elseif ($hasDischargedOnField) {
    $statusId = $toTimestamp($admission->getUnformatted('discharged_on')) > 0 ? 2 : 1;
  } else {
    $statusId = 1;
  }

  if ($statusId === 1) {
    $pendingDischargeCount++;
  }
}

$dashboardNavClass = 'nav-item' . ($isDashboard ? ' nav-item--active' : '');
$patientsNavClass = 'nav-item' . ($isPatients && !$isPendingDischarge ? ' nav-item--active' : '');
$pendingNavClass = 'nav-item' . ($isPendingDischarge ? ' nav-item--active' : '');
$searchIndexes = [
  'all' => 'All fields',
  'name' => 'Name',
  'date' => 'Date',
  'diagnosis' => 'Diagnosis',
  'procedure' => 'Procedure Name',
  'implant' => 'Implant Used',
  'address' => 'Address',
  'phone' => 'Phone Number',
  'id' => 'ID',
  'complaint' => 'Chief Complaints',
];
?>

<?php if (!$hideSidebar): ?>
<aside class="app-sidebar<?= $isCaseView ? ' app-sidebar--collapsed sidebar--collapsed' : '' ?>" id="app-sidebar" role="complementary" aria-label="Main navigation"<?= $isCaseView ? ' data-default-collapsed="1"' : '' ?>>
  <div class="app-sidebar__inner">
    <div class="sidebar-header">
      <div class="app-brand">
        <div class="app-brand__name">Dr. Md. Tawfiq Alam Siddique</div>
        <div class="app-brand__subtitle">Clinical Registry</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-nav__group">
        <?php if (!$canSeeNav || $canSeeNav('page_dashboard')): ?>
        <a href="/dashboard/" class="<?php echo $dashboardNavClass; ?>">
          <i data-lucide="layout-dashboard" aria-hidden="true"></i>
          <span>Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if (!$canSeeNav || $canSeeNav('page_all_patients')): ?>
        <a href="/patients/<?= $isMedicalOfficerUser && !$isConsultantUser && !$isAdminUser ? '?view=admitted' : '' ?>" class="<?php echo $patientsNavClass; ?>">
          <i data-lucide="users" aria-hidden="true"></i>
          <span><?= $isMedicalOfficerUser && !$isConsultantUser && !$isAdminUser ? 'Admitted Patients' : 'All Patients' ?></span>
        </a>
        <?php endif; ?>
        <?php if (!$canSeeNav || $canSeeNav('page_pending_discharge')): ?>
        <a href="/patients/?view=pending" class="<?php echo $pendingNavClass; ?>">
          <i data-lucide="clipboard-list" aria-hidden="true"></i>
          <span>Pending Discharge</span>
          <?php if ($pendingDischargeCount > 0): ?>
          <strong class="sidebar-nav__count"><?php echo (int) $pendingDischargeCount; ?></strong>
          <?php endif; ?>
        </a>
        <?php endif; ?>
      </div>
    </nav>

  </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<?php endif; ?>

<header class="app-topbar<?= ($hideSidebar || $isCaseView) ? ' app-topbar--sidebar-collapsed' : '' ?>" id="app-topbar" role="banner">
  <div class="topbar-shell">
    <div class="topbar-left">
      <button type="button" class="btn btn--icon" aria-label="Open navigation" data-sidebar-toggle>
        <i data-lucide="menu" aria-hidden="true"></i>
      </button>
      <div class="topbar-user-meta">
        <span class="topbar-user-meta__name"><?php echo $sanitizer->entities($doctorName); ?></span>
        <span class="topbar-user-meta__subtitle">Clinical Registry</span>
      </div>
    </div>

    

    <div class="topbar-right">
      <span class="topbar-role-chip">
        <i data-lucide="user" aria-hidden="true"></i>
        <span><?php echo $sanitizer->entities($doctorName); ?></span>
        <strong class="topbar-role-chip__role"><?php echo $roleLabel; ?></strong>
      </span>
      <a href="/?logout=1" class="btn btn--neutral btn--icon" title="Sign out" aria-label="Sign out">
        <i data-lucide="log-out" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</header>
