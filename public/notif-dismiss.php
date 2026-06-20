<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();
require_min_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect('/documents.php');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    auth_db()->prepare('UPDATE users SET last_notif_check = NOW() WHERE id = ?')
        ->execute([$userId]);
}

// Redirect back to wherever the user was
$back = $_POST['back'] ?? '/documents.php';
// Safety: only allow relative paths on this domain
if (!str_starts_with($back, '/') || str_starts_with($back, '//')) {
    $back = '/documents.php';
}
redirect($back);
