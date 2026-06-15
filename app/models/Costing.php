<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Product.php';

class Costing
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Product::ensureSchema();
    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS costings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        portions DECIMAL(10,2) NOT NULL DEFAULT 1,
        total_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        cost_per_portion DECIMAL(12,4) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_costings_product (product_id),
        CONSTRAINT fk_costings_product FOREIGN KEY (product_id) REFERENCES products(id)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS costing_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        costing_id INT NOT NULL,
        purchase_product_id INT NULL,
        ingredient_name VARCHAR(180) NOT NULL,
        package_description VARCHAR(180) NULL,
        cost_makro DECIMAL(12,4) NOT NULL DEFAULT 0,
        cost_mercado DECIMAL(12,4) NOT NULL DEFAULT 0,
        cost_proveedor DECIMAL(12,4) NOT NULL DEFAULT 0,
        selected_source VARCHAR(20) NOT NULL DEFAULT 'auto',
        selected_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        yield_quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
        yield_unit VARCHAR(60) NULL,
        usage_quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
        usage_unit VARCHAR(60) NULL,
        unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        total_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_costing_items_costing (costing_id),
        CONSTRAINT fk_costing_items_costing FOREIGN KEY (costing_id) REFERENCES costings(id)
          ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_costing_items_purchase_product FOREIGN KEY (purchase_product_id) REFERENCES products(id)
          ON DELETE SET NULL ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    self::$schemaEnsured = true;
  }

  public static function all(): array
  {
    self::ensureSchema();
    $rows = Database::conn()->query("
      SELECT c.*, p.name AS product_name
      FROM costings c
      JOIN products p ON p.id = c.product_id
      ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC
    ")->fetchAll();
    return self::withItems($rows);
  }

  public static function create(array $data): int
  {
    return self::save(null, $data);
  }

  public static function update(int $id, array $data): void
  {
    self::save($id, $data);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM costings WHERE id=?");
    $st->execute([$id]);
  }

  private static function save(?int $id, array $data): int
  {
    self::ensureSchema();
    $productId = (int)($data['product_id'] ?? 0);
    $title = trim((string)($data['title'] ?? ''));
    $portions = self::positive($data['portions'] ?? 1, 1);
    $items = self::normalizeItems((array)($data['items'] ?? []));

    if ($productId <= 0) {
      throw new RuntimeException('Debes seleccionar el producto relacionado.');
    }
    if ($title === '') {
      throw new RuntimeException('El nombre del costeo es obligatorio.');
    }
    if (empty($items)) {
      throw new RuntimeException('Debes agregar al menos un insumo.');
    }

    $total = round(array_sum(array_column($items, 'total_cost')), 4);
    $costPerPortion = round($total / max($portions, 1), 4);
    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      if ($id === null) {
        $st = $pdo->prepare("
          INSERT INTO costings (product_id, title, portions, total_cost, cost_per_portion, notes)
          VALUES (?,?,?,?,?,?)
        ");
        $st->execute([$productId, $title, $portions, $total, $costPerPortion, self::nullableText($data['notes'] ?? '')]);
        $id = (int)$pdo->lastInsertId();
      } else {
        $st = $pdo->prepare("
          UPDATE costings
          SET product_id=?, title=?, portions=?, total_cost=?, cost_per_portion=?, notes=?
          WHERE id=?
        ");
        $st->execute([$productId, $title, $portions, $total, $costPerPortion, self::nullableText($data['notes'] ?? ''), $id]);
        $pdo->prepare("DELETE FROM costing_items WHERE costing_id=?")->execute([$id]);
      }

      $itemSt = $pdo->prepare("
        INSERT INTO costing_items (
          costing_id, purchase_product_id, ingredient_name, package_description,
          cost_makro, cost_mercado, cost_proveedor, selected_source, selected_cost,
          yield_quantity, yield_unit, usage_quantity, usage_unit, unit_cost, total_cost, sort_order
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");
      foreach ($items as $index => $item) {
        $itemSt->execute([
          $id,
          $item['purchase_product_id'],
          $item['ingredient_name'],
          $item['package_description'],
          $item['cost_makro'],
          $item['cost_mercado'],
          $item['cost_proveedor'],
          $item['selected_source'],
          $item['selected_cost'],
          $item['yield_quantity'],
          $item['yield_unit'],
          $item['usage_quantity'],
          $item['usage_unit'],
          $item['unit_cost'],
          $item['total_cost'],
          $index + 1,
        ]);
      }

      $pdo->commit();
      return $id;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  private static function normalizeItems(array $raw): array
  {
    $items = [];
    $names = $raw['ingredient_name'] ?? [];
    foreach ($names as $index => $name) {
      $name = trim((string)$name);
      if ($name === '') {
        continue;
      }

      $source = in_array(($raw['selected_source'][$index] ?? 'auto'), ['auto', 'makro', 'mercado', 'proveedor'], true)
        ? (string)$raw['selected_source'][$index]
        : 'auto';
      $costMakro = self::number($raw['cost_makro'][$index] ?? 0);
      $costMercado = self::number($raw['cost_mercado'][$index] ?? 0);
      $costProveedor = self::number($raw['cost_proveedor'][$index] ?? 0);
      $selectedCost = self::selectedCost($source, $costMakro, $costMercado, $costProveedor);
      $yield = self::positive($raw['yield_quantity'][$index] ?? 1, 1);
      $usage = self::positive($raw['usage_quantity'][$index] ?? 1, 0);
      $unitCost = round($selectedCost / max($yield, 1), 4);
      $totalCost = round($unitCost * $usage, 4);

      $items[] = [
        'purchase_product_id' => !empty($raw['purchase_product_id'][$index]) ? (int)$raw['purchase_product_id'][$index] : null,
        'ingredient_name' => $name,
        'package_description' => self::nullableText($raw['package_description'][$index] ?? ''),
        'cost_makro' => $costMakro,
        'cost_mercado' => $costMercado,
        'cost_proveedor' => $costProveedor,
        'selected_source' => $source,
        'selected_cost' => $selectedCost,
        'yield_quantity' => $yield,
        'yield_unit' => self::nullableText($raw['yield_unit'][$index] ?? ''),
        'usage_quantity' => $usage,
        'usage_unit' => self::nullableText($raw['usage_unit'][$index] ?? ''),
        'unit_cost' => $unitCost,
        'total_cost' => $totalCost,
      ];
    }
    return $items;
  }

  private static function selectedCost(string $source, float $makro, float $mercado, float $proveedor): float
  {
    if ($source === 'makro') return $makro;
    if ($source === 'mercado') return $mercado;
    if ($source === 'proveedor') return $proveedor;
    $values = array_filter([$makro, $mercado, $proveedor], static fn($v) => $v > 0);
    return empty($values) ? 0.0 : min($values);
  }

  private static function withItems(array $rows): array
  {
    foreach ($rows as &$row) {
      $row['items'] = self::items((int)$row['id']);
    }
    unset($row);
    return $rows;
  }

  private static function items(int $costingId): array
  {
    $st = Database::conn()->prepare("
      SELECT ci.*, p.name AS purchase_product_name
      FROM costing_items ci
      LEFT JOIN products p ON p.id = ci.purchase_product_id
      WHERE ci.costing_id=?
      ORDER BY ci.sort_order ASC, ci.id ASC
    ");
    $st->execute([$costingId]);
    return $st->fetchAll();
  }

  private static function number($value): float
  {
    $value = trim((string)$value);
    return is_numeric($value) ? round(max(0, (float)$value), 4) : 0.0;
  }

  private static function positive($value, float $fallback): float
  {
    $number = self::number($value);
    return $number > 0 ? $number : $fallback;
  }

  private static function nullableText($value): ?string
  {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
  }
}
