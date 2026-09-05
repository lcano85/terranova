<?php
require_once __DIR__ . '/../core/Database.php';

class RhePayment
{
  public static function find(int $id): ?array
  {
    $st = Database::conn()->prepare('SELECT * FROM rhe_payments WHERE id=?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
  }

  public static function validateMonth(string $month): string
  {
    if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $month)) {
      throw new InvalidArgumentException('Selecciona un mes y año válidos.');
    }
    return $month . '-01';
  }

  public static function validateAmount(string $value): string
  {
    $value = trim($value);
    if (!preg_match('/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D', $value) || (float)$value <= 0) {
      throw new InvalidArgumentException('Ingresa un monto mayor a cero, con máximo dos decimales.');
    }
    $parts = explode('.', $value);
    return $parts[0] . '.' . str_pad($parts[1] ?? '', 2, '0');
  }

  public static function paginate(): array
  {
    $allowed = [10, 20, 50, 100];
    $perPage = (int)($_GET['rhe_per_page'] ?? 10);
    if (!in_array($perPage, $allowed, true)) $perPage = 10;
    $pdo = Database::conn();
    $total = (int)$pdo->query('SELECT COUNT(*) FROM rhe_payments')->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)($_GET['rhe_page'] ?? 1)));
    $offset = ($page - 1) * $perPage;
    $rows = $pdo->query("SELECT r.*, u.document_number, u.first_name, u.last_name
      FROM rhe_payments r LEFT JOIN users u ON u.id=r.user_id
      ORDER BY r.period_month DESC, r.id DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    return ['rows' => $rows, 'meta' => [
      'page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $pages,
      'page_param' => 'rhe_page', 'per_page_param' => 'rhe_per_page', 'allowed_per_page' => $allowed,
    ]];
  }

  public static function save(int $id, ?int $userId, string $month, string $image, string $amount, ?string $firstName = null, ?string $lastName = null): void
  {
    $pdo = Database::conn();
    if ($id > 0) {
      $pdo->prepare('UPDATE rhe_payments SET user_id=?, period_month=?, image_name=?, temporary_first_name=?, temporary_last_name=?, amount=? WHERE id=?')
        ->execute([$userId, $month, $image, $firstName, $lastName, self::validateAmount($amount), $id]);
    } else {
      $pdo->prepare('INSERT INTO rhe_payments (user_id, period_month, image_name, temporary_first_name, temporary_last_name, amount) VALUES (?,?,?,?,?,?)')
        ->execute([$userId, $month, $image, $firstName, $lastName, self::validateAmount($amount)]);
    }
  }

  public static function delete(int $id): void
  {
    Database::conn()->prepare('DELETE FROM rhe_payments WHERE id=?')->execute([$id]);
  }
}
