<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Mi perfil</h3>

    <div class="card shadow-sm" style="max-width: 960px;">
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
            <div class="fw-semibold"><?= Helpers::e(($user['document_type'] ?? '') . ' ' . ($user['document_number'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Rol</div>
            <div class="fw-semibold text-uppercase"><?= Helpers::e($user['role'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Area</div>
            <div class="fw-semibold"><?= Helpers::e($user['area_name'] ?? '-') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Pago diario</div>
            <div class="fw-semibold"><?= isset($dailyRate) && $dailyRate !== null ? 'S/ ' . number_format((float)$dailyRate, 2) : '-' ?></div>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Resumen del mes</h5>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Rango</div>
              <div class="fw-semibold"><?= Helpers::e(date('d/m/Y', strtotime($from)) . ' - ' . date('d/m/Y', strtotime($to))) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Dias trabajados</div>
              <div class="fw-semibold"><?= (int)$workedDays ?> / <?= (int)$totalWorkDays ?></div>
              <div class="text-muted small">Sin marcar hasta hoy: <?= (int)$absent ?> de <?= (int)$elapsedWorkDays ?></div>
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
              <div class="text-muted small">Bruto mensual</div>
              <div class="fw-semibold"><?= $monthPay['gross'] !== null ? 'S/ ' . number_format((float)$monthPay['gross'], 2) : '-' ?></div>
              <div class="text-muted small"><?= (int)$totalWorkDays ?> dias laborables del mes</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Descuento por tardanza</div>
              <div class="fw-semibold"><?= $monthPay['discount'] !== null ? 'S/ ' . number_format((float)$monthPay['discount'], 2) : '-' ?></div>
              <div class="text-muted small">Calculado por minuto</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Neto mensual</div>
              <div class="fw-semibold"><?= $monthPay['net'] !== null ? 'S/ ' . number_format((float)$monthPay['net'], 2) : '-' ?></div>
            </div>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Pago por quincena</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Primera quincena</div>
              <div class="fw-semibold mb-2"><?= Helpers::e(date('d/m/Y', strtotime($firstHalfFrom)) . ' - ' . date('d/m/Y', strtotime($firstHalfTo))) ?></div>
              <div class="small text-muted">Dias laborables: <?= (int)$firstHalfScheduledDays ?></div>
              <div class="small text-muted">Tardanza: <?= (int)($firstHalfSummary['total_minutes_late'] ?? 0) ?> min</div>
              <div class="mt-2">Bruto: <span class="fw-semibold"><?= $firstHalfPay['gross'] !== null ? 'S/ ' . number_format((float)$firstHalfPay['gross'], 2) : '-' ?></span></div>
              <div>Descuento: <span class="fw-semibold"><?= $firstHalfPay['discount'] !== null ? 'S/ ' . number_format((float)$firstHalfPay['discount'], 2) : '-' ?></span></div>
              <div>Neto: <span class="fw-semibold"><?= $firstHalfPay['net'] !== null ? 'S/ ' . number_format((float)$firstHalfPay['net'], 2) : '-' ?></span></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Segunda quincena</div>
              <div class="fw-semibold mb-2"><?= Helpers::e(date('d/m/Y', strtotime($secondHalfFrom)) . ' - ' . date('d/m/Y', strtotime($secondHalfTo))) ?></div>
              <div class="small text-muted">Dias laborables: <?= (int)$secondHalfScheduledDays ?></div>
              <div class="small text-muted">Tardanza: <?= (int)($secondHalfSummary['total_minutes_late'] ?? 0) ?> min</div>
              <div class="mt-2">Bruto: <span class="fw-semibold"><?= $secondHalfPay['gross'] !== null ? 'S/ ' . number_format((float)$secondHalfPay['gross'], 2) : '-' ?></span></div>
              <div>Descuento: <span class="fw-semibold"><?= $secondHalfPay['discount'] !== null ? 'S/ ' . number_format((float)$secondHalfPay['discount'], 2) : '-' ?></span></div>
              <div>Neto: <span class="fw-semibold"><?= $secondHalfPay['net'] !== null ? 'S/ ' . number_format((float)$secondHalfPay['net'], 2) : '-' ?></span></div>
            </div>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Dias sin marcacion</h5>
        <div class="border rounded p-3">
          <?php if (!empty($missingDays)): ?>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($missingDays as $missingDay): ?>
                <span class="badge text-bg-warning"><?= Helpers::e(date('d/m/Y', strtotime($missingDay))) ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted">No hay dias laborables sin marcacion hasta hoy.</div>
          <?php endif; ?>
        </div>

        <div class="alert alert-info mt-4 mb-0">
          Los domingos no se consideran dias laborables. Las faltas se muestran solo sobre los dias ya transcurridos del mes.
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
