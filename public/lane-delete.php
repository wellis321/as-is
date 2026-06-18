<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/documents.php');
}

$pdo = db();
$document = resolve_document_request($pdo);
$laneId = (int) ($_POST['lane_id'] ?? 0);

if ($document === null || $laneId < 1) {
    redirect('/documents.php');
}

$stmt = $pdo->prepare('SELECT id FROM lanes WHERE id = ? AND as_is_id = ?');
$stmt->execute([$laneId, $document['id']]);

if ($stmt->fetch()) {
    try {
        delete_lane($pdo, $laneId);
    } catch (Throwable $e) {
        // Lane may contain steps; database constraints will block delete.
    }
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']));
