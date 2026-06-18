<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('admin');

$pdo = db();
$document = resolve_document_request($pdo);

$error = null;

if ($document === null) {
    redirect('/documents.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmTitle = trim((string) ($_POST['confirm_title'] ?? ''));

    if (strcasecmp($confirmTitle, $document['title']) !== 0) {
        $error = 'The title did not match. Nothing was deleted.';
    } else {
        try {
            delete_document($pdo, (int) $document['id']);
            redirect('/documents.php');
        } catch (Throwable $e) {
            $error = 'Could not delete this map. Please try again.';
        }
    }
}

$error = $error ?? null;

ob_start();
?>
<header>
    <div>
        <h1>Delete AS-IS</h1>
        <p>This cannot be undone.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to delete <strong><?= h($document['title']) ?></strong> and all of its lanes, steps, systems, and connections.</p>
    <p>Type the document title <strong><?= h($document['title']) ?></strong> below to confirm.</p>

    <form method="post" class="form-grid">
        <input type="hidden" name="slug" value="<?= h($document['slug']) ?>">
        <div>
            <label for="confirm_title">Confirm title</label>
            <input type="text" id="confirm_title" name="confirm_title" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit">Delete permanently</button>
            <a class="btn btn-secondary" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Delete ' . $document['title'], ob_get_clean() ?: '');
