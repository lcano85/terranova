<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../models/Promotion.php';

function dayOptions(int $selected = 1): string {
  $days = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'];
  $html = '';
  foreach ($days as $k=>$v) {
    $sel = ($k === $selected) ? 'selected' : '';
    $html .= "<option value=\"{$k}\" {$sel}>{$v}</option>";
  }
  return $html;
}

function shiftOptions(string $selected = 'morning'): string {
  $opts = ['morning'=>'Mañana','afternoon'=>'Tarde'];
  $html = '';
  foreach ($opts as $k=>$v) {
    $sel = ($k === $selected) ? 'selected' : '';
    $html .= "<option value=\"{$k}\" {$sel}>{$v}</option>";
  }
  return $html;
}
?>

<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Promociones</h3>
        <div class="text-muted small">Se repiten automáticamente por día (Lun–Sáb) y turno (mañana/tarde), sin fechas.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">+ Nueva</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Día</th>
              <th>Turno</th>
              <th>Título</th>
              <th>Estado</th>
              <th style="width:180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($promos as $p): ?>
              <tr>
                <td><?= (int)$p['id'] ?></td>
                <td><?= Helpers::e(Promotion::weekdayLabel((int)$p['weekday'])) ?></td>
                <td><?= Helpers::e(Promotion::shiftLabel((string)$p['shift'])) ?></td>
                <td><?= Helpers::e((string)($p['title'] ?? '')) ?></td>
                <td>
                  <?php if ((int)$p['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Activa</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= (int)$p['id'] ?>">Editar</button>

                  <form method="POST" onsubmit="return confirm('¿Eliminar promoción?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEdit<?= (int)$p['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar promoción</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-4">
                            <label class="form-label">Día</label>
                            <select class="form-select" name="weekday" required>
                              <?= dayOptions((int)$p['weekday']) ?>
                            </select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Turno</label>
                            <select class="form-select" name="shift" required>
                              <?= shiftOptions((string)$p['shift']) ?>
                            </select>
                          </div>
                          <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int)$p['id'] ?>" <?= ((int)$p['is_active']===1)?'checked':'' ?>>
                              <label class="form-check-label" for="active<?= (int)$p['id'] ?>">Activa</label>
                            </div>
                          </div>

                          <div class="col-12">
                            <label class="form-label">Título (opcional)</label>
                            <input class="form-control" name="title" value="<?= Helpers::e((string)($p['title'] ?? '')) ?>" maxlength="120">
                          </div>

                          <div class="col-12">
                            <label class="form-label">Texto de la promoción</label>
                            <textarea class="form-control" name="content" rows="6" required><?= Helpers::e((string)($p['content'] ?? '')) ?></textarea>
                            <div class="form-text">Tip: aquí va el copy completo que quieres mostrar en “Promoción del día”.</div>
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

            <?php if (empty($promos)): ?>
              <tr><td colspan="6" class="text-muted">No hay promociones registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="modalCreate" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nueva promoción</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Día</label>
                  <select class="form-select" name="weekday" required>
                    <?= dayOptions(1) ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Turno</label>
                  <select class="form-select" name="shift" required>
                    <?= shiftOptions('morning') ?>
                  </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="activeNew" checked>
                    <label class="form-check-label" for="activeNew">Activa</label>
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label">Título (opcional)</label>
                  <input class="form-control" name="title" maxlength="120" placeholder="Ej: San Lunes, Martes de Locura, etc.">
                </div>

                <div class="col-12">
                  <label class="form-label">Texto de la promoción</label>
                  <textarea class="form-control" name="content" rows="6" required placeholder="Escribe aquí la promo completa..."></textarea>
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

  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
