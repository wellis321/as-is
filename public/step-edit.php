<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo = db();
ensure_schema($pdo);

$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/documents.php');
}

$asIsId  = (int) $document['id'];
$lanes   = fetch_lanes($pdo, $asIsId);
$systems = fetch_systems($pdo);

$stepId = isset($_GET['step_id']) ? (int) $_GET['step_id'] : (int) ($_POST['step_id'] ?? 0);
$step   = $stepId > 0 ? fetch_step($pdo, $stepId) : null;
$isEdit = $step !== null && (int) $step['as_is_id'] === $asIsId;

$error      = null;
$stepNumber = $isEdit ? (int) $step['step_number'] : 1;
$title      = $isEdit ? $step['title'] : '';
$description = $isEdit ? (string) ($step['description'] ?? '') : '';
$stepType   = $isEdit ? $step['step_type'] : 'task';
$actionType = $isEdit ? ($step['action_type'] ?? 'general') : 'general';
$laneId     = $isEdit ? (int) $step['lane_id'] : (int) ($lanes[0]['id'] ?? 0);
$selectedSystemIds = $isEdit ? fetch_step_system_ids($pdo, $stepId) : [];

if ($lanes === []) {
    ob_start();
    ?>
    <header>
        <div><h1>No swimlanes</h1><p>Add lanes before creating steps.</p></div>
        <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Back</a>
    </header>
    <?php
    render_layout('No swimlanes', ob_get_clean() ?: '');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $stepId      = (int) ($_POST['step_id']     ?? 0);
    $step        = $stepId > 0 ? fetch_step($pdo, $stepId) : null;
    $isEdit      = $step !== null && (int) $step['as_is_id'] === $asIsId;

    $laneId      = (int) ($_POST['lane_id']     ?? 0);
    $stepNumber  = (int) ($_POST['step_number'] ?? 0);
    $title       = trim((string) ($_POST['title']       ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $stepType    = valid_step_type((string) ($_POST['step_type']   ?? 'task'));
    $actionType  = valid_action_type((string) ($_POST['action_type'] ?? 'general'));
    $selectedSystemIds = array_map('intval', (array) ($_POST['system_ids'] ?? []));

    if ($title === '') {
        $error = 'Title is required.';
    } elseif ($stepNumber < 1) {
        $error = 'Step number must be at least 1.';
    } elseif (!in_array($laneId, array_map(static fn ($l) => (int) $l['id'], $lanes), true)) {
        $error = 'Choose a valid swimlane.';
    } else {
        try {
            if ($isEdit) {
                update_step($pdo, $stepId, $laneId, $stepNumber, $title, $description, $stepType, $actionType);
                sync_step_systems($pdo, $stepId, $selectedSystemIds);
            } else {
                $newId = create_step($pdo, $asIsId, $laneId, $stepNumber, $title, $description, $stepType, $actionType);
                sync_step_systems($pdo, $newId, $selectedSystemIds);
            }
            redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#steps');
        } catch (Throwable $e) {
            $error = 'Could not save this step. Please try again.';
        }
    }
}

// Build list of used step numbers for hint (excluding the current step if editing).
$usedNumbers = array_map(
    static fn ($s) => (int) $s['step_number'],
    fetch_steps($pdo, $asIsId)
);
if ($isEdit) {
    $usedNumbers = array_filter($usedNumbers, static fn ($n) => $n !== (int) ($step['step_number'] ?? 0));
}
sort($usedNumbers);

ob_start();
?>
<header>
    <div>
        <h1><?= $isEdit ? 'Edit step' : 'Add step' ?></h1>
        <p><?= h($document['title']) ?></p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Back</a>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="step_id" value="<?= (int) $step['id'] ?>">
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:120px 1fr;gap:1rem;">
            <div>
                <label for="step_number">Step number</label>
                <input type="number" id="step_number" name="step_number" min="1" value="<?= (int) $stepNumber ?>" required>
                <?php if ($usedNumbers !== []): ?>
                    <p class="field-help">Used: <?= implode(', ', $usedNumbers) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="lane_id">Swimlane</label>
                <select id="lane_id" name="lane_id" required>
                    <?php foreach ($lanes as $lane): ?>
                        <option value="<?= (int) $lane['id'] ?>" <?= $laneId === (int) $lane['id'] ? 'selected' : '' ?>>
                            <?= h($lane['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= h($title) ?>" required>
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= h($description) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div>
                <label for="step_type">Step type</label>
                <select id="step_type" name="step_type">
                    <option value="start"      <?= $stepType === 'start'      ? 'selected' : '' ?>>Start</option>
                    <option value="task"       <?= $stepType === 'task'       ? 'selected' : '' ?>>Task</option>
                    <option value="decision"   <?= $stepType === 'decision'   ? 'selected' : '' ?>>Decision</option>
                    <option value="subprocess" <?= $stepType === 'subprocess' ? 'selected' : '' ?>>Subprocess</option>
                    <option value="parallel"   <?= $stepType === 'parallel'   ? 'selected' : '' ?>>Parallel gateway</option>
                    <option value="end"        <?= $stepType === 'end'        ? 'selected' : '' ?>>End</option>
                </select>
                <p class="field-help">
                    <em>Start</em> / <em>End</em> mark where the process begins and ends.
                    <em>Decision</em> for Yes/No branches.
                    <em>Subprocess</em> for a step that expands into its own detailed process map.
                    <em>Parallel gateway</em> where multiple things happen simultaneously (not a choice — all paths run).
                    <em>Task</em> for everything else.
                </p>
            </div>
            <div>
                <label for="action_type">Action type</label>
                <select id="action_type" name="action_type">
                    <?php foreach (action_type_options() as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= $actionType === $val ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">What kind of action is this? Colour-codes steps in the diagram so you can see at a glance whether a process is phone-heavy, data-entry-heavy, and so on.</p>
            </div>
        </div>

        <?php if ($systems !== []): ?>
            <div>
                <label>Systems used at this step</label>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.25rem;">
                    <?php foreach ($systems as $sys): ?>
                        <label style="font-weight:normal;display:flex;align-items:center;gap:0.35rem;
                                      background:var(--bg);border:1px solid var(--border);border-radius:8px;
                                      padding:0.4rem 0.65rem;cursor:pointer;">
                            <input type="checkbox" name="system_ids[]" value="<?= (int) $sys['id'] ?>"
                                <?= in_array((int) $sys['id'], array_map('intval', $selectedSystemIds), true) ? 'checked' : '' ?>>
                            <?= h($sys['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-help">Don't see the system you need? <a href="/systems.php">Add it to the library</a> first.</p>
            </div>
        <?php else: ?>
            <p class="field-help">
                The systems library is empty.
                <a href="/systems.php">Add systems</a> first, then come back to assign them to this step.
            </p>
        <?php endif; ?>

        <div class="actions">
            <button class="btn" type="submit"><?= $isEdit ? 'Save step' : 'Add step' ?></button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout(($isEdit ? 'Edit step' : 'Add step'), ob_get_clean() ?: '');
