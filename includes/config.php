<?php

declare(strict_types=1);

// ── Global error handling — never expose stack traces to users ────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Something went wrong</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 10vh auto;
               padding: 2rem; color: #1f2937; background: #f9fafb; }
        h1   { font-size: 1.5rem; margin: 0 0 .75rem; }
        p    { margin: 0 0 .5rem; color: #6b7280; font-size: .9375rem; }
        a    { color: #006A51; }
    </style>
    </head><body>
    <h1>Something went wrong</h1>
    <p>An unexpected error occurred. Please try again or <a href="/">return home</a>.</p>
    <p>If the problem persists, contact your system administrator.</p>
    </body></html>';
    exit;
});

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . '/env.php';
load_env(dirname(__DIR__) . '/.env');

$_appUrl = rtrim(env('APP_URL', '') ?? '', '/');
if ($_appUrl === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

define('APP_URL', $_appUrl);
define('APP_ENV', env('APP_ENV', 'local') ?? 'local');
define('SESSION_SECRET', env('SESSION_SECRET', 'changeme') ?? 'changeme');
define('SOR_SITE_URL', rtrim(env('SOR_SITE_URL', 'https://papayawhip-hamster-802775.hostingersite.com') ?? '', '/'));
define('ERC_SITE_URL', rtrim(env('ERC_SITE_URL', 'https://aqua-quetzal-992173.hostingersite.com') ?? '', '/'));
define('METRICS_SITE_URL', rtrim(env('METRICS_SITE_URL', 'https://dodgerblue-wombat-806788.hostingersite.com') ?? '', '/'));

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Permitted-Cross-Domain-Policies: none');
// Allow Google Fonts and Lucide CDN; connect-src allows source maps and CDN fetches
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (APP_ENV !== 'local') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('as_is_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (APP_ENV !== 'local'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_url(string $path = ''): string
{
    if ($path === '') {
        return APP_URL;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return APP_URL . (str_starts_with($path, '/') ? $path : '/' . $path);
}
