<?php
require_once __DIR__ . '/../core/Database.php';

class Shift {
  public static function all(): array {
    return Database::conn()->query("SELECT * FROM shifts ORDER BY id DESC")->fetchAll();
  }

  public static function find(int $id): ?array {
    $st = Database::conn()->prepare("SELECT * FROM shifts WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
  }

  public static function create(string $name, string $start, string $end): void {
    $st = Database::conn()->prepare("INSERT INTO shifts (name,start_time,end_time) VALUES (?,?,?)");
    $st->execute([$name,$start,$end]);
  }

  public static function update(int $id, string $name, string $start, string $end): void {
    $st = Database::conn()->prepare("UPDATE shifts SET name=?, start_time=?, end_time=? WHERE id=?");
    $st->execute([$name,$start,$end,$id]);
  }

  public static function delete(int $id): void {
    $st = Database::conn()->prepare("DELETE FROM shifts WHERE id=?");
    $st->execute([$id]);
  }
}
