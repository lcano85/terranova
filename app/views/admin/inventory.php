<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

function inventoryActionLabel(string $action): string {
  if ($action === 'create') return 'Creacion';
  if ($action === 'update') return 'Actualizacion';
  if ($action === 'activate') return 'Activacion';
  if ($action === 'deactivate') return 'Desactivacion';
  return $action;
}

function inventoryHistoryDetail(array $history): string {
  $snapshot = json_decode((string)($history['after_snapshot'] ?? ''), true);
  if (!is_array($snapshot)) {
    $snapshot = json_decode((string)($history['before_snapshot'] ?? ''), true);
  }
  if (!is_array($snapshot)) {
    return '-';
  }

  $quantity = (string)(int)($snapshot['quantity'] ?? 0);
  $status = (int)($snapshot['is_active'] ?? 0) === 1 ? 'Activo' : 'Inactivo';
  return trim((string)($snapshot['name'] ?? '')) . ' | ' . $quantity . ' ' . trim((string)($snapshot['unit'] ?? '')) . ' | ' . $status;
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <h3 class="mb-0">Inventario por area</h3>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateInventory">
        + Registrar inventario
      </button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2" method="GET">
          <div class="col-md-4">
            <select class="form-select" name="area_id">
              <option value="">Todas las areas</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= (int)$area['id'] ?>" <?= $areaId === (int)$area['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($area['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <select class="form-select" name="status">
              <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos los estados</option>
              <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Activos</option>
              <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactivos</option>
            </select>
          </div>
          <div class="col-md-4 d-grid">
            <button class="btn btn-outline-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (empty($grouped)): ?>
      <div class="card shadow-sm">
        <div class="card-body text-muted">No hay inventario registrado para los filtros seleccionados.</div>
      </div>
    <?php endif; ?>

    <div class="accordion" id="inventoryAccordion">
    <?php $inventoryAccordionIndex = 0; ?>
    <?php foreach ($grouped as $areaName => $items): ?>
      <?php
      $inventoryAccordionIndex++;
      $totalItemsInArea = count($items);
      $inventoryGroupKey = substr(md5($areaName), 0, 8);
      $inventoryCollapseId = 'inventoryAreaCollapse' . $inventoryGroupKey;
      $inventoryGroupPagination = Pagination::paginateArray($items, 'inventory_page_' . $inventoryGroupKey, 'inventory_per_page_' . $inventoryGroupKey);
      $items = $inventoryGroupPagination['rows'];
      $inventoryGroupPaginationMeta = $inventoryGroupPagination['meta'];
      ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="page-toolbar mb-3">
            <div>
              <h5 class="mb-0"><?= Helpers::e($areaName) ?></h5>
              <div class="text-muted small"><?= $totalItemsInArea ?> item(s) registrados</div>
            </div>
            <button
              class="btn btn-sm btn-outline-secondary"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#<?= Helpers::e($inventoryCollapseId) ?>"
              aria-expanded="<?= $inventoryAccordionIndex === 1 ? 'true' : 'false' ?>"
              aria-controls="<?= Helpers::e($inventoryCollapseId) ?>"
            >
              Ver bloque
            </button>
          </div>

          <div
            id="<?= Helpers::e($inventoryCollapseId) ?>"
            class="collapse <?= $inventoryAccordionIndex === 1 ? 'show' : '' ?>"
            data-bs-parent="#inventoryAccordion"
          >
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Trabajador</th>
                  <th>Documento</th>
                  <th>Item</th>
                  <th>Cantidad</th>
                  <th>Unidad</th>
                  <th>Estado</th>
                  <th>Notas</th>
                  <th style="width: 110px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <?php $unseenCount = (int)($unseenInventoryUpdates[(int)$item['id']] ?? 0); ?>
                  <tr class="<?= $unseenCount > 0 ? 'table-warning' : '' ?>" data-inventory-row="<?= (int)$item['id'] ?>">
                    <td><?= (int)$item['id'] ?></td>
                    <td><?= Helpers::e($item['first_name'] . ' ' . $item['last_name']) ?></td>
                    <td><?= Helpers::e($item['document_number']) ?></td>
                    <td><?= Helpers::e($item['name']) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td><?= Helpers::e($item['unit']) ?></td>
                    <td>
                      <span class="badge text-bg-<?= (int)$item['is_active'] === 1 ? 'success' : 'secondary' ?>">
                        <?= (int)$item['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                      </span>
                    </td>
                    <td><?= Helpers::e($item['notes'] ?? '-') ?></td>
                    <td>
                      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalEditInventory<?= (int)$item['id'] ?>">
                        Editar
                      </button>
                      <button
                        class="btn btn-sm <?= $unseenCount > 0 ? 'btn-warning' : 'btn-outline-primary' ?> mt-1 position-relative"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modalHistoryInventory<?= (int)$item['id'] ?>"
                        data-history-item-id="<?= (int)$item['id'] ?>"
                      >
                        <?= $unseenCount > 0 ? 'Actualizado' : 'Historial' ?>
                        <?php if ($unseenCount > 0): ?>
                          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" data-history-badge="<?= (int)$item['id'] ?>">
                            <?= $unseenCount ?>
                            <span class="visually-hidden">actualizaciones pendientes</span>
                          </span>
                        <?php endif; ?>
                      </button>
                      <button class="btn btn-sm btn-outline-danger mt-1" type="button" data-bs-toggle="modal" data-bs-target="#modalDeleteInventory<?= (int)$item['id'] ?>">
                        Eliminar
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php foreach ($items as $item): ?>
            <div class="modal fade" id="modalEditInventory<?= (int)$item['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                    <div class="modal-header">
                      <h5 class="modal-title">Editar item de inventario</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="form-label">Area</label>
                          <select class="form-select" name="area_id" required>
                            <option value="">Selecciona area</option>
                            <?php foreach ($areas as $area): ?>
                              <option value="<?= (int)$area['id'] ?>" <?= (int)$item['area_id'] === (int)$area['id'] ? 'selected' : '' ?>>
                                <?= Helpers::e($area['name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Trabajador</label>
                          <select class="form-select" name="user_id" required>
                            <option value="">Selecciona trabajador</option>
                            <?php foreach ($workers as $worker): ?>
                              <option value="<?= (int)$worker['id'] ?>" <?= (int)$item['user_id'] === (int)$worker['id'] ? 'selected' : '' ?>>
                                <?= Helpers::e($worker['document_number'] . ' - ' . $worker['first_name'] . ' ' . $worker['last_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-12">
                          <label class="form-label">Item</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($item['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Cantidad</label>
                          <input type="number" step="1" min="0" class="form-control" name="quantity" value="<?= (int)$item['quantity'] ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Unidad</label>
                          <input class="form-control" name="unit" value="<?= Helpers::e($item['unit']) ?>" placeholder="kg, unid, botellas..." required>
                        </div>
                        <div class="col-md-12">
                          <label class="form-label">Notas</label>
                          <textarea class="form-control" name="notes" rows="3"><?= Helpers::e($item['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="inventoryActive<?= (int)$item['id'] ?>" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="inventoryActive<?= (int)$item['id'] ?>">Activo</label>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button class="btn btn-primary">Guardar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div class="modal fade" id="modalHistoryInventory<?= (int)$item['id'] ?>" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <div>
                      <h5 class="modal-title">Historial de inventario</h5>
                      <div class="text-muted small"><?= Helpers::e($item['name']) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>

                  <div class="modal-body">
                    <?php $historyRows = $inventoryHistory[(int)$item['id']] ?? []; ?>
                    <?php if (empty($historyRows)): ?>
                      <div class="text-muted">Aun no hay historial registrado para este item.</div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm align-middle">
                          <thead>
                            <tr>
                              <th>Fecha</th>
                              <th>Accion</th>
                              <th>Usuario</th>
                              <th>Rol</th>
                              <th>Detalle</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php $highlightedUnseenUpdate = false; ?>
                            <?php foreach ($historyRows as $history): ?>
                              <?php
                              $isUnseenWorkerUpdate = !$highlightedUnseenUpdate
                                && (string)$history['action'] === 'update'
                                && empty($history['admin_seen_at']);
                              if ($isUnseenWorkerUpdate) {
                                $highlightedUnseenUpdate = true;
                              }
                              ?>
                              <tr class="<?= $isUnseenWorkerUpdate ? 'table-warning' : '' ?>" data-history-row="<?= $isUnseenWorkerUpdate ? (int)$item['id'] : '' ?>">
                                <td><?= Helpers::e(Helpers::formatDateTime($history['created_at'])) ?></td>
                                <td><?= Helpers::e(inventoryActionLabel((string)$history['action'])) ?></td>
                                <td><?= Helpers::e(trim(($history['first_name'] ?? '') . ' ' . ($history['last_name'] ?? '')) ?: '-') ?></td>
                                <td><?= Helpers::e(($history['actor_role'] ?? '') === 'admin' ? 'Administrador' : 'Trabajador') ?></td>
                                <td><?= Helpers::e(inventoryHistoryDetail($history)) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="modalDeleteInventory<?= (int)$item['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                    <div class="modal-header">
                      <h5 class="modal-title">Eliminar item de inventario</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <p class="mb-1">Estas seguro de eliminar este item?</p>
                      <div class="fw-semibold"><?= Helpers::e($item['name']) ?></div>
                      <div class="text-muted small">Esta accion eliminara tambien su historial.</div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button class="btn btn-danger">Eliminar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <?= Pagination::render($inventoryGroupPaginationMeta) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <div class="modal fade" id="modalCreateInventory" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Registrar inventario</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">Area</label>
                  <select class="form-select" name="area_id" required>
                    <option value="">Selecciona area</option>
                    <?php foreach ($areas as $area): ?>
                      <option value="<?= (int)$area['id'] ?>"><?= Helpers::e($area['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Trabajador</label>
                  <select class="form-select" name="user_id" required>
                    <option value="">Selecciona trabajador</option>
                    <?php foreach ($workers as $worker): ?>
                      <option value="<?= (int)$worker['id'] ?>">
                        <?= Helpers::e($worker['document_number'] . ' - ' . $worker['first_name'] . ' ' . $worker['last_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-12">
                  <label class="form-label">Item</label>
                  <input class="form-control" name="name" placeholder="Ej: Servilleteros" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Cantidad</label>
                  <input type="number" step="1" min="0" class="form-control" name="quantity" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Unidad</label>
                  <input class="form-control" name="unit" placeholder="kg, unid, botellas..." required>
                </div>
                <div class="col-md-12">
                  <label class="form-label">Notas</label>
                  <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-primary">Registrar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(() => {
  const csrfToken = <?= json_encode(Csrf::token()) ?>;

  document.querySelectorAll('[data-history-item-id]').forEach((button) => {
    const modalSelector = button.getAttribute('data-bs-target');
    const itemId = button.getAttribute('data-history-item-id');
    const modal = modalSelector ? document.querySelector(modalSelector) : null;
    if (!modal || !itemId) return;

    modal.addEventListener('shown.bs.modal', async () => {
      const badge = document.querySelector(`[data-history-badge="${itemId}"]`);
      if (!badge) return;

      const formData = new FormData();
      formData.append('_csrf', csrfToken);
      formData.append('item_id', itemId);

      try {
        const response = await fetch('/admin/inventory/history-seen', {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: formData
        });
        if (response.ok) {
          badge.remove();
          button.classList.remove('btn-warning');
          button.classList.add('btn-outline-primary');
          button.childNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
              node.textContent = 'Historial';
            }
          });
          document.querySelectorAll(`[data-inventory-row="${itemId}"]`).forEach((row) => {
            row.classList.remove('table-warning');
          });
          modal.querySelectorAll(`[data-history-row="${itemId}"]`).forEach((row) => {
            row.classList.remove('table-warning');
            row.removeAttribute('data-history-row');
          });
        }
      } catch (error) {
        // Si falla la marca de revision, el indicador se conserva para el proximo ingreso.
      }
    }, { once: true });
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
