<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$pdo      = db();
ensure_schema($pdo);

$systemId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$system   = $systemId > 0 ? fetch_system($pdo, $systemId) : null;

if ($system === null) {
    redirect('/systems.php');
}

$error   = null;
$success = null;

// Populate fields from the current record.
$name        = $system['name'];
$description = (string) ($system['description'] ?? '');
$category    = (string) ($system['category']    ?? '');
$hosting     = (string) ($system['hosting']     ?? 'unknown');
$vendor      = (string) ($system['vendor']      ?? '');
$owner       = (string) ($system['owner']       ?? '');
$contact     = (string) ($system['contact']     ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim((string) ($_POST['name']        ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category    = trim((string) ($_POST['category']    ?? ''));
    $hosting     = trim((string) ($_POST['hosting']     ?? 'unknown'));
    $vendor      = trim((string) ($_POST['vendor']      ?? ''));
    $owner       = trim((string) ($_POST['owner']       ?? ''));
    $contact     = trim((string) ($_POST['contact']     ?? ''));

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        try {
            update_system($pdo, $systemId, $name, $description, $category, $hosting, $vendor, $owner, $contact);
            $system  = fetch_system($pdo, $systemId);
            $success = 'Changes saved.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Edit system</h1>
        <p><?= h($system['name']) ?></p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/systems.php">Back to library</a>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php elseif ($success): ?>
    <div class="notice notice-success"><?= h($success) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= $systemId ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="name">Name <span style="color:var(--danger);">*</span></label>
                <input type="text" id="name" name="name" value="<?= h($name) ?>" required>
            </div>
            <div>
                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?= h($category) ?>"
                       placeholder="e.g. Telephony, CRM, Finance" list="cat-list">
                <datalist id="cat-list">
                    <option value="Telephony"><option value="Job Management">
                    <option value="Finance"><option value="HR">
                    <option value="CRM"><option value="Document Management">
                    <option value="Scheduling"><option value="Reporting">
                    <option value="Email"><option value="Web / Portal">
                </datalist>
            </div>
            <div>
                <label for="hosting">Hosting</label>
                <select id="hosting" name="hosting">
                    <?php foreach (['unknown','saas','cloud','on-premise','hybrid'] as $h): ?>
                        <option value="<?= $h ?>" <?= $hosting === $h ? 'selected' : '' ?>>
                            <?= hosting_label($h) ?><?= $h === 'saas' ? ' (vendor-managed cloud)' : '' ?><?= $h === 'cloud' ? ' (self-managed)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description" style="min-height:80px;"><?= h($description) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="vendor">Vendor / supplier</label>
                <input type="text" id="vendor" name="vendor" value="<?= h($vendor) ?>" placeholder="e.g. Northgate">
            </div>
            <div>
                <label for="owner">Internal owner / team</label>
                <input type="text" id="owner" name="owner" value="<?= h($owner) ?>" placeholder="e.g. ICT team">
            </div>
            <div>
                <label for="contact">Support contact</label>
                <input type="text" id="contact" name="contact" value="<?= h($contact) ?>"
                       placeholder="Email or name">
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Save changes</button>
            <a class="btn btn-secondary" href="/systems.php">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Edit — ' . h($system['name']), ob_get_clean() ?: '');
