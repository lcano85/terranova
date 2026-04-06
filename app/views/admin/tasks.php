<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

function taskWeekColumns(): array {
  return [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miercoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sabado'
  ];
}

$weekColumns = taskWeekColumns();
$tasksTableRows = $tasks;
$tasksCatalogPagination = Pagination::paginateArray($tasksTableRows, 'tasks_catalog_page', 'tasks_catalog_per_page');
$tasksTableRows = $tasksCatalogPagination['rows'];
$tasksCatalogPaginationMeta = $tasksCatalogPagination['meta'];

$assignmentsPagination = Pagination::paginateArray($assignments, 'task_assignments_page', 'task_assignments_per_page');
$assignments = $assignmentsPagination['rows'];
$assignmentsPaginationMeta = $assignmentsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <h3 class="mb-0">Gestion de tareas</h3>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTask">+ Nueva tarea</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body table-responsive">
        <div class="page-toolbar mb-3">
          <h5 class="mb-0">Catalogo de tareas</h5>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCreateAssignment">+ Asignar tarea</button>
        </div>
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tarea</th>
              <th>Estado</th>
              <th style="width: 220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasksTableRows as $task): ?>
              <tr>
                <td><?= (int)$task['id'] ?></td>
                <td><?= Helpers::e($task['name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$task['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$task['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
                  </span>
                </td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditTask<?= (int)$task['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar tarea?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditTask<?= (int)$task['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update_task">
                      <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar tarea</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Nombre</label>
                          <input class="form-control" name="name" value="<?= Helpers::e($task['name']) ?>" required>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" id="taskActive<?= (int)$task['id'] ?>" <?= (int)$task['is_active'] === 1 ? 'checked' : '' ?>>
                          <label class="form-check-label" for="taskActive<?= (int)$task['id'] ?>">Activa</label>
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

            <?php if (empty($tasksTableRows)): ?>
              <tr><td colspan="4" class="text-muted">No hay tareas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($tasksCatalogPaginationMeta) ?>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-body table-responsive">
        <h5 class="mb-3">Asignaciones por trabajador</h5>
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Trabajador</th>
              <th>Turno</th>
              <th>Dia</th>
              <th>Tarea</th>
              <th>Estado</th>
              <th style="width: 220px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignments as $assignment): ?>
              <tr>
                <td><?= (int)$assignment['id'] ?></td>
                <td><?= Helpers::e($assignment['first_name'] . ' ' . $assignment['last_name']) ?></td>
                <td><?= Helpers::e($assignment['shift_name'] ?? '-') ?></td>
                <td><?= Helpers::e(Task::weekdayLabel((int)$assignment['weekday'])) ?></td>
                <td><?= Helpers::e($assignment['task_name']) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$assignment['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$assignment['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
                  </span>
                </td>
                <td class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditAssignment<?= (int)$assignment['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar asignacion?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete_assignment">
                    <input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>

              <div class="modal fade" id="modalEditAssignment<?= (int)$assignment['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="update_assignment">
                      <input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>">

                      <div class="modal-header">
                        <h5 class="modal-title">Editar asignacion</h5>
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
                          <label class="form-label">Dia</label>
                          <select class="form-select" name="weekday" required>
                            <?php foreach ($weekColumns as $dayNumber => $dayLabel): ?>
                              <option value="<?= (int)$dayNumber ?>" <?= (int)$dayNumber === (int)$assignment['weekday'] ? 'selected' : '' ?>>
                                <?= Helpers::e($dayLabel) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Tarea</label>
                          <select class="form-select" name="task_id" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($tasks as $task): ?>
                              <option value="<?= (int)$task['id'] ?>" <?= (int)$task['id'] === (int)$assignment['task_id'] ? 'selected' : '' ?>>
                                <?= Helpers::e($task['name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" id="assignmentActive<?= (int)$assignment['id'] ?>" <?= (int)$assignment['is_active'] === 1 ? 'checked' : '' ?>>
                          <label class="form-check-label" for="assignmentActive<?= (int)$assignment['id'] ?>">Activa</label>
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
              <tr><td colspan="7" class="text-muted">No hay tareas asignadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($assignmentsPaginationMeta) ?>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Tablero semanal de tareas</h5>
        <div class="row g-3">
          <?php foreach ($weekColumns as $dayNumber => $dayLabel): ?>
            <?php $dayBoard = $board[$dayNumber] ?? ['morning' => [], 'afternoon' => []]; ?>
            <div class="col-md-2">
              <div class="border rounded h-100 p-2">
                <div class="fw-semibold mb-2"><?= Helpers::e($dayLabel) ?></div>

                <div class="small fw-semibold text-primary mb-1">Turno mañana</div>
                <?php if (empty($dayBoard['morning'])): ?>
                  <div class="text-muted small mb-2">Sin asignaciones</div>
                <?php else: ?>
                  <?php foreach ($dayBoard['morning'] as $workerData): ?>
                    <div class="mb-2">
                      <div class="small fw-semibold"><?= Helpers::e($workerData['worker_name']) ?></div>
                      <ul class="small mb-0 ps-3">
                        <?php foreach ($workerData['tasks'] as $taskName): ?>
                          <li><?= Helpers::e($taskName) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <div class="small fw-semibold text-success mb-1 mt-3">Turno tarde</div>
                <?php if (empty($dayBoard['afternoon'])): ?>
                  <div class="text-muted small">Sin asignaciones</div>
                <?php else: ?>
                  <?php foreach ($dayBoard['afternoon'] as $workerData): ?>
                    <div class="mb-2">
                      <div class="small fw-semibold"><?= Helpers::e($workerData['worker_name']) ?></div>
                      <ul class="small mb-0 ps-3">
                        <?php foreach ($workerData['tasks'] as $taskName): ?>
                          <li><?= Helpers::e($taskName) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalCreateTask" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create_task">

            <div class="modal-header">
              <h5 class="modal-title">Nueva tarea</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" required>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="createTaskActive" checked>
                <label class="form-check-label" for="createTaskActive">Activa</label>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-primary">Crear</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalCreateAssignment" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create_assignment">

            <div class="modal-header">
              <h5 class="modal-title">Asignar tarea</h5>
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
                <label class="form-label">Dia</label>
                <select class="form-select" name="weekday" required>
                  <?php foreach ($weekColumns as $dayNumber => $dayLabel): ?>
                    <option value="<?= (int)$dayNumber ?>"><?= Helpers::e($dayLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Tarea</label>
                <select class="form-select" name="task_id" required>
                  <option value="">Selecciona</option>
                  <?php foreach ($tasks as $task): ?>
                    <option value="<?= (int)$task['id'] ?>"><?= Helpers::e($task['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="createAssignmentActive" checked>
                <label class="form-check-label" for="createAssignmentActive">Activa</label>
              </div>
            </div>

            <div class="modal-footer">
              <button class="btn btn-primary">Asignar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
