<?php
class Csrf {
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
      exit('419 - CSRF token inválido');
    }
  }
}
