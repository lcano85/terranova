<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/ProductCategory.php';

class Product
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    ProductCategory::ensureSchema();

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS products (
        id INT NOT NULL AUTO_INCREMENT,
        category_id INT NULL,
        name VARCHAR(180) NOT NULL,
        normalized_name VARCHAR(180) NOT NULL,
        variant VARCHAR(150) NULL,
        brand VARCHAR(120) NULL,
        internal_code VARCHAR(100) NULL,
        manufacturer_code VARCHAR(100) NULL,
        unit_price DECIMAL(12,2) NULL,
        cost_price DECIMAL(12,2) NULL,
        stock_quantity DECIMAL(12,2) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_products_normalized_name (normalized_name),
        KEY idx_products_category (category_id),
        CONSTRAINT fk_products_category
          FOREIGN KEY (category_id) REFERENCES product_categories (id)
          ON DELETE SET NULL ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function summary(): array
  {
    self::ensureSchema();
    $sql = "
      SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN category_id IS NOT NULL THEN 1 ELSE 0 END) AS categorized_products,
        SUM(CASE WHEN category_id IS NULL THEN 1 ELSE 0 END) AS uncategorized_products,
        COALESCE(SUM(stock_quantity), 0) AS total_stock
      FROM products
    ";
    return Database::conn()->query($sql)->fetch() ?: [];
  }

  public static function byCategory(?int $categoryId = null, string $search = ''): array
  {
    self::ensureSchema();

    $sql = "
      SELECT p.*, pc.name AS category_name
      FROM products p
      LEFT JOIN product_categories pc ON pc.id = p.category_id
      WHERE 1=1
    ";
    $params = [];

    if ($categoryId !== null) {
      $sql .= " AND p.category_id=?";
      $params[] = $categoryId;
    }

    $search = trim($search);
    if ($search !== '') {
      $sql .= " AND (
        p.name LIKE ?
        OR p.internal_code LIKE ?
        OR p.manufacturer_code LIKE ?
        OR pc.name LIKE ?
      )";
      $like = '%' . $search . '%';
      array_push($params, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY COALESCE(pc.name, 'Sin categoria') ASC, p.name ASC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function groupedByCategory(?int $categoryId = null, string $search = ''): array
  {
    $rows = self::byCategory($categoryId, $search);
    $grouped = [];

    foreach ($rows as $row) {
      $key = $row['category_name'] ?: 'Sin categoria';
      $grouped[$key][] = $row;
    }

    return $grouped;
  }

  public static function create(array $data): void
  {
    self::ensureSchema();
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('El nombre del producto es obligatorio.');
    }

    $st = Database::conn()->prepare("
      INSERT INTO products (
        category_id, name, normalized_name, variant, brand, internal_code, manufacturer_code,
        unit_price, cost_price, stock_quantity, is_active
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      !empty($data['category_id']) ? (int)$data['category_id'] : null,
      $name,
      self::normalize($name),
      self::nullableText($data['variant'] ?? null),
      self::nullableText($data['brand'] ?? null),
      self::nullableText($data['internal_code'] ?? null),
      self::nullableText($data['manufacturer_code'] ?? null),
      self::nullableDecimal($data['unit_price'] ?? null),
      self::nullableDecimal($data['cost_price'] ?? null),
      self::nullableDecimal($data['stock_quantity'] ?? null),
      isset($data['is_active']) ? (int)$data['is_active'] : 1,
    ]);
  }

  public static function update(int $id, array $data): void
  {
    self::ensureSchema();
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('El nombre del producto es obligatorio.');
    }

    $st = Database::conn()->prepare("
      UPDATE products
      SET category_id=?, name=?, normalized_name=?, variant=?, brand=?, internal_code=?, manufacturer_code=?,
          unit_price=?, cost_price=?, stock_quantity=?, is_active=?
      WHERE id=?
    ");
    $st->execute([
      !empty($data['category_id']) ? (int)$data['category_id'] : null,
      $name,
      self::normalize($name),
      self::nullableText($data['variant'] ?? null),
      self::nullableText($data['brand'] ?? null),
      self::nullableText($data['internal_code'] ?? null),
      self::nullableText($data['manufacturer_code'] ?? null),
      self::nullableDecimal($data['unit_price'] ?? null),
      self::nullableDecimal($data['cost_price'] ?? null),
      self::nullableDecimal($data['stock_quantity'] ?? null),
      isset($data['is_active']) ? (int)$data['is_active'] : 1,
      $id,
    ]);
  }

  public static function updatePrice(int $id, ?float $price): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE products SET unit_price=? WHERE id=?");
    $st->execute([$price, $id]);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM products WHERE id=?");
    $st->execute([$id]);
  }

  public static function upsertFromInventoryRow(array $row): void
  {
    self::ensureSchema();

    $name = trim((string)($row['PRODUCTO'] ?? ''));
    if ($name === '') {
      return;
    }

    $categoryId = ProductCategory::firstOrCreate($row['CATEGORIA'] ?? null);
    $normalized = self::normalize($name);
    $existing = self::findByNormalizedName($normalized);

    $payload = [
      'category_id' => $categoryId,
      'name' => $name,
      'variant' => trim((string)($row['VARIANTE'] ?? '')),
      'brand' => trim((string)($row['MARCA'] ?? '')),
      'internal_code' => trim((string)($row['C. INTERNO'] ?? $row['CODIGO INTERNO'] ?? '')),
      'manufacturer_code' => trim((string)($row['C. FABRICANTE'] ?? $row['CODIGO FABRICANTE'] ?? '')),
      'unit_price' => self::number($row['PRECIO'] ?? null),
      'cost_price' => self::number($row['COSTO'] ?? null),
      'stock_quantity' => self::number($row['STOCK'] ?? null),
      'is_active' => 1,
    ];

    if ($existing) {
      self::update((int)$existing['id'], $payload);
      return;
    }

    self::create($payload);
  }

  public static function firstOrCreateFromSalesRow(array $row): int
  {
    self::ensureSchema();
    $name = trim((string)($row['PRODUCTO'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('La fila de ventas no contiene nombre de producto.');
    }

    $normalized = self::normalize($name);
    $existing = self::findByNormalizedName($normalized);
    $categoryId = ProductCategory::firstOrCreate($row['CATEGORIA'] ?? null);

    $payload = [
      'category_id' => $categoryId ?: ($existing['category_id'] ?? null),
      'name' => $name,
      'variant' => trim((string)($row['VARIANTE'] ?? ($existing['variant'] ?? ''))),
      'brand' => trim((string)($row['MARCA'] ?? ($existing['brand'] ?? ''))),
      'internal_code' => trim((string)($row['C. INTERNO'] ?? $row['CODIGO INTERNO'] ?? ($existing['internal_code'] ?? ''))),
      'manufacturer_code' => trim((string)($row['C. FABRICANTE'] ?? $row['CODIGO FABRICANTE'] ?? ($existing['manufacturer_code'] ?? ''))),
      'unit_price' => self::number($row['PRECIO UNITARIO'] ?? ($row['PRECIO'] ?? ($existing['unit_price'] ?? null))),
      'cost_price' => self::number($row['COSTO UNITARIO'] ?? ($row['COSTO'] ?? ($existing['cost_price'] ?? null))),
      'stock_quantity' => self::number($row['STOCK'] ?? ($existing['stock_quantity'] ?? null)),
      'is_active' => 1,
    ];

    if ($existing) {
      self::update((int)$existing['id'], $payload);
      return (int)$existing['id'];
    }

    self::create($payload);
    return (int)Database::conn()->lastInsertId();
  }

  public static function findByNormalizedName(string $normalizedName): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM products WHERE normalized_name=? LIMIT 1");
    $st->execute([$normalizedName]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function normalize(string $name): string
  {
    $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';
    return mb_strtoupper($name, 'UTF-8');
  }

  public static function number($value): ?float
  {
    if ($value === null) {
      return null;
    }

    $value = trim((string)$value);
    if ($value === '') {
      return null;
    }

    $normalized = str_replace([',', ' '], ['', ''], $value);
    return is_numeric($normalized) ? (float)$normalized : null;
  }

  private static function nullableText($value): ?string
  {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
  }

  private static function nullableDecimal($value): ?float
  {
    return self::number($value);
  }
}
