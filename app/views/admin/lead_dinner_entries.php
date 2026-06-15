<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireLogin();
if (!Auth::canManageLeadDinner()) {
  http_response_code(403);
  exit('403 - Acceso denegado');
}
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$limitedLeadAccess = !empty($limitedLeadAccess);
$rowsPagination = Pagination::paginateArray($rows, 'leads_cena_page', 'leads_cena_per_page');
$rows = $rowsPagination['rows'];
$rowsPaginationMeta = $rowsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require $limitedLeadAccess
    ? __DIR__ . '/../layouts/sidebar_worker.php'
    : __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Leads Cena</h3>
        <div class="text-muted small">Registros del formulario publico del concurso de cena.</div>
      </div>
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createLeadModal">
        + Nuevo lead
      </button>
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
              <th>Campaña</th>
              <th>Voucher</th>
              <th>Fecha/Hora</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= Helpers::e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= Helpers::e($row['whatsapp']) ?></td>
                <td><?= Helpers::e($row['email']) ?></td>
                <td><?= Helpers::e($row['campaign_name'] ?? 'Sin campaña') ?></td>
                <td>
                  <?php if (!empty($row['voucher_path'])): ?>
                    <a href="<?= Helpers::e($row['voucher_path']) ?>" target="_blank" rel="noopener">
                      <?= Helpers::e($row['voucher_original_name']) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">Sin voucher</span>
                  <?php endif; ?>
                </td>
                <td><?= Helpers::e(Helpers::formatDateTime($row['created_at'])) ?></td>
                <td style="min-width: 220px;">
                  <?php if ($limitedLeadAccess): ?>
                    <span class="badge text-bg-secondary"><?= Helpers::e($row['status_name']) ?></span>
                  <?php else: ?>
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
                  <?php endif; ?>
                </td>
                <td style="min-width: 160px;">
                  <div class="d-flex gap-2">
                    <button
                      class="btn btn-sm btn-outline-secondary"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#editLeadModal<?= (int)$row['id'] ?>"
                    >
                      Editar
                    </button>
                    <?php if (!$limitedLeadAccess): ?>
                      <form method="POST" onsubmit="return confirm('Eliminar este lead y su voucher?');">
                        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-muted">No hay leads registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($rowsPaginationMeta) ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="createLeadModal" tabindex="-1" aria-labelledby="createLeadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="modal-header">
          <h5 class="modal-title" id="createLeadModalLabel">Crear lead</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombres</label>
              <input class="form-control" name="first_name" maxlength="120" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Apellidos</label>
              <input class="form-control" name="last_name" maxlength="120" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp</label>
              <input class="form-control" name="whatsapp" maxlength="30" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo</label>
              <input type="email" class="form-control" name="email" maxlength="180">
              <div class="form-text">Opcional.</div>
            </div>
            <?php if (!$limitedLeadAccess): ?>
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select class="form-select" name="status_id" required>
                  <option value="">Selecciona un estado</option>
                  <?php foreach ($statuses as $status): ?>
                    <option value="<?= (int)$status['id'] ?>"><?= Helpers::e($status['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label">Campaña</label>
              <select class="form-select" name="campaign_id" required>
                <option value="">Selecciona una campaña activa</option>
                <?php foreach ($campaigns as $campaign): ?>
                  <option value="<?= (int)$campaign['id'] ?>"><?= Helpers::e($campaign['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Voucher de consumo</label>
              <input type="file" class="form-control" name="voucher" accept="image/*,.pdf,.heic,.heif">
              <div class="form-text">Opcional. JPG, PNG, WEBP, HEIC, HEIF o PDF.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Crear lead</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($rows as $row): ?>
  <div class="modal fade" id="editLeadModal<?= (int)$row['id'] ?>" tabindex="-1" aria-labelledby="editLeadModalLabel<?= (int)$row['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

          <div class="modal-header">
            <h5 class="modal-title" id="editLeadModalLabel<?= (int)$row['id'] ?>">Editar lead #<?= (int)$row['id'] ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nombres</label>
                <input class="form-control" name="first_name" maxlength="120" value="<?= Helpers::e($row['first_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Apellidos</label>
                <input class="form-control" name="last_name" maxlength="120" value="<?= Helpers::e($row['last_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">WhatsApp</label>
                <input class="form-control" name="whatsapp" maxlength="30" value="<?= Helpers::e($row['whatsapp']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo</label>
                <input type="email" class="form-control" name="email" maxlength="180" value="<?= Helpers::e($row['email']) ?>">
                <div class="form-text">Opcional.</div>
              </div>
              <?php if (!$limitedLeadAccess): ?>
                <div class="col-md-6">
                  <label class="form-label">Estado</label>
                  <select class="form-select" name="status_id" required>
                    <?php foreach ($statuses as $status): ?>
                      <option value="<?= (int)$status['id'] ?>" <?= (int)$row['status_id'] === (int)$status['id'] ? 'selected' : '' ?>>
                        <?= Helpers::e($status['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <div class="col-md-6">
                <label class="form-label">Campaña</label>
                <select class="form-select" name="campaign_id" required>
                  <option value="">Selecciona una campaña activa</option>
                  <?php foreach ($campaigns as $campaign): ?>
                    <option value="<?= (int)$campaign['id'] ?>" <?= (int)($row['campaign_id'] ?? 0) === (int)$campaign['id'] ? 'selected' : '' ?>>
                      <?= Helpers::e($campaign['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Reemplazar voucher</label>
                <input type="file" class="form-control" name="voucher" accept="image/*,.pdf,.heic,.heif">
                <div class="form-text">
                  Opcional.
                  <?php if (!empty($row['voucher_path'])): ?>
                    Actual:
                    <a href="<?= Helpers::e($row['voucher_path']) ?>" target="_blank" rel="noopener">
                      <?= Helpers::e($row['voucher_original_name']) ?>
                    </a>
                  <?php else: ?>
                    Actualmente no tiene voucher.
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
