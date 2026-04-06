<?php
class Controller {
  protected function view(string $path, array $data = []): void {
    extract($data);
    $viewFile = dirname(__DIR__) . '/views/' . $path . '.php';
    if (!is_file($viewFile)) {
      throw new RuntimeException('Vista no encontrada: ' . $viewFile);
    }
    require $viewFile;
  }
}
