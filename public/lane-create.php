<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/documents.php');
}

$pdo = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/documents.php');
}

$name = trim((string) ($_POST['name'] ?? ''));
$color = trim((string) ($_POST['color'] ?? '#e8f0fe'));

if ($name !== '') {
    try {
        create_lane($pdo, (int) $document['id'], $name, $color !== '' ? $color : '#e8f0fe');
    } catch (Throwable $e) {
        // Ignore for now; edit page remains usable.
    }
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']));
