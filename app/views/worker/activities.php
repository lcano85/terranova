<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Csrf.php';

function workerActivityWeekDays(string $from): array {
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

$weekDays = workerActivityWeekDays($week['from']);
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <div class="mb-3">
      <h3 class="mb-0">Mis actividades</h3>
      <div class="text-muted small">Registra las actividades realizadas del dia y revisa tu semana.</div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Fecha de registro</label>
              <input type="date" class="form-control" name="activity_date" value="<?= Helpers::e($today) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Buscar actividad</label>
              <input type="text" class="form-control" id="activitySearch" placeholder="Buscar actividad asignada">
            </div>
          </div>

          <hr class="my-4">

          <div class="row g-2" id="assignedActivitiesList">
            <?php foreach ($assignedActivities as $activity): ?>
              <div class="col-md-4 activity-option" data-name="<?= Helpers::e(mb_strtolower($activity['name'])) ?>">
                <label class="border rounded p-3 w-100 d-flex align-items-start gap-2">
                  <input class="form-check-input mt-1"
                         type="checkbox"
                         name="activity_ids[]"
                         value="<?= (int)$activity['id'] ?>"
                         <?= in_array((int)$activity['id'], $selectedToday, true) ? 'checked' : '' ?>>
                  <span><?= Helpers::e($activity['name']) ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (empty($assignedActivities)): ?>
            <div class="text-muted">No tienes actividades asignadas por el administrador.</div>
          <?php endif; ?>

          <div class="mt-3">
            <button class="btn btn-primary" <?= empty($assignedActivities) ? 'disabled' : '' ?>>Guardar actividades del dia</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Tablero semanal</h5>
        <div class="text-muted small mb-3">
          Semana: <?= Helpers::e(date('d/m/Y', strtotime($week['from']))) ?> - <?= Helpers::e(date('d/m/Y', strtotime($week['to']))) ?>
        </div>

        <div class="row g-3">
          <?php foreach ($weekDays as $weekDay): ?>
            <div class="col-md-2">
              <div class="border rounded h-100 p-2">
                <div class="fw-semibold small"><?= Helpers::e($weekDay['label']) ?></div>
                <div class="text-muted small mb-2"><?= Helpers::e(date('d/m/Y', strtotime($weekDay['date']))) ?></div>
                <?php $items = $board[$weekDay['date']] ?? []; ?>
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
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('activitySearch');
    const items = Array.from(document.querySelectorAll('.activity-option'));

    if (!searchInput) {
      return;
    }

    searchInput.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();
      items.forEach(function (item) {
        const name = item.getAttribute('data-name') || '';
        item.style.display = name.includes(term) ? '' : 'none';
      });
    });
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
