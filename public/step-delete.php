<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/documents.php');
}

$pdo = db();
$document = resolve_document_request($pdo);
$stepId = (int) ($_POST['step_id'] ?? 0);

if ($document === null || $stepId < 1) {
    redirect('/documents.php');
}

$step = fetch_step($pdo, $stepId);
if ($step === null || (int) $step['as_is_id'] !== (int) $document['id']) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']));
}

try {
    delete_step($pdo, $stepId);
} catch (Throwable $e) {
    // Redirect back; edit page can show errors later if needed.
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']));
