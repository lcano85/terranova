<?php
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Database.php';

class App {
  public function run(): void {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rtrim($path, '/') ?: '/';

    $routes = [
      '/' => ['AuthController', 'login'],
      '/login' => ['AuthController', 'login'],
      '/logout' => ['AuthController', 'logout'],

      '/attendance/mark' => ['AttendanceController', 'mark'],

      '/admin' => ['AdminController', 'dashboard'],
      '/admin/workers' => ['AdminController', 'workers'],
      '/admin/shifts' => ['AdminController', 'shifts'],
      '/admin/areas' => ['AdminController', 'areas'],
      '/admin/purchase-areas' => ['AdminController', 'purchaseAreas'],
      '/admin/requirements' => ['AdminController', 'requirements'],
      '/admin/activities' => ['AdminController', 'activities'],
      '/admin/promotions' => ['AdminController', 'promotions'],
      '/admin/attendance' => ['AdminController', 'attendance'],
      '/admin/inventory' => ['AdminController', 'inventory'],
      '/admin/profile' => ['AdminController', 'profile'],

      // Público: ver promoción del día (para la pantalla de marcación)
      '/promotions/today' => ['PromotionsController', 'today'],

      '/worker' => ['WorkerController', 'dashboard'],
      '/worker/attendance' => ['WorkerController', 'myAttendance'],
      '/worker/inventory' => ['WorkerController', 'inventory'],
      '/worker/requirements' => ['WorkerController', 'requirements'],
      '/worker/activities' => ['WorkerController', 'activities'],
      '/worker/profile' => ['WorkerController', 'profile'],
    ];

    if (!isset($routes[$path])) {
      http_response_code(404);
      echo "404 - Ruta no encontrada";
      return;
    }

    [$controller, $method] = $routes[$path];

    require_once __DIR__ . '/../controllers/' . $controller . '.php';
    $instance = new $controller();

    if (!method_exists($instance, $method)) {
      http_response_code(500);
      echo "500 - Método no existe";
      return;
    }

    $instance->$method();
  }
}
