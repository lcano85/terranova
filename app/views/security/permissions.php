<?php
require __DIR__ . '/../layouts/header.php';
Auth::requirePermission('security.permissions');
require_once __DIR__ . '/../../core/Csrf.php';
$groupedPermissions = [];
foreach ($permissions as $permission) {
  $groupedPermissions[$permission['parent_name'] ?: 'Opciones principales'][] = $permission;
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3"><div><h3 class="mb-1">Seguridad · Permisos</h3><div class="text-muted">Define las opciones del menú y módulos disponibles para cada perfil.</div></div></div>
    <?php if (!empty($msg)): ?><div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div><?php endif; ?>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">Perfil</label><select class="form-select" name="role_id" onchange="this.form.submit()">
          <?php foreach ($roles as $role): ?><option value="<?= (int)$role['id'] ?>" <?= $roleId === (int)$role['id'] ? 'selected' : '' ?>><?= Helpers::e($role['name']) ?></option><?php endforeach; ?>
        </select></div>
      </form>
    </div></div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>"><input type="hidden" name="role_id" value="<?= (int)$roleId ?>">
      <div class="row g-3">
        <?php foreach ($groupedPermissions as $group => $items): ?>
          <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header fw-semibold"><?= Helpers::e($group) ?></div><div class="card-body">
            <?php foreach ($items as $permission): ?>
              <label class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?= (int)$permission['id'] ?>" <?= in_array((int)$permission['id'], $selectedPermissionIds, true) ? 'checked' : '' ?>>
                <span class="form-check-label"><?= Helpers::e($permission['name']) ?><?php if ($permission['route']): ?><span class="d-block small text-muted"><?= Helpers::e($permission['route']) ?></span><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
          </div></div></div>
        <?php endforeach; ?>
      </div>
      <div class="sticky-bottom bg-light border rounded p-3 mt-3 d-flex justify-content-end"><button class="btn btn-primary">Guardar permisos</button></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
