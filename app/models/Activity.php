<?php
require_once __DIR__ . '/../core/Database.php';

class Activity
{
  public static function weekRangeForDate(?string $date = null): array
  {
    $base = $date ? new DateTime($date) : new DateTime();
    $start = clone $base;
    $start->modify('monday this week');
    $end = clone $start;
    $end->modify('+5 days');

    return [
      'from' => $start->format('Y-m-d'),
      'to' => $end->format('Y-m-d')
    ];
  }

  public static function assignedAll(): array
  {
    $sql = "
      SELECT aa.*, u.first_name, u.last_name, u.document_number
      FROM activity_assignments aa
      JOIN users u ON u.id = aa.user_id
      WHERE u.role='worker'
      ORDER BY u.first_name ASC, u.last_name ASC, aa.name ASC
    ";
    return Database::conn()->query($sql)->fetchAll();
  }

  public static function assignedByWorker(int $userId, ?int $onlyActive = 1): array
  {
    $sql = "SELECT * FROM activity_assignments WHERE user_id=?";
    $params = [$userId];

    if ($onlyActive !== null) {
      $sql .= " AND is_active=?";
      $params[] = $onlyActive;
    }

    $sql .= " ORDER BY name ASC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function createAssignment(int $userId, string $name, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("
      INSERT INTO activity_assignments (user_id, name, is_active)
      VALUES (?,?,?)
    ");
    $st->execute([$userId, $name, $isActive]);
  }

  public static function updateAssignment(int $id, int $userId, string $name, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("
      UPDATE activity_assignments
      SET user_id=?, name=?, is_active=?, updated_at=NOW()
      WHERE id=?
    ");
    $st->execute([$userId, $name, $isActive, $id]);
  }

  public static function deleteAssignment(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM activity_assignments WHERE id=?");
    $st->execute([$id]);
  }

  public static function saveDailyActivities(int $userId, string $activityDate, array $assignmentIds): void
  {
    $pdo = Database::conn();
    $week = self::weekRangeForDate($activityDate);
    $day = new DateTime($activityDate);
    $weekday = (int)$day->format('N');
    if ($weekday < 1 || $weekday > 6) {
      throw new RuntimeException('Solo se pueden registrar actividades de lunes a sabado');
    }

    $assignmentIds = array_values(array_unique(array_map('intval', $assignmentIds)));
    if (empty($assignmentIds)) {
      throw new RuntimeException('Debes seleccionar al menos una actividad');
    }

    $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
    $checkSql = "
      SELECT id, name
      FROM activity_assignments
      WHERE user_id=?
        AND is_active=1
        AND id IN ($placeholders)
    ";
    $checkParams = array_merge([$userId], $assignmentIds);
    $checkSt = $pdo->prepare($checkSql);
    $checkSt->execute($checkParams);
    $assignments = $checkSt->fetchAll();

    if (count($assignments) !== count($assignmentIds)) {
      throw new RuntimeException('Hay actividades no validas en la seleccion');
    }

    $pdo->beginTransaction();

    try {
      $deleteSt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id=? AND activity_date=?");
      $deleteSt->execute([$userId, $activityDate]);

      $insertSt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, activity_assignment_id, activity_name, activity_date, week_start, weekday)
        VALUES (?,?,?,?,?,?)
      ");

      foreach ($assignments as $assignment) {
        $insertSt->execute([
          $userId,
          (int)$assignment['id'],
          $assignment['name'],
          $activityDate,
          $week['from'],
          $weekday
        ]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function performedByWorkerWeek(int $userId, string $weekStart): array
  {
    $st = Database::conn()->prepare("
      SELECT *
      FROM activity_logs
      WHERE user_id=?
        AND week_start=?
      ORDER BY activity_date ASC, id ASC
    ");
    $st->execute([$userId, $weekStart]);
    return $st->fetchAll();
  }

  public static function performedForAdminWeek(string $weekStart): array
  {
    $st = Database::conn()->prepare("
      SELECT al.*, u.first_name, u.last_name
      FROM activity_logs al
      JOIN users u ON u.id = al.user_id
      WHERE al.week_start=?
        AND u.role='worker'
      ORDER BY u.first_name ASC, u.last_name ASC, al.activity_date ASC, al.id ASC
    ");
    $st->execute([$weekStart]);
    return $st->fetchAll();
  }
}
