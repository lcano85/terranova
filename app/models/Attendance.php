<?php
require_once __DIR__ . '/../core/Database.php';

class Attendance
{
  private static function isOvernightShift(?string $startTime, ?string $endTime): bool
  {
    return !empty($startTime) && !empty($endTime) && $endTime <= $startTime;
  }

  private static function resolveWorkDate(string $markedAt, bool $overnightShift, ?string $endTime): string
  {
    $dt = new DateTime($markedAt);
    if ($overnightShift && !empty($endTime) && $dt->format('H:i:s') < $endTime) {
      $dt->modify('-1 day');
    }

    return $dt->format('Y-m-d');
  }

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
    if ($where) {
      $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY a.id DESC LIMIT 300";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function monthlySummary(
    int $userId,
    string $fromDate,
    string $toDate,
    ?string $shiftStartTime = null,
    ?string $shiftEndTime = null
  ): array {
    $overnightShift = self::isOvernightShift($shiftStartTime, $shiftEndTime);
    $queryTo = new DateTime($toDate . ' 23:59:59');
    if ($overnightShift) {
      $queryTo->modify('+1 day');
    }

    $st = Database::conn()->prepare("
      SELECT
        id,
        mark_type,
        marked_at,
        minutes_late
      FROM attendance
      WHERE user_id=?
        AND marked_at >= ?
        AND marked_at <= ?
      ORDER BY marked_at ASC, id ASC
    ");
    $st->execute([
      $userId,
      $fromDate . ' 00:00:00',
      $queryTo->format('Y-m-d H:i:s')
    ]);
    $rows = $st->fetchAll();

    $daysMap = [];
    foreach ($rows as $row) {
      $workDate = self::resolveWorkDate($row['marked_at'], $overnightShift, $shiftEndTime);
      if ($workDate < $fromDate || $workDate > $toDate) {
        continue;
      }

      if (!isset($daysMap[$workDate])) {
        $daysMap[$workDate] = [
          'day' => $workDate,
          'first_in' => null,
          'last_out' => null,
          'minutes_late' => 0
        ];
      }

      if ($row['mark_type'] === 'in') {
        if ($daysMap[$workDate]['first_in'] === null || $row['marked_at'] < $daysMap[$workDate]['first_in']) {
          $daysMap[$workDate]['first_in'] = $row['marked_at'];
        }
        $daysMap[$workDate]['minutes_late'] += (int)($row['minutes_late'] ?? 0);
      }

      if ($row['mark_type'] === 'out') {
        if ($daysMap[$workDate]['last_out'] === null || $row['marked_at'] > $daysMap[$workDate]['last_out']) {
          $daysMap[$workDate]['last_out'] = $row['marked_at'];
        }
      }
    }

    ksort($daysMap);
    $days = array_values($daysMap);

    $totalSeconds = 0;
    $workedDays = 0;
    $totalLate = 0;
    $workedDates = [];

    foreach ($days as $d) {
      if (!empty($d['first_in'])) {
        $workedDays++;
        $totalLate += (int)($d['minutes_late'] ?? 0);
        $workedDates[] = $d['day'];

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
      'worked_dates' => $workedDates,
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
