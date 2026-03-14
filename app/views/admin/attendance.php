<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">Asistencia</h3>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateAttendance">+ Nuevo</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2" method="GET">
          <div class="col-md-3">
            <input class="form-control" name="doc" placeholder="Documento" value="<?= Helpers::e($doc ?? '') ?>">
          </div>
          <div class="col-md-3">
            <input type="date" class="form-control" name="from" value="<?= Helpers::e($from ?? '') ?>">
          </div>
          <div class="col-md-3">
            <input type="date" class="form-control" name="to" value="<?= Helpers::e($to ?? '') ?>">
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-outline-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Documento</th>
              <th>Trabajador</th>
              <th>Tipo</th>
              <th>Fecha/Hora</th>
              <th>Tardanza</th>
              <th>IP</th>
              <th style="width: 180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= Helpers::e($r['document_number']) ?></td>
                <td><?= Helpers::e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= $r['mark_type'] === 'in' ? 'success' : 'secondary' ?>">
                    <?= $r['mark_type'] === 'in' ? 'Entrada' : 'Salida' ?>
                  </span>
                </td>
                <td><?= Helpers::e($r['marked_at']) ?></td>
                <td><?= (int)$r['minutes_late'] ?> min</td>
                <td><?= Helpers::e($r['ip_address'] ?? '-') ?></td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditAttendance<?= (int)$r['id'] ?>">Editar</button>

                  <form method="POST" onsubmit="return confirm('Eliminar asistencia?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditAttendance<?= (int)$r['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar asistencia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="row g-2">
                          <div class="col-md-12">
                            <label class="form-label">Trabajador</label>
                            <select class="form-select" name="user_id" required>
                              <option value="">Selecciona trabajador</option>
                              <?php foreach ($workers as $w): ?>
                                <option value="<?= (int)$w['id'] ?>" <?= ((int)$r['user_id'] === (int)$w['id']) ? 'selected' : '' ?>>
                                  <?= Helpers::e($w['document_number'] . ' - ' . $w['first_name'] . ' ' . $w['last_name']) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="mark_type" required>
                              <option value="in" <?= $r['mark_type'] === 'in' ? 'selected' : '' ?>>Entrada</option>
                              <option value="out" <?= $r['mark_type'] === 'out' ? 'selected' : '' ?>>Salida</option>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Fecha y hora</label>
                            <input type="datetime-local" class="form-control" name="marked_at" value="<?= Helpers::e(date('Y-m-d\TH:i', strtotime($r['marked_at']))) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">IP</label>
                            <input class="form-control" name="ip_address" value="<?= Helpers::e($r['ip_address'] ?? '') ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">User agent</label>
                            <input class="form-control" name="user_agent" value="<?= Helpers::e($r['user_agent'] ?? '') ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Latitud</label>
                            <input type="number" step="0.000001" class="form-control" name="latitude" value="<?= Helpers::e($r['latitude'] ?? '') ?>">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Longitud</label>
                            <input type="number" step="0.000001" class="form-control" name="longitude" value="<?= Helpers::e($r['longitude'] ?? '') ?>">
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
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-muted">Sin registros para los filtros</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="modalCreateAttendance" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nueva asistencia</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="row g-2">
                <div class="col-md-12">
                  <label class="form-label">Trabajador</label>
                  <select class="form-select" name="user_id" required>
                    <option value="">Selecciona trabajador</option>
                    <?php foreach ($workers as $w): ?>
                      <option value="<?= (int)$w['id'] ?>">
                        <?= Helpers::e($w['document_number'] . ' - ' . $w['first_name'] . ' ' . $w['last_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Tipo</label>
                  <select class="form-select" name="mark_type" required>
                    <option value="in">Entrada</option>
                    <option value="out">Salida</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Fecha y hora</label>
                  <input type="datetime-local" class="form-control" name="marked_at" value="<?= Helpers::e(date('Y-m-d\TH:i')) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">IP</label>
                  <input class="form-control" name="ip_address">
                </div>
                <div class="col-md-6">
                  <label class="form-label">User agent</label>
                  <input class="form-control" name="user_agent">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Latitud</label>
                  <input type="number" step="0.000001" class="form-control" name="latitude">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Longitud</label>
                  <input type="number" step="0.000001" class="form-control" name="longitude">
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
