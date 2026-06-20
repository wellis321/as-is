<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

ob_start();
?>
<header>
    <div>
        <h1>Development & Roadmap</h1>
        <p>What has been built, what is planned, and where the tool is heading.</p>
    </div>
</header>

<!-- ── Delivered ──────────────────────────────────────────────────── -->
<div class="card">
    <h2>Recently delivered</h2>
    <p style="color:var(--muted);font-size:0.875rem;margin-top:0;">Features built and live in the current version.</p>

    <div style="display:grid;gap:0;">
        <?php
        $done = [
            ['Diagram viewer — multi-row layout',
             'Steps wrap into multiple rows within each swimlane so even a 20+ step process is readable without horizontal scrolling. Lane heights adjust dynamically to fit their content.'],
            ['Colour-coded swimlane bands',
             'Each lane displays its own tinted background and a named header strip, making it immediately clear which team owns which steps.'],
            ['Interactive focus mode',
             'Click any step in the diagram to highlight it and its immediate connections. Everything else fades to grey. Click again or click the background to restore the full view.'],
            ['Step detail card panel',
             'Clicking a step opens a floating card list showing the descriptions, action type icons, and linked systems for every step in the mini-flow. The panel can be dragged to any position to avoid covering highlighted steps.'],
            ['Colour-coded connection types',
             'Grey arrows for same-lane flow, blue for cross-lane handoffs, amber dashed for loop-backs, and L-shaped arrows for row-wraps. Each type is visually distinct.'],
            ['Subprocess step type',
             'A new step type for steps that represent a whole separate process. Shown as a rectangle with a + badge. Planned: click-through to the linked process map.'],
            ['Parallel gateway step type',
             'A diamond with + inside for steps where multiple paths run simultaneously — not a decision, but a split where all branches are taken at once.'],
            ['Zoom, pan, fit and full screen',
             'Scroll to zoom centred on the cursor, drag to pan, Fit to reset, and full-screen mode for workshops and presentations.'],
            ['Sample diagrams',
             'Three sample process maps are available to explore: Housing Repair (7 steps, simple), Customer First (21 steps, 3 lanes), and Purchase to Pay (20 steps, 4 lanes).'],
        ];
        foreach ($done as $i => [$title, $body]):
        ?>
        <div style="display:grid;grid-template-columns:28px 1fr;gap:0.5rem;
                    padding:0.9rem 0;<?= $i < count($done) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <span style="font-size:1.1rem;color:var(--success);padding-top:0.05rem;">✓</span>
            <div>
                <strong style="font-size:0.9rem;"><?= h($title) ?></strong>
                <p style="margin:0.2rem 0 0;font-size:0.82rem;color:var(--muted);"><?= h($body) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Planned ────────────────────────────────────────────────────── -->
<div class="card">
    <h2>Planned features</h2>
    <p style="color:var(--muted);font-size:0.875rem;margin-top:0;">Ideas and features that are scoped or under consideration.</p>

    <div style="display:grid;gap:0;">
        <?php
        $planned = [
            ['high', 'Subprocess — link to another document',
             'When a step is marked as Subprocess, clicking it in the diagram navigates to the linked process map (identified by slug). This allows complex processes to be broken into navigable layers without losing the overview.'],
            ['high', 'Off-page connector',
             'A new step type that signals the flow continues in a different process map, with a visible link. Complementary to Subprocess — where Subprocess means "this step is itself a whole process", an off-page connector means "the flow picks up over there".'],
            ['high', 'Feedback widget',
             'A persistent feedback button available on every page so users can submit suggestions, report issues, or flag diagram errors without leaving the tool. Captured with page context automatically.'],
            ['medium', 'Export as image or PDF',
             'One-click export of the current diagram as a PNG image or print-optimised PDF, suitable for pasting into reports or sharing with people who don\'t have access to the system.'],
            ['medium', 'Version comparison',
             'Side-by-side or overlay view showing what changed between two versions of the same process map — useful for tracking how a process has evolved after a review or system change.'],
            ['medium', 'TO-BE mapping',
             'Pair an AS-IS with a proposed future-state (TO-BE) map so analysts can document both the current process and the target design in the same tool.'],
            ['medium', 'Step annotations and comments',
             'Add freetext notes or comments to individual steps — for example "flagged as a pain point in interviews" or "system integration not yet confirmed".'],
            ['low', 'Bulk step import from spreadsheet',
             'Upload a CSV or paste from a spreadsheet to create or update steps in bulk, reducing the time to capture a large process after a workshop.'],
            ['low', 'Search across all maps',
             'Full-text search across all published process maps — find which processes reference a specific system, step type, or team without opening each document.'],
            ['low', 'Diagram embed / shareable link with hash',
             'A shareable URL that opens the diagram with a specific step already in focus — useful for sharing direct links to a particular part of a complex process.'],
        ];
        $priority = ['high' => ['Urgent', '#dc2626', '#fef2f2'], 'medium' => ['Soon', '#d97706', '#fffbeb'], 'low' => ['Later', '#6b7280', '#f9fafb']];
        foreach ($planned as $i => [$p, $title, $body]):
            [$plabel, $pcol, $pbg] = $priority[$p];
        ?>
        <div style="display:grid;grid-template-columns:28px 1fr;gap:0.5rem;
                    padding:0.9rem 0;<?= $i < count($planned) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <span style="font-size:0.95rem;color:var(--muted);padding-top:0.1rem;">○</span>
            <div>
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.2rem;">
                    <strong style="font-size:0.9rem;"><?= h($title) ?></strong>
                    <span style="font-size:0.68rem;font-weight:700;text-transform:uppercase;
                                 letter-spacing:0.05em;padding:0.1rem 0.4rem;border-radius:3px;
                                 color:<?= $pcol ?>;background:<?= $pbg ?>;"><?= $plabel ?></span>
                </div>
                <p style="margin:0;font-size:0.82rem;color:var(--muted);"><?= h($body) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Notes ──────────────────────────────────────────────────────── -->
<div class="card" style="background:var(--bg);">
    <h2>Development notes</h2>
    <p>
        This tool is built as a single PHP application with no framework, no build step, and a plain MySQL database.
        All diagram rendering is done client-side in SVG using vanilla JavaScript — no charting library.
    </p>
    <p>
        If you have a feature suggestion or find a bug, use the feedback button at the bottom of any page
        or speak directly to the development team.
    </p>
    <p style="margin:0;font-size:0.875rem;color:var(--muted);">
        Last updated: <?= date('d F Y') ?>
    </p>
</div>

<?php
render_layout('Development & Roadmap', ob_get_clean() ?: '');
