<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');

$monthNames = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$money = static fn($value): string => 'S/ ' . number_format((float)$value, 2);
$quantity = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
$openMonth = 0;
foreach ($months as $monthNumber => $month) {
  if ((int)$month['request_count'] > 0) {
    $openMonth = (int)$monthNumber;
  }
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Gastos en compras</h3>
        <div class="text-muted small">Resumen mensual y los 10 productos más solicitados de cada mes.</div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label" for="expenseYear">Año</label>
            <select class="form-select" id="expenseYear" name="year">
              <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year ?>" <?= $selectedYear === (int)$year ? 'selected' : '' ?>>
                  <?= (int)$year ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-primary" type="submit">Filtrar por año</button>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small">Gasto total <?= (int)$selectedYear ?></div>
          <div class="fs-4 fw-bold text-success"><?= $money($annualTotal) ?></div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small">Compras registradas</div>
          <div class="fs-4 fw-bold"><?= number_format((int)$annualRequests) ?></div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small">Meses con gastos</div>
          <div class="fs-4 fw-bold"><?= (int)$monthsWithExpenses ?> / 12</div>
        </div></div>
      </div>
    </div>

    <?php foreach ($months as $monthNumber => $month): ?>
      <?php
      $monthCollapseId = 'purchaseExpenseMonth' . (int)$monthNumber;
      $monthIsOpen = (int)$monthNumber === $openMonth;
      ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
          <button
            class="btn w-100 p-0 text-start"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?= Helpers::e($monthCollapseId) ?>"
            aria-expanded="<?= $monthIsOpen ? 'true' : 'false' ?>"
            aria-controls="<?= Helpers::e($monthCollapseId) ?>">
            <div class="page-toolbar">
              <div>
                <h5 class="mb-0"><?= Helpers::e($monthNames[$monthNumber]) ?> <?= (int)$selectedYear ?></h5>
                <div class="text-muted small"><?= number_format((int)$month['request_count']) ?> compra(s) completada(s)</div>
              </div>
              <div class="d-flex flex-wrap gap-3 align-items-center">
                <div class="text-end">
                  <div class="text-muted small">Total mensual</div>
                  <div class="fs-5 fw-bold text-success"><?= $money($month['total']) ?></div>
                </div>
                <span class="btn btn-sm btn-outline-secondary">Ver contenido</span>
              </div>
            </div>
          </button>
        </div>

        <div class="collapse <?= $monthIsOpen ? 'show' : '' ?>" id="<?= Helpers::e($monthCollapseId) ?>">
          <div class="card-body p-0">
            <?php if (empty($month['top_products'])): ?>
              <div class="text-muted text-center py-4">No hay compras completadas en este mes.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center" style="width: 70px;">#</th>
                      <th>Producto</th>
                      <th class="text-end">Veces solicitado</th>
                      <th class="text-end">Cantidad</th>
                      <th class="text-end">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($month['top_products'] as $position => $product): ?>
                      <tr>
                        <td class="text-center fw-semibold"><?= $position + 1 ?></td>
                        <td><?= Helpers::e($product['product_name']) ?></td>
                        <td class="text-end"><?= number_format((int)$product['request_count']) ?></td>
                        <td class="text-end"><?= $quantity($product['total_quantity']) ?></td>
                        <td class="text-end fw-semibold"><?= $money($product['subtotal']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot class="table-light">
                    <tr>
                      <th colspan="4" class="text-end">Total del mes</th>
                      <th class="text-end"><?= $money($month['total']) ?></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <?php if ($month['unpriced_count'] > 0): ?>
                <div class="alert alert-warning rounded-0 border-0 border-top mb-0 py-2">
                  <?= (int)$month['unpriced_count'] ?> compra(s) no se incluyeron en el monto porque no tienen cantidad o precio.
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
