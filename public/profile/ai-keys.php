<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();

$pdo    = db();
$userId = get_current_user_id();
$error  = null;
$success = null;

ensure_schema($pdo);

// Load current keys
$row = $pdo->prepare('SELECT groq_api_key, gemini_api_key FROM users WHERE id = ? LIMIT 1');
$row->execute([$userId]);
$user = $row->fetch();

$currentGroq   = (string) ($user['groq_api_key']   ?? '');
$currentGemini = (string) ($user['gemini_api_key'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/profile/ai-keys.php'); }

    $groqKey   = trim((string) ($_POST['groq_key']   ?? ''));
    $geminiKey = trim((string) ($_POST['gemini_key'] ?? ''));

    $pdo->prepare('UPDATE users SET groq_api_key = ?, gemini_api_key = ? WHERE id = ?')
        ->execute([
            $groqKey   !== '' ? $groqKey   : null,
            $geminiKey !== '' ? $geminiKey : null,
            $userId,
        ]);

    $currentGroq   = $groqKey;
    $currentGemini = $geminiKey;
    $success = 'API key settings saved.';
}

function mask_key(string $key): string
{
    return $key !== '' ? substr($key, 0, 6) . str_repeat('•', max(0, strlen($key) - 6)) : '';
}

$activeSource = $currentGroq !== '' ? 'Groq (Llama 3.3 70B)' : ($currentGemini !== '' ? 'Gemini 2.0 Flash' : 'Site default');

ob_start();
?>
<header>
    <div>
        <h1>My AI key</h1>
        <p style="margin:0;">Set your own API key for AI diagram generation.</p>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php elseif ($success): ?>
    <div class="notice notice-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="card">
    <p style="font-size:0.875rem;color:var(--muted);margin:0 0 1rem;">
        Your personal key is used instead of the site-wide key, so your usage is separate from other users.
        If you do not set one, the site's shared key (set by your administrator) is used automatically.
        Priority order: <strong>your key</strong> → site key → local Ollama.
    </p>

    <div style="background:oklch(97% 0.015 220);border:1px solid oklch(88% 0.04 220);border-radius:8px;
                padding:0.65rem 0.9rem;font-size:0.8125rem;margin-bottom:1.25rem;">
        <strong>Getting a free Groq key:</strong>
        go to <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a>,
        sign in with Google or GitHub, and click <em>Create API key</em>. Takes about two minutes, no credit card needed.
    </div>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>

        <div>
            <label for="groq_key">Groq API key <span style="font-weight:400;color:var(--muted);">(recommended — free, fast)</span></label>
            <input type="text" id="groq_key" name="groq_key"
                   value="<?= h($currentGroq) ?>"
                   placeholder="gsk_…"
                   autocomplete="off"
                   style="font-family:monospace;max-width:520px;">
            <?php if ($currentGroq !== ''): ?>
                <p class="field-help">Saved: <code><?= h(mask_key($currentGroq)) ?></code></p>
            <?php else: ?>
                <p class="field-help">Not set — site default will be used.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="gemini_key">Google Gemini API key <span style="font-weight:400;color:var(--muted);">(alternative)</span></label>
            <input type="text" id="gemini_key" name="gemini_key"
                   value="<?= h($currentGemini) ?>"
                   placeholder="AIza…"
                   autocomplete="off"
                   style="font-family:monospace;max-width:520px;">
            <?php if ($currentGemini !== ''): ?>
                <p class="field-help">Saved: <code><?= h(mask_key($currentGemini)) ?></code></p>
            <?php else: ?>
                <p class="field-help">Not set — site default will be used.</p>
            <?php endif; ?>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Save</button>
            <?php if ($currentGroq !== '' || $currentGemini !== ''): ?>
                <button class="btn btn-secondary" type="submit"
                        onclick="document.getElementById('groq_key').value='';document.getElementById('gemini_key').value='';">
                    Clear my keys
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Your active AI source</h2>
    <p style="margin:0;">
        <?php if ($currentGroq !== ''): ?>
            <span class="badge badge-start">Groq — Llama 3.3 70B</span>
            &nbsp; Using your personal Groq key.
        <?php elseif ($currentGemini !== ''): ?>
            <span class="badge badge-start">Gemini 2.0 Flash</span>
            &nbsp; Using your personal Gemini key.
        <?php else: ?>
            <span class="badge">Site default</span>
            &nbsp; Using the key set by your administrator (if configured).
        <?php endif; ?>
    </p>
</div>
<?php
render_layout('My AI key', ob_get_clean() ?: '');
