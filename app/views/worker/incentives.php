<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Pagination.php';

$pagination = Pagination::paginateArray($incentives, 'incentives_page', 'incentives_per_page', [10, 20, 50]);
$rows = $pagination['rows'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Incentivos y bonos</h3>
        <div class="text-muted small">Consulta los incentivos publicados por la administración.</div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">No hay incentivos o bonos publicados actualmente.</div></div>
    <?php endif; ?>

    <div class="row g-3">
      <?php foreach ($rows as $item): ?>
        <div class="col-lg-6">
          <div class="card shadow-sm h-100 <?=(int)$item['is_active'] === 1 ? 'border-success' : 'border-secondary'?>">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <span class="badge text-bg-<?=(int)$item['is_active'] === 1 ? 'success' : 'secondary'?> mb-2"><?=(int)$item['is_active'] === 1 ? 'Activo' : 'Inactivo'?></span>
                  <h4 class="mb-0"><?=Helpers::e($item['title'])?></h4>
                </div>
                <div class="fs-4 fw-bold text-primary text-nowrap">S/ <?=number_format((float)$item['amount'], 2)?></div>
              </div>
              <h6>Información</h6>
              <div style="white-space:pre-line"><?=Helpers::e($item['worker_message'] ?: 'La administración no agregó un mensaje adicional.')?></div>
              <div class="text-muted small mt-3">Actualizado: <?=Helpers::e(date('d/m/Y H:i', strtotime($item['updated_at'])))?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?=Pagination::render($pagination['meta'])?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
