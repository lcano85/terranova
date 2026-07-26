<?php
$local = __DIR__ . '/mail.local.php';
if (is_file($local)) {
  return require $local;
}

$password = (string)(getenv('MAIL_PASSWORD') ?: '');
$recipients = array_values(array_filter(array_map(
  'trim',
  explode(',', (string)(getenv('MAIL_ADMIN_RECIPIENTS') ?: ''))
)));

return [
  'enabled' => filter_var(getenv('MAIL_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN) && $password !== '',
  'host' => (string)(getenv('MAIL_HOST') ?: ''),
  'port' => (int)(getenv('MAIL_PORT') ?: 587),
  'encryption' => (string)(getenv('MAIL_ENCRYPTION') ?: 'tls'),
  'username' => (string)(getenv('MAIL_USERNAME') ?: ''),
  'password' => $password,
  'from_email' => (string)(getenv('MAIL_FROM_ADDRESS') ?: ''),
  'from_name' => (string)(getenv('MAIL_FROM_NAME') ?: 'Terranova'),
  'admin_recipients' => $recipients,
  'connect_timeout' => 20,
  'verify_peer' => true,
  'verify_peer_name' => true,
  'allow_self_signed' => false,
  'debug' => false,
];
