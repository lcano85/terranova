<?php
require_once __DIR__ . '/../core/Database.php';

class InventoryItem
{
  public static function byWorker(int $userId, ?int $isActive = null): array
  {
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
    ?string $notes
  ): void {
    $st = Database::conn()->prepare("
      INSERT INTO inventory_items (user_id, area_id, name, quantity, unit, notes, is_active)
      VALUES (?,?,?,?,?,?,1)
    ");
    $st->execute([$userId, $areaId, $name, $quantity, $unit, $notes]);
  }

  public static function updateByWorker(
    int $id,
    int $userId,
    string $name,
    float $quantity,
    string $unit,
    ?string $notes
  ): void {
    $st = Database::conn()->prepare("
      UPDATE inventory_items
      SET name=?, quantity=?, unit=?, notes=?
      WHERE id=? AND user_id=?
    ");
    $st->execute([$name, $quantity, $unit, $notes, $id, $userId]);
  }

  public static function setActiveByWorker(int $id, int $userId, int $isActive): void
  {
    $st = Database::conn()->prepare("
      UPDATE inventory_items
      SET is_active=?
      WHERE id=? AND user_id=?
    ");
    $st->execute([$isActive, $id, $userId]);
  }
}
