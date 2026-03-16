<?php
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
?>
<div class="sidebar bg-white border-end p-3">
  <div class="mb-3">
    <div class="fw-bold">Panel Admin</div>
    <div class="text-muted small"><?= Helpers::e((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')) ?></div>
  </div>

  <div class="nav flex-column gap-1">
    <a class="nav-link <?= $path === '/admin' ? 'active' : '' ?>" href="/admin">Dashboard</a>
    <a class="nav-link <?= $path === '/admin/workers' ? 'active' : '' ?>" href="/admin/workers">Trabajadores</a>
    <a class="nav-link <?= $path === '/admin/shifts' ? 'active' : '' ?>" href="/admin/shifts">Turnos</a>
    <a class="nav-link <?= $path === '/admin/areas' ? 'active' : '' ?>" href="/admin/areas">Areas</a>
    <a class="nav-link <?= $path === '/admin/purchase-areas' ? 'active' : '' ?>" href="/admin/purchase-areas">Areas de compras</a>
    <a class="nav-link <?= $path === '/admin/requirements' ? 'active' : '' ?>" href="/admin/requirements">Requerimientos</a>
    <a class="nav-link <?= $path === '/admin/activities' ? 'active' : '' ?>" href="/admin/activities">Actividades</a>
    <a class="nav-link <?= $path === '/admin/inventory' ? 'active' : '' ?>" href="/admin/inventory">Inventario</a>
    <a class="nav-link <?= $path === '/admin/promotions' ? 'active' : '' ?>" href="/admin/promotions">Promociones</a>
    <a class="nav-link <?= $path === '/admin/attendance' ? 'active' : '' ?>" href="/admin/attendance">Asistencia</a>
    <a class="nav-link <?= $path === '/admin/profile' ? 'active' : '' ?>" href="/admin/profile">Mi perfil</a>
    <hr>
    <a class="nav-link text-danger" href="/logout">Salir</a>
  </div>
</div>
