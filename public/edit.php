<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ai.php';

require_min_role('editor');

$pdo = db();
ensure_schema($pdo);

$document = resolve_document_request($pdo);
$error   = null;
$success = null;

if ($document === null) {
    ob_start();
    ?>
    <header>
        <div><h1>Not found</h1><p>AS-IS document not found.</p></div>
        <a class="btn btn-secondary btn-sm" href="/documents.php">Back</a>
    </header>
    <?php
    render_layout('Not found', ob_get_clean() ?: '');
    return;
}

$asIsId = (int) $document['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $title       = trim((string) ($_POST['title']       ?? ''));
    $slug        = trim((string) ($_POST['slug']        ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $status      = valid_status((string) ($_POST['status'] ?? 'draft'));
    $owner       = trim((string) ($_POST['owner']       ?? ''));
    $department  = trim((string) ($_POST['department']  ?? ''));
    $capturedDate = trim((string) ($_POST['captured_date'] ?? ''));
    $version     = trim((string) ($_POST['version']     ?? ''));

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        try {
            update_document($pdo, $asIsId, $title, $slug, $description, $status,
                            $owner, $department, $capturedDate, $version);
            $document = fetch_document($pdo, $asIsId);
            if ($document === null) {
                throw new RuntimeException('Document could not be reloaded.');
            }
            $success = 'Changes saved.';
        } catch (Throwable $e) {
            $error = 'Could not save your changes. Please try again.';
        }
    }
}

$lanes       = fetch_lanes($pdo, $asIsId);
$steps       = fetch_steps($pdo, $asIsId);
$connections = fetch_connections($pdo, $asIsId);

// Systems used by steps in this document (read-only summary).
$usedSystems = [];
if ($steps !== []) {
    $stepIds  = implode(',', array_map(fn($s) => (int)$s['id'], $steps));
    $sysRows  = $pdo->query(
        "SELECT DISTINCT sys.id, sys.name, sys.description
         FROM systems sys
         INNER JOIN step_systems ss ON ss.system_id = sys.id
         WHERE ss.step_id IN ($stepIds)
         ORDER BY sys.name"
    )->fetchAll();
    $usedSystems = $sysRows;
}
$allSystems = fetch_systems($pdo);

$laneNames = [];
foreach ($lanes as $lane) {
    $laneNames[(int) $lane['id']] = $lane['name'];
}

// ── Workflow stage calculation ────────────────────────────────────
$hasLanes       = $lanes       !== [];
$hasSteps       = $steps       !== [];
$hasConnections = $connections !== [];

if (!$hasLanes)            $currentStage = 2;
elseif (!$hasSteps)        $currentStage = 3;
elseif (!$hasConnections)  $currentStage = 4;
else                       $currentStage = 5; // all complete

$stageClass = static function (int $stage) use ($currentStage): string {
    if ($currentStage === 5 || $stage < $currentStage) return 'is-done';
    if ($stage === $currentStage)                       return 'is-current';
    return 'is-upcoming';
};

ob_start();
?>
<header class="no-print">
    <div>
        <h1><?= h($document['title']) ?></h1>
        <p style="margin:0;">Edit process map</p>
    </div>
    <div class="actions">
        <?php if ($hasSteps): ?>
            <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">View diagram</a>
        <?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="/documents.php">All documents</a>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php elseif ($success): ?>
    <div class="notice notice-success"><?= h($success) ?></div>
<?php endif; ?>

<!-- ── Workflow progress tracker ──────────────────────────────────── -->
<div class="build-tracker no-print">
    <a href="#details"     class="build-stage <?= $stageClass(1) ?>">
        <div class="build-num"><?= $stageClass(1) === 'is-done' ? '<i data-lucide="check" style="width:0.9em;height:0.9em;"></i>' : '1' ?></div>
        <div class="build-info">
            <div class="build-name">Details</div>
            <div class="build-status"><?= $stageClass(1) === 'is-current' ? 'Fill in title &amp; metadata' : 'Complete' ?></div>
        </div>
    </a>
    <a href="#lanes"       class="build-stage <?= $stageClass(2) ?>">
        <div class="build-num"><?= $stageClass(2) === 'is-done' ? '<i data-lucide="check" style="width:0.9em;height:0.9em;"></i>' : '2' ?></div>
        <div class="build-info">
            <div class="build-name">Swimlanes</div>
            <div class="build-status">
                <?php if ($stageClass(2) === 'is-done'): ?><?= count($lanes) ?> lane<?= count($lanes) !== 1 ? 's' : '' ?> added
                <?php else: ?>Add your first lane
                <?php endif; ?>
            </div>
        </div>
    </a>
    <a href="#steps"       class="build-stage <?= $stageClass(3) ?>">
        <div class="build-num"><?= $stageClass(3) === 'is-done' ? '<i data-lucide="check" style="width:0.9em;height:0.9em;"></i>' : '3' ?></div>
        <div class="build-info">
            <div class="build-name">Steps</div>
            <div class="build-status">
                <?php if ($stageClass(3) === 'is-done'):    ?><?= count($steps) ?> step<?= count($steps) !== 1 ? 's' : '' ?> added
                <?php elseif ($stageClass(3) === 'is-current'): ?>Add steps to your lanes
                <?php else:                                      ?>Add lanes first
                <?php endif; ?>
            </div>
        </div>
    </a>
    <a href="#connections" class="build-stage <?= $stageClass(4) ?>">
        <div class="build-num"><?= $stageClass(4) === 'is-done' ? '<i data-lucide="check" style="width:0.9em;height:0.9em;"></i>' : '4' ?></div>
        <div class="build-info">
            <div class="build-name">Connections</div>
            <div class="build-status">
                <?php if ($stageClass(4) === 'is-done'):    ?><?= count($connections) ?> connection<?= count($connections) !== 1 ? 's' : '' ?>
                <?php elseif ($stageClass(4) === 'is-current'): ?>Wire up the flow arrows
                <?php else:                                    ?>Add steps first
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php if ($currentStage === 5): ?>
        <a href="/view.php?slug=<?= rawurlencode($document['slug']) ?>" class="build-tracker-cta">
            View diagram &rarr;
        </a>
    <?php endif; ?>
</div>

<!-- ── Document details ──────────────────────────────────────────── -->
<div class="card" id="details">
    <h2>Details</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">

        <div>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= h($document['title']) ?>" required>
        </div>
        <details>
            <summary style="font-size:0.8125rem;color:var(--muted);cursor:pointer;user-select:none;
                            list-style:none;display:flex;align-items:center;gap:0.3rem;">
                <i data-lucide="settings-2" class="licon" style="width:0.85rem;height:0.85rem;"></i>
                Advanced — short link name
            </summary>
            <div style="margin-top:0.75rem;max-width:480px;">
                <label for="slug">Link name</label>
                <input type="text" id="slug" name="slug" value="<?= h($document['slug']) ?>">
                <p class="field-help">
                    Used when sharing this map. Keep it short, using letters, numbers, and hyphens.
                </p>
            </div>
        </details>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= h((string) ($document['description'] ?? '')) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="owner">Owner</label>
                <input type="text" id="owner" name="owner" value="<?= h((string) ($document['owner'] ?? '')) ?>" placeholder="Name or team">
            </div>
            <div>
                <label for="department">Department</label>
                <input type="text" id="department" name="department" value="<?= h((string) ($document['department'] ?? '')) ?>" placeholder="e.g. Housing">
            </div>
            <div>
                <label for="captured_date">Date captured</label>
                <input type="date" id="captured_date" name="captured_date" value="<?= h((string) ($document['captured_date'] ?? '')) ?>">
            </div>
            <div>
                <label for="version">Version</label>
                <input type="text" id="version" name="version" value="<?= h((string) ($document['version'] ?? '')) ?>" placeholder="e.g. v1.0">
            </div>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status" style="width:auto;">
                <option value="draft"     <?= $document['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $document['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Save details</button>
        </div>
    </form>
</div>

<!-- ── Swimlanes ─────────────────────────────────────────────────── -->
<?php
// Diagram alternates these two colours by lane position — shown as preview swatches.
$laneColours = ['#ffffff', '#e8eaed'];
?>
<div class="card" id="lanes">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.85rem;flex-wrap:wrap;gap:0.5rem;">
        <h2 style="margin:0;">Swimlanes</h2>
        <span style="font-size:0.8125rem;color:var(--muted);">
            Colours alternate automatically in the diagram
            <span style="display:inline-flex;gap:0.25rem;vertical-align:middle;margin-left:0.35rem;">
                <span style="display:inline-block;width:14px;height:14px;background:#ffffff;border:1px solid var(--border);border-radius:2px;"></span>
                <span style="display:inline-block;width:14px;height:14px;background:#e8eaed;border:1px solid var(--border);border-radius:2px;"></span>
            </span>
        </span>
    </div>

    <?php if ($lanes !== []): ?>
        <table>
            <thead>
                <tr>
                    <th style="width:80px;">Order</th>
                    <th>Name</th>
                    <th style="width:80px;">In diagram</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lanes as $i => $lane): ?>
                    <tr>
                        <td style="width:80px;">
                            <?php if ($i > 0): ?>
                                <form class="inline-form" method="post" action="/lane-reorder.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="slug"      value="<?= h($document['slug']) ?>">
                                    <input type="hidden" name="lane_id"   value="<?= (int) $lane['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-link btn-sm" type="submit" title="Move up"><i data-lucide="chevron-up" style="width:1rem;height:1rem;"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($i < count($lanes) - 1): ?>
                                <form class="inline-form" method="post" action="/lane-reorder.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="slug"      value="<?= h($document['slug']) ?>">
                                    <input type="hidden" name="lane_id"   value="<?= (int) $lane['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-link btn-sm" type="submit" title="Move down"><i data-lucide="chevron-down" style="width:1rem;height:1rem;"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?= h($lane['name']) ?></td>
                        <td>
                            <span style="display:inline-block;width:32px;height:18px;border-radius:3px;
                                         background:<?= $laneColours[$i % 2] ?>;border:1px solid var(--border);
                                         vertical-align:middle;"></span>
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="row-actions">
                                <a class="lnk-danger"
                                   href="/lane-delete.php?slug=<?= rawurlencode($document['slug']) ?>&lane_id=<?= (int) $lane['id'] ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <p class="empty-state-title">No swimlanes yet</p>
            <p class="empty-state-body">
                Swimlanes represent the people or teams involved in the process — for example,
                <em>Tenant</em>, <em>Customer First</em>, <em>Technical Officer</em>.
                Add one per role or department. Steps will live inside their lane, and
                connections will show the handoffs between lanes.
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="/lane-create.php" class="form-grid" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border);">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <input type="hidden" name="color" value="#ffffff"><!-- colour is automatic; value unused -->
        <div style="display:grid;grid-template-columns:1fr auto;gap:0.75rem;align-items:end;">
            <div>
                <label for="lane_name">New swimlane name</label>
                <input type="text" id="lane_name" name="name" placeholder="e.g. Customer First" required>
            </div>
            <div>
                <button class="btn btn-secondary" type="submit" style="white-space:nowrap;">Add swimlane</button>
            </div>
        </div>
    </form>
</div>

<!-- ── Systems ───────────────────────────────────────────────────── -->
<div class="card" id="systems">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 0.2rem;">Systems &amp; tools</h2>
            <p style="margin:0;font-size:0.875rem;">
                Systems are shared across all documents.
                Assign them to steps below, or
                <a href="/systems.php">manage the library</a> to add, edit or delete systems.
            </p>
        </div>
        <a class="btn btn-secondary btn-sm" href="/systems.php">Manage library</a>
    </div>

    <?php if ($usedSystems !== []): ?>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.5rem;">
            <?php foreach ($usedSystems as $sys): ?>
                <span class="badge" title="<?= h((string)($sys['description'] ?? '')) ?>"><?= h($sys['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <p style="margin:0.5rem 0 0;font-size:0.8rem;color:var(--muted);">
            These systems are currently assigned to steps in this document.
        </p>
    <?php elseif ($allSystems === []): ?>
        <p style="color:var(--muted);margin:0;">
            No systems in the library yet.
            <a href="/systems.php">Add systems</a> before assigning them to steps.
        </p>
    <?php else: ?>
        <p style="color:var(--muted);margin:0;font-size:0.875rem;">
            No systems assigned to steps yet — assign them when
            <a href="/step-edit.php?slug=<?= rawurlencode($document['slug']) ?>">editing a step</a>.
        </p>
    <?php endif; ?>
</div>

<!-- ── Steps ─────────────────────────────────────────────────────── -->
<div class="card" id="steps">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 0.1rem;">Steps</h2>
            <?php if ($steps !== []): ?>
                <span style="font-size:0.8rem;color:var(--muted);"><?= count($steps) ?> step<?= count($steps) !== 1 ? 's' : '' ?> across <?= count($lanes) ?> lane<?= count($lanes) !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>
        <?php if ($lanes !== []): ?>
            <a class="btn btn-secondary btn-sm" href="/step-edit.php?slug=<?= rawurlencode($document['slug']) ?>">+ Add step</a>
        <?php endif; ?>
    </div>

    <!-- Document → Description ─────────────────────────────────────── -->
    <details style="margin-bottom:1rem;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
        <summary style="display:flex;align-items:center;gap:0.5rem;padding:0.65rem 1rem;
                        cursor:pointer;user-select:none;font-size:0.875rem;font-weight:600;
                        background:var(--bg-subtle,var(--bg));list-style:none;">
            <i data-lucide="file-text" style="width:1rem;height:1rem;color:var(--accent);flex-shrink:0;"></i>
            Extract process from a document
            <span style="font-weight:400;color:var(--muted);margin-left:0.25rem;">&mdash; upload a PDF or paste text, AI writes the description</span>
        </summary>
        <div style="padding:1rem;border-top:1px solid var(--border);">
            <p style="margin:0 0 0.75rem;font-size:0.8125rem;color:var(--muted);">
                Upload a process document or paste its text. AI will read it and write a plain-English
                process description, which you can review and edit before generating the diagram.
            </p>

            <div style="margin-bottom:0.75rem;">
                <label style="font-size:0.8125rem;font-weight:600;display:block;margin-bottom:0.3rem;">
                    Upload documents
                    <span style="font-weight:400;color:var(--muted);">— PDF or text files, select multiple</span>
                </label>
                <input type="file" id="doc-file" accept=".pdf,.docx,.xlsx,.xls,.csv,.txt,.md" multiple
                       style="font-size:0.8rem;width:100%;box-sizing:border-box;">
                <div id="doc-file-list" style="margin-top:0.4rem;display:flex;flex-wrap:wrap;gap:0.3rem;"></div>
                <p style="font-size:0.775rem;color:var(--muted);margin:0.25rem 0 0;">
                    PDF, Word (.docx), Excel (.xlsx, .csv), and plain text. Mix types freely.
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:var(--muted);font-size:0.8rem;">
                <div style="flex:1;height:1px;background:var(--border);"></div>
                and / or
                <div style="flex:1;height:1px;background:var(--border);"></div>
            </div>

            <div style="margin-bottom:0.75rem;">
                <label for="doc-paste" style="font-size:0.8125rem;font-weight:600;display:block;margin-bottom:0.3rem;">Paste document text</label>
                <textarea id="doc-paste" rows="4"
                          style="width:100%;box-sizing:border-box;resize:vertical;font-size:0.8125rem;"
                          placeholder="Paste text from one or more documents here — or combine with uploaded files above…"></textarea>
            </div>

            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                <button type="button" id="doc-extract-btn" class="btn btn-secondary btn-sm">Extract description</button>
                <span id="doc-status" style="font-size:0.8rem;color:var(--muted);"></span>
            </div>

            <div id="doc-result" style="margin-top:0.75rem;display:none;">
                <label for="doc-preview" style="font-size:0.8125rem;font-weight:600;display:block;margin-bottom:0.3rem;">
                    Extracted description — review and edit before generating
                </label>
                <textarea id="doc-preview" rows="5"
                          style="width:100%;box-sizing:border-box;resize:vertical;font-size:0.875rem;
                                 border:2px solid var(--accent);border-radius:6px;"></textarea>
                <div style="margin-top:0.5rem;">
                    <button type="button" id="doc-use-btn" class="btn btn-sm"
                            style="background:var(--accent);color:#fff;border-color:var(--accent);">
                        Use this description
                    </button>
                    <span style="font-size:0.8rem;color:var(--muted);margin-left:0.5rem;">
                        Copies to the Generate panel below — then click Generate
                    </span>
                </div>
            </div>
        </div>
    </details>
    <script>
    (function () {
        const extractBtn = document.getElementById('doc-extract-btn');
        const docStatus  = document.getElementById('doc-status');
        const docResult  = document.getElementById('doc-result');
        const docPreview = document.getElementById('doc-preview');
        const useBtn     = document.getElementById('doc-use-btn');
        const csrf       = <?= json_encode(csrf_token()) ?>;
        const slug       = <?= json_encode($document['slug']) ?>;

        // Show file chips when selection changes
        document.getElementById('doc-file').addEventListener('change', function () {
            const list = document.getElementById('doc-file-list');
            list.innerHTML = '';
            Array.from(this.files).forEach(f => {
                const chip = document.createElement('span');
                chip.style.cssText = 'background:var(--bg);border:1px solid var(--border);border-radius:6px;' +
                                     'padding:0.2rem 0.5rem;font-size:0.775rem;display:flex;align-items:center;gap:0.25rem;';
                const ext  = f.name.split('.').pop().toLowerCase();
                const icon = ext === 'pdf' ? '📄' : (ext === 'docx' ? '📝' : (ext === 'xlsx' || ext === 'xls' || ext === 'csv' ? '📊' : '📃'));
                chip.textContent = icon + ' ' + f.name;
                list.appendChild(chip);
            });
        });

        extractBtn.addEventListener('click', async () => {
            const files     = document.getElementById('doc-file').files;
            const pasteText = document.getElementById('doc-paste').value.trim();

            if (files.length === 0 && !pasteText) {
                docStatus.textContent = 'Upload at least one file or paste some text first.';
                return;
            }

            const sourceCount = files.length + (pasteText ? 1 : 0);
            extractBtn.disabled   = true;
            docResult.style.display = 'none';
            docStatus.textContent = sourceCount > 1
                ? 'Reading ' + sourceCount + ' sources and synthesising…'
                : 'Reading document…';

            try {
                const body = new FormData();
                body.append('csrf_token', csrf);
                body.append('slug',       slug);
                Array.from(files).forEach(f => body.append('documents[]', f));
                if (pasteText) body.append('paste_text', pasteText);

                const resp = await fetch('/ai-extract.php', { method: 'POST', body });
                const data = await resp.json();

                if (!resp.ok || data.error) {
                    docStatus.textContent = data.error || 'Something went wrong.';
                    return;
                }

                docPreview.value        = data.description;
                docResult.style.display = '';

                let msg = 'Extracted from ' + (data.sources || []).join(', ') + ' (' + data.model + ')';
                if (data.warning) msg += ' — ⚠ ' + data.warning;
                docStatus.textContent = msg;

            } catch (err) {
                docStatus.textContent = 'Could not reach the server.';
            } finally {
                extractBtn.disabled = false;
            }
        });

        useBtn.addEventListener('click', () => {
            const description = document.getElementById('ai-description');
            if (description) {
                description.value = docPreview.value;
                // Scroll to and open the generate panel
                const panel = description.closest('details');
                if (panel) panel.open = true;
                description.focus();
                description.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }());
    </script>

    <!-- AI Assist ─────────────────────────────────────────────── -->
    <details class="ai-assist-panel" style="margin-bottom:1rem;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
        <summary style="display:flex;align-items:center;gap:0.5rem;padding:0.65rem 1rem;
                        cursor:pointer;user-select:none;font-size:0.875rem;font-weight:600;
                        background:var(--bg-subtle,var(--bg));list-style:none;">
            <i data-lucide="sparkles" style="width:1rem;height:1rem;color:var(--accent);flex-shrink:0;"></i>
            Build diagram from description
            <span style="font-weight:400;color:var(--muted);margin-left:0.25rem;">&mdash; AI reads your text and creates the swimlanes and steps</span>
        </summary>
        <div style="padding:1rem;border-top:1px solid var(--border);">
            <?php if ($hasLanes): ?>
            <p style="margin:0 0 0.6rem;font-size:0.8125rem;color:var(--muted);">
                Describe what happens in plain language. AI will suggest steps for your existing swimlanes
                (<strong><?= h(implode(', ', array_map(fn ($l) => $l['name'], $lanes))) ?></strong>)
                and add them all in one go.
            </p>
            <?php else: ?>
            <p style="margin:0 0 0.6rem;font-size:0.8125rem;color:var(--muted);">
                Describe the whole process in plain language — who does what and in what order.
                AI will work out the swimlanes and steps and create everything at once.
                You do not need to set up swimlanes first.
            </p>
            <?php endif; ?>
            <textarea id="ai-description" rows="4"
                      style="width:100%;box-sizing:border-box;resize:vertical;font-size:0.875rem;"
                      placeholder="e.g. The tenant contacts Customer First who talks through the issue. Using the diagnostic tool the Customer First advisor creates a job, which is then sent to the scheduling system."></textarea>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.6rem;flex-wrap:wrap;">
                <button type="button" id="ai-generate-btn" class="btn btn-secondary btn-sm">Generate</button>
                <?php
                $aiLabel = '';
                if (strlen(resolve_groq_key()) > 10)   $aiLabel = 'Groq — Llama 3.3 70B';
                elseif (strlen(resolve_gemini_key()) > 10) $aiLabel = 'Gemini 2.0 Flash';
                if ($aiLabel): ?>
                    <span style="font-size:0.775rem;color:var(--muted);border:1px solid var(--border);border-radius:6px;padding:0.2rem 0.5rem;"><?= h($aiLabel) ?></span>
                <?php endif; ?>
                <span id="ai-status" style="font-size:0.8rem;color:var(--muted);"></span>
            </div>
            <div id="ai-results" style="margin-top:0.75rem;"></div>
        </div>
    </details>
    <script>
    (function () {
        const btn          = document.getElementById('ai-generate-btn');
        const status       = document.getElementById('ai-status');
        const results = document.getElementById('ai-results');
        const slug    = <?= json_encode($document['slug']) ?>;
        const csrf    = <?= json_encode(csrf_token()) ?>;

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = String(str);
            return d.innerHTML;
        }

        function renderResults(data) {
            let html = '';

            // Show suggested lanes (only when none existed before)
            if (data.lanes && data.lanes.length > 0) {
                html += '<div style="margin-bottom:0.75rem;">' +
                        '<div style="font-size:0.775rem;font-weight:600;color:var(--muted);text-transform:uppercase;' +
                        'letter-spacing:0.04em;margin-bottom:0.35rem;">Swimlanes to create</div>' +
                        '<div style="display:flex;flex-wrap:wrap;gap:0.35rem;">' +
                        data.lanes.map(l => '<span style="background:var(--bg);border:1px solid var(--border);' +
                            'border-radius:6px;padding:0.25rem 0.6rem;font-size:0.8rem;">' + esc(l) + '</span>').join('') +
                        '</div></div>';
            }

            // Show step preview cards
            html += '<div style="display:flex;flex-direction:column;gap:0.4rem;">' +
                data.steps.map((step, i) =>
                    '<div style="border:1px solid var(--border);border-radius:8px;padding:0.6rem 0.85rem;background:var(--bg);">' +
                    '<div style="display:flex;align-items:baseline;gap:0.5rem;">' +
                    '<span style="font-size:0.75rem;color:var(--muted);flex-shrink:0;">' + esc(step.step_number) + '.</span>' +
                    '<span style="font-weight:600;font-size:0.875rem;">' + esc(step.title) + '</span>' +
                    '</div>' +
                    '<div style="font-size:0.775rem;color:var(--muted);margin:0.1rem 0 0.25rem 1.35rem;">' +
                    esc(step.lane_name) + ' &middot; ' + esc(step.step_type) + ' &middot; ' + esc(step.action_type) +
                    '</div>' +
                    '<div style="font-size:0.8rem;color:var(--text,inherit);margin-left:1.35rem;">' + esc(step.description) + '</div>' +
                    '</div>'
                ).join('') +
                '</div>';

            // Create all button
            const connCount  = (data.connections || []).length;
            const laneCount2 = (data.lanes || []).length;
            let btnLabel = 'Create ';
            if (laneCount2) btnLabel += laneCount2 + ' lane' + (laneCount2 !== 1 ? 's' : '') + ' + ';
            btnLabel += data.steps.length + ' step' + (data.steps.length !== 1 ? 's' : '');
            if (connCount)  btnLabel += ' + ' + connCount + ' connection' + (connCount !== 1 ? 's' : '');

            html += '<div style="margin-top:0.85rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">' +
                    '<button type="button" id="ai-create-btn" class="btn btn-sm" ' +
                    'style="background:var(--accent);color:#fff;border-color:var(--accent);">' +
                    btnLabel +
                    '</button>' +
                    '<span id="ai-create-status" style="font-size:0.8rem;color:var(--muted);"></span>' +
                    '</div>';

            results.innerHTML = html;

            // Wire up create button
            document.getElementById('ai-create-btn').addEventListener('click', async () => {
                const createBtn    = document.getElementById('ai-create-btn');
                const createStatus = document.getElementById('ai-create-status');
                createBtn.disabled = true;
                createStatus.textContent = 'Creating…';

                try {
                    const body = new FormData();
                    body.append('csrf_token',  csrf);
                    body.append('slug',        slug);
                    body.append('lanes',       JSON.stringify(data.lanes || []));
                    body.append('steps',       JSON.stringify(data.steps));
                    body.append('connections', JSON.stringify(data.connections || []));

                    const resp = await fetch('/ai-batch-create.php', { method: 'POST', body });
                    const text = await resp.text();
                    console.log('ai-batch-create raw response:', text);

                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (parseErr) {
                        createStatus.textContent = 'Server returned unexpected output — check console for details.';
                        createBtn.disabled = false;
                        return;
                    }

                    if (!resp.ok || result.error) {
                        createStatus.textContent = result.error || 'Something went wrong (HTTP ' + resp.status + ').';
                        createBtn.disabled = false;
                        return;
                    }

                    // href-only hash changes don't reload; force a full navigation
                    const dest = new URL(result.redirect, window.location.href);
                    if (dest.pathname + dest.search === window.location.pathname + window.location.search) {
                        window.location.reload();
                    } else {
                        window.location.href = result.redirect;
                    }
                } catch (err) {
                    console.error('ai-batch-create fetch error:', err);
                    createStatus.textContent = 'Network error — check console for details.';
                    createBtn.disabled = false;
                }
            });
        }

        btn.addEventListener('click', async () => {
            const description = document.getElementById('ai-description').value.trim();
            if (!description) { status.textContent = 'Write a description first.'; return; }

            btn.disabled = true;
            status.textContent = 'Thinking… this may take 10–30 seconds';
            results.innerHTML = '';

            try {
                const body = new FormData();
                body.append('csrf_token',  csrf);
                body.append('slug',        slug);
                body.append('description', description);

                const resp = await fetch('/ai-parse.php', { method: 'POST', body });
                const data = await resp.json();

                if (!resp.ok || data.error) {
                    status.textContent = data.error || 'Something went wrong.';
                    if (data.hint === 'install') {
                        status.innerHTML += ' — <a href="/ai-settings.php">Configure AI</a>';
                    }
                    return;
                }

                const laneCount = (data.lanes || []).length;
                status.textContent = (laneCount ? laneCount + ' lane' + (laneCount !== 1 ? 's' : '') + ' + ' : '') +
                                     data.steps.length + ' step' + (data.steps.length !== 1 ? 's' : '') +
                                     ' suggested (' + (data.model || 'AI') + ')';
                renderResults(data);

            } catch (err) {
                status.textContent = err.message || 'Could not reach the AI.';
            } finally {
                btn.disabled = false;
            }
        });


    }());
    </script>

    <?php if ($lanes === []): ?>
        <div class="empty-state">
            <p class="empty-state-title">Add swimlanes first</p>
            <p class="empty-state-body" style="margin:0;">
                Steps need a home — add at least one swimlane in the section above,
                then come back here to add your first step.
            </p>
        </div>
    <?php elseif ($steps === []): ?>
        <div class="empty-state">
            <p class="empty-state-title">No steps yet</p>
            <p class="empty-state-body">
                Each step is one action in the process. Give it a title, choose which
                swimlane it belongs to, and pick an action type (phone call, data entry, decision, etc.)
                to show what kind of work it involves.
                After adding your steps, you&rsquo;ll connect them below to draw the flow.
            </p>
            <a class="btn btn-secondary btn-sm" href="/step-edit.php?slug=<?= rawurlencode($document['slug']) ?>">Add first step</a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:48px;">No.</th>
                    <th>Lane</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Action</th>
                    <th>Systems</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($steps as $step): ?>
                    <tr>
                        <td><?= (int) $step['step_number'] ?></td>
                        <td style="white-space:nowrap;"><?= h($laneNames[(int) $step['lane_id']] ?? '—') ?></td>
                        <td><?= h($step['title']) ?></td>
                        <td>
                            <span class="badge badge-<?= h($step['step_type']) ?>">
                                <?= h(step_type_label($step['step_type'])) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if (($step['action_type'] ?? 'general') !== 'general'): ?>
                                <span><?= action_type_icon($step['action_type']) ?></span>
                                <span style="font-size:0.8rem;color:var(--muted);">
                                    <?= h(action_type_options()[$step['action_type']] ?? '') ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;color:var(--muted);">
                            <?= h((string) ($step['systems'] ?? '—')) ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="row-actions">
                                <a class="btn btn-secondary btn-sm"
                                   href="/step-edit.php?slug=<?= rawurlencode($document['slug']) ?>&step_id=<?= (int) $step['id'] ?>">Edit</a>
                                <form method="post" action="/step-clone.php" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="slug"    value="<?= h($document['slug']) ?>">
                                    <input type="hidden" name="step_id" value="<?= (int) $step['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"
                                            title="Duplicate this step">Clone</button>
                                </form>
                                <a class="lnk-danger"
                                   href="/step-delete.php?slug=<?= rawurlencode($document['slug']) ?>&step_id=<?= (int) $step['id'] ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php if ($steps !== [] && !$hasConnections): ?>
        <div style="padding:0.7rem 1.25rem;background:oklch(97% 0.01 220);border-top:1px solid var(--border);
                    display:flex;align-items:center;gap:0.6rem;font-size:0.85rem;">
            <i data-lucide="arrow-down-circle" style="width:1rem;height:1rem;color:var(--accent);flex-shrink:0;"></i>
            <span>Steps added — now <a href="#connections" style="color:var(--accent);font-weight:600;">add connections</a> to turn them into a flow diagram.</span>
        </div>
    <?php endif; ?>

    <?php if ($hasSteps): ?>
    <!-- AI Refine ──────────────────────────────────────────────── -->
    <details style="margin-top:1rem;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
        <summary style="display:flex;align-items:center;gap:0.5rem;padding:0.65rem 1rem;
                        cursor:pointer;user-select:none;font-size:0.875rem;font-weight:600;
                        background:var(--bg-subtle,var(--bg));list-style:none;">
            <i data-lucide="wand-2" style="width:1rem;height:1rem;color:var(--accent);flex-shrink:0;"></i>
            Refine diagram with AI
            <span style="font-weight:400;color:var(--muted);margin-left:0.25rem;">&mdash; describe a change and AI will apply it</span>
        </summary>
        <div style="padding:1rem;border-top:1px solid var(--border);">
            <p style="margin:0 0 0.6rem;font-size:0.8125rem;color:var(--muted);">
                Describe what you want to add, change, or insert. For example:
                <em>"Add a decision point after step 3 — if specialist work is needed, escalate to a contractor."</em>
            </p>
            <textarea id="ai-instruction" rows="3"
                      style="width:100%;box-sizing:border-box;resize:vertical;font-size:0.875rem;"
                      placeholder="e.g. Add a decision after the diagnostic step — if the repair is specialist, route to a contractor lane."></textarea>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.6rem;flex-wrap:wrap;">
                <button type="button" id="ai-refine-btn" class="btn btn-secondary btn-sm">Suggest changes</button>
                <span id="ai-refine-status" style="font-size:0.8rem;color:var(--muted);"></span>
            </div>
            <div id="ai-refine-results" style="margin-top:0.75rem;"></div>
        </div>
    </details>
    <script>
    (function () {
        // Wire mic button using the shared attachMic defined in the build panel script
const btn     = document.getElementById('ai-refine-btn');
        const status  = document.getElementById('ai-refine-status');
        const results = document.getElementById('ai-refine-results');
        const slug    = <?= json_encode($document['slug']) ?>;
        const csrf    = <?= json_encode(csrf_token()) ?>;

        function esc(str) { const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML; }

        btn.addEventListener('click', async () => {
            const instruction = document.getElementById('ai-instruction').value.trim();
            if (!instruction) { status.textContent = 'Describe the change first.'; return; }

            btn.disabled = true;
            status.textContent = 'Thinking… this may take 10–30 seconds';
            results.innerHTML = '';

            try {
                const body = new FormData();
                body.append('csrf_token',  csrf);
                body.append('slug',        slug);
                body.append('instruction', instruction);

                const resp = await fetch('/ai-refine.php', { method: 'POST', body });
                const data = await resp.json();

                if (!resp.ok || data.error) {
                    status.textContent = data.error || 'Something went wrong.';
                    return;
                }

                const addL = (data.add_lanes        || []).length;
                const addS = (data.add_steps        || []).length;
                const addC = (data.add_connections  || []).length;
                const remC = (data.remove_connections || []).length;

                if (!addL && !addS && !addC && !remC) {
                    status.textContent = 'AI suggested no changes — try rephrasing.';
                    return;
                }

                status.textContent = [
                    addL ? addL + ' lane' + (addL !== 1 ? 's' : '') : '',
                    addS ? addS + ' step' + (addS !== 1 ? 's' : '') : '',
                    addC ? addC + ' connection' + (addC !== 1 ? 's' : '') + ' added' : '',
                    remC ? remC + ' connection' + (remC !== 1 ? 's' : '') + ' removed' : '',
                ].filter(Boolean).join(' + ') + ' suggested (' + data.model + ')';

                let html = '';

                if (addL) html += '<div style="margin-bottom:0.5rem;font-size:0.8rem;"><strong>New lanes:</strong> ' + data.add_lanes.map(esc).join(', ') + '</div>';

                if (addS) html += '<div style="margin-bottom:0.35rem;font-size:0.775rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;">Steps to add</div>' +
                    '<div style="display:flex;flex-direction:column;gap:0.35rem;margin-bottom:0.5rem;">' +
                    data.add_steps.map(s =>
                        '<div style="border:1px solid var(--border);border-radius:8px;padding:0.55rem 0.8rem;background:var(--bg);">' +
                        '<span style="font-weight:600;font-size:0.875rem;">' + esc(s.title) + '</span> ' +
                        '<span style="font-size:0.775rem;color:var(--muted);">' + esc(s.lane_name) + ' · ' + esc(s.step_type) + ' · ' + esc(s.action_type) + '</span>' +
                        '<div style="font-size:0.8rem;margin-top:0.15rem;">' + esc(s.description) + '</div></div>'
                    ).join('') + '</div>';

                if (addC) html += '<div style="font-size:0.8rem;margin-bottom:0.35rem;"><strong>Connections to add:</strong> ' +
                    data.add_connections.map(c => esc(c.from) + '→' + esc(c.to) + (c.label ? ' <em>(' + esc(c.label) + ')</em>' : '')).join(', ') + '</div>';

                if (remC) html += '<div style="font-size:0.8rem;margin-bottom:0.5rem;"><strong>Connections to remove:</strong> ' +
                    data.remove_connections.map(c => esc(c.from) + '→' + esc(c.to)).join(', ') + '</div>';

                const applyLabel = [
                    addL ? 'add ' + addL + ' lane' + (addL !== 1 ? 's' : '') : '',
                    addS ? 'add ' + addS + ' step' + (addS !== 1 ? 's' : '') : '',
                    addC ? 'wire ' + addC + ' connection' + (addC !== 1 ? 's' : '') : '',
                    remC ? 'remove ' + remC + ' connection' + (remC !== 1 ? 's' : '') : '',
                ].filter(Boolean).join(', ');

                html += '<div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem;">' +
                    '<button type="button" id="ai-refine-apply-btn" class="btn btn-sm" ' +
                    'style="background:var(--accent);color:#fff;border-color:var(--accent);">Apply — ' + applyLabel + '</button>' +
                    '<span id="ai-refine-apply-status" style="font-size:0.8rem;color:var(--muted);"></span></div>';

                results.innerHTML = html;

                document.getElementById('ai-refine-apply-btn').addEventListener('click', async () => {
                    const applyBtn = document.getElementById('ai-refine-apply-btn');
                    const applyStatus = document.getElementById('ai-refine-apply-status');
                    applyBtn.disabled = true;
                    applyStatus.textContent = 'Applying…';

                    try {
                        const b = new FormData();
                        b.append('csrf_token',         csrf);
                        b.append('slug',               slug);
                        b.append('add_lanes',          JSON.stringify(data.add_lanes          || []));
                        b.append('add_steps',          JSON.stringify(data.add_steps          || []));
                        b.append('add_connections',    JSON.stringify(data.add_connections    || []));
                        b.append('remove_connections', JSON.stringify(data.remove_connections || []));

                        const r    = await fetch('/ai-refine-apply.php', { method: 'POST', body: b });
                        const text = await r.text();
                        let res;
                        try { res = JSON.parse(text); } catch { applyStatus.textContent = 'Unexpected server response.'; applyBtn.disabled = false; return; }

                        if (!r.ok || res.error) { applyStatus.textContent = res.error || 'Error (HTTP ' + r.status + ').'; applyBtn.disabled = false; return; }

                        const dest = new URL(res.redirect, window.location.href);
                        if (dest.pathname + dest.search === window.location.pathname + window.location.search) {
                            window.location.reload();
                        } else {
                            window.location.href = res.redirect;
                        }
                    } catch (err) {
                        applyStatus.textContent = 'Network error.';
                        applyBtn.disabled = false;
                    }
                });

            } catch (err) {
                status.textContent = 'Could not reach the server.';
            } finally {
                btn.disabled = false;
            }
        });
    }());
    </script>
    <?php endif; ?>

    <?php endif; ?><?php // closes: if ($lanes===[]): ... elseif ... else: ?>
</div>

<!-- ── Connections ───────────────────────────────────────────────── -->
<div class="card" id="connections">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.85rem;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 0.2rem;">Connections</h2>
            <p style="margin:0;font-size:0.875rem;">The arrows that turn your steps into a flow diagram.</p>
        </div>
        <?php if ($connections !== [] && $hasSteps): ?>
            <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">View diagram</a>
        <?php endif; ?>
    </div>

    <?php if ($steps === []): ?>
        <div class="empty-state">
            <p class="empty-state-title">Add steps first</p>
            <p class="empty-state-body" style="margin:0;">
                Once you have steps above, come back here to draw the arrows between them.
                Connections are what turns your list of steps into a real process map.
            </p>
        </div>
    <?php else: ?>
        <?php if ($connections !== []): ?>
            <table style="margin-bottom:1.25rem;">
                <thead>
                    <tr>
                        <th>From</th>
                        <th></th>
                        <th>Label</th>
                        <th></th>
                        <th>To</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $c): ?>
                        <tr>
                            <td>
                                <strong><?= (int) $c['from_number'] ?>.</strong>
                                <?= h($c['from_title']) ?>
                            </td>
                            <td style="color:var(--muted);padding:0.5rem;"><i data-lucide="arrow-right" style="width:1rem;height:1rem;"></i></td>
                            <td style="color:var(--muted);font-size:0.875rem;">
                                <?= $c['label'] !== null ? h($c['label']) : '<em>—</em>' ?>
                            </td>
                            <td style="color:var(--muted);padding:0.5rem;"><i data-lucide="arrow-right" style="width:1rem;height:1rem;"></i></td>
                            <td>
                                <strong><?= (int) $c['to_number'] ?>.</strong>
                                <?= h($c['to_title']) ?>
                            </td>
                            <td style="vertical-align:middle;">
                                <div class="row-actions">
                                    <a class="lnk-danger"
                                       href="/connection-delete.php?slug=<?= rawurlencode($document['slug']) ?>&connection_id=<?= (int) $c['id'] ?>">Remove</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p class="empty-state-title">No connections yet — this is the important step</p>
                <p class="empty-state-body">
                    Connections are the arrows that make the diagram meaningful.
                    Without them, steps appear as isolated nodes with no visible flow.
                    Select a starting step and an ending step below. Add a label for
                    decision branches (for example: <em>Yes</em>, <em>No</em>, <em>New repair</em>).
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="/connection-create.php" style="padding-top:1rem;border-top:1px solid var(--border);">
            <?= csrf_field() ?>
            <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
            <div class="connection-row" style="margin-bottom:0.75rem;">
                <div>
                    <label for="from_step_id">From step</label>
                    <select id="from_step_id" name="from_step_id" required>
                        <?php foreach ($steps as $s): ?>
                            <option value="<?= (int) $s['id'] ?>">
                                <?= (int) $s['step_number'] ?>. <?= h($s['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="arrow-label"><i data-lucide="arrow-right" style="width:0.9rem;height:0.9rem;"></i></div>
                <div>
                    <label for="to_step_id">To step</label>
                    <select id="to_step_id" name="to_step_id" required>
                        <?php foreach ($steps as $s): ?>
                            <option value="<?= (int) $s['id'] ?>">
                                <?= (int) $s['step_number'] ?>. <?= h($s['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="conn_label">Label <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
                    <input type="text" id="conn_label" name="label" placeholder="Yes / No / etc.">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button class="btn btn-secondary" type="submit">Add connection</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- ── Danger zone ───────────────────────────────────────────────── -->
<div class="card danger-zone">
    <h2>Delete AS-IS</h2>
    <p>Permanently removes this document, all lanes, steps, systems, and connections.</p>
    <a class="btn btn-danger" href="/delete.php?slug=<?= rawurlencode($document['slug']) ?>">Delete document</a>
</div>
<?php
render_layout('Edit ' . $document['title'], ob_get_clean() ?: '');
