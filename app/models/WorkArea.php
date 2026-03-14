<?php
require_once __DIR__ . '/../core/Database.php';

class WorkArea {
  public static function all(): array {
    return Database::conn()->query("SELECT * FROM work_areas ORDER BY id DESC")->fetchAll();
  }

  public static function find(int $id): ?array {
    $st = Database::conn()->prepare("SELECT * FROM work_areas WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
  }

  public static function create(string $name): void {
    $st = Database::conn()->prepare("INSERT INTO work_areas (name) VALUES (?)");
    $st->execute([$name]);
  }

  public static function update(int $id, string $name): void {
    $st = Database::conn()->prepare("UPDATE work_areas SET name=? WHERE id=?");
    $st->execute([$name,$id]);
  }

  public static function delete(int $id): void {
    // Nota: si un trabajador está asignado a esta área, el FK bloqueará el delete (mejor que borrar por accidente)
    $st = Database::conn()->prepare("DELETE FROM work_areas WHERE id=?");
    $st->execute([$id]);
  }
}
