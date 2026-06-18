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

$laneId    = (int) ($_POST['lane_id'] ?? 0);
$direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';

if ($laneId > 0) {
    reorder_lane($pdo, $laneId, (int) $document['id'], $direction);
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
