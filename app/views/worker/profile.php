<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Mi perfil</h3>

    <div class="card shadow-sm" style="max-width: 720px;">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Nombres</div>
            <div class="fw-semibold"><?= Helpers::e($user['first_name'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Apellidos</div>
            <div class="fw-semibold"><?= Helpers::e($user['last_name'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Documento</div>
            <div class="fw-semibold"><?= Helpers::e(($user['document_type'] ?? '').' '.($user['document_number'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Rol</div>
            <div class="fw-semibold text-uppercase"><?= Helpers::e($user['role'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Área</div>
            <div class="fw-semibold"><?= Helpers::e($user['area_name'] ?? '-') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Pago diario</div>
            <div class="fw-semibold"><?= isset($dailyRate) && $dailyRate !== null ? 'S/ '.number_format((float)$dailyRate,2) : '-' ?></div>
          </div>
        </div>

        
        <hr class="my-4">

        <h5 class="mb-3">Resumen del mes</h5>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Rango</div>
              <div class="fw-semibold"><?= Helpers::e(date('d/m/Y', strtotime($from)).' - '.date('d/m/Y', strtotime($to))) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Días trabajados</div>
              <div class="fw-semibold"><?= (int)($summary['worked_days'] ?? 0) ?> / <?= (int)$totalWorkDays ?></div>
              <div class="text-muted small">Ausentes: <?= (int)$absent ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Horas trabajadas</div>
              <div class="fw-semibold"><?= number_format((float)$hours, 2) ?> h</div>
              <div class="text-muted small">Tardanza total: <?= (int)($summary['total_minutes_late'] ?? 0) ?> min</div>
            </div>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Bruto</div>
              <div class="fw-semibold"><?= $gross !== null ? 'S/ '.number_format((float)$gross,2) : '-' ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Descuento por tardanza</div>
              <div class="fw-semibold"><?= $gross !== null ? 'S/ '.number_format((float)$discount,2) : '-' ?></div>
              <div class="text-muted small">Proporcional al minuto</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Neto estimado</div>
              <div class="fw-semibold"><?= $net !== null ? 'S/ '.number_format((float)$net,2) : '-' ?></div>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
          Nota: un día sin marcación se considera <b>ausente</b>. Si necesitas reglas especiales (feriados, domingos, permisos, etc.), lo ajustamos.
        </div>

      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
