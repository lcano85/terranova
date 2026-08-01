<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Helpers::e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f6f7fb; }
    .app-shell { min-height: 100vh; }
    .sidebar {
      width: 260px;
      flex: 0 0 260px;
      min-height: 100vh;
      max-height: 100vh;
      overflow-y: auto;
      position: sticky;
      top: 0;
      align-self: flex-start;
    }
    .content {
      flex: 1;
      min-width: 0;
    }
    .mobile-topbar {
      display: none;
      position: sticky;
      top: 0;
      z-index: 1020;
      background: rgba(246, 247, 251, 0.92);
      backdrop-filter: blur(10px);
    }
    .nav-link.active { font-weight: 600; background: #f3f4f6; border-radius: .5rem; }
    .security-menu-label {
      align-items: center;
      background: transparent;
      border: 0;
      color: #495057;
      display: flex;
      font-size: .82rem;
      font-weight: 700;
      justify-content: space-between;
      letter-spacing: .02em;
      padding-left: .75rem;
      padding-right: .75rem;
      padding-top: .65rem;
      padding-bottom: .35rem;
      text-align: left;
      text-transform: uppercase;
      width: 100%;
    }
    .security-menu-label.active { color: #0d6efd; }
    .security-menu-toggle:hover { color: #0d6efd; }
    .security-menu-toggle:focus-visible {
      border-radius: .5rem;
      box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .18);
      outline: 0;
    }
    .security-menu-chevron {
      border-bottom: 2px solid currentColor;
      border-right: 2px solid currentColor;
      flex: 0 0 auto;
      height: .5rem;
      margin-left: .5rem;
      transform: rotate(225deg);
      transition: transform .2s ease;
      width: .5rem;
    }
    .security-menu-toggle.collapsed .security-menu-chevron { transform: rotate(45deg); }
    .security-submenu { border-left: 1px solid #e9ecef; margin-left: .75rem; }
    .page-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    .page-toolbar > :first-child {
      margin-bottom: 0;
    }

    @media (max-width: 991.98px) {
      .app-shell {
        display: block !important;
      }
      .sidebar {
        display: none;
      }
      .mobile-topbar {
        display: block;
      }
      .content.p-4 {
        padding: 1rem !important;
      }
    }

    @media (max-width: 767.98px) {
      .page-toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      .page-toolbar .btn,
      .page-toolbar .btn-group {
        width: 100%;
      }
      .page-toolbar .btn-group > .btn {
        width: 100%;
      }
      .table-responsive {
        font-size: .95rem;
      }
      .modal-dialog {
        margin: .75rem;
      }
    }
  </style>
</head>
<body>
