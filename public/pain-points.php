<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$pdo      = db();
ensure_schema($pdo);
$document = resolve_document_request($pdo);

if ($document === null) {
    redirect('/documents.php');
}

$asIsId = (int) $document['id'];
$lanes  = fetch_lanes($pdo, $asIsId);
$steps  = fetch_steps($pdo, $asIsId);

// Group steps by lane, keeping only those with pain_points filled in
$byLane      = [];
$totalWithPP = 0;
$laneIndex   = [];
foreach ($lanes as $l) {
    $laneIndex[(int) $l['id']] = $l;
}
foreach ($steps as $s) {
    $pp = trim((string) ($s['pain_points'] ?? ''));
    if ($pp === '') continue;
    $lid = (int) $s['lane_id'];
    if (!isset($byLane[$lid])) {
        $byLane[$lid] = ['lane' => $laneIndex[$lid] ?? ['name' => 'Unknown', 'color' => '#e8f0fe'], 'steps' => []];
    }
    $byLane[$lid]['steps'][] = $s;
    $totalWithPP++;
}

ob_start();
?>
<style>
.pp-lane-heading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1.75rem 0 0.6rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
}
.pp-lane-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.pp-card {
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 0.9rem 1.1rem;
    background: #fff;
    margin-bottom: 0.5rem;
}
.pp-step-meta {
    font-size: 0.75rem;
    color: var(--muted);
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.pp-step-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.45rem;
}
.pp-text {
    font-size: 0.875rem;
    line-height: 1.6;
    white-space: pre-wrap;
    color: var(--text);
}
#copy-all-btn { transition: background 0.15s; }
</style>

<header>
    <div>
        <h1><?= h($document['title']) ?></h1>
        <p style="margin:0;color:var(--muted);font-size:0.875rem;">
            Issues &amp; frustrations —
            <?php if ($totalWithPP === 0): ?>
                none captured yet
            <?php else: ?>
                <?= $totalWithPP ?> step<?= $totalWithPP !== 1 ? 's' : '' ?> with notes
            <?php endif; ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($totalWithPP > 0): ?>
            <button id="copy-all-btn" class="btn btn-secondary btn-sm" type="button">Copy all as text</button>
        <?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>">View diagram</a>
        <?php if (can_edit_document($document)): ?>
            <a class="btn btn-secondary btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Edit</a>
        <?php endif; ?>
    </div>
</header>

<?php if ($totalWithPP === 0): ?>
<div class="card" style="color:var(--muted);">
    <p style="margin:0;">No issues or frustrations have been captured yet. Open any step and fill in the <strong>Issues / frustrations</strong> field to start building this list.</p>
</div>
<?php else: ?>

<div id="pp-content">
<?php foreach ($byLane as $lid => $group):
    $lane = $group['lane'];
?>
    <div class="pp-lane-heading">
        <span class="pp-lane-dot" style="background:<?= h($lane['color']) ?>;"></span>
        <?= h($lane['name']) ?>
    </div>

    <?php foreach ($group['steps'] as $s): ?>
    <div class="pp-card">
        <div class="pp-step-meta">
            <span><?= (int) $s['step_number'] ?></span>
            <span style="color:var(--border);">·</span>
            <span><?= h(step_type_label($s['step_type'])) ?></span>
        </div>
        <div class="pp-step-title"><?= h($s['title']) ?></div>
        <div class="pp-text"><?= h(trim((string) ($s['pain_points'] ?? ''))) ?></div>
        <?php if (can_edit_document($document)): ?>
        <div style="margin-top:0.6rem;">
            <a class="lnk-muted" style="font-size:0.78rem;"
               href="/step-edit.php?slug=<?= rawurlencode($document['slug']) ?>&step_id=<?= (int) $s['id'] ?>">Edit step</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

<?php endforeach; ?>
</div>

<script>
(function () {
    const btn = document.getElementById('copy-all-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const lines = [];
        const title = <?= json_encode($document['title']) ?>;
        lines.push(title);
        lines.push('Issues & frustrations');
        lines.push('');

        document.querySelectorAll('#pp-content .pp-lane-heading').forEach(heading => {
            lines.push('─── ' + heading.textContent.trim() + ' ───');
            lines.push('');

            let el = heading.nextElementSibling;
            while (el && el.classList.contains('pp-card')) {
                const num   = el.querySelector('.pp-step-meta')?.textContent?.trim().split('·')[0]?.trim() ?? '';
                const title = el.querySelector('.pp-step-title')?.textContent?.trim() ?? '';
                const text  = el.querySelector('.pp-text')?.textContent?.trim() ?? '';
                lines.push('Step ' + num + ' — ' + title);
                lines.push(text);
                lines.push('');
                el = el.nextElementSibling;
            }
        });

        navigator.clipboard.writeText(lines.join('\n')).then(() => {
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy all as text', 1800);
        }).catch(() => {
            btn.textContent = 'Copy failed';
            setTimeout(() => btn.textContent = 'Copy all as text', 1800);
        });
    });
})();
</script>

<?php endif; ?>
<?php
render_layout('Issues & frustrations — ' . $document['title'], ob_get_clean() ?: '');
