<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

$pdo = db();

// Ensure table exists with archived_at column
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type         VARCHAR(30)   NOT NULL DEFAULT 'other',
        message      TEXT          NOT NULL,
        page         VARCHAR(500)  NOT NULL DEFAULT '',
        submitted_by VARCHAR(120)  NOT NULL DEFAULT '',
        ip           VARCHAR(45)   NOT NULL DEFAULT '',
        created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archived_at  DATETIME      NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add archived_at to existing tables that don't have it
    $col = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'archived_at'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN archived_at DATETIME NULL DEFAULT NULL");
    }
} catch (Throwable) {}

// ── Actions ───────────────────────────────────────────────────────────────────
$actionMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['fb_action'] ?? '';

    if ($id > 0) {
        try {
            if ($action === 'archive') {
                $pdo->prepare('UPDATE feedback SET archived_at = NOW() WHERE id = ?')->execute([$id]);
                $actionMsg = ['success', 'Archived.'];
            } elseif ($action === 'unarchive') {
                $pdo->prepare('UPDATE feedback SET archived_at = NULL WHERE id = ?')->execute([$id]);
                $actionMsg = ['success', 'Restored to inbox.'];
            } elseif ($action === 'delete') {
                $pdo->prepare('DELETE FROM feedback WHERE id = ?')->execute([$id]);
                $actionMsg = ['success', 'Deleted permanently.'];
            }
        } catch (Throwable $e) {
            $actionMsg = ['error', 'Action failed.'];
        }
    }
    $_SESSION['fb_action_msg'] = $actionMsg;
    $showArchived = !empty($_POST['show_archived']) ? '1' : '0';
    redirect('/feedback-view.php' . ($showArchived === '1' ? '?archived=1' : ''));
}

if (isset($_SESSION['fb_action_msg'])) {
    $actionMsg = $_SESSION['fb_action_msg'];
    unset($_SESSION['fb_action_msg']);
}

$showArchived = !empty($_GET['archived']);

// ── Fetch ─────────────────────────────────────────────────────────────────────
$rows = [];
try {
    $sql  = $showArchived
        ? 'SELECT * FROM feedback WHERE archived_at IS NOT NULL ORDER BY archived_at DESC LIMIT 200'
        : 'SELECT * FROM feedback WHERE archived_at IS NULL ORDER BY created_at DESC LIMIT 200';
    $rows = $pdo->query($sql)->fetchAll();
} catch (Throwable) {}

$totalActive   = 0;
$totalArchived = 0;
try {
    $totalActive   = (int) $pdo->query('SELECT COUNT(*) FROM feedback WHERE archived_at IS NULL')->fetchColumn();
    $totalArchived = (int) $pdo->query('SELECT COUNT(*) FROM feedback WHERE archived_at IS NOT NULL')->fetchColumn();
} catch (Throwable) {}

ob_start();
?>
<header>
    <div>
        <h1>Feedback</h1>
        <p style="margin:0;">
            <a href="/feedback-view.php"
               style="font-weight:<?= $showArchived ? '400' : '600' ?>;color:<?= $showArchived ? 'var(--muted)' : 'var(--accent)' ?>;text-decoration:none;">
                Inbox (<?= $totalActive ?>)
            </a>
            &nbsp;·&nbsp;
            <a href="/feedback-view.php?archived=1"
               style="font-weight:<?= $showArchived ? '600' : '400' ?>;color:<?= $showArchived ? 'var(--accent)' : 'var(--muted)' ?>;text-decoration:none;">
                Archived (<?= $totalArchived ?>)
            </a>
        </p>
    </div>
</header>

<?php if ($actionMsg): ?>
<div class="notice" style="background:<?= $actionMsg[0] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;
     color:#fff;padding:0.55rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:0.875rem;">
    <?= h($actionMsg[1]) ?>
</div>
<?php endif; ?>

<?php if ($rows === []): ?>
<div class="card">
    <p style="color:var(--muted);margin:0;">
        <?= $showArchived ? 'No archived feedback.' : 'No feedback in your inbox.' ?>
    </p>
</div>
<?php else: ?>

<div style="display:grid;gap:0.75rem;">
<?php foreach ($rows as $r):
    $preview = mb_strlen($r['message']) > 200
        ? mb_substr($r['message'], 0, 200) . '…'
        : $r['message'];
    $hasMore = mb_strlen($r['message']) > 200;
    $id      = (int) $r['id'];
?>
<div class="card" style="padding:0.9rem 1.1rem;" id="fb-<?= $id ?>">
    <!-- Meta row -->
    <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.5rem;">
        <span style="font-size:0.78rem;color:var(--muted);white-space:nowrap;">
            <?= h(date('d M Y H:i', strtotime($r['created_at']))) ?>
        </span>
        <span class="badge"><?= h($r['type']) ?></span>
        <?php if ($r['page']): ?>
            <a href="<?= h($r['page']) ?>" style="font-size:0.8rem;color:var(--muted);"
               title="<?= h($r['page']) ?>">
                <?= h(page_label($r['page'])) ?>
            </a>
        <?php endif; ?>
        <?php if ($r['submitted_by']): ?>
            <span style="font-size:0.8rem;color:var(--muted);"><?= h($r['submitted_by']) ?></span>
        <?php endif; ?>
    </div>

    <!-- Message -->
    <div style="font-size:0.9rem;line-height:1.55;white-space:pre-wrap;word-break:break-word;">
        <span class="fb-preview-<?= $id ?>"><?= h($preview) ?></span>
        <?php if ($hasMore): ?>
            <span class="fb-full-<?= $id ?>" style="display:none;"><?= h($r['message']) ?></span>
            <button onclick="
                    document.querySelector('.fb-preview-<?= $id ?>').style.display='none';
                    document.querySelector('.fb-full-<?= $id ?>').style.display='';
                    this.style.display='none';"
                    style="background:none;border:none;cursor:pointer;font-size:0.78rem;
                           color:var(--accent);padding:0 0.25rem;vertical-align:middle;">
                Read more
            </button>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:0.5rem;margin-top:0.65rem;align-items:center;">
        <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="show_archived" value="<?= $showArchived ? '1' : '0' ?>">
            <?php if ($showArchived): ?>
                <input type="hidden" name="fb_action" value="unarchive">
                <button type="submit" class="btn btn-secondary btn-sm">Restore to inbox</button>
            <?php else: ?>
                <input type="hidden" name="fb_action" value="archive">
                <button type="submit" class="btn btn-secondary btn-sm">Archive</button>
            <?php endif; ?>
        </form>
        <form method="post" style="display:inline;"
              onsubmit="return confirm('Permanently delete this feedback? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="fb_action" value="delete">
            <input type="hidden" name="show_archived" value="<?= $showArchived ? '1' : '0' ?>">
            <button type="submit" class="lnk-danger">Delete</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>
<?php
render_layout('Feedback', ob_get_clean() ?: '');
