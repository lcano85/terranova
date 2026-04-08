<?php
class Helpers {
  public static function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }

  public static function formatDate(?string $value): string {
    if (empty($value)) {
      return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
      return (string)$value;
    }

    return date('d/m/Y', $timestamp);
  }

  public static function formatDateTime(?string $value): string {
    if (empty($value)) {
      return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
      return (string)$value;
    }

    return date('d/m/Y H:i', $timestamp);
  }

  public static function redirect(string $path): void {
    header('Location: ' . (BASE_URL . $path));
    exit;
  }

  public static function isPost(): bool {
    return (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
  }

  public static function now(): DateTime {
    return new DateTime('now');
  }
}
