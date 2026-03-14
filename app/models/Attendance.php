<?php
require_once __DIR__ . '/../core/Database.php';

class Attendance
{
  public static function find(int $id): ?array
  {
    $st = Database::conn()->prepare("
      SELECT a.*, u.first_name, u.last_name, u.document_number, u.document_type
      FROM attendance a
      JOIN users u ON u.id=a.user_id
      WHERE a.id=?
      LIMIT 1
    ");
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function latest(int $limit = 10): array
  {
    $st = Database::conn()->prepare("
      SELECT a.*, u.first_name, u.last_name, u.document_number
      FROM attendance a
      JOIN users u ON u.id=a.user_id
      ORDER BY a.id DESC
      LIMIT ?
    ");
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function byWorker(int $userId, int $limit = 50): array
  {
    $st = Database::conn()->prepare("
      SELECT * FROM attendance
      WHERE user_id=?
      ORDER BY id DESC
      LIMIT ?
    ");
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
  }

  public static function filter(?string $doc, ?string $from, ?string $to): array
  {
    $where = [];
    $params = [];

    if ($doc) {
      $where[] = "u.document_number LIKE ?";
      $params[] = "%$doc%";
    }
    if ($from) {
      $where[] = "DATE(a.marked_at) >= ?";
      $params[] = $from;
    }
    if ($to) {
      $where[] = "DATE(a.marked_at) <= ?";
      $params[] = $to;
    }

    $sql = "
      SELECT a.*, u.first_name, u.last_name, u.document_number
      FROM attendance a
      JOIN users u ON u.id=a.user_id
    ";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY a.id DESC LIMIT 300";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }


  public static function monthlySummary(int $userId, string $fromDate, string $toDate): array
  {
    // Resumen por día: primera entrada y última salida + minutos de tardanza (solo en entradas)
    $st = Database::conn()->prepare("
      SELECT
        DATE(marked_at) AS day,
        MIN(CASE WHEN mark_type='in'  THEN marked_at END) AS first_in,
        MAX(CASE WHEN mark_type='out' THEN marked_at END) AS last_out,
        SUM(CASE WHEN mark_type='in' THEN minutes_late ELSE 0 END) AS minutes_late
      FROM attendance
      WHERE user_id=?
        AND DATE(marked_at) >= ?
        AND DATE(marked_at) <= ?
      GROUP BY DATE(marked_at)
      ORDER BY day ASC
    ");
    $st->execute([$userId, $fromDate, $toDate]);
    $days = $st->fetchAll();

    $totalSeconds = 0;
    $workedDays = 0;
    $totalLate = 0;

    foreach ($days as $d) {
      // Día trabajado: si al menos marcó entrada (first_in)
      if (!empty($d['first_in'])) {
        $workedDays++;
        $totalLate += (int)($d['minutes_late'] ?? 0);

        if (!empty($d['last_out'])) {
          $in  = new DateTime($d['first_in']);
          $out = new DateTime($d['last_out']);
          if ($out > $in) {
            $totalSeconds += ($out->getTimestamp() - $in->getTimestamp());
          }
        }
      }
    }

    return [
      'days' => $days,
      'worked_days' => $workedDays,
      'total_seconds' => $totalSeconds,
      'total_minutes_late' => $totalLate
    ];
  }

  public static function create(
    int $userId,
    string $type,
    string $markedAt,
    int $late,
    ?string $ip,
    ?float $lat,
    ?float $lng,
    ?string $ua
  ): void {
    $st = Database::conn()->prepare("
    INSERT INTO attendance (user_id, mark_type, marked_at, minutes_late, latitude, longitude, ip_address, user_agent)
    VALUES (?,?,?,?,?,?,?,?)
  ");

    $st->execute([$userId, $type, $markedAt, $late, $lat, $lng, $ip, $ua]);
  }

  public static function update(
    int $id,
    int $userId,
    string $type,
    string $markedAt,
    int $late,
    ?string $ip,
    ?float $lat,
    ?float $lng,
    ?string $ua
  ): void {
    $st = Database::conn()->prepare("
      UPDATE attendance
      SET user_id=?, mark_type=?, marked_at=?, minutes_late=?, latitude=?, longitude=?, ip_address=?, user_agent=?
      WHERE id=?
    ");

    $st->execute([$userId, $type, $markedAt, $late, $lat, $lng, $ip, $ua, $id]);
  }

  public static function delete(int $id): void
  {
    $st = Database::conn()->prepare("DELETE FROM attendance WHERE id=?");
    $st->execute([$id]);
  }
}
