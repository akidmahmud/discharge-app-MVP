/**
 * Clinical Discharge & Surgical Registry System
 * main.js — Module 0: Project Foundation
 * Spec version: 1.0 FINAL
 */

'use strict';

/* ==============================================================
   LUCIDE ICON INITIALIZATION
   Runs after DOM is ready. Spec Part 5 — Lucide, stroke 1.75.
   ============================================================== */

document.addEventListener('DOMContentLoaded', function () {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons({
      attrs: {
        'stroke-width': 1.75
      }
    });
  }
});

document.addEventListener('DOMContentLoaded', function () {
  var toggle  = document.querySelector('[data-sidebar-toggle]');
  var sidebar = document.getElementById('app-sidebar');
  var main    = document.getElementById('app-main');
  var topbar  = document.getElementById('app-topbar');
  var overlay = document.getElementById('sidebar-overlay');

  if (!toggle || !sidebar) return;

  var isMobile = function () {
    return window.innerWidth <= 1024;
  };

  function openSidebar() {
    if (isMobile()) {
      sidebar.classList.add('sidebar--mobile-open');
      if (overlay) {
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
      }
      document.body.style.overflow = 'hidden';
    } else {
      if (sidebar.hasAttribute('data-default-collapsed')) {
        sidebar.classList.remove('app-sidebar--collapsed');
      }
      sidebar.classList.remove('sidebar--collapsed');
      if (main) main.classList.remove('app-main--sidebar-collapsed');
      if (topbar) topbar.classList.remove('app-topbar--sidebar-collapsed');
    }
  }

  function closeSidebar() {
    if (isMobile()) {
      sidebar.classList.remove('sidebar--mobile-open');
      if (overlay) {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
      }
      document.body.style.overflow = '';
    } else {
      if (sidebar.hasAttribute('data-default-collapsed')) {
        sidebar.classList.add('app-sidebar--collapsed');
      }
      sidebar.classList.add('sidebar--collapsed');
      if (main) main.classList.add('app-main--sidebar-collapsed');
      if (topbar) topbar.classList.add('app-topbar--sidebar-collapsed');
    }
  }

  toggle.addEventListener('click', function () {
    if (isMobile()) {
      sidebar.classList.contains('sidebar--mobile-open') ? closeSidebar() : openSidebar();
    } else {
      if (sidebar.classList.contains('sidebar--collapsed')) {
        openSidebar();
      } else {
        closeSidebar();
      }
    }
  });

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  window.addEventListener('resize', function () {
    if (!isMobile()) {
      sidebar.classList.remove('sidebar--mobile-open');
      if (overlay) {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
      }
      document.body.style.overflow = '';
    }
  });
});


/* ==============================================================
   GLOBAL DROPDOWN CONTRACT
   Spec Part 8.8 — applies to ALL dropdown menus.

   API:
     - Trigger element must have: data-dropdown-trigger="<id>"
     - Menu element must have:   data-dropdown-menu="<id>"
     - Optional chevron:         data-dropdown-chevron inside trigger

   Behavior:
     - Click trigger  → open menu, focus first item
     - Click outside  → close
     - Escape key     → close, return focus to trigger
     - Tab away       → close
     - Arrow keys     → navigate items
     - Enter/Space    → activate focused item
   ============================================================== */

(function () {

  /** Currently open dropdown ID, or null */
  var activeDropdown = null;

  /**
   * Open a dropdown by ID.
   * @param {string} id
   */
  function openDropdown(id) {
    var trigger = document.querySelector('[data-dropdown-trigger="' + id + '"]');
    var menu    = document.querySelector('[data-dropdown-menu="' + id + '"]');
    if (!trigger || !menu) return;

    closeAll();

    activeDropdown = id;
    menu.setAttribute('aria-hidden', 'false');
    menu.classList.add('dropdown--open');

    /* Trigger open-state tint */
    trigger.classList.add('dropdown-trigger--open');

    /* Rotate chevron if present */
    var chevron = trigger.querySelector('[data-dropdown-chevron]');
    if (chevron) chevron.classList.add('chevron--rotated');

    /* Focus first enabled item — spec 8.8 focus management */
    var firstItem = menu.querySelector(
      '[role="menuitem"]:not([aria-disabled="true"])'
    );
    if (firstItem) firstItem.focus();
  }

  /**
   * Close a dropdown by ID.
   * @param {string} id
   * @param {boolean} [returnFocus=true]
   */
  function closeDropdown(id, returnFocus) {
    var trigger = document.querySelector('[data-dropdown-trigger="' + id + '"]');
    var menu    = document.querySelector('[data-dropdown-menu="' + id + '"]');
    if (!trigger || !menu) return;

    menu.setAttribute('aria-hidden', 'true');
    menu.classList.remove('dropdown--open');
    trigger.classList.remove('dropdown-trigger--open');

    var chevron = trigger.querySelector('[data-dropdown-chevron]');
    if (chevron) chevron.classList.remove('chevron--rotated');

    if (returnFocus !== false) trigger.focus();
    if (activeDropdown === id) activeDropdown = null;
  }

  /** Close all open dropdowns without returning focus. */
  function closeAll() {
    if (activeDropdown) {
      closeDropdown(activeDropdown, false);
    }
    /* Belt-and-suspenders: clear any open menus not tracked */
    document.querySelectorAll('[data-dropdown-menu].dropdown--open')
      .forEach(function (menu) {
        var id = menu.getAttribute('data-dropdown-menu');
        closeDropdown(id, false);
      });
    activeDropdown = null;
  }

  /* ── Click: trigger toggle ─────────────────────────────── */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-dropdown-trigger]');

    if (trigger) {
      e.stopPropagation();
      var id = trigger.getAttribute('data-dropdown-trigger');
      if (activeDropdown === id) {
        closeDropdown(id);
      } else {
        openDropdown(id);
      }
      return;
    }

    /* Click outside any open menu → close */
    if (!e.target.closest('[data-dropdown-menu]')) {
      closeAll();
    }
  });

  /* ── Keyboard: Escape → close ──────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && activeDropdown) {
      closeDropdown(activeDropdown, true);
    }
  });

  /* ── Keyboard: Arrow navigation inside open menu ──────── */
  document.addEventListener('keydown', function (e) {
    if (!activeDropdown) return;
    var menu = document.querySelector(
      '[data-dropdown-menu="' + activeDropdown + '"]'
    );
    if (!menu) return;

    var items = Array.from(
      menu.querySelectorAll('[role="menuitem"]:not([aria-disabled="true"])')
    );
    if (!items.length) return;

    var focused = document.activeElement;
    var idx     = items.indexOf(focused);

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      var next = idx < items.length - 1 ? idx + 1 : 0;
      items[next].focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      var prev = idx > 0 ? idx - 1 : items.length - 1;
      items[prev].focus();
    } else if (e.key === 'Home') {
      e.preventDefault();
      items[0].focus();
    } else if (e.key === 'End') {
      e.preventDefault();
      items[items.length - 1].focus();
    } else if (e.key === 'Tab') {
      /* Tab away from open menu → close without returning focus */
      closeDropdown(activeDropdown, false);
    }
  });

  /* ── Item activation: Enter / Space ───────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var item = e.target.closest('[role="menuitem"]');
    if (!item) return;
    e.preventDefault();
    item.click();
  });

  /* ── Item click: close menu after selection ────────────── */
  document.addEventListener('click', function (e) {
    var item = e.target.closest('[role="menuitem"]');
    if (item && activeDropdown) {
      closeDropdown(activeDropdown, true);
    }
  });

  /* Expose for any inline usage */
  window.AppDropdown = { open: openDropdown, close: closeDropdown, closeAll: closeAll };

}());
