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
require_once __DIR__ . '/../models/WorkerPayRate.php';
require_once __DIR__ . '/../models/Promotion.php';
require_once __DIR__ . '/../models/InventoryItem.php';

class AdminController extends Controller
{
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

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'toggle_item') {
          Requirement::setPurchased(
            (int)($_POST['item_id'] ?? 0),
            isset($_POST['is_purchased']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Estado de compra actualizado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $week = Requirement::weekRangeForDate();
    $rows = Requirement::forAdminWeek($week['from']);
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
          'items' => []
        ];
      }

      $grouped[$workerKey]['areas'][$areaKey]['items'][] = $row;
    }

    $this->view('admin/requirements', compact('msg', 'week', 'grouped'));
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
}
