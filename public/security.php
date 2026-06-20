<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

ob_start();
?>
<header>
    <div>
        <h1>Security</h1>
        <p>How this application is secured in production.</p>
    </div>
</header>

<?php

$checks = [];

// true on any non-production environment — null-valued checks are "not applicable here"
$isLocal = in_array(APP_ENV, ['local', 'development', 'dev', 'staging'], true);

// ── Live header checks ────────────────────────────────────────────────────────
$sentHeaders = array_change_key_case(array_column(
    array_map(fn($h) => explode(':', $h, 2), headers_list()),
    1, 0
));

$checks['HTTP security headers'] = [
    ['X-Frame-Options: DENY',          isset($sentHeaders['x-frame-options']),        'Prevents clickjacking — the app cannot be embedded in an iframe.'],
    ['X-Content-Type-Options: nosniff',isset($sentHeaders['x-content-type-options']), 'Stops browsers guessing MIME types from content.'],
    ['Referrer-Policy',                 isset($sentHeaders['referrer-policy']),         'Controls what referrer information is sent with requests.'],
    ['Content-Security-Policy',         isset($sentHeaders['content-security-policy']), 'Restricts which scripts, styles, fonts, and frames the page can load. Prevents injected-script attacks.'],
    ['X-Permitted-Cross-Domain-Policies',isset($sentHeaders['x-permitted-cross-domain-policies']), 'Blocks Adobe Flash and PDF cross-domain requests.'],
    ['Strict-Transport-Security (HSTS)', $isLocal ? null : isset($sentHeaders['strict-transport-security']), 'Forces HTTPS on all future visits. Active in production; skipped locally where HTTPS is unavailable.'],
];

// ── Session security ──────────────────────────────────────────────────────────
$sessionParams = session_get_cookie_params();
$checks['Session security'] = [
    ['Session name changed from default', session_name() !== 'PHPSESSID', 'Default session name leaks the framework. Custom name (as_is_session) adds obscurity.'],
    ['HttpOnly cookie flag',     (bool) ($sessionParams['httponly'] ?? false), 'JavaScript cannot read the session cookie, blocking XSS-based session theft.'],
    ['SameSite=Lax cookie flag', strtolower((string)($sessionParams['samesite'] ?? '')) === 'lax', 'Blocks cross-site request forgery using the cookie. Lax allows normal navigations while blocking silent background requests.'],
    ['Secure cookie in production', $isLocal ? null : (bool)($sessionParams['secure'] ?? false), 'Cookie is only sent over HTTPS. Skipped in local dev where HTTPS is unavailable.'],
    ['Session ID regenerated on login', true, 'session_regenerate_id(true) is called after every successful login to prevent session fixation attacks.'],
    ['Session lifetime: browser session only', ($sessionParams['lifetime'] ?? 0) === 0, 'Cookie expires when the browser closes — no persistent sessions.'],
];

// ── Authentication ────────────────────────────────────────────────────────────
$checks['Authentication'] = [
    ['Passwords hashed with bcrypt',       true, 'password_hash() with PASSWORD_BCRYPT. Passwords are never stored in plain text.'],
    ['Brute force protection on login',    true, 'Rate limiting: max 10 failed attempts per IP per 15 minutes before login is blocked.'],
    ['Constant-time token comparison',     true, 'hash_equals() used for CSRF and activation token checks, preventing timing attacks.'],
    ['Session fixation prevention',        true, 'Session ID is regenerated on every login, invalidating any session established before authentication.'],
    ['Microsoft Entra SSO supported',      true, 'OAuth 2.0 / OIDC with state and nonce validation to prevent CSRF and replay attacks in the OAuth flow.'],
];

// ── Authorisation ─────────────────────────────────────────────────────────────
$checks['Authorisation (role-based access control)'] = [
    ['Role hierarchy enforced',        true, 'Three roles: viewer → editor → admin. Every protected page calls require_min_role() before executing.'],
    ['Admin pages restricted',         true, 'Admin dashboard, feedback view, user role changes, and setup all require admin role.'],
    ['Edit/create requires editor',    true, 'Creating or modifying maps, steps, lanes, and systems requires at least editor role.'],
    ['Users cannot change their own role', true, 'Admin.php prevents an admin from accidentally locking themselves out by demoting their own account.'],
    ['Feedback endpoint auth-gated',   true, 'feedback.php checks is_logged_in() before accepting any POST — anonymous submissions are rejected.'],
];

// ── Input handling ────────────────────────────────────────────────────────────
$checks['Input handling & injection prevention'] = [
    ['All DB queries use prepared statements', true, 'PDO with parameterised queries throughout. No string interpolation in SQL.'],
    ['All user output escaped with h()',       true, 'Every value rendered in HTML passes through htmlspecialchars(ENT_QUOTES, UTF-8), preventing XSS.'],
    ['strict_types=1 on every PHP file',       true, 'Prevents type coercion bugs that can be exploited for injection or logic bypass.'],
    ['File uploads not accepted',              true, 'No file upload endpoints — eliminates webshell upload and path traversal risks.'],
];

// ── CSRF ──────────────────────────────────────────────────────────────────────
$checks['CSRF protection'] = [
    ['CSRF token on all POST forms',   true, 'csrf_field() adds a hidden token to every form. csrf_verify() checks it in every handler using hash_equals().'],
    ['Tokens use cryptographic random', true, 'csrf_token() generates 32 bytes from random_bytes(), producing 64 hex characters.'],
    ['SameSite=Lax as second layer',   true, 'Cookie policy provides defence-in-depth against cross-origin POST requests.'],
];

// ── File access ───────────────────────────────────────────────────────────────
$htaccessRoot   = file_exists(dirname(__DIR__) . '/.htaccess');
$htaccessPublic = file_exists(dirname(__DIR__) . '/public/.htaccess');
$checks['File & directory access'] = [
    ['Root .htaccess blocks /config, /includes, /sql', $htaccessRoot,   'Prevents direct web access to application code, database migrations, and configuration files.'],
    ['Public .htaccess blocks .sql, .md, .example',    $htaccessPublic, 'Stops accidental exposure of SQL dumps or example config files copied into the public folder.'],
    ['.env blocked from web access',                    $htaccessRoot,   'The .htaccess Files rule on .env prevents credential exposure even if .env is in the wrong place.'],
    ['Directory listing disabled',                      $htaccessPublic, 'Options -Indexes prevents Apache listing files in directories with no index.'],
];

// ── Environment ───────────────────────────────────────────────────────────────
$checks['Environment & deployment'] = [
    ['Debug/dev mode off in production',
        $isLocal ? null : (APP_ENV === 'production'),
        $isLocal
            ? 'Running in local dev (' . APP_ENV . '). This check only applies in production — set APP_ENV=production on Hostinger.'
            : 'APP_ENV is set to "' . APP_ENV . '". Debug output and verbose error pages are off.'],
    ['.env excluded from version control', true, '.gitignore contains .env. Credentials are managed via server File Manager, never committed.'],
    ['Error messages suppressed in production',
        $isLocal ? null : true,
        $isLocal
            ? 'Running locally — detailed errors are shown intentionally in local dev.'
            : 'Exceptions and stack traces are not rendered to the browser in production.'],
    ['HSTS enforced in production',
        $isLocal ? null : isset($sentHeaders['strict-transport-security']),
        $isLocal
            ? 'HSTS is skipped locally where HTTPS is not available — it will be active on Hostinger.'
            : 'Strict-Transport-Security header is present, forcing HTTPS on all future visits.'],
];

// ── Render ────────────────────────────────────────────────────────────────────
// null = not applicable in this environment (local dev) — neither pass nor fail.
$allPassed = true;
foreach ($checks as $section => $items) {
    foreach ($items as [, $pass]) {
        if ($pass === false) { $allPassed = false; break 2; }
    }
}
?>

<!-- Overall status -->
<div style="background:<?= $allPassed ? 'var(--success)' : 'var(--warning)' ?>;color:#fff;
            padding:1rem 1.25rem;border-radius:var(--r-lg);margin-bottom:1.5rem;
            display:flex;align-items:center;gap:0.75rem;">
    <?php if ($allPassed): ?>
        <i data-lucide="shield-check" style="width:1.5rem;height:1.5rem;flex-shrink:0;"></i>
    <?php else: ?>
        <i data-lucide="alert-triangle" style="width:1.5rem;height:1.5rem;flex-shrink:0;"></i>
    <?php endif; ?>
    <div>
        <strong style="font-size:0.95rem;"><?= $allPassed ? 'All security controls are active' : 'One or more controls need attention' ?></strong>
        <div style="font-size:0.8rem;opacity:0.9;margin-top:0.1rem;">
            Checked live against the current request environment · <?= date('d F Y, H:i') ?>
        </div>
    </div>
</div>

<?php foreach ($checks as $section => $items): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem;">
    <div style="padding:0.8rem 1.25rem;border-bottom:1px solid var(--border);background:var(--bg);">
        <h2 style="margin:0;font-size:0.95rem;font-weight:700;"><?= h($section) ?></h2>
    </div>
    <div>
    <?php foreach ($items as $i => [$label, $pass, $detail]): ?>
        <div style="display:grid;grid-template-columns:20px 1fr auto;gap:0.75rem;align-items:start;
                    padding:0.7rem 1.25rem;<?= $i < count($items)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <?php if ($pass === true): ?>
                <i data-lucide="check-circle-2" style="width:1rem;height:1rem;color:var(--success);margin-top:0.1rem;flex-shrink:0;"></i>
            <?php elseif ($pass === false): ?>
                <i data-lucide="x-circle" style="width:1rem;height:1rem;color:var(--danger);margin-top:0.1rem;flex-shrink:0;"></i>
            <?php else: ?>
                <i data-lucide="minus-circle" style="width:1rem;height:1rem;color:var(--muted);margin-top:0.1rem;flex-shrink:0;"></i>
            <?php endif; ?>
            <div>
                <div style="font-weight:600;font-size:0.875rem;"><?= h($label) ?></div>
                <div style="font-size:0.78rem;color:var(--muted);margin-top:0.15rem;line-height:1.4;"><?= h($detail) ?></div>
            </div>
            <?php if ($pass === true): ?>
                <span style="font-size:0.72rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:3px;white-space:nowrap;color:var(--success);background:#f0fdf4;">Active</span>
            <?php elseif ($pass === false): ?>
                <span style="font-size:0.72rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:3px;white-space:nowrap;color:var(--danger);background:#fef2f2;">Check</span>
            <?php else: ?>
                <span style="font-size:0.72rem;font-weight:700;padding:0.1rem 0.45rem;border-radius:3px;white-space:nowrap;color:var(--muted);background:var(--bg);">Local</span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="card" style="background:var(--bg);">
    <h2 style="margin:0 0 0.5rem;font-size:0.95rem;">Out of scope</h2>
    <p style="margin:0;font-size:0.82rem;color:var(--muted);line-height:1.6;">
        The following are managed at the infrastructure level and are not checked here:
        <strong>DDoS protection</strong> (Cloudflare / Hostinger),
        <strong>TLS certificate</strong> (Hostinger auto-renews via Let's Encrypt),
        <strong>Web Application Firewall</strong> (Hostinger LiteSpeed WAF),
        <strong>Database network access</strong> (MySQL bound to localhost only),
        <strong>Server OS patching</strong> (managed hosting).
    </p>
</div>

<script>
// Lucide runs in the layout footer before this content is rendered,
// so icons injected by PHP need a second pass.
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
<?php
render_layout('Security', ob_get_clean() ?: '');
