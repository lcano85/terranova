<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$rowsPagination = Pagination::paginateArray($rows, 'leads_cena_page', 'leads_cena_per_page');
$rows = $rowsPagination['rows'];
$rowsPaginationMeta = $rowsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Leads Cena</h3>
        <div class="text-muted small">Registros del formulario publico del concurso de cena.</div>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2">
          <div class="col-md-4">
            <select class="form-select" name="status_id">
              <option value="">Todos los estados</option>
              <?php foreach ($statuses as $status): ?>
                <option value="<?= (int)$status['id'] ?>" <?= $statusId === (int)$status['id'] ? 'selected' : '' ?>>
                  <?= Helpers::e($status['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <input class="form-control" name="q" value="<?= Helpers::e($search) ?>" placeholder="Buscar por nombre, whatsapp o correo">
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-outline-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Cliente</th>
              <th>WhatsApp</th>
              <th>Correo</th>
              <th>Voucher</th>
              <th>Fecha/Hora</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= Helpers::e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= Helpers::e($row['whatsapp']) ?></td>
                <td><?= Helpers::e($row['email']) ?></td>
                <td>
                  <a href="<?= Helpers::e($row['voucher_path']) ?>" target="_blank" rel="noopener">
                    <?= Helpers::e($row['voucher_original_name']) ?>
                  </a>
                </td>
                <td><?= Helpers::e(Helpers::formatDateTime($row['created_at'])) ?></td>
                <td style="min-width: 220px;">
                  <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <div class="d-flex gap-2">
                      <select class="form-select form-select-sm" name="status_id">
                        <?php foreach ($statuses as $status): ?>
                          <option value="<?= (int)$status['id'] ?>" <?= (int)$row['status_id'] === (int)$status['id'] ? 'selected' : '' ?>>
                            <?= Helpers::e($status['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-primary">Guardar</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-muted">No hay leads registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($rowsPaginationMeta) ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
