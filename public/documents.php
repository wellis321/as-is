<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$error     = null;
$documents = [];
$notice    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'load_examples') {
    try {
        $pdo = db();
        ensure_schema($pdo);
        run_sql_file($pdo, dirname(__DIR__) . '/sql/seed_samples.sql');
        redirect('/documents.php');
    } catch (Throwable) {
        $notice = 'Could not add the example maps. Please try again.';
    }
}

try {
    $pdo = db();
    try { ensure_schema($pdo); } catch (Throwable) {} // best-effort migration
    $documents = fetch_documents($pdo);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

ob_start();
?>
<header>
    <div>
        <h1>Process maps</h1>
        <p>Your AS-IS documents — structured process maps you can view, edit, and share.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/new.php">+ New AS-IS</a>
    </div>
</header>

<?php if ($notice): ?>
    <div class="notice"><?= h($notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="notice">
        We could not load your process maps right now. Please try again in a few moments.
    </div>

<?php elseif ($documents === []): ?>

    <div class="home-empty">
        <p class="home-empty-eyebrow">Ready to document your first process</p>
        <h2 class="home-empty-heading">No process maps yet</h2>
        <p class="home-empty-body">
            An AS-IS document captures how a process currently works —
            the people involved, the systems they use, and the flow of actions
            from start to finish.
        </p>
        <div class="actions" style="margin-top:1.75rem;">
            <a class="btn" href="/new.php">Create your first AS-IS</a>
            <a class="btn btn-secondary" href="/view.php?slug=sample-customer-first">Browse an example</a>
        </div>
        <form method="post" style="margin-top:1rem;">
            <input type="hidden" name="action" value="load_examples">
            <button class="btn btn-link" type="submit">Add example maps to your library</button>
        </form>
        <p style="margin-top:1.25rem;font-size:0.875rem;color:var(--muted);">
            Not sure where to start?
            <a href="/help.php">Read the guidance</a>.
        </p>
    </div>

<?php else: ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <table style="table-layout:fixed;width:100%;">
            <colgroup>
                <col style="width:45%;">
                <col style="width:30%;">
                <col style="width:13%;">
                <col style="width:12%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Team</th>
                    <th>Last updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc):
                    $isSample = str_starts_with($doc['slug'], 'sample-');
                    $owner    = trim((string) ($doc['owner']      ?? ''));
                    $dept     = trim((string) ($doc['department'] ?? ''));
                    $team     = implode(' · ', array_filter([$owner, $dept]));
                ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:baseline;gap:0.4rem;min-width:0;">
                                <a class="doc-title-link td-clip"
                                   href="/view.php?slug=<?= rawurlencode($doc['slug']) ?>"
                                   title="<?= h($doc['title']) ?>"><?= h($doc['title']) ?></a>
                                <?php if ($isSample): ?>
                                    <span class="badge-example" style="flex-shrink:0;">Example</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($doc['description'])): ?>
                                <div class="td-clip"
                                     style="font-size:0.8125rem;color:var(--muted);margin-top:0.1rem;"
                                     title="<?= h($doc['description']) ?>"><?= h($doc['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="td-clip"
                            style="font-size:0.875rem;color:var(--muted);"
                            title="<?= h($team ?: '') ?>"><?= h($team ?: '—') ?></td>
                        <td class="td-clip" style="font-size:0.875rem;color:var(--muted);">
                            <?= h(date('d M Y', strtotime($doc['updated_at']))) ?>
                        </td>
                        <td style="vertical-align:middle;">
                            <div class="row-actions">
                                <a class="btn btn-sm"
                                   href="/view.php?slug=<?= rawurlencode($doc['slug']) ?>">View</a>
                                <a class="btn btn-secondary btn-sm"
                                   href="/edit.php?slug=<?= rawurlencode($doc['slug']) ?>">Edit</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
<?php
render_layout('Process maps', ob_get_clean() ?: '');
