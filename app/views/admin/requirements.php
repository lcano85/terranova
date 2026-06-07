<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
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
        <button class="btn w-100 p-0 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#mailLogCollapse" aria-expanded="true" aria-controls="mailLogCollapse">
          <div class="page-toolbar">
            <div>
              <h5 class="mb-0">Log de correos</h5>
              <div class="text-muted small">Ultimos intentos de notificacion por correo al administrador.</div>
            </div>
            <span class="btn btn-sm btn-outline-secondary">Mostrar / ocultar</span>
          </div>
        </button>
      </div>
      <div class="collapse show" id="mailLogCollapse">
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
                <tr><td colspan="5" class="text-muted">Aun no hay correos registrados.</td></tr>
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
                <div class="col-md-6">
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

                <div class="col-md-6">
                  <label class="form-label">Area de compra</label>
                  <select class="form-select" name="purchase_area_id" required>
                    <option value="">Selecciona area</option>
                    <?php foreach ($purchaseAreas as $area): ?>
                      <option value="<?= (int)$area['id'] ?>"><?= Helpers::e($area['name']) ?></option>
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

              <div id="adminRequirementItems" class="d-flex flex-column gap-2">
                <input class="form-control" name="items[]" placeholder="Ej: 1 caja de guantes" required>
                <input class="form-control" name="items[]" placeholder="Ej: botellas poet">
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
        $workerIsComplete = $workerTotalItems > 0 && $workerPendingItems === 0;
        $workerIsAdmin = ($worker['user_role'] ?? '') === 'admin';
        $workerIsOpen = $workerIsAdmin || $workerIsComplete;
        $workerCollapseId = 'workerRequirements' . (int)$workerIndex;
      ?>
      <div class="card shadow-sm mb-3" data-requirement-worker-card <?= $workerIsAdmin ? 'data-admin-requirements-card' : '' ?>>
        <div class="card-header bg-white">
          <button class="btn w-100 p-0 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?= Helpers::e($workerCollapseId) ?>" aria-expanded="<?= $workerIsOpen ? 'true' : 'false' ?>" aria-controls="<?= Helpers::e($workerCollapseId) ?>">
            <div class="page-toolbar">
              <div>
                <h5 class="mb-1"><?= $workerIsAdmin ? 'Administrador' : 'Trabajador' ?>: <?= Helpers::e($worker['worker_name']) ?></h5>
                <div class="text-muted small">
                  <span data-worker-purchased-count><?= (int)$workerPurchasedItems ?></span> comprado(s) /
                  <span data-worker-total-count><?= (int)$workerTotalItems ?></span> producto(s)
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

        <div class="collapse <?= $workerIsOpen ? 'show' : '' ?>" id="<?= Helpers::e($workerCollapseId) ?>" <?= $workerIsAdmin ? 'data-admin-requirements-collapse' : '' ?>>
          <div class="card-body">

            <?php foreach ($worker['areas'] as $area): ?>
              <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <div class="fw-semibold text-capitalize">Area de compra: <?= Helpers::e($area['purchase_area_name']) ?></div>
                    <span class="badge text-bg-<?= ($area['status'] ?? '') === 'draft' ? 'warning' : 'success' ?>">
                      <?= ($area['status'] ?? '') === 'draft' ? 'Borrador' : 'Enviado' ?>
                    </span>
                  </div>
                  <div class="text-muted small">Fecha: <?= Helpers::e(date('d/m/Y', strtotime($area['required_date']))) ?></div>
                </div>

                <div class="d-flex flex-column gap-2">
                  <?php foreach ($area['items'] as $item): ?>
                    <div class="border rounded px-3 py-2">
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
                          </label>
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
    const addAdminItemButton = document.getElementById('addAdminRequirementItem');
    const adminItemsContainer = document.getElementById('adminRequirementItems');
    const forms = document.querySelectorAll('.js-requirement-toggle-form');

    if (addAdminItemButton && adminItemsContainer) {
      addAdminItemButton.addEventListener('click', function() {
        const input = document.createElement('input');
        input.className = 'form-control';
        input.name = 'items[]';
        input.placeholder = 'Ej: 1 paquete de servilletas';
        adminItemsContainer.appendChild(input);
      });
    }

    document.querySelectorAll('[data-admin-requirements-collapse]').forEach(function(section) {
      section.classList.add('show');

      const button = document.querySelector('[data-bs-target="#' + section.id + '"]');
      if (button) {
        button.setAttribute('aria-expanded', 'true');
      }

      if (window.bootstrap?.Collapse) {
        window.bootstrap.Collapse.getOrCreateInstance(section, { toggle: false }).show();
      }
    });

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
        } catch (error) {
          checkbox.checked = previousChecked;
          updateWorkerSummary(form);
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
