<?php
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$adminLinks = [
  ['/admin', 'Dashboard'],
  ['/admin/workers', 'Trabajadores'],
  ['/admin/shifts', 'Turnos'],
  ['/admin/areas', 'Areas'],
  ['/admin/purchase-areas', 'Areas de compras'],
  ['/admin/requirements', 'Requerimientos'],
  ['/admin/activities', 'Actividades'],
  ['/admin/tasks', 'Gestion de tareas'],
  ['/admin/inventory', 'Inventario'],
  ['/admin/products', 'Productos'],
  ['/admin/sales', 'Ventas'],
  ['/admin/promotions', 'Promociones'],
  ['/admin/attendance', 'Asistencia'],
  ['/admin/profile', 'Mi perfil'],
];
?>
<div class="mobile-topbar border-bottom px-3 py-2">
  <div class="d-flex align-items-center justify-content-between gap-3">
    <div class="min-w-0">
      <div class="fw-bold">Panel Admin</div>
      <div class="text-muted small"><?= Helpers::e((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')) ?></div>
    </div>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarMobile" aria-controls="adminSidebarMobile">
      Menu
    </button>
  </div>
</div>

<div class="sidebar bg-white border-end p-3">
  <div class="mb-3">
    <div class="fw-bold">Panel Admin</div>
    <div class="text-muted small"><?= Helpers::e((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')) ?></div>
  </div>

  <div class="nav flex-column gap-1">
    <?php foreach ($adminLinks as [$href, $label]): ?>
      <a class="nav-link <?= $path === $href ? 'active' : '' ?>" href="<?= Helpers::e($href) ?>"><?= Helpers::e($label) ?></a>
    <?php endforeach; ?>
    <hr>
    <a class="nav-link text-danger" href="/logout">Salir</a>
  </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="adminSidebarMobile" aria-labelledby="adminSidebarMobileLabel">
  <div class="offcanvas-header">
    <div>
      <h5 class="offcanvas-title mb-0" id="adminSidebarMobileLabel">Panel Admin</h5>
      <div class="text-muted small"><?= Helpers::e((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <div class="nav flex-column gap-1">
      <?php foreach ($adminLinks as [$href, $label]): ?>
        <a class="nav-link <?= $path === $href ? 'active' : '' ?>" href="<?= Helpers::e($href) ?>"><?= Helpers::e($label) ?></a>
      <?php endforeach; ?>
      <hr>
      <a class="nav-link text-danger" href="/logout">Salir</a>
    </div>
  </div>
</div>
