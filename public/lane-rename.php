<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

$pdo    = db();
$laneId = (int) ($_POST['lane_id'] ?? 0);
$name   = trim((string) ($_POST['name'] ?? ''));
$slug   = trim((string) ($_POST['slug'] ?? ''));

if ($laneId < 1 || $name === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing lane_id or name']));
}

// Verify the lane belongs to a document this user can edit
$stmt = $pdo->prepare(
    'SELECT d.* FROM lanes l
     JOIN as_is_documents d ON d.id = l.as_is_id
     WHERE l.id = ? AND d.slug = ?'
);
$stmt->execute([$laneId, $slug]);
$document = $stmt->fetch() ?: null;

if ($document === null) {
    http_response_code(404);
    exit(json_encode(['error' => 'Lane not found']));
}

if (!can_edit_document($document)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Not permitted']));
}

$pdo->prepare('UPDATE lanes SET name = ? WHERE id = ?')->execute([$name, $laneId]);

echo json_encode(['ok' => true, 'name' => $name]);
