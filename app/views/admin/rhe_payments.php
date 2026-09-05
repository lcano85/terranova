<?php
$emptyForm = ['id' => 0, 'amount' => '', 'user_id' => '', 'month' => date('Y-m'), 'image_name' => '', 'temporary_first_name' => '', 'temporary_last_name' => '', 'worker_type' => 'active'];
$temporaryForm = ($form['id'] || $form['worker_type'] === 'temporary') ? $form : null;
if ($temporaryForm) $form = $emptyForm;
?>
<?php require __DIR__ . '/../layouts/header.php'; ?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3"><h3 class="mb-0">Pago RHE</h3><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTemporaryNew">+ Trabajador temporal</button></div>
    <?php if ($msg): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>" role="alert"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h5 class="mb-3"><?= $form['id'] ? 'Editar pago RHE #' . (int)$form['id'] : 'Nuevo pago RHE' ?></h5>
        <form method="POST" action="<?= Helpers::e(BASE_URL . '/admin/rhe-payments') ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
          <div class="col-lg-4 col-md-6">
            <label class="form-label" for="rheWorker">Trabajador</label>
            <select class="form-select" name="user_id" id="rheWorker" required>
              <option value="">Selecciona trabajador</option>
              <?php foreach ($workers as $worker): ?>
                <option value="<?= (int)$worker['id'] ?>" <?= (int)$form['user_id'] === (int)$worker['id'] ? 'selected' : '' ?>><?= Helpers::e($worker['document_number'] . ' - ' . $worker['first_name'] . ' ' . $worker['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label" for="rheMonth">Mes y año</label>
            <input type="month" class="form-control" name="month" id="rheMonth" min="1000-01" max="9999-12" value="<?= Helpers::e($form['month']) ?>" required>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label" for="rheAmount">Monto (S/)</label>
            <input type="number" class="form-control" id="rheAmount" name="amount" min="0.01" max="9999999999.99" step="0.01" value="<?= Helpers::e($form['amount']) ?>" placeholder="0.00" required>
          </div>
          <div class="col-lg-4 col-md-8">
            <label class="form-label" for="rheImage">Archivo adjunto</label>
            <input type="file" class="form-control" name="image" id="rheImage" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf" aria-describedby="rheImageHelp" <?= $form['image_name'] ? '' : 'required' ?>>
          </div>
          <div class="col-lg-2 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Guardar</button>
            <a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/admin/rhe-payments') ?>">Limpiar</a>
          </div>
          <div class="col-12 mt-2">
            <div class="form-text" id="rheImageHelp">JPG, PNG, WebP o PDF. Máximo 5 MB.<?= $form['image_name'] ? ' Deja el campo vacío para conservar el archivo actual.' : '' ?></div>
            <?php if ($form['image_name']): ?>
              <a href="<?= Helpers::e(BASE_URL . '/admin/rhe-payments/image?id=' . (int)$form['id']) ?>" target="_blank" rel="noopener">Ver archivo actual</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Pagos RHE registrados</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>ID</th><th>Trabajador</th><th>Mes / Año</th><th class="text-end">Monto (S/)</th><th>Adjunto</th><th>Acciones</th></tr></thead>
            <tbody>
              <?php foreach ($pagination['rows'] as $row): ?>
                <tr>
                  <td><?= (int)$row['id'] ?></td>
                  <td><?= Helpers::e($row['user_id'] === null ? $row['temporary_first_name'] . ' ' . $row['temporary_last_name'] : $row['document_number'] . ' - ' . $row['first_name'] . ' ' . $row['last_name']) ?><?php if ($row['user_id'] === null): ?> <span class="badge text-bg-secondary">Temporal</span><?php endif; ?></td>
                  <td><?= Helpers::e(date('m/Y', strtotime($row['period_month']))) ?></td>
                  <td class="text-end text-nowrap"><?= $row['amount'] !== null ? 'S/ ' . number_format((float)$row['amount'], 2) : '<span class="text-muted">Sin registrar</span>' ?></td>
                  <td><a href="<?= Helpers::e(BASE_URL . '/admin/rhe-payments/image?id=' . (int)$row['id']) ?>" target="_blank" rel="noopener"><?php if (pathinfo($row['image_name'], PATHINFO_EXTENSION) === 'pdf'): ?><span class="btn btn-sm btn-outline-secondary">Ver PDF</span><?php else: ?><img src="<?= Helpers::e(BASE_URL . '/admin/rhe-payments/image?id=' . (int)$row['id']) ?>" alt="Ver imagen RHE #<?= (int)$row['id'] ?>" loading="lazy" class="rounded border" style="width:80px;height:60px;object-fit:contain"><?php endif; ?></a></td>
                  <td>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalRheEdit<?= (int)$row['id'] ?>">Editar</button>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRheDelete<?= (int)$row['id'] ?>">Eliminar</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$pagination['rows']): ?><tr><td colspan="6" class="text-muted">No hay pagos RHE registrados.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <?= Pagination::render($pagination['meta']) ?>
      </div>
    </div>
  </div>
</div>
<?php
$temporaryModals = ['modalTemporaryNew' => ($temporaryForm && !$temporaryForm['id']) ? $temporaryForm : $emptyForm];
foreach ($pagination['rows'] as $row) {
  $temporaryModals['modalRheEdit' . (int)$row['id']] = [
    'id' => $row['id'], 'user_id' => $row['user_id'], 'month' => substr($row['period_month'], 0, 7),
    'amount' => $row['amount'] ?? '', 'image_name' => $row['image_name'],
    'worker_type' => $row['user_id'] === null ? 'temporary' : 'active',
    'temporary_first_name' => $row['temporary_first_name'] ?? '', 'temporary_last_name' => $row['temporary_last_name'] ?? '',
  ];
}
$temporaryModals['modalTemporaryNew']['worker_type'] = 'temporary';
if ($temporaryForm && $temporaryForm['id']) $temporaryModals['modalRheEdit' . (int)$temporaryForm['id']] = $temporaryForm;
foreach ($temporaryModals as $modalId => $temporary):
?>
<div class="modal fade" id="<?= Helpers::e($modalId) ?>" tabindex="-1" aria-labelledby="<?= Helpers::e($modalId) ?>Title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?= Helpers::e(BASE_URL . '/admin/rhe-payments') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="worker_type" value="<?= Helpers::e($temporary['worker_type']) ?>">
        <input type="hidden" name="id" value="<?= (int)$temporary['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="<?= Helpers::e($modalId) ?>Title"><?= $temporary['id'] ? 'Editar pago RHE #' . (int)$temporary['id'] : 'Agregar trabajador temporal' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <?php if ($temporaryForm && $temporary['id'] == $temporaryForm['id'] && $msg && $msg['type'] === 'danger'): ?><div class="alert alert-danger" role="alert"><?= Helpers::e($msg['text']) ?></div><?php endif; ?>
          <div class="row g-3">
            <?php if ($temporary['worker_type'] === 'temporary'): ?>
            <div class="col-md-6">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>First">Nombres</label>
              <input class="form-control" id="<?= Helpers::e($modalId) ?>First" name="temporary_first_name" maxlength="100" value="<?= Helpers::e($temporary['temporary_first_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>Last">Apellidos</label>
              <input class="form-control" id="<?= Helpers::e($modalId) ?>Last" name="temporary_last_name" maxlength="100" value="<?= Helpers::e($temporary['temporary_last_name']) ?>" required>
            </div>
            <?php else: ?>
            <div class="col-12">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>Worker">Trabajador</label>
              <select class="form-select" name="user_id" id="<?= Helpers::e($modalId) ?>Worker" required>
                <option value="">Selecciona trabajador activo</option>
                <?php foreach ($workers as $worker): ?>
                  <option value="<?= (int)$worker['id'] ?>" <?= (int)$temporary['user_id'] === (int)$worker['id'] ? 'selected' : '' ?>><?= Helpers::e($worker['document_number'] . ' - ' . $worker['first_name'] . ' ' . $worker['last_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>Month">Mes y año</label>
              <input type="month" class="form-control" id="<?= Helpers::e($modalId) ?>Month" name="month" min="1000-01" max="9999-12" value="<?= Helpers::e($temporary['month']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>Amount">Monto (S/)</label>
              <input type="number" class="form-control" id="<?= Helpers::e($modalId) ?>Amount" name="amount" min="0.01" max="9999999999.99" step="0.01" value="<?= Helpers::e($temporary['amount']) ?>" placeholder="0.00" required>
            </div>
            <div class="col-md-12">
              <label class="form-label" for="<?= Helpers::e($modalId) ?>File">Archivo adjunto</label>
              <input type="file" class="form-control" id="<?= Helpers::e($modalId) ?>File" name="image" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf" <?= $temporary['image_name'] ? '' : 'required' ?>>
              <div class="form-text">JPG, PNG, WebP o PDF. Máximo 5 MB.</div>
              <?php if ($temporary['image_name']): ?>
                <div class="form-text">Deja el campo vacío para conservar el archivo actual.</div>
                <a href="<?= Helpers::e(BASE_URL . '/admin/rhe-payments/image?id=' . (int)$temporary['id']) ?>" target="_blank" rel="noopener">Ver archivo actual</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if ($temporaryForm): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  new bootstrap.Modal(document.getElementById('<?= $temporaryForm['id'] ? 'modalRheEdit' . (int)$temporaryForm['id'] : 'modalTemporaryNew' ?>')).show();
});
</script>
<?php endif; ?>
<?php foreach ($pagination['rows'] as $row): ?>
<div class="modal fade" id="modalRheDelete<?= (int)$row['id'] ?>" tabindex="-1" aria-labelledby="modalRheDeleteTitle<?= (int)$row['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?= Helpers::e(BASE_URL . '/admin/rhe-payments') ?>">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalRheDeleteTitle<?= (int)$row['id'] ?>">Eliminar pago RHE #<?= (int)$row['id'] ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p>¿Confirmas la eliminación de este pago RHE?</p>
          <p class="mb-1 fw-semibold"><?= Helpers::e($row['user_id'] === null ? $row['temporary_first_name'] . ' ' . $row['temporary_last_name'] : $row['first_name'] . ' ' . $row['last_name']) ?></p>
          <p><?= Helpers::e(date('m/Y', strtotime($row['period_month']))) ?> · <?= $row['amount'] !== null ? 'S/ ' . number_format((float)$row['amount'], 2) : 'Monto sin registrar' ?></p>
          <p class="text-muted mb-0">Se eliminarán el registro y su archivo adjunto. Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Sí, eliminar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
