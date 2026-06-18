<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/entra_auth.php';

$result = entra_handle_callback();

if ($result['ok']) {
    redirect($result['next'] ?? app_url('/documents.php'));
}

flash('error', $result['error'] ?? 'Microsoft sign-in failed.');
redirect(app_url('/login.php'));
