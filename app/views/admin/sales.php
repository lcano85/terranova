<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

function salesMoney($value): string {
  return $value !== null && $value !== '' ? 'S/ ' . number_format((float)$value, 2) : 'S/ 0.00';
}

function salesNumber($value): string {
  return $value !== null && $value !== '' ? rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') : '0';
}
?>
<?php
$topProductsPagination = Pagination::paginateArray($topProducts, 'sales_top_page', 'sales_top_per_page');
$topProducts = $topProductsPagination['rows'];
$topProductsPaginationMeta = $topProductsPagination['meta'];

$categoryBreakdownPagination = Pagination::paginateArray($categoryBreakdown, 'sales_categories_page', 'sales_categories_per_page');
$categoryBreakdown = $categoryBreakdownPagination['rows'];
$categoryBreakdownPaginationMeta = $categoryBreakdownPagination['meta'];

$topByCategoryPagination = Pagination::paginateArray($topByCategory, 'sales_top_category_page', 'sales_top_category_per_page');
$topByCategory = $topByCategoryPagination['rows'];
$topByCategoryPaginationMeta = $topByCategoryPagination['meta'];

$auditIssuesPagination = Pagination::paginateArray($auditIssues, 'sales_audit_page', 'sales_audit_per_page');
$auditIssues = $auditIssuesPagination['rows'];
$auditIssuesPaginationMeta = $auditIssuesPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Ventas mensuales</h3>
        <div class="text-muted small">Importa el Excel de ventas del mes y revisa productos mas vendidos en general o filtrados por categoria.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportSales">Importar ventas</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2" method="GET">
          <div class="col-md-4">
            <label class="form-label">Mes</label>
            <select class="form-select" name="month">
              <option value="">Selecciona un mes</option>
              <?php foreach ($months as $month): ?>
                <?php $monthKey = date('Y-m', strtotime($month['period_month'])); ?>
                <option value="<?= Helpers::e($monthKey) ?>" <?= $selectedMonth === $monthKey ? 'selected' : '' ?>>
                  <?= Helpers::e(date('m/Y', strtotime($month['period_month']))) ?> (<?= (int)$month['rows_count'] ?> productos)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Categoria</label>
            <select class="form-select" name="category_id">
              <option value="">Todas las categorias</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($category['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-primary">Aplicar filtros</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!$periodMonth): ?>
      <div class="card shadow-sm">
        <div class="card-body text-muted">Aun no hay ventas importadas. Sube un archivo mensual para empezar a construir el dashboard.</div>
      </div>
    <?php else: ?>
      <?php if (!empty($latestAudit)): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <div class="page-toolbar mb-3">
              <div>
                <h5 class="mb-0">Auditoria de importacion</h5>
                <div class="text-muted small">Ultima importacion del mes seleccionado.</div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                  <div class="text-muted small">Estado</div>
                  <div class="fw-semibold text-uppercase"><?= Helpers::e($latestAudit['status']) ?></div>
                  <div class="small text-muted"><?= Helpers::e($latestAudit['source_file'] ?: '-') ?></div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                  <div class="text-muted small">Filas importadas</div>
                  <div class="fw-semibold"><?= (int)$latestAudit['rows_imported'] ?> / <?= (int)$latestAudit['rows_total'] ?></div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                  <div class="text-muted small">Monto origen</div>
                  <div class="fw-semibold"><?= salesMoney($latestAudit['raw_total_amount']) ?></div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                  <div class="text-muted small">Monto normalizado</div>
                  <div class="fw-semibold"><?= salesMoney($latestAudit['normalized_total_amount']) ?></div>
                  <div class="small text-muted">Incidencias: <?= (int)$latestAudit['issues_count'] ?></div>
                </div>
              </div>
            </div>

            <?php if (!empty($latestAudit['error_message'])): ?>
              <div class="alert alert-danger mt-3 mb-0"><?= Helpers::e($latestAudit['error_message']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Mes analizado</div>
              <div class="fs-4 fw-bold"><?= Helpers::e(date('m/Y', strtotime($periodMonth))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Cantidad vendida</div>
              <div class="fs-4 fw-bold"><?= salesNumber($overview['total_units'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Monto vendido</div>
              <div class="fs-4 fw-bold"><?= salesMoney($overview['total_amount'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Productos en el corte</div>
              <div class="fs-4 fw-bold"><?= (int)($overview['products_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-7">
          <div class="card shadow-sm h-100">
            <div class="card-body table-responsive">
              <div class="page-toolbar mb-3">
                <div>
                  <h5 class="mb-0">Productos mas vendidos sin filtro de categoria</h5>
                  <div class="text-muted small">Ranking general del mes por cantidad y monto vendido.</div>
                </div>
              </div>

              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Categoria</th>
                    <th>Cantidad</th>
                    <th>Monto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($topProducts as $index => $row): ?>
                    <tr>
                      <td><?= $index + 1 ?></td>
                      <td><?= Helpers::e($row['name']) ?></td>
                      <td><?= Helpers::e($row['category_name'] ?: 'Sin categoria') ?></td>
                      <td><?= salesNumber($row['units_sold']) ?></td>
                      <td><?= salesMoney($row['total_amount']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($topProducts)): ?>
                    <tr><td colspan="5" class="text-muted">No hay datos para este mes.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
              <?= Pagination::render($topProductsPaginationMeta) ?>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card shadow-sm h-100">
            <div class="card-body table-responsive">
              <div class="page-toolbar mb-3">
                <div>
                  <h5 class="mb-0">Ventas por categoria</h5>
                  <div class="text-muted small">Distribucion del mes para decidir que categoria analizar.</div>
                </div>
              </div>

              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Categoria</th>
                    <th>Cantidad</th>
                    <th>Monto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($categoryBreakdown as $row): ?>
                    <tr>
                      <td><?= Helpers::e($row['category_name']) ?></td>
                      <td><?= salesNumber($row['units_sold']) ?></td>
                      <td><?= salesMoney($row['total_amount']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($categoryBreakdown)): ?>
                    <tr><td colspan="3" class="text-muted">No hay categorias con ventas registradas.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
              <?= Pagination::render($categoryBreakdownPaginationMeta) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body table-responsive">
          <div class="page-toolbar mb-3">
            <div>
              <h5 class="mb-0">Productos mas vendidos por categoria</h5>
              <div class="text-muted small">
                <?= $categoryId > 0 ? 'Mostrando solo la categoria seleccionada.' : 'Selecciona una categoria en el filtro para ver un ranking puntual.' ?>
              </div>
            </div>
          </div>

          <table class="table align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Categoria</th>
                <th>Cantidad</th>
                <th>Monto</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topByCategory as $index => $row): ?>
                <tr>
                  <td><?= $index + 1 ?></td>
                  <td><?= Helpers::e($row['name']) ?></td>
                  <td><?= Helpers::e($row['category_name'] ?: 'Sin categoria') ?></td>
                  <td><?= salesNumber($row['units_sold']) ?></td>
                  <td><?= salesMoney($row['total_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($topByCategory)): ?>
                <tr><td colspan="5" class="text-muted">No hay datos para el filtro seleccionado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <?= Pagination::render($topByCategoryPaginationMeta) ?>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body table-responsive">
          <div class="page-toolbar mb-3">
            <div>
              <h5 class="mb-0">Log de auditoria</h5>
              <div class="text-muted small">Filas detectadas con diferencias entre VENTA TOTAL y el calculo UNIDADES x PRECIO UNITARIO.</div>
            </div>
          </div>

          <table class="table align-middle">
            <thead>
              <tr>
                <th>Fila</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Monto origen</th>
                <th>Monto calculado</th>
                <th>Monto usado</th>
                <th>Detalle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auditIssues as $issue): ?>
                <tr>
                  <td><?= (int)$issue['source_row_number'] ?></td>
                  <td><?= Helpers::e($issue['product_name'] ?: '-') ?></td>
                  <td><?= Helpers::e($issue['issue_type']) ?></td>
                  <td><?= salesMoney($issue['raw_total_amount']) ?></td>
                  <td><?= salesMoney($issue['derived_total_amount']) ?></td>
                  <td><?= salesMoney($issue['chosen_total_amount']) ?></td>
                  <td><?= Helpers::e($issue['details'] ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($auditIssues)): ?>
                <tr><td colspan="7" class="text-muted">No se detectaron incidencias en la ultima importacion de este mes.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <?= Pagination::render($auditIssuesPaginationMeta) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="modalImportSales" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="import_sales">

        <div class="modal-header">
          <h5 class="modal-title">Importar ventas mensuales</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Archivo de ventas (.xlsx)</label>
            <input type="file" class="form-control" name="sales_file" accept=".xlsx" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Mes del corte</label>
            <input type="month" class="form-control" name="sales_month">
            <div class="form-text">Si lo dejas vacio, el sistema intentara detectarlo desde el nombre del archivo.</div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary">Importar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
