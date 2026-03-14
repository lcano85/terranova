<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Dashboard</h3>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="text-muted">Trabajadores</div>
            <div class="fs-2 fw-bold"><?= (int)$workersCount ?></div>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-bold">Marcación pública</div>
                <div class="text-muted small">Comparte este link para marcar asistencia</div>
              </div>
              <a class="btn btn-outline-primary" href="/attendance/mark" target="_blank">Abrir</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fw-bold mb-2">Últimas marcaciones</div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Documento</th>
                <th>Trabajador</th>
                <th>Tipo</th>
                <th>Fecha/Hora</th>
                <th>Tardanza</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($latest as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= Helpers::e($r['document_number']) ?></td>
                  <td><?= Helpers::e($r['first_name'].' '.$r['last_name']) ?></td>
                  <td>
                    <span class="badge text-bg-<?= $r['mark_type']==='in'?'success':'secondary' ?>">
                      <?= $r['mark_type']==='in'?'Entrada':'Salida' ?>
                    </span>
                  </td>
                  <td><?= Helpers::e($r['marked_at']) ?></td>
                  <td><?= (int)$r['minutes_late'] ?> min</td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($latest)): ?>
                <tr><td colspan="6" class="text-muted">Sin registros</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
