<?php
Auth::requireRole('worker');
require __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../core/Pagination.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>
  <div class="content p-4">
    <nav class="nav nav-tabs mb-3" aria-label="Requerimientos">
      <a class="nav-link" href="/worker/requirements">Mis requerimientos</a>
      <a class="nav-link active" aria-current="page" href="/worker/requirements?tab=purchases">Estado general de compras</a>
    </nav>
    <h3>Estado general de compras</h3>
    <p class="text-muted">Consulta los productos enviados por todos los trabajadores, pendientes y comprados hasta la fecha.</p>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="purchases">
        <div class="col-lg-4 col-md-6">
          <label class="form-label" for="productSearch">Buscar producto</label>
          <input type="search" class="form-control" id="productSearch" name="product_search" value="<?= Helpers::e($search) ?>" placeholder="Nombre del producto">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label" for="purchaseStatus">Estado</label>
          <select class="form-select" id="purchaseStatus" name="status">
            <?php foreach (['all'=>'Todos','pending'=>'Pendientes','purchased'=>'Comprados'] as $value=>$label): ?>
              <option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label" for="dateFrom">Solicitud desde</label>
          <input type="date" class="form-control" id="dateFrom" name="from" value="<?= Helpers::e($from) ?>">
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label" for="dateTo">Solicitud hasta</label>
          <input type="date" class="form-control" id="dateTo" name="to" value="<?= Helpers::e($to) ?>">
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline-secondary" href="/worker/requirements?tab=purchases">Limpiar</a>
        </div>
      </form>
    </div></div>
    <?php if ($error): ?><div class="alert alert-warning" role="alert"><?= Helpers::e($error) ?></div><?php endif; ?>
    <div class="card shadow-sm"><div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Producto</th><th>Cantidad / Unidad</th><th>Área de compra</th><th>Fecha y hora de solicitud</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach ($pagination['rows'] as $item): ?>
              <tr>
                <td><?= Helpers::e($item['item_name']) ?><?php if ($item['detail']): ?><div class="text-muted small"><?= Helpers::e($item['detail']) ?></div><?php endif; ?></td>
                <td><?= Helpers::e(rtrim(rtrim(number_format((float)$item['quantity'], 2, '.', ''), '0'), '.') . ' ' . ($item['unit_label'] ?? '')) ?></td>
                <td><?= Helpers::e($item['area_name']) ?></td>
                <td><?= Helpers::e(Helpers::formatDateTime($item['created_at'])) ?></td>
                <td><span class="badge text-bg-<?= (int)$item['is_purchased']===1?'success':'secondary' ?>"><?= (int)$item['is_purchased']===1?'Comprado':'Pendiente' ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$pagination['rows']): ?><tr><td colspan="5" class="text-muted">No hay productos para los filtros seleccionados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($pagination['meta']) ?>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>