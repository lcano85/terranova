<?php
class InventoryImage
{
  public static function path(string $name): string
  {
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $name)) throw new RuntimeException('Imagen no válida.');
    return __DIR__ . '/../storage/inventory/' . $name;
  }

  public static function upload(): ?string
  {
    $file = $_FILES['image'] ?? [];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo cargar la imagen. Revisa su tamaño e inténtalo nuevamente.');
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('Carga de imagen no válida.');
    if (filesize($tmp) > 5 * 1024 * 1024) throw new RuntimeException('La imagen debe pesar como máximo 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($tmp);
    if (!isset($extensions[$mime]) || !$info || ($info['mime'] ?? '') !== $mime) throw new RuntimeException('Selecciona una imagen JPG, PNG o WebP válida.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $path = self::path($name);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) throw new RuntimeException('No se pudo preparar el almacenamiento.');
    if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('No se pudo guardar la imagen.');
    return $name;
  }

  public static function remove(?string $name): void
  {
    if (!$name) return;
    $path = self::path($name);
    if (is_file($path) && !unlink($path)) error_log('No se pudo retirar una imagen de inventario.');
  }
}