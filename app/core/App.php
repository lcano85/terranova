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
      '/tasks/board' => ['TasksController', 'board'],
      '/concurso/cena' => ['LeadDinnerController', 'form'],
      '/concurso/cena/gracias' => ['LeadDinnerController', 'thankYou'],

      '/admin' => ['AdminController', 'dashboard'],
      '/admin/workers' => ['AdminController', 'workers'],
      '/admin/shifts' => ['AdminController', 'shifts'],
      '/admin/areas' => ['AdminController', 'areas'],
      '/admin/purchase-areas' => ['AdminController', 'purchaseAreas'],
      '/admin/requirements' => ['AdminController', 'requirements'],
      '/admin/purchase-expenses' => ['AdminController', 'purchaseExpenses'],
      '/admin/purchase-frequency' => ['AdminController', 'purchaseFrequency'],
      '/admin/supplies' => ['AdminController', 'supplies'],
      '/admin/unit-measures' => ['AdminController', 'unitMeasures'],
      '/admin/activities' => ['AdminController', 'activities'],
      '/admin/tasks' => ['AdminController', 'tasks'],
      '/admin/promotions' => ['AdminController', 'promotions'],
      '/admin/attendance' => ['AdminController', 'attendance'],
      '/admin/payroll' => ['AdminController', 'payroll'],
      '/admin/incentives' => ['AdminController', 'incentives'],
      '/admin/inventory' => ['AdminController', 'inventory'],
      '/admin/inventory/history-seen' => ['AdminController', 'inventoryHistorySeen'],
      '/admin/beverages' => ['AdminController', 'beverages'],
      '/admin/products' => ['AdminController', 'products'],
      '/admin/costing' => ['AdminController', 'costing'],
      '/admin/delivery-pricing' => ['AdminController', 'deliveryPricing'],
      '/admin/recipes' => ['AdminController', 'recipes'],
      '/admin/sales' => ['AdminController', 'sales'],
      '/admin/sales/statistics' => ['AdminController', 'salesStatistics'],
      '/admin/finance/income-expenses' => ['AdminController', 'incomeExpenses'],
      '/admin/finance/other-expenses' => ['AdminController', 'otherExpenses'],
      '/admin/finance/other-expenses/statistics' => ['AdminController', 'otherExpenseStatistics'],
      '/admin/leads-cena' => ['AdminController', 'leadDinnerEntries'],
      '/admin/leads-cena-campaigns' => ['AdminController', 'leadDinnerCampaigns'],
      '/admin/leads-cena-statuses' => ['AdminController', 'leadDinnerStatuses'],
      '/admin/profile' => ['AdminController', 'profile'],
      '/admin/security/users' => ['SecurityController', 'users'],
      '/admin/security/roles' => ['SecurityController', 'roles'],
      '/admin/security/permissions' => ['SecurityController', 'permissions'],
      '/admin/security/logs' => ['SecurityController', 'logs'],

      // Público: ver promoción del día (para la pantalla de marcación)
      '/promotions/today' => ['PromotionsController', 'today'],
      '/promotions/weekly' => ['PromotionsController', 'weekly'],

      '/worker' => ['WorkerController', 'dashboard'],
      '/worker/attendance' => ['WorkerController', 'myAttendance'],
      '/worker/leads-cena' => ['AdminController', 'leadDinnerEntries'],
      '/worker/payments' => ['WorkerController', 'payments'],
      '/worker/incentives' => ['WorkerController', 'incentives'],
      '/worker/inventory' => ['WorkerController', 'inventory'],
      '/worker/beverages' => ['AdminController', 'beverages'],
      '/worker/requirements' => ['WorkerController', 'requirements'],
      '/worker/purchase-frequency' => ['WorkerController', 'purchaseFrequency'],
      '/worker/activities' => ['WorkerController', 'activities'],
      '/worker/tasks' => ['WorkerController', 'tasks'],
      '/worker/recipes' => ['WorkerController', 'recipes'],
      '/worker/profile' => ['WorkerController', 'profile'],
    ];

    if (!isset($routes[$path])) {
      http_response_code(404);
      echo "404 - Ruta no encontrada";
      return;
    }

    [$controller, $method] = $routes[$path];

    $sessionUser = Auth::user();
    if ($sessionUser && (str_starts_with($path, '/admin') || str_starts_with($path, '/worker'))) {
      require_once __DIR__ . '/../models/Security.php';
      if (!Security::canAccessPath((int)$sessionUser['id'], $path)) {
        Security::log((int)$sessionUser['id'], 'access_denied', $path, null, 'Permiso insuficiente');
        http_response_code(403);
        echo '403 - No tienes permiso para acceder a este módulo';
        return;
      }
      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        Security::logModuleAccess((int)$sessionUser['id'], $path);
      }
    }

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
