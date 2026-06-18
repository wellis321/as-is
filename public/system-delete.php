<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/systems.php');
}

$pdo      = db();
$systemId = (int) ($_POST['system_id'] ?? 0);

if ($systemId > 0 && fetch_system($pdo, $systemId) !== null) {
    delete_system($pdo, $systemId);
}

redirect('/systems.php');
