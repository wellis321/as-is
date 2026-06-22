<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$pdo      = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Document not found']);
    exit;
}

$asIsId      = (int) $document['id'];
$laneNames   = json_decode((string) ($_POST['lanes']       ?? '[]'), true) ?: [];
$stepsData   = json_decode((string) ($_POST['steps']       ?? '[]'), true) ?: [];
$connsData   = json_decode((string) ($_POST['connections'] ?? '[]'), true) ?: [];

if (!is_array($laneNames) || !is_array($stepsData) || !is_array($connsData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data format']);
    exit;
}

set_time_limit(30);

$validStepTypes   = ['start', 'task', 'decision', 'end', 'subprocess', 'parallel'];
$validActionTypes = array_keys(action_type_options());

try {

// Build lane map from what already exists
$laneMap = [];
foreach (fetch_lanes($pdo, $asIsId) as $lane) {
    $laneMap[strtolower(trim($lane['name']))] = (int) $lane['id'];
}

// Create any new lanes the AI suggested
$createdLanes = 0;
foreach ($laneNames as $name) {
    $name = substr(trim((string) $name), 0, 120);
    if ($name === '') continue;
    $key = strtolower($name);
    if (!isset($laneMap[$key])) {
        $laneId          = create_lane($pdo, $asIsId, $name);
        $laneMap[$key]   = $laneId;
        $createdLanes++;
    }
}

// Resolve a lane name (from AI) to a lane id
function resolve_lane(string $laneName, array $laneMap): ?int
{
    $key = strtolower(trim($laneName));
    if (isset($laneMap[$key])) return $laneMap[$key];
    foreach ($laneMap as $mapKey => $id) {
        if (str_contains($mapKey, $key) || str_contains($key, $mapKey)) {
            return $id;
        }
    }
    return empty($laneMap) ? null : array_values($laneMap)[0];
}

// Figure out the starting step number
$existingSteps = fetch_steps($pdo, $asIsId);
$nextNum       = $existingSteps !== []
    ? max(array_map(fn ($s) => (int) $s['step_number'], $existingSteps)) + 1
    : 1;

// Create steps and track step_number → step_id for connection wiring
$createdSteps  = 0;
$stepIdByNum   = []; // step_number → database id

foreach ($stepsData as $step) {
    $laneId = resolve_lane((string) ($step['lane_name'] ?? ''), $laneMap);
    if ($laneId === null) continue;

    $title      = substr(trim((string) ($step['title'] ?? '')), 0, 120);
    $desc       = substr(trim((string) ($step['description'] ?? '')), 0, 500);
    $stepType   = in_array($step['step_type'] ?? '', $validStepTypes, true) ? $step['step_type'] : 'task';
    $actionType = in_array($step['action_type'] ?? '', $validActionTypes, true) ? $step['action_type'] : 'general';
    $stepNum    = max(1, (int) ($step['step_number'] ?? $nextNum));

    if ($title === '') continue;

    $newId               = create_step($pdo, $asIsId, $laneId, $stepNum, $title, $desc, $stepType, $actionType);
    $stepIdByNum[$stepNum] = $newId;
    $nextNum             = max($nextNum, $stepNum) + 1;
    $createdSteps++;
}

// Create connections
$createdConns = 0;
foreach ($connsData as $conn) {
    $fromNum = (int) ($conn['from']  ?? 0);
    $toNum   = (int) ($conn['to']    ?? 0);
    $label   = substr(trim((string) ($conn['label'] ?? '')), 0, 80);

    if (!isset($stepIdByNum[$fromNum], $stepIdByNum[$toNum])) continue;

    create_connection($pdo, $stepIdByNum[$fromNum], $stepIdByNum[$toNum], $label);
    $createdConns++;
}

    echo json_encode([
        'success'  => true,
        'created'  => ['lanes' => $createdLanes, 'steps' => $createdSteps, 'connections' => $createdConns],
        'redirect' => '/edit.php?slug=' . rawurlencode($document['slug']) . '#steps',
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'stage' => 'db']);
}
