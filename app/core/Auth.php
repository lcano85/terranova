<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Security.php';

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
    Security::ensureSchema();
    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user'] = [
      'id' => (int)$u['id'],
      'document_type' => $u['document_type'],
      'document_number' => $u['document_number'],
      'first_name' => $u['first_name'],
      'last_name' => $u['last_name'],
      'role' => $u['role'],
      'security_role_id' => $u['security_role_id'] ?? null,
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
    $u = $_SESSION['user'] ?? null;
    if (!empty($u['id'])) {
      Security::log((int)$u['id'], 'logout', '/logout', 'Cerrar sesión');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
      ]);
    }
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
    if (($u['role'] ?? '') === $role) {
      return;
    }

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
    if ($role === 'admin' && ($path === '/admin' || str_starts_with($path, '/admin/')) && Security::canAccessPath((int)$u['id'], $path)) {
      return;
    }

    if (($u['role'] ?? '') !== $role) {
      http_response_code(403);
      exit('403 - Acceso denegado');
    }
  }

  public static function requirePermission(string $permissionKey): void {
    self::requireLogin();
    $u = self::user();
    if (!Security::hasPermission((int)$u['id'], $permissionKey)) {
      http_response_code(403);
      exit('403 - No tienes permiso para acceder a este módulo');
    }
  }

  public static function canManageLeadDinner(): bool {
    $u = self::user();
    if (empty($u['id'])) {
      return false;
    }
    return Security::hasPermission((int)$u['id'], 'admin.lead_entries')
      || Security::hasPermission((int)$u['id'], 'worker.leads')
      || ($u['role'] ?? '') === 'admin'
      || (
        ($u['role'] ?? '') === 'worker'
        && (string)($u['document_number'] ?? '') === self::LEAD_DINNER_MANAGER_DOCUMENT
      );
  }

  public static function canManageBeverages(): bool {
    $u = self::user();
    if (empty($u['id'])) {
      return false;
    }
    return Security::hasPermission((int)$u['id'], 'admin.beverages')
      || Security::hasPermission((int)$u['id'], 'worker.beverages')
      || ($u['role'] ?? '') === 'admin'
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
