<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';

$suppliesForJs = array_map(static function ($supply) {
  return [
    'id' => (int)$supply['id'],
    'name' => (string)$supply['name'],
    'area_ids' => array_values(array_filter(array_map('intval', explode(',', (string)($supply['purchase_area_ids'] ?? ''))))),
  ];
}, $supplies ?? []);

$unitMeasuresForJs = array_map(static function ($unit) {
  return [
    'id' => (int)$unit['id'],
    'name' => (string)$unit['name'],
    'abbreviation' => (string)($unit['abbreviation'] ?? ''),
  ];
}, $unitMeasures ?? []);
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

          <datalist id="workerRequirementSupplyOptions"></datalist>

          <div id="requirementItems" class="d-flex flex-column gap-2">
            <?php $renderItems = !empty($formItems ?? []) ? $formItems : [['supply_id' => '', 'item_name' => '', 'detail' => '', 'quantity' => '', 'unit_measure_id' => '']]; ?>
            <?php foreach ($renderItems as $index => $itemValue): ?>
              <?php
                $itemRow = is_array($itemValue) ? $itemValue : ['supply_id' => '', 'item_name' => (string)$itemValue, 'detail' => '', 'quantity' => '', 'unit_measure_id' => ''];
              ?>
              <div class="row g-2 align-items-start" data-worker-requirement-item>
                <div class="col-md-4">
                  <input type="hidden" name="supply_ids[]" value="<?= (int)($itemRow['supply_id'] ?? 0) ?>" data-supply-id>
                  <input class="form-control" name="items[]" value="<?= Helpers::e((string)($itemRow['item_name'] ?? '')) ?>" list="workerRequirementSupplyOptions" placeholder="Escribe y selecciona un insumo" data-supply-name required>
                  <div class="form-text text-danger d-none" data-supply-error>Selecciona un insumo valido del listado.</div>
                </div>
                <div class="col-md-3">
                  <input class="form-control" name="details[]" value="<?= Helpers::e((string)($itemRow['detail'] ?? '')) ?>" placeholder="Detalle (opcional)" maxlength="255">
                </div>
                <div class="col-md-1">
                  <input class="form-control" type="number" name="quantities[]" value="<?= Helpers::e((string)($itemRow['quantity'] ?? '')) ?>" placeholder="Cant." min="0.01" step="0.01" required>
                </div>
                <div class="col-md-3">
                  <select class="form-select" name="unit_measure_ids[]" required data-unit-select>
                    <option value="">Unidad</option>
                    <?php foreach ($unitMeasures as $unit): ?>
                      <option value="<?= (int)$unit['id'] ?>" <?= (int)($itemRow['unit_measure_id'] ?? 0) === (int)$unit['id'] ? 'selected' : '' ?>>
                        <?= Helpers::e($unit['abbreviation'] ? $unit['name'] . ' (' . $unit['abbreviation'] . ')' : $unit['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-1 d-grid">
                  <button type="button" class="btn btn-outline-danger" data-remove-worker-item <?= $index === 0 && count($renderItems) === 1 ? 'disabled' : '' ?>>&times;</button>
                </div>
              </div>
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
                    <?= Helpers::e(Requirement::itemDisplayName($item)) ?>
                    <?php if (!empty($item['detail'])): ?>
                      <div class="small text-muted">Detalle: <?= Helpers::e($item['detail']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['item_created_at'])): ?>
                      <div class="small text-muted">Hora de registro: <?= Helpers::e(date('H:i', strtotime($item['item_created_at']))) ?></div>
                    <?php endif; ?>
                    <?php if ((int)$item['is_purchased'] === 1): ?>
                      <span class="badge text-bg-success">Comprado</span>
                      <?php if (!empty($item['purchased_at'])): ?>
                        <div class="small text-success">
                          Fecha completado: <?= Helpers::e(date('d/m/Y', strtotime($item['purchased_at']))) ?>
                          - Hora completado: <?= Helpers::e(date('H:i', strtotime($item['purchased_at']))) ?>
                        </div>
                      <?php endif; ?>
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
    const requirementSupplies = <?= json_encode($suppliesForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
    const requirementUnits = <?= json_encode($unitMeasuresForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
    const addButton = document.getElementById('addRequirementItem');
    const itemsContainer = document.getElementById('requirementItems');
    const supplyOptions = document.getElementById('workerRequirementSupplyOptions');

    if (!addButton || !itemsContainer) {
      return;
    }

    function availableSupplies() {
      return requirementSupplies;
    }

    function refreshSupplyOptions() {
      if (!supplyOptions) {
        return;
      }
      supplyOptions.innerHTML = '';
      availableSupplies().forEach(function(supply) {
        const option = document.createElement('option');
        option.value = supply.name;
        supplyOptions.appendChild(option);
      });
    }

    function resolveSupply(input) {
      const row = input.closest('[data-worker-requirement-item]');
      const hidden = row?.querySelector('[data-supply-id]');
      const error = row?.querySelector('[data-supply-error]');
      const value = input.value.trim().toLowerCase();
      const match = availableSupplies().find(function(supply) {
        return String(supply.name || '').trim().toLowerCase() === value;
      });

      if (hidden) {
        hidden.value = match ? String(match.id) : '';
      }
      if (error) {
        error.classList.toggle('d-none', input.value.trim() === '' || !!match);
      }
    }

    function unitOptionsHtml() {
      return '<option value="">Unidad</option>' + requirementUnits.map(function(unit) {
        const label = unit.abbreviation ? unit.name + ' (' + unit.abbreviation + ')' : unit.name;
        return '<option value="' + unit.id + '">' + label.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
      }).join('');
    }

    function bindRequirementItem(row) {
      const input = row.querySelector('[data-supply-name]');
      const removeButton = row.querySelector('[data-remove-worker-item]');
      if (input) {
        input.addEventListener('input', function() {
          resolveSupply(input);
        });
        input.addEventListener('change', function() {
          resolveSupply(input);
        });
      }
      if (removeButton) {
        removeButton.addEventListener('click', function() {
          row.remove();
          updateRemoveButtons();
        });
      }
    }

    function updateRemoveButtons() {
      const rows = itemsContainer.querySelectorAll('[data-worker-requirement-item]');
      rows.forEach(function(row) {
        const button = row.querySelector('[data-remove-worker-item]');
        if (button) {
          button.disabled = rows.length <= 1;
        }
      });
    }

    function addRequirementItem() {
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-start';
      row.setAttribute('data-worker-requirement-item', '');
      row.innerHTML =
        '<div class="col-md-4">' +
          '<input type="hidden" name="supply_ids[]" data-supply-id>' +
          '<input class="form-control" name="items[]" list="workerRequirementSupplyOptions" placeholder="Escribe y selecciona un insumo" data-supply-name required>' +
          '<div class="form-text text-danger d-none" data-supply-error>Selecciona un insumo valido del listado.</div>' +
        '</div>' +
        '<div class="col-md-3">' +
          '<input class="form-control" name="details[]" placeholder="Detalle (opcional)" maxlength="255">' +
        '</div>' +
        '<div class="col-md-1">' +
          '<input class="form-control" type="number" name="quantities[]" placeholder="Cant." min="0.01" step="0.01" required>' +
        '</div>' +
        '<div class="col-md-3">' +
          '<select class="form-select" name="unit_measure_ids[]" required data-unit-select>' + unitOptionsHtml() + '</select>' +
        '</div>' +
        '<div class="col-md-1 d-grid">' +
          '<button type="button" class="btn btn-outline-danger" data-remove-worker-item>&times;</button>' +
        '</div>';

      itemsContainer.appendChild(row);
      bindRequirementItem(row);
      updateRemoveButtons();
    }

    refreshSupplyOptions();

    itemsContainer.querySelectorAll('[data-worker-requirement-item]').forEach(bindRequirementItem);
    updateRemoveButtons();
    addButton.addEventListener('click', addRequirementItem);
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
