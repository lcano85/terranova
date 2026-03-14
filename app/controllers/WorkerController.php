<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/InventoryItem.php';

class WorkerController extends Controller {
  public function dashboard(): void {
    Auth::requireRole('worker');
    $user = Auth::user();
    $rows = Attendance::byWorker((int)$user['id'], 10);
    $this->view('worker/dashboard', compact('user','rows'));
  }

  public function profile(): void {
    Auth::requireRole('worker');

    // Traer al usuario con detalles (turno, área, pago)
    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;

    // Rango dinámico: del día 1 al último día del mes actual
    $now = new DateTime();
    $from = $now->format('Y-m-01');
    $to = $now->format('Y-m-t');

    $summary = Attendance::monthlySummary((int)$user['id'], $from, $to);

    // Horas trabajadas
    $hours = round(((int)$summary['total_seconds']) / 3600, 2);

    // Cálculo de descuento por tardanza:
    // descuentoPorMin = pagoDiario / minutosTurno
    $dailyRate = isset($user['daily_rate']) ? (float)$user['daily_rate'] : null;

    $shiftMinutes = null;
    if (!empty($user['start_time']) && !empty($user['end_time'])) {
      $st = DateTime::createFromFormat('H:i:s', $user['start_time']);
      $en = DateTime::createFromFormat('H:i:s', $user['end_time']);
      if ($st && $en) {
        // Ajuste por si el turno cruza medianoche
        $baseDate = new DateTime('2000-01-01');
        $st->setDate(2000,1,1);
        $en->setDate(2000,1,1);
        if ($en <= $st) $en->modify('+1 day');
        $shiftMinutes = (int)(($en->getTimestamp() - $st->getTimestamp()) / 60);
      }
    }
    // Fallback al típico 7 a 4 (9h = 540 min)
    if (!$shiftMinutes || $shiftMinutes <= 0) $shiftMinutes = 540;

    $discount = 0.0;
    if ($dailyRate !== null && $dailyRate > 0) {
      $discountPerMin = $dailyRate / $shiftMinutes;
      $discount = round($discountPerMin * (int)$summary['total_minutes_late'], 2);
    }

    $gross = ($dailyRate !== null) ? round(((int)$summary['worked_days']) * $dailyRate, 2) : null;
    $net = ($gross !== null) ? round($gross - $discount, 2) : null;

    // Días ausentes: Lunes a Sábado del mes actual - días trabajados
    $totalWorkDays = 0;
    $dt = new DateTime($from);
    $end = new DateTime($to);
    while ($dt <= $end) {
      $dow = (int)$dt->format('N'); // 1=Mon..7=Sun
      if ($dow >= 1 && $dow <= 6) $totalWorkDays++;
      $dt->modify('+1 day');
    }
    $absent = max(0, $totalWorkDays - (int)$summary['worked_days']);

    $this->view('worker/profile', compact('user','summary','from','to','hours','discount','gross','net','absent','totalWorkDays','dailyRate'));
  }

  public function myAttendance(): void {
    Auth::requireRole('worker');
    $user = Auth::user();
    $rows = Attendance::byWorker((int)$user['id'], 120);
    $this->view('worker/my_attendance', compact('user','rows'));
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
