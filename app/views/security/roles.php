<?php
require __DIR__ . '/../layouts/header.php';
Auth::requirePermission('security.roles');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div><h3 class="mb-1">Seguridad · Perfiles</h3><div class="text-muted">Crea roles funcionales como Barra, Compras o Supervisión.</div></div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleCreateModal">+ Crear perfil</button>
    </div>
    <?php if (!empty($msg)): ?><div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div><?php endif; ?>
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table align-middle">
        <thead><tr><th>Perfil</th><th>Descripción</th><th>Estado</th><th>Acción</th></tr></thead>
        <tbody>
          <?php foreach ($roles as $role): ?>
            <tr>
              <td><?= Helpers::e($role['name']) ?><?= (int)$role['is_system'] === 1 ? ' · Sistema' : '' ?></td>
              <td><?= Helpers::e($role['description'] ?? '-') ?></td>
              <td><span class="badge text-bg-<?= (int)$role['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$role['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
              <td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#roleModal<?= (int)$role['id'] ?>">Editar</button></td>
            </tr>
            <div class="modal fade" id="roleModal<?= (int)$role['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
              <form method="POST">
                <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$role['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Editar perfil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <label class="form-label">Nombre</label><input class="form-control mb-3" name="name" value="<?= Helpers::e($role['name']) ?>" required>
                  <label class="form-label">Descripción</label><textarea class="form-control mb-3" name="description"><?= Helpers::e($role['description'] ?? '') ?></textarea>
                  <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int)$role['is_active'] === 1 ? 'checked' : '' ?>> <span class="form-check-label">Activo</span></label>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
              </form>
            </div></div></div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<div class="modal fade" id="roleCreateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
    <div class="modal-header"><h5 class="modal-title">Crear perfil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <label class="form-label">Nombre</label><input class="form-control mb-3" name="name" required>
      <label class="form-label">Descripción</label><textarea class="form-control mb-3" name="description"></textarea>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <span class="form-check-label">Activo</span></label>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear</button></div>
  </form>
</div></div></div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
