<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');

$monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$monthNames = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$money = static fn($value): string => 'S/ ' . number_format((float)$value, 2);
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Estadísticas otros gastos</h3>
        <div class="text-muted small">Evolución mensual general y comportamiento anual de cada detalle.</div>
      </div>
      <a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/admin/finance/other-expenses') ?>">Registrar otros gastos</a>
    </div>

    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label" for="otherExpenseStatisticsYear">Año</label>
          <select class="form-select" id="otherExpenseStatisticsYear" name="year">
            <?php foreach ($years as $year): ?>
              <option value="<?= (int)$year ?>" <?= $selectedYear === (int)$year ? 'selected' : '' ?>><?= (int)$year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">Actualizar estadísticas</button></div>
      </form>
    </div></div>

    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Gasto total <?= (int)$selectedYear ?></div><div class="fs-4 fw-bold text-danger"><?= $money($annualTotal) ?></div>
      </div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Meses con gastos</div><div class="fs-4 fw-bold"><?= (int)$monthsWithData ?> / 12</div>
      </div></div></div>
      <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Mes de mayor gasto</div>
        <div class="fs-5 fw-bold"><?= $highestMonth ? Helpers::e($monthNames[$highestMonth]) : '-' ?></div>
        <div class="text-danger"><?= $money($highestMonthAmount) ?></div>
      </div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h5 class="mb-1">Gastos mensuales — <?= (int)$selectedYear ?></h5>
        <div class="text-muted small mb-3">Total registrado en cada mes del año seleccionado.</div>
        <div style="height: 360px"><canvas id="otherExpensesMonthlyChart"></canvas></div>
        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Mes</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($monthlyTotals as $month => $amount): ?>
                <tr><td><?= Helpers::e($monthNames[$month]) ?></td><td class="text-end fw-semibold"><?= $money($amount) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mb-2">
      <h5 class="mb-1">Gasto anual por detalle</h5>
      <div class="text-muted small">Cada gráfico contiene un punto por mes para identificar aumentos, disminuciones y meses sin gasto.</div>
    </div>
    <?php if (empty($details)): ?>
      <div class="card shadow-sm"><div class="card-body text-muted text-center py-4">No hay otros gastos registrados en el año seleccionado.</div></div>
    <?php endif; ?>
    <div class="row g-3">
      <?php foreach ($details as $detail): ?>
        <div class="col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><?= Helpers::e($detail['name']) ?></h6>
                <span class="badge text-bg-danger"><?= $money($detail['total']) ?></span>
              </div>
              <div style="height: 260px"><canvas id="expenseDetailChart<?= (int)$detail['id'] ?>"></canvas></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
  const labels = <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const money = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: { y: { beginAtZero: true, ticks: { callback: value => money.format(value) } } },
    plugins: { tooltip: { callbacks: { label: item => `${item.dataset.label}: ${money.format(item.raw)}` } } }
  };

  new Chart(document.getElementById('otherExpensesMonthlyChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Otros gastos',
        data: <?= json_encode(array_values($monthlyTotals)) ?>,
        backgroundColor: 'rgba(220,53,69,.65)',
        borderColor: '#dc3545',
        borderWidth: 1
      }]
    },
    options: commonOptions
  });

  const details = <?= json_encode(array_map(static fn(array $detail): array => [
    'id' => (int)$detail['id'],
    'name' => $detail['name'],
    'data' => array_values($detail['months']),
  ], $details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  details.forEach(detail => {
    new Chart(document.getElementById(`expenseDetailChart${detail.id}`), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: detail.name,
          data: detail.data,
          borderColor: '#0d6efd',
          backgroundColor: 'rgba(13,110,253,.16)',
          pointBackgroundColor: '#0d6efd',
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: .25
        }]
      },
      options: commonOptions
    });
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
