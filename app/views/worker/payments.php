<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Pagination.php';

$paymentsPagination = Pagination::paginateArray($rows, 'payments_page', 'payments_per_page');
$rows = $paymentsPagination['rows'];
$paymentsPaginationMeta = $paymentsPagination['meta'];

function workerPaymentTypeLabel(string $type): string {
  if ($type === 'monthly') return 'Mensual';
  if ($type === 'weekly') return 'Semanal';
  return 'Quincenal';
}
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Mis pagos</h3>
        <div class="text-muted small">Consulta los pagos publicados por administracion.</div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Periodo</th>
              <th>Tipo</th>
              <th>Dias</th>
              <th>Bruto</th>
              <th>Descuentos</th>
              <th>Adicionales</th>
              <th>Pago final</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $payment): ?>
              <tr>
                <td><?= Helpers::e(Helpers::formatDateTime($payment['created_at'])) ?></td>
                <td>
                  <?= Helpers::e(Helpers::formatDate($payment['period_start'])) ?> -
                  <?= Helpers::e(Helpers::formatDate($payment['period_end'])) ?>
                </td>
                <td><?= Helpers::e(workerPaymentTypeLabel((string)$payment['payment_type'])) ?></td>
                <td><?= Helpers::e((string)(float)$payment['worked_days']) ?></td>
                <td>S/ <?= number_format((float)$payment['gross_amount'], 2) ?></td>
                <td>S/ <?= number_format((float)$payment['deductions_total'], 2) ?></td>
                <td>S/ <?= number_format((float)$payment['additions_total'], 2) ?></td>
                <td><strong>S/ <?= number_format((float)$payment['net_amount'], 2) ?></strong></td>
                <td>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#paymentDetail<?= (int)$payment['id'] ?>"
                  >
                    Ver detalle
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="9" class="text-muted">No tienes pagos publicados.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($paymentsPaginationMeta) ?>
    </div>
  </div>
</div>

<?php foreach ($rows as $payment): ?>
  <div class="modal fade" id="paymentDetail<?= (int)$payment['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Detalle del pago #<?= (int)$payment['id'] ?></h5>
            <div class="text-muted small">
              <?= Helpers::e(Helpers::formatDate($payment['period_start'])) ?> -
              <?= Helpers::e(Helpers::formatDate($payment['period_end'])) ?>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="text-muted small">Tipo de pago</div>
              <strong><?= Helpers::e(workerPaymentTypeLabel((string)$payment['payment_type'])) ?></strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Dias trabajados</div>
              <strong><?= Helpers::e((string)(float)$payment['worked_days']) ?></strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Pago por dia</div>
              <strong>S/ <?= number_format((float)$payment['daily_rate'], 2) ?></strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Horas por dia</div>
              <strong><?= Helpers::e((string)(float)$payment['hours_per_day']) ?></strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Minutos de tardanza</div>
              <strong><?= (int)$payment['late_minutes'] ?> min</strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Factor de descuento por minuto</div>
              <strong>S/ <?= number_format((float)$payment['late_rate_per_minute'], 4) ?></strong>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">Descuento por tardanza</div>
              <strong>S/ <?= number_format((float)$payment['late_discount'], 2) ?></strong>
            </div>
          </div>

          <?php if (!empty($payment['items'])): ?>
            <h6>Adicionales y descuentos</h6>
            <div class="table-responsive mb-3">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th>Concepto</th>
                    <th class="text-end">Monto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payment['items'] as $item): ?>
                    <tr>
                      <td><?= $item['item_type'] === 'addition' ? 'Adicional' : 'Descuento' ?></td>
                      <td><?= Helpers::e((string)$item['concept']) ?></td>
                      <td class="text-end">S/ <?= number_format((float)$item['amount'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div class="row justify-content-end">
            <div class="col-lg-6">
              <div class="d-flex justify-content-between border-bottom py-2">
                <span>Pago bruto</span>
                <strong>S/ <?= number_format((float)$payment['gross_amount'], 2) ?></strong>
              </div>
              <div class="d-flex justify-content-between border-bottom py-2">
                <span>Adicionales</span>
                <strong>S/ <?= number_format((float)$payment['additions_total'], 2) ?></strong>
              </div>
              <div class="d-flex justify-content-between border-bottom py-2">
                <span>Descuentos</span>
                <strong>S/ <?= number_format((float)$payment['deductions_total'], 2) ?></strong>
              </div>
              <div class="d-flex justify-content-between py-3 fs-5">
                <span>Pago final</span>
                <strong>S/ <?= number_format((float)$payment['net_amount'], 2) ?></strong>
              </div>
            </div>
          </div>

          <?php if (trim((string)($payment['notes'] ?? '')) !== ''): ?>
            <div class="alert alert-light border mb-0">
              <strong>Notas:</strong><br>
              <?= nl2br(Helpers::e((string)$payment['notes'])) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
