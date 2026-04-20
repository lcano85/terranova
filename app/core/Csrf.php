<?php
class Csrf {
  private static function expectsJson(): bool {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
  }

  public static function token(): string {
    if (empty($_SESSION['_csrf'])) {
      $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
  }

  public static function check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $t = $_POST['_csrf'] ?? '';
    if (!$t || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
      http_response_code(419);
      if (self::expectsJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'ok' => false,
          'message' => 'CSRF token invalido. Recarga la pagina e intenta de nuevo.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
      }
      exit('419 - CSRF token invalido');
    }
  }
}
