<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/PurchaseArea.php';
require_once __DIR__ . '/../models/Requirement.php';

class WorkerController extends Controller {
  private function calculateShiftMinutes(array $user): int
  {
    if (!empty($user['start_time']) && !empty($user['end_time'])) {
      $start = DateTime::createFromFormat('H:i:s', $user['start_time']);
      $end = DateTime::createFromFormat('H:i:s', $user['end_time']);
      if ($start && $end) {
        $start->setDate(2000, 1, 1);
        $end->setDate(2000, 1, 1);
        if ($end <= $start) {
          $end->modify('+1 day');
        }

        $minutes = (int)(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($minutes > 0) {
          return $minutes;
        }
      }
    }

    return 540;
  }

  private function countWorkDays(string $from, string $to): int
  {
    $count = 0;
    $dt = new DateTime($from);
    $end = new DateTime($to);

    while ($dt <= $end) {
      if ((int)$dt->format('N') !== 7) {
        $count++;
      }
      $dt->modify('+1 day');
    }

    return $count;
  }

  private function listMissingWorkDays(string $from, string $to, array $workedDates): array
  {
    $workedMap = array_fill_keys($workedDates, true);
    $missing = [];
    $dt = new DateTime($from);
    $end = new DateTime($to);

    while ($dt <= $end) {
      $day = $dt->format('Y-m-d');
      if ((int)$dt->format('N') !== 7 && empty($workedMap[$day])) {
        $missing[] = $day;
      }
      $dt->modify('+1 day');
    }

    return $missing;
  }

  private function buildPaySummary(?float $dailyRate, int $shiftMinutes, int $lateMinutes, int $scheduledDays): array
  {
    if ($dailyRate === null || $dailyRate <= 0) {
      return [
        'gross' => null,
        'discount' => null,
        'net' => null
      ];
    }

    $gross = round($scheduledDays * $dailyRate, 2);
    $discountPerMin = $dailyRate / $shiftMinutes;
    $discount = round($discountPerMin * $lateMinutes, 2);

    return [
      'gross' => $gross,
      'discount' => $discount,
      'net' => round($gross - $discount, 2)
    ];
  }

  public function dashboard(): void {
    Auth::requireRole('worker');
    $user = Auth::user();
    $rows = Attendance::byWorker((int)$user['id'], 10);
    $this->view('worker/dashboard', compact('user','rows'));
  }

  public function profile(): void {
    Auth::requireRole('worker');

    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;

    $now = new DateTime();
    $from = $now->format('Y-m-01');
    $to = $now->format('Y-m-t');
    $today = $now->format('Y-m-d');
    $lateCutoff = $today < $to ? $today : $to;

    $summary = Attendance::monthlySummary(
      (int)$user['id'],
      $from,
      $to,
      $user['start_time'] ?? null,
      $user['end_time'] ?? null
    );

    $firstHalfFrom = $from;
    $firstHalfTo = $now->format('Y-m-15');
    $secondHalfFrom = $now->format('Y-m-16');
    $secondHalfTo = $to;

    $firstHalfSummary = Attendance::monthlySummary(
      (int)$user['id'],
      $firstHalfFrom,
      $firstHalfTo,
      $user['start_time'] ?? null,
      $user['end_time'] ?? null
    );
    $secondHalfSummary = Attendance::monthlySummary(
      (int)$user['id'],
      $secondHalfFrom,
      $secondHalfTo,
      $user['start_time'] ?? null,
      $user['end_time'] ?? null
    );

    $hours = round(((int)$summary['total_seconds']) / 3600, 2);
    $dailyRate = isset($user['daily_rate']) ? (float)$user['daily_rate'] : null;
    $shiftMinutes = $this->calculateShiftMinutes($user);

    $totalWorkDays = $this->countWorkDays($from, $to);
    $elapsedWorkDays = $this->countWorkDays($from, $lateCutoff);
    $missingDays = $this->listMissingWorkDays($from, $lateCutoff, $summary['worked_dates'] ?? []);
    $absent = count($missingDays);

    $workedDays = 0;
    foreach (($summary['worked_dates'] ?? []) as $workedDate) {
      $dt = new DateTime($workedDate);
      if ((int)$dt->format('N') !== 7) {
        $workedDays++;
      }
    }

    $firstHalfScheduledDays = $this->countWorkDays($firstHalfFrom, $firstHalfTo);
    $secondHalfScheduledDays = $this->countWorkDays($secondHalfFrom, $secondHalfTo);

    $firstHalfPay = $this->buildPaySummary(
      $dailyRate,
      $shiftMinutes,
      (int)($firstHalfSummary['total_minutes_late'] ?? 0),
      $firstHalfScheduledDays
    );
    $secondHalfPay = $this->buildPaySummary(
      $dailyRate,
      $shiftMinutes,
      (int)($secondHalfSummary['total_minutes_late'] ?? 0),
      $secondHalfScheduledDays
    );
    $monthPay = $this->buildPaySummary(
      $dailyRate,
      $shiftMinutes,
      (int)($summary['total_minutes_late'] ?? 0),
      $totalWorkDays
    );

    $this->view('worker/profile', compact(
      'user',
      'summary',
      'from',
      'to',
      'hours',
      'dailyRate',
      'absent',
      'workedDays',
      'totalWorkDays',
      'elapsedWorkDays',
      'missingDays',
      'firstHalfFrom',
      'firstHalfTo',
      'secondHalfFrom',
      'secondHalfTo',
      'firstHalfScheduledDays',
      'secondHalfScheduledDays',
      'firstHalfSummary',
      'secondHalfSummary',
      'firstHalfPay',
      'secondHalfPay',
      'monthPay'
    ));
  }

  public function myAttendance(): void {
    Auth::requireRole('worker');
    $user = Auth::user();
    $rows = Attendance::byWorker((int)$user['id'], 120);
    $this->view('worker/my_attendance', compact('user','rows'));
  }

  public function requirements(): void {
    Auth::requireRole('worker');

    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;
    $msg = null;
    $purchaseAreas = PurchaseArea::active();
    $defaultDate = Requirement::nextAllowedDate();

    if (Helpers::isPost()) {
      Csrf::check();

      try {
        $purchaseAreaId = (int)($_POST['purchase_area_id'] ?? 0);
        $requiredDate = trim((string)($_POST['required_date'] ?? ''));
        $itemsRaw = $_POST['items'] ?? [];
        $items = [];

        foreach ((array)$itemsRaw as $item) {
          $item = trim((string)$item);
          if ($item !== '') {
            $items[] = $item;
          }
        }

        if ($purchaseAreaId <= 0) {
          throw new RuntimeException('Debes seleccionar un area de compra');
        }
        if ($requiredDate === '' || !Requirement::isAllowedDate($requiredDate)) {
          throw new RuntimeException('La fecha solo puede ser jueves o sabado');
        }
        if (empty($items)) {
          throw new RuntimeException('Debes ingresar al menos un item');
        }

        Requirement::create((int)$user['id'], $purchaseAreaId, $requiredDate, $items);
        $msg = ['type' => 'success', 'text' => 'Requerimiento registrado'];
        $defaultDate = $requiredDate;
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $week = Requirement::weekRangeForDate();
    $rows = Requirement::forWorkerWeek((int)$user['id'], $week['from']);
    $grouped = [];

    foreach ($rows as $row) {
      $key = $row['required_date'] . '|' . $row['purchase_area_name'];
      if (!isset($grouped[$key])) {
        $grouped[$key] = [
          'required_date' => $row['required_date'],
          'purchase_area_name' => $row['purchase_area_name'],
          'items' => []
        ];
      }
      $grouped[$key]['items'][] = $row;
    }

    $this->view('worker/requirements', compact('user', 'purchaseAreas', 'defaultDate', 'msg', 'week', 'grouped'));
  }

  public function inventory(): void {
    Auth::requireRole('worker');

    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        $userId = (int)$user['id'];
        $areaId = (int)($user['area_id'] ?? 0);
        if ($areaId <= 0) {
          throw new RuntimeException('No tienes un area asignada');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unit = trim((string)($_POST['unit'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (in_array($action, ['create', 'update'], true)) {
          if ($name === '') {
            throw new RuntimeException('El nombre del item es obligatorio');
          }
          if ($quantity < 0) {
            throw new RuntimeException('La cantidad no puede ser negativa');
          }
          if ($unit === '') {
            throw new RuntimeException('La unidad es obligatoria');
          }
        }

        if ($action === 'create') {
          InventoryItem::create($userId, $areaId, $name, $quantity, $unit, $notes !== '' ? $notes : null);
          $msg = ['type' => 'success', 'text' => 'Item registrado'];
        }

        if ($action === 'update') {
          InventoryItem::updateByWorker(
            (int)($_POST['id'] ?? 0),
            $userId,
            $name,
            $quantity,
            $unit,
            $notes !== '' ? $notes : null
          );
          $msg = ['type' => 'success', 'text' => 'Item actualizado'];
        }

        if ($action === 'deactivate') {
          InventoryItem::setActiveByWorker((int)($_POST['id'] ?? 0), $userId, 0);
          $msg = ['type' => 'warning', 'text' => 'Item desactivado'];
        }

        if ($action === 'activate') {
          InventoryItem::setActiveByWorker((int)($_POST['id'] ?? 0), $userId, 1);
          $msg = ['type' => 'success', 'text' => 'Item activado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $rows = InventoryItem::byWorker((int)$user['id']);
    $this->view('worker/inventory', compact('user', 'rows', 'msg'));
  }
}
