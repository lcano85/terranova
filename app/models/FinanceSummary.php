<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Payroll.php';
require_once __DIR__ . '/Requirement.php';
require_once __DIR__ . '/MonthlyProductSale.php';

class FinanceSummary
{
  public static function availableYears(): array
  {
    self::ensureSources();
    $rows = Database::conn()->query("
      SELECT finance_year
      FROM (
        SELECT YEAR(period_month) AS finance_year FROM payrolls
        UNION
        SELECT YEAR(purchased_at) AS finance_year
          FROM requirement_items
          WHERE is_purchased=1 AND purchased_at IS NOT NULL
        UNION
        SELECT YEAR(period_month) AS finance_year FROM monthly_product_sales
      ) years
      WHERE finance_year IS NOT NULL
      GROUP BY finance_year
      ORDER BY finance_year DESC
    ")->fetchAll();

    return array_map(static fn(array $row): int => (int)$row['finance_year'], $rows);
  }

  public static function monthlyForYear(int $year): array
  {
    self::ensureSources();
    $months = array_fill(1, 12, [
      'personnel' => 0.0,
      'personnel_records' => 0,
      'purchases' => 0.0,
      'purchase_records' => 0,
      'unpriced_purchases' => 0,
      'sales' => 0.0,
      'units_sold' => 0.0,
      'sales_products' => 0,
    ]);
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-01-01', $year + 1);
    $pdo = Database::conn();

    $payroll = $pdo->prepare("
      SELECT MONTH(period_month) AS month_number,
             COUNT(*) AS records_count,
             COALESCE(SUM(net_amount), 0) AS total
      FROM payrolls
      WHERE period_month >= ? AND period_month < ?
      GROUP BY MONTH(period_month)
    ");
    $payroll->execute([$from, $to]);
    foreach ($payroll->fetchAll() as $row) {
      $month = (int)$row['month_number'];
      $months[$month]['personnel'] = round((float)$row['total'], 2);
      $months[$month]['personnel_records'] = (int)$row['records_count'];
    }

    $purchases = $pdo->prepare("
      SELECT MONTH(ri.purchased_at) AS month_number,
             COUNT(*) AS records_count,
             SUM(CASE WHEN ri.quantity IS NULL OR COALESCE(ri.unit_price, s.price) IS NULL THEN 1 ELSE 0 END) AS unpriced_count,
             COALESCE(SUM(
               CASE
                 WHEN ri.quantity IS NOT NULL AND COALESCE(ri.unit_price, s.price) IS NOT NULL
                 THEN ri.quantity * COALESCE(ri.unit_price, s.price)
                 ELSE 0
               END
             ), 0) AS total
      FROM requirement_items ri
      LEFT JOIN supplies s ON s.id=ri.supply_id
      WHERE ri.is_purchased=1
        AND ri.purchased_at >= ?
        AND ri.purchased_at < ?
      GROUP BY MONTH(ri.purchased_at)
    ");
    $purchases->execute([$from . ' 00:00:00', $to . ' 00:00:00']);
    foreach ($purchases->fetchAll() as $row) {
      $month = (int)$row['month_number'];
      $months[$month]['purchases'] = round((float)$row['total'], 2);
      $months[$month]['purchase_records'] = (int)$row['records_count'];
      $months[$month]['unpriced_purchases'] = (int)$row['unpriced_count'];
    }

    $sales = $pdo->prepare("
      SELECT MONTH(period_month) AS month_number,
             COUNT(*) AS products_count,
             COALESCE(SUM(units_sold), 0) AS units_sold,
             COALESCE(SUM(total_amount), 0) AS total
      FROM monthly_product_sales
      WHERE period_month >= ? AND period_month < ?
      GROUP BY MONTH(period_month)
    ");
    $sales->execute([$from, $to]);
    foreach ($sales->fetchAll() as $row) {
      $month = (int)$row['month_number'];
      $months[$month]['sales'] = round((float)$row['total'], 2);
      $months[$month]['units_sold'] = (float)$row['units_sold'];
      $months[$month]['sales_products'] = (int)$row['products_count'];
    }

    foreach ($months as &$month) {
      $month['expenses'] = round($month['personnel'] + $month['purchases'], 2);
      $month['balance'] = round($month['sales'] - $month['expenses'], 2);
      $month['has_data'] = $month['personnel_records'] > 0
        || $month['purchase_records'] > 0
        || $month['sales_products'] > 0;
    }
    unset($month);

    return $months;
  }

  private static function ensureSources(): void
  {
    Payroll::ensureSchema();
    Requirement::ensureSchema();
    MonthlyProductSale::ensureSchema();
  }
}
