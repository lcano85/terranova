<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

$campaignsPagination = Pagination::paginateArray($campaigns, 'campaigns_page', 'campaigns_per_page');
$campaigns = $campaignsPagination['rows'];
$campaignsPaginationMeta = $campaignsPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Campañas</h3>
        <div class="text-muted small">Administra las campañas disponibles para los leads.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
        + Nueva campaña
      </button>
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
              <th>Campaña</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Sorteo</th>
              <th>Estado</th>
              <th>Leads</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($campaigns as $campaign): ?>
              <tr>
                <td><?= (int)$campaign['id'] ?></td>
                <td><?= Helpers::e($campaign['name']) ?></td>
                <td><?= Helpers::e(Helpers::formatDate($campaign['start_date'])) ?></td>
                <td><?= Helpers::e(Helpers::formatDate($campaign['end_date'])) ?></td>
                <td><?= Helpers::e(Helpers::formatDate($campaign['draw_date'])) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int)$campaign['is_active'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$campaign['is_active'] === 1 ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td><?= (int)$campaign['leads_count'] ?></td>
                <td>
                  <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editCampaignModal<?= (int)$campaign['id'] ?>">
                      Editar
                    </button>
                    <form method="POST" onsubmit="return confirm('Eliminar esta campaña?');">
                      <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?>
              <tr><td colspan="8" class="text-muted">No hay campañas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?= Pagination::render($campaignsPaginationMeta) ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="createCampaignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Nueva campaña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre de la campaña</label>
              <input class="form-control" name="name" maxlength="150" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de inicio</label>
              <input type="date" class="form-control" name="start_date" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha fin</label>
              <input type="date" class="form-control" name="end_date" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de sorteo</label>
              <input type="date" class="form-control" name="draw_date" required>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="campaignActiveNew" checked>
                <label class="form-check-label" for="campaignActiveNew">Activo</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary">Crear campaña</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($campaigns as $campaign): ?>
  <div class="modal fade" id="editCampaignModal<?= (int)$campaign['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Editar campaña</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nombre de la campaña</label>
                <input class="form-control" name="name" maxlength="150" value="<?= Helpers::e($campaign['name']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de inicio</label>
                <input type="date" class="form-control" name="start_date" value="<?= Helpers::e($campaign['start_date']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha fin</label>
                <input type="date" class="form-control" name="end_date" value="<?= Helpers::e($campaign['end_date']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de sorteo</label>
                <input type="date" class="form-control" name="draw_date" value="<?= Helpers::e($campaign['draw_date']) ?>" required>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_active" id="campaignActive<?= (int)$campaign['id'] ?>" <?= (int)$campaign['is_active'] === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="campaignActive<?= (int)$campaign['id'] ?>">Activo</label>
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
