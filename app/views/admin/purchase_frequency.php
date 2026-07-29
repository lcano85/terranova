<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Pagination.php';

$money = static fn($value): string => 'S/ ' . number_format((float)$value, 2);
$number = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
$calendarMonthNames = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$filterQuery = [
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'purchase_area_id' => $purchaseAreaId ?: null,
  'product' => $productSearch ?: null,
  'frequency' => $frequencyFilter ?: null,
  'status' => $statusFilter ?: null,
];
$filterQuery = array_filter($filterQuery, static fn($value): bool => $value !== null && $value !== '');
$listFilterBaseQuery = array_filter([
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'purchase_area_id' => $purchaseAreaId ?: null,
  'frequency' => $frequencyFilter ?: null,
  'calendar_month' => $calendarStart->format('Y-m'),
], static fn($value): bool => $value !== null && $value !== '');
$calendarYear = (int)$calendarStart->format('Y');
$calendarMonthNumber = (int)$calendarStart->format('n');
$daysInMonth = (int)$calendarStart->format('t');
$firstWeekday = (int)$calendarStart->format('N');
$productPagination = Pagination::paginateArray($products, 'frequency_page', 'frequency_per_page', [20, 50, 100]);
$visibleProducts = $productPagination['rows'];
$calendarModalDays = [];
?>
<style>
  .purchase-calendar { display: grid; grid-template-columns: repeat(7, minmax(125px, 1fr)); min-width: 875px; }
  .purchase-calendar > div { border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; }
  .purchase-calendar > div:nth-child(7n) { border-right: 0; }
  .purchase-calendar-day { min-height: 125px; padding: .6rem; background: #fff; }
  .purchase-calendar-day.is-empty { background: #f8f9fa; }
  .purchase-calendar-item { font-size: .72rem; line-height: 1.2; margin-top: .3rem; padding: .25rem .35rem; border-radius: .25rem; background: #e8f5ee; color: #146c43; }
</style>

<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Frecuencia de compras</h3>
        <div class="text-muted small">Historial, calendario y próxima compra estimada según los productos completados.</div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label">Fecha desde</label>
            <input class="form-control" type="date" name="date_from" value="<?= Helpers::e($dateFrom) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Fecha hasta</label>
            <input class="form-control" type="date" name="date_to" value="<?= Helpers::e($dateTo) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Área de compra</label>
            <select class="form-select" name="purchase_area_id">
              <option value="">Todas</option>
              <?php foreach ($areas as $area): ?>
                <option value="<?= (int)$area['id'] ?>" <?= $purchaseAreaId === (int)$area['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($area['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Producto</label>
            <input class="form-control" name="product" value="<?= Helpers::e($productSearch) ?>" placeholder="Buscar producto">
          </div>
          <div class="col-md-2">
            <label class="form-label">Frecuencia</label>
            <select class="form-select" name="frequency">
              <option value="">Todas</option>
              <option value="weekly" <?= $frequencyFilter === 'weekly' ? 'selected' : '' ?>>Semanal</option>
              <option value="biweekly" <?= $frequencyFilter === 'biweekly' ? 'selected' : '' ?>>Quincenal</option>
              <option value="monthly" <?= $frequencyFilter === 'monthly' ? 'selected' : '' ?>>Mensual</option>
              <option value="sporadic" <?= $frequencyFilter === 'sporadic' ? 'selected' : '' ?>>Esporádica</option>
              <option value="irregular" <?= $frequencyFilter === 'irregular' ? 'selected' : '' ?>>Irregular</option>
              <option value="insufficient" <?= $frequencyFilter === 'insufficient' ? 'selected' : '' ?>>Datos insuficientes</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Recomendación</label>
            <select class="form-select" name="status">
              <option value="">Todas</option>
              <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Vencido</option>
              <option value="today" <?= $statusFilter === 'today' ? 'selected' : '' ?>>Comprar hoy</option>
              <option value="soon" <?= $statusFilter === 'soon' ? 'selected' : '' ?>>Próximos 7 días</option>
              <option value="normal" <?= $statusFilter === 'normal' ? 'selected' : '' ?>>Dentro del ciclo</option>
              <option value="irregular" <?= $statusFilter === 'irregular' ? 'selected' : '' ?>>Irregular</option>
              <option value="insufficient" <?= $statusFilter === 'insufficient' ? 'selected' : '' ?>>Datos insuficientes</option>
            </select>
          </div>
          <div class="col-12 d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            <a class="btn btn-outline-secondary" href="<?= Helpers::e(BASE_URL . '/admin/purchase-frequency') ?>">Limpiar</a>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Productos analizados</div><div class="fs-4 fw-bold"><?= (int)$summary['products'] ?></div>
      </div></div></div>
      <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Compras completadas</div><div class="fs-4 fw-bold"><?= (int)$summary['purchases'] ?></div>
      </div></div></div>
      <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Gasto del periodo</div><div class="fs-4 fw-bold text-success"><?= $money($summary['spend']) ?></div>
      </div></div></div>
      <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Requieren atención</div><div class="fs-4 fw-bold text-danger"><?= (int)$summary['due'] ?></div>
      </div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white py-3">
        <div class="page-toolbar">
          <div>
            <h5 class="mb-0">Calendario de compras — <?= Helpers::e($calendarMonthNames[$calendarMonthNumber]) ?> <?= $calendarYear ?></h5>
            <div class="text-muted small">Los productos se ubican en su fecha real de completado.</div>
          </div>
          <div class="btn-group">
            <a class="btn btn-outline-secondary" href="?<?= Helpers::e(http_build_query($filterQuery + ['calendar_month' => $calendarPrevious])) ?>">&larr;</a>
            <a class="btn btn-outline-secondary" href="?<?= Helpers::e(http_build_query($filterQuery + ['calendar_month' => $calendarNext])) ?>">&rarr;</a>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <div class="purchase-calendar">
          <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday): ?>
            <div class="text-center fw-semibold bg-light py-2"><?= Helpers::e($weekday) ?></div>
          <?php endforeach; ?>
          <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
            <div class="purchase-calendar-day is-empty"></div>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
            $dateKey = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonthNumber, $day);
            $dayEvents = $calendarEvents[$dateKey] ?? [];
            ?>
            <div class="purchase-calendar-day">
              <div class="d-flex justify-content-between">
                <span class="fw-semibold"><?= $day ?></span>
                <?php if (!empty($dayEvents)): ?><span class="badge text-bg-success"><?= count($dayEvents) ?></span><?php endif; ?>
              </div>
              <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                <div class="purchase-calendar-item" title="<?= Helpers::e($event['purchase_area_name'] . ' · ' . date('H:i', strtotime($event['purchased_at']))) ?>">
                  <?= Helpers::e($event['product_name']) ?>
                </div>
              <?php endforeach; ?>
              <?php if (count($dayEvents) > 3): ?>
                <?php
                $dayModalId = 'purchaseDayModal' . $calendarStart->format('Ym') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $calendarDayTotal = array_reduce(
                  $dayEvents,
                  static fn(float $total, array $event): float => $total + ($event['subtotal'] !== null ? (float)$event['subtotal'] : 0.0),
                  0.0
                );
                $calendarModalDays[] = [
                  'id' => $dayModalId,
                  'date' => $dateKey,
                  'events' => $dayEvents,
                ];
                ?>
                <button
                  class="btn btn-sm btn-outline-success w-100 mt-2"
                  type="button"
                  data-bs-toggle="modal"
                  data-bs-target="#<?= Helpers::e($dayModalId) ?>">
                  Ver productos (<?= count($dayEvents) ?>)
                </button>
                <div class="small text-success fw-semibold text-center mt-1">
                  Total gastado: <?= $money($calendarDayTotal) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <?php foreach ($calendarModalDays as $modalDay): ?>
      <?php
      $dayTotal = array_reduce(
        $modalDay['events'],
        static fn(float $total, array $event): float => $total + ($event['subtotal'] !== null ? (float)$event['subtotal'] : 0.0),
        0.0
      );
      ?>
      <div class="modal fade" id="<?= Helpers::e($modalDay['id']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title">Productos comprados</h5>
                <div class="text-muted small">
                  Fecha completada: <?= Helpers::e(date('d/m/Y', strtotime($modalDay['date']))) ?>
                  · <?= count($modalDay['events']) ?> producto(s)
                </div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Producto</th>
                      <th>Hora</th>
                      <th>Área</th>
                      <th class="text-end">Cantidad</th>
                      <th class="text-end">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($modalDay['events'] as $event): ?>
                      <tr>
                        <td class="fw-semibold"><?= Helpers::e($event['product_name']) ?></td>
                        <td><?= Helpers::e(date('H:i', strtotime($event['purchased_at']))) ?></td>
                        <td><?= Helpers::e($event['purchase_area_name']) ?></td>
                        <td class="text-end"><?= $event['quantity'] !== null ? $number($event['quantity']) : '-' ?></td>
                        <td class="text-end"><?= $event['subtotal'] !== null ? $money($event['subtotal']) : '-' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                  <tfoot class="table-light">
                    <tr>
                      <th colspan="4" class="text-end">Total gastado del día</th>
                      <th class="text-end text-success fs-6"><?= $money($dayTotal) ?></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white py-3">
        <h5 class="mb-0">Frecuencia y próxima compra estimada</h5>
        <div class="text-muted small">Se requieren al menos 3 fechas distintas. La frecuencia utiliza la mediana de días entre compras.</div>
      </div>
      <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="date_from" value="<?= Helpers::e($dateFrom) ?>">
          <input type="hidden" name="date_to" value="<?= Helpers::e($dateTo) ?>">
          <?php if ($purchaseAreaId > 0): ?>
            <input type="hidden" name="purchase_area_id" value="<?= (int)$purchaseAreaId ?>">
          <?php endif; ?>
          <?php if ($frequencyFilter !== ''): ?>
            <input type="hidden" name="frequency" value="<?= Helpers::e($frequencyFilter) ?>">
          <?php endif; ?>
          <input type="hidden" name="calendar_month" value="<?= Helpers::e($calendarStart->format('Y-m')) ?>">

          <div class="col-md-5">
            <label class="form-label" for="frequencyProductSearch">Nombre del producto</label>
            <input
              class="form-control"
              id="frequencyProductSearch"
              name="product"
              value="<?= Helpers::e($productSearch) ?>"
              placeholder="Escribe el nombre del producto"
              autocomplete="off">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="frequencyStatusSearch">Estado</label>
            <select class="form-select" id="frequencyStatusSearch" name="status">
              <option value="">Todos los estados</option>
              <option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Vencido</option>
              <option value="today" <?= $statusFilter === 'today' ? 'selected' : '' ?>>Comprar hoy</option>
              <option value="soon" <?= $statusFilter === 'soon' ? 'selected' : '' ?>>Próximos 7 días</option>
              <option value="normal" <?= $statusFilter === 'normal' ? 'selected' : '' ?>>Dentro del ciclo</option>
              <option value="irregular" <?= $statusFilter === 'irregular' ? 'selected' : '' ?>>Compra irregular</option>
              <option value="insufficient" <?= $statusFilter === 'insufficient' ? 'selected' : '' ?>>Datos insuficientes</option>
            </select>
          </div>
          <div class="col-md-4 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Buscar</button>
            <a class="btn btn-outline-secondary" href="?<?= Helpers::e(http_build_query($listFilterBaseQuery)) ?>">Limpiar</a>
          </div>
        </form>
        <div class="form-text mt-2">También puedes presionar Enter desde el campo de nombre para realizar la búsqueda.</div>
      </div>
      <div class="card-body p-0">
        <?php if (empty($products)): ?>
          <div class="text-muted text-center py-4">No hay productos que coincidan con los filtros.</div>
        <?php else: ?>
          <?php foreach ($visibleProducts as $index => $product): ?>
            <?php $collapseId = 'purchaseFrequencyProduct' . $index; ?>
            <div class="border-bottom">
              <button class="btn w-100 text-start rounded-0 px-3 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                <div class="row g-2 align-items-center">
                  <div class="col-lg-3">
                    <div class="fw-semibold"><?= Helpers::e($product['product_name']) ?></div>
                    <div class="text-muted small"><?= (int)$product['purchase_count'] ?> compra(s) · <?= count($product['dates']) ?> fecha(s)</div>
                  </div>
                  <div class="col-6 col-lg-2"><div class="text-muted small">Última compra</div><?= Helpers::e(date('d/m/Y', strtotime($product['last_purchase']))) ?></div>
                  <div class="col-6 col-lg-2"><div class="text-muted small">Frecuencia</div><?= Helpers::e($product['frequency_text']) ?></div>
                  <div class="col-6 col-lg-2">
                    <div class="text-muted small">Próxima estimada</div>
                    <?= $product['next_purchase'] ? Helpers::e(date('d/m/Y', strtotime($product['next_purchase']))) : '-' ?>
                  </div>
                  <div class="col-6 col-lg-2"><span class="badge text-bg-<?= Helpers::e($product['status_class']) ?>"><?= Helpers::e($product['status_text']) ?></span></div>
                  <div class="col-lg-1 text-end"><span class="btn btn-sm btn-outline-secondary">Ver</span></div>
                </div>
              </button>
              <div class="collapse" id="<?= $collapseId ?>">
                <div class="px-3 pb-3">
                  <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Cantidad acumulada</div><strong><?= $number($product['total_quantity']) ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Gasto acumulado</div><strong><?= $money($product['total_spend']) ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Intervalos observados</div><strong><?= empty($product['intervals']) ? '-' : Helpers::e(implode(', ', $product['intervals'])) . ' días' ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-2"><div class="text-muted small">Sin precio/cantidad</div><strong><?= (int)$product['unpriced_count'] ?></strong></div></div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead><tr><th>Fecha completada</th><th>Área</th><th>Solicitado por</th><th class="text-end">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr></thead>
                      <tbody>
                        <?php foreach (array_reverse($product['purchases']) as $purchase): ?>
                          <tr>
                            <td><?= Helpers::e(date('d/m/Y H:i', strtotime($purchase['purchased_at']))) ?></td>
                            <td><?= Helpers::e($purchase['purchase_area_name']) ?></td>
                            <td><?= Helpers::e($purchase['requested_by']) ?></td>
                            <td class="text-end"><?= $purchase['quantity'] !== null ? $number($purchase['quantity']) : '-' ?></td>
                            <td class="text-end"><?= $purchase['unit_price'] !== null ? $money($purchase['unit_price']) : '-' ?></td>
                            <td class="text-end"><?= $purchase['subtotal'] !== null ? $money($purchase['subtotal']) : '-' ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="px-3 pb-3">
            <?= Pagination::render($productPagination['meta']) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="alert alert-info">
      <strong>Interpretación:</strong> la fecha sugerida se basa en el historial de compras, no en el stock disponible.
      Los productos irregulares o con menos de tres fechas necesitan más información antes de generar una recomendación confiable.
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
