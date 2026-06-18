<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';

load_env(dirname(__DIR__) . '/.env');

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', '8889'),
    'database' => env('DB_DATABASE', 'as_is'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', 'root'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];
