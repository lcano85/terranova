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
          Semana actual: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

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
                  <option value="<?= (int)$area['id'] ?>" <?= (int)($selectedPurchaseAreaId ?? 0) === (int)$area['id'] ? 'selected' : '' ?>><?= Helpers::e($area['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" class="form-control" name="required_date" value="<?= Helpers::e($defaultDate) ?>" min="<?= Helpers::e(date('Y-m-d')) ?>" required>
              <div class="form-text">Puedes seleccionar hoy o cualquier fecha futura.</div>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Productos</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRequirementItem">+ Agregar item</button>
          </div>

          <div id="requirementItems" class="d-flex flex-column gap-2">
            <?php $renderItems = !empty($formItems ?? []) ? $formItems : ['', '']; ?>
            <?php foreach ($renderItems as $index => $itemValue): ?>
              <input class="form-control" name="items[]" value="<?= Helpers::e((string)$itemValue) ?>" placeholder="<?= $index === 0 ? 'Ej: 1 sol de culantro' : 'Ej: 1 sol de zapallo' ?>" <?= $index === 0 ? 'required' : '' ?>>
            <?php endforeach; ?>
          </div>

          <div class="mt-3 d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" name="action" value="save_draft">Guardar</button>
            <button class="btn btn-primary" name="action" value="send">Enviar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
          <div>
            <h5 class="mb-0">Lista registrada esta semana</h5>
            <div class="text-muted small">Los borradores se pueden editar antes de enviar.</div>
          </div>
          <?php if (!empty(array_filter($grouped, static fn($group) => ($group['status'] ?? '') === 'draft'))): ?>
            <form method="POST">
              <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="submit_saved">
              <button class="btn btn-success">Enviar requerimientos guardados</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (empty($grouped)): ?>
          <div class="text-muted">Aun no tienes requerimientos registrados esta semana.</div>
        <?php endif; ?>

        <?php foreach ($grouped as $group): ?>
          <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="fw-semibold text-capitalize"><?= Helpers::e($group['purchase_area_name']) ?></div>
                <span class="badge text-bg-<?= ($group['status'] ?? '') === 'draft' ? 'warning' : 'success' ?>">
                  <?= ($group['status'] ?? '') === 'draft' ? 'Borrador' : 'Enviado' ?>
                </span>
              </div>
              <div class="text-muted small">Fecha: <?= Helpers::e(date('d/m/Y', strtotime($group['required_date']))) ?></div>
            </div>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($group['items'] as $item): ?>
                <div class="d-flex justify-content-between align-items-center gap-2 border rounded px-3 py-2">
                  <div>
                    <?= Helpers::e($item['item_name']) ?>
                    <?php if ((int)$item['is_purchased'] === 1): ?>
                      <span class="badge text-bg-success">Comprado</span>
                    <?php endif; ?>
                  </div>
                  <?php if (($group['status'] ?? '') === 'draft'): ?>
                    <form method="POST" onsubmit="return confirm('Eliminar este item del borrador?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="delete_item">
                      <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
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
