'use strict';

(function () {
  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function updateProgressCount() {
    var progress = document.querySelector('.case-progress');
    if (!progress) return;

    var completed = progress.querySelectorAll('.case-progress__item.is-complete').length;
    var total = progress.querySelectorAll('.case-progress__item').length;
    var counter = progress.querySelector('[data-progress-count]');
    if (counter) {
      counter.textContent = completed + '/' + total + ' complete';
    }
  }

  function setActiveStep(stepIdentifier) {
    var items = document.querySelectorAll('.case-progress__item');
    items.forEach(function (item) {
      item.classList.toggle('is-active', item.getAttribute('data-step') === String(stepIdentifier));
    });
  }

  function initSmoothScroll() {
    var links = document.querySelectorAll('.case-progress__link[href^="#module-"]');
    links.forEach(function (link) {
      link.addEventListener('click', function (event) {
        var targetSelector = link.getAttribute('href');
        var target = document.querySelector(targetSelector);
        if (!target) return;

        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  function initScrollSpy() {
    var modules = document.querySelectorAll('[data-module-step]');
    if (!modules.length || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      var visibleEntries = entries.filter(function (entry) {
        return entry.isIntersecting;
      });

      if (!visibleEntries.length) return;

      visibleEntries.sort(function (a, b) {
        return b.intersectionRatio - a.intersectionRatio;
      });

      var active = visibleEntries[0].target;
      setActiveStep(active.id || active.getAttribute('data-module-step'));
    }, {
      root: null,
      rootMargin: '-20% 0px -55% 0px',
      threshold: [0.2, 0.4, 0.6]
    });

    modules.forEach(function (module) {
      observer.observe(module);
    });
  }

  function initModuleCollapse() {
    var modules = document.querySelectorAll('.case-module');
    modules.forEach(function (module) {
      var header = module.querySelector(':scope > .card__header');
      if (!header) return;

      header.addEventListener('click', function (e) {
        if (e.target.closest('a, button, input, textarea, select')) return;

        module.classList.toggle('is-collapsed');
      });
    });
  }

  function applyWorkflowFieldConfig() {
    var configEl = document.getElementById('case-workflow-config');
    if (!configEl) return;
    var config;
    try { config = JSON.parse(configEl.textContent); } catch (e) { return; }

    document.querySelectorAll('[data-module-name]').forEach(function (moduleEl) {
      var moduleName = moduleEl.getAttribute('data-module-name');
      var moduleConf = config[moduleName];
      if (!moduleConf || !moduleConf.fields) return;

      moduleEl.querySelectorAll('[data-field]').forEach(function (input) {
        var fieldKey = input.getAttribute('data-field');
        var fieldConf = moduleConf.fields[fieldKey];
        if (!fieldConf) return;

        var wrapper = input.closest('.field') || input.parentElement;

        if (fieldConf.visible === false) {
          if (wrapper) wrapper.style.display = 'none';
          return;
        }
        if (fieldConf.editable === false) {
          if (input.tagName === 'SELECT') { input.disabled = true; }
          else { input.readOnly = true; input.style.opacity = '0.6'; }
        }
        if (fieldConf.mandatory === true) {
          input.required = true;
          var labelEl = wrapper ? wrapper.querySelector('.field__label') : null;
          if (labelEl && !labelEl.querySelector('.field__required')) {
            var star = document.createElement('span');
            star.className = 'field__required';
            star.textContent = '★';
            star.title = 'Required field';
            labelEl.appendChild(star);
          }
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateProgressCount();
    initSmoothScroll();
    initScrollSpy();
    initModuleCollapse();
    applyWorkflowFieldConfig();

    var firstModule = document.querySelector('[data-module-step]');
    if (firstModule) {
      setActiveStep(firstModule.id || firstModule.getAttribute('data-module-step'));
    }

  });
}());
