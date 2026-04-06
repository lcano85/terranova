<?php
return [
  'enabled' => false,
  'host' => 'smtp.tudominio.com',
  'port' => 587,
  'encryption' => 'tls', // tls | ssl | none
  'username' => 'notificaciones@tudominio.com',
  'password' => 'cambiar-por-clave-real',
  'from_email' => 'notificaciones@tudominio.com',
  'from_name' => 'Terranova',
  'admin_recipients' => [
    'admin@tudominio.com',
  ],
  'connect_timeout' => 20,
];
