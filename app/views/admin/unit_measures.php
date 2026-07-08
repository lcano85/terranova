<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$unitsPagination = Pagination::paginateArray($units, 'unit_measures_page', 'unit_measures_per_page');
$units = $unitsPagination['rows'];
$unitsPaginationMeta = $unitsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-1">Unidades de medida</h3>
        <div class="text-muted">Unidades disponibles para requerimientos.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateUnit">+ Registrar unidad</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th style="width:80px;">ID</th>
              <th>Nombre</th>
              <th>Abreviatura</th>
              <th>Estado</th>
              <th style="width:260px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($units as $unit): ?>
              <tr>
                <td><?= (int)$unit['id'] ?></td>
                <td><?= Helpers::e($unit['name']) ?></td>
                <td><?= Helpers::e($unit['abbreviation'] ?: '-') ?></td>
                <td>
                  <?php if ((int)$unit['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Activo</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex flex-wrap gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditUnit<?= (int)$unit['id'] ?>">Editar</button>

                  <?php if ((int)$unit['is_active'] === 1): ?>
                    <form method="POST" onsubmit="return confirm('Desactivar esta unidad de medida?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">
                      <button class="btn btn-sm btn-outline-warning">Desactivar</button>
                    </form>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">
                      <button class="btn btn-sm btn-outline-success">Activar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>

              <div class="modal fade" id="modalEditUnit<?= (int)$unit['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar unidad de medida</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Nombre</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($unit['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Abreviatura</label>
                          <input class="form-control" name="abbreviation" value="<?= Helpers::e($unit['abbreviation'] ?? '') ?>" placeholder="Ej: kg, und, lt">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Estado</label>
                          <select class="form-select" name="is_active">
                            <option value="1" <?= (int)$unit['is_active'] === 1 ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= (int)$unit['is_active'] === 0 ? 'selected' : '' ?>>Inactivo</option>
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

            <?php if (empty($units)): ?>
              <tr><td colspan="5" class="text-muted">No hay unidades de medida registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?= Pagination::render($unitsPaginationMeta) ?>
      </div>
    </div>

    <div class="modal fade" id="modalCreateUnit" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Registrar unidad de medida</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" placeholder="Ej: Unidad, Kilogramo, Litro" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Abreviatura</label>
                <input class="form-control" name="abbreviation" placeholder="Ej: und, kg, lt">
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
