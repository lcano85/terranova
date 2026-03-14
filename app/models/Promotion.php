<?php
require_once __DIR__ . '/../core/Database.php';

class Promotion
{
  /**
   * Devuelve promociones (todas)
   */
  public static function all(): array
  {
    $sql = "SELECT * FROM promotions ORDER BY weekday ASC, shift ASC, id DESC";
    return Database::conn()->query($sql)->fetchAll();
  }

  public static function find(int $id): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM promotions WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function create(int $weekday, string $shift, string $title, string $content, int $isActive = 1): int
  {
    $pdo = Database::conn();

    $st = $pdo->prepare("INSERT INTO promotions (weekday, shift, title, content, is_active) VALUES (?,?,?,?,?)");
    $st->execute([$weekday, $shift, $title, $content, $isActive]);

    return (int)$pdo->lastInsertId();
  }

  public static function update(int $id, int $weekday, string $shift, string $title, string $content, int $isActive = 1): void
  {
    $pdo = Database::conn();
    $st = $pdo->prepare("UPDATE promotions SET weekday=?, shift=?, title=?, content=?, is_active=?, updated_at=NOW() WHERE id=?");
    $st->execute([$weekday, $shift, $title, $content, $isActive, $id]);
  }

  public static function delete(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM promotions WHERE id=?");
    $st->execute([$id]);
  }

  /**
   * Retorna la promo del día (Lun-Sáb) según turno.
   * - weekday: 1=Lun ... 6=Sab
   * - shift: 'morning'|'afternoon'
   */
  public static function forDayAndShift(int $weekday, string $shift): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM promotions WHERE weekday=? AND shift=? AND is_active=1 ORDER BY id DESC LIMIT 1");
    $st->execute([$weekday, $shift]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function forDayAnyShift(int $weekday): ?array
  {
    $st = Database::conn()->prepare("SELECT * FROM promotions WHERE weekday=? AND is_active=1 ORDER BY FIELD(shift,'morning','afternoon'), id DESC LIMIT 1");
    $st->execute([$weekday]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function weekdayLabel(int $weekday): string
  {
    $map = [
      1 => 'Lunes',
      2 => 'Martes',
      3 => 'Miércoles',
      4 => 'Jueves',
      5 => 'Viernes',
      6 => 'Sábado',
    ];
    return $map[$weekday] ?? '—';
  }

  public static function shiftLabel(string $shift): string
  {
    return $shift === 'afternoon' ? 'Tarde' : 'Mañana';
  }
}
