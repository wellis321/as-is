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

$pdo      = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Document not found']);
    exit;
}

$asIsId      = (int) $document['id'];
$description = trim((string) ($_POST['description'] ?? ''));

if ($description === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Description is required']);
    exit;
}

$lanes     = fetch_lanes($pdo, $asIsId);
$hasLanes  = $lanes !== [];
$laneList  = $hasLanes ? implode(', ', array_map(fn ($l) => $l['name'], $lanes)) : '';

$existingSteps  = fetch_steps($pdo, $asIsId);
$nextStepNumber = $existingSteps !== []
    ? max(array_map(fn ($s) => (int) $s['step_number'], $existingSteps)) + 1
    : 1;

$actionTypeList = implode(', ', array_keys(action_type_options()));

$connectionNote = 'Each connection must have: from (step_number integer), to (step_number integer), label (string, usually empty "" unless it is a decision branch like "Yes" or "No").';

if ($hasLanes) {
    $prompt = <<<PROMPT
You are a process mapping assistant for a local council. Given a description of a business process, extract the steps and the connections (arrows) between them, then return them as structured JSON.

The swimlanes (roles/teams) in this diagram are: {$laneList}

Step types: "start" (use once, first step), "task" (most steps), "decision" (yes/no branch), "end" (use once, last step), "subprocess", "parallel"
Action types: "general", "phone", "document", "email", "letter", "wait", "meeting", "data-entry", "check", "escalation", "automated", "notification", "visit", "payment", "report"

Process description:
{$description}

Return ONLY a valid JSON object with this exact structure:
{"lanes":[],"steps":[...],"connections":[...]}

Rules:
- "lanes" must be empty [].
- Each step: step_number (integer from {$nextStepNumber}), lane_name (must match one of: {$laneList}), title (max 8 words), description (one sentence), step_type, action_type.
- {$connectionNote}
- Every step except "end" must appear in at least one connection as "from". The first step must NOT appear as "to" unless it is a loop.

No explanation, no preamble, no <think> tags — only the JSON. /no_think
PROMPT;
} else {
    $prompt = <<<PROMPT
You are a process mapping assistant for a local council. Given a description of a business process, identify the roles or teams involved, extract the steps, and define the connections (arrows) between steps. Return them as structured JSON.

Step types: "start" (use once, first step), "task" (most steps), "decision" (yes/no branch), "end" (use once, last step), "subprocess", "parallel"
Action types: "general", "phone", "document", "email", "letter", "wait", "meeting", "data-entry", "check", "escalation", "automated", "notification", "visit", "payment", "report"

Process description:
{$description}

Return ONLY a valid JSON object with this exact structure:
{"lanes":["Role A","Role B"],"steps":[...],"connections":[...]}

Rules:
- "lanes" should list distinct roles or teams (e.g. ["Tenant","Customer First","Scheduling System"]).
- Each step: step_number (integer from {$nextStepNumber}), lane_name (must exactly match a lane you listed), title (max 8 words, present tense), description (one sentence), step_type, action_type.
- {$connectionNote}
- Every step except "end" must appear in at least one connection as "from". The first step must NOT appear as "to" unless it is a loop.

No explanation, no preamble, no <think> tags — only the JSON. /no_think
PROMPT;
}

// ── Choose AI source: Groq → Gemini → Ollama ─────────────────────────
// resolve_* checks the user's personal key first, then falls back to site-wide env key
$groqKey   = resolve_groq_key();
$geminiKey = resolve_gemini_key();
$modelLabel = '';
$rawResponse = '';

if (strlen($groqKey) > 10) {
    $raw = groq_generate($prompt, $groqKey);
    if ($raw === false) {
        http_response_code(503);
        echo json_encode(['error' => 'Could not reach the Groq API — check your key or internet connection.']);
        exit;
    }
    $groqData = json_decode($raw, true);
    if (isset($groqData['error'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Groq error: ' . ($groqData['error']['message'] ?? 'unknown')]);
        exit;
    }
    $rawResponse = groq_extract_text($raw) ?? '';
    $modelLabel  = 'Groq — Llama 3.3 70B';

} elseif (strlen($geminiKey) > 10) {
    $raw = gemini_generate($prompt, $geminiKey);
    if ($raw === false) {
        http_response_code(503);
        echo json_encode(['error' => 'Could not reach the Gemini API — check your key or internet connection.']);
        exit;
    }
    $geminiData = json_decode($raw, true);
    if (isset($geminiData['error'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Gemini error: ' . ($geminiData['error']['message'] ?? 'unknown')]);
        exit;
    }
    $rawResponse = gemini_extract_text($raw) ?? '';
    $modelLabel  = 'Gemini 2.0 Flash';

} else {
    $model = ollama_detect_model();
    if ($model === null) {
        http_response_code(503);
        echo json_encode(['error' => 'No AI configured. Ask your administrator to add a Groq or Gemini API key in AI settings.', 'hint' => 'install']);
        exit;
    }
    $raw = ollama_generate($model, $prompt);
    if ($raw === false) {
        http_response_code(503);
        echo json_encode(['error' => 'Ollama did not respond — is it running?']);
        exit;
    }
    $ollamaData = json_decode($raw, true);
    if (!isset($ollamaData['response'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Unexpected Ollama response format']);
        exit;
    }
    $rawResponse = preg_replace('/<think>.*?<\/think>/si', '', $ollamaData['response']) ?? '';
    $modelLabel  = $model;
}

$parsed = json_decode(trim($rawResponse), true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['steps']) || !is_array($parsed['steps'])) {
    error_log('ai-parse: could not parse response: ' . $rawResponse);
    http_response_code(500);
    echo json_encode(['error' => 'Could not parse AI response as structured steps']);
    exit;
}

$validStepTypes   = ['start', 'task', 'decision', 'end', 'subprocess', 'parallel'];
$validActionTypes = array_keys(action_type_options());

// Collect suggested lanes (only populated when no lanes existed before)
$suggestedLanes = [];
if (!$hasLanes) {
    if (!empty($parsed['lanes']) && is_array($parsed['lanes'])) {
        foreach ($parsed['lanes'] as $name) {
            $name = substr(trim((string) $name), 0, 120);
            if ($name !== '') $suggestedLanes[] = $name;
        }
    }
    // Fallback: if the AI returned no lanes, create a single default lane
    if ($suggestedLanes === []) {
        $suggestedLanes = ['General'];
    }
}

// Build a lookup using existing lanes OR suggested lane names for step matching
$laneByName = [];
foreach ($lanes as $lane) {
    $laneByName[strtolower(trim($lane['name']))] = $lane;
}
// For the no-lanes case, create virtual lane entries keyed by suggested name
foreach ($suggestedLanes as $name) {
    $key = strtolower($name);
    if (!isset($laneByName[$key])) {
        $laneByName[$key] = ['id' => 0, 'name' => $name]; // id=0 means not yet created
    }
}

$cleanSteps = [];
foreach ($parsed['steps'] as $step) {
    $laneName    = trim((string) ($step['lane_name'] ?? ''));
    $matchedLane = $laneByName[strtolower($laneName)] ?? null;

    if ($matchedLane === null) {
        foreach ($laneByName as $key => $candidate) {
            if (str_contains($key, strtolower($laneName)) || str_contains(strtolower($laneName), $key)) {
                $matchedLane = $candidate;
                break;
            }
        }
        $matchedLane ??= array_values($laneByName)[0] ?? ['id' => 0, 'name' => $laneName];
    }

    $cleanSteps[] = [
        'step_number' => max(1, (int) ($step['step_number'] ?? 1)),
        'lane_id'     => (int) $matchedLane['id'],
        'lane_name'   => $matchedLane['name'],
        'title'       => substr(trim((string) ($step['title'] ?? 'Untitled step')), 0, 120),
        'description' => substr(trim((string) ($step['description'] ?? '')), 0, 500),
        'step_type'   => in_array($step['step_type'] ?? '', $validStepTypes, true) ? $step['step_type'] : 'task',
        'action_type' => in_array($step['action_type'] ?? '', $validActionTypes, true) ? $step['action_type'] : 'general',
    ];
}

// Extract and validate connections
$cleanConnections = [];
if (!empty($parsed['connections']) && is_array($parsed['connections'])) {
    $stepNumbers = array_column($cleanSteps, 'step_number');
    foreach ($parsed['connections'] as $conn) {
        $from  = (int) ($conn['from'] ?? 0);
        $to    = (int) ($conn['to']   ?? 0);
        $label = substr(trim((string) ($conn['label'] ?? '')), 0, 80);
        if ($from > 0 && $to > 0 && $from !== $to
            && in_array($from, $stepNumbers, true)
            && in_array($to,   $stepNumbers, true)) {
            $cleanConnections[] = ['from' => $from, 'to' => $to, 'label' => $label];
        }
    }
}

echo json_encode(['lanes' => $suggestedLanes, 'steps' => $cleanSteps, 'connections' => $cleanConnections, 'model' => $modelLabel]);
