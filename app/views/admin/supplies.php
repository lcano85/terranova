<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-1">Insumos</h3>
        <div class="text-muted">Registro de insumos por una o mas areas de compras.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateSupply">+ Registrar insumo</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input class="form-control" name="search" value="<?= Helpers::e($search ?? '') ?>" placeholder="Nombre del insumo o area">
          </div>
          <div class="col-md-3">
            <label class="form-label">Area de compras</label>
            <select class="form-select" name="purchase_area_id">
              <option value="">Todas las areas</option>
              <?php foreach ($purchaseAreas as $area): ?>
                <option value="<?= (int)$area['id'] ?>" <?= (int)($purchaseAreaId ?? 0) === (int)$area['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($area['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select class="form-select" name="status">
              <option value="" <?= ($rawStatus ?? '') === '' ? 'selected' : '' ?>>Todos</option>
              <option value="1" <?= ($rawStatus ?? '') === '1' ? 'selected' : '' ?>>Activo</option>
              <option value="0" <?= ($rawStatus ?? '') === '0' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </div>
          <div class="col-md-3 d-grid d-md-flex gap-2">
            <button class="btn btn-primary">Buscar</button>
            <a class="btn btn-outline-secondary" href="/admin/supplies">Limpiar</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th style="width:80px;">ID</th>
              <th>Nombre del insumo</th>
              <th>Areas de compras</th>
              <th>Estado</th>
              <th style="width:250px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($supplies as $supply): ?>
              <?php $selectedAreaIds = array_filter(array_map('intval', explode(',', (string)($supply['purchase_area_ids'] ?? '')))); ?>
              <tr>
                <td><?= (int)$supply['id'] ?></td>
                <td><?= Helpers::e($supply['name']) ?></td>
                <td><?= Helpers::e($supply['purchase_area_names'] ?: 'Sin areas asignadas') ?></td>
                <td>
                  <?php if ((int)$supply['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Activo</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex flex-wrap gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditSupply<?= (int)$supply['id'] ?>">Editar</button>

                  <?php if ((int)$supply['is_active'] === 1): ?>
                    <form method="POST" onsubmit="return confirm('Desactivar este insumo?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="id" value="<?= (int)$supply['id'] ?>">
                      <button class="btn btn-sm btn-outline-warning">Desactivar</button>
                    </form>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="id" value="<?= (int)$supply['id'] ?>">
                      <button class="btn btn-sm btn-outline-success">Activar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>

              <div class="modal fade" id="modalEditSupply<?= (int)$supply['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$supply['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar insumo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Nombre del insumo</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($supply['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Areas de compras</label>
                          <div class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                            <?php foreach ($purchaseAreas as $area): ?>
                              <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="purchase_area_ids[]"
                                       id="editSupplyArea<?= (int)$supply['id'] ?>_<?= (int)$area['id'] ?>"
                                       value="<?= (int)$area['id'] ?>"
                                       <?= in_array((int)$area['id'], $selectedAreaIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="editSupplyArea<?= (int)$supply['id'] ?>_<?= (int)$area['id'] ?>">
                                  <?= Helpers::e($area['name']) ?>
                                </label>
                              </div>
                            <?php endforeach; ?>
                          </div>
                          <div class="form-text">Selecciona una o mas areas.</div>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Estado</label>
                          <select class="form-select" name="is_active">
                            <option value="1" <?= (int)$supply['is_active'] === 1 ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= (int)$supply['is_active'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                          </select>
                        </div>
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary">Guardar cambios</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($supplies)): ?>
              <tr><td colspan="5" class="text-muted">No hay insumos registrados con los filtros actuales.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?= Pagination::render($suppliesPaginationMeta) ?>
      </div>
    </div>

    <div class="modal fade" id="modalCreateSupply" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Registrar insumo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nombre del insumo</label>
                <input class="form-control" name="name" placeholder="Ej: Limpiavidrios" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Areas de compras</label>
                <div class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                  <?php foreach ($purchaseAreas as $area): ?>
                    <div class="form-check">
                      <input class="form-check-input"
                             type="checkbox"
                             name="purchase_area_ids[]"
                             id="createSupplyArea<?= (int)$area['id'] ?>"
                             value="<?= (int)$area['id'] ?>">
                      <label class="form-check-label" for="createSupplyArea<?= (int)$area['id'] ?>">
                        <?= Helpers::e($area['name']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-text">Selecciona una o mas areas.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="is_active">
                  <option value="1" selected>Activo</option>
                  <option value="0">Inactivo</option>
                </select>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-primary">Registrar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
