<?php
require_once __DIR__ . '/../core/Database.php';

class MailNotificationLog
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    Database::conn()->exec("
      CREATE TABLE IF NOT EXISTS mail_notification_logs (
        id INT NOT NULL AUTO_INCREMENT,
        notification_type VARCHAR(60) NOT NULL,
        recipients TEXT NULL,
        subject VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        related_id INT NULL,
        error_message TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_mail_notification_logs_type (notification_type),
        KEY idx_mail_notification_logs_related (related_id),
        KEY idx_mail_notification_logs_created_at (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    self::$schemaEnsured = true;
  }

  public static function create(string $type, array $recipients, string $subject, ?int $relatedId = null): int
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      INSERT INTO mail_notification_logs (notification_type, recipients, subject, related_id)
      VALUES (?, ?, ?, ?)
    ");
    $st->execute([
      $type,
      $recipients ? implode(', ', $recipients) : null,
      $subject,
      $relatedId,
    ]);

    return (int)Database::conn()->lastInsertId();
  }

  public static function markSent(int $id): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE mail_notification_logs SET status='sent', error_message=NULL WHERE id=?");
    $st->execute([$id]);
  }

  public static function markFailed(int $id, string $errorMessage): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE mail_notification_logs SET status='failed', error_message=? WHERE id=?");
    $st->execute([$errorMessage, $id]);
  }
}
