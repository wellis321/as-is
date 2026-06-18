<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(app_url('/documents.php'));
}

$userId        = (int) ($_SESSION['activation_user_id'] ?? 0);
$prefilledCode = '';

// Accept direct link from email: activate.php?uid=X&token=XXXXXX
if ($userId === 0) {
    $urlUid   = (int) ($_GET['uid'] ?? 0);
    $urlToken = preg_replace('/\D/', '', trim($_GET['token'] ?? ''));
    if ($urlUid > 0 && strlen($urlToken) === 6) {
        $chk = db()->prepare(
            'SELECT id FROM password_setup_tokens
              WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL
              LIMIT 1'
        );
        $chk->execute([$urlUid, $urlToken]);
        if ($chk->fetch()) {
            $userId        = $urlUid;
            $_SESSION['activation_user_id'] = $userId;
            $prefilledCode = $urlToken;
        }
    }
}

if ($userId === 0) {
    flash('info', 'Enter your username below with the password left blank — you\'ll be sent here to enter your code.');
    redirect(app_url('/login.php'));
}

$stmt = db()->prepare(
    'SELECT id, username, email, display_name FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
);
$stmt->execute([$userId]);
$activationUser = $stmt->fetch();
if (!$activationUser) {
    unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
    redirect(app_url('/login.php'));
}

$maskedEmail = '';
$email = (string) ($activationUser['email'] ?? '');
if ($email !== '') {
    [$local, $domain] = explode('@', $email, 2);
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid form submission.';
        goto render;
    }

    $attempts = (int) ($_SESSION['activation_attempts'] ?? 0);
    if ($attempts >= 5) {
        unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
        flash('error', 'Too many incorrect attempts. Please sign in again to receive a new code.');
        redirect(app_url('/login.php'));
    }

    $code            = trim($_POST['code'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Enter the 6-digit code from your email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        if (!consume_password_setup_token($userId, $code)) {
            $_SESSION['activation_attempts'] = $attempts + 1;
            $errors[] = 'That code is incorrect or has expired. Check your email and try again.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

            $freshStmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $freshStmt->execute([$userId]);
            $freshUser = $freshStmt->fetch();

            unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
            establish_session($freshUser);

            flash('success', 'Password created — welcome to AS-IS.');
            redirect(app_url('/documents.php'));
        }
    }
}

render:

ob_start();
?>
<div class="login-card">
    <h1>Activate your account</h1>
    <p class="login-sub">
        We've sent a 6-digit code to
        <?= $maskedEmail !== '' ? '<strong>' . h($maskedEmail) . '</strong>' : 'your email address' ?>.
        Enter it below along with a new password.
    </p>

    <?= render_flash() ?>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $er): ?>
                <div><?= h($er) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
        <?= csrf_field() ?>

        <div class="field" style="margin-bottom:.75rem;">
            <label for="code">6-digit code</label>
            <input type="text" id="code" name="code"
                   inputmode="numeric" pattern="\d{6}" maxlength="6"
                   placeholder="e.g. 482 031"
                   autocomplete="one-time-code"
                   value="<?= h($prefilledCode) ?>"
                   <?= $prefilledCode !== '' ? 'readonly' : 'autofocus' ?>
                   required
                   style="letter-spacing:.2em;font-size:1.25rem;text-align:center;">
        </div>

        <div class="field" style="margin-bottom:.75rem;">
            <label for="password">New password</label>
            <input type="password" id="password" name="password"
                   minlength="8" required autocomplete="new-password"
                   <?= $prefilledCode !== '' ? 'autofocus' : '' ?>>
            <p style="margin:.35rem 0 0;font-size:.8rem;color:var(--muted);">Minimum 8 characters.</p>
        </div>

        <div class="field" style="margin-bottom:1.25rem;">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   minlength="8" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn" style="width:100%;">Set password &amp; sign in</button>
    </form>

    <p style="margin-top:1rem;font-size:.8rem;text-align:center;color:var(--muted);">
        Code not arrived? <a href="<?= h(app_url('/login.php')) ?>">Go back</a> and sign in again to request a new one.
    </p>
</div>
<?php
$content = ob_get_clean();
render_layout('Activate account', $content, ['landing' => true]);
