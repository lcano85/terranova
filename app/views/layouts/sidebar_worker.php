<?php
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
?>
<div class="sidebar bg-white border-end p-3">
  <div class="mb-3">
    <div class="fw-bold">Panel Trabajador</div>
    <div class="text-muted small"><?= Helpers::e((Auth::user()['first_name'] ?? '') . ' ' . (Auth::user()['last_name'] ?? '')) ?></div>
  </div>

  <div class="nav flex-column gap-1">
    <a class="nav-link <?= $path === '/worker' ? 'active' : '' ?>" href="/worker">Dashboard</a>
    <a class="nav-link <?= $path === '/worker/attendance' ? 'active' : '' ?>" href="/worker/attendance">Mi asistencia</a>
    <a class="nav-link <?= $path === '/worker/inventory' ? 'active' : '' ?>" href="/worker/inventory">Inventario</a>
    <a class="nav-link <?= $path === '/worker/requirements' ? 'active' : '' ?>" href="/worker/requirements">Requerimientos</a>
    <a class="nav-link <?= $path === '/worker/profile' ? 'active' : '' ?>" href="/worker/profile">Mi perfil</a>
    <hr>
    <a class="nav-link text-danger" href="/logout">Salir</a>
  </div>
</div>
