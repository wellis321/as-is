<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pdo      = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    http_response_code(404);
    exit('Document not found.');
}

$asIsId      = (int) $document['id'];
$lanes       = fetch_lanes($pdo, $asIsId);
$steps       = fetch_steps($pdo, $asIsId);
$connections = fetch_connections($pdo, $asIsId);

// Build step → systems lookup
$stepSystems = [];
foreach ($steps as $s) {
    $sysList = trim((string) ($s['systems'] ?? ''));
    $stepSystems[(int) $s['id']] = $sysList !== ''
        ? array_map('trim', explode(',', $sysList))
        : [];
}

// Build export structure
$export = [
    'as_is_version' => '1.0',
    'title'         => $document['title'],
    'description'   => $document['description'] ?? '',
    'owner'         => $document['owner']        ?? '',
    'department'    => $document['department']   ?? '',
    'captured_date' => $document['captured_date'] ?? '',
    'version'       => $document['version']      ?? '',
    'lanes'         => array_values(array_map(fn($l) => [
        'name'  => $l['name'],
        'color' => $l['color'],
    ], $lanes)),
    'steps' => array_values(array_map(fn($s) => array_filter([
        'step_number' => (int) $s['step_number'],
        'lane'        => $s['lane_name'] ?? (function() use ($s, $lanes) {
            foreach ($lanes as $l) {
                if ((int)$l['id'] === (int)$s['lane_id']) return $l['name'];
            }
            return '';
        })(),
        'title'       => $s['title'],
        'description' => $s['description'] ?? '',
        'step_type'   => $s['step_type'],
        'action_type' => ($s['action_type'] ?? 'general') !== 'general' ? $s['action_type'] : null,
        'systems'     => $stepSystems[(int)$s['id']] ?: null,
    ], fn($v) => $v !== null && $v !== ''), $steps)),
    'connections' => array_values(array_map(fn($c) => array_filter([
        'from'  => (int) $c['from_number'],
        'to'    => (int) $c['to_number'],
        'label' => $c['label'] ?: null,
    ], fn($v) => $v !== null), $connections)),
];

$filename = preg_replace('/[^a-z0-9-]/', '-', strtolower($document['slug'] ?: $document['title']));
$filename = trim(preg_replace('/-+/', '-', $filename), '-') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
