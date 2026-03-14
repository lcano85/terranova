<?php
require_once __DIR__ . '/../core/Database.php';

class WorkerPayRate {
  public static function getByUser(int $userId): ?array {
    $st = Database::conn()->prepare("SELECT * FROM worker_pay_rates WHERE user_id=? LIMIT 1");
    $st->execute([$userId]);
    $r = $st->fetch();
    return $r ?: null;
  }

  public static function upsert(int $userId, ?float $dailyRate): void {
    $pdo = Database::conn();

    if ($dailyRate === null) {
      $st = $pdo->prepare("DELETE FROM worker_pay_rates WHERE user_id=?");
      $st->execute([$userId]);
      return;
    }

    $st = $pdo->prepare("
      INSERT INTO worker_pay_rates (user_id, daily_rate)
      VALUES (?, ?)
      ON DUPLICATE KEY UPDATE daily_rate=VALUES(daily_rate)
    ");
    $st->execute([$userId, $dailyRate]);
  }
}
