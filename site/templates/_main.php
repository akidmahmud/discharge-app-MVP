<?php namespace ProcessWire;

/**
 * _main.php — Global app shell (appendTemplateFile)
 * All pages render inside this wrapper via ProcessWire markup regions.
 * Template files replace <div id="content"> with page-specific output.
 */

/** @var Page   $page */
/** @var Pages  $pages */
/** @var Config $config */

$tplUrl      = $config->urls->templates;
$isLoginPage = $page->template->name === 'home';
$isAdminPage = $page->template->name === 'admin-panel';
$mainClasses = ['app-main'];
if ($page->template->name === 'admission-record' || strpos($page->url, '/case-view/') === 0) {
  $mainClasses[] = 'app-main--sidebar-collapsed';
}
if ($isLoginPage)  { $mainClasses[] = 'app-main--login'; }
if ($isAdminPage)  { $mainClasses[] = 'app-main--admin'; }

?><!DOCTYPE html>
<html lang="en">
<head id="html-head">
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $sanitizer->entities($page->title); ?> — Clinical Registry</title>

  <!-- Design system stylesheet -->
  <link rel="stylesheet" href="<?php echo $tplUrl; ?>styles/main.css" />
  <link rel="stylesheet" href="<?php echo $tplUrl; ?>styles/mobile.css" />
  <?php if ($isLoginPage): ?>
  <link rel="stylesheet" href="<?php echo $tplUrl; ?>styles/login.css" />
  <?php endif; ?>
  <?php if ($isAdminPage): ?>
  <link rel="stylesheet" href="<?php echo $tplUrl; ?>styles/admin.css" />
  <?php endif; ?>

  <!-- Lucide icons (CDN) — stroke 1.75 applied via main.js init -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  <!-- Inter font is loaded via @import in main.css -->
</head>
<body id="html-body">

  <?php
  /*
   * _shell.php outputs:
   *   .app-sidebar  — fixed 240px left sidebar
   *   .app-topbar   — fixed 64px top bar
   * Template files may suppress shell output for special pages
   * (e.g. login, print) by setting $noShell = true before this file runs.
   */
  if (empty($noShell)) {
    include($config->paths->templates . '_shell.php');
  }
  ?>

  <!-- Main scrollable content region -->
  <main class="<?php echo implode(' ', $mainClasses); ?>" id="app-main">

    <!--
      Breadcrumb slot — spec Part 7.
      Shown on: Case View, Procedure Details, any 2+ level deep page.
      NOT shown on: Dashboard, Patient List.
      Templates that need breadcrumbs replace this region:
        <div id="breadcrumb">
          <nav class="app-breadcrumb-strip" aria-label="Breadcrumb">...</nav>
        </div>
    -->
    <div id="breadcrumb" class="app-breadcrumb-wrap"></div>

    <div class="app-content">
      <div class="app-container">

        <!-- Page templates replace this region via markup regions -->
        <div id="content">
          <!-- Default: no content -->
        </div>

      </div>
    </div>

  </main>

  <!-- Design token object (Module 1) — must load before main.js -->
  <script src="<?php echo $tplUrl; ?>scripts/tokens.js"></script>
  <!-- Global app behaviours (Module 0) -->
  <script src="<?php echo $tplUrl; ?>scripts/main.js"></script>
  <script src="<?php echo $tplUrl; ?>scripts/modal.js"></script>
  <script src="<?php echo $tplUrl; ?>scripts/toast.js"></script>
  
  <script src="<?php echo $tplUrl; ?>scripts/upload.js"></script>
  <?php if ($page->template->name === 'patients') { ?>
  <script src="https://cdn.jsdelivr.net/npm/fuse.js@7/dist/fuse.min.js"></script>
  <script src="<?php echo $tplUrl; ?>scripts/patients-search.js"></script>
  <?php } ?>
  <?php if ($page->template->name === 'admission-record' || $page->template->name === 'case-view') { ?>
  <script src="<?php echo $tplUrl; ?>scripts/case-engine.js"></script>
  <?php } ?>
  <?php if ($isAdminPage) { ?>
  <script src="<?php echo $tplUrl; ?>scripts/admin.js"></script>
  <?php } ?>

</body>
</html>
