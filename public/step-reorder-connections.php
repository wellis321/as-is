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
$stepIds = array_map('intval', $_POST['step_ids'] ?? []);

if (count($stepIds) < 2) {
    exit(json_encode(['ok' => true, 'message' => 'Nothing to connect']));
}

// Verify all steps belong to this document
$owned = $pdo->prepare('SELECT id FROM steps WHERE as_is_id = ? AND id = ?');
foreach ($stepIds as $id) {
    $owned->execute([$asIsId, $id]);
    if (!$owned->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Step does not belong to this document']));
    }
}

$pdo->beginTransaction();

// Delete existing connections for this document
$pdo->prepare(
    'DELETE FROM step_connections
     WHERE from_step_id IN (SELECT id FROM steps WHERE as_is_id = ?)
        OR to_step_id   IN (SELECT id FROM steps WHERE as_is_id = ?)'
)->execute([$asIsId, $asIsId]);

// Insert sequential connections: step[0]→step[1]→step[2]…
$insert = $pdo->prepare(
    'INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES (?, ?, NULL)'
);
for ($i = 0; $i < count($stepIds) - 1; $i++) {
    $insert->execute([$stepIds[$i], $stepIds[$i + 1]]);
}

$pdo->commit();

echo json_encode(['ok' => true, 'connections' => count($stepIds) - 1]);
