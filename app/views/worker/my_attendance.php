<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('worker');
require_once __DIR__ . '/../../core/Pagination.php';

$myAttendancePagination = Pagination::paginateArray($rows, 'my_attendance_page', 'my_attendance_per_page');
$rows = $myAttendancePagination['rows'];
$myAttendancePaginationMeta = $myAttendancePagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_worker.php'; ?>

  <div class="content p-4">
    <h3 class="mb-3">Mi asistencia</h3>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tipo</th>
              <th>Fecha/Hora</th>
              <th>Tardanza</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                  <span class="badge text-bg-<?= $r['mark_type']==='in'?'success':'secondary' ?>">
                    <?= $r['mark_type']==='in'?'Entrada':'Salida' ?>
                  </span>
                </td>
                <td><?= Helpers::e(Helpers::formatDateTime($r['marked_at'])) ?></td>
                <td><?= (int)$r['minutes_late'] ?> min</td>
                <td><?= Helpers::e($r['ip_address'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" class="text-muted">Sin registros</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($myAttendancePaginationMeta) ?>
    </div>

  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
