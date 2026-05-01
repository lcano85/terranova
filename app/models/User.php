<?php
require_once __DIR__ . '/../core/Database.php';

class User
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
    $existing = [];
    foreach ($columns as $column) {
      $existing[$column['Field']] = true;
    }

    if (empty($existing['is_active'])) {
      $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
    }

    self::$schemaEnsured = true;
  }

  public static function findByDoc(string $docType, string $docNumber): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM users WHERE document_type=? AND document_number=? LIMIT 1");
    $st->execute([$docType, $docNumber]);
    $u = $st->fetch();
    return $u ?: null;
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
  }


  public static function findWithDetails(int $id): ?array
  {
    self::ensureSchema();
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
    self::ensureSchema();
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
    self::ensureSchema();
    $st = Database::conn()->query("SELECT COUNT(*) c FROM users WHERE role='worker'");
    return (int)($st->fetch()['c'] ?? 0);
  }

  public static function isWorkerActive(int $id): bool
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT is_active FROM users WHERE id=? AND role='worker' LIMIT 1");
    $st->execute([$id]);
    $value = $st->fetchColumn();
    return $value !== false && (int)$value === 1;
  }

  public static function createWorker(array $d): int
  {
    self::ensureSchema();
    $pass = (string)($d['password'] ?? '');
    if ($pass === '') $pass = '123456';

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $isActive = isset($d['is_active']) ? (int)$d['is_active'] : 1;

    $st = Database::conn()->prepare("
    INSERT INTO users (document_type, document_number, first_name, last_name, role, is_active, shift_id, area_id, password_hash)
    VALUES (?,?,?,?, 'worker', ?, ?, ?, ?)
  ");

    $st->execute([
      $d['document_type'],
      $d['document_number'],
      $d['first_name'],
      $d['last_name'],
      $isActive,
      !empty($d['shift_id']) ? (int)$d['shift_id'] : null,
      !empty($d['area_id']) ? (int)$d['area_id'] : null,
      $hash
    ]);

    return (int)Database::conn()->lastInsertId();
  }


  public static function updateWorker(int $id, array $d): void
  {
    self::ensureSchema();
    $pdo = Database::conn();

    $shiftId = !empty($d['shift_id']) ? (int)$d['shift_id'] : null;
    $areaId  = !empty($d['area_id']) ? (int)$d['area_id'] : null;
    $isActive = isset($d['is_active']) ? (int)$d['is_active'] : 0;

    // OJO: password puede venir con espacios o null
    $pass = trim((string)($d['password'] ?? ''));

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);

      $st = $pdo->prepare("
      UPDATE users
      SET document_type=?, document_number=?, first_name=?, last_name=?, is_active=?, shift_id=?, area_id=?, password_hash=?
      WHERE id=? AND role='worker'
    ");

      $st->execute([
        $d['document_type'],
        $d['document_number'],
        $d['first_name'],
        $d['last_name'],
        $isActive,
        $shiftId,
        $areaId,
        $hash,
        $id
      ]);

      return;
    }

    $st = $pdo->prepare("
    UPDATE users
    SET document_type=?, document_number=?, first_name=?, last_name=?, is_active=?, shift_id=?, area_id=?
    WHERE id=? AND role='worker'
  ");

    $st->execute([
      $d['document_type'],
      $d['document_number'],
      $d['first_name'],
      $d['last_name'],
      $isActive,
      $shiftId,
      $areaId,
      $id
    ]);

    return;
  }


  public static function deleteWorker(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM users WHERE id=? AND role='worker'");
    $st->execute([$id]);
  }
}
