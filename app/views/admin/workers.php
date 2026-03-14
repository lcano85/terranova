<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">Trabajadores</h3>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">+ Nuevo</button>
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
              <th>Documento</th>
              <th>Nombres</th>
              <th>Turno</th>
              <th>Área</th>
              <th>Pago diario</th>
              <th style="width:180px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($workers as $w): ?>
              <tr>
                <td><?= (int)$w['id'] ?></td>
                <td><?= Helpers::e($w['document_type'].' '.$w['document_number']) ?></td>
                <td><?= Helpers::e($w['first_name'].' '.$w['last_name']) ?></td>
                <td><?= Helpers::e($w['shift_name'] ?? '-') ?></td>
                <td><?= Helpers::e($w['area_name'] ?? '-') ?></td>
                <td><?= isset($w['daily_rate']) ? 'S/ '.number_format((float)$w['daily_rate'],2) : '-' ?></td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEdit<?= (int)$w['id'] ?>">Editar</button>

                  <form method="POST" onsubmit="return confirm('¿Eliminar trabajador?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEdit<?= (int)$w['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar trabajador</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="row g-2">
                          <div class="col-md-6">
                            <label class="form-label">Tipo doc</label>
                            <select class="form-select" name="document_type" required>
                              <option value="dni" <?= $w['document_type']==='dni'?'selected':'' ?>>DNI</option>
                              <option value="cedula" <?= $w['document_type']==='cedula'?'selected':'' ?>>Cédula</option>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Nro doc</label>
                            <input class="form-control" name="document_number" value="<?= Helpers::e($w['document_number']) ?>" required>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label">Nombres</label>
                            <input class="form-control" name="first_name" value="<?= Helpers::e($w['first_name']) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input class="form-control" name="last_name" value="<?= Helpers::e($w['last_name']) ?>" required>
                          </div>

                          <div class="col-md-12">
                            <label class="form-label">Turno</label>
                            <select class="form-select" name="shift_id">
                              <option value="">(Sin turno)</option>
                              <?php foreach ($shifts as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)$w['shift_id']===(int)$s['id'])?'selected':'' ?>>
                                  <?= Helpers::e($s['name'].' ('.$s['start_time'].'-'.$s['end_time'].')') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="col-md-12">
                            <label class="form-label">Área</label>
                            <select class="form-select" name="area_id" required>
                              <option value="">(Selecciona área)</option>
                              <?php foreach ($areas as $ar): ?>
                                <option value="<?= (int)$ar['id'] ?>" <?= ((int)$w['area_id']===(int)$ar['id'])?'selected':'' ?>>
                                  <?= Helpers::e($ar['name']) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="col-md-12">
                            <label class="form-label">Pago diario (S/)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="daily_rate"
                                   value="<?= isset($w['daily_rate']) ? Helpers::e($w['daily_rate']) : '' ?>"
                                   placeholder="Ej: 60.00">
                            <div class="text-muted small mt-1">Si lo dejas vacío, se guardará sin tarifa.</div>
                          </div>


                          <div class="col-md-12">
                            <label class="form-label">Nueva clave (opcional)</label>
                            <input type="password" class="form-control" name="password" placeholder="Dejar vacío para no cambiar">
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

            <?php if (empty($workers)): ?>
              <tr><td colspan="7" class="text-muted">No hay trabajadores</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="modalCreate" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nuevo trabajador</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">Tipo doc</label>
                  <select class="form-select" name="document_type" required>
                    <option value="dni">DNI</option>
                    <option value="cedula">Cédula</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Nro doc</label>
                  <input class="form-control" name="document_number" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Nombres</label>
                  <input class="form-control" name="first_name" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Apellidos</label>
                  <input class="form-control" name="last_name" required>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Turno</label>
                  <select class="form-select" name="shift_id">
                    <option value="">(Sin turno)</option>
                    <?php foreach ($shifts as $s): ?>
                      <option value="<?= (int)$s['id'] ?>">
                        <?= Helpers::e($s['name'].' ('.$s['start_time'].'-'.$s['end_time'].')') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Área</label>
                  <select class="form-select" name="area_id" required>
                    <option value="">(Selecciona área)</option>
                    <?php foreach ($areas as $ar): ?>
                      <option value="<?= (int)$ar['id'] ?>"><?= Helpers::e($ar['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Pago diario (S/)</label>
                  <input type="number" step="0.01" min="0" class="form-control" name="daily_rate" placeholder="Ej: 60.00">
                </div>


                <div class="col-md-12">
                  <label class="form-label">Clave</label>
                  <input type="password" class="form-control" name="password" value="123456" required>
                  <div class="text-muted small mt-1">Puedes cambiarla luego (sí, ya sé… 123456 😄)</div>
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
