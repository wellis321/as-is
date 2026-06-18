<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$pdo = db();
$document = resolve_document_request($pdo);
$connectionId = isset($_GET['connection_id']) ? (int) $_GET['connection_id'] : (int) ($_POST['connection_id'] ?? 0);
$error = null;

if ($document === null || $connectionId < 1) {
    redirect('/documents.php');
}

$stmt = $pdo->prepare(
    'SELECT c.*, fs.step_number AS from_number, fs.title AS from_title,
            ts.step_number AS to_number, ts.title AS to_title
     FROM step_connections c
     INNER JOIN steps fs ON fs.id = c.from_step_id
     INNER JOIN steps ts ON ts.id = c.to_step_id
     WHERE c.id = ? AND fs.as_is_id = ?'
);
$stmt->execute([$connectionId, (int) $document['id']]);
$connection = $stmt->fetch();

if ($connection === false) {
    redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#connections');
}

$confirmPhrase = (int) $connection['from_number'] . ' to ' . (int) $connection['to_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typed = trim((string) ($_POST['confirm_phrase'] ?? ''));

    if ($typed !== $confirmPhrase) {
        $error = 'The confirmation text did not match. Nothing was removed.';
    } else {
        try {
            delete_connection($pdo, $connectionId);
            redirect('/edit.php?slug=' . rawurlencode($document['slug']) . '#connections');
        } catch (Throwable $e) {
            $error = 'Could not remove this connection. Please try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Remove connection</h1>
        <p>This cannot be undone.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#connections">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to remove the connection:</p>
    <p>
        <strong><?= (int) $connection['from_number'] ?>. <?= h($connection['from_title']) ?></strong>
        &#8594;
        <?php if (!empty($connection['label'])): ?>
            <em><?= h($connection['label']) ?></em> &#8594;
        <?php endif; ?>
        <strong><?= (int) $connection['to_number'] ?>. <?= h($connection['to_title']) ?></strong>
    </p>
    <p>Type <strong><?= h($confirmPhrase) ?></strong> below to confirm.</p>

    <form method="post" class="form-grid">
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <input type="hidden" name="connection_id" value="<?= (int) $connection['id'] ?>">
        <div>
            <label for="confirm_phrase">Confirm</label>
            <input type="text" id="confirm_phrase" name="confirm_phrase" placeholder="<?= h($confirmPhrase) ?>" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit">Remove permanently</button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>#connections">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Remove connection', ob_get_clean() ?: '');
