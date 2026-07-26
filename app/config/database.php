<?php
$local = __DIR__ . '/database.local.php';
if (is_file($local)) {
  return require $local;
}

$required = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($required as $name) {
  if (getenv($name) === false) {
    throw new RuntimeException("Falta configurar la variable de entorno {$name}.");
  }
}

return [
  'host' => (string)getenv('DB_HOST'),
  'db' => (string)getenv('DB_DATABASE'),
  'user' => (string)getenv('DB_USERNAME'),
  'pass' => (string)getenv('DB_PASSWORD'),
  'charset' => (string)(getenv('DB_CHARSET') ?: 'utf8mb4'),
];
