<?php

declare(strict_types=1);

// Show a clean "database unavailable" page and stop — never expose stack traces.
function db_unavailable(string $detail = ''): never
{
    // Log the real error server-side without showing it to the user
    error_log('Database connection failed: ' . $detail);

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Service unavailable</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 10vh auto;
               padding: 2rem; color: #1f2937; background: #f9fafb; }
        h1   { font-size: 1.5rem; margin: 0 0 .75rem; color: #111827; }
        p    { margin: 0 0 .5rem; color: #6b7280; font-size: .9375rem; }
        a    { color: #006A51; }
    </style>
    </head><body>
    <h1>Service temporarily unavailable</h1>
    <p>The database is not reachable right now. Please try again in a moment.</p>
    <p>If this keeps happening, contact your system administrator.</p>
    </body></html>';
    exit;
}

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

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        db_unavailable($e->getMessage());
    }

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

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        db_unavailable($e->getMessage());
    }

    return $pdo;
}

/**
 * Returns a PDO connection to the shared auth database (users, login_attempts,
 * password_setup_tokens). Falls back to the main db() when AUTH_DB_NAME is not
 * set, so single-database setups work without any extra config.
 */
function auth_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $authDbName = env('AUTH_DB_NAME', '');
    if ($authDbName === '') {
        return db();
    }

    $main = db_config();
    $dsn  = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        env('AUTH_DB_HOST', $main['host']),
        (int) env('AUTH_DB_PORT', (string) $main['port']),
        $authDbName
    );

    $pdo = new PDO(
        $dsn,
        env('AUTH_DB_USER', $main['username']),
        env('AUTH_DB_PASS', $main['password']),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

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
