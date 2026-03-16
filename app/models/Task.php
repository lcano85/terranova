<?php
require_once __DIR__ . '/../core/Database.php';

class Task
{
  public static function weekdayLabel(int $weekday): string
  {
    $labels = [
      1 => 'Lunes',
      2 => 'Martes',
      3 => 'Miercoles',
      4 => 'Jueves',
      5 => 'Viernes',
      6 => 'Sabado'
    ];

    return $labels[$weekday] ?? '-';
  }

  private static function resolveShiftBucket(array $row): string
  {
    $shiftName = mb_strtolower((string)($row['shift_name'] ?? ''));
    if (strpos($shiftName, 'tarde') !== false) {
      return 'afternoon';
    }
    if (strpos($shiftName, 'manana') !== false || strpos($shiftName, 'mañana') !== false) {
      return 'morning';
    }

    $startTime = $row['start_time'] ?? null;
    if (!empty($startTime)) {
      $hour = (int)substr((string)$startTime, 0, 2);
      return $hour < 15 ? 'morning' : 'afternoon';
    }

    return 'morning';
  }

  public static function catalogAll(): array
  {
    return Database::conn()->query("SELECT * FROM tasks ORDER BY name ASC")->fetchAll();
  }

  public static function activeCatalog(): array
  {
    return Database::conn()->query("SELECT * FROM tasks WHERE is_active=1 ORDER BY name ASC")->fetchAll();
  }

  public static function createTask(string $name, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("INSERT INTO tasks (name, is_active) VALUES (?, ?)");
    $st->execute([$name, $isActive]);
  }

  public static function updateTask(int $id, string $name, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("UPDATE tasks SET name=?, is_active=?, updated_at=NOW() WHERE id=?");
    $st->execute([$name, $isActive, $id]);
  }

  public static function deleteTask(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM tasks WHERE id=?");
    $st->execute([$id]);
  }

  public static function assignmentsAll(): array
  {
    $sql = "
      SELECT
        ta.*,
        t.name AS task_name,
        t.is_active AS task_is_active,
        u.first_name,
        u.last_name,
        u.document_number,
        s.name AS shift_name,
        s.start_time
      FROM task_assignments ta
      JOIN tasks t ON t.id = ta.task_id
      JOIN users u ON u.id = ta.user_id
      LEFT JOIN shifts s ON s.id = u.shift_id
      WHERE u.role='worker'
      ORDER BY ta.weekday ASC, s.start_time ASC, u.first_name ASC, t.name ASC
    ";
    return Database::conn()->query($sql)->fetchAll();
  }

  public static function createAssignment(int $userId, int $taskId, int $weekday, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("
      INSERT INTO task_assignments (user_id, task_id, weekday, is_active)
      VALUES (?,?,?,?)
    ");
    $st->execute([$userId, $taskId, $weekday, $isActive]);
  }

  public static function updateAssignment(int $id, int $userId, int $taskId, int $weekday, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("
      UPDATE task_assignments
      SET user_id=?, task_id=?, weekday=?, is_active=?, updated_at=NOW()
      WHERE id=?
    ");
    $st->execute([$userId, $taskId, $weekday, $isActive, $id]);
  }

  public static function deleteAssignment(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM task_assignments WHERE id=?");
    $st->execute([$id]);
  }

  public static function weeklyBoard(): array
  {
    $sql = "
      SELECT
        ta.weekday,
        t.name AS task_name,
        u.id AS user_id,
        u.first_name,
        u.last_name,
        s.name AS shift_name,
        s.start_time
      FROM task_assignments ta
      JOIN tasks t ON t.id = ta.task_id
      JOIN users u ON u.id = ta.user_id
      LEFT JOIN shifts s ON s.id = u.shift_id
      WHERE ta.is_active=1
        AND t.is_active=1
        AND u.role='worker'
      ORDER BY ta.weekday ASC, s.start_time ASC, u.first_name ASC, t.name ASC
    ";
    $rows = Database::conn()->query($sql)->fetchAll();

    $board = [];
    foreach ($rows as $row) {
      $weekday = (int)$row['weekday'];
      if ($weekday < 1 || $weekday > 6) {
        continue;
      }

      if (!isset($board[$weekday])) {
        $board[$weekday] = [
          'morning' => [],
          'afternoon' => []
        ];
      }

      $shiftBucket = self::resolveShiftBucket($row);
      $workerKey = (int)$row['user_id'];

      if (!isset($board[$weekday][$shiftBucket][$workerKey])) {
        $board[$weekday][$shiftBucket][$workerKey] = [
          'worker_name' => trim($row['first_name'] . ' ' . $row['last_name']),
          'tasks' => []
        ];
      }

      $board[$weekday][$shiftBucket][$workerKey]['tasks'][] = $row['task_name'];
    }

    return $board;
  }
}
