<?php
require_once __DIR__ . '/../core/Database.php';

class ProductCategory
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS product_categories (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        normalized_name VARCHAR(120) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_product_categories_normalized_name (normalized_name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function all(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT pc.*,
             COUNT(p.id) AS products_count
      FROM product_categories pc
      LEFT JOIN products p ON p.category_id = pc.id
      GROUP BY pc.id
      ORDER BY pc.name ASC
    ")->fetchAll();
  }

  public static function create(string $name): int
  {
    self::ensureSchema();
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('La categoria es obligatoria.');
    }

    $normalized = self::normalize($name);
    $st = Database::conn()->prepare("
      INSERT INTO product_categories (name, normalized_name)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = CURRENT_TIMESTAMP
    ");
    $st->execute([$name, $normalized]);

    return (int)self::findIdByNormalizedName($normalized);
  }

  public static function update(int $id, string $name): void
  {
    self::ensureSchema();
    $name = trim($name);
    if ($name === '') {
      throw new RuntimeException('La categoria es obligatoria.');
    }

    $st = Database::conn()->prepare("
      UPDATE product_categories
      SET name=?, normalized_name=?
      WHERE id=?
    ");
    $st->execute([$name, self::normalize($name), $id]);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM product_categories WHERE id=?");
    $st->execute([$id]);
  }

  public static function firstOrCreate(?string $name): ?int
  {
    self::ensureSchema();
    $name = trim((string)$name);
    if ($name === '') {
      return null;
    }

    return self::create($name);
  }

  private static function findIdByNormalizedName(string $normalizedName): ?int
  {
    $st = Database::conn()->prepare("
      SELECT id
      FROM product_categories
      WHERE normalized_name=?
      LIMIT 1
    ");
    $st->execute([$normalizedName]);
    $row = $st->fetch();
    return $row ? (int)$row['id'] : null;
  }

  public static function normalize(string $name): string
  {
    $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';
    return mb_strtoupper($name, 'UTF-8');
  }
}
