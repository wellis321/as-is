<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo   = db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/import.php'); }

    // Accept JSON from the textarea (edited) or fall back to file upload
    $raw = trim((string) ($_POST['json_content'] ?? ''));

    if ($raw === '' && isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $raw = file_get_contents($_FILES['json_file']['tmp_name']);
    }

    if ($raw === '') {
        $error = 'Please upload a file or paste JSON into the editor.';
    } else {
        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['title'], $data['lanes'], $data['steps'])) {
            $error = 'Invalid JSON. Make sure the file was exported from this tool, or check the structure below.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Document
                $document = create_document(
                    $pdo,
                    trim($data['title']),
                    trim($data['description'] ?? ''),
                    'draft',
                    trim($data['owner']        ?? ''),
                    trim($data['department']   ?? ''),
                    trim($data['captured_date'] ?? ''),
                    trim($data['version']      ?? '')
                );
                $asIsId = (int) $document['id'];

                // 2. Lanes
                $laneIdOf = [];
                foreach ($data['lanes'] as $lane) {
                    $laneIdOf[trim($lane['name'])] = create_lane(
                        $pdo, $asIsId, trim($lane['name']), trim($lane['color'] ?? '#e8f0fe')
                    );
                }

                // 3. Systems (upsert by name)
                $sysIdOf = [];
                foreach ($data['steps'] as $step) {
                    foreach ($step['systems'] ?? [] as $sysName) {
                        $sysName = trim($sysName);
                        if ($sysName === '' || isset($sysIdOf[$sysName])) continue;
                        $row = $pdo->prepare('SELECT id FROM systems WHERE name = ?');
                        $row->execute([$sysName]);
                        $existing = $row->fetchColumn();
                        $sysIdOf[$sysName] = $existing
                            ? (int) $existing
                            : (function() use ($pdo, $sysName) {
                                $pdo->prepare('INSERT INTO systems (name) VALUES (?)')->execute([$sysName]);
                                return (int) $pdo->lastInsertId();
                            })();
                    }
                }

                // 4. Steps
                $stepIdOf = [];
                foreach ($data['steps'] as $step) {
                    $laneName = trim($step['lane'] ?? '');
                    $laneId   = $laneIdOf[$laneName] ?? (array_values($laneIdOf)[0] ?? 0);
                    if (!$laneId) continue;

                    $stepId = create_step(
                        $pdo, $asIsId, $laneId,
                        (int) ($step['step_number'] ?? 0),
                        trim($step['title']       ?? 'Untitled'),
                        trim($step['description'] ?? ''),
                        valid_step_type($step['step_type']   ?? 'task'),
                        valid_action_type($step['action_type'] ?? 'general')
                    );
                    $stepIdOf[(int) $step['step_number']] = $stepId;

                    foreach ($step['systems'] ?? [] as $sysName) {
                        $sysName = trim($sysName);
                        if (isset($sysIdOf[$sysName])) {
                            $pdo->prepare(
                                'INSERT IGNORE INTO step_systems (step_id, system_id) VALUES (?, ?)'
                            )->execute([$stepId, $sysIdOf[$sysName]]);
                        }
                    }
                }

                // 5. Connections
                foreach ($data['connections'] ?? [] as $conn) {
                    $fromId = $stepIdOf[(int)($conn['from'] ?? 0)] ?? null;
                    $toId   = $stepIdOf[(int)($conn['to']   ?? 0)] ?? null;
                    if ($fromId && $toId) {
                        $pdo->prepare(
                            'INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES (?, ?, ?)'
                        )->execute([$fromId, $toId, $conn['label'] ?? null]);
                    }
                }

                $pdo->commit();
                redirect('/edit.php?slug=' . rawurlencode($document['slug']));

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Import failed: ' . h($e->getMessage());
            }
        }
    }
}

$exampleJson = json_encode([
    'as_is_version' => '1.0',
    'title'         => 'My process map',
    'description'   => 'What this process covers',
    'owner'         => 'Team name',
    'department'    => 'Department',
    'captured_date' => '2024-01-01',
    'version'       => 'v1.0',
    'lanes' => [
        ['name' => 'Tenant',         'color' => '#fff3e0'],
        ['name' => 'Housing Officer','color' => '#e8f5e9'],
    ],
    'steps' => [
        ['step_number' => 1, 'lane' => 'Tenant',         'title' => 'Report repair',  'step_type' => 'start', 'action_type' => 'phone'],
        ['step_number' => 2, 'lane' => 'Housing Officer','title' => 'Log request',    'step_type' => 'task',  'action_type' => 'data-entry', 'systems' => ['NEC Housing'], 'description' => 'Log caller details.'],
        ['step_number' => 3, 'lane' => 'Housing Officer','title' => 'Complete repair','step_type' => 'end',   'action_type' => 'visit'],
    ],
    'connections' => [
        ['from' => 1, 'to' => 2],
        ['from' => 2, 'to' => 3, 'label' => 'Approved'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

ob_start();
?>
<header>
    <div>
        <h1>Import process map</h1>
        <p>Load a JSON file or paste JSON directly — review and edit it before creating the diagram.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/documents.php">Back</a>
</header>

<?php if ($error): ?>
    <div class="notice"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid" id="importForm">
        <?= csrf_field() ?>

        <!-- File picker (populates the editor below) -->
        <div>
            <label for="json_file">Load from file</label>
            <input type="file" id="json_file" name="json_file" accept=".json">
            <p class="field-help">
                Selecting a file loads its contents into the editor below where you can review it before importing.
                Export files are available on any diagram's <strong>View</strong> page.
            </p>
        </div>

        <!-- Live JSON editor -->
        <div>
            <label for="json_content" style="display:flex;justify-content:space-between;align-items:center;">
                <span>JSON editor</span>
                <button type="button" id="btnLoadExample"
                        style="font-size:0.78rem;background:none;border:none;cursor:pointer;
                               color:var(--accent);text-decoration:underline;padding:0;">
                    Load example
                </button>
            </label>
            <textarea id="json_content" name="json_content"
                      style="font-family:'IBM Plex Mono',monospace,sans-serif;font-size:0.8rem;
                             line-height:1.55;resize:vertical;min-height:340px;width:100%;
                             border:1px solid var(--border);border-radius:var(--r);
                             padding:0.65rem 0.75rem;box-sizing:border-box;background:var(--bg);"
                      placeholder="Paste JSON here, or load a file above…"><?= isset($_POST['json_content']) ? h($_POST['json_content']) : '' ?></textarea>
            <p class="field-help" id="jsonStatus"></p>
        </div>

        <div class="actions">
            <button class="btn" type="submit" id="btnImport" disabled>Import and create document</button>
            <span style="font-size:0.8rem;color:var(--muted);">The import button enables once valid JSON is entered.</span>
        </div>
    </form>
</div>

<script>
(function () {
    const fileInput  = document.getElementById('json_file');
    const editor     = document.getElementById('json_content');
    const status     = document.getElementById('jsonStatus');
    const btnImport  = document.getElementById('btnImport');
    const btnExample = document.getElementById('btnLoadExample');
    const example    = <?= $exampleJson ?>;

    function validate(text) {
        if (!text.trim()) {
            status.textContent = '';
            btnImport.disabled = true;
            return;
        }
        try {
            const d = JSON.parse(text);
            if (!d.title || !d.lanes || !d.steps) throw new Error('Missing required fields (title, lanes, steps)');
            status.textContent = `✓ Valid — "${d.title}" · ${d.lanes.length} lane${d.lanes.length !== 1 ? 's' : ''} · ${d.steps.length} step${d.steps.length !== 1 ? 's' : ''} · ${(d.connections || []).length} connection${(d.connections || []).length !== 1 ? 's' : ''}`;
            status.style.color = 'var(--success)';
            btnImport.disabled = false;
        } catch (err) {
            status.textContent = '✗ ' + err.message;
            status.style.color = 'var(--danger)';
            btnImport.disabled = true;
        }
    }

    // Load file into editor
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            try {
                // Pretty-print the loaded JSON
                editor.value = JSON.stringify(JSON.parse(e.target.result), null, 2);
                validate(editor.value);
            } catch (err) {
                status.textContent = '✗ File is not valid JSON: ' + err.message;
                status.style.color = 'var(--danger)';
                btnImport.disabled = true;
            }
        };
        reader.readAsText(file);
    });

    // Validate on every keystroke in the editor
    editor.addEventListener('input', () => validate(editor.value));

    // Load example
    btnExample.addEventListener('click', () => {
        editor.value = JSON.stringify(example, null, 2);
        validate(editor.value);
        editor.focus();
    });

    // Validate on load (e.g. if form re-submitted with error)
    validate(editor.value);
})();
</script>
<?php
render_layout('Import', ob_get_clean() ?: '');
