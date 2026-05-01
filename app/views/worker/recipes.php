<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';

$areaLabel = $areaType === 'cocina' ? 'Cocina' : 'Barra';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Recetario</h3>
        <div class="text-muted small">Area: <?= Helpers::e($areaLabel) ?></div>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="POST" id="recipeForm">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

          <div class="mb-3">
            <label class="form-label">Titulo del producto</label>
            <input class="form-control" name="title" required maxlength="180" placeholder="Ej: Chilcano de maracuya">
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0">Ingredientes</label>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addIngredient">+ Agregar ingrediente</button>
            </div>
            <div id="ingredients" class="d-flex flex-column gap-2">
              <input class="form-control" name="ingredients[]" required placeholder="Ej: 2 oz de pisco">
              <input class="form-control" name="ingredients[]" placeholder="Ej: 1 oz de jarabe">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Preparacion</label>
            <textarea class="form-control" name="preparation" rows="5" required placeholder="Describe los pasos de preparacion"></textarea>
          </div>

          <button class="btn btn-primary">Registrar receta</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Recetas aprobadas</h5>

        <?php if (empty($recipes)): ?>
          <div class="text-muted">Aun no hay recetas aprobadas para esta area.</div>
        <?php endif; ?>

        <div class="row g-3">
          <?php foreach ($recipes as $recipe): ?>
            <div class="col-lg-6">
              <div class="border rounded p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <h6 class="mb-1"><?= Helpers::e($recipe['title']) ?></h6>
                    <div class="text-muted small">Registrado por <?= Helpers::e(trim($recipe['first_name'] . ' ' . $recipe['last_name'])) ?></div>
                  </div>
                  <span class="badge text-bg-success">Aprobada</span>
                </div>

                <div class="fw-semibold small mb-1">Ingredientes</div>
                <ul class="mb-3">
                  <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                    <li><?= Helpers::e($ingredient) ?></li>
                  <?php endforeach; ?>
                </ul>

                <div class="fw-semibold small mb-1">Preparacion</div>
                <div class="text-body" style="white-space: pre-line;"><?= Helpers::e($recipe['preparation']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const addButton = document.getElementById('addIngredient');
    const container = document.getElementById('ingredients');

    if (!addButton || !container) {
      return;
    }

    addButton.addEventListener('click', function () {
      const input = document.createElement('input');
      input.className = 'form-control';
      input.name = 'ingredients[]';
      input.placeholder = 'Nuevo ingrediente';
      container.appendChild(input);
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
