<?php namespace ProcessWire;
/**
 * _admin-shell.php — Admin topbar (included by admin-panel.php)
 */
$adminUser = $user->name;
?>
<header class="admin-topbar" id="admin-topbar">
  <div class="admin-topbar__left">
    <button class="admin-topbar__toggle" id="admin-sidebar-toggle" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="admin-topbar__brand">
      <svg viewBox="0 0 24 24" width="20" height="20" stroke="#2563EB" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L3 7v6c0 5.25 3.75 10.15 9 11.25C17.25 23.15 21 18.25 21 13V7L12 2z"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
      <span>Admin Panel</span>
    </div>
  </div>

  <div class="admin-topbar__center">
    <div class="admin-search">
      <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Quick search..." class="admin-search__input" id="admin-global-search">
    </div>
  </div>

  <div class="admin-topbar__right">
    <a href="/dashboard/" class="admin-topbar__link">
      <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <div class="admin-topbar__user">
      <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      <span><?= htmlspecialchars($adminUser) ?></span>
      <strong class="admin-topbar__role-chip">SU</strong>
    </div>
    <a href="/?logout=1" class="admin-topbar__logout">
      <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</header>
