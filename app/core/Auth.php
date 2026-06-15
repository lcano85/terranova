<?php
require_once __DIR__ . '/../models/User.php';

class Auth {
  private const ADMIN_SESSION_TTL = 7200;
  private const LEAD_DINNER_MANAGER_DOCUMENT = '74581146';

  public static function check(): bool {
    self::enforceAdminSessionTtl();
    return isset($_SESSION['user']);
  }

  public static function user(): ?array {
    self::enforceAdminSessionTtl();
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

    if (($u['role'] ?? '') === 'admin') {
      $_SESSION['admin_last_activity'] = time();
    } else {
      unset($_SESSION['admin_last_activity']);
    }
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
    if (($u['role'] ?? '') === 'admin') {
      $_SESSION['admin_last_activity'] = time();
    }

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

  public static function canManageLeadDinner(): bool {
    $u = self::user();
    return ($u['role'] ?? '') === 'admin'
      || (
        ($u['role'] ?? '') === 'worker'
        && (string)($u['document_number'] ?? '') === self::LEAD_DINNER_MANAGER_DOCUMENT
      );
  }

  private static function enforceAdminSessionTtl(): void {
    $u = $_SESSION['user'] ?? null;
    if (($u['role'] ?? '') !== 'admin') {
      return;
    }

    $lastActivity = (int)($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity <= 0 || (time() - $lastActivity) > self::ADMIN_SESSION_TTL) {
      self::logout();
    }
  }
}
