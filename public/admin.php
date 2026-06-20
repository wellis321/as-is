<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

$pdo = db();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalMaps   = (int) $pdo->query("SELECT COUNT(*) FROM as_is_documents")->fetchColumn();
$published   = (int) $pdo->query("SELECT COUNT(*) FROM as_is_documents WHERE status = 'published'")->fetchColumn();
$draft       = (int) $pdo->query("SELECT COUNT(*) FROM as_is_documents WHERE status = 'draft'")->fetchColumn();

$feedbackCount = 0;
try { $feedbackCount = (int) $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn(); } catch (Throwable) {}

// ── Users ─────────────────────────────────────────────────────────────────────
$users = [];
try {
    $users = auth_db()->query(
        "SELECT id, username, display_name, email, app_role, auth_provider, is_active, created_at
         FROM users ORDER BY app_role ASC, username ASC"
    )->fetchAll();
} catch (Throwable) {}

// ── Recent feedback ───────────────────────────────────────────────────────────
$recentFeedback = [];
try {
    $recentFeedback = $pdo->query(
        "SELECT type, message, page, submitted_by, created_at
         FROM feedback ORDER BY created_at DESC LIMIT 6"
    )->fetchAll();
} catch (Throwable) {}

// ── Recent documents ─────────────────────────────────────────────────────────
$recentDocs = $pdo->query(
    "SELECT title, slug, status, owner, department
     FROM as_is_documents ORDER BY id DESC LIMIT 8"
)->fetchAll();

$roleStyle = [
    'admin'  => 'color:#7c3aed;background:#f5f3ff;',
    'editor' => 'color:#0284c7;background:#f0f9ff;',
    'viewer' => 'color:#475569;background:#f8fafc;',
];

ob_start();
?>
<header>
    <div>
        <h1>Admin</h1>
        <p>System overview, users, and recent activity.</p>
    </div>
</header>

<!-- ── Summary stat links ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem;">

    <a href="/documents.php" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:2rem;font-weight:700;line-height:1.1;"><?= $totalMaps ?></div>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Process maps</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;display:flex;gap:0.75rem;">
            <span style="color:var(--success);font-weight:600;"><?= $published ?> published</span>
            <span style="color:var(--warning);font-weight:600;"><?= $draft ?> draft</span>
        </div>
    </a>

    <a href="/feedback-view.php" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:2rem;font-weight:700;line-height:1.1;"><?= $feedbackCount ?></div>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Feedback submissions</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">View all &rarr;</div>
    </a>

    <a href="#users" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:2rem;font-weight:700;line-height:1.1;"><?= count($users) ?></div>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Users with access</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">Manage below &darr;</div>
    </a>

    <a href="/dev.php" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:2rem;font-weight:700;line-height:1.1;">→</div>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Roadmap &amp; planned features</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">View roadmap &rarr;</div>
    </a>

    <a href="/setup.php" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="font-size:2rem;font-weight:700;line-height:1.1;">⚙</div>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Setup &amp; seed data</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">Open setup &rarr;</div>
    </a>

</div>

<!-- ── Users ──────────────────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;" id="users">
    <div style="padding:0.9rem 1.25rem;border-bottom:1px solid var(--border);">
        <h2 style="margin:0;font-size:1rem;">Users (<?= count($users) ?>)</h2>
        <p style="margin:0.25rem 0 0;font-size:0.8rem;color:var(--muted);">
            To add users or change roles, update the seed SQL or provision via Microsoft Entra.
        </p>
    </div>
    <?php if ($users === []): ?>
        <p style="padding:1rem 1.25rem;color:var(--muted);margin:0;">No users found.</p>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr style="background:var(--bg);border-bottom:2px solid var(--border);">
                <th style="padding:0.55rem 1.25rem;text-align:left;font-weight:600;">Name</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Username</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Email</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Role</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Auth</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Status</th>
                <th style="padding:0.55rem 1rem;text-align:left;font-weight:600;">Since</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
            $rs = $roleStyle[$u['app_role']] ?? 'color:#64748b;background:#f8fafc;';
            $mailLink = $u['email'] ? 'mailto:' . rawurlencode($u['email']) : null;
        ?>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.6rem 1.25rem;font-weight:500;">
                    <?php if ($mailLink): ?>
                        <a href="<?= h($mailLink) ?>" style="color:inherit;text-decoration:none;"
                           onmouseover="this.style.textDecoration='underline'"
                           onmouseout="this.style.textDecoration='none'">
                            <?= h($u['display_name'] ?: $u['username']) ?>
                        </a>
                    <?php else: ?>
                        <?= h($u['display_name'] ?: $u['username']) ?>
                    <?php endif; ?>
                </td>
                <td style="padding:0.6rem 1rem;color:var(--muted);"><?= h($u['username']) ?></td>
                <td style="padding:0.6rem 1rem;font-size:0.8rem;">
                    <?php if ($u['email']): ?>
                        <a href="mailto:<?= rawurlencode($u['email']) ?>"
                           style="color:var(--muted);text-decoration:none;"
                           onmouseover="this.style.textDecoration='underline'"
                           onmouseout="this.style.textDecoration='none'">
                            <?= h($u['email']) ?>
                        </a>
                    <?php else: ?>
                        <span style="opacity:.4">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:0.6rem 1rem;">
                    <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;
                                 letter-spacing:0.04em;padding:0.15rem 0.45rem;border-radius:4px;<?= $rs ?>">
                        <?= h($u['app_role']) ?>
                    </span>
                </td>
                <td style="padding:0.6rem 1rem;font-size:0.8rem;color:var(--muted);">
                    <?= h(ucfirst($u['auth_provider'])) ?>
                </td>
                <td style="padding:0.6rem 1rem;">
                    <?php if ($u['is_active']): ?>
                        <span style="color:var(--success);font-size:0.8rem;font-weight:600;">Active</span>
                    <?php else: ?>
                        <span style="color:var(--danger);font-size:0.8rem;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td style="padding:0.6rem 1rem;color:var(--muted);font-size:0.8rem;white-space:nowrap;">
                    <?= h(date('d M Y', strtotime($u['created_at']))) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── Recent feedback ────────────────────────────────────────────── -->
<?php if ($recentFeedback !== []): ?>
<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:0.9rem 1.25rem;border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0;font-size:1rem;">Recent feedback</h2>
        <a href="/feedback-view.php" class="btn btn-secondary btn-sm">View all <?= $feedbackCount ?></a>
    </div>
    <?php foreach ($recentFeedback as $i => $fb): ?>
    <a href="/feedback-view.php" style="text-decoration:none;color:inherit;display:grid;
        grid-template-columns:auto 1fr auto;gap:0.75rem;align-items:start;
        padding:0.75rem 1.25rem;font-size:0.875rem;
        <?= $i < count($recentFeedback)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>
        transition:background .1s;"
        onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
        <span class="badge" style="margin-top:0.05rem;"><?= h($fb['type']) ?></span>
        <div>
            <div><?= h($fb['message']) ?></div>
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.15rem;"><?= h($fb['page']) ?></div>
        </div>
        <div style="text-align:right;font-size:0.75rem;color:var(--muted);white-space:nowrap;">
            <?= h($fb['submitted_by']) ?><br>
            <?= h(date('d M H:i', strtotime($fb['created_at']))) ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Recent process maps ────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:0.9rem 1.25rem;border-bottom:1px solid var(--border);
                display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0;font-size:1rem;">Recent process maps</h2>
        <a href="/documents.php" class="btn btn-secondary btn-sm">View all <?= $totalMaps ?></a>
    </div>
    <?php foreach ($recentDocs as $i => $doc): ?>
    <a href="/view.php?slug=<?= rawurlencode($doc['slug']) ?>"
       style="text-decoration:none;color:inherit;display:grid;
              grid-template-columns:1fr auto auto;gap:1rem;align-items:center;
              padding:0.7rem 1.25rem;font-size:0.875rem;
              <?= $i < count($recentDocs)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>
              transition:background .1s;"
       onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
        <div>
            <div style="font-weight:500;"><?= h($doc['title']) ?></div>
            <?php if ($doc['owner'] || $doc['department']): ?>
            <div style="font-size:0.78rem;color:var(--muted);margin-top:0.1rem;">
                <?= h(implode(' · ', array_filter([$doc['owner'], $doc['department']]))) ?>
            </div>
            <?php endif; ?>
        </div>
        <span class="badge"><?= h(ucfirst($doc['status'])) ?></span>
        <span style="font-size:0.8rem;color:var(--accent);white-space:nowrap;">View &rarr;</span>
    </a>
    <?php endforeach; ?>
</div>

<?php
render_layout('Admin', ob_get_clean() ?: '');
