<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        redirect(app_url('/login.php') . '?next=' . urlencode($uri));
    }
}

function resolve_login_next(string $next, string $defaultPath = '/documents.php'): string
{
    if ($next === '') {
        return app_url($defaultPath);
    }
    if (str_starts_with($next, APP_URL . '/')) {
        return $next;
    }
    if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        return app_url($next);
    }

    return app_url($defaultPath);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_rate_limited(string $ip): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);

    return (int) $stmt->fetchColumn() >= 10;
}

function record_failed_attempt(string $ip): void
{
    db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
}

function clear_attempts(string $ip): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

/** @param array<string,mixed> $user */
function establish_session(array $user, array $entraRoles = []): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user']      = (string) ($user['display_name'] ?: $user['username']);
    $_SESSION['user_id']         = (int) $user['id'];
    $_SESSION['auth_provider']   = (string) ($user['auth_provider'] ?? 'local');
    $_SESSION['app_role']        = (string) ($user['app_role'] ?? 'viewer');
    $_SESSION['entra_roles']     = $entraRoles;
}

/**
 * Returns true on success, false on bad credentials, or 'needs_activation'
 * when the account exists but has never had a password set.
 *
 * @return true|false|'needs_activation'
 */
function attempt_login(string $user, string $pass): bool|string
{
    $user  = trim($user);
    $email = strtolower($user);

    $db   = db();
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, auth_provider, display_name, app_role, email
         FROM users
         WHERE is_active = 1
           AND (username = ? OR LOWER(email) = ?)
         LIMIT 1'
    );
    $stmt->execute([$user, $email]);
    $row = $stmt->fetch();

    // Account exists but no password set — trigger activation flow
    if ($row && empty($row['password_hash']) && ($row['auth_provider'] ?? 'local') === 'local') {
        $rowEmail = (string) ($row['email'] ?? '');
        if ($rowEmail === '') {
            $_SESSION['_last_login_fail'] = 'microsoft_only_no_local_password';
            return false;
        }
        $code = create_password_setup_token((int) $row['id']);
        send_activation_email($rowEmail, (string) ($row['display_name'] ?: $row['username']), $code, (int) $row['id']);
        $_SESSION['activation_user_id'] = (int) $row['id'];
        return 'needs_activation';
    }

    $hash  = $row && !empty($row['password_hash'])
        ? $row['password_hash']
        : '$2y$12$invalidsaltinvalidsaltinvalidsaltinvalidsaltinvalidsa';
    $valid = password_verify($pass, $hash);

    $reason = 'bad_password';
    if (!$row) {
        $reason = 'user_not_found';
    } elseif (empty($row['password_hash'])) {
        $reason = 'microsoft_only_no_local_password';
    } elseif ($valid) {
        $reason = 'ok';
    }

    $_SESSION['_last_login_fail'] = $reason;

    if ($row && $valid && !empty($row['password_hash'])) {
        establish_session($row);
        unset($_SESSION['_last_login_fail']);
        return true;
    }

    return false;
}

function last_login_fail_reason(): string
{
    return (string) ($_SESSION['_last_login_fail'] ?? '');
}

function active_users_count(): int
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    } catch (Throwable) {
        return -1;
    }
}

// ── Password setup tokens ─────────────────────────────────────────────────────

function create_password_setup_token(int $userId): string
{
    // Reuse an existing valid token so re-entering username doesn't invalidate the email
    $stmt = db()->prepare(
        'SELECT token FROM password_setup_tokens
          WHERE user_id = ? AND expires_at > NOW() AND used_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$userId]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return (string) $existing;
    }

    db()->prepare('DELETE FROM password_setup_tokens WHERE user_id = ?')->execute([$userId]);
    $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 1800);
    db()->prepare(
        'INSERT INTO password_setup_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $code, $expires]);
    return $code;
}

function consume_password_setup_token(int $userId, string $code): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM password_setup_tokens
          WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$userId, $code]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    db()->prepare('UPDATE password_setup_tokens SET used_at = NOW() WHERE id = ?')
        ->execute([(int) $row['id']]);
    return true;
}

/**
 * Provision or link a user from Entra ID token claims and start a session.
 *
 * @param array<string,mixed> $claims
 * @return array{ok: bool, error?: string}
 */
function login_user_from_entra(array $claims): array
{
    require_once __DIR__ . '/entra_config.php';
    require_once __DIR__ . '/permissions.php';

    $oid   = (string) ($claims['oid'] ?? '');
    $email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? '')));
    $name  = trim((string) ($claims['name'] ?? $email));

    if ($oid === '') {
        return ['ok' => false, 'error' => 'Microsoft account identifier missing from token.'];
    }

    $rolesClaim = $claims['roles'] ?? [];
    if (!is_array($rolesClaim)) {
        $rolesClaim = $rolesClaim !== '' && $rolesClaim !== null ? [(string) $rolesClaim] : [];
    }

    $allowed = array_values(array_intersect($rolesClaim, entra_allowed_role_names()));
    if ($allowed === []) {
        $roleHint = implode(', ', entra_allowed_role_names());

        return ['ok' => false, 'error' => 'No application role assigned. Ask your administrator to assign ' . $roleHint . ' in Microsoft Entra.'];
    }

    $appRole = map_entra_roles_to_app_role($allowed);
    if ($appRole === null) {
        return ['ok' => false, 'error' => 'Unrecognised application role in token.'];
    }

    $db = db();

    $stmt = $db->prepare('SELECT * FROM users WHERE entra_oid = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$oid]);
    $user = $stmt->fetch();

    if (!$user && $email !== '') {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $db->prepare(
                'UPDATE users SET entra_oid = ?, auth_provider = ?, display_name = ?, app_role = ? WHERE id = ?'
            )->execute([$oid, 'microsoft', $name ?: $user['display_name'], $appRole, $user['id']]);
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $user = $stmt->fetch();
        }
    }

    if (!$user) {
        $username = $email !== '' ? $email : ('entra_' . substr($oid, 0, 12));
        $db->prepare(
            'INSERT INTO users (username, password_hash, auth_provider, entra_oid, email, display_name, app_role, is_active)
             VALUES (?, NULL, ?, ?, ?, ?, ?, 1)'
        )->execute([$username, 'microsoft', $oid, $email ?: null, $name ?: null, $appRole]);
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $db->lastInsertId()]);
        $user = $stmt->fetch();
    } else {
        $db->prepare(
            'UPDATE users SET auth_provider = ?, email = COALESCE(?, email), display_name = COALESCE(?, display_name), app_role = ? WHERE id = ?'
        )->execute(['microsoft', $email ?: null, $name ?: null, $appRole, $user['id']]);
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        return ['ok' => false, 'error' => 'Could not provision user account.'];
    }

    establish_session($user, $allowed);

    return ['ok' => true];
}

function get_current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
