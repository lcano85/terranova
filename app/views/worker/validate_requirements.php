<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';

$progress = $totalCount > 0 ? (int)round(($receivedCount / $totalCount) * 100) : 0;
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <main class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-1">Validar requerimiento</h3>
        <div class="text-muted">Confirma los productos que recibiste de tus requerimientos enviados.</div>
      </div>
      <a class="btn btn-outline-primary" href="/worker/requirements">Ir a mis requerimientos</a>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>" role="alert"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-7 col-lg-5">
            <label class="form-label" for="validationWeek">Semana del requerimiento</label>
            <select class="form-select" id="validationWeek" name="week_start" onchange="this.form.submit()">
              <?php foreach ($weekOptions as $option): ?>
                <option value="<?= Helpers::e($option['from']) ?>" <?= $selectedWeekStart === $option['from'] ? 'selected' : '' ?>>
                  <?= Helpers::e($option['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-auto d-grid">
            <a class="btn btn-outline-secondary" href="/worker/requirements/validate">Semana actual</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
          <div>
            <div class="fw-semibold">Avance de recepción</div>
            <div class="text-muted small">
              Semana: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
            </div>
          </div>
          <span class="badge fs-6 text-bg-<?= $progress === 100 && $totalCount > 0 ? 'success' : 'primary' ?>">
            <?= (int)$receivedCount ?> de <?= (int)$totalCount ?> recibidos
          </span>
        </div>
        <div class="progress" role="progressbar" aria-label="Productos recibidos" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" style="height: .75rem;">
          <div class="progress-bar <?= $progress === 100 && $totalCount > 0 ? 'bg-success' : '' ?>" style="width: <?= $progress ?>%"></div>
        </div>
      </div>
    </div>

    <?php if (empty($grouped)): ?>
      <div class="card shadow-sm">
        <div class="card-body py-5 text-center">
          <div class="fs-5 fw-semibold mb-1">No hay requerimientos enviados esta semana</div>
          <div class="text-muted">Los borradores no aparecen aquí hasta que sean enviados.</div>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($grouped as $group): ?>
      <?php
      $groupReceived = count(array_filter($group['items'], static fn($item) => (int)$item['is_received'] === 1));
      ?>
      <section class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div>
            <h5 class="mb-0"><?= Helpers::e($group['purchase_area_name']) ?></h5>
            <div class="text-muted small">Fecha solicitada: <?= Helpers::e(date('d/m/Y', strtotime($group['required_date']))) ?></div>
          </div>
          <span class="badge text-bg-<?= $groupReceived === count($group['items']) ? 'success' : 'secondary' ?>">
            <?= $groupReceived ?> / <?= count($group['items']) ?>
          </span>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach ($group['items'] as $item): ?>
            <?php $received = (int)$item['is_received'] === 1; ?>
            <div class="list-group-item p-3 <?= $received ? 'bg-success-subtle' : '' ?>">
              <form method="POST" class="d-flex align-items-start gap-3">
                <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                <input type="hidden" name="week_start" value="<?= Helpers::e($selectedWeekStart) ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                <div class="form-check pt-1">
                  <input
                    class="form-check-input fs-4 mt-0"
                    type="checkbox"
                    name="is_received"
                    value="1"
                    id="receivedItem<?= (int)$item['item_id'] ?>"
                    onchange="this.form.submit()"
                    <?= $received ? 'checked' : '' ?>>
                </div>
                <label class="flex-grow-1 cursor-pointer" for="receivedItem<?= (int)$item['item_id'] ?>">
                  <span class="fw-semibold <?= $received ? 'text-decoration-line-through' : '' ?>">
                    <?= Helpers::e(Requirement::itemDisplayName($item)) ?>
                  </span>
                  <?php if (!empty($item['detail'])): ?>
                    <span class="d-block small text-muted">Detalle: <?= Helpers::e($item['detail']) ?></span>
                  <?php endif; ?>
                  <span class="d-flex gap-2 flex-wrap mt-1">
                    <span class="badge text-bg-<?= (int)$item['is_purchased'] === 1 ? 'primary' : 'warning' ?>">
                      <?= (int)$item['is_purchased'] === 1 ? 'Comprado' : 'Compra pendiente' ?>
                    </span>
                    <?php if ($received && !empty($item['received_at'])): ?>
                      <span class="small text-success">
                        Recibido el <?= Helpers::e(date('d/m/Y H:i', strtotime($item['received_at']))) ?>
                      </span>
                    <?php endif; ?>
                  </span>
                </label>
                <span class="badge text-bg-<?= $received ? 'success' : 'light' ?> border <?= $received ? '' : 'text-dark' ?>">
                  <?= $received ? 'Recibido' : 'Por validar' ?>
                </span>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
