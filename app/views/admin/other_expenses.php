<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');

$monthNames = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Otros gastos</h3>
        <div class="text-muted small">Maestro de detalles y registro mensual de gastos del local y servicios.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="<?= Helpers::e(BASE_URL . '/admin/finance/other-expenses/statistics') ?>">Ver estadísticas</a>
        <a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/admin/finance/income-expenses') ?>">Ver egresos e ingresos</a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white py-3">
        <h5 class="mb-0">Registrar gastos del mes</h5>
        <div class="text-muted small">Selecciona el periodo e ingresa el monto de cada detalle.</div>
      </div>
      <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="otherExpenseYear">Año</label>
            <select class="form-select" id="otherExpenseYear" name="year">
              <?php for ($year = (int)date('Y') + 1; $year >= 2020; $year--): ?>
                <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= $year ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="otherExpenseMonth">Mes</label>
            <select class="form-select" id="otherExpenseMonth" name="month">
              <?php foreach ($monthNames as $number => $name): ?>
                <option value="<?= $number ?>" <?= $selectedMonth === $number ? 'selected' : '' ?>><?= Helpers::e($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">Seleccionar periodo</button></div>
        </form>
      </div>

      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="save_month">
        <input type="hidden" name="period" value="<?= Helpers::e(date('Y-m', strtotime($periodMonth))) ?>">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>Detalle del gasto</th><th style="width: 260px;">Monto</th></tr>
            </thead>
            <tbody>
              <?php if (empty($monthEntries)): ?>
                <tr><td colspan="2" class="text-muted text-center py-4">Primero crea un detalle de gasto en la tabla maestra.</td></tr>
              <?php endif; ?>
              <?php foreach ($monthEntries as $entry): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= Helpers::e($entry['name']) ?></div>
                    <?php if ((int)$entry['is_active'] !== 1): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?>
                  </td>
                  <td>
                    <div class="input-group">
                      <span class="input-group-text">S/</span>
                      <input class="form-control text-end" type="number" min="0" step="0.01"
                        name="amounts[<?= (int)$entry['expense_detail_id'] ?>]"
                        value="<?= (float)$entry['amount'] > 0 ? Helpers::e(number_format((float)$entry['amount'], 2, '.', '')) : '' ?>"
                        placeholder="0.00">
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr><th class="text-end">Total del mes</th><th class="text-end fs-5">S/ <?= number_format($monthTotal, 2) ?></th></tr>
            </tfoot>
          </table>
        </div>
        <?php if (!empty($monthEntries)): ?>
          <div class="card-body text-end"><button class="btn btn-success" type="submit">Guardar gastos del mes</button></div>
        <?php endif; ?>
      </form>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h5 class="mb-0">Tabla maestra: detalles de gastos</h5>
        <div class="text-muted small">Crea conceptos reutilizables como Local, Agua salón, Agua cocina, Luz o Internet.</div>
      </div>
      <div class="card-body border-bottom">
        <form method="POST" class="row g-2 align-items-end">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="create_detail">
          <div class="col-md-8">
            <label class="form-label" for="expenseDetailName">Nuevo detalle de gasto</label>
            <input class="form-control" id="expenseDetailName" name="name" maxlength="150"
              placeholder="Ejemplo: Agua de cocina" required>
          </div>
          <div class="col-md-4 d-grid"><button class="btn btn-primary" type="submit">Crear detalle</button></div>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Detalle</th><th>Estado</th><th class="text-end">Acción</th></tr></thead>
          <tbody>
            <?php if (empty($details)): ?><tr><td colspan="3" class="text-muted text-center py-4">No hay detalles creados.</td></tr><?php endif; ?>
            <?php foreach ($details as $detail): ?>
              <tr>
                <td class="fw-semibold"><?= Helpers::e($detail['name']) ?></td>
                <td><span class="badge text-bg-<?= (int)$detail['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$detail['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="modal" data-bs-target="#editExpenseDetailModal"
                    data-expense-detail-id="<?= (int)$detail['id'] ?>"
                    data-expense-detail-name="<?= Helpers::e($detail['name']) ?>">
                    Editar
                  </button>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="toggle_detail">
                    <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= (int)$detail['is_active'] === 1 ? 0 : 1 ?>">
                    <button class="btn btn-sm btn-outline-<?= (int)$detail['is_active'] === 1 ? 'danger' : 'success' ?>" type="submit">
                      <?= (int)$detail['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editExpenseDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="update_detail">
      <input type="hidden" name="id" id="editExpenseDetailId">
      <div class="modal-header">
        <h5 class="modal-title">Editar detalle de gasto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="editExpenseDetailName">Nombre</label>
        <input class="form-control" id="editExpenseDetailName" name="name" maxlength="150" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editExpenseDetailModal')?.addEventListener('show.bs.modal', function(event) {
  const button = event.relatedTarget;
  document.getElementById('editExpenseDetailId').value = button?.dataset.expenseDetailId || '';
  document.getElementById('editExpenseDetailName').value = button?.dataset.expenseDetailName || '';
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
