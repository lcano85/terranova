<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Attendance.php';

class Payroll
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS payrolls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        payment_type VARCHAR(20) NOT NULL,
        period_month DATE NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        salary_basis VARCHAR(20) NOT NULL,
        salary_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        base_days DECIMAL(6,2) NOT NULL DEFAULT 0,
        daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        hours_per_day DECIMAL(6,2) NOT NULL DEFAULT 0,
        worked_days DECIMAL(6,2) NOT NULL DEFAULT 0,
        gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        late_minutes INT NOT NULL DEFAULT 0,
        late_rate_per_minute DECIMAL(10,4) NOT NULL DEFAULT 0,
        late_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        additions_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        deductions_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payrolls_user_period (user_id, period_start, period_end),
        INDEX idx_payrolls_period_month (period_month)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS payroll_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_id INT NOT NULL,
        item_type VARCHAR(20) NOT NULL,
        concept VARCHAR(120) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        INDEX idx_payroll_items_payroll (payroll_id),
        CONSTRAINT fk_payroll_items_payroll
          FOREIGN KEY (payroll_id) REFERENCES payrolls(id)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    self::$schemaEnsured = true;
  }

  public static function periodRange(string $paymentType, string $month, ?string $periodPart, ?string $weekStart): array
  {
    $monthDate = DateTime::createFromFormat('Y-m', $month);
    if (!$monthDate) {
      $monthDate = new DateTime(date('Y-m-01'));
    }

    $monthStart = new DateTime($monthDate->format('Y-m-01'));
    $monthEnd = (clone $monthStart)->modify('last day of this month');

    if ($paymentType === 'weekly') {
      $start = DateTime::createFromFormat('Y-m-d', (string)$weekStart) ?: clone $monthStart;
      $end = (clone $start)->modify('+6 days');
      if ($start < $monthStart) {
        $start = clone $monthStart;
      }
      if ($end > $monthEnd) {
        $end = clone $monthEnd;
      }
      return [$monthStart->format('Y-m-01'), $start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    if ($paymentType === 'biweekly') {
      if ($periodPart === 'second') {
        return [$monthStart->format('Y-m-01'), $monthStart->format('Y-m-16'), $monthEnd->format('Y-m-d')];
      }
      return [$monthStart->format('Y-m-01'), $monthStart->format('Y-m-01'), $monthStart->format('Y-m-15')];
    }

    return [$monthStart->format('Y-m-01'), $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];
  }

  public static function workingDaysInMonth(string $periodMonth): int
  {
    $start = new DateTime($periodMonth);
    $end = (clone $start)->modify('last day of this month');
    $days = 0;

    while ($start <= $end) {
      if ((int)$start->format('N') <= 5) {
        $days++;
      }
      $start->modify('+1 day');
    }

    return max(1, $days);
  }

  public static function calculatePreview(array $input): array
  {
    self::ensureSchema();

    $userId = (int)($input['user_id'] ?? 0);
    $user = User::findWithDetails($userId);
    if (!$user || ($user['role'] ?? '') !== 'worker') {
      throw new RuntimeException('Trabajador no valido.');
    }

    $paymentType = in_array(($input['payment_type'] ?? ''), ['monthly', 'biweekly', 'weekly'], true)
      ? (string)$input['payment_type']
      : 'biweekly';
    [$periodMonth, $periodStart, $periodEnd] = self::periodRange(
      $paymentType,
      (string)($input['period_month'] ?? date('Y-m')),
      $input['period_part'] ?? null,
      $input['week_start'] ?? null
    );

    $summary = Attendance::monthlySummary(
      $userId,
      $periodStart,
      $periodEnd,
      $user['start_time'] ?? null,
      $user['end_time'] ?? null
    );

    $salaryBasis = ($input['salary_basis'] ?? '') === 'monthly' ? 'monthly' : 'daily';
    $salaryAmount = self::money($input['salary_amount'] ?? ($user['daily_rate'] ?? 0));
    if ($salaryAmount <= 0 && isset($user['daily_rate'])) {
      $salaryAmount = self::money($user['daily_rate']);
      $salaryBasis = 'daily';
    }
    if ($salaryAmount <= 0) {
      throw new RuntimeException('Ingresa el salario mensual o pago diario del trabajador.');
    }

    $baseDays = self::number($input['base_days'] ?? 0);
    if ($baseDays <= 0) {
      $baseDays = $salaryBasis === 'monthly' ? self::workingDaysInMonth($periodMonth) : 1;
    }

    $dailyRate = $salaryBasis === 'monthly' ? round($salaryAmount / $baseDays, 2) : round($salaryAmount, 2);
    $hoursPerDay = self::number($input['hours_per_day'] ?? 0);
    if ($hoursPerDay <= 0) {
      $hoursPerDay = self::shiftHours($user['start_time'] ?? null, $user['end_time'] ?? null);
    }
    if ($hoursPerDay <= 0) {
      $hoursPerDay = 8;
    }

    $workedDays = self::number($input['worked_days'] ?? $summary['worked_days']);
    $lateMinutes = max(0, (int)($input['late_minutes'] ?? $summary['total_minutes_late']));
    $gross = round($workedDays * $dailyRate, 2);
    $lateRate = $hoursPerDay > 0 ? round(($dailyRate / $hoursPerDay) / 60, 4) : 0;
    $lateDiscount = round($lateRate * $lateMinutes, 2);

    $items = self::normalizeItems($input['items'] ?? []);
    $additions = 0.0;
    $deductions = $lateDiscount;
    foreach ($items as $item) {
      if ($item['item_type'] === 'addition') {
        $additions += $item['amount'];
      } else {
        $deductions += $item['amount'];
      }
    }

    $additions = round($additions, 2);
    $deductions = round($deductions, 2);
    $net = round($gross + $additions - $deductions, 2);

    return [
      'user' => $user,
      'period_month' => $periodMonth,
      'period_start' => $periodStart,
      'period_end' => $periodEnd,
      'payment_type' => $paymentType,
      'salary_basis' => $salaryBasis,
      'salary_amount' => $salaryAmount,
      'base_days' => $baseDays,
      'daily_rate' => $dailyRate,
      'hours_per_day' => $hoursPerDay,
      'worked_days' => $workedDays,
      'gross_amount' => $gross,
      'late_minutes' => $lateMinutes,
      'late_rate_per_minute' => $lateRate,
      'late_discount' => $lateDiscount,
      'additions_total' => $additions,
      'deductions_total' => $deductions,
      'net_amount' => $net,
      'items' => $items,
      'attendance_summary' => $summary,
    ];
  }

  public static function create(array $input, int $createdBy): int
  {
    self::ensureSchema();
    $preview = self::calculatePreview($input);
    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        INSERT INTO payrolls (
          user_id, payment_type, period_month, period_start, period_end,
          salary_basis, salary_amount, base_days, daily_rate, hours_per_day,
          worked_days, gross_amount, late_minutes, late_rate_per_minute, late_discount,
          additions_total, deductions_total, net_amount, notes, created_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");
      $st->execute([
        (int)$preview['user']['id'],
        $preview['payment_type'],
        $preview['period_month'],
        $preview['period_start'],
        $preview['period_end'],
        $preview['salary_basis'],
        $preview['salary_amount'],
        $preview['base_days'],
        $preview['daily_rate'],
        $preview['hours_per_day'],
        $preview['worked_days'],
        $preview['gross_amount'],
        $preview['late_minutes'],
        $preview['late_rate_per_minute'],
        $preview['late_discount'],
        $preview['additions_total'],
        $preview['deductions_total'],
        $preview['net_amount'],
        trim((string)($input['notes'] ?? '')),
        $createdBy,
      ]);

      $payrollId = (int)$pdo->lastInsertId();
      $itemSt = $pdo->prepare("INSERT INTO payroll_items (payroll_id, item_type, concept, amount) VALUES (?,?,?,?)");
      foreach ($preview['items'] as $item) {
        $itemSt->execute([$payrollId, $item['item_type'], $item['concept'], $item['amount']]);
      }

      $pdo->commit();
      return $payrollId;
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT p.*, u.first_name, u.last_name, u.document_number
      FROM payrolls p
      JOIN users u ON u.id = p.user_id
      WHERE p.id = ?
      LIMIT 1
    ");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
      return null;
    }

    $row['items'] = self::items($id);
    return $row;
  }

  public static function update(int $id, array $input): void
  {
    self::ensureSchema();
    if (!self::find($id)) {
      throw new RuntimeException('El pago que intentas editar no existe.');
    }

    $preview = self::calculatePreview($input);
    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        UPDATE payrolls SET
          user_id = ?, payment_type = ?, period_month = ?, period_start = ?, period_end = ?,
          salary_basis = ?, salary_amount = ?, base_days = ?, daily_rate = ?, hours_per_day = ?,
          worked_days = ?, gross_amount = ?, late_minutes = ?, late_rate_per_minute = ?,
          late_discount = ?, additions_total = ?, deductions_total = ?, net_amount = ?, notes = ?
        WHERE id = ?
      ");
      $st->execute([
        (int)$preview['user']['id'],
        $preview['payment_type'],
        $preview['period_month'],
        $preview['period_start'],
        $preview['period_end'],
        $preview['salary_basis'],
        $preview['salary_amount'],
        $preview['base_days'],
        $preview['daily_rate'],
        $preview['hours_per_day'],
        $preview['worked_days'],
        $preview['gross_amount'],
        $preview['late_minutes'],
        $preview['late_rate_per_minute'],
        $preview['late_discount'],
        $preview['additions_total'],
        $preview['deductions_total'],
        $preview['net_amount'],
        trim((string)($input['notes'] ?? '')),
        $id,
      ]);

      $pdo->prepare("DELETE FROM payroll_items WHERE payroll_id = ?")->execute([$id]);
      $itemSt = $pdo->prepare("INSERT INTO payroll_items (payroll_id, item_type, concept, amount) VALUES (?,?,?,?)");
      foreach ($preview['items'] as $item) {
        $itemSt->execute([$id, $item['item_type'], $item['concept'], $item['amount']]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  public static function recent(?string $month = null): array
  {
    self::ensureSchema();
    $params = [];
    $where = '';
    if ($month) {
      $where = 'WHERE p.period_month = ?';
      $params[] = $month . '-01';
    }

    $st = Database::conn()->prepare("
      SELECT p.*, u.first_name, u.last_name, u.document_number
      FROM payrolls p
      JOIN users u ON u.id = p.user_id
      $where
      ORDER BY p.id DESC
      LIMIT 200
    ");
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function items(int $payrollId): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM payroll_items WHERE payroll_id=? ORDER BY id ASC");
    $st->execute([$payrollId]);
    return $st->fetchAll();
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("DELETE FROM payrolls WHERE id=?");
    $st->execute([$id]);
  }

  private static function normalizeItems(array $rawItems): array
  {
    $items = [];
    $types = $rawItems['type'] ?? [];
    $concepts = $rawItems['concept'] ?? [];
    $amounts = $rawItems['amount'] ?? [];

    foreach ($concepts as $index => $concept) {
      $concept = trim((string)$concept);
      $amount = self::money($amounts[$index] ?? 0);
      if ($concept === '' || $amount <= 0) {
        continue;
      }
      $type = ($types[$index] ?? '') === 'addition' ? 'addition' : 'deduction';
      $items[] = [
        'item_type' => $type,
        'concept' => $concept,
        'amount' => $amount,
      ];
    }

    return $items;
  }

  private static function shiftHours(?string $startTime, ?string $endTime): float
  {
    if (!$startTime || !$endTime) {
      return 0.0;
    }

    $start = DateTime::createFromFormat('H:i:s', $startTime);
    $end = DateTime::createFromFormat('H:i:s', $endTime);
    if (!$start || !$end) {
      return 0.0;
    }
    if ($end <= $start) {
      $end->modify('+1 day');
    }

    return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
  }

  private static function money($value): float
  {
    return round(max(0, (float)$value), 2);
  }

  private static function number($value): float
  {
    return round(max(0, (float)$value), 2);
  }
}
