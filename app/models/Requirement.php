<?php
require_once __DIR__ . '/../core/Database.php';

class Requirement
{
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

  public static function create(int $userId, int $purchaseAreaId, string $requiredDate, array $items): int
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

  public static function detailForNotification(int $requirementId): ?array
  {
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
        ri.item_name
      FROM requirements r
      JOIN users u ON u.id = r.user_id
      LEFT JOIN work_areas wa ON wa.id = u.area_id
      JOIN purchase_areas pa ON pa.id = r.purchase_area_id
      JOIN requirement_items ri ON ri.requirement_id = r.id
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
      'required_date' => $first['required_date'],
      'week_start' => $first['week_start'],
      'worker_name' => trim($first['first_name'] . ' ' . $first['last_name']),
      'document_number' => $first['document_number'],
      'worker_area_name' => $first['worker_area_name'],
      'purchase_area_name' => $first['purchase_area_name'],
      'items' => array_map(static fn($row) => $row['item_name'], $rows),
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
