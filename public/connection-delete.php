<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/documents.php');
}

$pdo = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/documents.php');
}

$connectionId = (int) ($_POST['connection_id'] ?? 0);

if ($connectionId > 0) {
    // Verify the connection belongs to this document before deleting.
    $stmt = $pdo->prepare(
        'SELECT c.id FROM step_connections c
         INNER JOIN steps s ON s.id = c.from_step_id
         WHERE c.id = ? AND s.as_is_id = ?'
    );
    $stmt->execute([$connectionId, (int) $document['id']]);

    if ($stmt->fetch()) {
        delete_connection($pdo, $connectionId);
    }
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#connections');
