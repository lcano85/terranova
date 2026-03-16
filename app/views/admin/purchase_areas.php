<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">Areas de compras</h3>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">+ Nueva</button>
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
              <th>Estado</th>
              <th style="width: 240px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($areas as $area): ?>
              <tr>
                <td><?= (int)$area['id'] ?></td>
                <td><?= Helpers::e($area['name']) ?></td>
                <td>
                  <?php if ((int)$area['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Activa</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEdit<?= (int)$area['id'] ?>">Editar</button>

                  <?php if ((int)$area['is_active'] === 1): ?>
                    <form method="POST" onsubmit="return confirm('Desactivar area de compra?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                      <button class="btn btn-sm btn-outline-warning">Desactivar</button>
                    </form>
                  <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                      <button class="btn btn-sm btn-outline-success">Activar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>

              <div class="modal fade" id="modalEdit<?= (int)$area['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar area de compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="name" value="<?= Helpers::e($area['name']) ?>" required>
                      </div>

                      <div class="modal-footer">
                        <button class="btn btn-primary">Guardar</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($areas)): ?>
              <tr><td colspan="4" class="text-muted">No hay areas de compras registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="modalCreate" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nueva area de compra</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" placeholder="Ej: Mercado, Makro, Supermercado" required>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="createPurchaseAreaActive" checked>
                <label class="form-check-label" for="createPurchaseAreaActive">Activa</label>
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
