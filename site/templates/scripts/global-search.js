/**
 * global-search.js
 * Implements global fuzzy search in the topbar and syncs with patients page search.
 */

'use strict';

(function () {
  var fuse = null;
  var input = document.getElementById('topbar-search-q');
  var resultsContainer = document.getElementById('topbar-search-results');
  var patientsPageInput = document.getElementById('patients-search-q');
  var toggleBtn = document.getElementById('topbar-search-toggle');
  var closeBtn = document.getElementById('topbar-search-close');
  var container = document.getElementById('topbar-search-container');

  // Mobile toggle — wired up independently so it works even without Fuse/data
  function initMobileToggle() {
    if (!toggleBtn || !container) return;

    toggleBtn.addEventListener('click', function () {
      container.classList.add('is-active');
      if (input) input.focus();
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        container.classList.remove('is-active');
        if (input) {
          input.value = '';
          hideResults();
        }
        if (patientsPageInput) {
          patientsPageInput.value = '';
          patientsPageInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    }

    document.addEventListener('click', function (e) {
      if (!container || !container.classList.contains('is-active')) return;
      var inside = container.contains(e.target) || toggleBtn.contains(e.target);
      if (!inside) {
        container.classList.remove('is-active');
        hideResults();
      }
    });
  }

  function init() {
    if (!input || !resultsContainer) return;
    if (typeof Fuse === 'undefined') return;

    var data = window.GLOBAL_SEARCH_DATA || [];
    if (!data.length) return;

    fuse = new Fuse(data, {
      keys: [
        { name: 'name', weight: 0.4 },
        { name: 'pid',  weight: 0.3 },
        { name: 'ip',   weight: 0.2 },
        { name: 'diag', weight: 0.1 },
      ],
      threshold: 0.4,
      includeScore: true,
      ignoreLocation: true,
    });

    input.addEventListener('input', function () {
      var q = this.value.trim();

      if (patientsPageInput) {
        patientsPageInput.value = this.value;
        patientsPageInput.dispatchEvent(new Event('input', { bubbles: true }));
      }

      if (q.length < 2) {
        hideResults();
        return;
      }

      var results = fuse.search(q).slice(0, 8);
      renderResults(results);
    });

    var form = input.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
      });
    }
  }

  function renderResults(results) {
    if (!results.length) {
      resultsContainer.innerHTML = '<div class="search-results__empty">No patients found.</div>';
    } else {
      var html = '<div class="search-results__header">Matching Patients</div>';
      results.forEach(function (result) {
        var item = result.item;
        html += '<a href="' + item.url + '" class="search-result-item">' +
                  '<span class="search-result-item__title">' + escapeHtml(item.name) + '</span>' +
                  '<span class="search-result-item__meta">ID: ' + escapeHtml(item.pid) + ' | IP: ' + escapeHtml(item.ip) + '</span>' +
                  '<span class="search-result-item__meta">' + escapeHtml(item.diag) + '</span>' +
                '</a>';
      });
      resultsContainer.innerHTML = html;
    }
    resultsContainer.classList.add('is-open');
  }

  function hideResults() {
    if (!resultsContainer) return;
    resultsContainer.classList.remove('is-open');
    resultsContainer.innerHTML = '';
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initMobileToggle();
      init();
    });
  } else {
    initMobileToggle();
    init();
  }
})();
