<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

$rows = [];
try {
    $pdo   = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type       VARCHAR(30)  NOT NULL DEFAULT 'other',
        message    TEXT         NOT NULL,
        page       VARCHAR(500) NOT NULL DEFAULT '',
        submitted_by VARCHAR(120) NOT NULL DEFAULT '',
        ip         VARCHAR(45)  NOT NULL DEFAULT '',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $rows  = $pdo->query('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 200')->fetchAll();
} catch (Throwable) {}

ob_start();
?>
<header>
    <div>
        <h1>Feedback submissions</h1>
        <p><?= count($rows) ?> submission<?= count($rows) !== 1 ? 's' : '' ?> — most recent first.</p>
    </div>
</header>

<?php if ($rows === []): ?>
<div class="card"><p style="color:var(--muted);margin:0;">No feedback submitted yet.</p></div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr style="background:var(--bg);border-bottom:2px solid var(--border);">
                <th style="padding:0.65rem 1rem;text-align:left;font-weight:600;">Date</th>
                <th style="padding:0.65rem 1rem;text-align:left;font-weight:600;">Type</th>
                <th style="padding:0.65rem 1rem;text-align:left;font-weight:600;">Page</th>
                <th style="padding:0.65rem 1rem;text-align:left;font-weight:600;">Submitted by</th>
                <th style="padding:0.65rem 1rem;text-align:left;font-weight:600;">Message</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.6rem 1rem;white-space:nowrap;color:var(--muted);">
                    <?= h(date('d M Y H:i', strtotime($r['created_at']))) ?>
                </td>
                <td style="padding:0.6rem 1rem;">
                    <span class="badge"><?= h($r['type']) ?></span>
                </td>
                <td style="padding:0.6rem 1rem;font-size:0.8rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?php if ($r['page']): ?>
                        <a href="<?= h($r['page']) ?>" style="color:var(--muted);text-decoration:none;"
                           onmouseover="this.style.textDecoration='underline'"
                           onmouseout="this.style.textDecoration='none'"
                           title="<?= h($r['page']) ?>">
                            <?= h(page_label($r['page'])) ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td style="padding:0.6rem 1rem;color:var(--muted);"><?= h($r['submitted_by']) ?></td>
                <td style="padding:0.6rem 1rem;max-width:340px;"><?= h($r['message']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php
render_layout('Feedback', ob_get_clean() ?: '');
