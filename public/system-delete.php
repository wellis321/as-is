<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$pdo = db();
ensure_schema($pdo);

$systemId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['system_id'] ?? 0);
$system = $systemId > 0 ? fetch_system($pdo, $systemId) : null;
$error = null;

if ($system === null) {
    redirect('/systems.php');
}

$stepCount = (int) ($system['step_count'] ?? 0);
if ($stepCount === 0) {
    $usage = $pdo->prepare(
        'SELECT COUNT(*) FROM step_systems ss
         INNER JOIN steps s ON s.id = ss.step_id
         WHERE ss.system_id = ?'
    );
    $usage->execute([$systemId]);
    $stepCount = (int) $usage->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmName = trim((string) ($_POST['confirm_name'] ?? ''));

    if (strcasecmp($confirmName, $system['name']) !== 0) {
        $error = 'The name did not match. Nothing was deleted.';
    } else {
        try {
            delete_system($pdo, $systemId);
            redirect('/systems.php');
        } catch (Throwable $e) {
            $error = 'Could not delete this system. Please try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Delete system</h1>
        <p>This cannot be undone.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="/system-edit.php?id=<?= (int) $system['id'] ?>">Cancel</a>
</header>

<?php if (!empty($error)): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card danger-zone">
    <p>You are about to delete <strong><?= h($system['name']) ?></strong> from the systems library.</p>
    <?php if ($stepCount > 0): ?>
        <p>It is used in <strong><?= $stepCount ?></strong> step<?= $stepCount === 1 ? '' : 's' ?> and will be removed from them.</p>
    <?php endif; ?>
    <p>Type the system name <strong><?= h($system['name']) ?></strong> below to confirm.</p>

    <form method="post" class="form-grid">
        <input type="hidden" name="system_id" value="<?= (int) $system['id'] ?>">
        <div>
            <label for="confirm_name">Confirm name</label>
            <input type="text" id="confirm_name" name="confirm_name" required autocomplete="off">
        </div>
        <div class="actions">
            <button class="btn btn-danger" type="submit">Delete permanently</button>
            <a class="btn btn-secondary" href="/systems.php">Cancel</a>
        </div>
    </form>
</div>
<?php
render_layout('Delete ' . $system['name'], ob_get_clean() ?: '');
