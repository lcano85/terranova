<?php
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
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
    .sidebar { width: 260px; }
    .content { flex: 1; }
    .nav-link.active { font-weight: 600; background: #f3f4f6; border-radius: .5rem; }
  </style>
</head>
<body>
