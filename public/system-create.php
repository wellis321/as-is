<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo = db();
ensure_schema($pdo);

$error       = null;
$name        = '';
$description = '';
$category    = '';
$hosting     = 'unknown';
$vendor      = '';
$owner       = '';
$contact     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/systems.php'); }
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
            create_system_full($pdo, $name, $description, $category, $hosting, $vendor, $owner, $contact);
            redirect('/systems.php');
        } catch (Throwable $e) {
            $error = str_contains($e->getMessage(), 'Duplicate')
                ? "A system named \"{$name}\" already exists."
                : $e->getMessage();
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Add system</h1>
        <p>Add a system or tool to the shared library.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/systems.php">Back to library</a>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="name">Name <span style="color:var(--danger);">*</span></label>
                <input type="text" id="name" name="name" value="<?= h($name) ?>"
                       placeholder="e.g. Liberty Converse" required autofocus>
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
                    <option value="unknown" <?= $hosting === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                    <option value="saas"       <?= $hosting === 'saas'       ? 'selected' : '' ?>>SaaS (vendor-managed cloud)</option>
                    <option value="cloud"      <?= $hosting === 'cloud'      ? 'selected' : '' ?>>Cloud (self-managed)</option>
                    <option value="on-premise" <?= $hosting === 'on-premise' ? 'selected' : '' ?>>On-premise</option>
                    <option value="hybrid"     <?= $hosting === 'hybrid'     ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>
        </div>

        <div>
            <label for="description">Description</label>
            <input type="text" id="description" name="description" value="<?= h($description) ?>"
                   placeholder="What does this system do in your organisation?">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="vendor">Vendor / supplier</label>
                <input type="text" id="vendor" name="vendor" value="<?= h($vendor) ?>"
                       placeholder="e.g. Northgate, Capita">
            </div>
            <div>
                <label for="owner">Internal owner / team</label>
                <input type="text" id="owner" name="owner" value="<?= h($owner) ?>"
                       placeholder="e.g. ICT, Finance team">
            </div>
            <div>
                <label for="contact">Support contact</label>
                <input type="text" id="contact" name="contact" value="<?= h($contact) ?>"
                       placeholder="Email or name">
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Add system</button>
            <a class="btn btn-secondary" href="/systems.php">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Add system', ob_get_clean() ?: '');
