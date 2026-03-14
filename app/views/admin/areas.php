<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">Áreas de trabajo</h3>
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
              <th style="width:180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($areas as $ar): ?>
              <tr>
                <td><?= (int)$ar['id'] ?></td>
                <td><?= Helpers::e($ar['name']) ?></td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEdit<?= (int)$ar['id'] ?>">Editar</button>

                  <form method="POST" onsubmit="return confirm('¿Eliminar área?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$ar['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEdit<?= (int)$ar['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$ar['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar área</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="name" value="<?= Helpers::e($ar['name']) ?>" required>
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
              <tr><td colspan="3" class="text-muted">No hay áreas registradas</td></tr>
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
              <h5 class="modal-title">Nueva área</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <label class="form-label">Nombre</label>
              <input class="form-control" name="name" placeholder="Ej: Salón, Cocina, Barra" required>
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
