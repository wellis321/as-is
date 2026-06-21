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
            ['Decision-branch-aware layout',
             'When a decision step has multiple outgoing paths, each branch is placed on its own row — the branch with the most steps continues on the primary row, exception paths drop below. This mirrors how a process analyst would draw the diagram by hand.'],
            ['Monochrome alternating lanes',
             'Swimlane bands alternate between two neutral grey tints, keeping the focus on step content and connection colours rather than lane colours.'],
            ['Straight/curved connection toggle',
             'Switch between right-angle (orthogonal) and smooth bezier connections in the diagram toolbar. Straight connections are easier to trace on complex maps. Preference saved per user in localStorage.'],
            ['Interactive focus mode with auto-scroll',
             'Click any step to highlight it and its immediate connections. The diagram automatically scrolls and zooms so all related steps are visible at once. Click a different step to switch focus without closing the panel.'],
            ['Step detail card panel with mini flow diagram',
             'Clicking a step opens a floating panel showing a compact schematic of the focused steps and their connections, followed by a card list with full descriptions, action types, and linked systems. Panel stays open and updates in place when switching steps.'],
            ['Colour-coded connection types',
             'Grey arrows for same-lane flow, blue for cross-lane handoffs, amber dashed for loop-backs, and L-shaped arrows for row-wraps. Each type is visually distinct.'],
            ['Subprocess and Parallel gateway step types',
             'Subprocess (rectangle with + badge) for steps that expand into a separate process. Parallel gateway (diamond with + inside) for steps where all outgoing paths run simultaneously.'],
            ['Zoom, pan, fit and full screen',
             'Scroll to zoom, drag to pan, Fit to reset, and Full screen with an exit button inside the diagram — useful for workshops and presentations.'],
            ['JSON export and import with live editor',
             'Export any process map as a clean JSON file. Import page shows a live editor where you can review and edit the JSON before creating the diagram, with real-time validation and a load-example button.'],
            ['Admin dashboard',
             'Admin-only page showing system stats, user list with inline role editing, recent feedback, and recent process maps. Accessible from the profile menu.'],
            ['Feedback widget',
             'A persistent Feedback button available on every page for logged-in users. Submissions capture the page URL and username automatically, and are viewable in the Admin section.'],
            ['Security controls page',
             'Admin-accessible page that runs live checks against the current request environment and reports on HTTP headers, session security, authentication, CSRF protection, authorisation, and file access. Shows Active / Local / Check status for each control.'],
            ['Lane templates',
             'New AS-IS form offers preset lane configurations (Housing repairs, Procurement, two teams, three teams) so lanes are created automatically on save.'],
            ['Clone step and auto step numbering',
             'Clone button on any step row creates an exact duplicate at the next available step number. New steps also pre-fill the step number field with the next available number.'],
            ['PDF print — diagram only',
             'The Print button now outputs the diagram SVG scaled to page width, with no header, navigation, or research notice — just the process map.'],
            ['Sample diagrams',
             'Three sample process maps: Housing Repair Quick View (shows subprocess and parallel gateway), Customer First (21 steps, 3 lanes), and Purchase to Pay (20 steps, 4 lanes).'],
        ];
        foreach ($done as $i => [$title, $body]):
        ?>
        <div style="display:grid;grid-template-columns:28px 1fr;gap:0.5rem;
                    padding:0.9rem 0;<?= $i < count($done) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <i data-lucide="check-circle-2" style="width:1.1rem;height:1.1rem;color:var(--success);flex-shrink:0;margin-top:0.1rem;"></i>
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
            ['high', 'Subprocess — click-through to linked document',
             'When a step is marked as Subprocess, clicking it in the diagram navigates to the linked process map (identified by slug). This allows complex processes to be broken into navigable layers without losing the overview.'],
            ['high', 'Publish / unpublish from the process maps list',
             'Toggle a diagram between Draft and Published directly from the list row without opening the edit page — a quick status change that is currently only possible inside the Edit page.'],
            ['high', 'Off-page connector step type',
             'A step type that signals the flow continues in a different process map, with a visible link. Complements Subprocess — where Subprocess means "this step is a whole sub-process", an off-page connector means "the flow continues in a different map".'],
            ['medium', 'Export diagram as PNG image',
             'One-click export of the current diagram as a PNG image, suitable for pasting into reports or presentations. Print-to-PDF is already available via the Print button; PNG fills the gap for embedding in other documents.'],
            ['medium', 'Version comparison',
             'Side-by-side or overlay view showing what changed between two versions of the same process map — useful for tracking how a process has evolved after a review or system change.'],
            ['medium', 'TO-BE mapping',
             'Pair an AS-IS with a proposed future-state (TO-BE) map so analysts can document both the current process and the target design in the same tool.'],
            ['medium', 'Step annotations and comments',
             'Add freetext notes or comments to individual steps — for example "flagged as a pain point in interviews" or "system integration not yet confirmed".'],
            ['medium', 'Visio / Lucidchart import via BPMN',
             'Accept a BPMN 2.0 XML export from Visio or Lucidchart and convert it into a process map. Both tools can export BPMN; the XML structure maps directly to our lanes, steps, and connections model.'],
            ['low', 'Search across all maps',
             'Full-text search across all published process maps — find which processes reference a specific system, step type, or team without opening each document.'],
            ['low', 'Diagram embed / shareable link with focus',
             'A shareable URL that opens the diagram with a specific step already highlighted in focus mode — useful for sharing direct links to a particular part of a complex process.'],
        ];
        $priority = ['high' => ['Urgent', '#dc2626', '#fef2f2'], 'medium' => ['Soon', '#d97706', '#fffbeb'], 'low' => ['Later', '#6b7280', '#f9fafb']];
        foreach ($planned as $i => [$p, $title, $body]):
            [$plabel, $pcol, $pbg] = $priority[$p];
        ?>
        <div style="display:grid;grid-template-columns:28px 1fr;gap:0.5rem;
                    padding:0.9rem 0;<?= $i < count($planned) - 1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <i data-lucide="circle" style="width:0.95rem;height:0.95rem;color:var(--muted);flex-shrink:0;margin-top:0.15rem;"></i>
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

<!-- ── SQL files to run ───────────────────────────────────────────── -->
<div class="card">
    <h2>SQL files to run</h2>
    <p style="color:var(--muted);font-size:0.875rem;margin-top:0;">
        Migration and seed scripts that need to be applied in phpMyAdmin (Hostinger) or run locally.
        All files are in the <code style="font-size:0.85em;background:var(--bg);padding:0.1rem 0.3rem;border-radius:3px;">sql/</code> folder of the project.
    </p>

    <div style="display:grid;gap:0;border:1px solid var(--border);border-radius:var(--r);">

        <?php
        $sqlFiles = [
            [
                'file'    => 'migrate_subprocess.sql',
                'status'  => 'pending',
                'title'   => 'Add Subprocess &amp; Parallel step types',
                'detail'  => 'Extends the <code>step_type</code> ENUM on the <code>steps</code> table to include <code>subprocess</code> and <code>parallel</code>. Required before users can create those step types. Safe to run again — the MODIFY is idempotent. <strong>Note:</strong> this also runs automatically on the next page load via <code>ensure_schema()</code>, so it may already be applied.',
                'env'     => 'Local + Hostinger',
            ],
            [
                'file'    => 'seed_samples.sql',
                'status'  => 'optional',
                'title'   => 'Load / refresh sample process maps',
                'detail'  => 'Deletes and recreates the three sample diagrams: <em>Housing Repair — Quick View</em>, <em>Customer First — Housing Repairs</em>, and <em>Purchase to Pay</em>. Run this after deploy if you want the latest sample content, or use the <strong>Load sample documents</strong> button on the <a href="/admin.php">Admin page</a>.',
                'env'     => 'Local + Hostinger',
            ],
            [
                'file'    => 'migrate_auth.sql',
                'status'  => 'done',
                'title'   => 'Create auth tables (users, login_attempts)',
                'detail'  => 'Creates the <code>users</code> and <code>login_attempts</code> tables. Already applied — included here for reference.',
                'env'     => 'Applied',
            ],
        ];

        $statusStyle = [
            'pending'  => ['#d97706', '#fffbeb', 'Pending'],
            'optional' => ['#0284c7', '#f0f9ff', 'Optional'],
            'done'     => ['#16a34a', '#f0fdf4', 'Applied'],
        ];

        foreach ($sqlFiles as $i => $f):
            [$sc, $sb, $sl] = $statusStyle[$f['status']];
        ?>
        <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:start;
                    padding:0.9rem 1rem;<?= $i < count($sqlFiles)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
            <div>
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                    <code style="font-size:0.85rem;font-weight:600;background:var(--bg);
                                 padding:0.1rem 0.4rem;border-radius:3px;border:1px solid var(--border);">
                        sql/<?= h($f['file']) ?>
                    </code>
                    <span style="font-size:0.7rem;font-weight:700;text-transform:uppercase;
                                 letter-spacing:0.05em;padding:0.1rem 0.4rem;border-radius:3px;
                                 color:<?= $sc ?>;background:<?= $sb ?>;"><?= $sl ?></span>
                </div>
                <div style="font-weight:600;font-size:0.875rem;margin-bottom:0.2rem;"><?= $f['title'] ?></div>
                <p style="margin:0;font-size:0.8rem;color:var(--muted);line-height:1.5;"><?= $f['detail'] ?></p>
            </div>
            <div style="font-size:0.75rem;color:var(--muted);white-space:nowrap;text-align:right;padding-top:0.1rem;">
                <?= h($f['env']) ?>
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

<script>document.addEventListener('DOMContentLoaded', () => { if (typeof lucide !== 'undefined') lucide.createIcons(); });</script>
<?php
render_layout('Development & Roadmap', ob_get_clean() ?: '');
