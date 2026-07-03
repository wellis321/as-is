<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

$pdo  = db();
$slug = trim($_POST['slug'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM as_is_documents WHERE slug = ?');
$stmt->execute([$slug]);
$document = $stmt->fetch() ?: null;

if ($document === null) {
    http_response_code(404);
    exit(json_encode(['error' => 'Document not found']));
}

$asIsId  = (int) $document['id'];
$stepIds = $_POST['step_ids'] ?? [];

if (!is_array($stepIds) || empty($stepIds)) {
    http_response_code(400);
    exit(json_encode(['error' => 'No step IDs provided']));
}

// Verify all submitted IDs actually belong to this document
$owned = $pdo->prepare('SELECT id FROM steps WHERE as_is_id = ? AND id = ?');
foreach ($stepIds as $id) {
    $owned->execute([$asIsId, (int) $id]);
    if (!$owned->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Step does not belong to this document']));
    }
}

// Reassign step_numbers sequentially in two phases to avoid the unique
// constraint on (step_number, as_is_id) firing mid-update:
//   Phase 1 → temporary negative values (guaranteed no conflict)
//   Phase 2 → final sequential 1, 2, 3…
$pdo->beginTransaction();

// Use a large offset for phase 1 — step_number is UNSIGNED so negatives aren't allowed.
// 10000+ is safely above any real step number and frees up 1,2,3… for phase 2.
$offset = 10000;
$stmt   = $pdo->prepare('UPDATE steps SET step_number = ? WHERE id = ? AND as_is_id = ?');
foreach ($stepIds as $i => $id) {
    $stmt->execute([$offset + $i + 1, (int) $id, $asIsId]); // e.g. 10001, 10002…
}
foreach ($stepIds as $i => $id) {
    $stmt->execute([$i + 1,           (int) $id, $asIsId]); // e.g.     1,     2…
}

$pdo->commit();

echo json_encode(['ok' => true, 'count' => count($stepIds)]);
