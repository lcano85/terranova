<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$selectedUserId = (int)($_GET['user_id'] ?? ($preview['user']['id'] ?? 0));
$paymentType = (string)($_GET['payment_type'] ?? ($preview['payment_type'] ?? 'biweekly'));
$isEditing = !empty($editingPayroll);
$periodPart = (string)($_GET['period_part'] ?? (
  $isEditing && (int)date('j', strtotime($editingPayroll['period_start'])) >= 16 ? 'second' : 'first'
));
$weekStart = (string)($_GET['week_start'] ?? ($isEditing ? $editingPayroll['period_start'] : ''));
$salaryBasis = (string)($_GET['salary_basis'] ?? ($preview['salary_basis'] ?? 'daily'));
$salaryAmount = (string)($_GET['salary_amount'] ?? ($preview['salary_amount'] ?? ''));
$baseDays = (string)($_GET['base_days'] ?? ($preview['base_days'] ?? ''));
$hoursPerDay = (string)($_GET['hours_per_day'] ?? ($preview['hours_per_day'] ?? ''));

$payrollPagination = Pagination::paginateArray($rows, 'payroll_page', 'payroll_per_page');
$rows = $payrollPagination['rows'];
$payrollPaginationMeta = $payrollPagination['meta'];

function payrollTypeLabel(string $type): string {
  if ($type === 'monthly') return 'Mensual';
  if ($type === 'weekly') return 'Semanal';
  return 'Quincenal';
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <h3 class="mb-0">Pagos de trabajadores</h3>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
          <div class="col-lg-3 col-md-6">
            <label class="form-label">Trabajador</label>
            <select class="form-select" name="user_id" required>
              <option value="">Selecciona trabajador</option>
              <?php foreach ($workers as $w): ?>
                <option value="<?= (int)$w['id'] ?>" <?= $selectedUserId === (int)$w['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($w['document_number'] . ' - ' . $w['first_name'] . ' ' . $w['last_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Mes</label>
            <input type="month" class="form-control" name="month" value="<?= Helpers::e($selectedMonth) ?>" required>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Forma de pago</label>
            <select class="form-select" name="payment_type" id="paymentType">
              <option value="monthly" <?= $paymentType === 'monthly' ? 'selected' : '' ?>>Mensual</option>
              <option value="biweekly" <?= $paymentType === 'biweekly' ? 'selected' : '' ?>>Quincenal</option>
              <option value="weekly" <?= $paymentType === 'weekly' ? 'selected' : '' ?>>Semanal</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6" data-period-part>
            <label class="form-label">Quincena</label>
            <select class="form-select" name="period_part">
              <option value="first" <?= $periodPart === 'first' ? 'selected' : '' ?>>1 al 15</option>
              <option value="second" <?= $periodPart === 'second' ? 'selected' : '' ?>>16 al fin de mes</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6" data-week-start>
            <label class="form-label">Inicio semana</label>
            <input type="date" class="form-control" name="week_start" value="<?= Helpers::e($weekStart) ?>">
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Base salario</label>
            <select class="form-select" name="salary_basis">
              <option value="daily" <?= $salaryBasis === 'daily' ? 'selected' : '' ?>>Pago diario</option>
              <option value="monthly" <?= $salaryBasis === 'monthly' ? 'selected' : '' ?>>Salario mensual</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Salario / dia</label>
            <input type="number" step="0.01" min="0" class="form-control" name="salary_amount" value="<?= Helpers::e((string)$salaryAmount) ?>" placeholder="Ej: 1200">
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Dias base mes</label>
            <input type="number" step="0.01" min="0" class="form-control" name="base_days" value="<?= Helpers::e((string)$baseDays) ?>" placeholder="Auto">
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label">Horas por dia</label>
            <input type="number" step="0.01" min="0" class="form-control" name="hours_per_day" value="<?= Helpers::e((string)$hoursPerDay) ?>" placeholder="Auto">
          </div>
          <div class="col-lg-2 col-md-6 d-grid">
            <button class="btn btn-outline-primary">Calcular</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($preview)): ?>
      <form method="POST" id="payrollForm" class="mb-3">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
        <?php if ($isEditing): ?>
          <input type="hidden" name="id" value="<?= (int)$editingPayroll['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="user_id" value="<?= (int)$preview['user']['id'] ?>">
        <input type="hidden" name="payment_type" value="<?= Helpers::e($preview['payment_type']) ?>">
        <input type="hidden" name="period_month" value="<?= Helpers::e(date('Y-m', strtotime($preview['period_month']))) ?>">
        <input type="hidden" name="period_part" value="<?= Helpers::e($periodPart) ?>">
        <input type="hidden" name="week_start" value="<?= Helpers::e($weekStart) ?>">

        <div class="row g-3">
          <div class="col-xl-8">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                  <div>
                    <h5 class="mb-1"><?= Helpers::e($preview['user']['first_name'] . ' ' . $preview['user']['last_name']) ?></h5>
                    <div class="text-muted small">
                      <?= Helpers::e(payrollTypeLabel($preview['payment_type'])) ?>:
                      <?= Helpers::e(Helpers::formatDate($preview['period_start'])) ?> -
                      <?= Helpers::e(Helpers::formatDate($preview['period_end'])) ?>
                    </div>
                  </div>
                  <span class="badge <?= $isEditing ? 'text-bg-warning' : 'text-bg-primary' ?>">
                    <?= $isEditing ? 'Editando pago #' . (int)$editingPayroll['id'] : 'Vista previa' ?>
                  </span>
                </div>

                <div class="row g-2 mb-3">
                  <div class="col-md-3">
                    <label class="form-label">Base salario</label>
                    <select class="form-select js-calc" name="salary_basis">
                      <option value="daily" <?= $preview['salary_basis'] === 'daily' ? 'selected' : '' ?>>Pago diario</option>
                      <option value="monthly" <?= $preview['salary_basis'] === 'monthly' ? 'selected' : '' ?>>Salario mensual</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Salario / dia</label>
                    <input type="number" step="0.01" min="0" class="form-control js-calc" name="salary_amount" value="<?= Helpers::e((string)$preview['salary_amount']) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Dias base mes</label>
                    <input type="number" step="0.01" min="0" class="form-control js-calc" name="base_days" value="<?= Helpers::e((string)$preview['base_days']) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Horas por dia</label>
                    <input type="number" step="0.01" min="0" class="form-control js-calc" name="hours_per_day" value="<?= Helpers::e((string)$preview['hours_per_day']) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Dias trabajados</label>
                    <input type="number" step="0.01" min="0" class="form-control js-calc" name="worked_days" value="<?= Helpers::e((string)$preview['worked_days']) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Minutos tardanza</label>
                    <input type="number" step="1" min="0" class="form-control js-calc" name="late_minutes" value="<?= (int)$preview['late_minutes'] ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Notas</label>
                    <input class="form-control" name="notes" value="<?= Helpers::e((string)($editingPayroll['notes'] ?? '')) ?>" placeholder="Ej: no marco entrada el 19 y 21">
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-2" id="itemsTable">
                    <thead>
                      <tr>
                        <th style="width:160px;">Tipo</th>
                        <th>Concepto</th>
                        <th style="width:160px;">Monto</th>
                        <th style="width:80px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $formItems = $preview['items'] ?? [];
                      if (empty($formItems)) {
                        $formItems = [['item_type' => 'addition', 'concept' => '', 'amount' => 0]];
                      }
                      foreach ($formItems as $item):
                      ?>
                        <tr>
                          <td>
                            <select class="form-select form-select-sm js-calc item-type" name="items[type][]">
                              <option value="addition" <?= $item['item_type'] === 'addition' ? 'selected' : '' ?>>Adicional</option>
                              <option value="deduction" <?= $item['item_type'] === 'deduction' ? 'selected' : '' ?>>Descuento</option>
                            </select>
                          </td>
                          <td><input class="form-control form-control-sm" name="items[concept][]" value="<?= Helpers::e((string)$item['concept']) ?>" placeholder="Horas extras, propinas, adelanto, prestamo"></td>
                          <td><input type="number" step="0.01" min="0" class="form-control form-control-sm js-calc item-amount" name="items[amount][]" value="<?= Helpers::e((string)$item['amount']) ?>"></td>
                          <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Quitar</button></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addItem">+ Agregar adicional/descuento</button>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <h5 class="mb-3">Resumen</h5>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Pago por dia</span>
                  <strong>S/ <span data-daily-rate><?= number_format((float)$preview['daily_rate'], 2) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Bruto</span>
                  <strong>S/ <span data-gross><?= number_format((float)$preview['gross_amount'], 2) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Factor tardanza</span>
                  <strong>S/ <span data-late-rate><?= number_format((float)$preview['late_rate_per_minute'], 4) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Descuento tardanza</span>
                  <strong>S/ <span data-late-discount><?= number_format((float)$preview['late_discount'], 2) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Adicionales</span>
                  <strong>S/ <span data-additions><?= number_format((float)$preview['additions_total'], 2) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                  <span>Descuentos total</span>
                  <strong>S/ <span data-deductions><?= number_format((float)$preview['deductions_total'], 2) ?></span></strong>
                </div>
                <div class="d-flex justify-content-between py-3 fs-5">
                  <span>Pago final</span>
                  <strong>S/ <span data-net><?= number_format((float)$preview['net_amount'], 2) ?></span></strong>
                </div>
                <div class="text-muted small mb-3">
                  Asistencia detectada: <?= (int)$preview['attendance_summary']['worked_days'] ?> dia(s),
                  <?= (int)$preview['attendance_summary']['total_minutes_late'] ?> min de tardanza.
                </div>
                <div class="d-grid gap-2">
                  <button class="btn btn-primary" onclick="return confirm('<?= $isEditing ? 'Guardar los cambios de este pago?' : 'Generar este pago?' ?>');">
                    <?= $isEditing ? 'Guardar cambios' : 'Generar pago' ?>
                  </button>
                  <?php if ($isEditing): ?>
                    <a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/admin/payroll?month=' . urlencode($selectedMonth)) ?>">Cancelar edición</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0">Pagos generados</h5>
          <span class="text-muted small"><?= Helpers::e(date('m/Y', strtotime($selectedMonth . '-01'))) ?></span>
        </div>
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Trabajador</th>
              <th>Periodo</th>
              <th>Tipo</th>
              <th>Dias</th>
              <th>Bruto</th>
              <th>Descuentos</th>
              <th>Adicionales</th>
              <th>Final</th>
              <th style="width: 170px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= Helpers::e($r['document_number'] . ' - ' . $r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= Helpers::e(Helpers::formatDate($r['period_start']) . ' - ' . Helpers::formatDate($r['period_end'])) ?></td>
                <td><?= Helpers::e(payrollTypeLabel($r['payment_type'])) ?></td>
                <td><?= Helpers::e((string)(float)$r['worked_days']) ?></td>
                <td>S/ <?= number_format((float)$r['gross_amount'], 2) ?></td>
                <td>S/ <?= number_format((float)$r['deductions_total'], 2) ?></td>
                <td>S/ <?= number_format((float)$r['additions_total'], 2) ?></td>
                <td><strong>S/ <?= number_format((float)$r['net_amount'], 2) ?></strong></td>
                <td>
                  <div class="d-flex gap-1">
                    <a class="btn btn-sm btn-outline-primary" href="<?= Helpers::e(BASE_URL . '/admin/payroll?month=' . urlencode($selectedMonth) . '&edit_id=' . (int)$r['id']) ?>">Editar</a>
                    <form method="POST" onsubmit="return confirm('Eliminar pago generado?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="10" class="text-muted">No hay pagos generados para este mes.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($payrollPaginationMeta) ?>
    </div>
  </div>
</div>

<script>
(() => {
  const paymentType = document.getElementById('paymentType');
  const periodPart = document.querySelector('[data-period-part]');
  const weekStart = document.querySelector('[data-week-start]');

  function togglePeriodFields() {
    if (!paymentType) return;
    periodPart.style.display = paymentType.value === 'biweekly' ? '' : 'none';
    weekStart.style.display = paymentType.value === 'weekly' ? '' : 'none';
  }
  paymentType?.addEventListener('change', togglePeriodFields);
  togglePeriodFields();

  const form = document.getElementById('payrollForm');
  if (!form) return;

  const table = document.getElementById('itemsTable');
  const addItem = document.getElementById('addItem');
  const money = (value) => Number.parseFloat(value || '0') || 0;
  const setText = (selector, value, decimals = 2) => {
    const el = document.querySelector(selector);
    if (el) el.textContent = value.toFixed(decimals);
  };

  function calculate() {
    const basis = form.querySelector('[name="salary_basis"]').value;
    const salary = money(form.querySelector('[name="salary_amount"]').value);
    const baseDays = Math.max(money(form.querySelector('[name="base_days"]').value), 1);
    const hours = Math.max(money(form.querySelector('[name="hours_per_day"]').value), 1);
    const workedDays = money(form.querySelector('[name="worked_days"]').value);
    const lateMinutes = money(form.querySelector('[name="late_minutes"]').value);
    const dailyRate = basis === 'monthly' ? salary / baseDays : salary;
    const gross = dailyRate * workedDays;
    const lateRate = (dailyRate / hours) / 60;
    const lateDiscount = lateRate * lateMinutes;
    let additions = 0;
    let manualDeductions = 0;

    table.querySelectorAll('tbody tr').forEach((row) => {
      const amount = money(row.querySelector('.item-amount')?.value);
      const type = row.querySelector('.item-type')?.value;
      if (type === 'addition') additions += amount;
      if (type === 'deduction') manualDeductions += amount;
    });

    const deductions = lateDiscount + manualDeductions;
    const net = gross + additions - deductions;
    setText('[data-daily-rate]', dailyRate);
    setText('[data-gross]', gross);
    setText('[data-late-rate]', lateRate, 4);
    setText('[data-late-discount]', lateDiscount);
    setText('[data-additions]', additions);
    setText('[data-deductions]', deductions);
    setText('[data-net]', net);
  }

  addItem?.addEventListener('click', () => {
    const first = table.querySelector('tbody tr');
    const row = first.cloneNode(true);
    row.querySelectorAll('input').forEach((input) => input.value = input.type === 'number' ? '0' : '');
    row.querySelectorAll('select').forEach((select) => select.value = 'addition');
    table.querySelector('tbody').appendChild(row);
    calculate();
  });

  table.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (!button) return;
    const rows = table.querySelectorAll('tbody tr');
    if (rows.length === 1) {
      rows[0].querySelectorAll('input').forEach((input) => input.value = input.type === 'number' ? '0' : '');
    } else {
      button.closest('tr').remove();
    }
    calculate();
  });

  form.addEventListener('input', (event) => {
    if (event.target.closest('.js-calc') || event.target.closest('#itemsTable')) {
      calculate();
    }
  });
  form.addEventListener('change', calculate);
  calculate();
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
