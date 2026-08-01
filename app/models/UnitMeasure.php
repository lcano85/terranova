<?php
require_once __DIR__ . '/../core/Database.php';

class UnitMeasure
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS unit_measures (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        normalized_name VARCHAR(120) NOT NULL,
        abbreviation VARCHAR(30) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_unit_measures_normalized_name (normalized_name),
        KEY idx_unit_measures_active (is_active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function all(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT *
      FROM unit_measures
      ORDER BY name ASC
    ")->fetchAll();
  }

  public static function active(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT *
      FROM unit_measures
      WHERE is_active=1
      ORDER BY name ASC
    ")->fetchAll();
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM unit_measures WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function dimensionFor(array|string $unit): ?string
  {
    $value = is_array($unit)
      ? (string)(($unit['abbreviation'] ?? '') ?: ($unit['name'] ?? ''))
      : $unit;
    $normalized = self::normalizeUnitCode($value);
    if (in_array($normalized, ['kg', 'g', 'gr', 'kilogramo', 'kilogramos', 'gramo', 'gramos'], true)) {
      return 'weight';
    }
    if (in_array($normalized, ['l', 'lt', 'litro', 'litros', 'ml', 'mililitro', 'mililitros'], true)) {
      return 'volume';
    }
    if (in_array($normalized, ['und', 'unid', 'unidad', 'unidades'], true)) {
      return 'unit';
    }
    return null;
  }

  public static function areCompatible(int $fromId, int $toId): bool
  {
    if ($fromId <= 0 || $toId <= 0) return false;
    if ($fromId === $toId) return true;
    $from = self::find($fromId);
    $to = self::find($toId);
    if (!$from || !$to) return false;
    $fromDimension = self::dimensionFor($from);
    return $fromDimension !== null && $fromDimension === self::dimensionFor($to);
  }

  public static function convertQuantity(float $quantity, string $fromUnit, string $toUnit): ?float
  {
    $fromCode = self::normalizeUnitCode($fromUnit);
    $toCode = self::normalizeUnitCode($toUnit);
    if ($fromCode !== '' && $fromCode === $toCode) return $quantity;

    $fromDimension = self::dimensionFor($fromCode);
    $toDimension = self::dimensionFor($toCode);
    if ($fromDimension === null || $fromDimension !== $toDimension) return null;

    $factor = static function (string $code): float {
      return in_array($code, ['kg', 'kilogramo', 'kilogramos', 'l', 'lt', 'litro', 'litros'], true) ? 1000.0 : 1.0;
    };
    return $quantity * $factor($fromCode) / $factor($toCode);
  }

  private static function normalizeUnitCode(string $unit): string
  {
    $value = function_exists('mb_strtolower') ? mb_strtolower(trim($unit), 'UTF-8') : strtolower(trim($unit));
    return rtrim($value, '.');
  }

  public static function create(array $data): void
  {
    self::ensureSchema();
    $payload = self::payload($data);

    $st = Database::conn()->prepare("
      INSERT INTO unit_measures (name, normalized_name, abbreviation, is_active)
      VALUES (?, ?, ?, ?)
    ");
    $st->execute([
      $payload['name'],
      self::normalize($payload['name']),
      $payload['abbreviation'],
      $payload['is_active'],
    ]);
  }

  public static function update(int $id, array $data): void
  {
    self::ensureSchema();
    if ($id <= 0) {
      throw new RuntimeException('Unidad de medida no valida.');
    }

    $payload = self::payload($data);
    $st = Database::conn()->prepare("
      UPDATE unit_measures
      SET name=?, normalized_name=?, abbreviation=?, is_active=?, updated_at=NOW()
      WHERE id=?
    ");
    $st->execute([
      $payload['name'],
      self::normalize($payload['name']),
      $payload['abbreviation'],
      $payload['is_active'],
      $id,
    ]);
  }

  public static function setActive(int $id, int $isActive): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE unit_measures SET is_active=?, updated_at=NOW() WHERE id=?");
    $st->execute([$isActive === 1 ? 1 : 0, $id]);
  }

  private static function payload(array $data): array
  {
    $name = preg_replace('/\s+/u', ' ', trim((string)($data['name'] ?? ''))) ?? trim((string)($data['name'] ?? ''));
    $abbreviation = preg_replace('/\s+/u', ' ', trim((string)($data['abbreviation'] ?? ''))) ?? trim((string)($data['abbreviation'] ?? ''));

    if ($name === '') {
      throw new RuntimeException('El nombre de la unidad es obligatorio.');
    }

    return [
      'name' => $name,
      'abbreviation' => $abbreviation !== '' ? $abbreviation : null,
      'is_active' => (int)($data['is_active'] ?? 1) === 1 ? 1 : 0,
    ];
  }

  private static function normalize(string $value): string
  {
    $clean = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (function_exists('mb_strtolower')) {
      return mb_strtolower($clean, 'UTF-8');
    }
    return strtolower($clean);
  }
}
