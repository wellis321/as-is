<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

$pdo = db();

// ── Handle load samples ──────────────────────────────────────────────────────
$samplesNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'load_samples') {
    if (!csrf_verify()) { redirect('/admin.php'); }
    try {
        ensure_schema($pdo);
        run_sql_file($pdo, dirname(__DIR__) . '/sql/seed_samples.sql');
        $samplesNotice = ['success', 'Sample documents loaded successfully.'];
    } catch (Throwable $e) {
        $samplesNotice = ['error', 'Could not load samples: ' . $e->getMessage()];
    }
    $_SESSION['admin_notice_samples'] = $samplesNotice;
    redirect('/admin.php');
}
if (isset($_SESSION['admin_notice_samples'])) {
    $samplesNotice = $_SESSION['admin_notice_samples'];
    unset($_SESSION['admin_notice_samples']);
}

// ── Handle role change ────────────────────────────────────────────────────────
$roleNotice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_role') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $roleNotice = ['error', 'Invalid request — please try again.'];
    } else {
        $targetId  = (int) ($_POST['user_id'] ?? 0);
        $newRole   = $_POST['role'] ?? '';
        $myId      = (int) ($_SESSION['user_id'] ?? 0);

        if (!in_array($newRole, ['viewer', 'editor', 'admin'], true)) {
            $roleNotice = ['error', 'Invalid role.'];
        } elseif ($targetId === $myId) {
            $roleNotice = ['error', 'You cannot change your own role.'];
        } elseif ($targetId > 0) {
            try {
                auth_db()->prepare('UPDATE users SET app_role = ? WHERE id = ?')
                    ->execute([$newRole, $targetId]);
                $roleNotice = ['success', 'Role updated.'];
            } catch (Throwable $e) {
                $roleNotice = ['error', 'Could not update role: ' . $e->getMessage()];
            }
        }
    }
    // Redirect to avoid re-POST on refresh
    $_SESSION['admin_notice'] = $roleNotice;
    redirect('/admin.php#users');
}

if (isset($_SESSION['admin_notice'])) {
    $roleNotice = $_SESSION['admin_notice'];
    unset($_SESSION['admin_notice']);
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];
$myUserId  = (int) ($_SESSION['user_id'] ?? 0);

// ── Check if sample data is already loaded ────────────────────────────────────
$samplesLoaded = false;
try {
    $s = $pdo->query("SELECT COUNT(*) FROM as_is_documents WHERE slug IN ('sample-customer-first','sample-purchase-to-pay','sample-repair-quick')")->fetchColumn();
    $samplesLoaded = (int)$s > 0;
} catch (Throwable) {}

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

<?php if ($roleNotice): ?>
<div class="notice" style="background:<?= $roleNotice[0] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;
     color:#fff;padding:0.6rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:0.875rem;">
    <?= h($roleNotice[1]) ?>
</div>
<?php endif; ?>
<?php if ($samplesNotice): ?>
<div class="notice" style="background:<?= $samplesNotice[0] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;
     color:#fff;padding:0.6rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:0.875rem;">
    <?= h($samplesNotice[1]) ?>
</div>
<?php endif; ?>

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
        <i data-lucide="map" style="width:1.75rem;height:1.75rem;color:var(--accent);display:block;margin-bottom:0.5rem;"></i>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Roadmap &amp; planned features</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">View roadmap &rarr;</div>
    </a>

    <a href="/security.php" style="text-decoration:none;color:inherit;
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);
        padding:1.1rem 1.25rem;display:block;transition:border-color .15s;"
        onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <i data-lucide="shield-check" style="width:1.75rem;height:1.75rem;color:var(--success);display:block;margin-bottom:0.5rem;"></i>
        <div style="font-size:0.8rem;color:var(--muted);margin-top:0.2rem;">Security controls</div>
        <div style="font-size:0.75rem;margin-top:0.5rem;color:var(--accent);font-weight:600;">View report &rarr;</div>
    </a>


</div>

<!-- ── Users ──────────────────────────────────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;" id="users">
    <div style="padding:0.9rem 1.25rem;border-bottom:1px solid var(--border);">
        <h2 style="margin:0;font-size:1rem;">Users (<?= count($users) ?>)</h2>
        <p style="margin:0.25rem 0 0;font-size:0.8rem;color:var(--muted);">
            Change a role using the dropdown — it saves immediately. You cannot change your own role.
        To add new users, run the seed SQL or provision via Microsoft Entra.
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
                <td style="padding:0.4rem 1rem;">
                    <?php if ((int)$u['id'] === $myUserId): ?>
                        <!-- Cannot edit your own role -->
                        <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;
                                     letter-spacing:0.04em;padding:0.15rem 0.45rem;border-radius:4px;<?= $rs ?>">
                            <?= h($u['app_role']) ?>
                        </span>
                    <?php else: ?>
                        <form method="post" action="/admin.php" style="display:flex;align-items:center;gap:0.35rem;">
                            <input type="hidden" name="action"     value="change_role">
                            <input type="hidden" name="user_id"   value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <select name="role"
                                    data-original="<?= h($u['app_role']) ?>"
                                    data-username="<?= h($u['display_name'] ?: $u['username']) ?>"
                                    onchange="confirmRoleChange(this)"
                                    style="font-size:0.75rem;font-weight:700;text-transform:uppercase;
                                           letter-spacing:0.04em;padding:0.15rem 0.4rem;border-radius:4px;
                                           border:1px solid var(--border);cursor:pointer;<?= $rs ?>
                                           appearance:none;-webkit-appearance:none;">
                                <option value="viewer" <?= $u['app_role']==='viewer' ?'selected':'' ?>>Viewer</option>
                                <option value="editor" <?= $u['app_role']==='editor' ?'selected':'' ?>>Editor</option>
                                <option value="admin"  <?= $u['app_role']==='admin'  ?'selected':'' ?>>Admin</option>
                            </select>
                        </form>
                    <?php endif; ?>
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
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.15rem;">
                <?php if ($fb['page']): ?>
                    <a href="<?= h($fb['page']) ?>" style="color:var(--muted);"><?= h(page_label($fb['page'])) ?></a>
                <?php endif; ?>
            </div>
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

<!-- ── Sample data ────────────────────────────────────────────────── -->
<div class="card" style="padding:1rem 1.25rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 0.2rem;font-size:1rem;">Sample process maps</h2>
            <?php if ($samplesLoaded): ?>
                <p style="margin:0;font-size:0.85rem;color:var(--muted);">
                    The three sample diagrams are already loaded —
                    <a href="/view.php?slug=sample-repair-quick">Housing Repair Quick View</a>,
                    <a href="/view.php?slug=sample-customer-first">Customer First</a>, and
                    <a href="/view.php?slug=sample-purchase-to-pay">Purchase to Pay</a>.
                    Reload to reset them to their original state.
                </p>
            <?php else: ?>
                <p style="margin:0;font-size:0.85rem;color:var(--muted);">
                    Load three worked example diagrams — Housing Repair Quick View, Customer First, and Purchase to Pay.
                    Never touches your own maps.
                </p>
            <?php endif; ?>
        </div>
        <form method="post" style="flex-shrink:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="load_samples">
            <button class="btn btn-secondary btn-sm" type="submit">
                <?= $samplesLoaded ? 'Reload sample documents' : 'Load sample documents' ?>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => { if (typeof lucide !== 'undefined') lucide.createIcons(); });

function confirmRoleChange(select) {
    var orig  = select.dataset.original;
    var name  = select.dataset.username;
    var label = select.options[select.selectedIndex].text;
    if (window.confirm('Change ' + name + '’s role to ' + label + '?')) {
        select.form.submit();
    } else {
        select.value = orig;
    }
}
</script>
<?php
render_layout('Admin', ob_get_clean() ?: '');
