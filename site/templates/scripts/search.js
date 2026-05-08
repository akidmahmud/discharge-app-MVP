'use strict';

(function () {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function closeAll(except) {
    document.querySelectorAll('.search-bar[data-search-initialized="true"]').forEach(function (searchBar) {
      if (except && searchBar === except) return;
      var results = searchBar.querySelector('.search-results');
      if (!results) return;
      results.classList.remove('is-open');
      results.setAttribute('aria-hidden', 'true');
      results.innerHTML = '';
      searchBar.removeAttribute('data-search-active-index');
      searchBar.searchResultsList = [];
    });
  }

  function updateIcons() {
    if (typeof lucide !== 'undefined' && lucide && typeof lucide.createIcons === 'function') {
      lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
    }
  }

  function bindSearchBar(searchBar) {
    if (!searchBar || searchBar.getAttribute('data-search-initialized') === 'true') return;

    var input = searchBar.querySelector('.search-bar__input');
    var indexSelect = searchBar.querySelector('.search-bar__select');
    var clear = searchBar.querySelector('.search-bar__clear');
    var results = searchBar.querySelector('.search-results');
    if (!input || !clear || !results) return;

    var debounceTimer = null;
    var requestId = 0;
    searchBar.searchResultsList = [];
    searchBar.setAttribute('data-search-active-index', '-1');
    searchBar.setAttribute('data-search-initialized', 'true');

    function setActiveIndex(index) {
      searchBar.setAttribute('data-search-active-index', String(index));
      (searchBar.searchResultsList || []).forEach(function (item, itemIndex) {
        item.classList.toggle('is-active', itemIndex === index);
      });
    }

    function setClearVisibility() {
      clear.classList.toggle('is-visible', input.value.trim().length > 0);
    }

    function closeResults() {
      results.classList.remove('is-open');
      results.setAttribute('aria-hidden', 'true');
      results.innerHTML = '';
      searchBar.searchResultsList = [];
      setActiveIndex(-1);
    }

    function renderLoading() {
      closeAll(searchBar);
      results.innerHTML = '<div class="search-results__header">Patients</div><div class="search-results__loading">Searching...</div>';
      results.classList.add('is-open');
      results.setAttribute('aria-hidden', 'false');
      searchBar.searchResultsList = [];
      setActiveIndex(-1);
    }

    function renderResults(items) {
      closeAll(searchBar);

      if (!items.length) {
        results.innerHTML = '<div class="search-results__header">Patients</div><div class="search-results__empty">No matching records found.</div>';
        results.classList.add('is-open');
        results.setAttribute('aria-hidden', 'false');
        searchBar.searchResultsList = [];
        setActiveIndex(-1);
        return;
      }

      var html = '<div class="search-results__header">Patients</div>';
      items.forEach(function (item, index) {
        var meta = [item.mrn || 'No IP', item.ward || 'Ward pending', item.status || ''].filter(Boolean).join(' • ');
        html += ''
          + '<a class="search-result-item" href="' + escapeHtml(item.url || '/patients/') + '" data-search-index="' + index + '">'
          +   '<span class="search-result-item__title">' + escapeHtml(item.name || 'Unknown patient') + '</span>'
          +   '<span class="search-result-item__meta">' + escapeHtml(meta) + '</span>'
          + '</a>';
      });

      results.innerHTML = html;
      results.classList.add('is-open');
      results.setAttribute('aria-hidden', 'false');
      searchBar.searchResultsList = Array.prototype.slice.call(results.querySelectorAll('.search-result-item'));
      setActiveIndex(-1);
      updateIcons();
    }

    function performSearch(query) {
      if (!query || query.length < 2) {
        closeResults();
        return;
      }

      renderLoading();
      requestId += 1;
      var currentRequest = requestId;

      var indexValue = indexSelect ? indexSelect.value : 'all';
      fetch('/api/search/?q=' + encodeURIComponent(query) + '&index=' + encodeURIComponent(indexValue), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (response) {
          return response.ok ? response.json() : [];
        })
        .then(function (items) {
          if (currentRequest !== requestId) return;
          renderResults(Array.isArray(items) ? items : []);
        })
        .catch(function () {
          if (currentRequest !== requestId) return;
          renderResults([]);
        });
    }

    function scheduleSearch() {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        performSearch(input.value.trim());
      }, 250);
    }

    input.addEventListener('input', function () {
      setClearVisibility();
      scheduleSearch();
    });

    input.addEventListener('keydown', function (event) {
      var resultsList = searchBar.searchResultsList || [];
      var activeIndex = parseInt(searchBar.getAttribute('data-search-active-index') || '-1', 10);
      if (!results.classList.contains('is-open')) {
        if (event.key === 'Escape') closeResults();
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (resultsList.length) {
          activeIndex = activeIndex < resultsList.length - 1 ? activeIndex + 1 : 0;
          setActiveIndex(activeIndex);
        }
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (resultsList.length) {
          activeIndex = activeIndex > 0 ? activeIndex - 1 : resultsList.length - 1;
          setActiveIndex(activeIndex);
        }
      } else if (event.key === 'Enter') {
        if (activeIndex >= 0 && resultsList[activeIndex]) {
          event.preventDefault();
          window.location.href = resultsList[activeIndex].getAttribute('href');
        }
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeResults();
      }
    });

    clear.addEventListener('click', function () {
      input.value = '';
      setClearVisibility();
      closeResults();
      input.focus();
    });

    results.addEventListener('mousemove', function (event) {
      var item = event.target.closest('.search-result-item');
      if (!item) return;
      setActiveIndex(Number(item.getAttribute('data-search-index')));
    });

    setClearVisibility();
  }

  function init() {
    document.querySelectorAll('.search-bar').forEach(bindSearchBar);
    updateIcons();
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.search-bar')) {
      closeAll();
    }
  });

  document.addEventListener('DOMContentLoaded', init);

  window.AppSearch = {
    init: init,
    closeAll: closeAll
  };
}());
