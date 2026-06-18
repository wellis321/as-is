<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

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
        <div class="build-num"><?= $stageClass(1) === 'is-done' ? '✓' : '1' ?></div>
        <div class="build-info">
            <div class="build-name">Details</div>
            <div class="build-status"><?= $stageClass(1) === 'is-current' ? 'Fill in title &amp; metadata' : 'Complete' ?></div>
        </div>
    </a>
    <a href="#lanes"       class="build-stage <?= $stageClass(2) ?>">
        <div class="build-num"><?= $stageClass(2) === 'is-done' ? '✓' : '2' ?></div>
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
        <div class="build-num"><?= $stageClass(3) === 'is-done' ? '✓' : '3' ?></div>
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
        <div class="build-num"><?= $stageClass(4) === 'is-done' ? '✓' : '4' ?></div>
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
                                    <input type="hidden" name="slug"      value="<?= h($document['slug']) ?>">
                                    <input type="hidden" name="lane_id"   value="<?= (int) $lane['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-link btn-sm" type="submit" title="Move up">&#8593;</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($i < count($lanes) - 1): ?>
                                <form class="inline-form" method="post" action="/lane-reorder.php">
                                    <input type="hidden" name="slug"      value="<?= h($document['slug']) ?>">
                                    <input type="hidden" name="lane_id"   value="<?= (int) $lane['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-link btn-sm" type="submit" title="Move down">&#8595;</button>
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
                                <a class="lnk-danger"
                                   href="/step-delete.php?slug=<?= rawurlencode($document['slug']) ?>&step_id=<?= (int) $step['id'] ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
                            <td style="color:var(--muted);font-size:1.1rem;padding:0.5rem;">&#8594;</td>
                            <td style="color:var(--muted);font-size:0.875rem;">
                                <?= $c['label'] !== null ? h($c['label']) : '<em>—</em>' ?>
                            </td>
                            <td style="color:var(--muted);font-size:1.1rem;padding:0.5rem;">&#8594;</td>
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
                <div class="arrow-label">&#8594;</div>
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
