<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Requerimientos</h3>
        <div class="text-muted small">
          Semana visible: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Semana</label>
            <select class="form-select" name="week_start" onchange="this.form.submit()">
              <?php foreach ($weekOptions as $option): ?>
                <option value="<?= Helpers::e($option['from']) ?>" <?= $selectedWeekStart === $option['from'] ? 'selected' : '' ?>>
                  <?= Helpers::e($option['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-grid">
            <label class="form-label">&nbsp;</label>
            <a class="btn btn-outline-secondary" href="/admin/requirements">Semana actual</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body table-responsive">
        <div class="page-toolbar mb-3">
          <div>
            <h5 class="mb-0">Log de correos</h5>
            <div class="text-muted small">Ultimos intentos de notificacion por correo al administrador.</div>
          </div>
        </div>

        <table class="table align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Asunto</th>
              <th>Destinatarios</th>
              <th>Estado</th>
              <th>Error</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mailLogs as $log): ?>
              <tr>
                <td><?= Helpers::e(Helpers::formatDateTime($log['created_at'])) ?></td>
                <td><?= Helpers::e($log['subject']) ?></td>
                <td><?= Helpers::e($log['recipients'] ?? '-') ?></td>
                <td>
                  <span class="badge text-bg-<?= ($log['status'] ?? '') === 'sent' ? 'success' : (($log['status'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>">
                    <?= Helpers::e($log['status']) ?>
                  </span>
                </td>
                <td><?= Helpers::e($log['error_message'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($mailLogs)): ?>
              <tr><td colspan="5" class="text-muted">Aun no hay correos registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <?php if (empty($grouped)): ?>
      <div class="card shadow-sm">
        <div class="card-body text-muted">No hay requerimientos registrados para la semana seleccionada.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($grouped as $worker): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h5 class="mb-3">Trabajador: <?= Helpers::e($worker['worker_name']) ?></h5>

          <?php foreach ($worker['areas'] as $area): ?>
            <div class="border rounded p-3 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold text-capitalize">Area de compra: <?= Helpers::e($area['purchase_area_name']) ?></div>
                <div class="text-muted small">Fecha: <?= Helpers::e(date('d/m/Y', strtotime($area['required_date']))) ?></div>
              </div>

              <div class="d-flex flex-column gap-2">
                <?php foreach ($area['items'] as $item): ?>
                  <form method="POST" class="border rounded px-3 py-2">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="toggle_item">
                    <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">

                    <div class="form-check d-flex align-items-center gap-2">
                      <input class="form-check-input mt-0"
                             type="checkbox"
                             name="is_purchased"
                             value="1"
                             onchange="this.form.submit()"
                             <?= (int)$item['is_purchased'] === 1 ? 'checked' : '' ?>>
                      <label class="form-check-label flex-grow-1">
                        <?= Helpers::e($item['item_name']) ?>
                      </label>
                      <span class="badge text-bg-<?= (int)$item['is_purchased'] === 1 ? 'success' : 'secondary' ?>">
                        <?= (int)$item['is_purchased'] === 1 ? 'Comprado' : 'Pendiente' ?>
                      </span>
                    </div>
                  </form>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
