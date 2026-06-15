<?php
require_once __DIR__ . '/../core/Database.php';

class LeadDinnerCampaign
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS lead_dinner_campaigns (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        draw_date DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_lead_dinner_campaigns_active (is_active),
        KEY idx_lead_dinner_campaigns_dates (start_date, end_date, draw_date)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function all(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT c.*, COUNT(l.id) AS leads_count
      FROM lead_dinner_campaigns c
      LEFT JOIN lead_dinner_entries l ON l.campaign_id = c.id
      GROUP BY c.id
      ORDER BY c.start_date DESC, c.id DESC
    ")->fetchAll();
  }

  public static function active(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT *
      FROM lead_dinner_campaigns
      WHERE is_active = 1
      ORDER BY start_date DESC, id DESC
    ")->fetchAll();
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM lead_dinner_campaigns WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(array $data): int
  {
    self::ensureSchema();
    $data = self::validate($data);
    $st = Database::conn()->prepare("
      INSERT INTO lead_dinner_campaigns (name, start_date, end_date, draw_date, is_active)
      VALUES (?, ?, ?, ?, ?)
    ");
    $st->execute([$data['name'], $data['start_date'], $data['end_date'], $data['draw_date'], $data['is_active']]);
    return (int)Database::conn()->lastInsertId();
  }

  public static function update(int $id, array $data): void
  {
    self::ensureSchema();
    if (!self::find($id)) {
      throw new RuntimeException('La campaña que intentas editar no existe.');
    }

    $data = self::validate($data);
    $st = Database::conn()->prepare("
      UPDATE lead_dinner_campaigns
      SET name = ?, start_date = ?, end_date = ?, draw_date = ?, is_active = ?
      WHERE id = ?
    ");
    $st->execute([$data['name'], $data['start_date'], $data['end_date'], $data['draw_date'], $data['is_active'], $id]);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT COUNT(*) FROM lead_dinner_entries WHERE campaign_id = ?");
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
      throw new RuntimeException('No puedes eliminar una campaña que tiene leads asociados. Puedes desactivarla.');
    }

    $st = Database::conn()->prepare("DELETE FROM lead_dinner_campaigns WHERE id = ?");
    $st->execute([$id]);
  }

  private static function validate(array $data): array
  {
    $name = trim((string)($data['name'] ?? ''));
    $startDate = trim((string)($data['start_date'] ?? ''));
    $endDate = trim((string)($data['end_date'] ?? ''));
    $drawDate = trim((string)($data['draw_date'] ?? ''));
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($name === '') {
      throw new RuntimeException('El nombre de la campaña es obligatorio.');
    }
    foreach ([$startDate, $endDate, $drawDate] as $date) {
      $parsed = DateTime::createFromFormat('Y-m-d', $date);
      if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new RuntimeException('Las fechas de la campaña no son validas.');
      }
    }
    if ($endDate < $startDate) {
      throw new RuntimeException('La fecha fin no puede ser anterior a la fecha de inicio.');
    }
    if ($drawDate < $endDate) {
      throw new RuntimeException('La fecha de sorteo no puede ser anterior a la fecha fin.');
    }

    return [
      'name' => $name,
      'start_date' => $startDate,
      'end_date' => $endDate,
      'draw_date' => $drawDate,
      'is_active' => $isActive,
    ];
  }
}
