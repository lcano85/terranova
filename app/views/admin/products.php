<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

function productMoney($value): string {
  return $value !== null && $value !== '' ? 'S/ ' . number_format((float)$value, 2) : '-';
}

function productNumber($value): string {
  return $value !== null && $value !== '' ? rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') : '-';
}
?>
<?php
$categoriesPagination = Pagination::paginateArray($categories, 'product_categories_page', 'product_categories_per_page');
$categoriesTableRows = $categoriesPagination['rows'];
$categoriesPaginationMeta = $categoriesPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Productos</h3>
        <div class="text-muted small">Administra categorias, productos y precios. Tambien puedes importar el catalogo desde tu Excel de inventario.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImportInventory">Importar inventario</button>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCreateCategory">+ Categoria</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateProduct">+ Producto</button>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Categorias</div>
            <div class="fs-3 fw-bold"><?= count($categories) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Productos</div>
            <div class="fs-3 fw-bold"><?= (int)($summary['total_products'] ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Con categoria</div>
            <div class="fs-3 fw-bold"><?= (int)($summary['categorized_products'] ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Stock total</div>
            <div class="fs-3 fw-bold"><?= productNumber($summary['total_stock'] ?? 0) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2" method="GET">
          <div class="col-md-4">
            <select class="form-select" name="category_id">
              <option value="">Todas las categorias</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($category['name']) ?> (<?= (int)$category['products_count'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <input class="form-control" name="q" value="<?= Helpers::e($search) ?>" placeholder="Buscar por producto, categoria o codigo">
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-outline-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body table-responsive">
        <div class="page-toolbar mb-3">
          <div>
            <h5 class="mb-0">Categorias</h5>
            <div class="text-muted small">Vista rapida de las categorias activas en el catalogo.</div>
          </div>
        </div>

        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Categoria</th>
              <th>Productos</th>
              <th style="width: 180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categoriesTableRows as $category): ?>
              <tr>
                <td><?= (int)$category['id'] ?></td>
                <td><?= Helpers::e($category['name']) ?></td>
                <td><?= (int)$category['products_count'] ?></td>
                <td class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditCategory<?= (int)$category['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar categoria? Los productos quedaran sin categoria.');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditCategory<?= (int)$category['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update_category">
                      <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar categoria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="name" value="<?= Helpers::e($category['name']) ?>" required>
                      </div>

                      <div class="modal-footer">
                        <button class="btn btn-primary">Guardar</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($categoriesTableRows)): ?>
              <tr><td colspan="4" class="text-muted">No hay categorias registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($categoriesPaginationMeta) ?>
      </div>
    </div>

    <?php if (empty($grouped)): ?>
      <div class="card shadow-sm">
        <div class="card-body text-muted">No hay productos para los filtros seleccionados.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($grouped as $groupName => $products): ?>
      <?php
      $totalProductsInGroup = count($products);
      $productGroupKey = substr(md5($groupName), 0, 8);
      $productGroupPagination = Pagination::paginateArray($products, 'products_page_' . $productGroupKey, 'products_per_page_' . $productGroupKey);
      $products = $productGroupPagination['rows'];
      $productGroupPaginationMeta = $productGroupPagination['meta'];
      ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="page-toolbar mb-3">
            <div>
              <h5 class="mb-0"><?= Helpers::e($groupName) ?></h5>
              <div class="text-muted small"><?= $totalProductsInGroup ?> producto(s)</div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Producto</th>
                  <th>Codigos</th>
                  <th>Marca / Variante</th>
                  <th>Precio</th>
                  <th>Costo</th>
                  <th>Stock</th>
                  <th>Estado</th>
                  <th style="width: 220px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                  <tr>
                    <td><?= (int)$product['id'] ?></td>
                    <td><?= Helpers::e($product['name']) ?></td>
                    <td>
                      <div class="small">Interno: <?= Helpers::e($product['internal_code'] ?: '-') ?></div>
                      <div class="small">Fabricante: <?= Helpers::e($product['manufacturer_code'] ?: '-') ?></div>
                    </td>
                    <td>
                      <div class="small">Marca: <?= Helpers::e($product['brand'] ?: '-') ?></div>
                      <div class="small">Variante: <?= Helpers::e($product['variant'] ?: '-') ?></div>
                    </td>
                    <td><?= productMoney($product['unit_price']) ?></td>
                    <td><?= productMoney($product['cost_price']) ?></td>
                    <td><?= productNumber($product['stock_quantity']) ?></td>
                    <td>
                      <span class="badge text-bg-<?= (int)$product['is_active'] === 1 ? 'success' : 'secondary' ?>">
                        <?= (int)$product['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                      </span>
                    </td>
                    <td class="d-flex gap-2 flex-wrap">
                      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPrice<?= (int)$product['id'] ?>">Precio</button>
                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditProduct<?= (int)$product['id'] ?>">Editar</button>
                      <form method="POST" onsubmit="return confirm('Eliminar producto?');">
                        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                      </form>
                    </td>
                  </tr>

                  <div class="modal fade" id="modalPrice<?= (int)$product['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <form method="POST">
                          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                          <input type="hidden" name="action" value="update_price">
                          <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

                          <div class="modal-header">
                            <h5 class="modal-title">Actualizar precio</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>

                          <div class="modal-body">
                            <div class="fw-semibold mb-2"><?= Helpers::e($product['name']) ?></div>
                            <label class="form-label">Precio de venta</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="<?= Helpers::e($product['unit_price'] ?? '') ?>">
                          </div>

                          <div class="modal-footer">
                            <button class="btn btn-primary">Guardar</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <div class="modal fade" id="modalEditProduct<?= (int)$product['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                      <div class="modal-content">
                        <form method="POST">
                          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                          <input type="hidden" name="action" value="update_product">
                          <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

                          <div class="modal-header">
                            <h5 class="modal-title">Editar producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>

                          <div class="modal-body">
                            <div class="row g-3">
                              <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input class="form-control" name="name" value="<?= Helpers::e($product['name']) ?>" required>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" name="category_id">
                                  <option value="">Sin categoria</option>
                                  <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>" <?= (int)$product['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                      <?= Helpers::e($category['name']) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Variante</label>
                                <input class="form-control" name="variant" value="<?= Helpers::e($product['variant'] ?? '') ?>">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Marca</label>
                                <input class="form-control" name="brand" value="<?= Helpers::e($product['brand'] ?? '') ?>">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Codigo interno</label>
                                <input class="form-control" name="internal_code" value="<?= Helpers::e($product['internal_code'] ?? '') ?>">
                              </div>
                              <div class="col-md-6">
                                <label class="form-label">Codigo fabricante</label>
                                <input class="form-control" name="manufacturer_code" value="<?= Helpers::e($product['manufacturer_code'] ?? '') ?>">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Precio</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="<?= Helpers::e($product['unit_price'] ?? '') ?>">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Costo</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="cost_price" value="<?= Helpers::e($product['cost_price'] ?? '') ?>">
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Stock</label>
                                <input type="number" step="0.01" class="form-control" name="stock_quantity" value="<?= Helpers::e($product['stock_quantity'] ?? '') ?>">
                              </div>
                              <div class="col-12">
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="is_active" id="productActive<?= (int)$product['id'] ?>" <?= (int)$product['is_active'] === 1 ? 'checked' : '' ?>>
                                  <label class="form-check-label" for="productActive<?= (int)$product['id'] ?>">Activo</label>
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
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= Pagination::render($productGroupPaginationMeta) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="modalCreateCategory" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create_category">

        <div class="modal-header">
          <h5 class="modal-title">Nueva categoria</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="name" required>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCreateProduct" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create_product">

        <div class="modal-header">
          <h5 class="modal-title">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input class="form-control" name="name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Categoria</label>
              <select class="form-select" name="category_id">
                <option value="">Sin categoria</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= (int)$category['id'] ?>"><?= Helpers::e($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Variante</label>
              <input class="form-control" name="variant">
            </div>
            <div class="col-md-6">
              <label class="form-label">Marca</label>
              <input class="form-control" name="brand">
            </div>
            <div class="col-md-6">
              <label class="form-label">Codigo interno</label>
              <input class="form-control" name="internal_code">
            </div>
            <div class="col-md-6">
              <label class="form-label">Codigo fabricante</label>
              <input class="form-control" name="manufacturer_code">
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio</label>
              <input type="number" step="0.01" min="0" class="form-control" name="unit_price">
            </div>
            <div class="col-md-4">
              <label class="form-label">Costo</label>
              <input type="number" step="0.01" min="0" class="form-control" name="cost_price">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock</label>
              <input type="number" step="0.01" class="form-control" name="stock_quantity">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="newProductActive" checked>
                <label class="form-check-label" for="newProductActive">Activo</label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalImportInventory" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="import_inventory">

        <div class="modal-header">
          <h5 class="modal-title">Importar catalogo desde Excel</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <label class="form-label">Archivo de inventario (.xlsx)</label>
          <input type="file" class="form-control" name="inventory_file" accept=".xlsx" required>
          <div class="form-text">El archivo debe contener columnas como PRODUCTO, CATEGORIA, STOCK, COSTO y PRECIO.</div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary">Importar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
