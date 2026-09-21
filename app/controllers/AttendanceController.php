<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/AttendanceLocation.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/Attendance.php';

class AttendanceController extends Controller
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

  public function mark(): void
  {
    $msg = null;
    $locationReference = require __DIR__ . '/../config/attendance_location.php';

    if (Helpers::isPost()) {
      try {
        Csrf::check();

        $docType = trim((string)($_POST['document_type'] ?? ''));
        $docNumber = trim((string)($_POST['document_number'] ?? ''));
        $type = trim((string)($_POST['mark_type'] ?? ''));

        if ($docType === '' || $docNumber === '') {
          throw new RuntimeException('Documento invalido');
        }
        if (!in_array($type, ['in', 'out'], true)) {
          throw new RuntimeException('Tipo de marcacion invalido');
        }

        [$lat, $lng] = AttendanceLocation::requireAllowed(
          $_POST['latitude'] ?? null,
          $_POST['longitude'] ?? null,
          $_POST['accuracy'] ?? null,
          $locationReference
        );

        $user = User::findByDoc($docType, $docNumber);
        if (!$user || ($user['role'] ?? '') !== 'worker') {
          throw new RuntimeException('Trabajador no encontrado');
        }

        $markedAt = new DateTime();
        $late = $this->calculateMinutesLate((int)$user['id'], $type, $markedAt);
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        Attendance::create(
          (int)$user['id'],
          $type,
          $markedAt->format('Y-m-d H:i:s'),
          $late,
          $ip !== '' ? $ip : null,
          $lat,
          $lng,
          $ua !== '' ? $ua : null
        );

        $msg = [
          'type' => 'success',
          'text' => 'Marcación de ' . ($type === 'in' ? 'entrada' : 'salida') . ' registrada correctamente',
          'worker' => trim($user['first_name'] . ' ' . $user['last_name']),
          'document_label' => $docType === 'dni' ? 'DNI' : 'Cédula',
          'document_number' => $user['document_number'],
        ];
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $this->view('attendance/mark', compact('msg', 'locationReference'));
  }
}
