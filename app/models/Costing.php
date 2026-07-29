<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Recipe.php';
require_once __DIR__ . '/Supply.php';

class Costing
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) return;

    Product::ensureSchema();
    Recipe::ensureSchema();
    Supply::ensureSchema();
    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS costings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        recipe_id INT NULL,
        title VARCHAR(180) NOT NULL,
        portions DECIMAL(10,2) NOT NULL DEFAULT 1,
        ingredient_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        labor_minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
        labor_hourly_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        labor_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        gas_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        equipment_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        other_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        total_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        cost_per_portion DECIMAL(12,4) NOT NULL DEFAULT 0,
        selling_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        target_margin DECIMAL(7,2) NOT NULL DEFAULT 30,
        suggested_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_costings_product (product_id),
        KEY idx_costings_recipe (recipe_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    self::addColumns($pdo, 'costings', [
      'recipe_id' => 'INT NULL AFTER product_id',
      'ingredient_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER portions',
      'labor_minutes' => 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER ingredient_cost',
      'labor_hourly_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER labor_minutes',
      'labor_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER labor_hourly_cost',
      'gas_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER labor_cost',
      'equipment_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER gas_cost',
      'other_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER equipment_cost',
      'selling_price' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER cost_per_portion',
      'target_margin' => 'DECIMAL(7,2) NOT NULL DEFAULT 30 AFTER selling_price',
      'suggested_price' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER target_margin',
    ]);
    $pdo->exec("ALTER TABLE costings MODIFY product_id INT NULL");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS costing_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        costing_id INT NOT NULL,
        supply_id INT NULL,
        purchase_product_id INT NULL,
        ingredient_name VARCHAR(180) NOT NULL,
        package_description VARCHAR(180) NULL,
        purchase_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        purchase_quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
        purchase_unit VARCHAR(20) NOT NULL DEFAULT 'und',
        waste_percent DECIMAL(7,2) NOT NULL DEFAULT 0,
        usage_quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
        usage_unit VARCHAR(20) NOT NULL DEFAULT 'und',
        unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        total_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_costing_items_costing (costing_id),
        KEY idx_costing_items_supply (supply_id),
        CONSTRAINT fk_costing_items_costing FOREIGN KEY (costing_id) REFERENCES costings(id)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    self::addColumns($pdo, 'costing_items', [
      'supply_id' => 'INT NULL AFTER costing_id',
      'purchase_cost' => 'DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER package_description',
      'purchase_quantity' => 'DECIMAL(12,4) NOT NULL DEFAULT 1 AFTER purchase_cost',
      'purchase_unit' => "VARCHAR(20) NOT NULL DEFAULT 'und' AFTER purchase_quantity",
      'waste_percent' => 'DECIMAL(7,2) NOT NULL DEFAULT 0 AFTER purchase_unit',
    ]);
    self::$schemaEnsured = true;
  }

  public static function all(string $search = ''): array
  {
    self::ensureSchema();
    $sql = "SELECT c.*, p.name AS product_name, r.title AS recipe_title, r.area_type
      FROM costings c
      LEFT JOIN products p ON p.id=c.product_id
      LEFT JOIN recipes r ON r.id=c.recipe_id";
    $params = [];
    if (trim($search) !== '') {
      $sql .= " WHERE c.title LIKE ? OR p.name LIKE ? OR r.title LIKE ?";
      $like = '%' . trim($search) . '%';
      $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY COALESCE(c.updated_at,c.created_at) DESC,c.id DESC";
    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return self::withItems($st->fetchAll());
  }

  public static function create(array $data): int { return self::save(null, $data); }
  public static function update(int $id, array $data): void { self::save($id, $data); }
  public static function delete(int $id): void
  {
    self::ensureSchema();
    Database::conn()->prepare("DELETE FROM costings WHERE id=?")->execute([$id]);
  }

  private static function save(?int $id, array $data): int
  {
    self::ensureSchema();
    $recipeId = (int)($data['recipe_id'] ?? 0);
    $productId = (int)($data['product_id'] ?? 0);
    $title = trim((string)($data['title'] ?? ''));
    $portions = self::positive($data['portions'] ?? 1, 1);
    $items = self::normalizeItems((array)($data['items'] ?? []));
    if ($recipeId <= 0) throw new RuntimeException('Debes seleccionar una receta.');
    if (!Recipe::find($recipeId)) throw new RuntimeException('La receta seleccionada no existe.');
    if ($title === '') throw new RuntimeException('El nombre del plato es obligatorio.');
    if (!$items) throw new RuntimeException('Debes agregar al menos un ingrediente.');

    $ingredientCost = round(array_sum(array_column($items, 'total_cost')), 4);
    $laborMinutes = self::number($data['labor_minutes'] ?? 0);
    $laborHourly = self::number($data['labor_hourly_cost'] ?? 0);
    $laborCost = round(($laborMinutes / 60) * $laborHourly, 4);
    $gas = self::number($data['gas_cost'] ?? 0);
    $equipment = self::number($data['equipment_cost'] ?? 0);
    $other = self::number($data['other_cost'] ?? 0);
    $total = round($ingredientCost + $laborCost + $gas + $equipment + $other, 4);
    $perPortion = round($total / $portions, 4);
    $sellingPrice = self::number($data['selling_price'] ?? 0);
    $targetMargin = min(95, self::number($data['target_margin'] ?? 30));
    $suggested = round($perPortion / max(0.05, 1 - ($targetMargin / 100)), 2);

    $pdo = Database::conn();
    $pdo->beginTransaction();
    try {
      $values = [$productId ?: null, $recipeId, $title, $portions, $ingredientCost, $laborMinutes,
        $laborHourly, $laborCost, $gas, $equipment, $other, $total, $perPortion, $sellingPrice,
        $targetMargin, $suggested, self::nullableText($data['notes'] ?? '')];
      if ($id === null) {
        $st = $pdo->prepare("INSERT INTO costings
          (product_id,recipe_id,title,portions,ingredient_cost,labor_minutes,labor_hourly_cost,labor_cost,
           gas_cost,equipment_cost,other_cost,total_cost,cost_per_portion,selling_price,target_margin,suggested_price,notes)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute($values);
        $id = (int)$pdo->lastInsertId();
      } else {
        $st = $pdo->prepare("UPDATE costings SET product_id=?,recipe_id=?,title=?,portions=?,ingredient_cost=?,
          labor_minutes=?,labor_hourly_cost=?,labor_cost=?,gas_cost=?,equipment_cost=?,other_cost=?,total_cost=?,
          cost_per_portion=?,selling_price=?,target_margin=?,suggested_price=?,notes=? WHERE id=?");
        $st->execute([...$values, $id]);
        $pdo->prepare("DELETE FROM costing_items WHERE costing_id=?")->execute([$id]);
      }
      $st = $pdo->prepare("INSERT INTO costing_items
        (costing_id,supply_id,purchase_product_id,ingredient_name,package_description,purchase_cost,
         purchase_quantity,purchase_unit,waste_percent,usage_quantity,usage_unit,unit_cost,total_cost,sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      foreach ($items as $i => $item) {
        $st->execute([$id,$item['supply_id'],null,$item['ingredient_name'],$item['package_description'],
          $item['purchase_cost'],$item['purchase_quantity'],$item['purchase_unit'],$item['waste_percent'],
          $item['usage_quantity'],$item['usage_unit'],$item['unit_cost'],$item['total_cost'],$i + 1]);
      }
      $pdo->commit();
      return $id;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $e;
    }
  }

  private static function normalizeItems(array $raw): array
  {
    $items = [];
    foreach (($raw['ingredient_name'] ?? []) as $i => $name) {
      $name = trim((string)$name);
      if ($name === '') continue;
      $purchaseCost = self::number($raw['purchase_cost'][$i] ?? 0);
      $purchaseQty = self::positive($raw['purchase_quantity'][$i] ?? 1, 1);
      $purchaseUnit = self::unit($raw['purchase_unit'][$i] ?? 'und');
      $usageQty = self::number($raw['usage_quantity'][$i] ?? 0);
      $usageUnit = self::unit($raw['usage_unit'][$i] ?? 'und');
      $waste = min(99, self::number($raw['waste_percent'][$i] ?? 0));
      $purchaseBase = self::toBase($purchaseQty, $purchaseUnit);
      $usageBase = self::toBase($usageQty, $usageUnit);
      if (self::dimension($purchaseUnit) !== self::dimension($usageUnit)) {
        throw new RuntimeException("Las unidades de compra y uso de {$name} no son compatibles.");
      }
      $usable = $purchaseBase * (1 - $waste / 100);
      $unitCost = round($purchaseCost / max($usable, 0.0001), 6);
      $total = round($unitCost * $usageBase, 4);
      $items[] = [
        'supply_id' => !empty($raw['supply_id'][$i]) ? (int)$raw['supply_id'][$i] : null,
        'ingredient_name' => $name,
        'package_description' => self::nullableText($raw['package_description'][$i] ?? ''),
        'purchase_cost' => $purchaseCost, 'purchase_quantity' => $purchaseQty,
        'purchase_unit' => $purchaseUnit, 'waste_percent' => $waste,
        'usage_quantity' => $usageQty, 'usage_unit' => $usageUnit,
        'unit_cost' => $unitCost, 'total_cost' => $total,
      ];
    }
    return $items;
  }

  private static function withItems(array $rows): array
  {
    $st = Database::conn()->prepare("SELECT ci.*,s.name AS supply_name FROM costing_items ci
      LEFT JOIN supplies s ON s.id=ci.supply_id WHERE ci.costing_id=? ORDER BY ci.sort_order,ci.id");
    foreach ($rows as &$row) { $st->execute([(int)$row['id']]); $row['items']=$st->fetchAll(); }
    return $rows;
  }

  private static function addColumns(PDO $pdo, string $table, array $columns): void
  {
    foreach ($columns as $name => $definition) {
      $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
      $st->execute([$table,$name]);
      if (!(int)$st->fetchColumn()) $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
    }
  }
  private static function unit($value): string
  {
    $unit=strtolower(trim((string)$value));
    return in_array($unit,['kg','g','l','ml','und'],true) ? $unit : 'und';
  }
  private static function dimension(string $unit): string { return in_array($unit,['kg','g'],true)?'weight':(in_array($unit,['l','ml'],true)?'volume':'unit'); }
  private static function toBase(float $qty,string $unit): float { return in_array($unit,['kg','l'],true)?$qty*1000:$qty; }
  private static function number($value): float { $v=str_replace(',','.',trim((string)$value)); return is_numeric($v)?round(max(0,(float)$v),4):0; }
  private static function positive($value,float $fallback): float { $v=self::number($value); return $v>0?$v:$fallback; }
  private static function nullableText($value): ?string { $v=trim((string)$value); return $v===''?null:$v; }
}
