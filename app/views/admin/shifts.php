<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$shiftsPagination = Pagination::paginateArray($shifts, 'shifts_page', 'shifts_per_page');
$shifts = $shiftsPagination['rows'];
$shiftsPaginationMeta = $shiftsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <h3 class="mb-0">Turnos</h3>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateShift">+ Nuevo</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th style="width:180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($shifts as $s): ?>
              <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= Helpers::e($s['name']) ?></td>
                <td><?= Helpers::e($s['start_time']) ?></td>
                <td><?= Helpers::e($s['end_time']) ?></td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditShift<?= (int)$s['id'] ?>">Editar</button>

                  <form method="POST" onsubmit="return confirm('¿Eliminar turno?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditShift<?= (int)$s['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar turno</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-2">
                          <label class="form-label">Nombre</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($s['name']) ?>" required>
                        </div>
                        <div class="row g-2">
                          <div class="col-md-6">
                            <label class="form-label">Inicio</label>
                            <input type="time" class="form-control" name="start_time" value="<?= Helpers::e(substr($s['start_time'],0,5)) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Fin</label>
                            <input type="time" class="form-control" name="end_time" value="<?= Helpers::e(substr($s['end_time'],0,5)) ?>" required>
                          </div>
                        </div>
                        <div class="text-muted small mt-2">
                          Para turno tarde (17:00–00:00) usa fin 00:00.
                        </div>
                      </div>

                      <div class="modal-footer">
                        <button class="btn btn-primary">Guardar</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($shifts)): ?>
              <tr><td colspan="5" class="text-muted">No hay turnos</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($shiftsPaginationMeta) ?>
    </div>

    <div class="modal fade" id="modalCreateShift" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nuevo turno</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-2">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" placeholder="Mañana / Tarde" required>
              </div>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">Inicio</label>
                  <input type="time" class="form-control" name="start_time" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Fin</label>
                  <input type="time" class="form-control" name="end_time" required>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-primary">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
