<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$error     = null;
$documents = [];

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
        <?php if ($documents !== [] && !$error): ?>
            <h1>Process maps
                <span style="font-size:1rem;font-weight:400;font-family:var(--f-sans);
                             color:var(--muted);letter-spacing:0;">(<?= count($documents) ?>)</span>
            </h1>
        <?php else: ?>
            <h1>Process maps</h1>
        <?php endif; ?>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice">
        Could not connect to the database. Make sure MAMP MySQL is running,
        then visit <a href="/setup.php">setup</a>.
        <br><br><strong>Error:</strong> <?= h($error) ?>
    </div>
    <div class="card">
        <h2>Run locally</h2>
        <p>From the project folder:</p>
        <p><code>php -S localhost:8890 -t public</code></p>
        <p style="margin:0;">Then open <code>http://localhost:8890</code>.
           Database settings live in <code>.env</code>.</p>
    </div>

<?php elseif ($documents === []): ?>

    <!-- ── First-time empty state ──────────────────────────────────── -->
    <div class="home-empty">
        <p class="home-empty-eyebrow">Ready to document your first process</p>
        <h2 class="home-empty-heading">No process maps yet</h2>
        <p class="home-empty-body">
            An AS-IS document captures how a process currently works —
            the people involved, the systems they use, and the flow of actions
            from start to finish. It replaces hard-to-read diagrams with something
            structured, editable, and easy to share.
        </p>
        <div class="actions" style="margin-top:1.75rem;">
            <a class="btn" href="/new.php">Create your first AS-IS</a>
            <a class="btn btn-secondary" href="/setup.php">Load example documents</a>
        </div>
        <p style="margin-top:1.25rem;font-size:0.875rem;color:var(--muted);">
            Not sure where to start?
            <a href="/help.php">Read the guidance</a>.
        </p>
    </div>

<?php else: ?>

    <!-- ── Document list ───────────────────────────────────────────── -->
    <div class="card" style="padding:0;overflow:hidden;">
        <table style="table-layout:fixed;width:100%;">
            <colgroup>
                <col style="width:34%;"><!-- Title -->
                <col style="width:19%;"><!-- Team -->
                <col style="width:9%;"><!-- Status -->
                <col style="width:6%;"><!-- Steps -->
                <col style="width:13%;"><!-- Last updated -->
                <col style="width:19%;"><!-- Actions -->
            </colgroup>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Team</th>
                    <th>Status</th>
                    <th style="text-align:right;padding-right:1.5rem;">Steps</th>
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
                        <!-- Title + optional description, each clipped to one line -->
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

                        <!-- Team: clips with ellipsis, full value on hover via title -->
                        <td class="td-clip"
                            style="font-size:0.875rem;color:var(--muted);"
                            title="<?= h($team ?: '') ?>"><?= h($team ?: '—') ?></td>

                        <td>
                            <span class="badge badge-<?= h($doc['status']) ?>"><?= h($doc['status']) ?></span>
                        </td>

                        <td style="text-align:right;padding-right:1.5rem;color:var(--muted);">
                            <?= (int) $doc['step_count'] ?>
                        </td>

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
render_layout('Home', ob_get_clean() ?: '');
