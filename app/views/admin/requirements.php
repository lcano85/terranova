<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';

$suppliesForJs = array_map(static function ($supply) {
  return [
    'id' => (int)$supply['id'],
    'name' => (string)$supply['name'],
    'price' => $supply['price'] !== null ? (float)$supply['price'] : null,
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
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Requerimientos</h3>
        <div class="text-muted small">
          Semana visible: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>
      </div>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createRequirementModal">
        + Agregar requerimiento
      </button>
    </div>

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
            <a class="btn btn-outline-secondary" href="/admin/requirements">Semana actual</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white">
        <button class="btn w-100 p-0 text-start collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mailLogCollapse" aria-expanded="false" aria-controls="mailLogCollapse">
          <div class="page-toolbar">
            <div>
              <h5 class="mb-0">Log de correos</h5>
              <div class="text-muted small">Ultimos intentos de notificacion por correo al administrador.</div>
            </div>
            <span class="btn btn-sm btn-outline-secondary">Mostrar / ocultar</span>
          </div>
        </button>
      </div>
      <div class="collapse" id="mailLogCollapse">
        <div class="card-body table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Asunto</th>
                <th>Destinatarios</th>
                <th>Estado</th>
                <th>Error</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mailLogs as $log): ?>
                <tr>
                  <td><?= Helpers::e(Helpers::formatDateTime($log['created_at'])) ?></td>
                  <td><?= Helpers::e($log['subject']) ?></td>
                  <td><?= Helpers::e($log['recipients'] ?? '-') ?></td>
                  <td>
                    <span class="badge text-bg-<?= ($log['status'] ?? '') === 'sent' ? 'success' : (($log['status'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>">
                      <?= Helpers::e($log['status']) ?>
                    </span>
                  </td>
                  <td><?= Helpers::e($log['error_message'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($mailLogs)): ?>
                <tr>
                  <td colspan="5" class="text-muted">Aun no hay correos registrados.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal fade" id="createRequirementModal" tabindex="-1" aria-labelledby="createRequirementModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create_requirement">

            <div class="modal-header">
              <div>
                <h5 class="modal-title" id="createRequirementModalLabel">Registrar requerimiento</h5>
                <div class="text-muted small">Agrega un requerimiento faltante a nombre de un trabajador o administrador.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Trabajador / Administrador</label>
                  <select class="form-select" name="user_id" required>
                    <option value="">Selecciona trabajador o administrador</option>
                    <?php foreach ($workers as $worker): ?>
                      <option value="<?= (int)$worker['id'] ?>">
                        <?= Helpers::e(($worker['role'] === 'admin' ? 'Administrador' : 'Trabajador') . ' - ' . $worker['document_number'] . ' - ' . $worker['first_name'] . ' ' . $worker['last_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Fecha requerida</label>
                  <input type="date" class="form-control" name="required_date" value="<?= Helpers::e($defaultRequirementDate) ?>" required>
                </div>
              </div>

              <hr class="my-4">

              <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h6 class="mb-0">Productos</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addAdminRequirementItem">+ Agregar producto</button>
              </div>

              <datalist id="adminRequirementSupplyOptions"></datalist>

              <div id="adminRequirementItems" class="d-flex flex-column gap-2">
                <div class="row g-2 align-items-start" data-admin-requirement-item>
                  <div class="col-md-4">
                    <input type="hidden" name="supply_ids[]" data-supply-id>
                    <input class="form-control" name="items[]" list="adminRequirementSupplyOptions" placeholder="Escribe y selecciona un insumo" data-supply-name required>
                    <div class="form-text text-danger d-none" data-supply-error>Selecciona un insumo valido del listado.</div>
                  </div>
                  <div class="col-md-3">
                    <input class="form-control" name="details[]" placeholder="Detalle (opcional)" maxlength="255">
                  </div>
                  <div class="col-md-1">
                    <input class="form-control" type="number" name="quantities[]" placeholder="Cant." min="0.01" step="0.01" required>
                  </div>
                  <div class="col-md-3">
                    <select class="form-select" name="unit_measure_ids[]" required data-unit-select>
                      <option value="">Unidad</option>
                      <?php foreach ($unitMeasures as $unit): ?>
                        <option value="<?= (int)$unit['id'] ?>">
                          <?= Helpers::e($unit['abbreviation'] ? $unit['name'] . ' (' . $unit['abbreviation'] . ')' : $unit['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger" data-remove-admin-item disabled>&times;</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-primary">Registrar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3" data-requirement-week-summary>
      <div class="col-md-4">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Total estimado de la semana</div>
            <div class="fs-4 fw-semibold" data-week-estimated-total>S/ <?= number_format((float)$weeklyEstimatedTotal, 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100 border-success">
          <div class="card-body">
            <div class="text-muted small">Total comprado</div>
            <div class="fs-4 fw-semibold text-success" data-week-purchased-total>S/ <?= number_format((float)$weeklyPurchasedTotal, 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100 border-warning">
          <div class="card-body">
            <div class="text-muted small">Total pendiente</div>
            <div class="fs-4 fw-semibold text-warning" data-week-pending-total>S/ <?= number_format(max(0, (float)$weeklyEstimatedTotal - (float)$weeklyPurchasedTotal), 2) ?></div>
            <?php if ((int)$weeklyUnpricedItems > 0): ?>
              <div class="small text-danger mt-1"><?= (int)$weeklyUnpricedItems ?> producto(s) sin precio o cantidad no incluidos.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($grouped)): ?>
      <div class="card shadow-sm">
        <div class="card-body text-muted">No hay requerimientos registrados para la semana seleccionada.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($grouped as $workerIndex => $worker): ?>
      <?php
      $workerTotalItems = 0;
      $workerPendingItems = 0;
      foreach ($worker['areas'] as $area) {
        foreach ($area['items'] as $item) {
          $workerTotalItems++;
          if ((int)$item['is_purchased'] !== 1) {
            $workerPendingItems++;
          }
        }
      }
      $workerPurchasedItems = max(0, $workerTotalItems - $workerPendingItems);
      $workerIsAdmin = ($worker['user_role'] ?? '') === 'admin';
      $workerIsOpen = $workerPendingItems > 0;
      $workerCollapseId = 'workerRequirements' . (int)$workerIndex;
      ?>
      <div class="card shadow-sm mb-3" data-requirement-worker-card>
        <div class="card-header bg-white">
          <button class="btn w-100 p-0 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?= Helpers::e($workerCollapseId) ?>" aria-expanded="<?= $workerIsOpen ? 'true' : 'false' ?>" aria-controls="<?= Helpers::e($workerCollapseId) ?>">
            <div class="page-toolbar">
              <div>
                <h5 class="mb-1"><?= $workerIsAdmin ? 'Administrador' : 'Trabajador' ?>: <?= Helpers::e($worker['worker_name']) ?></h5>
                <div class="text-muted small">
                  <span data-worker-purchased-count><?= (int)$workerPurchasedItems ?></span> comprado(s) /
                  <span data-worker-total-count><?= (int)$workerTotalItems ?></span> producto(s)
                  &middot; Total: S/ <?= number_format((float)$worker['estimated_total'], 2) ?>
                  &middot; Comprado: <span data-worker-purchased-total>S/ <?= number_format((float)$worker['purchased_total'], 2) ?></span>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge <?= $workerPendingItems > 0 ? 'text-bg-warning' : 'text-bg-success' ?>" data-worker-status>
                  <?= $workerPendingItems > 0 ? (int)$workerPendingItems . ' pendiente(s)' : 'Completo' ?>
                </span>
                <span class="btn btn-sm btn-outline-secondary">Ver contenido</span>
              </div>
            </div>
          </button>
        </div>

        <div class="collapse <?= $workerIsOpen ? 'show' : '' ?>" id="<?= Helpers::e($workerCollapseId) ?>">
          <div class="card-body">

            <?php foreach ($worker['areas'] as $area): ?>
              <div class="border rounded p-3 mb-3" data-requirement-area>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <div class="fw-semibold text-capitalize">Area de compra: <?= Helpers::e($area['purchase_area_name']) ?></div>
                    <span class="badge text-bg-<?= ($area['status'] ?? '') === 'draft' ? 'warning' : 'success' ?>">
                      <?= ($area['status'] ?? '') === 'draft' ? 'Borrador' : 'Enviado' ?>
                    </span>
                  </div>
                  <div class="text-end">
                    <div class="text-muted small">Fecha: <?= Helpers::e(date('d/m/Y', strtotime($area['required_date']))) ?></div>
                    <div class="fw-semibold">Subtotal: S/ <?= number_format((float)$area['estimated_total'], 2) ?></div>
                    <div class="small text-success">Comprado: <span data-area-purchased-total>S/ <?= number_format((float)$area['purchased_total'], 2) ?></span></div>
                  </div>
                </div>

                <div class="d-flex flex-column gap-2">
                  <?php foreach ($area['items'] as $item): ?>
                    <div class="border rounded px-3 py-2" data-requirement-item data-subtotal="<?= $item['subtotal'] !== null ? Helpers::e(number_format((float)$item['subtotal'], 2, '.', '')) : '' ?>">
                      <div class="d-flex align-items-center gap-2">
                        <form method="POST" action="<?= Helpers::e(BASE_URL . '/admin/requirements') ?>" class="js-requirement-toggle-form form-check d-flex align-items-center gap-2 flex-grow-1 mb-0">
                          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                          <input type="hidden" name="action" value="toggle_item">
                          <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">

                          <input class="form-check-input mt-0"
                            type="checkbox"
                            name="is_purchased"
                            value="1"
                            data-role="purchase-toggle"
                            <?= (int)$item['is_purchased'] === 1 ? 'checked' : '' ?>>
                          <label class="form-check-label flex-grow-1">
                            <?= Helpers::e($item['item_name']) ?>
                            <?php if (!empty($item['detail'])): ?>
                              <div class="small text-muted">Detalle: <?= Helpers::e($item['detail']) ?></div>
                            <?php endif; ?>
                            <?php if ($item['quantity'] !== null || !empty($item['unit_measure_name'])): ?>
                              <span class="text-muted small">
                                -
                                <?= $item['quantity'] !== null ? Helpers::e(rtrim(rtrim(number_format((float)$item['quantity'], 2, '.', ''), '0'), '.')) : '' ?>
                                <?= Helpers::e($item['unit_measure_abbreviation'] ?: ($item['unit_measure_name'] ?? '')) ?>
                              </span>
                            <?php endif; ?>
                          </label>
                          <div class="text-end small" style="min-width: 150px;">
                            <?php if ($item['unit_price'] !== null): ?>
                              <div class="text-muted">Precio: S/ <?= number_format((float)$item['unit_price'], 2) ?></div>
                              <?php if ($item['subtotal'] !== null): ?>
                                <div class="fw-semibold">Subtotal: S/ <?= number_format((float)$item['subtotal'], 2) ?></div>
                              <?php endif; ?>
                            <?php else: ?>
                              <span class="badge text-bg-danger">Sin precio</span>
                            <?php endif; ?>
                          </div>
                          <span class="js-purchase-status badge text-bg-<?= (int)$item['is_purchased'] === 1 ? 'success' : 'secondary' ?>">
                            <?= (int)$item['is_purchased'] === 1 ? 'Comprado' : 'Pendiente' ?>
                          </span>
                          <noscript>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button>
                          </noscript>
                        </form>

                        <form method="POST" class="mb-0" onsubmit="return confirm('Eliminar este item del requerimiento?');">
                          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                          <input type="hidden" name="action" value="delete_item">
                          <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                          <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const requirementSupplies = <?= json_encode($suppliesForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
    const requirementUnits = <?= json_encode($unitMeasuresForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
    const addAdminItemButton = document.getElementById('addAdminRequirementItem');
    const adminItemsContainer = document.getElementById('adminRequirementItems');
    const supplyOptions = document.getElementById('adminRequirementSupplyOptions');
    const forms = document.querySelectorAll('.js-requirement-toggle-form');

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
      const row = input.closest('[data-admin-requirement-item]');
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
      const removeButton = row.querySelector('[data-remove-admin-item]');
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
      const rows = adminItemsContainer ? adminItemsContainer.querySelectorAll('[data-admin-requirement-item]') : [];
      rows.forEach(function(row) {
        const button = row.querySelector('[data-remove-admin-item]');
        if (button) {
          button.disabled = rows.length <= 1;
        }
      });
    }

    function addRequirementItem() {
      if (!adminItemsContainer) {
        return;
      }

      const row = document.createElement('div');
      row.className = 'row g-2 align-items-start';
      row.setAttribute('data-admin-requirement-item', '');
      row.innerHTML =
        '<div class="col-md-4">' +
          '<input type="hidden" name="supply_ids[]" data-supply-id>' +
          '<input class="form-control" name="items[]" list="adminRequirementSupplyOptions" placeholder="Escribe y selecciona un insumo" data-supply-name required>' +
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
          '<button type="button" class="btn btn-outline-danger" data-remove-admin-item>&times;</button>' +
        '</div>';

      adminItemsContainer.appendChild(row);
      bindRequirementItem(row);
      updateRemoveButtons();
    }

    refreshSupplyOptions();

    if (addAdminItemButton && adminItemsContainer) {
      adminItemsContainer.querySelectorAll('[data-admin-requirement-item]').forEach(bindRequirementItem);
      updateRemoveButtons();
      addAdminItemButton.addEventListener('click', addRequirementItem);
    }

    function updateWorkerSummary(form) {
      const card = form.closest('[data-requirement-worker-card]');
      if (!card) {
        return;
      }

      const toggles = card.querySelectorAll('[data-role="purchase-toggle"]');
      const purchasedCount = Array.from(toggles).filter(function(toggle) {
        return toggle.checked;
      }).length;
      const pendingCount = toggles.length - purchasedCount;
      const purchasedEl = card.querySelector('[data-worker-purchased-count]');
      const totalEl = card.querySelector('[data-worker-total-count]');
      const statusEl = card.querySelector('[data-worker-status]');

      if (purchasedEl) {
        purchasedEl.textContent = String(purchasedCount);
      }
      if (totalEl) {
        totalEl.textContent = String(toggles.length);
      }
      if (statusEl) {
        statusEl.textContent = pendingCount > 0 ? pendingCount + ' pendiente(s)' : 'Completo';
        statusEl.classList.remove('text-bg-warning', 'text-bg-success');
        statusEl.classList.add(pendingCount > 0 ? 'text-bg-warning' : 'text-bg-success');
      }

      const section = card.querySelector('.collapse');
      const button = section ? card.querySelector('[data-bs-target="#' + section.id + '"]') : null;
      if (section && button) {
        button.setAttribute('aria-expanded', pendingCount > 0 ? 'true' : 'false');
        if (window.bootstrap?.Collapse) {
          const collapse = window.bootstrap.Collapse.getOrCreateInstance(section, {
            toggle: false
          });
          if (pendingCount > 0) {
            collapse.show();
          } else {
            collapse.hide();
          }
        } else {
          section.classList.toggle('show', pendingCount > 0);
        }
      }
    }

    function formatCurrency(value) {
      return 'S/ ' + Number(value || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function purchasedTotal(container) {
      return Array.from(container.querySelectorAll('[data-requirement-item]')).reduce(function(total, item) {
        const toggle = item.querySelector('[data-role="purchase-toggle"]');
        const subtotal = Number(item.dataset.subtotal || 0);
        return total + (toggle?.checked ? subtotal : 0);
      }, 0);
    }

    function updateFinancialSummary() {
      document.querySelectorAll('[data-requirement-area]').forEach(function(area) {
        const target = area.querySelector('[data-area-purchased-total]');
        if (target) {
          target.textContent = formatCurrency(purchasedTotal(area));
        }
      });

      document.querySelectorAll('[data-requirement-worker-card]').forEach(function(card) {
        const target = card.querySelector('[data-worker-purchased-total]');
        if (target) {
          target.textContent = formatCurrency(purchasedTotal(card));
        }
      });

      const page = document.querySelector('[data-requirement-week-summary]')?.parentElement;
      if (!page) {
        return;
      }
      const estimatedText = document.querySelector('[data-week-estimated-total]')?.textContent || '';
      const estimated = Number(estimatedText.replace(/[^0-9.-]/g, '')) || 0;
      const purchased = purchasedTotal(page);
      const purchasedTarget = document.querySelector('[data-week-purchased-total]');
      const pendingTarget = document.querySelector('[data-week-pending-total]');
      if (purchasedTarget) {
        purchasedTarget.textContent = formatCurrency(purchased);
      }
      if (pendingTarget) {
        pendingTarget.textContent = formatCurrency(Math.max(0, estimated - purchased));
      }
    }

    forms.forEach(function(form) {
      const checkbox = form.querySelector('[data-role="purchase-toggle"]');
      const statusBadge = form.querySelector('.js-purchase-status');
      if (!checkbox || !statusBadge) {
        return;
      }

      checkbox.addEventListener('change', async function() {
        const previousChecked = !checkbox.checked;
        const formData = new FormData(form);

        if (!checkbox.checked) {
          formData.delete('is_purchased');
        }

        checkbox.disabled = true;
        form.classList.add('opacity-50');

        try {
          const endpoint = form.getAttribute('action') || '<?= Helpers::e(BASE_URL . '/admin/requirements') ?>';
          const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          const rawResponse = await response.text();
          let result = null;

          try {
            result = JSON.parse(rawResponse);
          } catch (parseError) {
            throw new Error(rawResponse || 'El servidor devolvio una respuesta invalida.');
          }

          if (!response.ok || !result.ok) {
            throw new Error(result.message || 'No se pudo actualizar el item.');
          }

          const purchased = Number(result.item?.is_purchased || 0) === 1;
          checkbox.checked = purchased;
          statusBadge.textContent = result.item?.status_text || (purchased ? 'Comprado' : 'Pendiente');
          statusBadge.classList.remove('text-bg-success', 'text-bg-secondary');
          statusBadge.classList.add(purchased ? 'text-bg-success' : 'text-bg-secondary');
          updateWorkerSummary(form);
          updateFinancialSummary();
        } catch (error) {
          checkbox.checked = previousChecked;
          updateWorkerSummary(form);
          updateFinancialSummary();
          window.alert(error.message || 'No se pudo actualizar el item.');
        } finally {
          checkbox.disabled = false;
          form.classList.remove('opacity-50');
        }
      });
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
