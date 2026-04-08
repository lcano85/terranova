<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$statusesPagination = Pagination::paginateArray($statuses, 'lead_statuses_page', 'lead_statuses_per_page');
$statuses = $statusesPagination['rows'];
$statusesPaginationMeta = $statusesPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Estados Leads Cena</h3>
        <div class="text-muted small">CRUD de estados para clasificar los leads del concurso de cena.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateStatus">+ Nuevo estado</button>
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
              <th>Estado</th>
              <th>Activo</th>
              <th>Leads</th>
              <th style="width:180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($statuses as $status): ?>
              <tr>
                <td><?= (int)$status['id'] ?></td>
                <td><?= Helpers::e($status['name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$status['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$status['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td><?= (int)$status['leads_count'] ?></td>
                <td class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditStatus<?= (int)$status['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar estado?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$status['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditStatus<?= (int)$status['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$status['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar estado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Nombre</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($status['name']) ?>" required>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" id="leadStatusActive<?= (int)$status['id'] ?>" <?= (int)$status['is_active'] === 1 ? 'checked' : '' ?>>
                          <label class="form-check-label" for="leadStatusActive<?= (int)$status['id'] ?>">Activo</label>
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
            <?php if (empty($statuses)): ?>
              <tr><td colspan="5" class="text-muted">No hay estados registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($statusesPaginationMeta) ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCreateStatus" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="modal-header">
          <h5 class="modal-title">Nuevo estado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="name" required>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="leadStatusActiveNew" checked>
            <label class="form-check-label" for="leadStatusActiveNew">Activo</label>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
