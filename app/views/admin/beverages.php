<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireLogin();
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';
require_once __DIR__ . '/../../models/BeverageControl.php';

$limitedBeverageAccess = !empty($limitedBeverageAccess);
$entriesPagination = Pagination::paginateArray($entries, 'beverages_page', 'beverages_per_page');
$entries = $entriesPagination['rows'];
$entriesPaginationMeta = $entriesPagination['meta'];

$productsPagination = Pagination::paginateArray($allProducts ?? [], 'beverage_products_page', 'beverage_products_per_page');
$pagedProducts = $productsPagination['rows'];
$productsPaginationMeta = $productsPagination['meta'];

$productsJson = json_encode(array_map(static fn($product) => [
  'id' => (int)$product['id'],
  'units_per_package' => (int)$product['units_per_package'],
], $products), JSON_UNESCAPED_UNICODE);

function beverageExpiryBadge(string $expiryDate, int $warningDays): string {
  $status = BeverageControl::expiryStatus($expiryDate, $warningDays);
  if ($status === 'expired') {
    return '<span class="badge text-bg-danger">Vencido</span>';
  }
  if ($status === 'warning') {
    return '<span class="badge text-bg-warning">Por vencer</span>';
  }
  return '<span class="badge text-bg-success">Vigente</span>';
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . ($limitedBeverageAccess ? '/../layouts/sidebar_worker.php' : '/../layouts/sidebar_admin.php'); ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Control de bebidas</h3>
        <div class="text-muted small">Registra cajas o unidades, ventas y vencimientos.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if (!$limitedBeverageAccess): ?>
          <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createProductModal">
            + Nueva bebida
          </button>
        <?php endif; ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBeverageModal">
          + Registrar stock
        </button>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <?php if (!$limitedBeverageAccess): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white">
        <div class="fw-semibold">Bebidas administrables</div>
        <div class="text-muted small">Solo las bebidas activas aparecen al registrar stock.</div>
      </div>
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Bebida</th>
              <th>Cantidad</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pagedProducts as $product): ?>
              <tr>
                <td><?= (int)$product['id'] ?></td>
                <td class="fw-semibold"><?= Helpers::e($product['name']) ?></td>
                <td><?= (int)$product['units_per_package'] ?> unidad(es)</td>
                <td>
                  <span class="badge text-bg-<?= (int)$product['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$product['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProductModal<?= (int)$product['id'] ?>">Editar</button>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?= (int)$product['id'] ?>">Eliminar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($pagedProducts)): ?>
              <tr><td colspan="5" class="text-muted">No hay bebidas creadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($productsPaginationMeta) ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Stock registrado</div>
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Bebida</th>
              <th>Ingreso</th>
              <th>Vencimiento</th>
              <th>Cantidad</th>
              <th>Total unidades</th>
              <th>Vendidas</th>
              <th>Stock</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr class="<?= BeverageControl::expiryStatus($entry['expiry_date'], (int)($entry['expiry_warning_days'] ?? 3)) === 'warning' ? 'table-warning' : '' ?>">
                <td><?= (int)$entry['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= Helpers::e($entry['product_name']) ?></div>
                  <div class="text-muted small">Referencia catalogo: <?= (int)$entry['units_per_package'] ?> unidad(es)</div>
                </td>
                <td><?= Helpers::e(Helpers::formatDate($entry['entry_date'])) ?></td>
                <td>
                  <?= Helpers::e(Helpers::formatDate($entry['expiry_date'])) ?><br>
                  <?= beverageExpiryBadge((string)$entry['expiry_date'], (int)($entry['expiry_warning_days'] ?? 3)) ?>
                  <div class="text-muted small">Aviso: <?= (int)($entry['expiry_warning_days'] ?? 3) ?> dia(s)</div>
                </td>
                <td><?= (int)$entry['package_quantity'] ?></td>
                <td><?= (int)$entry['total_units'] ?></td>
                <td><?= (int)$entry['sold_units'] ?></td>
                <td><strong><?= (int)$entry['remaining_units'] ?></strong></td>
                <td>
                  <span class="badge text-bg-<?= (int)$entry['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$entry['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php $canSell = (int)$entry['is_active'] === 1 && (int)$entry['remaining_units'] > 0; ?>
                    <button
                      class="btn btn-sm btn-outline-success"
                      data-bs-toggle="modal"
                      data-bs-target="#saleBeverageModal<?= (int)$entry['id'] ?>"
                      <?= $canSell ? '' : 'disabled title="Sin stock disponible"' ?>
                    >Venta</button>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#historyBeverageModal<?= (int)$entry['id'] ?>">Historial</button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBeverageModal<?= (int)$entry['id'] ?>">Editar</button>
                    <?php if (!$limitedBeverageAccess): ?>
                      <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteBeverageModal<?= (int)$entry['id'] ?>">Eliminar</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
              <tr><td colspan="10" class="text-muted">No hay bebidas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($entriesPaginationMeta) ?>
      </div>
    </div>
  </div>
</div>

<?php if (!$limitedBeverageAccess): ?>
<div class="modal fade" id="createProductModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create_product">
        <input type="hidden" name="is_active" value="1">
        <div class="modal-header">
          <h5 class="modal-title">Nueva bebida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" name="name" placeholder="Ej: Pilsen personal" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Cantidad</label>
            <input type="number" step="1" min="1" class="form-control" name="units_per_package" placeholder="Ej: 24" required>
            <div class="form-text">Cantidad de unidades por caja, paquete o producto.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Crear bebida</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach (($allProducts ?? []) as $product): ?>
  <div class="modal fade" id="editProductModal<?= (int)$product['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="update_product">
          <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Editar bebida</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="name" value="<?= Helpers::e($product['name']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Cantidad</label>
              <input type="number" step="1" min="1" class="form-control" name="units_per_package" value="<?= (int)$product['units_per_package'] ?>" required>
              <div class="form-text">Cantidad de unidades por caja, paquete o producto.</div>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="productActive<?= (int)$product['id'] ?>" <?= (int)$product['is_active'] === 1 ? 'checked' : '' ?>>
              <label class="form-check-label" for="productActive<?= (int)$product['id'] ?>">Activo</label>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deleteProductModal<?= (int)$product['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="delete_product">
          <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Eliminar bebida</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Estas seguro de eliminar esta bebida del catalogo?</p>
            <div class="fw-semibold"><?= Helpers::e($product['name']) ?></div>
            <div class="text-muted small">Si ya tiene stock registrado, el sistema no la eliminara; debes desactivarla para conservar el historial.</div>
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
<?php endif; ?>

<div class="modal fade" id="createBeverageModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="is_active" value="1">

        <div class="modal-header">
          <h5 class="modal-title">Registrar bebida</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Bebida</label>
              <select class="form-select" name="beverage_product_id" data-beverage-product required>
                <option value="">Selecciona bebida</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= (int)$product['id'] ?>"><?= Helpers::e($product['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text" data-units-help>Selecciona una bebida para ver unidades por caja/paquete.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cantidad</label>
              <input type="number" step="1" min="1" class="form-control" name="package_quantity" required>
              <div class="form-text">Ingresa unidades directas. No se multiplica por la referencia del catalogo.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha de ingreso</label>
              <input type="date" class="form-control" name="entry_date" value="<?= Helpers::e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha de vencimiento</label>
              <input type="date" class="form-control" name="expiry_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Dias de aviso por vencimiento</label>
              <input type="number" step="1" min="0" class="form-control" name="expiry_warning_days" value="3" required>
              <div class="form-text">Ej: 3 avisara cuando falten 3 dias o menos.</div>
            </div>
            <div class="col-12">
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

<?php foreach ($entries as $entry): ?>
  <div class="modal fade" id="editBeverageModal<?= (int)$entry['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Editar bebida</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Bebida</label>
                <select class="form-select" name="beverage_product_id" data-beverage-product required>
                  <?php foreach (($allProducts ?? []) as $product): ?>
                    <?php if ((int)$product['is_active'] !== 1 && (int)$entry['beverage_product_id'] !== (int)$product['id']) continue; ?>
                    <option value="<?= (int)$product['id'] ?>" <?= (int)$entry['beverage_product_id'] === (int)$product['id'] ? 'selected' : '' ?>>
                      <?= Helpers::e($product['name']) ?><?= (int)$product['is_active'] !== 1 ? ' (inactiva)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text" data-units-help>Referencia catalogo: <?= (int)$entry['units_per_package'] ?> unidad(es). La cantidad se toma como unidades directas.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Cantidad</label>
                <input type="number" step="1" min="1" class="form-control" name="package_quantity" value="<?= (int)$entry['package_quantity'] ?>" required>
                <div class="form-text">Ingresa unidades directas. No se multiplica por la referencia del catalogo.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha de ingreso</label>
                <input type="date" class="form-control" name="entry_date" value="<?= Helpers::e($entry['entry_date']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha de vencimiento</label>
                <input type="date" class="form-control" name="expiry_date" value="<?= Helpers::e($entry['expiry_date']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Dias de aviso por vencimiento</label>
                <input type="number" step="1" min="0" class="form-control" name="expiry_warning_days" value="<?= (int)($entry['expiry_warning_days'] ?? 3) ?>" required>
                <div class="form-text">Ej: 3 avisara cuando falten 3 dias o menos.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea class="form-control" name="notes" rows="3"><?= Helpers::e($entry['notes'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_active" id="beverageActive<?= (int)$entry['id'] ?>" <?= (int)$entry['is_active'] === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="beverageActive<?= (int)$entry['id'] ?>">Activo</label>
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

  <?php $saleMax = max(0, (int)$entry['remaining_units']); ?>
  <div class="modal fade" id="saleBeverageModal<?= (int)$entry['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="sale">
          <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Registrar venta</h5>
              <div class="text-muted small"><?= Helpers::e($entry['product_name']) ?> - stock disponible: <?= (int)$entry['remaining_units'] ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Fecha de venta</label>
                <input type="date" class="form-control" name="sale_date" value="<?= Helpers::e(date('Y-m-d')) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Unidades vendidas</label>
                <input type="number" step="1" min="1" max="<?= $saleMax ?>" class="form-control" name="units_sold" <?= $saleMax <= 0 ? 'disabled' : 'required' ?>>
                <div class="form-text">Se bloquea cuando el stock llega a 0.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea class="form-control" name="sale_notes" rows="2"></textarea>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button class="btn btn-success" <?= $saleMax <= 0 ? 'disabled' : '' ?>>Registrar venta</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="historyBeverageModal<?= (int)$entry['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Historial de ventas</h5>
            <div class="text-muted small"><?= Helpers::e($entry['product_name']) ?> - stock actual: <?= (int)$entry['remaining_units'] ?></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php $sales = $salesByEntry[(int)$entry['id']] ?? []; ?>
          <?php if (empty($sales)): ?>
            <div class="text-muted">Aun no hay ventas para este registro.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>Fecha</th><th>Unidades vendidas</th><th>Notas</th></tr></thead>
                <tbody>
                  <?php foreach ($sales as $sale): ?>
                    <tr>
                      <td><?= Helpers::e(Helpers::formatDate($sale['sale_date'])) ?></td>
                      <td><?= (int)$sale['units_sold'] ?></td>
                      <td><?= Helpers::e($sale['notes'] ?? '-') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$limitedBeverageAccess): ?>
  <div class="modal fade" id="deleteBeverageModal<?= (int)$entry['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Eliminar bebida</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p>Estas seguro de eliminar este registro?</p>
            <div class="fw-semibold"><?= Helpers::e($entry['product_name']) ?></div>
            <div class="text-muted small">Tambien se eliminaran sus ventas registradas.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger">Eliminar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php endforeach; ?>

<script>
(() => {
  const products = <?= $productsJson ?: '[]' ?>;
  const unitsByProduct = new Map(products.map((product) => [String(product.id), product.units_per_package]));

  document.querySelectorAll('[data-beverage-product]').forEach((select) => {
    const help = select.closest('form')?.querySelector('[data-units-help]');
    const updateHelp = () => {
      const units = unitsByProduct.get(select.value);
      if (help) {
        help.textContent = units ? `Referencia catalogo: ${units} unidad(es). La cantidad se toma como unidades directas.` : 'Selecciona una bebida para ver la referencia del catalogo.';
      }
    };
    select.addEventListener('change', updateHelp);
    updateHelp();
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
