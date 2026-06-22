<?php

declare(strict_types=1);

/**
 * Return the best available Groq key: user's own key first, then site-wide key.
 */
function resolve_groq_key(): string
{
    // Try user's personal key from DB
    if (function_exists('get_current_user_id') && function_exists('db')) {
        $userId = get_current_user_id();
        if ($userId > 0) {
            try {
                $stmt = db()->prepare('SELECT groq_api_key FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $key = (string) ($stmt->fetchColumn() ?? '');
                if (strlen($key) > 10) return $key;
            } catch (Throwable) { /* fall through */ }
        }
    }
    // Fall back to site-wide key
    return env('GROQ_API_KEY', '') ?? '';
}

/**
 * Return the best available Gemini key: user's own key first, then site-wide key.
 */
function resolve_gemini_key(): string
{
    if (function_exists('get_current_user_id') && function_exists('db')) {
        $userId = get_current_user_id();
        if ($userId > 0) {
            try {
                $stmt = db()->prepare('SELECT gemini_api_key FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $key = (string) ($stmt->fetchColumn() ?? '');
                if (strlen($key) > 10) return $key;
            } catch (Throwable) { /* fall through */ }
        }
    }
    return env('GEMINI_API_KEY', '') ?? '';
}

/**
 * Call the Gemini 2.0 Flash API with a prompt.
 * Returns the raw response string or false on failure.
 */
function gemini_generate(string $prompt, string $apiKey): string|false
{
    $payload = json_encode([
        'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $result  = curl_exec($ch);
    $curlErr = curl_error($ch);

    if ($result === false) {
        error_log('gemini_generate curl error: ' . $curlErr);
        return false;
    }

    return $result;
}

/**
 * Parse the Gemini API response and return the text content.
 * Returns null on failure.
 */
function gemini_extract_text(string $response): ?string
{
    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/**
 * Call Groq (OpenAI-compatible) with a prompt. Returns raw response or false.
 */
function groq_generate(string $prompt, string $apiKey): string|false
{
    $payload = json_encode([
        'model'           => 'llama-3.3-70b-versatile',
        'messages'        => [
            ['role' => 'system', 'content' => 'You are a process mapping assistant for a local council. Return ONLY valid JSON — no explanation, no markdown, no prose.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature'     => 0.2,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $result  = curl_exec($ch);
    $curlErr = curl_error($ch);

    if ($result === false) {
        error_log('groq_generate curl error: ' . $curlErr);
        return false;
    }

    return $result;
}

/**
 * Parse the Groq (OpenAI-compatible) response and return the content text.
 */
function groq_extract_text(string $response): ?string
{
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

/**
 * Detect which Ollama model to use.
 */
function ollama_detect_model(): ?string
{
    $ch = curl_init('http://localhost:11434/api/tags');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
    $raw = curl_exec($ch);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (empty($data['models'])) return null;

    $available = array_column($data['models'], 'name');
    $preferred = ['qwen3', 'qwen2.5', 'llama3.2', 'llama3.1', 'llama3', 'mistral', 'phi3', 'phi', 'gemma'];

    foreach ($preferred as $p) {
        foreach ($available as $name) {
            if (str_starts_with($name, $p)) return $name;
        }
    }

    return $available[0] ?? null;
}

/**
 * Call Ollama /api/generate. Returns raw response or false.
 */
function ollama_generate(string $model, string $prompt): string|false
{
    $payload = json_encode([
        'model'   => $model,
        'prompt'  => $prompt,
        'stream'  => false,
        'format'  => 'json',
        'think'   => false,
        'options' => ['temperature' => 0.2],
    ]);

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $result  = curl_exec($ch);
    $curlErr = curl_error($ch);

    if ($result === false) {
        error_log('ollama_generate curl error: ' . $curlErr);
        return false;
    }

    return $result;
}
