<?php
require __DIR__ . '/../layouts/header.php';
Auth::requirePermission('security.logs');
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
  <div class="content p-4">
    <div class="page-toolbar mb-3"><div><h3 class="mb-1">Seguridad · Logs de acceso</h3><div class="text-muted">Inicios de sesión, cierres, accesos a módulos e intentos denegados.</div></div></div>
    <div class="card shadow-sm mb-3"><div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Evento</label><select class="form-select" name="event_type">
          <option value="">Todos</option>
          <?php foreach (['login_success'=>'Inicio correcto','login_failed'=>'Inicio fallido','logout'=>'Cierre de sesión','module_access'=>'Acceso a módulo','access_denied'=>'Acceso denegado'] as $key=>$label): ?>
            <option value="<?= $key ?>" <?= $filters['event_type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><label class="form-label">Usuario</label><select class="form-select" name="user_id"><option value="">Todos</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>" <?= (int)$filters['user_id'] === (int)$user['id'] ? 'selected' : '' ?>><?= Helpers::e(trim($user['first_name'].' '.$user['last_name'])) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Desde</label><input class="form-control" type="date" name="date_from" value="<?= Helpers::e($filters['date_from']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><input class="form-control" type="date" name="date_to" value="<?= Helpers::e($filters['date_to']) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filtrar</button><a class="btn btn-outline-secondary" href="/admin/security/logs">Limpiar</a></div>
      </form>
    </div></div>
    <div class="card shadow-sm"><div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Fecha/Hora</th><th>Usuario</th><th>Evento</th><th>Módulo</th><th>Ruta</th><th>IP</th><th>Detalle</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= Helpers::e(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
              <td><?= Helpers::e(trim($log['user_name'] ?? '') ?: 'No identificado') ?><div class="small text-muted"><?= Helpers::e($log['document_number'] ?? '') ?></div></td>
              <td><span class="badge text-bg-<?= in_array($log['event_type'], ['login_failed','access_denied'], true) ? 'danger' : ($log['event_type'] === 'login_success' ? 'success' : 'secondary') ?>"><?= Helpers::e($log['event_type']) ?></span></td>
              <td><?= Helpers::e($log['module_name'] ?? '-') ?></td><td><?= Helpers::e($log['route'] ?? '-') ?></td><td><?= Helpers::e($log['ip_address'] ?? '-') ?></td><td><?= Helpers::e($log['details'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?><tr><td colspan="7" class="text-muted">No hay eventos con estos filtros.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
