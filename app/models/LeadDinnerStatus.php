<?php
require_once __DIR__ . '/../core/Database.php';

class LeadDinnerStatus
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS lead_dinner_statuses (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_lead_dinner_statuses_name (name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $count = (int)(Database::conn()->query("SELECT COUNT(*) c FROM lead_dinner_statuses")->fetch()['c'] ?? 0);
    if ($count === 0) {
      $defaults = ['Nuevo', 'Contactado', 'Validado', 'Descartado'];
      $st = Database::conn()->prepare("INSERT INTO lead_dinner_statuses (name, is_active) VALUES (?, 1)");
      foreach ($defaults as $name) {
        $st->execute([$name]);
      }
    }

    self::$schemaEnsured = true;
  }

  public static function all(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT s.*, COUNT(l.id) AS leads_count
      FROM lead_dinner_statuses s
      LEFT JOIN lead_dinner_entries l ON l.status_id = s.id
      GROUP BY s.id
      ORDER BY s.name ASC
    ")->fetchAll();
  }

  public static function active(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT *
      FROM lead_dinner_statuses
      WHERE is_active=1
      ORDER BY name ASC
    ")->fetchAll();
  }

  public static function create(string $name, int $isActive = 1): void
  {
    self::ensureSchema();
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('El nombre del estado es obligatorio.');
    }

    $st = Database::conn()->prepare("INSERT INTO lead_dinner_statuses (name, is_active) VALUES (?, ?)");
    $st->execute([$name, $isActive]);
  }

  public static function update(int $id, string $name, int $isActive = 1): void
  {
    self::ensureSchema();
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('El nombre del estado es obligatorio.');
    }

    $st = Database::conn()->prepare("UPDATE lead_dinner_statuses SET name=?, is_active=? WHERE id=?");
    $st->execute([$name, $isActive, $id]);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM lead_dinner_statuses WHERE id=?");
    $st->execute([$id]);
  }

  public static function firstActiveId(): ?int
  {
    self::ensureSchema();
    $st = Database::conn()->query("SELECT id FROM lead_dinner_statuses WHERE is_active=1 ORDER BY id ASC LIMIT 1");
    $row = $st->fetch();
    return $row ? (int)$row['id'] : null;
  }
}
