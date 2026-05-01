<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/WorkArea.php';
require_once __DIR__ . '/../models/PurchaseArea.php';
require_once __DIR__ . '/../models/Requirement.php';
require_once __DIR__ . '/../models/Activity.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/WorkerPayRate.php';
require_once __DIR__ . '/../models/Promotion.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/ProductCategory.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Recipe.php';
require_once __DIR__ . '/../models/MonthlyProductSale.php';
require_once __DIR__ . '/../models/SalesImportAudit.php';
require_once __DIR__ . '/../models/MailNotificationLog.php';
require_once __DIR__ . '/../models/LeadDinnerStatus.php';
require_once __DIR__ . '/../models/LeadDinnerEntry.php';
require_once __DIR__ . '/../core/XlsxReader.php';

class AdminController extends Controller
{
  private function isJsonRequest(): bool
  {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
  }

  private function jsonResponse(array $payload, int $statusCode = 200): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function spreadsheetRowsToAssoc(array $rows): array
  {
    if (count($rows) < 2) {
      return [];
    }

    $headers = array_map(static fn($value) => trim((string)$value), $rows[0]);
    $items = [];

    for ($i = 1; $i < count($rows); $i++) {
      $row = $rows[$i];
      $assoc = [];

      foreach ($headers as $index => $header) {
        if ($header === '') {
          continue;
        }
        $assoc[$header] = isset($row[$index]) ? trim((string)$row[$index]) : '';
      }

      if (implode('', $assoc) !== '') {
        $items[] = $assoc;
      }
    }

    return $items;
  }

  private function uploadedXlsxPath(string $fieldName): array
  {
    $file = $_FILES[$fieldName] ?? null;
    if (!$file || !is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Debes subir un archivo .xlsx valido.');
    }

    $name = (string)($file['name'] ?? '');
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
      throw new RuntimeException('Solo se permiten archivos .xlsx.');
    }

    return [
      'tmp_name' => (string)$file['tmp_name'],
      'name' => $name,
    ];
  }

  private function resolveSalesPeriodMonth(string $fileName, ?string $rawMonth): string
  {
    $rawMonth = trim((string)$rawMonth);
    if ($rawMonth !== '') {
      $date = DateTime::createFromFormat('Y-m', $rawMonth);
      if (!$date) {
        throw new RuntimeException('El mes seleccionado no es valido.');
      }
      return $date->format('Y-m-01');
    }

    if (preg_match_all('/(\d{2})_(\d{2})_(\d{4})/', $fileName, $matches, PREG_SET_ORDER) >= 1) {
      $first = DateTime::createFromFormat('d_m_Y', $matches[0][0]);
      $last = count($matches) > 1 ? DateTime::createFromFormat('d_m_Y', $matches[count($matches) - 1][0]) : $first;

      if (!$first || !$last) {
        throw new RuntimeException('No se pudo detectar el mes del archivo.');
      }

      if ($first->format('Y-m') !== $last->format('Y-m')) {
        throw new RuntimeException('El archivo debe corresponder a un solo mes.');
      }

      return $first->format('Y-m-01');
    }

    throw new RuntimeException('Selecciona el mes de ventas a importar.');
  }

  private function importInventoryCatalog(array $upload): int
  {
    $rows = $this->spreadsheetRowsToAssoc(XlsxReader::rows($upload['tmp_name']));
    $count = 0;

    foreach ($rows as $row) {
      if (trim((string)($row['PRODUCTO'] ?? '')) === '') {
        continue;
      }
      Product::upsertFromInventoryRow($row);
      $count++;
    }

    return $count;
  }

  private function importMonthlySales(array $upload, ?string $rawMonth): array
  {
    $rows = $this->spreadsheetRowsToAssoc(XlsxReader::rows($upload['tmp_name']));
    $periodMonth = $this->resolveSalesPeriodMonth($upload['name'], $rawMonth);
    $result = MonthlyProductSale::replaceMonthFromRows($periodMonth, $rows, $upload['name']);

    return [
      'count' => $result['count'],
      'period_month' => $periodMonth,
      'audit_id' => $result['audit_id'],
      'issues_count' => $result['issues_count'],
      'raw_total_amount' => $result['raw_total_amount'],
      'normalized_total_amount' => $result['normalized_total_amount'],
    ];
  }

  private function calculateMinutesLate(int $userId, string $type, DateTime $markedAt): int
  {
    if ($type !== 'in') {
      return 0;
    }

    $user = User::find($userId);
    if (!$user || empty($user['shift_id'])) {
      return 0;
    }

    $shift = Shift::find((int)$user['shift_id']);
    if (!$shift) {
      return 0;
    }

    $start = DateTime::createFromFormat('H:i:s', $shift['start_time']);
    if (!$start) {
      return 0;
    }

    $start->setDate((int)$markedAt->format('Y'), (int)$markedAt->format('m'), (int)$markedAt->format('d'));
    if ($markedAt <= $start) {
      return 0;
    }

    $diff = $start->diff($markedAt);
    return ($diff->h * 60) + $diff->i;
  }

  public function dashboard(): void
  {
    Auth::requireRole('admin');
    $workersCount = User::countWorkers();
    $latest = Attendance::latest(12);
    $this->view('admin/dashboard', compact('workersCount', 'latest'));
  }

  public function profile(): void
  {
    Auth::requireRole('admin');
    $user = Auth::user();
    $this->view('admin/profile', compact('user'));
  }

  public function workers(): void
  {
    Auth::requireRole('admin');

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          $newId = User::createWorker([
            'document_type' => $_POST['document_type'] ?? 'dni',
            'document_number' => trim($_POST['document_number'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'shift_id' => (int)($_POST['shift_id'] ?? 0),
            'area_id' => (int)($_POST['area_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password' => $_POST['password'] ?? '123456'
          ]);
          // Pago diario (opcional)
          $dailyRate = isset($_POST['daily_rate']) && $_POST['daily_rate'] !== '' ? (float)$_POST['daily_rate'] : null;
          WorkerPayRate::upsert((int)$newId, $dailyRate);

          $msg = ['type' => 'success', 'text' => 'Trabajador creado'];
        }

        if ($action === 'update') {
          User::updateWorker((int)$_POST['id'], [
            'document_type' => $_POST['document_type'] ?? 'dni',
            'document_number' => trim($_POST['document_number'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'shift_id' => (int)($_POST['shift_id'] ?? 0),
            'area_id' => (int)($_POST['area_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password' => $_POST['password'] ?? ''
          ]);
          $dailyRate = isset($_POST['daily_rate']) && $_POST['daily_rate'] !== '' ? (float)$_POST['daily_rate'] : null;
          WorkerPayRate::upsert((int)$_POST['id'], $dailyRate);

          $msg = ['type' => 'success', 'text' => 'Trabajador actualizado'];
        }

        if ($action === 'delete') {
          WorkerPayRate::upsert((int)$_POST['id'], null);
          User::deleteWorker((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Trabajador eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::allWorkers();
    $shifts = Shift::all();
    $areas = WorkArea::all();
    $this->view('admin/workers', compact('workers', 'shifts', 'areas', 'msg'));
  }

  public function shifts(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Shift::create(trim($_POST['name']), $_POST['start_time'], $_POST['end_time']);
          $msg = ['type' => 'success', 'text' => 'Turno creado'];
        }
        if ($action === 'update') {
          Shift::update((int)$_POST['id'], trim($_POST['name']), $_POST['start_time'], $_POST['end_time']);
          $msg = ['type' => 'success', 'text' => 'Turno actualizado'];
        }
        if ($action === 'delete') {
          Shift::delete((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Turno eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $shifts = Shift::all();
    $this->view('admin/shifts', compact('shifts', 'msg'));
  }


  public function areas(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          WorkArea::create(trim($_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Área creada'];
        }
        if ($action === 'update') {
          WorkArea::update((int)$_POST['id'], trim($_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Área actualizada'];
        }
        if ($action === 'delete') {
          WorkArea::delete((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Área eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areas = WorkArea::all();
    $this->view('admin/areas', compact('areas', 'msg'));
  }

  public function purchaseAreas(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          PurchaseArea::create(trim((string)$_POST['name']), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Area de compra creada'];
        }
        if ($action === 'update') {
          PurchaseArea::update((int)$_POST['id'], trim((string)$_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Area de compra actualizada'];
        }
        if ($action === 'activate') {
          PurchaseArea::setActive((int)$_POST['id'], 1);
          $msg = ['type' => 'success', 'text' => 'Area de compra activada'];
        }
        if ($action === 'deactivate') {
          PurchaseArea::setActive((int)$_POST['id'], 0);
          $msg = ['type' => 'warning', 'text' => 'Area de compra desactivada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areas = PurchaseArea::all();
    $this->view('admin/purchase_areas', compact('areas', 'msg'));
  }

  public function requirements(): void
  {
    Auth::requireRole('admin');
    $msg = null;
    $selectedWeekStart = Requirement::normalizeWeekStart($_GET['week_start'] ?? null);
    $expectsJson = $this->isJsonRequest();

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'toggle_item') {
          $isPurchased = isset($_POST['is_purchased']) ? 1 : 0;
          Requirement::setPurchased(
            (int)($_POST['item_id'] ?? 0),
            $isPurchased
          );

          if ($expectsJson) {
            $this->jsonResponse([
              'ok' => true,
              'message' => 'Estado de compra actualizado',
              'item' => [
                'id' => (int)($_POST['item_id'] ?? 0),
                'is_purchased' => $isPurchased,
                'status_text' => $isPurchased === 1 ? 'Comprado' : 'Pendiente',
                'status_class' => $isPurchased === 1 ? 'success' : 'secondary',
              ],
            ]);
          }

          $msg = ['type' => 'success', 'text' => 'Estado de compra actualizado'];
        }

        if ($action === 'delete_item') {
          Requirement::deleteItem((int)($_POST['item_id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Item eliminado del requerimiento'];
        }
      } catch (Throwable $e) {
        if ($expectsJson) {
          $this->jsonResponse([
            'ok' => false,
            'message' => 'Error: ' . $e->getMessage(),
          ], 422);
        }

        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $week = Requirement::weekRangeForDate($selectedWeekStart);
    $rows = Requirement::forAdminWeek($week['from']);
    $weekOptions = Requirement::weekOptions(8);
    $mailLogs = array_slice($this->requirementMailLogs(), 0, 10);
    $grouped = [];

    foreach ($rows as $row) {
      $workerKey = (int)$row['user_id'];
      if (!isset($grouped[$workerKey])) {
        $grouped[$workerKey] = [
          'worker_name' => trim($row['first_name'] . ' ' . $row['last_name']),
          'areas' => []
        ];
      }

      $areaKey = $row['required_date'] . '|' . $row['purchase_area_name'];
      if (!isset($grouped[$workerKey]['areas'][$areaKey])) {
        $grouped[$workerKey]['areas'][$areaKey] = [
          'required_date' => $row['required_date'],
          'purchase_area_name' => $row['purchase_area_name'],
          'status' => $row['status'] ?? 'submitted',
          'items' => []
        ];
      }

      $grouped[$workerKey]['areas'][$areaKey]['items'][] = $row;
    }

    $this->view('admin/requirements', compact('msg', 'week', 'grouped', 'selectedWeekStart', 'weekOptions', 'mailLogs'));
  }

  private function requirementMailLogs(): array
  {
    MailNotificationLog::ensureSchema();
    $pdo = Database::conn();
    return $pdo->query("
      SELECT *
      FROM mail_notification_logs
      WHERE notification_type='requirement_created'
      ORDER BY created_at DESC, id DESC
      LIMIT 20
    ")->fetchAll();
  }

  public function activities(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Activity::createAssignment(
            (int)($_POST['user_id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Actividad creada'];
        }

        if ($action === 'update') {
          Activity::updateAssignment(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['user_id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Actividad actualizada'];
        }

        if ($action === 'delete') {
          Activity::deleteAssignment((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Actividad eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::allWorkers();
    $assignments = Activity::assignedAll();
    $week = Activity::weekRangeForDate();
    $rows = Activity::performedForAdminWeek($week['from']);
    $board = [];

    foreach ($rows as $row) {
      $workerKey = (int)$row['user_id'];
      if (!isset($board[$workerKey])) {
        $board[$workerKey] = [
          'worker_name' => trim($row['first_name'] . ' ' . $row['last_name']),
          'days' => []
        ];
      }

      $board[$workerKey]['days'][$row['activity_date']][] = $row['activity_name'];
    }

    $this->view('admin/activities', compact('msg', 'workers', 'assignments', 'week', 'board'));
  }

  public function tasks(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create_task') {
          Task::createTask(trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Tarea creada'];
        }

        if ($action === 'update_task') {
          Task::updateTask(
            (int)($_POST['id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Tarea actualizada'];
        }

        if ($action === 'delete_task') {
          Task::deleteTask((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Tarea eliminada'];
        }

        if ($action === 'create_assignment') {
          Task::createAssignment(
            (int)($_POST['user_id'] ?? 0),
            (int)($_POST['task_id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Asignacion creada'];
        }

        if ($action === 'update_assignment') {
          Task::updateAssignment(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['user_id'] ?? 0),
            (int)($_POST['task_id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Asignacion actualizada'];
        }

        if ($action === 'delete_assignment') {
          Task::deleteAssignment((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Asignacion eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::allWorkers();
    $tasks = Task::catalogAll();
    $assignments = Task::assignmentsAll();
    $board = Task::weeklyBoard();

    $this->view('admin/tasks', compact('msg', 'workers', 'tasks', 'assignments', 'board'));
  }


  public function promotions(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Promotion::create(
            (int)($_POST['weekday'] ?? 0),
            (string)($_POST['shift'] ?? 'morning'),
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['content'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Promoción creada'];
        }

        if ($action === 'update') {
          Promotion::update(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            (string)($_POST['shift'] ?? 'morning'),
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['content'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Promoción actualizada'];
        }

        if ($action === 'delete') {
          Promotion::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Promoción eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $promos = Promotion::all();
    $this->view('admin/promotions', compact('promos', 'msg'));
  }

  public function attendance(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        $userId = (int)($_POST['user_id'] ?? 0);
        $type = (string)($_POST['mark_type'] ?? '');
        $markedAtRaw = trim((string)($_POST['marked_at'] ?? ''));
        $markedAt = DateTime::createFromFormat('Y-m-d\TH:i', $markedAtRaw);
        if (!$markedAt) {
          $markedAt = DateTime::createFromFormat('Y-m-d H:i:s', $markedAtRaw);
        }

        if ($action !== 'delete') {
          $user = User::find($userId);
          if (!$user || ($user['role'] ?? '') !== 'worker') {
            throw new RuntimeException('Trabajador no válido');
          }
          if (!in_array($type, ['in', 'out'], true)) {
            throw new RuntimeException('Tipo de marcación inválido');
          }
          if (!$markedAt) {
            throw new RuntimeException('Fecha y hora inválidas');
          }
        }

        if ($action === 'create') {
          $late = $this->calculateMinutesLate($userId, $type, $markedAt);
          $ip = trim((string)($_POST['ip_address'] ?? ''));
          $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
          $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
          $ua = trim((string)($_POST['user_agent'] ?? ''));

          Attendance::create(
            $userId,
            $type,
            $markedAt->format('Y-m-d H:i:s'),
            $late,
            $ip !== '' ? $ip : null,
            $lat,
            $lng,
            $ua !== '' ? $ua : null
          );
          $msg = ['type' => 'success', 'text' => 'Asistencia registrada'];
        }

        if ($action === 'update') {
          $id = (int)($_POST['id'] ?? 0);
          $late = $this->calculateMinutesLate($userId, $type, $markedAt);
          $ip = trim((string)($_POST['ip_address'] ?? ''));
          $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
          $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
          $ua = trim((string)($_POST['user_agent'] ?? ''));

          Attendance::update(
            $id,
            $userId,
            $type,
            $markedAt->format('Y-m-d H:i:s'),
            $late,
            $ip !== '' ? $ip : null,
            $lat,
            $lng,
            $ua !== '' ? $ua : null
          );
          $msg = ['type' => 'success', 'text' => 'Asistencia actualizada'];
        }

        if ($action === 'delete') {
          Attendance::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Asistencia eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $doc = trim($_GET['doc'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');

    $rows = Attendance::filter($doc ?: null, $from ?: null, $to ?: null);
    $workers = User::allWorkers();
    $this->view('admin/attendance', compact('rows', 'doc', 'from', 'to', 'workers', 'msg'));
  }

  public function inventory(): void
  {
    Auth::requireRole('admin');

    $areaId = (int)($_GET['area_id'] ?? 0);
    $status = $_GET['status'] ?? '';

    $statusFilter = null;
    if ($status === 'active') {
      $statusFilter = 1;
    } elseif ($status === 'inactive') {
      $statusFilter = 0;
    }

    $areas = WorkArea::all();
    $rows = InventoryItem::forAdmin($areaId > 0 ? $areaId : null, $statusFilter);

    $grouped = [];
    foreach ($rows as $row) {
      $grouped[$row['area_name']][] = $row;
    }

    $this->view('admin/inventory', compact('areas', 'rows', 'grouped', 'areaId', 'status'));
  }

  public function products(): void
  {
    Auth::requireRole('admin');
    Product::ensureSchema();
    MonthlyProductSale::ensureSchema();

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create_category') {
          ProductCategory::create(trim((string)($_POST['name'] ?? '')));
          $msg = ['type' => 'success', 'text' => 'Categoria creada'];
        }

        if ($action === 'update_category') {
          ProductCategory::update((int)($_POST['id'] ?? 0), trim((string)($_POST['name'] ?? '')));
          $msg = ['type' => 'success', 'text' => 'Categoria actualizada'];
        }

        if ($action === 'delete_category') {
          ProductCategory::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Categoria eliminada'];
        }

        if ($action === 'create_product') {
          Product::create([
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'name' => trim((string)($_POST['name'] ?? '')),
            'variant' => trim((string)($_POST['variant'] ?? '')),
            'brand' => trim((string)($_POST['brand'] ?? '')),
            'internal_code' => trim((string)($_POST['internal_code'] ?? '')),
            'manufacturer_code' => trim((string)($_POST['manufacturer_code'] ?? '')),
            'unit_price' => $_POST['unit_price'] ?? null,
            'cost_price' => $_POST['cost_price'] ?? null,
            'stock_quantity' => $_POST['stock_quantity'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
          ]);
          $msg = ['type' => 'success', 'text' => 'Producto creado'];
        }

        if ($action === 'update_product') {
          Product::update((int)($_POST['id'] ?? 0), [
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'name' => trim((string)($_POST['name'] ?? '')),
            'variant' => trim((string)($_POST['variant'] ?? '')),
            'brand' => trim((string)($_POST['brand'] ?? '')),
            'internal_code' => trim((string)($_POST['internal_code'] ?? '')),
            'manufacturer_code' => trim((string)($_POST['manufacturer_code'] ?? '')),
            'unit_price' => $_POST['unit_price'] ?? null,
            'cost_price' => $_POST['cost_price'] ?? null,
            'stock_quantity' => $_POST['stock_quantity'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
          ]);
          $msg = ['type' => 'success', 'text' => 'Producto actualizado'];
        }

        if ($action === 'update_price') {
          $price = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null;
          Product::updatePrice((int)($_POST['id'] ?? 0), $price);
          $msg = ['type' => 'success', 'text' => 'Precio actualizado'];
        }

        if ($action === 'delete_product') {
          Product::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Producto eliminado'];
        }

        if ($action === 'import_inventory') {
          $upload = $this->uploadedXlsxPath('inventory_file');
          $count = $this->importInventoryCatalog($upload);
          $msg = ['type' => 'success', 'text' => 'Catalogo importado: ' . $count . ' producto(s) procesados'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $search = trim((string)($_GET['q'] ?? ''));

    $summary = Product::summary();
    $categories = ProductCategory::all();
    $grouped = Product::groupedByCategory($categoryId > 0 ? $categoryId : null, $search);
    $rows = Product::byCategory($categoryId > 0 ? $categoryId : null, $search);

    $this->view('admin/products', compact('msg', 'summary', 'categories', 'grouped', 'rows', 'categoryId', 'search'));
  }

  public function recipes(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'update') {
          Recipe::updateByAdmin(
            (int)($_POST['id'] ?? 0),
            (string)($_POST['area_type'] ?? ''),
            (string)($_POST['title'] ?? ''),
            (array)($_POST['ingredients'] ?? []),
            (string)($_POST['preparation'] ?? ''),
            isset($_POST['approved']) ? 'approved' : 'pending'
          );
          $msg = ['type' => 'success', 'text' => 'Receta actualizada'];
        }

        if ($action === 'delete') {
          Recipe::deleteByAdmin((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Receta eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areaType = $_GET['area_type'] ?? '';
    $status = $_GET['status'] ?? '';
    $recipes = Recipe::allForAdmin($areaType, $status);
    $this->view('admin/recipes', compact('msg', 'recipes', 'areaType', 'status'));
  }

  public function sales(): void
  {
    Auth::requireRole('admin');
    MonthlyProductSale::ensureSchema();

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'import_sales') {
          $upload = $this->uploadedXlsxPath('sales_file');
          $result = $this->importMonthlySales($upload, $_POST['sales_month'] ?? null);
          $msg = [
            'type' => 'success',
            'text' => 'Ventas importadas: ' . $result['count'] . ' producto(s) para ' . date('m/Y', strtotime($result['period_month'])) .
              '. Monto origen: S/ ' . number_format((float)$result['raw_total_amount'], 2) .
              '. Monto normalizado: S/ ' . number_format((float)$result['normalized_total_amount'], 2) .
              '. Incidencias auditadas: ' . (int)$result['issues_count']
          ];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $months = MonthlyProductSale::availableMonths();
    $selectedMonth = trim((string)($_GET['month'] ?? ''));
    if ($selectedMonth === '' && !empty($months)) {
      $selectedMonth = date('Y-m', strtotime($months[0]['period_month']));
    }

    $periodMonth = null;
    if ($selectedMonth !== '') {
      $date = DateTime::createFromFormat('Y-m', $selectedMonth);
      if ($date) {
        $periodMonth = $date->format('Y-m-01');
      }
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $categories = ProductCategory::all();
    $overview = $periodMonth ? MonthlyProductSale::overview($periodMonth, $categoryId > 0 ? $categoryId : null) : [];
    $topProducts = $periodMonth ? MonthlyProductSale::topProducts($periodMonth, null, 20) : [];
    $topByCategory = $periodMonth ? MonthlyProductSale::topProducts($periodMonth, $categoryId > 0 ? $categoryId : null, 20) : [];
    $categoryBreakdown = $periodMonth ? MonthlyProductSale::byCategory($periodMonth) : [];
    $latestAudit = $periodMonth ? SalesImportAudit::latestForMonth($periodMonth) : null;
    $auditIssues = $latestAudit ? SalesImportAudit::issues((int)$latestAudit['id'], 50) : [];
    $recentAudits = SalesImportAudit::recent($periodMonth, 10);

    $this->view('admin/sales', compact(
      'msg',
      'months',
      'categories',
      'selectedMonth',
      'periodMonth',
      'categoryId',
      'overview',
      'topProducts',
      'topByCategory',
      'categoryBreakdown',
      'latestAudit',
      'auditIssues',
      'recentAudits'
    ));
  }

  public function leadDinnerStatuses(): void
  {
    Auth::requireRole('admin');
    LeadDinnerStatus::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          LeadDinnerStatus::create(trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Estado creado'];
        }

        if ($action === 'update') {
          LeadDinnerStatus::update((int)($_POST['id'] ?? 0), trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Estado actualizado'];
        }

        if ($action === 'delete') {
          LeadDinnerStatus::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Estado eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $statuses = LeadDinnerStatus::all();
    $this->view('admin/lead_dinner_statuses', compact('statuses', 'msg'));
  }

  public function leadDinnerEntries(): void
  {
    Auth::requireRole('admin');
    LeadDinnerEntry::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'update_status') {
          LeadDinnerEntry::updateStatus((int)($_POST['id'] ?? 0), (int)($_POST['status_id'] ?? 0));
          $msg = ['type' => 'success', 'text' => 'Estado del lead actualizado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $statusId = (int)($_GET['status_id'] ?? 0);
    $search = trim((string)($_GET['q'] ?? ''));
    $statuses = LeadDinnerStatus::all();
    $rows = LeadDinnerEntry::all($statusId > 0 ? $statusId : null, $search);

    $this->view('admin/lead_dinner_entries', compact('rows', 'statuses', 'statusId', 'search', 'msg'));
  }
}
