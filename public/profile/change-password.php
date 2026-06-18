<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

if (is_microsoft_user()) {
    flash('info', 'Your password is managed by Microsoft Entra ID.');
    redirect(app_url('/documents.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid form token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $userId = get_current_user_id();
        $row    = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $row->execute([$userId]);
        $user   = $row->fetch();

        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$hash, $userId]);
            flash('success', 'Password changed successfully.');
            redirect(app_url('/documents.php'));
        }
    }
}

ob_start();
?>
<div class="page-header">
    <h1>Change password</h1>
    <p class="muted">Signed in as <strong><?= h($_SESSION['admin_user'] ?? '') ?></strong></p>
</div>

<?php if ($errors !== []): ?>
<div class="flash flash-error">
    <?php foreach ($errors as $er): ?>
        <div><?= h($er) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:28rem;">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:.85rem;">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
        </div>
        <div class="field" style="margin-bottom:.85rem;">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password"
                   autocomplete="new-password" minlength="8" required>
            <p class="hint">Minimum 8 characters.</p>
        </div>
        <div class="field" style="margin-bottom:1rem;">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Update password</button>
        <a href="/documents.php" class="btn btn-secondary" style="margin-left:.5rem;">Cancel</a>
    </form>
</div>
<?php
$content = ob_get_clean();
render_layout('Change password', $content);
