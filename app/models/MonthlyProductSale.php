<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/SalesImportAudit.php';

class MonthlyProductSale
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Product::ensureSchema();
    SalesImportAudit::ensureSchema();

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS monthly_product_sales (
        id INT NOT NULL AUTO_INCREMENT,
        period_month DATE NOT NULL,
        product_id INT NOT NULL,
        units_sold DECIMAL(12,2) NOT NULL DEFAULT 0,
        unit_price DECIMAL(12,2) NULL,
        total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(14,2) NULL,
        total_profit DECIMAL(14,2) NULL,
        stock_snapshot DECIMAL(12,2) NULL,
        source_file VARCHAR(255) NULL,
        imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_monthly_product_sales_period_product (period_month, product_id),
        KEY idx_monthly_product_sales_period (period_month),
        KEY idx_monthly_product_sales_product (product_id),
        CONSTRAINT fk_monthly_product_sales_product
          FOREIGN KEY (product_id) REFERENCES products (id)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function replaceMonthFromRows(string $periodMonth, array $rows, string $sourceFile = ''): array
  {
    self::ensureSchema();
    $auditId = SalesImportAudit::start($periodMonth, $sourceFile, count($rows));

    $pdo = Database::conn();
    $pdo->beginTransaction();
    try {
      $delete = $pdo->prepare("DELETE FROM monthly_product_sales WHERE period_month=?");
      $delete->execute([$periodMonth]);

      $insert = $pdo->prepare("
        INSERT INTO monthly_product_sales (
          period_month, product_id, units_sold, unit_price, total_amount, total_cost,
          total_profit, stock_snapshot, source_file
        ) VALUES (?,?,?,?,?,?,?,?,?)
      ");

      $count = 0;
      $issuesCount = 0;
      $rawTotalAmount = 0.0;
      $normalizedTotalAmount = 0.0;

      foreach ($rows as $index => $row) {
        $name = trim((string)($row['PRODUCTO'] ?? ''));
        if ($name === '') {
          continue;
        }

        $rowNumber = $index + 2;
        $units = Product::number($row['UNIDADES'] ?? 0) ?? 0;
        $unitPrice = Product::number($row['PRECIO UNITARIO'] ?? null);
        $rawTotal = Product::number($row['VENTA TOTAL'] ?? null) ?? 0;
        $derivedTotal = ($unitPrice !== null && $units > 0) ? round($units * $unitPrice, 2) : 0.0;
        $chosenTotal = $rawTotal;
        $issueType = null;
        $details = '';

        if ($derivedTotal > 0 && abs($rawTotal - $derivedTotal) > 0.05) {
          $chosenTotal = $derivedTotal;
          $issueType = $rawTotal == 0.0 ? 'raw_total_zero_formula_mismatch' : 'raw_total_mismatch';
          $details = 'Se reemplazo VENTA TOTAL por UNIDADES * PRECIO UNITARIO por diferencia detectada en el archivo.';
        }

        $productId = Product::firstOrCreateFromSalesRow($row);
        $insert->execute([
          $periodMonth,
          $productId,
          $units,
          $unitPrice,
          $chosenTotal,
          Product::number($row['COSTO TOTAL'] ?? null),
          Product::number($row['UTILIDAD'] ?? null),
          Product::number($row['STOCK'] ?? null),
          $sourceFile !== '' ? $sourceFile : null,
        ]);

        $rawTotalAmount += $rawTotal;
        $normalizedTotalAmount += $chosenTotal;

        if ($issueType !== null) {
          SalesImportAudit::addIssue(
            $auditId,
            $rowNumber,
            $name,
            $issueType,
            $rawTotal,
            $derivedTotal,
            $chosenTotal,
            $details,
            $row
          );
          $issuesCount++;
        }

        $count++;
      }

      $pdo->commit();
      SalesImportAudit::finish(
        $auditId,
        'success',
        $count,
        $issuesCount,
        $rawTotalAmount,
        $normalizedTotalAmount
      );
      return [
        'count' => $count,
        'audit_id' => $auditId,
        'issues_count' => $issuesCount,
        'raw_total_amount' => round($rawTotalAmount, 2),
        'normalized_total_amount' => round($normalizedTotalAmount, 2),
      ];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      SalesImportAudit::finish($auditId, 'error', 0, 0, 0, 0, $e->getMessage());
      throw $e;
    }
  }

  public static function availableMonths(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT period_month,
             DATE_FORMAT(period_month, '%Y-%m') AS period_key,
             COUNT(*) AS rows_count,
             MAX(imported_at) AS last_imported_at,
             MAX(source_file) AS source_file
      FROM monthly_product_sales
      GROUP BY period_month
      ORDER BY period_month DESC
    ")->fetchAll();
  }

  public static function overview(string $periodMonth, ?int $categoryId = null): array
  {
    self::ensureSchema();

    $sql = "
      SELECT
        COALESCE(SUM(mps.units_sold), 0) AS total_units,
        COALESCE(SUM(mps.total_amount), 0) AS total_amount,
        COALESCE(SUM(mps.total_profit), 0) AS total_profit,
        COUNT(*) AS products_count
      FROM monthly_product_sales mps
      JOIN products p ON p.id = mps.product_id
      WHERE mps.period_month=?
    ";
    $params = [$periodMonth];

    if ($categoryId !== null) {
      $sql .= " AND p.category_id=?";
      $params[] = $categoryId;
    }

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetch() ?: [];
  }

  public static function topProducts(string $periodMonth, ?int $categoryId = null, int $limit = 20): array
  {
    self::ensureSchema();

    $sql = "
      SELECT
        p.id,
        p.name,
        pc.name AS category_name,
        SUM(mps.units_sold) AS units_sold,
        SUM(mps.total_amount) AS total_amount
      FROM monthly_product_sales mps
      JOIN products p ON p.id = mps.product_id
      LEFT JOIN product_categories pc ON pc.id = p.category_id
      WHERE mps.period_month=?
    ";
    $params = [$periodMonth];

    if ($categoryId !== null) {
      $sql .= " AND p.category_id=?";
      $params[] = $categoryId;
    }

    $sql .= "
      GROUP BY p.id, p.name, pc.name
      ORDER BY units_sold DESC, total_amount DESC, p.name ASC
      LIMIT " . (int)$limit;

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function byCategory(string $periodMonth): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT
        COALESCE(pc.name, 'Sin categoria') AS category_name,
        p.category_id,
        SUM(mps.units_sold) AS units_sold,
        SUM(mps.total_amount) AS total_amount
      FROM monthly_product_sales mps
      JOIN products p ON p.id = mps.product_id
      LEFT JOIN product_categories pc ON pc.id = p.category_id
      WHERE mps.period_month=?
      GROUP BY p.category_id, pc.name
      ORDER BY total_amount DESC, units_sold DESC
    ");
    $st->execute([$periodMonth]);
    return $st->fetchAll();
  }

  public static function latestImportedMonth(): ?string
  {
    self::ensureSchema();
    $row = Database::conn()->query("
      SELECT DATE_FORMAT(MAX(period_month), '%Y-%m-%d') AS period_month
      FROM monthly_product_sales
    ")->fetch();
    return $row && !empty($row['period_month']) ? $row['period_month'] : null;
  }
}
