<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Supply.php';
require_once __DIR__ . '/UnitMeasure.php';

class Requirement
{
  private static bool $schemaEnsured = false;

  private static function normalizeItemName(string $itemName): string
  {
    $clean = preg_replace('/\s+/u', ' ', trim($itemName)) ?? trim($itemName);
    if (function_exists('mb_strtolower')) {
      return mb_strtolower($clean, 'UTF-8');
    }
    return strtolower($clean);
  }

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $columns = $pdo->query("SHOW COLUMNS FROM requirements")->fetchAll();
    $existing = [];
    foreach ($columns as $column) {
      $existing[$column['Field']] = true;
    }

    if (empty($existing['status'])) {
      $pdo->exec("ALTER TABLE requirements ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'submitted' AFTER week_end");
    }

    if (empty($existing['submitted_at'])) {
      $pdo->exec("ALTER TABLE requirements ADD COLUMN submitted_at DATETIME NULL AFTER status");
      $pdo->exec("UPDATE requirements SET submitted_at = COALESCE(submitted_at, created_at, NOW()) WHERE status = 'submitted'");
    }

    $itemColumns = $pdo->query("SHOW COLUMNS FROM requirement_items")->fetchAll();
    $existingItemColumns = [];
    foreach ($itemColumns as $column) {
      $existingItemColumns[$column['Field']] = true;
    }

    if (empty($existingItemColumns['supply_id'])) {
      Supply::ensureSchema();
      $pdo->exec("ALTER TABLE requirement_items ADD COLUMN supply_id INT NULL AFTER requirement_id");
      $pdo->exec("ALTER TABLE requirement_items ADD KEY idx_requirement_items_supply (supply_id)");
    }

    if (empty($existingItemColumns['quantity'])) {
      $pdo->exec("ALTER TABLE requirement_items ADD COLUMN quantity DECIMAL(12,2) NULL AFTER item_name");
    }

    if (empty($existingItemColumns['unit_measure_id'])) {
      UnitMeasure::ensureSchema();
      $pdo->exec("ALTER TABLE requirement_items ADD COLUMN unit_measure_id INT NULL AFTER quantity");
      $pdo->exec("ALTER TABLE requirement_items ADD KEY idx_requirement_items_unit_measure (unit_measure_id)");
    }

    self::$schemaEnsured = true;
  }

  public static function normalizeWeekStart(?string $weekStart = null): string
  {
    $week = self::weekRangeForDate($weekStart);
    return $week['from'];
  }

  public static function weekRangeForDate(?string $date = null): array
  {
    $base = $date ? new DateTime($date) : new DateTime();
    $weekStart = clone $base;
    $weekStart->modify('monday this week');
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');

    return [
      'from' => $weekStart->format('Y-m-d'),
      'to' => $weekEnd->format('Y-m-d')
    ];
  }

  public static function nextAllowedDate(?string $baseDate = null): string
  {
    $date = $baseDate ? new DateTime($baseDate) : new DateTime();
    return $date->format('Y-m-d');
  }

  public static function isAllowedDate(string $date): bool
  {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
      return false;
    }

    $today = new DateTime('today');
    $dt->setTime(0, 0, 0);
    return $dt >= $today;
  }

  public static function create(int $userId, int $purchaseAreaId, string $requiredDate, array $items, string $status = 'submitted'): int
  {
    self::ensureSchema();
    $pdo = Database::conn();
    $week = self::weekRangeForDate($requiredDate);
    $status = $status === 'draft' ? 'draft' : 'submitted';
    $submittedAt = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        INSERT INTO requirements (user_id, purchase_area_id, required_date, week_start, week_end, status, submitted_at)
        VALUES (?,?,?,?,?,?,?)
      ");
      $st->execute([$userId, $purchaseAreaId, $requiredDate, $week['from'], $week['to'], $status, $submittedAt]);
      $requirementId = (int)$pdo->lastInsertId();

      $itemSt = $pdo->prepare("
        INSERT INTO requirement_items (requirement_id, supply_id, item_name, quantity, unit_measure_id, is_purchased)
        VALUES (?,?,?,?,?,0)
      ");

      foreach ($items as $item) {
        if (is_array($item)) {
          $itemSt->execute([
            $requirementId,
            !empty($item['supply_id']) ? (int)$item['supply_id'] : null,
            $item['item_name'],
            $item['quantity'] ?? null,
            !empty($item['unit_measure_id']) ? (int)$item['unit_measure_id'] : null,
          ]);
          continue;
        }

        $itemSt->execute([$requirementId, null, $item, null, null]);
      }

      $pdo->commit();
      return $requirementId;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function forWorkerWeek(int $userId, string $weekStart): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.required_date,
        r.week_start,
        r.status,
        r.submitted_at,
        pa.name AS purchase_area_name,
        ri.id AS item_id,
        ri.supply_id,
        ri.item_name,
        ri.quantity,
        ri.unit_measure_id,
        um.name AS unit_measure_name,
        um.abbreviation AS unit_measure_abbreviation,
        ri.is_purchased
      FROM requirements r
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      LEFT JOIN unit_measures um ON um.id = ri.unit_measure_id
      WHERE r.user_id=?
        AND r.week_start=?
      ORDER BY r.required_date ASC, pa.name ASC, ri.id ASC
    ");
    $st->execute([$userId, $weekStart]);
    return $st->fetchAll();
  }

  public static function forAdminWeek(string $weekStart): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.required_date,
        r.week_start,
        r.status,
        r.submitted_at,
        r.user_id,
        u.role AS user_role,
        u.first_name,
        u.last_name,
        pa.name AS purchase_area_name,
        ri.id AS item_id,
        ri.supply_id,
        ri.item_name,
        ri.quantity,
        ri.unit_measure_id,
        um.name AS unit_measure_name,
        um.abbreviation AS unit_measure_abbreviation,
        ri.is_purchased
      FROM requirements r
      JOIN users u ON u.id = r.user_id
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      LEFT JOIN unit_measures um ON um.id = ri.unit_measure_id
      WHERE r.week_start=?
        AND u.role IN ('admin', 'worker')
      ORDER BY u.first_name ASC, u.last_name ASC, pa.name ASC, r.required_date ASC, ri.id ASC
    ");
    $st->execute([$weekStart]);
    return $st->fetchAll();
  }

  public static function setPurchased(int $itemId, int $isPurchased): void
  {
    self::ensureSchema();
    $purchasedAt = $isPurchased === 1 ? date('Y-m-d H:i:s') : null;
    $st = Database::conn()->prepare("
      UPDATE requirement_items
      SET is_purchased=?, purchased_at=?
      WHERE id=?
    ");
    $st->execute([$isPurchased, $purchasedAt, $itemId]);
  }

  public static function sanitizeItems(array $items): array
  {
    $uniqueItems = [];
    $duplicates = [];
    $seen = [];

    foreach ($items as $item) {
      $clean = preg_replace('/\s+/u', ' ', trim((string)$item)) ?? trim((string)$item);
      if ($clean === '') {
        continue;
      }

      $normalized = self::normalizeItemName($clean);
      if (isset($seen[$normalized])) {
        $duplicates[] = $clean;
        continue;
      }

      $seen[$normalized] = true;
      $uniqueItems[] = $clean;
    }

    return [
      'items' => $uniqueItems,
      'duplicates' => $duplicates,
    ];
  }

  public static function itemDisplayName(array $item): string
  {
    $name = trim((string)($item['item_name'] ?? ''));
    $quantity = $item['quantity'] ?? null;
    $unit = trim((string)($item['unit_measure_abbreviation'] ?? ''));
    if ($unit === '') {
      $unit = trim((string)($item['unit_measure_name'] ?? ''));
    }

    if ($quantity === null && $unit === '') {
      return $name;
    }

    $quantityText = '';
    if ($quantity !== null && $quantity !== '') {
      $quantityText = rtrim(rtrim(number_format((float)$quantity, 2, '.', ''), '0'), '.');
    }

    return trim($name . ' - ' . trim($quantityText . ' ' . $unit));
  }

  public static function sanitizeStructuredItems(array $itemNames, array $supplyIds, array $quantities, array $unitMeasureIds, int $purchaseAreaId = 0): array
  {
    self::ensureSchema();
    $uniqueItems = [];
    $duplicates = [];
    $seen = [];

    $max = max(count($itemNames), count($supplyIds), count($quantities), count($unitMeasureIds));
    for ($i = 0; $i < $max; $i++) {
      $itemName = preg_replace('/\s+/u', ' ', trim((string)($itemNames[$i] ?? ''))) ?? trim((string)($itemNames[$i] ?? ''));
      $supplyId = (int)($supplyIds[$i] ?? 0);
      $rawQuantity = trim((string)($quantities[$i] ?? ''));
      $unitMeasureId = (int)($unitMeasureIds[$i] ?? 0);

      if ($itemName === '' && $supplyId <= 0 && $rawQuantity === '' && $unitMeasureId <= 0) {
        continue;
      }

      if ($itemName === '' || $supplyId <= 0) {
        throw new RuntimeException('Debes seleccionar un producto registrado en insumos en todas las filas.');
      }

      $supplySt = Database::conn()->prepare("
        SELECT s.id, s.name
        FROM supplies s
        JOIN supply_purchase_areas spa ON spa.supply_id = s.id
        WHERE s.id=?
          AND s.is_active=1
          AND (? = 0 OR spa.purchase_area_id=?)
        LIMIT 1
      ");
      $supplySt->execute([$supplyId, $purchaseAreaId, $purchaseAreaId]);
      $supply = $supplySt->fetch();
      if (!$supply) {
        throw new RuntimeException('El producto ' . $itemName . ' no pertenece al area seleccionada o esta inactivo.');
      }
      if ($rawQuantity === '' || !is_numeric(str_replace(',', '.', $rawQuantity))) {
        throw new RuntimeException('Debes ingresar una cantidad valida para ' . $itemName . '.');
      }
      $quantity = (float)str_replace(',', '.', $rawQuantity);
      if ($quantity <= 0) {
        throw new RuntimeException('La cantidad debe ser mayor a cero para ' . $itemName . '.');
      }
      if ($unitMeasureId <= 0 || !UnitMeasure::find($unitMeasureId)) {
        throw new RuntimeException('Debes seleccionar una unidad de medida para ' . $itemName . '.');
      }

      $itemName = (string)$supply['name'];
      $normalized = self::normalizeItemName($itemName);
      if (isset($seen[$normalized])) {
        $duplicates[] = $itemName;
        continue;
      }

      $seen[$normalized] = true;
      $uniqueItems[] = [
        'supply_id' => $supplyId > 0 ? $supplyId : null,
        'item_name' => $itemName,
        'quantity' => $quantity,
        'unit_measure_id' => $unitMeasureId,
      ];
    }

    return [
      'items' => $uniqueItems,
      'duplicates' => $duplicates,
    ];
  }

  public static function duplicateItemsForWorkerSlot(int $userId, int $purchaseAreaId, string $requiredDate, array $items): array
  {
    self::ensureSchema();
    if (empty($items)) {
      return [];
    }

    $week = self::weekRangeForDate($requiredDate);
    $st = Database::conn()->prepare("
      SELECT ri.item_name
      FROM requirements r
      JOIN requirement_items ri ON ri.requirement_id = r.id
      WHERE r.user_id=?
        AND r.purchase_area_id=?
        AND r.required_date=?
        AND r.week_start=?
    ");
    $st->execute([$userId, $purchaseAreaId, $requiredDate, $week['from']]);
    $existingRows = $st->fetchAll();

    $existingMap = [];
    foreach ($existingRows as $row) {
      $itemName = (string)($row['item_name'] ?? '');
      $existingMap[self::normalizeItemName($itemName)] = $itemName;
    }

    $duplicates = [];
    foreach ($items as $item) {
      $itemName = is_array($item) ? (string)($item['item_name'] ?? '') : (string)$item;
      $normalized = self::normalizeItemName($itemName);
      if (isset($existingMap[$normalized])) {
        $duplicates[] = $existingMap[$normalized];
      }
    }

    return array_values(array_unique($duplicates));
  }

  public static function deleteItem(int $itemId, ?int $workerId = null, bool $draftOnly = false): void
  {
    self::ensureSchema();
    $pdo = Database::conn();

    $where = "ri.id=?";
    $params = [$itemId];

    if ($workerId !== null) {
      $where .= " AND r.user_id=?";
      $params[] = $workerId;
    }

    if ($draftOnly) {
      $where .= " AND r.status='draft'";
    }

    $st = $pdo->prepare("
      SELECT ri.requirement_id
      FROM requirement_items ri
      JOIN requirements r ON r.id = ri.requirement_id
      WHERE {$where}
      LIMIT 1
    ");
    $st->execute($params);
    $row = $st->fetch();
    if (!$row) {
      throw new RuntimeException('No se pudo eliminar el item solicitado.');
    }

    $pdo->beginTransaction();
    try {
      $delete = $pdo->prepare("DELETE FROM requirement_items WHERE id=?");
      $delete->execute([$itemId]);

      $count = $pdo->prepare("SELECT COUNT(*) FROM requirement_items WHERE requirement_id=?");
      $count->execute([(int)$row['requirement_id']]);
      if ((int)$count->fetchColumn() === 0) {
        $deleteRequirement = $pdo->prepare("DELETE FROM requirements WHERE id=?");
        $deleteRequirement->execute([(int)$row['requirement_id']]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function submitWorkerWeek(int $userId, string $weekStart): ?int
  {
    self::ensureSchema();
    $pdo = Database::conn();

    $find = $pdo->prepare("
      SELECT id
      FROM requirements
      WHERE user_id=?
        AND week_start=?
        AND status='draft'
      ORDER BY id ASC
      LIMIT 1
    ");
    $find->execute([$userId, $weekStart]);
    $firstId = $find->fetchColumn();
    if (!$firstId) {
      return null;
    }

    $update = $pdo->prepare("
      UPDATE requirements
      SET status='submitted', submitted_at=NOW()
      WHERE user_id=?
        AND week_start=?
        AND status='draft'
    ");
    $update->execute([$userId, $weekStart]);

    return (int)$firstId;
  }

  public static function detailForNotification(int $requirementId): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.user_id,
        r.required_date,
        r.week_start,
        u.first_name,
        u.last_name,
        u.document_number,
        wa.name AS worker_area_name,
        pa.name AS purchase_area_name,
        ri.item_name,
        ri.quantity,
        ri.unit_measure_id,
        um.name AS unit_measure_name,
        um.abbreviation AS unit_measure_abbreviation
      FROM requirements r
      JOIN users u ON u.id = r.user_id
      LEFT JOIN work_areas wa ON wa.id = u.area_id
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      LEFT JOIN unit_measures um ON um.id = ri.unit_measure_id
      WHERE r.id=?
      ORDER BY ri.id ASC
    ");
    $st->execute([$requirementId]);
    $rows = $st->fetchAll();
    if (empty($rows)) {
      return null;
    }

    $first = $rows[0];
    return [
      'requirement_id' => (int)$first['requirement_id'],
      'user_id' => (int)$first['user_id'],
      'required_date' => $first['required_date'],
      'week_start' => $first['week_start'],
      'worker_name' => trim($first['first_name'] . ' ' . $first['last_name']),
      'document_number' => $first['document_number'],
      'worker_area_name' => $first['worker_area_name'],
      'purchase_area_name' => $first['purchase_area_name'],
      'items' => array_map(static fn($row) => self::itemDisplayName($row), $rows),
    ];
  }

  public static function weeklyDetailForNotification(int $userId, string $weekStart): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.required_date,
        r.week_start,
        u.first_name,
        u.last_name,
        u.document_number,
        wa.name AS worker_area_name,
        pa.name AS purchase_area_name,
        ri.item_name,
        ri.quantity,
        ri.unit_measure_id,
        um.name AS unit_measure_name,
        um.abbreviation AS unit_measure_abbreviation
      FROM requirements r
      JOIN users u ON u.id = r.user_id
      LEFT JOIN work_areas wa ON wa.id = u.area_id
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      LEFT JOIN unit_measures um ON um.id = ri.unit_measure_id
      WHERE r.user_id=?
        AND r.week_start=?
        AND r.status='submitted'
        AND ri.is_purchased=0
      ORDER BY r.required_date ASC, pa.name ASC, ri.id ASC
    ");
    $st->execute([$userId, $weekStart]);
    $rows = $st->fetchAll();
    if (empty($rows)) {
      return [];
    }

    $first = $rows[0];
    $groups = [];
    foreach ($rows as $row) {
      $key = $row['required_date'] . '|' . $row['purchase_area_name'];
      if (!isset($groups[$key])) {
        $groups[$key] = [
          'required_date' => $row['required_date'],
          'purchase_area_name' => $row['purchase_area_name'],
          'items' => [],
        ];
      }
      $groups[$key]['items'][] = self::itemDisplayName($row);
    }

    return [
      'week_start' => $first['week_start'],
      'worker_name' => trim($first['first_name'] . ' ' . $first['last_name']),
      'document_number' => $first['document_number'],
      'worker_area_name' => $first['worker_area_name'],
      'groups' => array_values($groups),
    ];
  }

  public static function weekOptions(int $limit = 8, ?string $baseDate = null): array
  {
    $base = $baseDate ? new DateTime($baseDate) : new DateTime();
    $week = self::weekRangeForDate($base->format('Y-m-d'));
    $current = new DateTime($week['from']);
    $options = [];

    for ($i = 0; $i < $limit; $i++) {
      $from = $current->format('Y-m-d');
      $to = (clone $current)->modify('+6 days')->format('Y-m-d');
      $options[] = [
        'from' => $from,
        'to' => $to,
        'label' => date('d/m/Y', strtotime($from)) . ' - ' . date('d/m/Y', strtotime($to)),
      ];
      $current->modify('-7 days');
    }

    return $options;
  }
}
