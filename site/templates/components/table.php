<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <?php foreach ($tableColumns as $column): ?>
        <th class="<?php
          if (!empty($column['sortable'])) {
            echo 'col-sort';
            if (!empty($column['active'])) echo ' col-sort--active col-sort--' . $column['dir'];
          } elseif (!empty($column['action'])) {
            echo 'cell--action';
          }
        ?>">
          <?php if (!empty($column['sortable'])): ?>
          <a href="<?= $column['href'] ?>"><?= $sanitizer->entities($column['label']) ?></a>
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
            <path d="M8 7L12 3L16 7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M12 3V21" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M16 17L12 21L8 17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
          <?php else: ?>
          <?= $sanitizer->entities($column['label']) ?>
          <?php endif; ?>
        </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($tableRows)): ?>
        <?php foreach ($tableRows as $row): ?>
        <tr class="row--clickable" data-href="<?= $sanitizer->entities($row['url']) ?>" onclick="window.location=this.dataset.href">
          <td class="cell" data-label="Name">
            <div class="dashboard-patient-cell">
              <span class="dashboard-patient-cell__name"><?= $sanitizer->entities($row['patient']) ?></span>
              <?php if (!empty($row['patient_id'])): ?>
              <span class="dashboard-patient-cell__meta">ID: <?= $sanitizer->entities($row['patient_id']) ?></span>
              <?php endif; ?>
            </div>
          </td>
          <td class="cell" data-label="IP Number"><strong><?= $sanitizer->entities($row['ip_number']) ?></strong></td>
          <td class="cell dashboard-diagnosis" data-label="Diagnosis" title="<?= $sanitizer->entities($row['diagnosis']) ?>"><?= $sanitizer->entities($row['diagnosis']) ?></td>
          <td class="cell" data-label="Operation Date"><?= $sanitizer->entities($row['operation_date'] ?? '-') ?></td>
          <td class="cell" data-label="Status"><span class="<?= $sanitizer->entities($row['status_class']) ?>"><?= $sanitizer->entities($row['status_label']) ?></span></td>
          <td class="cell" data-label="Admission Date"><?= $sanitizer->entities($row['admission_date']) ?></td>
          <td class="cell cell--action" data-label="Actions" onclick="event.stopPropagation()">
            <a class="btn btn--icon" href="<?= $sanitizer->entities($row['url']) ?>" aria-label="View patient" title="View patient">
              <i data-lucide="eye" aria-hidden="true"></i>
            </a>
            <a class="btn btn--icon" href="<?= $sanitizer->entities($row['edit_url']) ?>" aria-label="Edit patient" title="Edit patient">
              <i data-lucide="square-pen" aria-hidden="true"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (empty($tableRows)): ?>
      <tr>
        <td class="table-empty" colspan="<?= count($tableColumns) ?>">No records found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
