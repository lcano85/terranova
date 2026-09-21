<span class="badge text-bg-<?= Helpers::e($location['color']) ?>"><?= Helpers::e($location['label']) ?></span>
<?php if ($location['distance'] !== null): ?>
  <div class="small text-muted my-1"><?= Helpers::e(number_format($location['distance'], 1, ',', '.')) ?> m del local</div>
  <a class="btn btn-sm btn-outline-primary" href="<?= Helpers::e($location['url']) ?>" target="_blank" rel="noopener noreferrer">Ver en Google Maps</a>
<?php endif; ?>