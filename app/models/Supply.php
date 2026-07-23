<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/PurchaseArea.php';

class Supply
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS supplies (
        id INT NOT NULL AUTO_INCREMENT,
        purchase_area_id INT NOT NULL,
        name VARCHAR(180) NOT NULL,
        normalized_name VARCHAR(180) NOT NULL,
        price DECIMAL(12,2) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_supplies_area_name (purchase_area_id, normalized_name),
        KEY idx_supplies_purchase_area (purchase_area_id),
        KEY idx_supplies_active (is_active),
        CONSTRAINT fk_supplies_purchase_area
          FOREIGN KEY (purchase_area_id) REFERENCES purchase_areas (id)
          ON DELETE RESTRICT ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS supply_purchase_areas (
        supply_id INT NOT NULL,
        purchase_area_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (supply_id, purchase_area_id),
        KEY idx_supply_purchase_areas_area (purchase_area_id),
        CONSTRAINT fk_supply_purchase_areas_supply
          FOREIGN KEY (supply_id) REFERENCES supplies (id)
          ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_supply_purchase_areas_area
          FOREIGN KEY (purchase_area_id) REFERENCES purchase_areas (id)
          ON DELETE RESTRICT ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    Database::conn()->exec("
      INSERT IGNORE INTO supply_purchase_areas (supply_id, purchase_area_id)
      SELECT id, purchase_area_id
      FROM supplies
      WHERE purchase_area_id IS NOT NULL
    ");

    $pdo = Database::conn();
    $priceColumn = $pdo->query("SHOW COLUMNS FROM supplies LIKE 'price'")->fetch();
    if (!$priceColumn) {
      $pdo->exec("ALTER TABLE supplies ADD COLUMN price DECIMAL(12,2) NULL AFTER normalized_name");
    }

    $hasUniqueNameIndex = $pdo->query("
      SHOW INDEX FROM supplies WHERE Key_name = 'uq_supplies_normalized_name'
    ")->fetch();

    if (!$hasUniqueNameIndex) {
      $duplicateNames = (int)$pdo->query("
        SELECT COUNT(*)
        FROM (
          SELECT normalized_name
          FROM supplies
          GROUP BY normalized_name
          HAVING COUNT(*) > 1
        ) duplicated
      ")->fetchColumn();

      if ($duplicateNames === 0) {
        $pdo->exec("ALTER TABLE supplies ADD UNIQUE KEY uq_supplies_normalized_name (normalized_name)");
      }
    }

    self::$schemaEnsured = true;
  }

  public static function paginate(string $search = '', ?int $purchaseAreaId = null, ?int $status = null, int $page = 1, int $perPage = 10): array
  {
    self::ensureSchema();

    $page = max(1, $page);
    $allowedPerPage = [10, 20, 50, 100];
    if (!in_array($perPage, $allowedPerPage, true)) {
      $perPage = 10;
    }

    [$where, $params] = self::filters($search, $purchaseAreaId, $status);

    $count = Database::conn()->prepare("
      SELECT COUNT(DISTINCT s.id)
      FROM supplies s
      {$where}
    ");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = "
      SELECT
        s.id,
        s.purchase_area_id,
        s.name,
        s.normalized_name,
        s.price,
        s.is_active,
        s.created_at,
        s.updated_at,
        GROUP_CONCAT(pa.id ORDER BY pa.name ASC SEPARATOR ',') AS purchase_area_ids,
        GROUP_CONCAT(pa.name ORDER BY pa.name ASC SEPARATOR ', ') AS purchase_area_names
      FROM supplies s
      LEFT JOIN supply_purchase_areas spa ON spa.supply_id = s.id
      LEFT JOIN purchase_areas pa ON pa.id = spa.purchase_area_id
      {$where}
      GROUP BY s.id, s.purchase_area_id, s.name, s.normalized_name, s.price, s.is_active, s.created_at, s.updated_at
      ORDER BY s.name ASC
      LIMIT {$perPage} OFFSET {$offset}
    ";
    $st = Database::conn()->prepare($sql);
    $st->execute($params);

    return [
      'rows' => $st->fetchAll(),
      'meta' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'page_param' => 'supplies_page',
        'per_page_param' => 'supplies_per_page',
        'allowed_per_page' => $allowedPerPage,
      ],
    ];
  }

  public static function create(array $data): void
  {
    self::ensureSchema();
    $payload = self::payload($data);
    self::assertNoDuplicateName(self::normalize($payload['name']));

    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        INSERT INTO supplies (purchase_area_id, name, normalized_name, price, is_active)
        VALUES (?,?,?,?,?)
      ");
      $st->execute([
        $payload['primary_purchase_area_id'],
        $payload['name'],
        self::normalize($payload['name']),
        $payload['price'],
        $payload['is_active'],
      ]);

      self::syncAreas((int)$pdo->lastInsertId(), $payload['purchase_area_ids']);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function update(int $id, array $data): void
  {
    self::ensureSchema();
    if ($id <= 0) {
      throw new RuntimeException('Insumo no valido.');
    }

    $payload = self::payload($data);
    self::assertNoDuplicateName(self::normalize($payload['name']), $id);
    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        UPDATE supplies
        SET purchase_area_id=?, name=?, normalized_name=?, price=?, is_active=?, updated_at=NOW()
        WHERE id=?
      ");
      $st->execute([
        $payload['primary_purchase_area_id'],
        $payload['name'],
        self::normalize($payload['name']),
        $payload['price'],
        $payload['is_active'],
        $id,
      ]);

      self::syncAreas($id, $payload['purchase_area_ids']);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function setActive(int $id, int $isActive): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE supplies SET is_active=?, updated_at=NOW() WHERE id=?");
    $st->execute([$isActive === 1 ? 1 : 0, $id]);
  }

  public static function activeForRequirements(): array
  {
    self::ensureSchema();
    $st = Database::conn()->query("
      SELECT
        s.id,
        s.name,
        s.price,
        GROUP_CONCAT(spa.purchase_area_id ORDER BY spa.purchase_area_id ASC SEPARATOR ',') AS purchase_area_ids
      FROM supplies s
      JOIN supply_purchase_areas spa ON spa.supply_id = s.id
      WHERE s.is_active=1
      GROUP BY s.id, s.name, s.price
      ORDER BY s.name ASC
    ");
    return $st->fetchAll();
  }

  private static function payload(array $data): array
  {
    $name = preg_replace('/\s+/u', ' ', trim((string)($data['name'] ?? ''))) ?? trim((string)($data['name'] ?? ''));
    $purchaseAreaIds = array_values(array_unique(array_filter(array_map(
      'intval',
      (array)($data['purchase_area_ids'] ?? $data['purchase_area_id'] ?? [])
    ), static fn($id) => $id > 0)));

    if ($name === '') {
      throw new RuntimeException('El nombre del insumo es obligatorio.');
    }

    if (empty($purchaseAreaIds)) {
      throw new RuntimeException('Debes seleccionar al menos un area de compra.');
    }

    $rawPrice = trim((string)($data['price'] ?? ''));
    $price = null;
    if ($rawPrice !== '') {
      if (!is_numeric($rawPrice) || (float)$rawPrice < 0) {
        throw new RuntimeException('El precio debe ser un numero mayor o igual a cero.');
      }
      if ((float)$rawPrice > 9999999999.99) {
        throw new RuntimeException('El precio ingresado excede el limite permitido.');
      }
      $price = number_format((float)$rawPrice, 2, '.', '');
    }

    foreach ($purchaseAreaIds as $purchaseAreaId) {
      if (!PurchaseArea::find($purchaseAreaId)) {
        throw new RuntimeException('Debes seleccionar areas de compra validas.');
      }
    }

    return [
      'name' => $name,
      'primary_purchase_area_id' => $purchaseAreaIds[0],
      'purchase_area_ids' => $purchaseAreaIds,
      'price' => $price,
      'is_active' => (int)($data['is_active'] ?? 1) === 1 ? 1 : 0,
    ];
  }

  private static function syncAreas(int $supplyId, array $purchaseAreaIds): void
  {
    $pdo = Database::conn();
    $delete = $pdo->prepare("DELETE FROM supply_purchase_areas WHERE supply_id=?");
    $delete->execute([$supplyId]);

    $insert = $pdo->prepare("
      INSERT INTO supply_purchase_areas (supply_id, purchase_area_id)
      VALUES (?, ?)
    ");

    foreach ($purchaseAreaIds as $purchaseAreaId) {
      $insert->execute([$supplyId, $purchaseAreaId]);
    }
  }

  private static function assertNoDuplicateName(string $normalizedName, ?int $ignoreSupplyId = null): void
  {
    $params = [$normalizedName];
    $ignoreSql = '';

    if ($ignoreSupplyId !== null) {
      $ignoreSql = ' AND s.id <> ?';
      $params[] = $ignoreSupplyId;
    }

    $st = Database::conn()->prepare("
      SELECT s.name
      FROM supplies s
      WHERE s.normalized_name = ?
        {$ignoreSql}
      LIMIT 1
    ");
    $st->execute($params);
    $existingName = $st->fetchColumn();

    if ($existingName) {
      throw new RuntimeException('Ya existe un insumo registrado con ese nombre.');
    }
  }

  private static function filters(string $search, ?int $purchaseAreaId, ?int $status): array
  {
    $where = "WHERE 1=1";
    $params = [];

    $search = trim($search);
    if ($search !== '') {
      $where .= " AND (
        s.name LIKE ?
        OR EXISTS (
          SELECT 1
          FROM supply_purchase_areas spa_search
          JOIN purchase_areas pa_search ON pa_search.id = spa_search.purchase_area_id
          WHERE spa_search.supply_id = s.id
            AND pa_search.name LIKE ?
        )
      )";
      $like = '%' . $search . '%';
      $params[] = $like;
      $params[] = $like;
    }

    if ($purchaseAreaId !== null && $purchaseAreaId > 0) {
      $where .= " AND EXISTS (
        SELECT 1
        FROM supply_purchase_areas spa_filter
        WHERE spa_filter.supply_id = s.id
          AND spa_filter.purchase_area_id=?
      )";
      $params[] = $purchaseAreaId;
    }

    if ($status !== null) {
      $where .= " AND s.is_active=?";
      $params[] = $status === 1 ? 1 : 0;
    }

    return [$where, $params];
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
