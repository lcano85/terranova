<?php
require_once __DIR__ . '/../core/Database.php';

class SalesImportAudit
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS sales_import_audits (
        id INT NOT NULL AUTO_INCREMENT,
        period_month DATE NOT NULL,
        source_file VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        rows_total INT NOT NULL DEFAULT 0,
        rows_imported INT NOT NULL DEFAULT 0,
        issues_count INT NOT NULL DEFAULT 0,
        raw_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        normalized_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sales_import_audits_period (period_month),
        KEY idx_sales_import_audits_created_at (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS sales_import_audit_items (
        id INT NOT NULL AUTO_INCREMENT,
        audit_id INT NOT NULL,
        source_row_number INT NOT NULL,
        product_name VARCHAR(180) NULL,
        issue_type VARCHAR(50) NOT NULL,
        raw_total_amount DECIMAL(14,2) NULL,
        derived_total_amount DECIMAL(14,2) NULL,
        chosen_total_amount DECIMAL(14,2) NULL,
        details TEXT NULL,
        payload_json LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sales_import_audit_items_audit (audit_id),
        CONSTRAINT fk_sales_import_audit_items_audit
          FOREIGN KEY (audit_id) REFERENCES sales_import_audits (id)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function start(string $periodMonth, string $sourceFile, int $rowsTotal): int
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      INSERT INTO sales_import_audits (period_month, source_file, rows_total)
      VALUES (?, ?, ?)
    ");
    $st->execute([$periodMonth, $sourceFile !== '' ? $sourceFile : null, $rowsTotal]);
    return (int)Database::conn()->lastInsertId();
  }

  public static function addIssue(
    int $auditId,
    int $rowNumber,
    ?string $productName,
    string $issueType,
    ?float $rawTotalAmount,
    ?float $derivedTotalAmount,
    ?float $chosenTotalAmount,
    string $details,
    array $payload = []
  ): void {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      INSERT INTO sales_import_audit_items (
        audit_id, source_row_number, product_name, issue_type, raw_total_amount, derived_total_amount,
        chosen_total_amount, details, payload_json
      ) VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      $auditId,
      $rowNumber,
      $productName,
      $issueType,
      $rawTotalAmount,
      $derivedTotalAmount,
      $chosenTotalAmount,
      $details,
      $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
  }

  public static function finish(
    int $auditId,
    string $status,
    int $rowsImported,
    int $issuesCount,
    float $rawTotalAmount,
    float $normalizedTotalAmount,
    ?string $errorMessage = null
  ): void {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      UPDATE sales_import_audits
      SET status=?, rows_imported=?, issues_count=?, raw_total_amount=?, normalized_total_amount=?, error_message=?
      WHERE id=?
    ");
    $st->execute([
      $status,
      $rowsImported,
      $issuesCount,
      round($rawTotalAmount, 2),
      round($normalizedTotalAmount, 2),
      $errorMessage,
      $auditId,
    ]);
  }

  public static function recent(?string $periodMonth = null, int $limit = 10): array
  {
    self::ensureSchema();
    $sql = "
      SELECT *
      FROM sales_import_audits
      WHERE 1=1
    ";
    $params = [];

    if ($periodMonth !== null) {
      $sql .= " AND period_month=?";
      $params[] = $periodMonth;
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit;
    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public static function latestForMonth(string $periodMonth): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT *
      FROM sales_import_audits
      WHERE period_month=?
      ORDER BY created_at DESC, id DESC
      LIMIT 1
    ");
    $st->execute([$periodMonth]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function issues(int $auditId, int $limit = 100): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT *
      FROM sales_import_audit_items
      WHERE audit_id=?
      ORDER BY source_row_number ASC, id ASC
      LIMIT " . (int)$limit
    );
    $st->execute([$auditId]);
    return $st->fetchAll();
  }
}
