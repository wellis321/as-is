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
$addLanes    = json_decode((string) ($_POST['add_lanes']          ?? '[]'), true) ?: [];
$addSteps    = json_decode((string) ($_POST['add_steps']          ?? '[]'), true) ?: [];
$addConns    = json_decode((string) ($_POST['add_connections']    ?? '[]'), true) ?: [];
$removeConns = json_decode((string) ($_POST['remove_connections'] ?? '[]'), true) ?: [];

$validStepTypes   = ['start', 'task', 'decision', 'end', 'subprocess', 'parallel'];
$validActionTypes = array_keys(action_type_options());

try {
    // Build lane map (existing + new)
    $laneMap = [];
    foreach (fetch_lanes($pdo, $asIsId) as $lane) {
        $laneMap[strtolower(trim($lane['name']))] = (int) $lane['id'];
    }

    $createdLanes = 0;
    foreach ($addLanes as $name) {
        $name = substr(trim((string) $name), 0, 120);
        if ($name === '') continue;
        $key = strtolower($name);
        if (!isset($laneMap[$key])) {
            $laneMap[$key] = create_lane($pdo, $asIsId, $name);
            $createdLanes++;
        }
    }

    // Resolve lane name → id
    $resolveLane = function (string $name) use ($laneMap): ?int {
        $key = strtolower(trim($name));
        if (isset($laneMap[$key])) return $laneMap[$key];
        foreach ($laneMap as $k => $id) {
            if (str_contains($k, $key) || str_contains($key, $k)) return $id;
        }
        return empty($laneMap) ? null : array_values($laneMap)[0];
    };

    // Build step_number → step_id map (existing steps)
    $stepIdByNum = [];
    foreach (fetch_steps($pdo, $asIsId) as $step) {
        $stepIdByNum[(int) $step['step_number']] = (int) $step['id'];
    }

    $existingNums = array_keys($stepIdByNum);
    $nextNum      = $existingNums !== [] ? max($existingNums) + 1 : 1;

    // Create new steps
    $createdSteps = 0;
    foreach ($addSteps as $step) {
        $laneId = $resolveLane((string) ($step['lane_name'] ?? ''));
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

    // Remove connections by step number pair
    $removedConns = 0;
    foreach ($removeConns as $pair) {
        $fromNum = (int) ($pair['from'] ?? 0);
        $toNum   = (int) ($pair['to']   ?? 0);
        if (!isset($stepIdByNum[$fromNum], $stepIdByNum[$toNum])) continue;

        $stmt = $pdo->prepare(
            'DELETE FROM step_connections WHERE from_step_id = ? AND to_step_id = ?'
        );
        $stmt->execute([$stepIdByNum[$fromNum], $stepIdByNum[$toNum]]);
        $removedConns += $stmt->rowCount();
    }

    // Add new connections
    $createdConns = 0;
    foreach ($addConns as $conn) {
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
        'removed'  => ['connections' => $removedConns],
        'redirect' => '/edit.php?slug=' . rawurlencode($document['slug']) . '#steps',
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'stage' => 'db']);
}
