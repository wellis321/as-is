<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('admin');

$pdo = db();
$document = resolve_document_request($pdo);
$stepId = isset($_GET['step_id']) ? (int) $_GET['step_id'] : (int) ($_POST['step_id'] ?? 0);
$error = null;

if ($document === null || $stepId < 1) {
    redirect('/documents.php');
}

$step = fetch_step($pdo, $stepId);
if ($step === null || (int) $step['as_is_id'] !== (int) $document['id']) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#steps');
}

$connectionCount = $pdo->prepare(
    'SELECT COUNT(*) FROM step_connections WHERE from_step_id = ? OR to_step_id = ?'
);
$connectionCount->execute([$stepId, $stepId]);
$connections = (int) $connectionCount->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $confirmTitle = trim((string) ($_POST['confirm_title'] ?? ''));

    if (strcasecmp($confirmTitle, $step['title']) !== 0) {
        $error = 'The title did not match. Nothing was deleted.';
    } else {
        try {
            delete_step($pdo, $stepId);
            redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#steps');
        } catch (Throwable $e) {
            $error = 'Could not delete this step. Please try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Delete step</h1>
        <p>This cannot be undone.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#steps">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to delete step <strong><?= (int) $step['step_number'] ?>. <?= h($step['title']) ?></strong>.</p>
    <?php if ($connections > 0): ?>
        <p>This will also remove <strong><?= $connections ?></strong> connection<?= $connections === 1 ? '' : 's' ?> linked to this step.</p>
    <?php endif; ?>
    <p>Type the step title <strong><?= h($step['title']) ?></strong> below to confirm.</p>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <input type="hidden" name="step_id" value="<?= (int) $step['id'] ?>">
        <div>
            <label for="confirm_title">Confirm title</label>
            <input type="text" id="confirm_title" name="confirm_title" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit">Delete permanently</button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#steps">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Delete step', ob_get_clean() ?: '');
