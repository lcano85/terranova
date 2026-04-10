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
require_once __DIR__ . '/../models/Activity.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/MailNotificationLog.php';
require_once __DIR__ . '/../core/NotificationMailer.php';

class WorkerController extends Controller {
  private function notifyAdminsAboutRequirement(int $requirementId): void
  {
    $recipients = NotificationMailer::adminRecipients();
    if (empty($recipients)) {
      throw new RuntimeException('No hay correos de administracion configurados en app/config/mail.php');
    }

    $detail = Requirement::detailForNotification($requirementId);
    if (!$detail) {
      throw new RuntimeException('No se pudo obtener el detalle del requerimiento para el correo.');
    }

    $weeklyDetail = Requirement::weeklyDetailForNotification((int)$detail['user_id'], (string)$detail['week_start']);
    if (empty($weeklyDetail)) {
      throw new RuntimeException('No se pudo obtener el bloque semanal del requerimiento para el correo.');
    }

    $subject = 'Requerimientos semanales registrados - ' . $weeklyDetail['worker_name'];
    $groupsHtml = '';
    $groupsText = '';
    foreach ($weeklyDetail['groups'] as $group) {
      $itemsHtml = '';
      $itemsText = '';
      foreach ($group['items'] as $item) {
        $itemsHtml .= '<li>' . Helpers::e((string)$item) . '</li>';
        $itemsText .= '  - ' . $item . "\n";
      }

      $groupsHtml .= '
        <div style="margin-bottom:16px;">
          <p><strong>Area de compra:</strong> ' . Helpers::e($group['purchase_area_name']) . '<br>
          <strong>Fecha solicitada:</strong> ' . Helpers::e(date('d/m/Y', strtotime($group['required_date']))) . '</p>
          <ul>' . $itemsHtml . '</ul>
        </div>
      ';

      $groupsText .=
        'Area de compra: ' . $group['purchase_area_name'] . "\n" .
        'Fecha solicitada: ' . date('d/m/Y', strtotime($group['required_date'])) . "\n" .
        $itemsText . "\n";
    }

    $htmlBody = '
      <h2>Nuevo requerimiento registrado</h2>
      <p><strong>Trabajador:</strong> ' . Helpers::e($weeklyDetail['worker_name']) . '</p>
      <p><strong>Documento:</strong> ' . Helpers::e($weeklyDetail['document_number']) . '</p>
      <p><strong>Area del trabajador:</strong> ' . Helpers::e((string)($weeklyDetail['worker_area_name'] ?? '-')) . '</p>
      <p><strong>Semana:</strong> ' . Helpers::e(date('d/m/Y', strtotime($weeklyDetail['week_start']))) . '</p>
      <p><strong>Bloque semanal registrado:</strong></p>
      ' . $groupsHtml . '
    ';

    $textBody =
      "Nuevo requerimiento registrado\n\n" .
      'Trabajador: ' . $weeklyDetail['worker_name'] . "\n" .
      'Documento: ' . $weeklyDetail['document_number'] . "\n" .
      'Area del trabajador: ' . ($weeklyDetail['worker_area_name'] ?: '-') . "\n" .
      'Semana: ' . date('d/m/Y', strtotime($weeklyDetail['week_start'])) . "\n\n" .
      "Bloque semanal registrado:\n" .
      $groupsText;

    $logId = MailNotificationLog::create('requirement_created', $recipients, $subject, $requirementId);

    try {
      NotificationMailer::send($recipients, $subject, $htmlBody, $textBody);
      MailNotificationLog::markSent($logId);
    } catch (Throwable $e) {
      MailNotificationLog::markFailed($logId, $e->getMessage());
      throw $e;
    }
  }

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
      $action = $_POST['action'] ?? 'save_draft';

      try {
        if ($action === 'delete_item') {
          Requirement::deleteItem((int)($_POST['item_id'] ?? 0), (int)$user['id'], true);
          $msg = ['type' => 'success', 'text' => 'Item eliminado del borrador'];
        }

        if ($action === 'submit_saved') {
          $week = Requirement::weekRangeForDate();
          $requirementId = Requirement::submitWorkerWeek((int)$user['id'], $week['from']);
          if ($requirementId === null) {
            throw new RuntimeException('No tienes requerimientos guardados para enviar.');
          }

          try {
            $this->notifyAdminsAboutRequirement($requirementId);
            $msg = ['type' => 'success', 'text' => 'Requerimiento enviado y correo notificado al administrador'];
          } catch (Throwable $mailError) {
            $msg = ['type' => 'warning', 'text' => 'Requerimiento enviado, pero el correo no se envio. El administrador puede revisar el log.'];
          }
        }

        if (in_array($action, ['save_draft', 'send'], true)) {
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

          if ($action === 'save_draft') {
            Requirement::create((int)$user['id'], $purchaseAreaId, $requiredDate, $items, 'draft');
            $msg = ['type' => 'success', 'text' => 'Requerimiento guardado como borrador. Puedes continuar el registro despues.'];
          }

          if ($action === 'send') {
            $requirementId = Requirement::create((int)$user['id'], $purchaseAreaId, $requiredDate, $items, 'submitted');
            $week = Requirement::weekRangeForDate($requiredDate);
            Requirement::submitWorkerWeek((int)$user['id'], $week['from']);

            try {
              $this->notifyAdminsAboutRequirement($requirementId);
              $msg = ['type' => 'success', 'text' => 'Requerimiento enviado y correo notificado al administrador'];
            } catch (Throwable $mailError) {
              $msg = ['type' => 'warning', 'text' => 'Requerimiento enviado, pero el correo no se envio. El administrador puede revisar el log.'];
            }
          }

        $defaultDate = $requiredDate;
        }
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
          'status' => $row['status'] ?? 'submitted',
          'items' => []
        ];
      }
      $grouped[$key]['items'][] = $row;
    }

    $this->view('worker/requirements', compact('user', 'purchaseAreas', 'defaultDate', 'msg', 'week', 'grouped'));
  }

  public function activities(): void {
    Auth::requireRole('worker');

    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;
    $msg = null;
    $today = date('Y-m-d');

    if (Helpers::isPost()) {
      Csrf::check();

      try {
        $activityDate = trim((string)($_POST['activity_date'] ?? ''));
        $assignmentIds = $_POST['activity_ids'] ?? [];

        if ($activityDate === '') {
          throw new RuntimeException('La fecha es obligatoria');
        }

        Activity::saveDailyActivities((int)$user['id'], $activityDate, (array)$assignmentIds);
        $msg = ['type' => 'success', 'text' => 'Actividades guardadas'];
        $today = $activityDate;
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $assignedActivities = Activity::assignedByWorker((int)$user['id'], 1);
    $week = Activity::weekRangeForDate();
    $rows = Activity::performedByWorkerWeek((int)$user['id'], $week['from']);
    $board = [];
    $selectedToday = [];

    foreach ($rows as $row) {
      $board[$row['activity_date']][] = $row['activity_name'];
      if ($row['activity_date'] === $today && !empty($row['activity_assignment_id'])) {
        $selectedToday[] = (int)$row['activity_assignment_id'];
      }
    }

    $this->view('worker/activities', compact(
      'user',
      'msg',
      'today',
      'assignedActivities',
      'week',
      'board',
      'selectedToday'
    ));
  }

  public function tasks(): void {
    Auth::requireRole('worker');

    $base = Auth::user();
    $user = User::findWithDetails((int)$base['id']) ?: $base;
    $board = Task::weeklyBoard();
    $this->view('worker/tasks', compact('user', 'board'));
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
