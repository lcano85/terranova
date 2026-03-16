<?php
require_once __DIR__ . '/../core/Database.php';

class Requirement
{
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
    for ($i = 0; $i < 14; $i++) {
      $weekday = (int)$date->format('N');
      if ($weekday === 4 || $weekday === 6) {
        return $date->format('Y-m-d');
      }
      $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
  }

  public static function isAllowedDate(string $date): bool
  {
    $dt = new DateTime($date);
    $weekday = (int)$dt->format('N');
    return $weekday === 4 || $weekday === 6;
  }

  public static function create(int $userId, int $purchaseAreaId, string $requiredDate, array $items): void
  {
    $pdo = Database::conn();
    $week = self::weekRangeForDate($requiredDate);

    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        INSERT INTO requirements (user_id, purchase_area_id, required_date, week_start, week_end)
        VALUES (?,?,?,?,?)
      ");
      $st->execute([$userId, $purchaseAreaId, $requiredDate, $week['from'], $week['to']]);
      $requirementId = (int)$pdo->lastInsertId();

      $itemSt = $pdo->prepare("
        INSERT INTO requirement_items (requirement_id, item_name, is_purchased)
        VALUES (?,?,0)
      ");

      foreach ($items as $itemName) {
        $itemSt->execute([$requirementId, $itemName]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function forWorkerWeek(int $userId, string $weekStart): array
  {
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.required_date,
        r.week_start,
        pa.name AS purchase_area_name,
        ri.id AS item_id,
        ri.item_name,
        ri.is_purchased
      FROM requirements r
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      WHERE r.user_id=?
        AND r.week_start=?
      ORDER BY r.required_date ASC, pa.name ASC, ri.id ASC
    ");
    $st->execute([$userId, $weekStart]);
    return $st->fetchAll();
  }

  public static function forAdminWeek(string $weekStart): array
  {
    $st = Database::conn()->prepare("
      SELECT
        r.id AS requirement_id,
        r.required_date,
        r.week_start,
        r.user_id,
        u.first_name,
        u.last_name,
        pa.name AS purchase_area_name,
        ri.id AS item_id,
        ri.item_name,
        ri.is_purchased
      FROM requirements r
      JOIN users u ON u.id = r.user_id
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
      WHERE r.week_start=?
        AND u.role='worker'
      ORDER BY u.first_name ASC, u.last_name ASC, pa.name ASC, r.required_date ASC, ri.id ASC
    ");
    $st->execute([$weekStart]);
    return $st->fetchAll();
  }

  public static function setPurchased(int $itemId, int $isPurchased): void
  {
    $purchasedAt = $isPurchased === 1 ? date('Y-m-d H:i:s') : null;
    $st = Database::conn()->prepare("
      UPDATE requirement_items
      SET is_purchased=?, purchased_at=?
      WHERE id=?
    ");
    $st->execute([$isPurchased, $purchasedAt, $itemId]);
  }
}
