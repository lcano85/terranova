<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/LeadDinnerStatus.php';

class LeadDinnerEntry
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    LeadDinnerStatus::ensureSchema();

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS lead_dinner_entries (
        id INT NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        whatsapp VARCHAR(30) NOT NULL,
        email VARCHAR(180) NOT NULL,
        voucher_path VARCHAR(255) NOT NULL,
        voucher_original_name VARCHAR(255) NOT NULL,
        status_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_lead_dinner_entries_status (status_id),
        KEY idx_lead_dinner_entries_created_at (created_at),
        CONSTRAINT fk_lead_dinner_entries_status
          FOREIGN KEY (status_id) REFERENCES lead_dinner_statuses (id)
          ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function create(array $data): int
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      INSERT INTO lead_dinner_entries (
        first_name, last_name, whatsapp, email, voucher_path, voucher_original_name, status_id
      ) VALUES (?,?,?,?,?,?,?)
    ");
    $st->execute([
      trim((string)$data['first_name']),
      trim((string)$data['last_name']),
      trim((string)$data['whatsapp']),
      trim((string)$data['email']),
      (string)$data['voucher_path'],
      (string)$data['voucher_original_name'],
      (int)$data['status_id'],
    ]);
    return (int)Database::conn()->lastInsertId();
  }

  public static function all(?int $statusId = null, string $search = ''): array
  {
    self::ensureSchema();
    $sql = "
      SELECT l.*, s.name AS status_name
      FROM lead_dinner_entries l
      JOIN lead_dinner_statuses s ON s.id = l.status_id
      WHERE 1=1
    ";
    $params = [];

    if ($statusId !== null) {
      $sql .= " AND l.status_id=?";
      $params[] = $statusId;
    }

    $search = trim($search);
    if ($search !== '') {
      $sql .= " AND (
        l.first_name LIKE ?
        OR l.last_name LIKE ?
        OR l.whatsapp LIKE ?
        OR l.email LIKE ?
      )";
      $like = '%' . $search . '%';
      array_push($params, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY l.created_at DESC, l.id DESC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function updateStatus(int $id, int $statusId): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE lead_dinner_entries SET status_id=? WHERE id=?");
    $st->execute([$statusId, $id]);
  }
}
