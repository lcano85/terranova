<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');

function statisticsMoney($value): string {
  return 'S/ ' . number_format((float)$value, 2);
}

function statisticsNumber($value): string {
  return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
}

$monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$salesByMonth = array_fill(1, 12, 0.0);
$unitsByMonth = array_fill(1, 12, 0.0);
foreach ($monthlyTotals as $row) {
  $month = (int)$row['month_number'];
  $salesByMonth[$month] = (float)$row['total_amount'];
  $unitsByMonth[$month] = (float)$row['units_sold'];
}

$products = [];
foreach ($productMonthlyRows as $row) {
  $productId = (int)$row['product_id'];
  if (!isset($products[$productId])) {
    $products[$productId] = [
      'name' => $row['product_name'],
      'category' => $row['category_name'],
      'months' => array_fill(1, 12, ['units' => 0.0, 'sales' => 0.0]),
      'total_units' => 0.0,
      'total_sales' => 0.0,
    ];
  }
  $month = (int)$row['month_number'];
  $units = (float)$row['units_sold'];
  $sales = (float)$row['total_amount'];
  $products[$productId]['months'][$month] = ['units' => $units, 'sales' => $sales];
  $products[$productId]['total_units'] += $units;
  $products[$productId]['total_sales'] += $sales;
}

$chartProducts = array_values($products);
usort($chartProducts, static fn(array $a, array $b): int => $b['total_units'] <=> $a['total_units']);
$chartProducts = array_slice($chartProducts, 0, 10);

$groupedProducts = [];
foreach ($products as $product) {
  $groupedProducts[$product['category']][] = $product;
}
ksort($groupedProducts, SORT_NATURAL | SORT_FLAG_CASE);

$annualSales = array_sum($salesByMonth);
$annualUnits = array_sum($unitsByMonth);
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Estadisticas de ventas</h3>
        <div class="text-muted small">Evolucion mensual, comparacion de productos y detalle por categoria.</div>
      </div>
      <a class="btn btn-outline-secondary" href="/admin/sales">Volver a ventas</a>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2" method="GET">
          <div class="col-md-4">
            <label class="form-label">Periodo</label>
            <select class="form-select" name="year">
              <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['year'] ?>" <?= $selectedYear === (int)$year['year'] ? 'selected' : '' ?>>
                  <?= (int)$year['year'] ?> (<?= (int)$year['months_count'] ?> meses con datos)
                </option>
              <?php endforeach; ?>
              <?php if (empty($years)): ?>
                <option value="<?= $selectedYear ?>"><?= $selectedYear ?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Categoria</label>
            <select class="form-select" name="category_id">
              <option value="">Todas las categorias</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($category['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-primary">Actualizar estadisticas</button>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Venta del periodo</div><div class="fs-4 fw-bold"><?= statisticsMoney($annualSales) ?></div>
      </div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Unidades del periodo</div><div class="fs-4 fw-bold"><?= statisticsNumber($annualUnits) ?></div>
      </div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Productos analizados</div><div class="fs-4 fw-bold"><?= count($products) ?></div>
      </div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h5 class="mb-1">Ventas mensuales - <?= $selectedYear ?></h5>
        <div class="text-muted small mb-3">Compara el monto vendido y las unidades de cada mes del periodo.</div>
        <div style="height: 360px"><canvas id="monthlySalesChart"></canvas></div>
        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Mes</th><th class="text-end">Cantidad</th><th class="text-end">Venta total</th><th class="text-end">Variacion vs. mes anterior</th></tr></thead>
            <tbody>
              <?php $previousSales = null; ?>
              <?php for ($month = 1; $month <= 12; $month++): ?>
                <?php
                  $currentSales = $salesByMonth[$month];
                  $variation = ($previousSales !== null && $previousSales > 0)
                    ? (($currentSales - $previousSales) / $previousSales) * 100
                    : null;
                ?>
                <tr>
                  <td><?= $monthLabels[$month - 1] ?></td>
                  <td class="text-end"><?= statisticsNumber($unitsByMonth[$month]) ?></td>
                  <td class="text-end"><?= statisticsMoney($currentSales) ?></td>
                  <td class="text-end <?= $variation !== null && $variation < 0 ? 'text-danger' : ($variation > 0 ? 'text-success' : '') ?>">
                    <?= $variation === null ? '-' : (($variation > 0 ? '+' : '') . number_format($variation, 1) . '%') ?>
                  </td>
                </tr>
                <?php $previousSales = $currentSales; ?>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h5 class="mb-1">Productos vendidos por mes</h5>
        <div class="text-muted small mb-3">Comparacion mensual por unidades de los 10 productos mas vendidos<?= $categoryId > 0 ? ' en la categoria seleccionada' : '' ?>.</div>
        <div style="height: 420px"><canvas id="productSalesChart"></canvas></div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-1">Detalle mensual por categoria y producto</h5>
        <div class="text-muted small mb-3">Cada mes muestra cantidad vendida y venta total para identificar aumentos o disminuciones.</div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle text-nowrap">
            <thead class="table-light">
              <tr>
                <th rowspan="2">Categoria / producto</th>
                <?php foreach ($monthLabels as $label): ?><th colspan="2" class="text-center"><?= $label ?></th><?php endforeach; ?>
                <th colspan="2" class="text-center">Total</th>
              </tr>
              <tr>
                <?php foreach ($monthLabels as $label): ?><th>Cant.</th><th>Venta</th><?php endforeach; ?>
                <th>Cant.</th><th>Venta</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($groupedProducts as $category => $categoryProducts): ?>
                <tr class="table-primary"><th colspan="27"><?= Helpers::e($category) ?></th></tr>
                <?php foreach ($categoryProducts as $product): ?>
                  <tr>
                    <td><?= Helpers::e($product['name']) ?></td>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                      <td class="text-end"><?= statisticsNumber($product['months'][$month]['units']) ?></td>
                      <td class="text-end"><?= statisticsMoney($product['months'][$month]['sales']) ?></td>
                    <?php endfor; ?>
                    <td class="text-end fw-semibold"><?= statisticsNumber($product['total_units']) ?></td>
                    <td class="text-end fw-semibold"><?= statisticsMoney($product['total_sales']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <?php if (empty($groupedProducts)): ?>
                <tr><td colspan="27" class="text-muted text-center py-4">No hay ventas para el periodo y categoria seleccionados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
  const labels = <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const money = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
  const monthlyCanvas = document.getElementById('monthlySalesChart');
  new Chart(monthlyCanvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Venta total',
        data: <?= json_encode(array_values($salesByMonth)) ?>,
        borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.22)', fill: true, tension: .25, yAxisID: 'sales'
      }, {
        label: 'Unidades',
        data: <?= json_encode(array_values($unitsByMonth)) ?>,
        borderColor: '#198754', backgroundColor: '#198754', tension: .25, yAxisID: 'units'
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      scales: {
        sales: { position: 'left', beginAtZero: true, ticks: { callback: value => money.format(value) } },
        units: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
      },
      plugins: { tooltip: { callbacks: { label: item => item.dataset.yAxisID === 'sales' ? `Venta: ${money.format(item.raw)}` : `Unidades: ${item.raw}` } } }
    }
  });

  const palette = ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997','#d63384','#0dcaf0','#6c757d','#ffc107'];
  const productData = <?= json_encode(array_map(static fn(array $product): array => [
    'label' => $product['name'],
    'data' => array_values(array_map(static fn(array $month): float => $month['units'], $product['months'])),
  ], $chartProducts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const productCanvas = document.getElementById('productSalesChart');
  new Chart(productCanvas, {
    type: 'line',
    data: { labels, datasets: productData.map((dataset, index) => ({ ...dataset, borderColor: palette[index], backgroundColor: palette[index], tension: .2 })) },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Cantidad vendida' } } } }
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
