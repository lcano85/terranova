<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Pagination.php';

$workerDashboardPagination = Pagination::paginateArray($rows, 'worker_dashboard_page', 'worker_dashboard_per_page');
$rows = $workerDashboardPagination['rows'];
$workerDashboardPaginationMeta = $workerDashboardPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Mi dashboard</h3>

    <div class="card shadow-sm mb-3" style="max-width: 900px;">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Trabajador</div>
            <div class="fw-semibold"><?= Helpers::e(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Documento</div>
            <div class="fw-semibold"><?= Helpers::e(($user['document_type'] ?? '').' '.($user['document_number'] ?? '')) ?></div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-secondary" href="/worker/inventory">Mi inventario</a>
          <a class="btn btn-outline-primary" href="/worker/attendance">Ver mi asistencia</a>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-bold mb-2">Mis últimas marcaciones</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Fecha/Hora</th>
                <th>Tardanza</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <span class="badge text-bg-<?= $r['mark_type']==='in'?'success':'secondary' ?>">
                      <?= $r['mark_type']==='in'?'Entrada':'Salida' ?>
                    </span>
                  </td>
                  <td><?= Helpers::e($r['marked_at']) ?></td>
                  <td><?= (int)$r['minutes_late'] ?> min</td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($rows)): ?>
                <tr><td colspan="4" class="text-muted">Aún no tienes marcaciones</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?= Pagination::render($workerDashboardPaginationMeta) ?>

      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
