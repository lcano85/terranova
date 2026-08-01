<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Pagination.php';

$number = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
$pagination = Pagination::paginateArray($products, 'purchased_page', 'purchased_per_page', [10, 20, 50, 100]);
$visibleProducts = $pagination['rows'];
$priorityCounts = ['high'=>0,'medium'=>0,'normal'=>0,'observation'=>0];
foreach ($products as $product) $priorityCounts[$product['priority_key']]++;
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Productos comprados</h3>
        <div class="text-muted small">Cantidades, frecuencia y prioridad de reposición. La información económica es exclusiva de administración.</div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-2"><label class="form-label">Fecha desde</label><input class="form-control" type="date" name="date_from" value="<?= Helpers::e($dateFrom) ?>"></div>
          <div class="col-md-2"><label class="form-label">Fecha hasta</label><input class="form-control" type="date" name="date_to" value="<?= Helpers::e($dateTo) ?>"></div>
          <div class="col-md-2">
            <label class="form-label">Área de compra</label>
            <select class="form-select" name="purchase_area_id">
              <option value="">Todas</option>
              <?php foreach ($areas as $area): ?><option value="<?= (int)$area['id'] ?>" <?= $purchaseAreaId === (int)$area['id'] ? 'selected' : '' ?>><?= Helpers::e($area['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label">Producto</label><input class="form-control" name="product" value="<?= Helpers::e($productSearch) ?>" placeholder="Buscar producto"></div>
          <div class="col-md-3">
            <label class="form-label">Prioridad</label>
            <select class="form-select" name="priority">
              <option value="">Todas las prioridades</option>
              <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>Alta</option>
              <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Media</option>
              <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
              <option value="observation" <?= $priorityFilter === 'observation' ? 'selected' : '' ?>>En observación</option>
            </select>
          </div>
          <div class="col-12 d-flex gap-2 mt-3"><button class="btn btn-primary">Aplicar filtros</button><a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/worker/purchase-frequency') ?>">Limpiar</a></div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6 col-lg"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Productos</div><div class="fs-4 fw-bold"><?= count($products) ?></div></div></div></div>
      <div class="col-6 col-lg"><div class="card shadow-sm h-100 border-danger"><div class="card-body"><div class="text-muted small">Prioridad alta</div><div class="fs-4 fw-bold text-danger"><?= $priorityCounts['high'] ?></div></div></div></div>
      <div class="col-6 col-lg"><div class="card shadow-sm h-100 border-warning"><div class="card-body"><div class="text-muted small">Prioridad media</div><div class="fs-4 fw-bold text-warning"><?= $priorityCounts['medium'] ?></div></div></div></div>
      <div class="col-6 col-lg"><div class="card shadow-sm h-100 border-success"><div class="card-body"><div class="text-muted small">Dentro del ciclo</div><div class="fs-4 fw-bold text-success"><?= $priorityCounts['normal'] ?></div></div></div></div>
      <div class="col-6 col-lg"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">En observación</div><div class="fs-4 fw-bold"><?= $priorityCounts['observation'] ?></div></div></div></div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header bg-white"><h5 class="mb-0">Todos los productos comprados</h5></div>
      <div class="card-body p-0">
        <?php if (empty($visibleProducts)): ?>
          <div class="text-muted text-center py-4">No se encontraron productos comprados.</div>
        <?php endif; ?>
        <?php foreach ($visibleProducts as $index => $product): ?>
          <?php $collapseId = 'workerPurchasedProduct' . $index; ?>
          <div class="border-bottom">
            <button class="btn w-100 text-start rounded-0 px-3 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
              <div class="row g-2 align-items-center">
                <div class="col-lg-4"><div class="fw-semibold"><?= Helpers::e($product['product_name']) ?></div><div class="text-muted small"><?= (int)$product['purchase_count'] ?> compra(s) · <?= count($product['dates']) ?> fecha(s)</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">Última compra</div><?= Helpers::e(date('d/m/Y', strtotime($product['last_purchase']))) ?></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">Frecuencia</div><?= Helpers::e($product['frequency_text']) ?></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">Próxima estimada</div><?= $product['next_purchase'] ? Helpers::e(date('d/m/Y', strtotime($product['next_purchase']))) : '-' ?></div>
                <div class="col-6 col-lg-2 text-lg-end"><span class="badge text-bg-<?= Helpers::e($product['priority_class']) ?>"><?= Helpers::e($product['priority_text']) ?></span></div>
              </div>
            </button>
            <div class="collapse" id="<?= $collapseId ?>">
              <div class="px-3 pb-3 table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>Producto</th><th>Fecha completada</th><th>Área</th><th class="text-end">Cantidad</th></tr></thead>
                  <tbody>
                    <?php foreach ($product['purchases'] as $purchase): ?>
                      <tr>
                        <td><?= Helpers::e($product['product_name']) ?></td>
                        <td><?= Helpers::e(date('d/m/Y H:i', strtotime($purchase['purchased_at']))) ?></td>
                        <td><?= Helpers::e($purchase['purchase_area_name']) ?></td>
                        <td class="text-end"><?= $purchase['quantity'] !== null ? $number($purchase['quantity']) . ' ' . Helpers::e($purchase['unit_measure_abbreviation'] ?: ($purchase['unit_measure_name'] ?? '')) : '-' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="px-3 pb-3"><?= Pagination::render($pagination['meta']) ?></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
