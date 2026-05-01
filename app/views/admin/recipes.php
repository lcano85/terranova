<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$recipesPagination = Pagination::paginateArray($recipes, 'recipes_page', 'recipes_per_page');
$recipes = $recipesPagination['rows'];
$recipesPaginationMeta = $recipesPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <h3 class="mb-0">Recetario</h3>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Area</label>
            <select class="form-select" name="area_type">
              <option value="" <?= $areaType === '' ? 'selected' : '' ?>>Todas</option>
              <option value="cocina" <?= $areaType === 'cocina' ? 'selected' : '' ?>>Cocina</option>
              <option value="barra" <?= $areaType === 'barra' ? 'selected' : '' ?>>Barra</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select class="form-select" name="status">
              <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
              <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendiente</option>
              <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprobada</option>
            </select>
          </div>
          <div class="col-md-4">
            <button class="btn btn-outline-primary">Filtrar</button>
            <a class="btn btn-outline-secondary" href="/admin/recipes">Limpiar</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <?php if (empty($recipes)): ?>
          <div class="text-muted">No hay recetas registradas.</div>
        <?php endif; ?>

        <div class="row g-3">
          <?php foreach ($recipes as $recipe): ?>
            <?php
              $modalId = 'modalRecipe' . (int)$recipe['id'];
              $areaLabel = $recipe['area_type'] === 'cocina' ? 'Cocina' : 'Barra';
              $statusLabel = $recipe['status'] === 'approved' ? 'Aprobada' : 'Pendiente';
              $statusClass = $recipe['status'] === 'approved' ? 'success' : 'warning';
            ?>
            <div class="col-xl-6">
              <div class="border rounded p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <h5 class="mb-1"><?= Helpers::e($recipe['title']) ?></h5>
                    <div class="text-muted small">
                      <?= Helpers::e($areaLabel) ?> - Registrado por <?= Helpers::e(trim($recipe['first_name'] . ' ' . $recipe['last_name'])) ?>
                    </div>
                  </div>
                  <span class="badge text-bg-<?= Helpers::e($statusClass) ?>"><?= Helpers::e($statusLabel) ?></span>
                </div>

                <div class="fw-semibold small mb-1">Ingredientes</div>
                <ul class="mb-3">
                  <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                    <li><?= Helpers::e($ingredient) ?></li>
                  <?php endforeach; ?>
                </ul>

                <div class="fw-semibold small mb-1">Preparacion</div>
                <div class="mb-3" style="white-space: pre-line;"><?= Helpers::e($recipe['preparation']) ?></div>

                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#<?= Helpers::e($modalId) ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar esta receta?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$recipe['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </div>
              </div>
            </div>

            <div class="modal fade" id="<?= Helpers::e($modalId) ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$recipe['id'] ?>">

                    <div class="modal-header">
                      <h5 class="modal-title">Editar receta</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <div class="row g-3">
                        <div class="col-md-8">
                          <label class="form-label">Titulo del producto</label>
                          <input class="form-control" name="title" value="<?= Helpers::e($recipe['title']) ?>" required maxlength="180">
                        </div>
                        <div class="col-md-4">
                          <label class="form-label">Area</label>
                          <select class="form-select" name="area_type" required>
                            <option value="cocina" <?= $recipe['area_type'] === 'cocina' ? 'selected' : '' ?>>Cocina</option>
                            <option value="barra" <?= $recipe['area_type'] === 'barra' ? 'selected' : '' ?>>Barra</option>
                          </select>
                        </div>

                        <div class="col-md-12">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Ingredientes</label>
                            <button type="button" class="btn btn-sm btn-outline-primary js-add-ingredient" data-target="ingredients<?= (int)$recipe['id'] ?>">+ Agregar ingrediente</button>
                          </div>
                          <div id="ingredients<?= (int)$recipe['id'] ?>" class="d-flex flex-column gap-2">
                            <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                              <input class="form-control" name="ingredients[]" value="<?= Helpers::e($ingredient) ?>" required>
                            <?php endforeach; ?>
                          </div>
                        </div>

                        <div class="col-md-12">
                          <label class="form-label">Preparacion</label>
                          <textarea class="form-control" name="preparation" rows="6" required><?= Helpers::e($recipe['preparation']) ?></textarea>
                        </div>

                        <div class="col-md-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="approved" id="approved<?= (int)$recipe['id'] ?>" <?= $recipe['status'] === 'approved' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="approved<?= (int)$recipe['id'] ?>">Aprobada y visible para trabajadores</label>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button class="btn btn-primary">Guardar cambios</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?= Pagination::render($recipesPaginationMeta) ?>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-add-ingredient').forEach(function (button) {
      button.addEventListener('click', function () {
        const target = document.getElementById(button.dataset.target);
        if (!target) {
          return;
        }

        const input = document.createElement('input');
        input.className = 'form-control';
        input.name = 'ingredients[]';
        input.placeholder = 'Nuevo ingrediente';
        target.appendChild(input);
      });
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
