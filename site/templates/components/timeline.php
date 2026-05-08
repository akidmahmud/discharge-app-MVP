<?php if (!empty($timelineItems)): ?>
<div class="timeline">
  <span class="timeline__connector" aria-hidden="true"></span>
  <?php foreach ($timelineItems as $item): ?>
    <?php $hasUrl = !empty($item['url']); ?>
    <?php if ($hasUrl): ?>
  <a class="timeline__item" data-timeline-type="<?php echo $sanitizer->entities($item['type']); ?>" href="<?php echo $sanitizer->entities($item['url']); ?>">
    <span class="timeline__node" aria-hidden="true"></span>
    <span class="timeline__content">
      <?php echo $sanitizer->entities($item['label']); ?>
      <span class="timeline__meta"><?php echo $sanitizer->entities($item['meta']); ?></span>
    </span>
  </a>
    <?php else: ?>
  <div class="timeline__item" data-timeline-type="<?php echo $sanitizer->entities($item['type']); ?>">
    <span class="timeline__node" aria-hidden="true"></span>
    <span class="timeline__content">
      <?php echo $sanitizer->entities($item['label']); ?>
      <span class="timeline__meta"><?php echo $sanitizer->entities($item['meta']); ?></span>
    </span>
  </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>
