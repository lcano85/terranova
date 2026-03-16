<?php
require_once __DIR__ . '/../core/Database.php';

class PurchaseArea
{
  public static function all(): array
  {
    return Database::conn()->query("SELECT * FROM purchase_areas ORDER BY id DESC")->fetchAll();
  }

  public static function find(int $id): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM purchase_areas WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(string $name, int $isActive = 1): void
  {
    $st = Database::conn()->prepare("INSERT INTO purchase_areas (name, is_active) VALUES (?, ?)");
    $st->execute([$name, $isActive]);
  }

  public static function update(int $id, string $name): void
  {
    $st = Database::conn()->prepare("UPDATE purchase_areas SET name=?, updated_at=NOW() WHERE id=?");
    $st->execute([$name, $id]);
  }

  public static function setActive(int $id, int $isActive): void
  {
    $st = Database::conn()->prepare("UPDATE purchase_areas SET is_active=?, updated_at=NOW() WHERE id=?");
    $st->execute([$isActive, $id]);
  }
}
