<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit('Unauthorised.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$type    = h(trim((string) ($_POST['type']    ?? 'other')));
$message = h(trim((string) ($_POST['message'] ?? '')));
$page    = h(trim((string) ($_POST['page']    ?? '')));
$user    = h($_SESSION['admin_user'] ?? 'anonymous');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$at      = date('Y-m-d H:i:s');

if ($message === '') {
    http_response_code(400);
    exit('No message.');
}

try {
    $pdo = db();
    // Create the feedback table if it does not exist yet
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type       VARCHAR(30)  NOT NULL DEFAULT 'other',
        message    TEXT         NOT NULL,
        page       VARCHAR(500) NOT NULL DEFAULT '',
        submitted_by VARCHAR(120) NOT NULL DEFAULT '',
        ip         VARCHAR(45)  NOT NULL DEFAULT '',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->prepare(
        'INSERT INTO feedback (type, message, page, submitted_by, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$type, $message, $page, $user, $ip, $at]);

    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'error';
}
