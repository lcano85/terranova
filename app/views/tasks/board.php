<?php
require __DIR__ . '/../layouts/header.php';

function publicTaskWeekColumns(): array {
  return [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miercoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sabado'
  ];
}

$weekColumns = publicTaskWeekColumns();
?>
<div class="container-fluid py-4" style="max-width: 1400px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 class="mb-0">Tablero de tareas</h3>
          <div class="text-muted small">Vista publica de tareas asignadas de lunes a sabado.</div>
        </div>
                <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="/attendance/mark">Marcar asistencia</a>
          <a class="btn btn-primary" href="/login">Iniciar sesion</a>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach ($weekColumns as $dayNumber => $dayLabel): ?>
          <?php $dayBoard = $board[$dayNumber] ?? ['morning' => [], 'afternoon' => []]; ?>
          <div class="col-md-2">
            <div class="border rounded h-100 p-2 bg-white">
              <div class="fw-semibold mb-2"><?= Helpers::e($dayLabel) ?></div>

              <div class="small fw-semibold text-primary mb-1">Turno mañana</div>
              <?php if (empty($dayBoard['morning'])): ?>
                <div class="text-muted small mb-2">Sin tareas</div>
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
                <div class="text-muted small">Sin tareas</div>
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
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>

