<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo      = db();
$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/documents.php');
}

if (!can_edit_document($document)) {
    redirect('/view.php?slug=' . rawurlencode($document['slug']));
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

// Build the current document JSON (same structure as export.php)
$currentJson = json_encode([
    'as_is_version' => '1.0',
    'title'         => $document['title'],
    'description'   => $document['description'] ?? '',
    'owner'         => $document['owner']        ?? '',
    'department'    => $document['department']   ?? '',
    'captured_date' => $document['captured_date'] ?? '',
    'version'       => $document['version']      ?? '',
    'lanes' => array_values(array_map(fn($l) => [
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
        'pain_points' => ($s['pain_points'] ?? '') ?: null,
    ], fn($v) => $v !== null && $v !== ''), $steps)),
    'connections' => array_values(array_map(fn($c) => array_filter([
        'from'  => (int) $c['from_number'],
        'to'    => (int) $c['to_number'],
        'label' => $c['label'] ?: null,
    ], fn($v) => $v !== null), $connections)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ── Apply changes ─────────────────────────────────────────────────────────────
$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }

    $raw = trim((string) ($_POST['json_content'] ?? ''));

    if ($raw === '') {
        $error = 'JSON cannot be empty.';
    } else {
        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['title'], $data['lanes'], $data['steps'])) {
            $error = 'Invalid JSON — must include title, lanes, and steps.';
        } else {
            try {
                // Save current state BEFORE the transaction — so if apply fails
                // and we rollback, the protective snapshot is still committed.
                save_document_version($pdo, $asIsId, 'Before JSON edit — ' . date('d M Y H:i'));

                $pdo->beginTransaction();

                // 1. Update document metadata
                update_document(
                    $pdo,
                    $asIsId,
                    trim($data['title']),
                    $document['slug'],               // keep existing slug
                    trim($data['description'] ?? ''),
                    valid_status($document['status']),
                    trim($data['owner']        ?? ''),
                    trim($data['department']   ?? ''),
                    trim($data['captured_date'] ?? ''),
                    trim($data['version']      ?? '')
                );

                // 2. Wipe existing content for this document
                $pdo->prepare(
                    'DELETE sc FROM step_connections sc
                     JOIN steps s ON (s.id = sc.from_step_id OR s.id = sc.to_step_id)
                     WHERE s.as_is_id = ?'
                )->execute([$asIsId]);
                $pdo->prepare(
                    'DELETE ss FROM step_systems ss
                     JOIN steps s ON s.id = ss.step_id
                     WHERE s.as_is_id = ?'
                )->execute([$asIsId]);
                $pdo->prepare('DELETE FROM steps WHERE as_is_id = ?')->execute([$asIsId]);
                $pdo->prepare('DELETE FROM lanes WHERE as_is_id = ?')->execute([$asIsId]);

                // 3. Recreate lanes
                $laneIdOf = [];
                foreach ($data['lanes'] as $lane) {
                    $laneIdOf[trim($lane['name'])] = create_lane(
                        $pdo, $asIsId,
                        trim($lane['name']),
                        trim($lane['color'] ?? '#e8f0fe')
                    );
                }

                // 4. Systems (upsert by name)
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

                // 5. Recreate steps
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
                        valid_step_type($step['step_type']    ?? 'task'),
                        valid_action_type($step['action_type'] ?? 'general'),
                        trim($step['pain_points'] ?? '')
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

                // 6. Recreate connections
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
                redirect('/view.php?slug=' . rawurlencode($document['slug']));

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not apply changes: ' . h($e->getMessage());
            }
        }
    }

    // On error, use the submitted JSON so the user doesn't lose their edits
    $currentJson = $_POST['json_content'] ?? $currentJson;
}

ob_start();
?>
<style>
#json-editor {
    font-family: 'IBM Plex Mono', 'Courier New', monospace;
    font-size: 0.8rem;
    line-height: 1.6;
    resize: vertical;
    min-height: 480px;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 0.75rem;
    box-sizing: border-box;
    background: #fff;
    tab-size: 2;
}
#json-editor:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
#json-status { font-size: 0.82rem; min-height: 1.4em; }
.json-stat { display:inline-flex;gap:0.25rem;align-items:center;font-size:0.78rem;color:var(--muted); }
</style>

<header>
    <div>
        <h1><?= h($document['title']) ?></h1>
        <p style="margin:0;color:var(--muted);font-size:0.875rem;">Edit JSON — changes replace the current diagram</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary btn-sm" href="/versions.php?slug=<?= rawurlencode($document['slug']) ?>">Version history</a>
        <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">View diagram</a>
        <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Edit</a>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice" style="border-left:3px solid var(--danger);color:var(--danger);"><?= $error ?></div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden;">
    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:1rem;padding:0.6rem 1rem;
                border-bottom:1px solid var(--border);background:var(--bg);flex-wrap:wrap;">
        <span id="json-status" style="flex:1;min-width:0;"></span>
        <div style="display:flex;gap:0.5rem;flex-shrink:0;">
            <button type="button" id="btn-format" class="btn btn-secondary btn-sm">Format</button>
            <button type="button" id="btn-reset"  class="btn btn-secondary btn-sm">Reset</button>
        </div>
    </div>

    <!-- Editor -->
    <form method="post" id="json-form" style="display:contents;">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <textarea id="json-editor" name="json_content"><?= h($currentJson) ?></textarea>

        <!-- Footer -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;
                    padding:0.75rem 1rem;border-top:1px solid var(--border);background:var(--bg);flex-wrap:wrap;">
            <p style="margin:0;font-size:0.8rem;color:var(--muted);max-width:42rem;">
                Applying replaces all lanes, steps and connections in this document.
                The slug and sharing settings are preserved.
            </p>
            <div style="display:flex;gap:0.5rem;flex-shrink:0;">
                <a class="btn btn-secondary" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">Cancel</a>
                <button class="btn" type="submit" id="btn-apply" disabled>Apply changes</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const editor     = document.getElementById('json-editor');
    const status     = document.getElementById('json-status');
    const btnApply   = document.getElementById('btn-apply');
    const btnFormat  = document.getElementById('btn-format');
    const btnReset   = document.getElementById('btn-reset');
    const original   = editor.value;

    function validate() {
        const text = editor.value.trim();
        if (!text) {
            status.textContent = '';
            status.style.color = '';
            btnApply.disabled = true;
            return;
        }
        try {
            const d = JSON.parse(text);
            if (!d.title || !Array.isArray(d.lanes) || !Array.isArray(d.steps)) {
                throw new Error('Missing required fields: title, lanes, steps');
            }
            const conns = (d.connections || []).length;
            status.innerHTML =
                '<span style="color:var(--success);">✓ Valid</span>' +
                ' &nbsp;·&nbsp; ' + d.lanes.length + ' lane' + (d.lanes.length !== 1 ? 's' : '') +
                ' &nbsp;·&nbsp; ' + d.steps.length + ' step' + (d.steps.length !== 1 ? 's' : '') +
                ' &nbsp;·&nbsp; ' + conns + ' connection' + (conns !== 1 ? 's' : '');
            btnApply.disabled = false;
        } catch (err) {
            status.innerHTML = '<span style="color:var(--danger);">✗ ' + err.message + '</span>';
            btnApply.disabled = true;
        }
    }

    editor.addEventListener('input', validate);

    btnFormat.addEventListener('click', () => {
        try {
            editor.value = JSON.stringify(JSON.parse(editor.value), null, 2);
            validate();
        } catch (e) { /* ignore if not valid yet */ }
    });

    btnReset.addEventListener('click', () => {
        if (editor.value !== original && !confirm('Reset to the last saved version? Your edits will be lost.')) return;
        editor.value = original;
        validate();
    });

    // Tab key inserts 2 spaces instead of changing focus
    editor.addEventListener('keydown', e => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const s = editor.selectionStart;
            const v = editor.value;
            editor.value = v.slice(0, s) + '  ' + v.slice(editor.selectionEnd);
            editor.selectionStart = editor.selectionEnd = s + 2;
        }
    });

    validate();
})();
</script>
<?php
render_layout('Edit JSON — ' . $document['title'], ob_get_clean() ?: '');
