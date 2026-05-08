<?php namespace ProcessWire;
?>
<div id="content">
  <div class="page-header">
    <div class="page-header__title-group">
      <h1 class="t-page-heading">Form System Test</h1>
      <p class="t-body">Form layout module rendered inside the shared application shell.</p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary" type="button" data-modal-trigger="test-modal">Open Modal</button>
      <button class="btn btn--neutral" type="button" onclick="AppToast.show({ type: 'success', title: 'Success', message: 'The success toast is rendering correctly.' });">Show Success Toast</button>
      <button class="btn btn--neutral" type="button" onclick="AppToast.show({ type: 'error', title: 'Error', message: 'The error toast is rendering correctly.' });">Show Error Toast</button>
      <button class="btn btn--neutral" type="button" onclick="AppToast.show({ type: 'warning', title: 'Warning', message: 'The warning toast is rendering correctly.' });">Show Warning Toast</button>
      <button class="btn btn--neutral" type="button" onclick="AppToast.show({ type: 'info', title: 'Info', message: 'The info toast is rendering correctly.' });">Show Info Toast</button>
    </div>
  </div>

  <div class="page-body">
    <?php include('./components/form.php'); ?>
  </div>

  <div class="modal modal--md" data-modal="test-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="test-modal-title">
    <div class="modal__header">
      <h2 class="t-section-heading" id="test-modal-title">Test Modal</h2>
      <button class="btn btn--icon" type="button" data-modal-close aria-label="Close modal">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <div class="modal__body">
      <div class="field">
        <label class="field__label" for="modal-test-input">First Input</label>
        <input class="input" id="modal-test-input" type="text" placeholder="Focus lands here">
      </div>
      <div class="field" style="margin-top:16px;">
        <label class="field__label" for="modal-test-notes">Notes</label>
        <textarea class="textarea" id="modal-test-notes" placeholder="Use Tab to confirm focus stays inside the modal"></textarea>
      </div>
    </div>
    <div class="modal__footer">
      <button class="btn btn--neutral" type="button" data-modal-close>Cancel</button>
      <button class="btn btn--primary" type="button">Save</button>
    </div>
  </div>
</div>
