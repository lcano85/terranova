<?php
require_once __DIR__ . '/../models/User.php';

class Auth {
  public static function check(): bool {
    return isset($_SESSION['user']);
  }

  public static function user(): ?array {
    return $_SESSION['user'] ?? null;
  }

  public static function login(array $u): void {
    $_SESSION['user'] = [
      'id' => (int)$u['id'],
      'document_type' => $u['document_type'],
      'document_number' => $u['document_number'],
      'first_name' => $u['first_name'],
      'last_name' => $u['last_name'],
      'role' => $u['role'],
      'is_active' => $u['is_active'] ?? 1,
      'shift_id' => $u['shift_id'] ?? null,
    ];
  }

  public static function logout(): void {
    unset($_SESSION['user']);
    session_destroy();
  }

  public static function requireLogin(): void {
    if (!self::check()) {
      Helpers::redirect('/login');
    }

    $u = self::user();
    if (($u['role'] ?? '') === 'worker' && !User::isWorkerActive((int)$u['id'])) {
      self::logout();
      Helpers::redirect('/login?inactive=1');
    }
  }

  public static function requireRole(string $role): void {
    self::requireLogin();
    $u = self::user();
    if (($u['role'] ?? '') !== $role) {
      http_response_code(403);
      exit('403 - Acceso denegado');
    }
  }
}
