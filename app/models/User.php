<?php
require_once __DIR__ . '/../core/Database.php';

class User
{
  public static function findByDoc(string $docType, string $docNumber): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM users WHERE document_type=? AND document_number=? LIMIT 1");
    $st->execute([$docType, $docNumber]);
    $u = $st->fetch();
    return $u ?: null;
  }

  public static function find(int $id): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
  }


  public static function findWithDetails(int $id): ?array
  {
    $st = Database::conn()->prepare("
      SELECT u.*, s.name AS shift_name, s.start_time, s.end_time,
             a.name AS area_name, pr.daily_rate
      FROM users u
      LEFT JOIN shifts s ON s.id = u.shift_id
      LEFT JOIN work_areas a ON a.id = u.area_id
      LEFT JOIN worker_pay_rates pr ON pr.user_id = u.id
      WHERE u.id=? LIMIT 1
    ");
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
  }


  public static function allWorkers(): array
  {
    $sql = "SELECT u.*, s.name AS shift_name, a.name AS area_name, pr.daily_rate
            FROM users u
            LEFT JOIN shifts s ON s.id = u.shift_id
            LEFT JOIN work_areas a ON a.id = u.area_id
            LEFT JOIN worker_pay_rates pr ON pr.user_id = u.id
            WHERE u.role='worker'
            ORDER BY u.id DESC";
    return Database::conn()->query($sql)->fetchAll();
  }

  public static function countWorkers(): int
  {
    $st = Database::conn()->query("SELECT COUNT(*) c FROM users WHERE role='worker'");
    return (int)($st->fetch()['c'] ?? 0);
  }

  public static function createWorker(array $d): int
  {
    $pass = (string)($d['password'] ?? '');
    if ($pass === '') $pass = '123456';

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $st = Database::conn()->prepare("
    INSERT INTO users (document_type, document_number, first_name, last_name, role, shift_id, area_id, password_hash)
    VALUES (?,?,?,?, 'worker', ?, ?, ?)
  ");

    $st->execute([
      $d['document_type'],
      $d['document_number'],
      $d['first_name'],
      $d['last_name'],
      !empty($d['shift_id']) ? (int)$d['shift_id'] : null,
      !empty($d['area_id']) ? (int)$d['area_id'] : null,
      $hash
    ]);

    return (int)Database::conn()->lastInsertId();
  }


  public static function updateWorker(int $id, array $d): void
  {
    $pdo = Database::conn();

    $shiftId = !empty($d['shift_id']) ? (int)$d['shift_id'] : null;
    $areaId  = !empty($d['area_id']) ? (int)$d['area_id'] : null;

    // OJO: password puede venir con espacios o null
    $pass = trim((string)($d['password'] ?? ''));

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);

      $st = $pdo->prepare("
      UPDATE users
      SET document_type=?, document_number=?, first_name=?, last_name=?, shift_id=?, area_id=?, password_hash=?
      WHERE id=? AND role='worker'
    ");

      $st->execute([
        $d['document_type'],
        $d['document_number'],
        $d['first_name'],
        $d['last_name'],
        $shiftId,
        $areaId,
        $hash,
        $id
      ]);

      return;
    }

    $st = $pdo->prepare("
    UPDATE users
    SET document_type=?, document_number=?, first_name=?, last_name=?, shift_id=?, area_id=?
    WHERE id=? AND role='worker'
  ");

    $st->execute([
      $d['document_type'],
      $d['document_number'],
      $d['first_name'],
      $d['last_name'],
      $shiftId,
      $areaId,
      $id
    ]);

    return;
  }


  public static function deleteWorker(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM users WHERE id=? AND role='worker'");
    $st->execute([$id]);
  }
}
