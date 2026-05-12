'use strict';

(function () {
  function escapeAttr(value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function isImageFile(file) {
    if (file && file.type) return file.type.indexOf('image/') === 0;
    var ext = (file && file.name ? file.name : '').split('.').pop().toLowerCase();
    return ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'gif' || ext === 'webp' || ext === 'bmp' || ext === 'svg';
  }

  function renderResults(zone, files) {
    var results = zone.querySelector('.case-upload-zone__results');
    if (!results) return;
    if (!files.length) {
      results.innerHTML = '';
      return;
    }

    results.innerHTML = files.map(function (file) {
      if (file.thumb) {
        return ''
          + '<div class="case-upload-result">'
          +   '<img src="' + escapeAttr(file.thumb) + '" alt="">'
          +   '<a href="' + escapeAttr(file.url) + '" target="_blank" class="case-upload-result__link">' + escapeAttr(file.name) + '</a>'
          + '</div>';
      }
      return ''
        + '<div class="case-upload-result">'
        +   '<span class="case-upload-result__icon"><i data-lucide="file-text" aria-hidden="true" style="width:24px;height:24px"></i></span>'
        +   '<a href="' + escapeAttr(file.url) + '" target="_blank" class="case-upload-result__link">' + escapeAttr(file.name) + '</a>'
        + '</div>';
    }).join('');

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
    }
  }

  function renderPending(zone, files) {
    var results = zone.querySelector('.case-upload-zone__results');
    if (!results) return;
    results.innerHTML = '';
    Array.prototype.forEach.call(files, function (file) {
      var isImage = isImageFile(file);
      var row = document.createElement('div');
      row.className = 'case-upload-result case-upload-result--pending';

      if (isImage) {
        var img = document.createElement('img');
        img.className = 'case-upload-result__preview-pending';
        var reader = new FileReader();
        reader.onload = function (e) {
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        row.appendChild(img);
      } else {
        var iconWrap = document.createElement('span');
        iconWrap.className = 'case-upload-result__icon';
        iconWrap.innerHTML = '<i data-lucide="file-text" aria-hidden="true" style="width:24px;height:24px"></i>';
        row.appendChild(iconWrap);
      }

      var name = document.createElement('span');
      name.textContent = file.name;
      row.appendChild(name);

      results.appendChild(row);
    });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
    }
  }

  function populateExisting(zone) {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
    }
  }

  function uploadFiles(zone, files) {
    if (!files || !files.length) return;
    var caseLayout = document.querySelector('.case-layout[data-case-id]');
    if (!caseLayout) return;
    var caseId = caseLayout.getAttribute('data-case-id');
    var formData = new FormData();
    Array.prototype.forEach.call(files, function (file) {
      formData.append('files[]', file);
    });
    formData.append('zone', zone.getAttribute('data-upload-zone') || '');
    formData.append('case_id', caseId);

    var results = zone.querySelector('.case-upload-zone__results');
    if (results) {
      renderPending(zone, files);
    }

    fetch('/api/upload/', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (response) {
        return response.ok ? response.json() : { success: false, files: [] };
      })
      .then(function (payload) {
        if (!payload.success) {
          if (results) {
            results.innerHTML = '<div class="t-meta" style="color:var(--color-danger,#dc2626)">Upload failed — field not available on this case.</div>';
          }
          return;
        }
        renderResults(zone, payload.files || []);
        if (window.AppToast) {
          window.AppToast.show({
            type: 'success',
            title: 'Upload complete',
            message: 'Files were attached to the case successfully.'
          });
        }
      })
      .catch(function () {
        if (results) {
          results.innerHTML = '<div class="t-meta" style="color:var(--color-danger,#dc2626)">Upload failed — server error.</div>';
        }
      });
  }

  function init() {
    // Global toggle for upload zones
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-toggle-upload]');
      if (!trigger) return;
      e.preventDefault();
      e.stopPropagation();
      var targetSelector = trigger.getAttribute('data-toggle-upload');
      var moduleEl = document.querySelector(targetSelector);
      if (!moduleEl) return;
      var zone = moduleEl.querySelector('[data-upload-zone]');
      if (!zone) return;

      var opening = !zone.classList.contains('is-active');
      zone.classList.toggle('is-active', opening);
      trigger.classList.toggle('btn--active', opening);
      zone.style.display = opening ? 'flex' : 'none';
    });

    document.querySelectorAll('[data-upload-zone]').forEach(function (zone) {
      var input = zone.querySelector('input[type="file"]');
      if (!input) return;

      populateExisting(zone);

      zone.addEventListener('dragover', function (event) {
        event.preventDefault();
        zone.classList.add('is-dragover');
      });

      zone.addEventListener('dragleave', function () {
        zone.classList.remove('is-dragover');
      });

      zone.addEventListener('drop', function (event) {
        event.preventDefault();
        zone.classList.remove('is-dragover');
        uploadFiles(zone, event.dataTransfer.files);
      });

      input.addEventListener('change', function () {
        uploadFiles(zone, input.files);
        input.value = '';
      });
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();