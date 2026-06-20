<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('admin');

$pdo = db();
$document = resolve_document_request($pdo);
$laneId = isset($_GET['lane_id']) ? (int) $_GET['lane_id'] : (int) ($_POST['lane_id'] ?? 0);
$error = null;

if ($document === null || $laneId < 1) {
    redirect('/documents.php');
}

$lane = null;
foreach (fetch_lanes($pdo, (int) $document['id']) as $row) {
    if ((int) $row['id'] === $laneId) {
        $lane = $row;
        break;
    }
}

if ($lane === null) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
}

$stepCount = $pdo->prepare('SELECT COUNT(*) FROM steps WHERE lane_id = ?');
$stepCount->execute([$laneId]);
$steps = (int) $stepCount->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $confirmName = trim((string) ($_POST['confirm_name'] ?? ''));

    if (strcasecmp($confirmName, $lane['name']) !== 0) {
        $error = 'The name did not match. Nothing was deleted.';
    } else {
        try {
            delete_lane($pdo, $laneId);
            redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#lanes');
        } catch (Throwable $e) {
            $error = 'Could not delete this swimlane. Please try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Delete swimlane</h1>
        <p>This cannot be undone.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#lanes">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to delete the swimlane <strong><?= h($lane['name']) ?></strong>.</p>
    <?php if ($steps > 0): ?>
        <p>This will also delete <strong><?= $steps ?></strong> step<?= $steps === 1 ? '' : 's' ?> in this lane and any connections involving them.</p>
    <?php endif; ?>
    <p>Type the swimlane name <strong><?= h($lane['name']) ?></strong> below to confirm.</p>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <input type="hidden" name="lane_id" value="<?= (int) $lane['id'] ?>">
        <div>
            <label for="confirm_name">Confirm name</label>
            <input type="text" id="confirm_name" name="confirm_name" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit">Delete permanently</button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#lanes">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Delete swimlane', ob_get_clean() ?: '');
