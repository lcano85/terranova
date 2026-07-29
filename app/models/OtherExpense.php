<?php
require_once __DIR__ . '/../core/Database.php';

class OtherExpense
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }
    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS expense_details (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_expense_details_name (name),
        KEY idx_expense_details_active (is_active)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS monthly_other_expenses (
        id INT NOT NULL AUTO_INCREMENT,
        expense_detail_id INT NOT NULL,
        period_month DATE NOT NULL,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_monthly_other_expense_detail_period (expense_detail_id, period_month),
        KEY idx_monthly_other_expenses_period (period_month),
        CONSTRAINT fk_monthly_other_expenses_detail
          FOREIGN KEY (expense_detail_id) REFERENCES expense_details(id)
          ON DELETE RESTRICT ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    self::$schemaEnsured = true;
  }

  public static function details(bool $activeOnly = false): array
  {
    self::ensureSchema();
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    return Database::conn()->query("SELECT * FROM expense_details {$where} ORDER BY is_active DESC, name ASC")->fetchAll();
  }

  public static function createDetail(string $name): void
  {
    self::ensureSchema();
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    if ($name === '') {
      throw new RuntimeException('Ingresa el nombre del gasto.');
    }
    Database::conn()->prepare("INSERT INTO expense_details (name) VALUES (?)")->execute([$name]);
  }

  public static function setDetailActive(int $id, int $isActive): void
  {
    self::ensureSchema();
    Database::conn()->prepare("UPDATE expense_details SET is_active=? WHERE id=?")
      ->execute([$isActive === 1 ? 1 : 0, $id]);
  }

  public static function updateDetail(int $id, string $name): void
  {
    self::ensureSchema();
    $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    if ($id <= 0 || $name === '') {
      throw new RuntimeException('El detalle de gasto no es válido.');
    }
    Database::conn()->prepare("UPDATE expense_details SET name=? WHERE id=?")->execute([$name, $id]);
  }

  public static function entriesForMonth(string $periodMonth): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT ed.id AS expense_detail_id, ed.name, ed.is_active, COALESCE(moe.amount, 0) AS amount
      FROM expense_details ed
      LEFT JOIN monthly_other_expenses moe
        ON moe.expense_detail_id=ed.id AND moe.period_month=?
      WHERE ed.is_active=1 OR moe.id IS NOT NULL
      ORDER BY ed.name ASC
    ");
    $st->execute([$periodMonth]);
    return $st->fetchAll();
  }

  public static function saveMonth(string $periodMonth, array $amounts): void
  {
    self::ensureSchema();
    $pdo = Database::conn();
    $validIds = array_map(static fn(array $row): int => (int)$row['id'], self::details());
    $upsert = $pdo->prepare("
      INSERT INTO monthly_other_expenses (expense_detail_id, period_month, amount)
      VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE amount=VALUES(amount), updated_at=NOW()
    ");
    $delete = $pdo->prepare("DELETE FROM monthly_other_expenses WHERE expense_detail_id=? AND period_month=?");
    $pdo->beginTransaction();
    try {
      foreach ($amounts as $detailId => $rawAmount) {
        $detailId = (int)$detailId;
        if (!in_array($detailId, $validIds, true)) {
          continue;
        }
        $amount = round((float)$rawAmount, 2);
        if ($amount <= 0) {
          $delete->execute([$detailId, $periodMonth]);
        } else {
          $upsert->execute([$detailId, $periodMonth, $amount]);
        }
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function monthlyTotalsForYear(int $year): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT MONTH(period_month) AS month_number, COUNT(*) AS records_count, COALESCE(SUM(amount), 0) AS total
      FROM monthly_other_expenses
      WHERE period_month >= ? AND period_month < ?
      GROUP BY MONTH(period_month)
    ");
    $st->execute([sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]);
    return $st->fetchAll();
  }

  public static function availableYears(): array
  {
    self::ensureSchema();
    $rows = Database::conn()->query("
      SELECT YEAR(period_month) AS expense_year
      FROM monthly_other_expenses
      GROUP BY YEAR(period_month)
      ORDER BY expense_year DESC
    ")->fetchAll();
    return array_map(static fn(array $row): int => (int)$row['expense_year'], $rows);
  }

  public static function detailMonthlyForYear(int $year): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT ed.id AS expense_detail_id, ed.name,
             MONTH(moe.period_month) AS month_number, moe.amount
      FROM monthly_other_expenses moe
      JOIN expense_details ed ON ed.id=moe.expense_detail_id
      WHERE moe.period_month >= ? AND moe.period_month < ?
      ORDER BY ed.name ASC, month_number ASC
    ");
    $st->execute([sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)]);
    return $st->fetchAll();
  }
}
