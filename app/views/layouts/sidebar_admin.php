<?php $sidebarName = trim((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')); ?>
<div class="mobile-topbar border-bottom px-3 py-2">
  <div class="d-flex align-items-center justify-content-between gap-3">
    <div class="min-w-0">
      <div class="fw-bold">Panel de gestión</div>
      <div class="text-muted small"><?= Helpers::e($sidebarName) ?></div>
    </div>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarMobile">Menú</button>
  </div>
</div>
<div class="sidebar bg-white border-end p-3">
  <div class="mb-3">
    <div class="fw-bold">Panel de gestión</div>
    <div class="text-muted small"><?= Helpers::e($sidebarName) ?></div>
  </div>
  <div class="nav flex-column gap-1">
    <?php require __DIR__ . '/security_menu.php'; ?>
    <hr>
    <a class="nav-link text-danger" href="/logout">Salir</a>
  </div>
</div>
<div class="offcanvas offcanvas-start" tabindex="-1" id="adminSidebarMobile">
  <div class="offcanvas-header">
    <div>
      <h5 class="offcanvas-title mb-0">Panel de gestión</h5>
      <div class="text-muted small"><?= Helpers::e($sidebarName) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="nav flex-column gap-1">
      <?php require __DIR__ . '/security_menu.php'; ?>
      <hr>
      <a class="nav-link text-danger" href="/logout">Salir</a>
    </div>
  </div>
</div>
