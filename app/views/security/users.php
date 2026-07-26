<?php
require __DIR__ . '/../layouts/header.php';
Auth::requirePermission('security.users');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-1">Seguridad · Usuarios</h3>
        <div class="text-muted">Asigna un perfil y sus permisos a cada usuario.</div>
      </div>
    </div>
    <?php if (!empty($msg)): ?><div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div><?php endif; ?>
    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Documento</th><th>Usuario</th><th>Tipo actual</th><th>Perfil de seguridad</th><th>Acción</th></tr></thead>
          <tbody>
            <?php foreach ($users as $row): ?>
              <tr>
                <td><?= Helpers::e($row['document_number']) ?></td>
                <td><?= Helpers::e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                <td><?= Helpers::e($row['role']) ?></td>
                <td>
                  <?php $formId = 'assignRole' . (int)$row['id']; ?>
                  <select class="form-select" name="role_id" required form="<?= $formId ?>">
                      <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= (int)$row['security_role_id'] === (int)$role['id'] ? 'selected' : '' ?>>
                          <?= Helpers::e($role['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                </td>
                <td>
                  <form method="POST" id="<?= $formId ?>">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                    <button class="btn btn-primary">Asignar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
