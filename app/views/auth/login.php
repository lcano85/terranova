<?php
require __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="container py-5" style="max-width: 420px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-1">Iniciar sesion</h4>
      <div class="text-muted mb-3">Acceso admin / trabajador</div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= Helpers::e($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

        <div class="mb-3">
          <label class="form-label">Tipo documento</label>
          <select class="form-select" name="document_type" required>
            <option value="dni">DNI</option>
            <option value="cedula">Cedula</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Nro documento</label>
          <input class="form-control" name="document_number" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Clave</label>
          <input type="password" class="form-control" name="password" required>
        </div>

        <button class="btn btn-primary w-100">Entrar</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
