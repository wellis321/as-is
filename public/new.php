<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo = db();
ensure_schema($pdo);

$error       = null;
$title       = '';
$description = '';
$status      = 'draft';
$owner       = '';
$department  = '';
$capturedDate = '';
$version     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $title        = trim((string) ($_POST['title']        ?? ''));
    $description  = trim((string) ($_POST['description']  ?? ''));
    $status       = valid_status((string) ($_POST['status'] ?? 'draft'));
    $owner        = trim((string) ($_POST['owner']        ?? ''));
    $department   = trim((string) ($_POST['department']   ?? ''));
    $capturedDate = trim((string) ($_POST['captured_date'] ?? ''));
    $version      = trim((string) ($_POST['version']      ?? ''));

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        try {
            $document = create_document($pdo, $title, $description, $status,
                                        $owner, $department, $capturedDate, $version);
            redirect('/edit.php?slug=' . rawurlencode($document['slug']));
        } catch (Throwable $e) {
            $error = 'Could not create this map. Please check your details and try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>New AS-IS</h1>
        <p>Create a new process map.</p>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= h($title) ?>" required placeholder="e.g. Customer First — Repairs Intake">
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Brief summary of what this process covers"><?= h($description) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="owner">Owner</label>
                <input type="text" id="owner" name="owner" value="<?= h($owner) ?>" placeholder="Name or team">
            </div>
            <div>
                <label for="department">Department</label>
                <input type="text" id="department" name="department" value="<?= h($department) ?>" placeholder="e.g. Housing">
            </div>
            <div>
                <label for="captured_date">Date captured</label>
                <input type="date" id="captured_date" name="captured_date" value="<?= h($capturedDate) ?>">
            </div>
            <div>
                <label for="version">Version</label>
                <input type="text" id="version" name="version" value="<?= h($version) ?>" placeholder="e.g. v1.0">
            </div>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status" style="width:auto;">
                <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Create process map</button>
        </div>
    </form>
</div>
<?php
render_layout('New AS-IS', ob_get_clean() ?: '');
