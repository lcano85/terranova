<?php
require_once __DIR__ . '/../core/Database.php';

class InventoryItem
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS inventory_item_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inventory_item_id INT NOT NULL,
        action VARCHAR(20) NOT NULL,
        actor_user_id INT NULL,
        actor_role VARCHAR(20) NULL,
        before_snapshot LONGTEXT NULL,
        after_snapshot LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inventory_item_history_item (inventory_item_id),
        INDEX idx_inventory_item_history_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    self::$schemaEnsured = true;
  }

  public static function byWorker(int $userId, ?int $isActive = null): array
  {
    self::ensureSchema();
    $sql = "
      SELECT ii.*, wa.name AS area_name
      FROM inventory_items ii
      JOIN work_areas wa ON wa.id = ii.area_id
      WHERE ii.user_id=?
    ";
    $params = [$userId];

    if ($isActive !== null) {
      $sql .= " AND ii.is_active=?";
      $params[] = $isActive;
    }

    $sql .= " ORDER BY ii.id DESC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function forAdmin(?int $areaId = null, ?int $isActive = null): array
  {
    self::ensureSchema();
    $sql = "
      SELECT ii.*, wa.name AS area_name, u.first_name, u.last_name, u.document_number
      FROM inventory_items ii
      JOIN work_areas wa ON wa.id = ii.area_id
      JOIN users u ON u.id = ii.user_id
      WHERE u.role='worker'
    ";
    $params = [];

    if ($areaId !== null) {
      $sql .= " AND ii.area_id=?";
      $params[] = $areaId;
    }

    if ($isActive !== null) {
      $sql .= " AND ii.is_active=?";
      $params[] = $isActive;
    }

    $sql .= " ORDER BY wa.name ASC, ii.id DESC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function create(
    int $userId,
    int $areaId,
    string $name,
    float $quantity,
    string $unit,
    ?string $notes,
    ?int $actorUserId = null,
    ?string $actorRole = null
  ): int {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      INSERT INTO inventory_items (user_id, area_id, name, quantity, unit, notes, is_active)
      VALUES (?,?,?,?,?,?,1)
    ");
    $st->execute([$userId, $areaId, $name, $quantity, $unit, $notes]);
    $id = (int)Database::conn()->lastInsertId();
    self::logChange($id, 'create', $actorUserId, $actorRole, null, self::findRaw($id));
    return $id;
  }

  public static function updateByWorker(
    int $id,
    int $userId,
    string $name,
    float $quantity,
    string $unit,
    ?string $notes,
    ?int $actorUserId = null,
    ?string $actorRole = null
  ): void {
    self::ensureSchema();
    $before = self::findRaw($id, $userId);
    $st = Database::conn()->prepare("
      UPDATE inventory_items
      SET name=?, quantity=?, unit=?, notes=?
      WHERE id=? AND user_id=?
    ");
    $st->execute([$name, $quantity, $unit, $notes, $id, $userId]);
    if ($before && $st->rowCount() > 0) {
      self::logChange($id, 'update', $actorUserId, $actorRole, $before, self::findRaw($id));
    }
  }

  public static function updateByAdmin(
    int $id,
    int $userId,
    int $areaId,
    string $name,
    float $quantity,
    string $unit,
    ?string $notes,
    int $isActive,
    ?int $actorUserId = null,
    ?string $actorRole = null
  ): void {
    self::ensureSchema();
    $before = self::findRaw($id);
    $st = Database::conn()->prepare("
      UPDATE inventory_items
      SET user_id=?, area_id=?, name=?, quantity=?, unit=?, notes=?, is_active=?
      WHERE id=?
    ");
    $st->execute([$userId, $areaId, $name, $quantity, $unit, $notes, $isActive, $id]);
    if ($before && $st->rowCount() > 0) {
      self::logChange($id, 'update', $actorUserId, $actorRole, $before, self::findRaw($id));
    }
  }

  public static function setActiveByWorker(int $id, int $userId, int $isActive, ?int $actorUserId = null, ?string $actorRole = null): void
  {
    self::ensureSchema();
    $before = self::findRaw($id, $userId);
    $st = Database::conn()->prepare("
      UPDATE inventory_items
      SET is_active=?
      WHERE id=? AND user_id=?
    ");
    $st->execute([$isActive, $id, $userId]);
    if ($before && $st->rowCount() > 0) {
      self::logChange($id, $isActive === 1 ? 'activate' : 'deactivate', $actorUserId, $actorRole, $before, self::findRaw($id));
    }
  }

  public static function historyForItems(array $itemIds, ?int $ownerUserId = null): array
  {
    self::ensureSchema();
    $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
    if (empty($itemIds)) {
      return [];
    }

    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $params = $itemIds;
    $ownerWhere = '';
    if ($ownerUserId !== null) {
      $ownerWhere = ' AND ii.user_id=?';
      $params[] = $ownerUserId;
    }

    $st = Database::conn()->prepare("
      SELECT h.*, u.first_name, u.last_name, u.document_number
      FROM inventory_item_history h
      JOIN inventory_items ii ON ii.id = h.inventory_item_id
      LEFT JOIN users u ON u.id = h.actor_user_id
      WHERE h.inventory_item_id IN ($placeholders)
        $ownerWhere
      ORDER BY h.created_at DESC, h.id DESC
    ");
    $st->execute($params);

    $grouped = [];
    foreach ($st->fetchAll() as $row) {
      $grouped[(int)$row['inventory_item_id']][] = $row;
    }
    return $grouped;
  }

  private static function findRaw(int $id, ?int $userId = null): ?array
  {
    $sql = "SELECT * FROM inventory_items WHERE id=?";
    $params = [$id];
    if ($userId !== null) {
      $sql .= " AND user_id=?";
      $params[] = $userId;
    }
    $sql .= " LIMIT 1";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
  }

  private static function logChange(int $itemId, string $action, ?int $actorUserId, ?string $actorRole, ?array $before, ?array $after): void
  {
    $st = Database::conn()->prepare("
      INSERT INTO inventory_item_history (inventory_item_id, action, actor_user_id, actor_role, before_snapshot, after_snapshot)
      VALUES (?,?,?,?,?,?)
    ");
    $st->execute([
      $itemId,
      $action,
      $actorUserId,
      $actorRole,
      $before ? json_encode(self::snapshot($before), JSON_UNESCAPED_UNICODE) : null,
      $after ? json_encode(self::snapshot($after), JSON_UNESCAPED_UNICODE) : null,
    ]);
  }

  private static function snapshot(array $row): array
  {
    return [
      'user_id' => (int)($row['user_id'] ?? 0),
      'area_id' => (int)($row['area_id'] ?? 0),
      'name' => (string)($row['name'] ?? ''),
      'quantity' => (float)($row['quantity'] ?? 0),
      'unit' => (string)($row['unit'] ?? ''),
      'notes' => $row['notes'] ?? null,
      'is_active' => (int)($row['is_active'] ?? 0),
    ];
  }
}
