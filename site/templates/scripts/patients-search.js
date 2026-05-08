'use strict';

(function () {
  var fuse = null;
  var allData = [];
  var tbody = null;
  var input = null;
  var counter = null;
  var rows = [];
  var idToRow = {};

  function init() {
    tbody = document.querySelector('.data-table tbody');
    input = document.querySelector('input[name="q"]');
    counter = document.getElementById('patients-fuzzy-count');

    if (!tbody) { console.warn('[patients-search] .data-table tbody not found'); return; }
    if (typeof Fuse === 'undefined') { console.warn('[patients-search] Fuse.js not loaded'); return; }

    allData = window.PATIENTS_DATA || [];
    if (!allData.length) return;

    rows = Array.from(tbody.querySelectorAll('tr.row--clickable'));
    if (!rows.length) return;

    rows.forEach(function (r, i) {
      if (allData[i] && allData[i].url) idToRow[allData[i].url] = r;
    });

    fuse = new Fuse(allData, {
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

    if (input) {
      input.addEventListener('input', function () { applyFilter(this.value); });
      if (input.value.trim()) applyFilter(input.value);
      var form = input.closest('form');
      if (form) {
        form.addEventListener('submit', function (e) {
          if (input.value.trim().length > 0) e.preventDefault();
        });
      }
    }
  }

  function applyFilter(q) {
    if (!rows.length) return;
    q = (q || '').trim();

    if (!q) {
      rows.forEach(function (r) { r.style.display = ''; tbody.appendChild(r); });
      if (counter) counter.textContent = '';
      return;
    }

    var results = fuse.search(q);
    var matchedSet = new Set(results.map(function (r) { return r.item.url; }));

    results.forEach(function (result) {
      var row = idToRow[result.item.url];
      if (row) { row.style.display = ''; tbody.appendChild(row); }
    });

    rows.forEach(function (r, i) {
      if (allData[i] && !matchedSet.has(allData[i].url)) {
        r.style.display = 'none';
        tbody.appendChild(r);
      }
    });

    if (counter) counter.textContent = results.length + ' match' + (results.length !== 1 ? 'es' : '');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());