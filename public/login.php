<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/entra_config.php';

if (is_logged_in()) {
    redirect(resolve_login_next($_GET['next'] ?? ''));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (is_rate_limited(client_ip())) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (attempt_login($user, $pass)) {
            clear_attempts(client_ip());
            redirect(resolve_login_next($_GET['next'] ?? ''));
        } else {
            record_failed_attempt(client_ip());
            $error = 'Incorrect username or password.';
        }
    }
}

ob_start();
?>
<div class="login-card">
    <h1>Sign in</h1>
    <p class="login-sub">AS-IS process mapping</p>

    <?php if ($error !== ''): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (entra_enabled()): ?>
        <?php
        $msNext = $_GET['next'] ?? '';
        $msLoginUrl = app_url('/auth/microsoft/login.php');
        if ($msNext !== '') {
            $msLoginUrl .= '?next=' . urlencode($msNext);
        }
        ?>
        <a href="<?= h($msLoginUrl) ?>" class="btn btn--microsoft" style="margin-bottom:1rem;">Sign in with Microsoft</a>
        <div class="login-divider" aria-hidden="true"><span>or</span></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:.75rem;">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username"
                   value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="field" style="margin-bottom:1.25rem;">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn" style="width:100%;">Sign in</button>
    </form>
</div>
<?php
$content = ob_get_clean();
render_layout('Sign in', $content, ['landing' => true]);
