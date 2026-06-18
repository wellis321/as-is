<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

$pdo = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/index.php');
}

$asIsId = (int) $document['id'];
$fromStepId = (int) ($_POST['from_step_id'] ?? 0);
$toStepId   = (int) ($_POST['to_step_id'] ?? 0);
$label      = trim((string) ($_POST['label'] ?? ''));

if ($fromStepId > 0 && $toStepId > 0 && $fromStepId !== $toStepId) {
    // Verify both steps belong to this document.
    $fromStep = fetch_step($pdo, $fromStepId);
    $toStep   = fetch_step($pdo, $toStepId);

    if (
        $fromStep !== null && (int) $fromStep['as_is_id'] === $asIsId &&
        $toStep   !== null && (int) $toStep['as_is_id']   === $asIsId
    ) {
        try {
            create_connection($pdo, $fromStepId, $toStepId, $label);
        } catch (Throwable) {
            // Duplicate connections are silently ignored.
        }
    }
}

redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#connections');
