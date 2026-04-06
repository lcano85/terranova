<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Mis requerimientos</h3>
        <div class="text-muted small">
          Semana visible: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Semana</label>
            <select class="form-select" name="week_start" onchange="this.form.submit()">
              <?php foreach ($weekOptions as $option): ?>
                <option value="<?= Helpers::e($option['from']) ?>" <?= $selectedWeekStart === $option['from'] ? 'selected' : '' ?>>
                  <?= Helpers::e($option['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid">
            <label class="form-label">&nbsp;</label>
            <a class="btn btn-outline-secondary" href="/worker/requirements">Semana actual</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="POST" id="requirementsForm">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Area de compra</label>
              <select class="form-select" name="purchase_area_id" required>
                <option value="">Selecciona</option>
                <?php foreach ($purchaseAreas as $area): ?>
                  <option value="<?= (int)$area['id'] ?>"><?= Helpers::e($area['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="required_date" value="<?= Helpers::e($defaultDate) ?>" required>
              <div class="form-text">Solo se permiten jueves o sabado.</div>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Productos</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRequirementItem">+ Agregar item</button>
          </div>

          <div id="requirementItems" class="d-flex flex-column gap-2">
            <input class="form-control" name="items[]" placeholder="Ej: 1 sol de culantro" required>
            <input class="form-control" name="items[]" placeholder="Ej: 1 sol de zapallo">
          </div>

          <div class="mt-3">
            <button class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Lista registrada en la semana seleccionada</h5>

        <?php if (empty($grouped)): ?>
          <div class="text-muted">Aun no tienes requerimientos registrados en la semana seleccionada.</div>
        <?php endif; ?>

        <?php foreach ($grouped as $group): ?>
          <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-semibold text-capitalize"><?= Helpers::e($group['purchase_area_name']) ?></div>
              <div class="text-muted small">Fecha: <?= Helpers::e(date('d/m/Y', strtotime($group['required_date']))) ?></div>
            </div>
            <ul class="mb-0">
              <?php foreach ($group['items'] as $item): ?>
                <li>
                  <?= Helpers::e($item['item_name']) ?>
                  <?php if ((int)$item['is_purchased'] === 1): ?>
                    <span class="badge text-bg-success">Comprado</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const addButton = document.getElementById('addRequirementItem');
    const itemsContainer = document.getElementById('requirementItems');

    if (!addButton || !itemsContainer) {
      return;
    }

    addButton.addEventListener('click', function () {
      const input = document.createElement('input');
      input.className = 'form-control';
      input.name = 'items[]';
      input.placeholder = 'Ej: 1 java de huevos';
      itemsContainer.appendChild(input);
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
