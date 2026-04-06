<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$workerInventoryPagination = Pagination::paginateArray($rows, 'worker_inventory_page', 'worker_inventory_per_page');
$rows = $workerInventoryPagination['rows'];
$workerInventoryPaginationMeta = $workerInventoryPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Mi inventario</h3>
        <div class="text-muted small">
          Area: <?= Helpers::e($user['area_name'] ?? 'Sin area asignada') ?>
        </div>
      </div>
      <?php if (!empty($user['area_id'])): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateInventory">+ Nuevo item</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <?php if (empty($user['area_id'])): ?>
      <div class="alert alert-warning">No tienes un area asignada. El administrador debe asignarte una para usar inventario.</div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Item</th>
              <th>Cantidad</th>
              <th>Unidad</th>
              <th>Estado</th>
              <th>Notas</th>
              <th style="width: 220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= Helpers::e($r['name']) ?></td>
                <td><?= Helpers::e(rtrim(rtrim(number_format((float)$r['quantity'], 2, '.', ''), '0'), '.')) ?></td>
                <td><?= Helpers::e($r['unit']) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$r['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$r['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td><?= Helpers::e($r['notes'] ?? '-') ?></td>
                <td class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditInventory<?= (int)$r['id'] ?>">Editar</button>

                  <?php if ((int)$r['is_active'] === 1): ?>
                    <form method="POST" onsubmit="return confirm('Desactivar item?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-warning">Desactivar</button>
                    </form>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-success">Activar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>

              <div class="modal fade" id="modalEditInventory<?= (int)$r['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="row g-2">
                          <div class="col-md-12">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="name" value="<?= Helpers::e($r['name']) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="quantity" value="<?= Helpers::e($r['quantity']) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Unidad</label>
                            <input class="form-control" name="unit" value="<?= Helpers::e($r['unit']) ?>" placeholder="kg, unid, botellas..." required>
                          </div>
                          <div class="col-md-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notes" rows="3"><?= Helpers::e($r['notes'] ?? '') ?></textarea>
                          </div>
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

            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-muted">Aun no has registrado items de inventario</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($workerInventoryPaginationMeta) ?>
    </div>

    <?php if (!empty($user['area_id'])): ?>
      <div class="modal fade" id="modalCreateInventory" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="create">

              <div class="modal-header">
                <h5 class="modal-title">Nuevo item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-md-12">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="name" placeholder="Ej: Arroz" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Cantidad</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="quantity" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Unidad</label>
                    <input class="form-control" name="unit" placeholder="kg, unid, botellas..." required>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
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
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
