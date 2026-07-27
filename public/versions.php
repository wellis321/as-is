<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo      = db();
$document = resolve_document_request($pdo);

if ($document === null) { redirect('/documents.php'); }
if (!can_edit_document($document)) {
    redirect('/view.php?slug=' . rawurlencode($document['slug']));
}

$asIsId = (int) $document['id'];
$error  = null;
$notice = null;

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action    = $_POST['action']     ?? '';
    $versionId = (int) ($_POST['version_id'] ?? 0);

    if ($action === 'save') {
        $label = trim($_POST['label'] ?? '');
        try {
            save_document_version($pdo, $asIsId, $label !== '' ? $label : 'Manual save');
            $_SESSION['version_notice'] = ['success', 'Version saved.'];
        } catch (Throwable $e) {
            $_SESSION['version_notice'] = ['error', 'Could not save version.'];
        }
        redirect('/versions.php?slug=' . rawurlencode($document['slug']));
    }

    if ($action === 'restore' && $versionId > 0) {
        // Verify version belongs to this document
        $v = $pdo->prepare('SELECT * FROM document_versions WHERE id = ? AND as_is_id = ?');
        $v->execute([$versionId, $asIsId]);
        $version = $v->fetch() ?: null;

        if ($version) {
            try {
                $pdo->beginTransaction();
                // Auto-save current state before restoring so user can undo the restore
                save_document_version($pdo, $asIsId, 'Before restore — ' . date('d M Y H:i'));
                restore_document_version($pdo, $asIsId, $version['snapshot'], $document['slug'], $document['status']);
                $pdo->commit();
                $_SESSION['version_notice'] = ['success', 'Restored to version from ' . date('d M Y H:i', strtotime($version['created_at'])) . '.'];
                redirect('/view.php?slug=' . rawurlencode($document['slug']));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Restore failed: ' . h($e->getMessage());
            }
        } else {
            $error = 'Version not found.';
        }
    }

    if ($action === 'delete' && $versionId > 0) {
        $pdo->prepare('DELETE FROM document_versions WHERE id = ? AND as_is_id = ?')
            ->execute([$versionId, $asIsId]);
        $_SESSION['version_notice'] = ['success', 'Version deleted.'];
        redirect('/versions.php?slug=' . rawurlencode($document['slug']));
    }
}

if (isset($_SESSION['version_notice'])) {
    $notice = $_SESSION['version_notice'];
    unset($_SESSION['version_notice']);
}

$versions = list_document_versions($pdo, $asIsId);

// Parse snapshot stats for display
$versionStats = [];
foreach ($versions as $v) {
    $data = json_decode($v['snapshot'], true);
    $versionStats[(int)$v['id']] = [
        'lanes'       => count($data['lanes']       ?? []),
        'steps'       => count($data['steps']       ?? []),
        'connections' => count($data['connections'] ?? []),
        'title'       => $data['title'] ?? '',
    ];
}

ob_start();
?>
<header>
    <div>
        <h1><?= h($document['title']) ?></h1>
        <p style="margin:0;color:var(--muted);font-size:0.875rem;">Version history — <?= count($versions) ?> saved version<?= count($versions) !== 1 ? 's' : '' ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">View diagram</a>
        <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Edit</a>
    </div>
</header>

<?php if ($notice): ?>
<div style="padding:0.55rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:0.875rem;
            background:<?= $notice[0] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;color:#fff;">
    <?= h($notice[1]) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="notice" style="border-left:3px solid var(--danger);color:var(--danger);"><?= $error ?></div>
<?php endif; ?>

<!-- Save new version -->
<div class="card">
    <h2 style="margin:0 0 0.6rem;">Save current version</h2>
    <p style="margin:0 0 1rem;color:var(--muted);font-size:0.875rem;">
        Snapshot the diagram as it is now. You can restore any saved version at any time.
    </p>
    <form method="post" style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="slug"   value="<?= h($document['slug']) ?>">
        <input type="hidden" name="action" value="save">
        <div style="flex:1;min-width:14rem;">
            <label for="label" style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.3rem;">
                Label <span style="font-weight:400;color:var(--muted);">(optional)</span>
            </label>
            <input type="text" id="label" name="label"
                   placeholder="e.g. Before adding Phase 2 steps"
                   style="width:100%;box-sizing:border-box;">
        </div>
        <button class="btn" type="submit" style="flex-shrink:0;">Save version</button>
    </form>
</div>

<!-- Version list -->
<?php if ($versions === []): ?>
<div class="card">
    <p style="margin:0;color:var(--muted);">No versions saved yet. Save one above, or versions are created automatically before major changes.</p>
</div>
<?php else: ?>
<div style="display:grid;gap:0.5rem;">
    <?php foreach ($versions as $i => $v):
        $stats = $versionStats[(int)$v['id']];
        $isAuto = str_starts_with($v['label'], 'Before ') || str_starts_with($v['label'], 'Auto');
        $age    = strtotime($v['created_at']);
        $when   = (time() - $age < 86400)
            ? date('H:i', $age) . ' today'
            : date('d M Y H:i', $age);
    ?>
    <div class="card" style="padding:0.85rem 1.1rem;">
        <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.3rem;">
                    <span style="font-weight:600;font-size:0.9rem;"><?= h($v['label'] !== '' ? $v['label'] : 'Version ' . ($i + 1)) ?></span>
                    <?php if ($isAuto): ?>
                        <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;
                                     padding:0.1rem 0.4rem;border-radius:3px;background:#f1f5f9;color:#64748b;">Auto</span>
                    <?php endif; ?>
                    <?php if ($i === 0): ?>
                        <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;
                                     padding:0.1rem 0.4rem;border-radius:3px;background:#f0fdf4;color:#16a34a;">Latest</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.8rem;color:var(--muted);">
                    <?= h($when) ?>
                    <?php if ($v['created_by_name']): ?>
                        · <?= h($v['created_by_name']) ?>
                    <?php endif; ?>
                    &nbsp;·&nbsp;
                    <?= $stats['lanes'] ?> lane<?= $stats['lanes'] !== 1 ? 's' : '' ?>
                    &nbsp;·&nbsp;
                    <?= $stats['steps'] ?> step<?= $stats['steps'] !== 1 ? 's' : '' ?>
                    &nbsp;·&nbsp;
                    <?= $stats['connections'] ?> connection<?= $stats['connections'] !== 1 ? 's' : '' ?>
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;align-items:center;flex-shrink:0;">
                <!-- Restore -->
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Restore to this version?\n\nThe current diagram will be saved automatically before restoring, so you can undo this if needed.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="slug"       value="<?= h($document['slug']) ?>">
                    <input type="hidden" name="action"     value="restore">
                    <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="btn btn-sm">Restore</button>
                </form>

                <!-- Delete -->
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Delete this version? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="slug"       value="<?= h($document['slug']) ?>">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="lnk-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<p style="font-size:0.78rem;color:var(--muted);margin-top:0.75rem;text-align:center;">
    Up to 30 versions are kept per document. The oldest are removed automatically when the limit is reached.
</p>
<?php endif; ?>
<?php
render_layout('Versions — ' . $document['title'], ob_get_clean() ?: '');
