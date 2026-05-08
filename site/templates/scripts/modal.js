'use strict';

(function () {
  var activeModal = null;
  var previousFocus = null;
  var backdrop = null;

  var FOCUSABLE_SELECTOR = [
    'a[href]',
    'area[href]',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])',
    'iframe',
    'object',
    'embed',
    '[contenteditable]',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  function getBackdrop() {
    if (backdrop && document.body.contains(backdrop)) return backdrop;
    backdrop = document.querySelector('.modal-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop';
      backdrop.setAttribute('aria-hidden', 'true');
      document.body.appendChild(backdrop);
      backdrop.addEventListener('click', function () {
        closeAll();
      });
    }
    return backdrop;
  }

  function getModal(id) {
    return document.querySelector('[data-modal="' + id + '"]');
  }

  function getFocusable(modal) {
    return Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE_SELECTOR))
      .filter(function (element) {
        return element.offsetParent !== null || element === document.activeElement;
      });
  }

  function focusFirst(modal) {
    var input = modal.querySelector('input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled])');
    if (input) {
      input.focus();
      return;
    }

    var focusable = getFocusable(modal);
    if (focusable.length) {
      focusable[0].focus();
      return;
    }

    modal.focus();
  }

  function lockScroll() {
    document.body.classList.add('modal-open');
  }

  function unlockScroll() {
    document.body.classList.remove('modal-open');
  }

  function open(id) {
    var modal = getModal(id);
    if (!modal) return;

    closeAll(false);

    previousFocus = document.activeElement;
    activeModal = modal;

    getBackdrop().classList.add('is-open');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modal.setAttribute('tabindex', '-1');
    lockScroll();
    focusFirst(modal);
  }

  function close(id, returnFocus) {
    var modal = id ? getModal(id) : activeModal;
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    if (backdrop) {
      backdrop.classList.remove('is-open');
    }

    activeModal = null;
    unlockScroll();

    if (returnFocus !== false && previousFocus && typeof previousFocus.focus === 'function') {
      previousFocus.focus();
    }

    previousFocus = null;
  }

  function closeAll(returnFocus) {
    var openModals = document.querySelectorAll('.modal.is-open');
    openModals.forEach(function (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });

    if (backdrop) {
      backdrop.classList.remove('is-open');
    }

    activeModal = null;
    unlockScroll();

    if (returnFocus !== false && previousFocus && typeof previousFocus.focus === 'function') {
      previousFocus.focus();
    }

    previousFocus = null;
  }

  function trapFocus(event) {
    if (!activeModal || event.key !== 'Tab') return;

    var focusable = getFocusable(activeModal);
    if (!focusable.length) {
      event.preventDefault();
      activeModal.focus();
      return;
    }

    var first = focusable[0];
    var last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-modal-trigger]');
    if (trigger) {
      var targetId = trigger.getAttribute('data-modal-trigger');
      if (targetId) {
        open(targetId);
      }
      return;
    }

    var closeTrigger = event.target.closest('[data-modal-close]');
    if (closeTrigger && activeModal) {
      close(activeModal.getAttribute('data-modal'));
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && activeModal) {
      close(activeModal.getAttribute('data-modal'));
      return;
    }

    trapFocus(event);
  });

  window.AppModal = {
    open: open,
    close: close,
    closeAll: closeAll
  };
}());
