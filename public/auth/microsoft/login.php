<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/entra_auth.php';

if (is_logged_in()) {
    redirect(app_url('/documents.php'));
}

$next = $_GET['next'] ?? '';
if ($next !== '') {
    $_SESSION['entra_login_next'] = $next;
}

entra_start_login();
