<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Mi perfil</h3>

    <div class="card shadow-sm" style="max-width: 720px;">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small">Nombres</div>
            <div class="fw-semibold"><?= Helpers::e($user['first_name'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Apellidos</div>
            <div class="fw-semibold"><?= Helpers::e($user['last_name'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Documento</div>
            <div class="fw-semibold"><?= Helpers::e(($user['document_type'] ?? '').' '.($user['document_number'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Rol</div>
            <div class="fw-semibold text-uppercase"><?= Helpers::e($user['role'] ?? '') ?></div>
          </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
          Si luego quieres “Editar perfil / cambiar clave”, lo agregamos en 5 minutos.
        </div>
      </div>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
