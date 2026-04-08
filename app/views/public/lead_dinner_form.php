<?php
require __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="container py-5" style="max-width: 760px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h3 class="mb-1">Concurso Cena</h3>
      <div class="text-muted mb-3">Registra tus datos y adjunta tu voucher de consumo para participar.</div>

      <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombres</label>
            <input class="form-control" name="first_name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Apellidos</label>
            <input class="form-control" name="last_name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">WhatsApp</label>
            <input class="form-control" name="whatsapp" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Correo</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="col-12">
            <label class="form-label">Voucher de consumo</label>
            <input type="file" class="form-control" name="voucher" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
            <div class="form-text">Formatos permitidos: JPG, PNG, WEBP o PDF.</div>
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-primary">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
