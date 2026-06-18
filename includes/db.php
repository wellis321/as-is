<?php

declare(strict_types=1);

function db_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/database.php';
    }

    return $config;
}

function db_server(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $config['host'],
        $config['port'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_database(): void
{
    $config = db_config();
    $name = $config['database'];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid database name in .env');
    }

    $server = db_server();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}
