<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';

function activityWeekDays(string $from): array {
  $days = [];
  $dt = new DateTime($from);
  $labels = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
  for ($i = 0; $i < 6; $i++) {
    $days[] = [
      'label' => $labels[$i],
      'date' => $dt->format('Y-m-d')
    ];
    $dt->modify('+1 day');
  }
  return $days;
}

$weekDays = activityWeekDays($week['from']);
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0">Actividades</h3>
        <div class="text-muted small">Administra actividades por trabajador y revisa lo realizado en la semana.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateActivity">+ Nueva</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body table-responsive">
        <h5 class="mb-3">Actividades asignadas</h5>
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Trabajador</th>
              <th>Documento</th>
              <th>Actividad</th>
              <th>Estado</th>
              <th style="width: 220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignments as $assignment): ?>
              <tr>
                <td><?= (int)$assignment['id'] ?></td>
                <td><?= Helpers::e($assignment['first_name'] . ' ' . $assignment['last_name']) ?></td>
                <td><?= Helpers::e($assignment['document_number']) ?></td>
                <td><?= Helpers::e($assignment['name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$assignment['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$assignment['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
                  </span>
                </td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditActivity<?= (int)$assignment['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar actividad asignada?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditActivity<?= (int)$assignment['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar actividad</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Trabajador</label>
                          <select class="form-select" name="user_id" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($workers as $worker): ?>
                              <option value="<?= (int)$worker['id'] ?>" <?= (int)$worker['id'] === (int)$assignment['user_id'] ? 'selected' : '' ?>>
                                <?= Helpers::e($worker['first_name'] . ' ' . $worker['last_name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Actividad</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($assignment['name']) ?>" required>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" id="isActive<?= (int)$assignment['id'] ?>" <?= (int)$assignment['is_active'] === 1 ? 'checked' : '' ?>>
                          <label class="form-check-label" for="isActive<?= (int)$assignment['id'] ?>">Activa</label>
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

            <?php if (empty($assignments)): ?>
              <tr><td colspan="6" class="text-muted">No hay actividades asignadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Actividades realizadas por trabajador</h5>
        <div class="text-muted small mb-3">
          Semana: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>

        <?php if (empty($board)): ?>
          <div class="text-muted">No hay actividades registradas esta semana.</div>
        <?php endif; ?>

        <?php foreach ($board as $worker): ?>
          <div class="border rounded p-3 mb-4">
            <h6 class="mb-3">Trabajador: <?= Helpers::e($worker['worker_name']) ?></h6>
            <div class="row g-3">
              <?php foreach ($weekDays as $weekDay): ?>
                <div class="col-md-2">
                  <div class="border rounded h-100 p-2">
                    <div class="fw-semibold small"><?= Helpers::e($weekDay['label']) ?></div>
                    <div class="text-muted small mb-2"><?= Helpers::e(date('d/m/Y', strtotime($weekDay['date']))) ?></div>
                    <?php $items = $worker['days'][$weekDay['date']] ?? []; ?>
                    <?php if (empty($items)): ?>
                      <div class="text-muted small">Sin actividades</div>
                    <?php else: ?>
                      <ul class="small mb-0 ps-3">
                        <?php foreach ($items as $item): ?>
                          <li><?= Helpers::e($item) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="modal fade" id="modalCreateActivity" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
              <h5 class="modal-title">Nueva actividad</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Trabajador</label>
                <select class="form-select" name="user_id" required>
                  <option value="">Selecciona</option>
                  <?php foreach ($workers as $worker): ?>
                    <option value="<?= (int)$worker['id'] ?>"><?= Helpers::e($worker['first_name'] . ' ' . $worker['last_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Actividad</label>
                <input class="form-control" name="name" placeholder="Ej: Limpiar cocina" required>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="createActivityActive" checked>
                <label class="form-check-label" for="createActivityActive">Activa</label>
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
