<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo      = db();
$document = resolve_document_request($pdo);
$laneId   = isset($_GET['lane_id']) ? (int) $_GET['lane_id'] : (int) ($_POST['lane_id'] ?? 0);
$error    = null;

if ($document === null || $laneId < 1) {
    redirect('/documents.php');
}

if (!can_edit_document($document)) {
    redirect('/view.php?slug=' . rawurlencode($document['slug']));
}

$allLanes = fetch_lanes($pdo, (int) $document['id']);
$lane     = null;
foreach ($allLanes as $row) {
    if ((int) $row['id'] === $laneId) { $lane = $row; break; }
}

if ($lane === null) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
}

$otherLanes = array_values(array_filter($allLanes, fn($l) => (int) $l['id'] !== $laneId));

$sc = $pdo->prepare('SELECT COUNT(*) FROM steps WHERE lane_id = ?');
$sc->execute([$laneId]);
$stepCount = (int) $sc->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }

    $confirmName  = trim((string) ($_POST['confirm_name'] ?? ''));
    $stepHandling = ($_POST['step_handling'] ?? 'delete');
    $moveTo       = (int) ($_POST['move_to_lane'] ?? 0);

    if (strcasecmp($confirmName, $lane['name']) !== 0) {
        $error = 'The name did not match. Nothing was deleted.';
    } elseif ($stepHandling === 'move' && $stepCount > 0) {
        // Validate the target lane belongs to the same document
        $validTarget = false;
        foreach ($otherLanes as $ol) {
            if ((int) $ol['id'] === $moveTo) { $validTarget = true; break; }
        }
        if (!$validTarget) {
            $error = 'Please choose a valid lane to move the steps into.';
        } else {
            try {
                $pdo->beginTransaction();
                move_steps_to_lane($pdo, $laneId, $moveTo);
                delete_lane($pdo, $laneId);
                $pdo->commit();
                redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not complete the operation. Please try again.';
            }
        }
    } else {
        // Delete steps along with the lane (cascade via FK or explicit delete)
        try {
            $pdo->beginTransaction();
            // Remove connections involving these steps first
            $pdo->prepare(
                'DELETE FROM step_connections
                 WHERE from_step_id IN (SELECT id FROM steps WHERE lane_id = ?)
                    OR to_step_id   IN (SELECT id FROM steps WHERE lane_id = ?)'
            )->execute([$laneId, $laneId]);
            $pdo->prepare('DELETE FROM step_systems WHERE step_id IN (SELECT id FROM steps WHERE lane_id = ?)')->execute([$laneId]);
            $pdo->prepare('DELETE FROM steps WHERE lane_id = ?')->execute([$laneId]);
            delete_lane($pdo, $laneId);
            $pdo->commit();
            redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Could not delete this swimlane. Please try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Delete swimlane</h1>
        <p><?= h($lane['name']) ?><?= $stepCount > 0 ? ' · ' . $stepCount . ' step' . ($stepCount === 1 ? '' : 's') : '' ?></p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#lanes">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to delete the swimlane <strong><?= h($lane['name']) ?></strong>.</p>

    <?php if ($stepCount > 0): ?>
    <p style="margin-bottom:1.1rem;">
        This lane contains <strong><?= $stepCount ?> step<?= $stepCount === 1 ? '' : 's' ?></strong>.
        Choose what to do with them:
    </p>

    <div style="display:grid;gap:0.5rem;margin-bottom:1.25rem;" id="step-handling-group">

        <?php if ($otherLanes): ?>
        <label style="display:flex;align-items:flex-start;gap:0.6rem;padding:0.75rem 0.85rem;
                       border:1px solid var(--border);border-radius:var(--r);cursor:pointer;"
               id="opt-move-label">
            <input type="radio" name="step_handling_ui" value="move" id="opt-move"
                   style="margin-top:0.15rem;flex-shrink:0;" checked>
            <div>
                <strong style="font-size:0.9rem;">Move steps to another lane</strong>
                <p style="margin:0.2rem 0 0;font-size:0.82rem;color:var(--muted);">
                    Keep all steps — just move them into a different swimlane.
                </p>
                <div style="margin-top:0.6rem;" id="move-target-wrap">
                    <label for="move_to_lane_sel" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.25rem;">
                        Move steps into:
                    </label>
                    <select id="move_to_lane_sel" style="width:auto;">
                        <?php foreach ($otherLanes as $ol): ?>
                            <option value="<?= (int) $ol['id'] ?>"><?= h($ol['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </label>
        <?php endif; ?>

        <label style="display:flex;align-items:flex-start;gap:0.6rem;padding:0.75rem 0.85rem;
                       border:1px solid var(--border);border-radius:var(--r);cursor:pointer;">
            <input type="radio" name="step_handling_ui" value="delete" id="opt-delete"
                   style="margin-top:0.15rem;flex-shrink:0;" <?= $otherLanes ? '' : 'checked' ?>>
            <div>
                <strong style="font-size:0.9rem;">Delete steps permanently</strong>
                <p style="margin:0.2rem 0 0;font-size:0.82rem;color:var(--muted);">
                    Remove all <?= $stepCount ?> step<?= $stepCount === 1 ? '' : 's' ?> and their connections. This cannot be undone.
                </p>
            </div>
        </label>

    </div>
    <?php endif; ?>

    <p style="margin-bottom:0.4rem;">Type <strong><?= h($lane['name']) ?></strong> to confirm:</p>

    <form method="post" class="form-grid" id="delete-form">
        <?= csrf_field() ?>
        <input type="hidden" name="slug"          value="<?= h($document['slug']) ?>">
        <input type="hidden" name="lane_id"        value="<?= (int) $lane['id'] ?>">
        <input type="hidden" name="step_handling"  value="<?= $otherLanes ? 'move' : 'delete' ?>" id="step-handling-hidden">
        <input type="hidden" name="move_to_lane"   value="<?= $otherLanes ? (int) $otherLanes[0]['id'] : 0 ?>" id="move-to-hidden">

        <div>
            <label for="confirm_name">Confirm name</label>
            <input type="text" id="confirm_name" name="confirm_name" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit" id="submit-btn">Delete lane</button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#lanes">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    const radios    = document.querySelectorAll('input[name="step_handling_ui"]');
    const hidden    = document.getElementById('step-handling-hidden');
    const moveHid   = document.getElementById('move-to-hidden');
    const moveSel   = document.getElementById('move_to_lane_sel');
    const submitBtn = document.getElementById('submit-btn');

    function sync() {
        const val = document.querySelector('input[name="step_handling_ui"]:checked')?.value ?? 'delete';
        if (hidden)  hidden.value  = val;
        if (moveHid) moveHid.value = (val === 'move' && moveSel) ? moveSel.value : '0';
        if (submitBtn) submitBtn.textContent = val === 'move' ? 'Move steps & delete lane' : 'Delete lane & steps';
    }

    radios.forEach(r => r.addEventListener('change', sync));
    if (moveSel) moveSel.addEventListener('change', () => { if (moveHid) moveHid.value = moveSel.value; });

    sync();
})();
</script>
<?php
render_layout('Delete swimlane', ob_get_clean() ?: '');
