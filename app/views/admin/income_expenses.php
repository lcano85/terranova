<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');

$monthNames = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$money = static fn($value): string => 'S/ ' . number_format((float)$value, 2);
$number = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
$openMonth = 0;
foreach ($months as $monthNumber => $month) {
  if ($month['has_data']) {
    $openMonth = (int)$monthNumber;
  }
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Egresos e ingresos</h3>
        <div class="text-muted small">Comparación mensual de pagos al personal, compras y ventas.</div>
      </div>
    </div>

    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label" for="financeYear">Año</label>
          <select class="form-select" id="financeYear" name="year">
            <?php foreach ($years as $year): ?>
              <option value="<?= (int)$year ?>" <?= $selectedYear === (int)$year ? 'selected' : '' ?>><?= (int)$year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">Filtrar por año</button></div>
      </form>
    </div></div>

    <div class="row g-3 mb-3">
      <div class="col-md-4 col-xl"><div class="card shadow-sm h-100 border-danger"><div class="card-body">
        <div class="text-muted small">Egresos del año</div><div class="fs-4 fw-bold text-danger"><?= $money($annual['expenses']) ?></div>
      </div></div></div>
      <div class="col-md-4 col-xl"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Personal</div><div class="fs-4 fw-bold"><?= $money($annual['personnel']) ?></div>
      </div></div></div>
      <div class="col-md-4 col-xl"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Compras</div><div class="fs-4 fw-bold"><?= $money($annual['purchases']) ?></div>
      </div></div></div>
      <div class="col-md-4 col-xl"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Otros gastos</div><div class="fs-4 fw-bold"><?= $money($annual['other_expenses']) ?></div>
      </div></div></div>
      <div class="col-md-4 col-xl"><div class="card shadow-sm h-100 border-success"><div class="card-body">
        <div class="text-muted small">Ingresos por ventas</div><div class="fs-4 fw-bold text-success"><?= $money($annual['sales']) ?></div>
      </div></div></div>
    </div>

    <div class="card shadow-sm mb-4 <?= $annual['balance'] >= 0 ? 'border-success' : 'border-danger' ?>">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><div class="text-muted">Resultado neto <?= (int)$selectedYear ?></div><div class="small">Ventas − Personal − Compras − Otros gastos</div></div>
        <div class="fs-3 fw-bold <?= $annual['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
          <?= $annual['balance'] >= 0 ? '+' : '-' ?><?= $money(abs($annual['balance'])) ?>
        </div>
      </div>
    </div>

    <?php foreach ($months as $monthNumber => $month): ?>
      <?php
      $collapseId = 'financeMonth' . (int)$monthNumber;
      $isOpen = (int)$monthNumber === $openMonth;
      $balancePositive = $month['balance'] >= 0;
      ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
          <button class="btn w-100 p-0 text-start" type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= Helpers::e($collapseId) ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
            aria-controls="<?= Helpers::e($collapseId) ?>">
            <div class="page-toolbar">
              <div>
                <h5 class="mb-1"><?= Helpers::e($monthNames[$monthNumber]) ?> <?= (int)$selectedYear ?></h5>
                <div class="text-muted small">Egresos: <?= $money($month['expenses']) ?> · Ingresos: <?= $money($month['sales']) ?></div>
              </div>
              <div class="d-flex flex-wrap gap-3 align-items-center">
                <div class="text-end">
                  <div class="text-muted small">Resultado del mes</div>
                  <div class="fs-5 fw-bold <?= $balancePositive ? 'text-success' : 'text-danger' ?>">
                    <?= $balancePositive ? '+' : '-' ?><?= $money(abs($month['balance'])) ?>
                  </div>
                </div>
                <span class="btn btn-sm btn-outline-secondary">Ver contenido</span>
              </div>
            </div>
          </button>
        </div>
        <div class="collapse <?= $isOpen ? 'show' : '' ?>" id="<?= Helpers::e($collapseId) ?>">
          <div class="card-body">
            <?php if (!$month['has_data']): ?>
              <div class="text-muted text-center py-3">No hay información financiera registrada en este mes.</div>
            <?php else: ?>
              <div class="row g-3">
                <div class="col-lg-6">
                  <div class="border border-danger rounded h-100">
                    <div class="p-3 border-bottom bg-light"><div class="text-danger fw-semibold">Egresos</div><div class="fs-5 fw-bold"><?= $money($month['expenses']) ?></div></div>
                    <div class="p-3">
                      <div class="d-flex justify-content-between border-bottom py-2">
                        <div><div class="fw-semibold">Personal</div><div class="text-muted small"><?= (int)$month['personnel_records'] ?> pago(s)</div></div>
                        <strong><?= $money($month['personnel']) ?></strong>
                      </div>
                      <div class="d-flex justify-content-between py-2">
                        <div>
                          <div class="fw-semibold">Compras</div>
                          <div class="text-muted small">
                            <?= (int)$month['purchase_records'] ?> producto(s)
                            <?php if ($month['unpriced_purchases'] > 0): ?> · <?= (int)$month['unpriced_purchases'] ?> sin precio/cantidad<?php endif; ?>
                          </div>
                        </div>
                        <strong><?= $money($month['purchases']) ?></strong>
                      </div>
                      <div class="d-flex justify-content-between border-top py-2">
                        <div>
                          <div class="fw-semibold">Otros gastos</div>
                          <div class="text-muted small"><?= (int)$month['other_expense_records'] ?> gasto(s) registrado(s)</div>
                        </div>
                        <strong><?= $money($month['other_expenses']) ?></strong>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6">
                  <div class="border border-success rounded h-100">
                    <div class="p-3 border-bottom bg-light"><div class="text-success fw-semibold">Ingresos</div><div class="fs-5 fw-bold"><?= $money($month['sales']) ?></div></div>
                    <div class="p-3">
                      <div class="d-flex justify-content-between py-2">
                        <div><div class="fw-semibold">Ventas</div><div class="text-muted small"><?= (int)$month['sales_products'] ?> producto(s) · <?= $number($month['units_sold']) ?> unidad(es)</div></div>
                        <strong><?= $money($month['sales']) ?></strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="border rounded mt-3 p-3 d-flex flex-wrap justify-content-between align-items-center">
                <div><div class="fw-semibold">Total del mes</div><div class="text-muted small">Ingresos menos egresos</div></div>
                <div class="fs-4 fw-bold <?= $balancePositive ? 'text-success' : 'text-danger' ?>">
                  <?= $balancePositive ? '+' : '-' ?><?= $money(abs($month['balance'])) ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
