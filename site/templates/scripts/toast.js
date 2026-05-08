'use strict';

(function () {
  var nextToastId = 1;
  var maxVisible = 3;
  var visibleToasts = [];
  var queue = [];

  function getRegion() {
    var region = document.querySelector('.toast-region');
    if (region) return region;

    region = document.createElement('div');
    region.className = 'toast-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    document.body.appendChild(region);
    return region;
  }

  function getVariant(type) {
    switch (type) {
      case 'success':
        return {
          className: 'toast--success',
          icon: 'check-circle'
        };
      case 'error':
        return {
          className: 'toast--error',
          icon: 'x-circle'
        };
      case 'warning':
        return {
          className: 'toast--warning',
          icon: 'alert-triangle'
        };
      default:
        return {
          className: 'toast--info',
          icon: 'info'
        };
    }
  }

  function renderToast(config) {
    var variant = getVariant(config.type);
    var toast = document.createElement('div');
    toast.className = 'toast ' + variant.className;
    toast.setAttribute('data-toast-id', String(config.id));
    toast.setAttribute('role', 'status');

    var icon = document.createElement('div');
    icon.className = 'toast__icon';
    icon.innerHTML = '<i data-lucide="' + variant.icon + '" style="width:18px;height:18px;"></i>';

    var content = document.createElement('div');
    content.className = 'toast__content';
    content.innerHTML =
      '<div class="t-body-medium">' + escapeHtml(config.title || 'Notification') + '</div>' +
      (config.message
        ? '<div class="t-body" style="margin-top:4px;color:var(--color-text-secondary);">' + escapeHtml(config.message) + '</div>'
        : '');

    var close = document.createElement('button');
    close.className = 'toast__close btn btn--icon';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.innerHTML = '<i data-lucide="x" style="width:14px;height:14px;"></i>';

    var progress = document.createElement('div');
    progress.className = 'toast__progress';

    toast.appendChild(icon);
    toast.appendChild(content);
    toast.appendChild(close);
    toast.appendChild(progress);

    close.addEventListener('click', function () {
      dismiss(config.id);
    });

    return {
      element: toast,
      progress: progress
    };
  }

  function animateProgress(record) {
    if (!record.progress) return;
    record.progress.style.transition = 'none';
    record.progress.style.transform = 'scaleX(1)';
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        record.progress.style.transition = 'transform ' + record.remaining + 'ms linear';
        record.progress.style.transform = 'scaleX(0)';
      });
    });
  }

  function startTimer(record) {
    clearTimeout(record.timer);
    record.startedAt = Date.now();
    animateProgress(record);
    record.timer = setTimeout(function () {
      dismiss(record.id);
    }, record.remaining);
  }

  function pauseTimer(record) {
    if (!record || record.paused) return;
    record.paused = true;
    clearTimeout(record.timer);
    record.remaining = Math.max(0, record.remaining - (Date.now() - record.startedAt));
    if (record.progress) {
      var total = record.duration || 1;
      var scale = Math.max(0, Math.min(1, record.remaining / total));
      record.progress.style.transition = 'none';
      record.progress.style.transform = 'scaleX(' + scale + ')';
    }
  }

  function resumeTimer(record) {
    if (!record || !record.paused) return;
    record.paused = false;
    startTimer(record);
  }

  function bindInteractions(record) {
    record.element.addEventListener('mouseenter', function () {
      pauseTimer(record);
    });

    record.element.addEventListener('mouseleave', function () {
      resumeTimer(record);
    });
  }

  function activateToast(record) {
    var region = getRegion();
    var rendered = renderToast(record);
    record.element = rendered.element;
    record.progress = rendered.progress;
    bindInteractions(record);
    region.appendChild(record.element);
    visibleToasts.push(record);

    if (typeof lucide !== 'undefined') {
      lucide.createIcons({
        attrs: {
          'stroke-width': 1.75
        }
      });
    }

    startTimer(record);
  }

  function maybeFlushQueue() {
    if (visibleToasts.length >= maxVisible || !queue.length) return;
    var next = queue.shift();
    activateToast(next);
  }

  function dismiss(id) {
    var matchIndex = visibleToasts.findIndex(function (toast) {
      return toast.id === id;
    });

    if (matchIndex === -1) {
      queue = queue.filter(function (toast) {
        return toast.id !== id;
      });
      return;
    }

    var record = visibleToasts[matchIndex];
    clearTimeout(record.timer);

    if (record.element && record.element.parentNode) {
      record.element.parentNode.removeChild(record.element);
    }

    visibleToasts.splice(matchIndex, 1);
    maybeFlushQueue();
  }

  function show(options) {
    var config = options || {};
    var record = {
      id: nextToastId++,
      type: config.type || 'info',
      title: config.title || 'Notification',
      message: config.message || '',
      duration: typeof config.duration === 'number' ? config.duration : 4000,
      remaining: typeof config.duration === 'number' ? config.duration : 4000,
      timer: null,
      paused: false,
      startedAt: 0,
      element: null,
      progress: null
    };

    if (visibleToasts.length < maxVisible) {
      activateToast(record);
    } else {
      queue.push(record);
    }

    return record.id;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  window.AppToast = {
    show: show,
    dismiss: dismiss
  };
}());
