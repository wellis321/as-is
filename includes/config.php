<?php

declare(strict_types=1);

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

header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Permitted-Cross-Domain-Policies: none');
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
