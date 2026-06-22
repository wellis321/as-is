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

// ── Load PDF parser ───────────────────────────────────────────────────
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

/**
 * Extract plain text from an uploaded file.
 * Returns the text or throws a RuntimeException with a user-friendly message.
 */
function extract_file_text(string $tmpPath, string $origName): string
{
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        if (!class_exists('\Smalot\PdfParser\Parser')) {
            throw new RuntimeException('PDF parsing library not installed — run composer install.');
        }
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($tmpPath);
            $text   = $pdf->getText();
        } catch (Throwable) {
            throw new RuntimeException("Could not read \"{$origName}\" — it may be scanned or image-only.");
        }
        if (trim($text) === '') {
            throw new RuntimeException("\"{$origName}\" appears to be an image-only PDF with no selectable text.");
        }
        return $text;
    }

    if (in_array($ext, ['txt', 'md'], true)) {
        $text = file_get_contents($tmpPath);
        if ($text === false) {
            throw new RuntimeException('Could not read "' . $origName . '".');
        }
        return $text;
    }

    if ($ext === 'docx') {
        try {
            $phpWord  = \PhpOffice\PhpWord\IOFactory::load($tmpPath);
            $sections = $phpWord->getSections();
            $lines    = [];
            foreach ($sections as $section) {
                foreach ($section->getElements() as $el) {
                    if (method_exists($el, 'getElements')) {
                        foreach ($el->getElements() as $child) {
                            if (method_exists($child, 'getText')) {
                                $t = trim($child->getText());
                                if ($t !== '') $lines[] = $t;
                            }
                        }
                    } elseif (method_exists($el, 'getText')) {
                        $t = trim($el->getText());
                        if ($t !== '') $lines[] = $t;
                    }
                }
            }
            $text = implode("\n", $lines);
            if (trim($text) === '') {
                throw new RuntimeException('"' . $origName . '" appears to be empty or has no extractable text.');
            }
            return $text;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException('Could not read "' . $origName . '" — it may be corrupt or an unsupported Word format.');
        }
    }

    if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
            $lines       = [];
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheetTitle = $sheet->getTitle();
                $lines[]    = "Sheet: {$sheetTitle}";
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $val = (string) $cell->getFormattedValue();
                        if ($val !== '') $cells[] = $val;
                    }
                    if ($cells !== []) $lines[] = implode(' | ', $cells);
                }
            }
            $text = implode("\n", $lines);
            if (trim($text) === '') {
                throw new RuntimeException('"' . $origName . '" appears to be empty.');
            }
            return $text;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            throw new RuntimeException('Could not read "' . $origName . '" — it may be corrupt or password-protected.');
        }
    }

    throw new RuntimeException('"' . $origName . '" is not a supported file type. Upload PDF, Word (.docx), Excel (.xlsx/.csv), or plain text files.');
}

// ── Collect text from all sources ────────────────────────────────────
$sources = []; // ['label' => '...', 'text' => '...']
$errors  = [];

// Multiple file uploads: <input name="documents[]" multiple>
if (!empty($_FILES['documents']['tmp_name'])) {
    $files = $_FILES['documents'];
    // Normalise to array regardless of single vs multiple
    $tmpNames  = (array) $files['tmp_name'];
    $names     = (array) $files['name'];
    $fileErrors = (array) $files['error'];

    foreach ($tmpNames as $i => $tmpPath) {
        $uploadErr = (int) ($fileErrors[$i] ?? UPLOAD_ERR_NO_FILE);
        $origName  = (string) ($names[$i] ?? 'file');

        if ($uploadErr === UPLOAD_ERR_NO_FILE) continue;
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error for "' . $origName . '" (code ' . $uploadErr . ').';
            continue;
        }

        try {
            $text = extract_file_text($tmpPath, $origName);
            $sources[] = ['label' => $origName, 'text' => trim($text)];
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Pasted text
$pasteText = trim((string) ($_POST['paste_text'] ?? ''));
if ($pasteText !== '') {
    $sources[] = ['label' => 'Pasted text', 'text' => $pasteText];
}

if ($sources === []) {
    $msg = $errors !== []
        ? implode(' ', $errors)
        : 'No documents or text provided.';
    http_response_code(400);
    echo json_encode(['error' => $msg]);
    exit;
}

// Report any non-fatal errors alongside the result
$warningMsg = $errors !== [] ? implode(' ', $errors) : null;

// ── Proportional text budget: 12,000 chars total ─────────────────────
$totalBudget = 12000;
$count       = count($sources);
$perDoc      = (int) floor($totalBudget / $count);

$documentBlocks = [];
foreach ($sources as $source) {
    $text = $source['text'];
    if (strlen($text) > $perDoc) {
        $text = substr($text, 0, $perDoc) . "\n[Truncated]";
    }
    $documentBlocks[] = "--- Document: {$source['label']} ---\n{$text}";
}

$combinedText = implode("\n\n", $documentBlocks);

// ── Build prompt ──────────────────────────────────────────────────────
$multiDoc = $count > 1;
$docWord  = $multiDoc ? 'documents' : 'document';
$synthNote = $multiDoc
    ? 'The documents may cover the same process from different angles, or different parts of the same process. Synthesise them into one coherent end-to-end description.'
    : '';

$prompt = <<<PROMPT
You are a process analyst for a local council. Read the {$docWord} below and write a plain-English description of the business process they describe.

Your description should:
- Describe what happens step by step, in the order it happens
- Name the people or teams involved (these will become swimlanes in a process map)
- Include any decision points (e.g. "if the repair is emergency…")
- Include any handoffs between teams
- Include any loops or repeat steps
- Be written as flowing prose — not bullet points
- Be concise but complete — aim for 4 to 8 sentences covering the full process
- Focus only on the process itself, not background context or policy
{$synthNote}

{$combinedText}

Write only the process description. No introduction, no headings, no bullet points — just the plain-English description.
PROMPT;

// ── Call AI ───────────────────────────────────────────────────────────
$groqKey    = resolve_groq_key();
$geminiKey  = resolve_gemini_key();
$modelLabel = '';
$description = '';

if (strlen($groqKey) > 10) {
    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => 'You are a process analyst. Write plain-English process descriptions from documents. Return only the description — no JSON, no headings, no bullet points.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature' => 0.3,
        'max_tokens'  => 600,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $groqKey],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $raw = curl_exec($ch);

    if ($raw !== false) {
        $data = json_decode($raw, true);
        if (isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => 'Groq error: ' . ($data['error']['message'] ?? 'unknown')]);
            exit;
        }
        $description = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        $modelLabel  = 'Groq — Llama 3.3 70B';
    }

} elseif (strlen($geminiKey) > 10) {
    $geminiPayload = json_encode([
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['maxOutputTokens' => 600, 'temperature' => 0.3],
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($geminiKey));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $geminiPayload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $raw = curl_exec($ch);

    if ($raw !== false) {
        $data = json_decode($raw, true);
        if (isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => 'Gemini error: ' . ($data['error']['message'] ?? 'unknown')]);
            exit;
        }
        $description = trim(gemini_extract_text($raw) ?? '');
        $modelLabel  = 'Gemini 2.0 Flash';
    }

} else {
    $model         = ollama_detect_model();
    $ollamaPayload = json_encode([
        'model'   => $model ?? 'qwen3',
        'prompt'  => $prompt,
        'stream'  => false,
        'think'   => false,
        'options' => ['temperature' => 0.3],
    ]);

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $ollamaPayload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 120,
    ]);
    $raw = curl_exec($ch);

    if ($raw !== false) {
        $data        = json_decode($raw, true);
        $description = trim((string) ($data['response'] ?? ''));
        $modelLabel  = $model ?? 'Ollama';
    }
}

if ($description === '') {
    http_response_code(503);
    echo json_encode(['error' => 'No AI configured or AI did not respond. Add a Groq key in AI settings.']);
    exit;
}

$response = [
    'description' => $description,
    'model'       => $modelLabel,
    'sources'     => array_column($sources, 'label'),
];
if ($warningMsg !== null) {
    $response['warning'] = $warningMsg;
}

echo json_encode($response);
