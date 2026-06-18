<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pdo = db();
ensure_schema($pdo);

$systems = fetch_systems($pdo);

ob_start();
?>
<header>
    <div>
        <h1>Systems &amp; tools library</h1>
        <p>A shared library of the tools and software used in your process maps. Assign them to steps to show which system is involved at each point in the process.</p>
    </div>
    <a class="btn" href="/system-create.php">Add system</a>
</header>

<?php if ($systems !== []): ?>
<div class="card" style="padding:0;overflow:hidden;">
    <table style="table-layout:fixed;width:100%;">
        <colgroup>
            <col style="width:23%;"><!-- Name -->
            <col style="width:13%;"><!-- Category -->
            <col style="width:10%;"><!-- Hosting -->
            <col style="width:14%;"><!-- Vendor -->
            <col style="width:14%;"><!-- Owner -->
            <col style="width:9%;"><!-- Used in -->
            <col style="width:17%;"><!-- Actions -->
        </colgroup>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Hosting</th>
                <th>Vendor</th>
                <th>Owner</th>
                <th>Steps</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($systems as $sys): ?>
                <?php
                $tooltip = implode(' · ', array_filter([
                    $sys['description'] ?? '',
                    $sys['contact']     ? 'Contact: ' . $sys['contact'] : '',
                ]));
                ?>
                <tr>
                    <!-- Name + clipped description; contact preserved in tooltip -->
                    <td title="<?= h($tooltip) ?>">
                        <div class="td-clip" style="font-weight:600;"><?= h($sys['name']) ?></div>
                        <?php if (!empty($sys['description'])): ?>
                            <div class="td-clip" style="font-size:0.8rem;color:var(--muted);"><?= h($sys['description']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td class="td-clip" style="font-size:0.875rem;color:var(--muted);"
                        title="<?= h((string)($sys['category'] ?? '')) ?>"><?= h((string)($sys['category'] ?? '—')) ?></td>

                    <td>
                        <?php if (!empty($sys['hosting']) && $sys['hosting'] !== 'unknown'): ?>
                            <span class="badge" style="background:<?= hosting_color($sys['hosting']) ?>;">
                                <?= h(hosting_label($sys['hosting'])) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--muted);">—</span>
                        <?php endif; ?>
                    </td>

                    <td class="td-clip" style="font-size:0.875rem;color:var(--muted);"
                        title="<?= h((string)($sys['vendor'] ?? '')) ?>"><?= h((string)($sys['vendor'] ?? '—')) ?></td>

                    <td class="td-clip" style="font-size:0.875rem;color:var(--muted);"
                        title="<?= h((string)($sys['owner'] ?? '')) ?>"><?= h((string)($sys['owner'] ?? '—')) ?></td>

                    <td style="font-size:0.875rem;">
                        <?php if ((int)$sys['step_count'] > 0): ?>
                            <?= (int)$sys['step_count'] ?> step<?= $sys['step_count'] != 1 ? 's' : '' ?>
                        <?php else: ?>
                            <span style="color:var(--muted);">Not used</span>
                        <?php endif; ?>
                    </td>

                    <td style="vertical-align:middle;">
                        <div class="row-actions">
                            <a class="btn btn-secondary btn-sm"
                               href="/system-edit.php?id=<?= (int)$sys['id'] ?>">Edit</a>
                            <a class="lnk-danger" href="/system-delete.php?id=<?= (int)$sys['id'] ?>">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="card">
    <p style="color:var(--muted);margin:0;">No systems in the library yet. Add your first one below.</p>
</div>
<?php endif; ?>

<?php
render_layout('Systems & tools', ob_get_clean() ?: '');
