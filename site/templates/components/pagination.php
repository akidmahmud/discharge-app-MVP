<?php
$totalPages = (int) ceil($total / $perPage);
$showPagination = $totalPages > 1;
$startItem = $total ? (($currentPage - 1) * $perPage) + 1 : 0;
$endItem = min($total, $currentPage * $perPage);

$buildPageUrl = function (int $pageNumber) use ($baseUrl, $paginationParams): string {
  $params = $paginationParams;
  if ($pageNumber > 1) {
    $params['page'] = $pageNumber;
  } else {
    unset($params['page']);
  }
  $queryString = http_build_query($params);
  return $baseUrl . ($queryString ? '?' . $queryString : '');
};

$pageItems = [];
if ($totalPages <= 7) {
  for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
    $pageItems[] = $pageNumber;
  }
} elseif ($currentPage <= 3) {
  $pageItems = [1, 2, 3, 4, 5, 'ellipsis', $totalPages];
} elseif ($currentPage >= $totalPages - 2) {
  $pageItems = [1, 'ellipsis', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
} else {
  $pageItems = [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $totalPages];
}
?>
<?php if ($total > 0): ?>
<nav class="pagination" aria-label="Pagination">
  <div class="pagination__info">Showing <?= $startItem ?>-<?= $endItem ?> of <?= $total ?> patients</div>
  <?php if ($showPagination): ?>
  <div class="pagination__controls">
    <?php if ($currentPage > 1): ?>
    <a class="pagination__btn" href="<?= $buildPageUrl($currentPage - 1) ?>">Prev</a>
    <?php else: ?>
    <span class="pagination__btn" aria-disabled="true">Prev</span>
    <?php endif; ?>

    <?php foreach ($pageItems as $item): ?>
      <?php if ($item === 'ellipsis'): ?>
      <span class="pagination__btn" aria-disabled="true">...</span>
      <?php else: ?>
        <?php if ($item === $currentPage): ?>
        <span class="pagination__btn" aria-current="page"><?= $item ?></span>
        <?php else: ?>
        <a class="pagination__btn" href="<?= $buildPageUrl($item) ?>"><?= $item ?></a>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($currentPage < $totalPages): ?>
    <a class="pagination__btn" href="<?= $buildPageUrl($currentPage + 1) ?>">Next</a>
    <?php else: ?>
    <span class="pagination__btn" aria-disabled="true">Next</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</nav>
<?php endif; ?>
