<?php

declare(strict_types=1);

ini_set('session.gc_maxlifetime', '7200');
session_start();

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/App.php';

$app = new App();
$app->run();
