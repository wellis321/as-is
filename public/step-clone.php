<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/documents.php');
}
if (!csrf_verify()) {
    redirect('/documents.php');
}

$pdo      = db();
$document = resolve_document_request($pdo);
if ($document === null) {
    redirect('/documents.php');
}

$asIsId = (int) $document['id'];
$stepId = (int) ($_POST['step_id'] ?? 0);
$step   = $stepId > 0 ? fetch_step($pdo, $stepId) : null;

if ($step === null || (int) $step['as_is_id'] !== $asIsId) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']));
}

// Find the next available step number
$usedNums   = array_map(
    static fn($s) => (int) $s['step_number'],
    fetch_steps($pdo, $asIsId)
);
$nextNumber = count($usedNums) > 0 ? max($usedNums) + 1 : 1;

// Create the duplicate
$newId = create_step(
    $pdo,
    $asIsId,
    (int) $step['lane_id'],
    $nextNumber,
    'Copy of ' . $step['title'],
    (string) ($step['description'] ?? ''),
    $step['step_type'],
    $step['action_type'] ?? 'general'
);

// Copy system links
$systemIds = fetch_step_system_ids($pdo, $stepId);
if ($systemIds) {
    sync_step_systems($pdo, $newId, $systemIds);
}

// Send the user straight to edit the clone so they can adjust the title/number
redirect('/step-edit.php?slug=' . rawurlencode($document['slug']) . '&step_id=' . $newId);
