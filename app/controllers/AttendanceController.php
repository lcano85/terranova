<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
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

        $user = User::findByDoc($docType, $docNumber);
        if (!$user || ($user['role'] ?? '') !== 'worker') {
          throw new RuntimeException('Trabajador no encontrado');
        }

        $markedAt = new DateTime();
        $late = $this->calculateMinutesLate((int)$user['id'], $type, $markedAt);
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

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

        $msg = ['type' => 'success', 'text' => 'Marcacion registrada correctamente'];
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $this->view('attendance/mark', compact('msg'));
  }
}
