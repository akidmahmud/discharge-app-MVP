/* admin.js — Admin panel interactivity */

(function () {
  'use strict';

  // ── Sidebar toggle ──────────────────────────────────────────────────────────
  var sidebarToggle = document.getElementById('admin-sidebar-toggle');
  var sidebar       = document.getElementById('admin-sidebar');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('is-collapsed');
    });
  }

  // ── Tabs ────────────────────────────────────────────────────────────────────
  document.querySelectorAll('.admin-tabs').forEach(function (tabGroup) {
    var tabs   = tabGroup.querySelectorAll('.admin-tab');
    var panels = document.querySelectorAll('.admin-tab-panel');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.dataset.tab;

        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');

        panels.forEach(function (p) {
          p.classList.toggle('is-active', p.id === target);
        });
      });
    });
  });

  // ── Modal helpers ───────────────────────────────────────────────────────────
  function openModal(id) {
    var overlay = document.getElementById(id);
    if (overlay) overlay.classList.add('is-open');
  }
  function closeModal(id) {
    var overlay = document.getElementById(id);
    if (overlay) overlay.classList.remove('is-open');
  }

  // Open buttons
  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.dataset.modalOpen);
    });
  });

  // Close buttons
  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeModal(btn.dataset.modalClose);
    });
  });

  // Click overlay to close
  document.querySelectorAll('.admin-modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.classList.remove('is-open');
    });
  });

  // ── Delete confirmation ─────────────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      var msg = el.dataset.confirm || 'Are you sure you want to delete this?';
      if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });

  // ── Toast notifications ─────────────────────────────────────────────────────
  function showToast(msg, type) {
    var colours = { success: '#16A34A', error: '#DC2626', info: '#2563EB' };
    var toast = document.createElement('div');
    toast.style.cssText = [
      'position:fixed', 'bottom:24px', 'right:24px',
      'background:' + (colours[type] || colours.info),
      'color:#fff', 'padding:12px 20px', 'border-radius:8px',
      'font-size:13.5px', 'font-weight:500',
      'box-shadow:0 8px 24px rgba(0,0,0,0.15)',
      'z-index:999', 'opacity:0', 'transition:opacity 0.2s',
      'max-width:320px', 'line-height:1.4',
    ].join(';');
    toast.textContent = msg;
    document.body.appendChild(toast);

    requestAnimationFrame(function () {
      toast.style.opacity = '1';
      setTimeout(function () {
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 200);
      }, 3000);
    });
  }
  window.adminToast = showToast;

  // Auto-show toast from URL param (?saved=1, ?deleted=1, ?error=1)
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('saved')   === '1') showToast('Changes saved successfully.', 'success');
    if (params.get('deleted') === '1') showToast('Record deleted.', 'success');
    if (params.get('created') === '1') showToast('Record created successfully.', 'success');
    if (params.get('error')   === '1') showToast('An error occurred. Please try again.', 'error');
  })();

  // ── Drag-and-drop for workflow ordering ─────────────────────────────────────
  var dragSrc = null;

  document.querySelectorAll('[data-draggable]').forEach(function (row) {
    row.setAttribute('draggable', 'true');

    row.addEventListener('dragstart', function (e) {
      dragSrc = row;
      row.style.opacity = '0.5';
      e.dataTransfer.effectAllowed = 'move';
    });

    row.addEventListener('dragend', function () {
      row.style.opacity = '';
      document.querySelectorAll('[data-draggable]').forEach(function (r) {
        r.classList.remove('drag-over');
      });
    });

    row.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      row.classList.add('drag-over');
    });

    row.addEventListener('dragleave', function () {
      row.classList.remove('drag-over');
    });

    row.addEventListener('drop', function (e) {
      e.preventDefault();
      row.classList.remove('drag-over');
      if (dragSrc && dragSrc !== row) {
        var parent = row.parentNode;
        var srcIdx = Array.from(parent.children).indexOf(dragSrc);
        var tgtIdx = Array.from(parent.children).indexOf(row);
        if (srcIdx < tgtIdx) {
          parent.insertBefore(dragSrc, row.nextSibling);
        } else {
          parent.insertBefore(dragSrc, row);
        }
        updateSortOrder();
      }
    });
  });

  function updateSortOrder() {
    var rows = document.querySelectorAll('[data-draggable]');
    var orderInput = document.getElementById('workflow-order');
    if (!orderInput) return;
    var ids = Array.from(rows).map(function (r) { return r.dataset.id; });
    orderInput.value = ids.join(',');
  }

  // ── Auto-dismiss alerts ─────────────────────────────────────────────────────
  document.querySelectorAll('.admin-alert[data-auto-dismiss]').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4000);
  });

  // ── Lucide icons init ───────────────────────────────────────────────────────
  if (window.lucide) lucide.createIcons();

})();
