<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo     = db();
$envFile = dirname(__DIR__) . '/.env';
$error   = null;
$success = null;

function update_env_value(string $file, string $key, string $value): bool
{
    $content = is_readable($file) ? (file_get_contents($file) ?: '') : '';
    $line    = $key . '=' . $value;
    $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

    $new = preg_match($pattern, $content)
        ? preg_replace($pattern, $line, $content)
        : rtrim($content) . "\n" . $line . "\n";

    return file_put_contents($file, $new) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/ai-settings.php'); }

    $saved = [];

    foreach (['groq_key' => 'GROQ_API_KEY', 'gemini_key' => 'GEMINI_API_KEY'] as $field => $envKey) {
        if (!array_key_exists($field, $_POST)) continue;
        $val = trim((string) $_POST[$field]);
        if (update_env_value($envFile, $envKey, $val)) {
            $_ENV[$envKey] = $val;
            putenv($envKey . '=' . $val);
            $saved[] = $envKey;
        } else {
            $error = 'Could not write to .env — check file permissions.';
        }
    }

    if (!$error) {
        $success = count($saved) ? 'Settings saved.' : 'No changes.';
    }
}

$groqKey    = env('GROQ_API_KEY',   '') ?? '';
$geminiKey  = env('GEMINI_API_KEY', '') ?? '';

function mask(string $key): string {
    return $key !== '' ? substr($key, 0, 6) . str_repeat('•', max(0, strlen($key) - 6)) : '';
}

ob_start();
?>
<header>
    <div>
        <h1>AI settings</h1>
        <p style="margin:0;">Configure which AI model powers diagram generation.</p>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php elseif ($success): ?>
    <div class="notice notice-success"><?= h($success) ?></div>
<?php endif; ?>

<!-- ── API keys ───────────────────────────────────────────── -->
<div class="card">
    <h2>API keys</h2>
    <p style="font-size:0.875rem;color:var(--muted);margin:0 0 0.5rem;">
        Priority order: <strong>Groq</strong> → Gemini → Ollama.
        Add at least one cloud key — when this site is hosted on a server, Ollama (which runs locally on a developer's machine) will not be available to other users.
    </p>
    <div style="background:oklch(97% 0.015 55);border:1px solid oklch(88% 0.04 55);border-radius:8px;
                padding:0.65rem 0.9rem;font-size:0.8125rem;margin-bottom:1.25rem;">
        <strong>Deployed to Hostinger?</strong> Set the key directly in your <code>.env</code> file via Hostinger's file manager — add a line <code>GROQ_API_KEY=gsk_...</code> and save. All users of the site will then share that key automatically.
    </div>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>

        <div>
            <label for="groq_key">Groq API key <span style="font-weight:400;color:var(--muted);">(recommended — free, no card)</span></label>
            <input type="text" id="groq_key" name="groq_key"
                   value="<?= h($groqKey) ?>"
                   placeholder="gsk_…"
                   autocomplete="off"
                   style="font-family:monospace;max-width:520px;">
            <p class="field-help">
                <?php if ($groqKey !== ''): ?>
                    Key set: <code><?= h(mask($groqKey)) ?></code> &mdash;
                    uses <strong>Llama 3.3 70B</strong>.
                <?php else: ?>
                    Get a free key at <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a> — no credit card needed.
                <?php endif; ?>
            </p>
        </div>

        <div>
            <label for="gemini_key">Google Gemini API key <span style="font-weight:400;color:var(--muted);">(fallback if no Groq key)</span></label>
            <input type="text" id="gemini_key" name="gemini_key"
                   value="<?= h($geminiKey) ?>"
                   placeholder="AIza…"
                   autocomplete="off"
                   style="font-family:monospace;max-width:520px;">
            <p class="field-help">
                <?php if ($geminiKey !== ''): ?>
                    Key set: <code><?= h(mask($geminiKey)) ?></code> &mdash;
                    uses <strong>Gemini 2.0 Flash</strong>.
                <?php else: ?>
                    Get a key at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>.
                <?php endif; ?>
            </p>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Save</button>
        </div>
    </form>
</div>

<!-- ── Active source ──────────────────────────────────────── -->
<div class="card">
    <h2>Active AI source</h2>
    <table>
        <thead><tr><th>Source</th><th>Status</th><th>Model</th></tr></thead>
        <tbody>
            <tr <?= $groqKey !== '' ? 'style="background:oklch(97% 0.01 145);"' : '' ?>>
                <td><strong>Groq</strong></td>
                <td><?php if ($groqKey !== ''): ?><span class="badge badge-start">Active</span><?php else: ?><span class="badge">No key</span><?php endif; ?></td>
                <td style="font-size:0.8rem;color:var(--muted);">Llama 3.3 70B — fast, free, 70B parameters</td>
            </tr>
            <tr <?= ($groqKey === '' && $geminiKey !== '') ? 'style="background:oklch(97% 0.01 145);"' : '' ?>>
                <td><strong>Gemini</strong></td>
                <td><?php if ($geminiKey !== ''): ?><span class="badge badge-start"><?= $groqKey !== '' ? 'Available' : 'Active' ?></span><?php else: ?><span class="badge">No key</span><?php endif; ?></td>
                <td style="font-size:0.8rem;color:var(--muted);">Gemini 2.0 Flash — cloud, free tier</td>
            </tr>
            <tr <?= ($groqKey === '' && $geminiKey === '') ? 'style="background:oklch(97% 0.01 145);"' : '' ?>>
                <td><strong>Ollama</strong></td>
                <td><span class="badge"><?= ($groqKey === '' && $geminiKey === '') ? 'Active' : 'Fallback' ?></span></td>
                <td style="font-size:0.8rem;color:var(--muted);">Local development only — not available on hosted servers</td>
            </tr>
        </tbody>
    </table>
</div>
<?php
render_layout('AI settings', ob_get_clean() ?: '');
