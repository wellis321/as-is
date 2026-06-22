<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ai.php';

require_min_role('editor');
set_time_limit(0);

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

$pdo         = db();
$document    = resolve_document_request($pdo);

if ($document === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Document not found']);
    exit;
}

$asIsId      = (int) $document['id'];
$instruction = trim((string) ($_POST['instruction'] ?? ''));

if ($instruction === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Refinement instruction is required']);
    exit;
}

$lanes       = fetch_lanes($pdo, $asIsId);
$steps       = fetch_steps($pdo, $asIsId);
$connections = fetch_connections($pdo, $asIsId);

if ($steps === []) {
    http_response_code(400);
    echo json_encode(['error' => 'No steps to refine — build the diagram first']);
    exit;
}

// Format current state for the AI
$laneList  = implode(', ', array_map(fn ($l) => $l['name'], $lanes));
$stepLines = implode("\n", array_map(fn ($s) =>
    $s['step_number'] . '. [' . $s['lane_name'] . '] ' . $s['title'] .
    ' (' . $s['step_type'] . ', ' . ($s['action_type'] ?? 'general') . ')' .
    ($s['description'] ? ' — ' . $s['description'] : ''),
    $steps
));
$connLines = implode(', ', array_map(fn ($c) =>
    $c['from_number'] . '→' . $c['to_number'] . ($c['label'] ? ' ("' . $c['label'] . '")' : ''),
    $connections
));

$maxStepNum = max(array_map(fn ($s) => (int) $s['step_number'], $steps));
$nextNum    = $maxStepNum + 1;

$prompt = <<<PROMPT
You are a process mapping assistant. Given the current state of a swimlane process diagram and a refinement request, return ONLY the changes needed.

Current diagram:
Lanes: {$laneList}

Steps:
{$stepLines}

Connections: {$connLines}

Refinement request:
{$instruction}

Return ONLY a valid JSON object with this exact structure:
{
  "add_lanes": [],
  "add_steps": [],
  "add_connections": [],
  "remove_connections": []
}

Rules:
- "add_lanes": array of new lane name strings to create (empty [] if none needed)
- "add_steps": new steps only — never repeat existing ones. Each must have: step_number (integer >= {$nextNum}), lane_name (must match an existing lane or one from add_lanes), title (max 8 words), description (one sentence), step_type (start/task/decision/end/subprocess/parallel), action_type (general/phone/document/email/letter/wait/meeting/data-entry/check/escalation/automated/notification/visit/payment/report)
- "add_connections": new arrows. Each must have: from (step_number), to (step_number), label (string, usually "")
- "remove_connections": connections to delete. Each must have: from (step_number), to (step_number)
- When inserting a step between two existing steps, remove the old direct connection and add two new ones through the new step
- Only return what changes — do not repeat unchanged steps or connections

No explanation, no preamble, no <think> tags — only the JSON. /no_think
PROMPT;

// ── Choose AI source: Groq → Gemini → Ollama ─────────────────────────
$groqKey   = resolve_groq_key();
$geminiKey = resolve_gemini_key();
$modelLabel = '';
$rawText    = '';

if (strlen($groqKey) > 10) {
    $raw = groq_generate($prompt, $groqKey);
    if ($raw === false) { http_response_code(503); echo json_encode(['error' => 'Could not reach the Groq API.']); exit; }
    $d = json_decode($raw, true);
    if (isset($d['error'])) { http_response_code(502); echo json_encode(['error' => 'Groq error: ' . ($d['error']['message'] ?? 'unknown')]); exit; }
    $rawText    = groq_extract_text($raw) ?? '';
    $modelLabel = 'Groq — Llama 3.3 70B';

} elseif (strlen($geminiKey) > 10) {
    $raw = gemini_generate($prompt, $geminiKey);
    if ($raw === false) { http_response_code(503); echo json_encode(['error' => 'Could not reach the Gemini API.']); exit; }
    $d = json_decode($raw, true);
    if (isset($d['error'])) { http_response_code(502); echo json_encode(['error' => 'Gemini error: ' . ($d['error']['message'] ?? 'unknown')]); exit; }
    $rawText    = gemini_extract_text($raw) ?? '';
    $modelLabel = 'Gemini 2.0 Flash';

} else {
    $model = ollama_detect_model();
    if ($model === null) { http_response_code(503); echo json_encode(['error' => 'No AI configured. Ask your administrator to add a Groq or Gemini API key in AI settings.', 'hint' => 'install']); exit; }
    $raw = ollama_generate($model, $prompt);
    if ($raw === false) { http_response_code(503); echo json_encode(['error' => 'Ollama did not respond.']); exit; }
    $ollamaData = json_decode($raw, true);
    if (!isset($ollamaData['response'])) { http_response_code(500); echo json_encode(['error' => 'Unexpected Ollama response format']); exit; }
    $rawText    = preg_replace('/<think>.*?<\/think>/si', '', $ollamaData['response']) ?? '';
    $modelLabel = $model;
}

$parsed = json_decode(trim($rawText), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not parse AI response']);
    exit;
}

$validStepTypes   = ['start', 'task', 'decision', 'end', 'subprocess', 'parallel'];
$validActionTypes = array_keys(action_type_options());
$laneNames        = array_map(fn ($l) => strtolower(trim($l['name'])), $lanes);

$addLanes = [];
foreach ((array) ($parsed['add_lanes'] ?? []) as $name) {
    $name = substr(trim((string) $name), 0, 120);
    if ($name !== '') $addLanes[] = $name;
}

$addSteps = [];
foreach ((array) ($parsed['add_steps'] ?? []) as $step) {
    $ln = trim((string) ($step['lane_name'] ?? ''));
    $addSteps[] = [
        'step_number' => max(1, (int) ($step['step_number'] ?? $nextNum++)),
        'lane_name'   => $ln,
        'title'       => substr(trim((string) ($step['title'] ?? '')), 0, 120),
        'description' => substr(trim((string) ($step['description'] ?? '')), 0, 500),
        'step_type'   => in_array($step['step_type'] ?? '', $validStepTypes, true) ? $step['step_type'] : 'task',
        'action_type' => in_array($step['action_type'] ?? '', $validActionTypes, true) ? $step['action_type'] : 'general',
    ];
}

$addConns = [];
foreach ((array) ($parsed['add_connections'] ?? []) as $c) {
    $from = (int) ($c['from'] ?? 0);
    $to   = (int) ($c['to']   ?? 0);
    if ($from > 0 && $to > 0 && $from !== $to) {
        $addConns[] = ['from' => $from, 'to' => $to, 'label' => substr(trim((string) ($c['label'] ?? '')), 0, 80)];
    }
}

$removeConns = [];
foreach ((array) ($parsed['remove_connections'] ?? []) as $c) {
    $from = (int) ($c['from'] ?? 0);
    $to   = (int) ($c['to']   ?? 0);
    if ($from > 0 && $to > 0) {
        $removeConns[] = ['from' => $from, 'to' => $to];
    }
}

echo json_encode([
    'add_lanes'          => $addLanes,
    'add_steps'          => $addSteps,
    'add_connections'    => $addConns,
    'remove_connections' => $removeConns,
    'model'              => $modelLabel,
]);
