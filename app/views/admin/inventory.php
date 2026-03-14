<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Inventario por area</h3>

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

    <?php foreach ($grouped as $areaName => $items): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-0"><?= Helpers::e($areaName) ?></h5>
              <div class="text-muted small"><?= count($items) ?> item(s) registrados</div>
            </div>
          </div>

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
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td><?= (int)$item['id'] ?></td>
                    <td><?= Helpers::e($item['first_name'] . ' ' . $item['last_name']) ?></td>
                    <td><?= Helpers::e($item['document_number']) ?></td>
                    <td><?= Helpers::e($item['name']) ?></td>
                    <td><?= Helpers::e(rtrim(rtrim(number_format((float)$item['quantity'], 2, '.', ''), '0'), '.')) ?></td>
                    <td><?= Helpers::e($item['unit']) ?></td>
                    <td>
                      <span class="badge text-bg-<?= (int)$item['is_active'] === 1 ? 'success' : 'secondary' ?>">
                        <?= (int)$item['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                      </span>
                    </td>
                    <td><?= Helpers::e($item['notes'] ?? '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
