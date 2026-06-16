<?php
require_once __DIR__ . '/../core/Database.php';

class BeverageControl
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS beverage_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        units_per_package INT NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_beverage_products_name (name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS beverage_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        beverage_product_id INT NOT NULL,
        package_quantity INT NOT NULL DEFAULT 0,
        entry_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        expiry_warning_days INT NOT NULL DEFAULT 3,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_beverage_entries_product (beverage_product_id),
        INDEX idx_beverage_entries_expiry (expiry_date),
        CONSTRAINT fk_beverage_entries_product
          FOREIGN KEY (beverage_product_id) REFERENCES beverage_products(id)
          ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    self::ensureEntryWarningDaysColumn($pdo);

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS beverage_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        beverage_entry_id INT NOT NULL,
        sale_date DATE NOT NULL,
        units_sold INT NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_beverage_sales_entry (beverage_entry_id),
        INDEX idx_beverage_sales_date (sale_date),
        CONSTRAINT fk_beverage_sales_entry
          FOREIGN KEY (beverage_entry_id) REFERENCES beverage_entries(id)
          ON DELETE CASCADE
          ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    self::seedProducts();
    self::$schemaEnsured = true;
  }

  public static function products(bool $activeOnly = false): array
  {
    self::ensureSchema();
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    return Database::conn()->query("
      SELECT *
      FROM beverage_products
      $where
      ORDER BY name ASC
    ")->fetchAll();
  }

  public static function createProduct(array $data): int
  {
    self::ensureSchema();
    $data = self::validateProduct($data);

    $st = Database::conn()->prepare("
      INSERT INTO beverage_products (name, units_per_package, is_active)
      VALUES (?, ?, ?)
    ");
    $st->execute([
      $data['name'],
      $data['units_per_package'],
      $data['is_active'],
    ]);
    return (int)Database::conn()->lastInsertId();
  }

  public static function updateProduct(int $id, array $data): void
  {
    self::ensureSchema();
    if (!self::findProduct($id)) {
      throw new RuntimeException('La bebida no existe.');
    }

    $data = self::validateProduct($data);
    $st = Database::conn()->prepare("
      UPDATE beverage_products
      SET name=?, units_per_package=?, is_active=?
      WHERE id=?
    ");
    $st->execute([
      $data['name'],
      $data['units_per_package'],
      $data['is_active'],
      $id,
    ]);
  }

  public static function deleteProduct(int $id): void
  {
    self::ensureSchema();
    if (!self::findProduct($id)) {
      throw new RuntimeException('La bebida no existe.');
    }

    $st = Database::conn()->prepare("SELECT COUNT(*) FROM beverage_entries WHERE beverage_product_id=?");
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
      throw new RuntimeException('Esta bebida ya tiene stock registrado. Desactivala para conservar el historial.');
    }

    Database::conn()->prepare("DELETE FROM beverage_products WHERE id=?")->execute([$id]);
  }

  public static function deleteTestProducts(): int
  {
    self::ensureSchema();
    $pdo = Database::conn();
    $ids = $pdo->query("SELECT id FROM beverage_products WHERE name LIKE 'Codex bebida test %'")->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids)) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $entrySt = $pdo->prepare("SELECT id FROM beverage_entries WHERE beverage_product_id IN ($placeholders)");
    $entrySt->execute($ids);
    $entryIds = array_values(array_filter(array_map('intval', $entrySt->fetchAll(PDO::FETCH_COLUMN))));

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
      $pdo->beginTransaction();
    }
    try {
      if (!empty($entryIds)) {
        $entryPlaceholders = implode(',', array_fill(0, count($entryIds), '?'));
        $pdo->prepare("DELETE FROM beverage_sales WHERE beverage_entry_id IN ($entryPlaceholders)")->execute($entryIds);
        $pdo->prepare("DELETE FROM beverage_entries WHERE id IN ($entryPlaceholders)")->execute($entryIds);
      }
      $pdo->prepare("DELETE FROM beverage_products WHERE id IN ($placeholders)")->execute($ids);

      if ($ownsTransaction) {
        $pdo->commit();
      }
    } catch (Throwable $e) {
      if ($ownsTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }

    return count($ids);
  }

  public static function entries(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT
        e.*,
        p.name AS product_name,
        p.units_per_package,
        e.package_quantity AS total_units,
        COALESCE(SUM(s.units_sold), 0) AS sold_units,
        (e.package_quantity - COALESCE(SUM(s.units_sold), 0)) AS remaining_units
      FROM beverage_entries e
      JOIN beverage_products p ON p.id = e.beverage_product_id
      LEFT JOIN beverage_sales s ON s.beverage_entry_id = e.id
      GROUP BY e.id
      ORDER BY e.entry_date DESC, e.id DESC
    ")->fetchAll();
  }

  public static function salesByEntryIds(array $entryIds): array
  {
    self::ensureSchema();
    $entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds))));
    if (empty($entryIds)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
    $st = Database::conn()->prepare("
      SELECT *
      FROM beverage_sales
      WHERE beverage_entry_id IN ($placeholders)
      ORDER BY sale_date DESC, id DESC
    ");
    $st->execute($entryIds);

    $grouped = [];
    foreach ($st->fetchAll() as $row) {
      $grouped[(int)$row['beverage_entry_id']][] = $row;
    }
    return $grouped;
  }

  public static function createEntry(array $data): int
  {
    self::ensureSchema();
    $data = self::validateEntry($data);
    $st = Database::conn()->prepare("
      INSERT INTO beverage_entries (beverage_product_id, package_quantity, entry_date, expiry_date, expiry_warning_days, is_active, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
      $data['beverage_product_id'],
      $data['package_quantity'],
      $data['entry_date'],
      $data['expiry_date'],
      $data['expiry_warning_days'],
      $data['is_active'],
      $data['notes'],
    ]);
    return (int)Database::conn()->lastInsertId();
  }

  public static function updateEntry(int $id, array $data): void
  {
    self::ensureSchema();
    $current = self::findEntry($id);
    if (!$current) {
      throw new RuntimeException('El registro de bebida no existe.');
    }

    $data = self::validateEntry($data, (int)$current['beverage_product_id']);
    $newTotal = $data['package_quantity'];
    $sold = self::soldUnits($id);
    if ($newTotal < $sold) {
      throw new RuntimeException('La nueva cantidad no puede ser menor a las unidades ya vendidas.');
    }

    $st = Database::conn()->prepare("
      UPDATE beverage_entries
      SET beverage_product_id=?, package_quantity=?, entry_date=?, expiry_date=?, expiry_warning_days=?, is_active=?, notes=?
      WHERE id=?
    ");
    $st->execute([
      $data['beverage_product_id'],
      $data['package_quantity'],
      $data['entry_date'],
      $data['expiry_date'],
      $data['expiry_warning_days'],
      $data['is_active'],
      $data['notes'],
      $id,
    ]);
  }

  public static function deleteEntry(int $id): void
  {
    self::ensureSchema();
    $pdo = Database::conn();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
      $pdo->beginTransaction();
    }
    try {
      $pdo->prepare("DELETE FROM beverage_sales WHERE beverage_entry_id=?")->execute([$id]);
      $pdo->prepare("DELETE FROM beverage_entries WHERE id=?")->execute([$id]);
      if ($ownsTransaction) {
        $pdo->commit();
      }
    } catch (Throwable $e) {
      if ($ownsTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function registerSale(int $entryId, array $data): void
  {
    self::ensureSchema();
    $entry = self::findEntry($entryId);
    if (!$entry) {
      throw new RuntimeException('El producto seleccionado no existe.');
    }
    if ((int)$entry['is_active'] !== 1) {
      throw new RuntimeException('No puedes vender un producto inactivo.');
    }

    $saleDate = trim((string)($data['sale_date'] ?? ''));
    $parsed = DateTime::createFromFormat('Y-m-d', $saleDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $saleDate) {
      throw new RuntimeException('La fecha de venta no es valida.');
    }

    $unitsSoldRaw = trim((string)($data['units_sold'] ?? ''));
    if (!ctype_digit($unitsSoldRaw) || (int)$unitsSoldRaw <= 0) {
      throw new RuntimeException('La cantidad vendida debe ser un numero entero mayor a cero.');
    }

    $unitsSold = (int)$unitsSoldRaw;
    if ($unitsSold > (int)$entry['remaining_units']) {
      throw new RuntimeException('La venta supera el stock disponible.');
    }

    $notes = trim((string)($data['sale_notes'] ?? ''));
    $st = Database::conn()->prepare("
      INSERT INTO beverage_sales (beverage_entry_id, sale_date, units_sold, notes)
      VALUES (?, ?, ?, ?)
    ");
    $st->execute([$entryId, $saleDate, $unitsSold, $notes !== '' ? $notes : null]);
  }

  public static function expiryStatus(string $expiryDate, int $warningDays = 3): string
  {
    $today = DateTime::createFromFormat('!Y-m-d', date('Y-m-d'));
    $expiry = DateTime::createFromFormat('!Y-m-d', $expiryDate);
    if (!$expiry) {
      return 'unknown';
    }
    if ($expiry < $today) {
      return 'expired';
    }
    $warningDays = max(0, $warningDays);
    $limit = (clone $today)->modify('+' . $warningDays . ' days');
    return $expiry <= $limit ? 'warning' : 'ok';
  }

  private static function findEntry(int $id): ?array
  {
    $st = Database::conn()->prepare("
      SELECT
        e.*,
        p.name AS product_name,
        p.units_per_package,
        e.package_quantity AS total_units,
        COALESCE(SUM(s.units_sold), 0) AS sold_units,
        (e.package_quantity - COALESCE(SUM(s.units_sold), 0)) AS remaining_units
      FROM beverage_entries e
      JOIN beverage_products p ON p.id = e.beverage_product_id
      LEFT JOIN beverage_sales s ON s.beverage_entry_id = e.id
      WHERE e.id=?
      GROUP BY e.id
      LIMIT 1
    ");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  private static function findProduct(int $id): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM beverage_products WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  private static function soldUnits(int $entryId): int
  {
    $st = Database::conn()->prepare("SELECT COALESCE(SUM(units_sold), 0) FROM beverage_sales WHERE beverage_entry_id=?");
    $st->execute([$entryId]);
    return (int)$st->fetchColumn();
  }

  private static function validateEntry(array $data, ?int $allowedInactiveProductId = null): array
  {
    $productId = (int)($data['beverage_product_id'] ?? 0);
    $product = self::findProduct($productId);
    if (!$product || ((int)$product['is_active'] !== 1 && $productId !== $allowedInactiveProductId)) {
      throw new RuntimeException('Debes seleccionar una bebida activa.');
    }

    $packageQuantityRaw = trim((string)($data['package_quantity'] ?? ''));
    if (!ctype_digit($packageQuantityRaw) || (int)$packageQuantityRaw <= 0) {
      throw new RuntimeException('La cantidad debe ser un numero entero mayor a cero.');
    }

    $entryDate = trim((string)($data['entry_date'] ?? date('Y-m-d')));
    $expiryDate = trim((string)($data['expiry_date'] ?? ''));
    foreach ([$entryDate, $expiryDate] as $date) {
      $parsed = DateTime::createFromFormat('Y-m-d', $date);
      if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new RuntimeException('Las fechas ingresadas no son validas.');
      }
    }

    $notes = trim((string)($data['notes'] ?? ''));
    $warningDaysRaw = trim((string)($data['expiry_warning_days'] ?? '3'));
    if (!ctype_digit($warningDaysRaw)) {
      throw new RuntimeException('Los dias de aviso deben ser un numero entero.');
    }

    return [
      'beverage_product_id' => $productId,
      'package_quantity' => (int)$packageQuantityRaw,
      'entry_date' => $entryDate,
      'expiry_date' => $expiryDate,
      'expiry_warning_days' => (int)$warningDaysRaw,
      'is_active' => !empty($data['is_active']) ? 1 : 0,
      'notes' => $notes !== '' ? $notes : null,
    ];
  }

  private static function ensureEntryWarningDaysColumn(PDO $pdo): void
  {
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'beverage_entries'
        AND COLUMN_NAME = 'expiry_warning_days'
    ");
    $st->execute();
    if ((int)$st->fetchColumn() === 0) {
      $pdo->exec("ALTER TABLE beverage_entries ADD COLUMN expiry_warning_days INT NOT NULL DEFAULT 3 AFTER expiry_date");
    }
  }

  private static function validateProduct(array $data): array
  {
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('Debes ingresar el nombre de la bebida.');
    }

    $unitsRaw = trim((string)($data['units_per_package'] ?? ''));
    if (!ctype_digit($unitsRaw) || (int)$unitsRaw <= 0) {
      throw new RuntimeException('La cantidad debe ser un numero entero mayor a cero.');
    }

    return [
      'name' => $name,
      'units_per_package' => (int)$unitsRaw,
      'is_active' => !empty($data['is_active']) ? 1 : 0,
    ];
  }

  private static function seedProducts(): void
  {
    $defaults = [
      ['Pilsen personal', 24],
      ['Cusqueña de trigo personal', 24],
      ['Pilsen grande', 12],
      ['Cusqueña grande', 12],
      ['Inca Kola 600', 1],
      ['Inca Kola 500', 1],
      ['Coca Cola 600', 1],
      ['Coca Cola 500', 1],
    ];

    $st = Database::conn()->prepare("
      INSERT IGNORE INTO beverage_products (name, units_per_package, is_active)
      VALUES (?, ?, 1)
    ");
    foreach ($defaults as [$name, $unitsPerPackage]) {
      $st->execute([$name, $unitsPerPackage]);
    }
  }
}
