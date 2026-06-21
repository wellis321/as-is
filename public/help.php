<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

ob_start();
?>
<header>
    <div>
        <h1>How to use AS-IS Management</h1>
        <p>A step-by-step guide to building and reading process maps.</p>
    </div>
</header>

<!-- ── What is an AS-IS? ─────────────────────────────────────────── -->
<div class="card">
    <h2>What is an AS-IS document?</h2>
    <p>
        An <strong>AS-IS</strong> captures how a process currently works — not how it should work or might work in future.
        It is produced from interviews with staff, workshops, or direct observation, and shows every step,
        decision, handoff, and system involved.
    </p>
    <p>
        Traditional AS-IS diagrams are drawn in tools like Visio and quickly become large, hard-to-read images.
        This system stores the same information in a structured way so it is easy to edit, search, and share —
        and still generates a diagram automatically.
    </p>
    <p style="margin:0;">
        Each AS-IS document has <strong>swimlanes</strong> (the rows, representing teams or roles),
        <strong>steps</strong> (the individual actions), and <strong>connections</strong> (the arrows that show the flow between steps).
    </p>
</div>

<!-- ── Quick start ───────────────────────────────────────────────── -->
<div class="card">
    <h2>Quick start</h2>

    <div style="display:grid;gap:0;">
        <?php
        $quickSteps = [
            ['1', 'Create a new AS-IS',
             'Click <strong>+ New AS-IS</strong> in the navigation. Give it a title, description, and optional metadata. Before saving, choose a <strong>Starter swimlanes</strong> template — for example "Housing repairs" automatically creates Tenant, Customer First, and Technical Officer lanes. You land on the Edit page with those lanes already in place.'],
            ['2', 'Check or add swimlanes',
             'If you used a template your lanes are ready. Otherwise add them in the <em>Swimlanes</em> section of the Edit page — one lane per team or role. Use the <i data-lucide="chevron-up" class="licon"></i> <i data-lucide="chevron-down" class="licon"></i> arrows to reorder them and the colour picker to colour-code each band.'],
            ['3', 'Add systems &amp; tools',
             'List the software systems or tools used in this process (e.g. Liberty Converse, NEC, DRS). You can then attach them to individual steps so it is clear which system each action takes place in.'],
            ['4', 'Add steps',
             'Each step is one action in the process. The <strong>step number</strong> is pre-filled with the next available number. Choose the swimlane, title, <strong>step type</strong>, and <strong>action type</strong>. Tick any systems used. If you have similar steps, use the <strong>Clone</strong> button on an existing step to duplicate it and adjust the copy.'],
            ['5', 'Add connections',
             'Connections are the arrows that make the diagram meaningful — without them the steps are just a list. Go to the <em>Connections</em> section on the Edit page, pick a From and To step, add an optional label (e.g. "Yes", "No", "Routine"), and click Add connection. The Edit page shows a prompt when steps have been added but no connections exist yet.'],
            ['6', 'View &amp; share',
             'Click <strong>View</strong> to open the interactive swimlane diagram. Scroll to zoom, drag to pan, click <strong>Full screen</strong> for workshops, and click any step to see its full description and connections. Use the <strong>Print</strong> button for a clean PDF-ready version.'],
        ];
        foreach ($quickSteps as [$num, $stepTitle, $body]):
        ?>
            <div style="display:grid;grid-template-columns:48px 1fr;gap:0;border-bottom:1px solid var(--border);padding:1rem 0;">
                <div style="font-size:1.4rem;font-weight:700;color:var(--accent);padding-top:0.1rem;"><?= $num ?></div>
                <div>
                    <strong><?= $stepTitle ?></strong>
                    <p style="margin-top:0.3rem;margin-bottom:0;"><?= $body ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Building faster ───────────────────────────────────────────── -->
<div class="card">
    <h2>Building faster</h2>
    <p>Several shortcuts make building a process map quicker once you know about them.</p>

    <div style="display:grid;gap:0;">
        <?php
        $shortcuts = [
            [
                'Lane templates',
                'When you create a new AS-IS, the form includes a <strong>Starter swimlanes</strong> section. Choosing a template creates your lanes automatically when you save — no need to add them one by one on the Edit page. Available templates:',
                '<ul style="margin:0.4rem 0 0;padding-left:1.25rem;font-size:0.875rem;display:grid;gap:0.2rem;">
                    <li><strong>Housing repairs</strong> — Tenant / Customer First / Technical Officer</li>
                    <li><strong>Procurement</strong> — Budget Holder / Procurement / Finance / Supplier</li>
                    <li><strong>Two teams</strong> or <strong>Three teams</strong> — blank lanes to rename as you go</li>
                    <li><strong>None</strong> — add lanes manually on the Edit page</li>
                </ul>',
            ],
            [
                'Clone a step',
                'Each step row on the Edit page has a <strong>Clone</strong> button. Clicking it creates an exact copy of that step — same lane, same step type, same action type, same systems — with the next available step number and "Copy of [title]" as the name. You are taken straight to the edit form to adjust the title and anything else that differs. Useful for processes with many similar steps.',
                '',
            ],
            [
                'Auto-suggested step number',
                'When you add a new step, the step number field is pre-filled with the next number after your highest existing step. You can change it, but you no longer need to count manually.',
                '',
            ],
            [
                'Connection prompt',
                'If you have added steps but no connections yet, a prompt appears at the bottom of the steps table linking directly to the Connections form. Connections are easy to forget — they are what turn your list of steps into an actual flow diagram.',
                '',
            ],
        ];
        foreach ($shortcuts as $i => [$title, $body, $extra]):
        ?>
        <div style="display:grid;grid-template-columns:1fr;gap:0;
                    border-bottom:<?= $i < count($shortcuts)-1 ? '1px solid var(--border)' : 'none' ?>;
                    padding:1rem 0;">
            <strong style="font-size:0.9rem;"><?= $title ?></strong>
            <p style="margin:0.3rem 0 0;font-size:0.875rem;"><?= $body ?></p>
            <?= $extra ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── JSON export and import ─────────────────────────────────────── -->
<div class="card">
    <h2>Exporting and importing JSON</h2>
    <p>
        Any process map can be exported as a clean JSON file and re-imported to create a new diagram — useful for
        sharing maps with colleagues, moving between environments, or backing up your work.
    </p>

    <div style="display:grid;gap:0;">
        <?php
        $jsonSteps = [
            ['Export a diagram',
             'On the <strong>View</strong> page for any diagram, click <strong>Export JSON</strong>. A <code>.json</code> file will download containing the full document — title, metadata, lanes, steps, systems, and connections.'],
            ['Review and edit the JSON',
             'On the <strong>Import</strong> page, either upload the file or paste the JSON directly into the editor. The content appears in a text editor where you can review it, rename steps, change colours, or adjust connections before creating the diagram.'],
            ['Import to create a new diagram',
             'Click <strong>Import and create document</strong>. The system creates the document, lanes, steps, system links, and connections, then takes you straight to the Edit page to review the result.'],
        ];
        foreach ($jsonSteps as $i => [$title, $body]):
        ?>
        <div style="display:grid;grid-template-columns:48px 1fr;gap:0;border-bottom:<?= $i < count($jsonSteps)-1 ? '1px solid var(--border)' : 'none' ?>;padding:1rem 0;">
            <div style="font-size:1.4rem;font-weight:700;color:var(--accent);padding-top:0.1rem;"><?= $i + 1 ?></div>
            <div>
                <strong><?= $title ?></strong>
                <p style="margin-top:0.3rem;margin-bottom:0;"><?= $body ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p style="margin:1rem 0 0;font-size:0.875rem;color:var(--muted);">
        The JSON format is human-readable — you can open it in any text editor and modify it directly.
        The import page shows the expected structure if you want to create a JSON file from scratch rather than exporting one.
        Go to <a href="/import.php">Import JSON</a> to get started.
    </p>
</div>

<!-- ── Step types ────────────────────────────────────────────────── -->
<div class="card">
    <h2>Step types</h2>
    <p>Step type controls how the step appears in the diagram.</p>
    <?php
    $stepTypeItems = [
        ['Start',            'badge-start',  '',                                           'The first step in the process — where it begins. Shown as a pill shape with a green border.'],
        ['Task',             'badge-task',   '',                                           'A regular action performed by someone in the lane. The default for most steps.'],
        ['Decision',         'badge-decision','',                                          'A branching point — one path or another is taken. Give outgoing connections labels like "Yes" and "No".'],
        ['Subprocess',       '',             'background:#eff6ff;color:#1e3a8a;border-color:#2563eb;', 'A step that represents a whole separate process. Shown as a rectangle with a small <strong>+</strong> badge — indicating there is more detail to explore.'],
        ['Parallel gateway', '',             'background:#fdf4ff;color:#581c87;border-color:#9333ea;', 'Multiple things happen <em>simultaneously</em> — all paths run at once, not one-or-the-other. Shown as a diamond with a <strong>+</strong> inside.'],
        ['End',              'badge-end',    '',                                           'The final step — where the process concludes. Shown as a pill shape with a red border.'],
    ];
    ?>
    <div class="help-step-types-grid">
        <?php foreach ($stepTypeItems as [$label, $cls, $style, $desc]): ?>
        <div>
            <div style="margin-bottom:0.6rem;">
                <span class="badge <?= $cls ?>" style="display:block;text-align:center;padding:0.4rem 0.6rem;border-radius:0;font-size:0.85rem;font-weight:600;<?= $style ?>">
                    <?= h($label) ?>
                </span>
            </div>
            <p style="margin:0;font-size:0.875rem;"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Action types ──────────────────────────────────────────────── -->
<div class="card">
    <h2>Action types</h2>
    <p>Action type adds a small icon to the step card to show <em>what kind</em> of action it is at a glance.</p>
    <div class="help-action-types-grid">
        <?php
        $icons = [
            ['phone',        'Phone call',          'Someone makes or receives a telephone call.'],
            ['email',        'Email',               'A formal email is sent or received.'],
            ['letter',       'Letter / post',       'A formal letter or document is sent by post — statutory notices, formal correspondence.'],
            ['notification', 'Notification / alert','An automated alert, text message, or system notification is triggered.'],
            ['document',     'Document',            'A form, record, or document is created, completed, or used.'],
            ['data-entry',   'Data entry',          'A person enters information into a system.'],
            ['automated',    'Automated / system',  'The system performs this step automatically — no human action required.'],
            ['api-call',     'API call',            'One system makes a programmatic call to another — a system-to-system integration point.'],
            ['report',       'Report / record',     'A formal report or output record is produced at the end of a process step.'],
            ['check',        'Check / review',      'Something is checked, verified, or quality-reviewed.'],
            ['meeting',      'Meeting / approval',  'A discussion, sign-off, or formal approval is required.'],
            ['payment',      'Payment',             'A financial transaction takes place — raising an invoice, processing payment, or issuing a refund.'],
            ['visit',        'Visit / inspection',  'Someone travels to a physical location to carry out work, a survey, or an inspection.'],
            ['wait',         'Wait / hold',         'The process pauses — waiting for a response, a date, or an action from elsewhere.'],
            ['escalation',   'Escalation',          'The task or decision is passed to a more senior person or team.'],
        ];
        foreach ($icons as [$type, $label, $desc]):
        ?>
            <div class="help-action-types-item">
                <span class="hat-icon"><?= action_type_icon($type) ?></span>
                <div>
                    <strong><?= h($label) ?></strong>
                    <p><?= h($desc) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Connections explained ─────────────────────────────────────── -->
<div class="card">
    <h2>Understanding connections</h2>
    <p>
        Connections are the arrows between steps. They are the most important part of the AS-IS because they show
        <em>who hands off to whom</em>, <em>what happens at decisions</em>, and <em>where the process loops back</em>.
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div>
            <h3 style="margin:0 0 0.5rem;font-size:1rem;">Linear flow</h3>
            <p style="margin:0;">Add a connection from Step 1 → Step 2 → Step 3 with no label to show a straight sequence of actions.</p>
        </div>
        <div>
            <h3 style="margin:0 0 0.5rem;font-size:1rem;">Decision branches</h3>
            <p style="margin:0;">From a Decision step, add two connections with labels — e.g. "Yes" → Step 5 and "No" → Step 9. The diagram shows both paths.</p>
        </div>
        <div>
            <h3 style="margin:0 0 0.5rem;font-size:1rem;">Cross-lane handoffs</h3>
            <p style="margin:0;">Connect a step in the "Customer First" lane to a step in the "Technical Officer" lane to show the point of handoff.</p>
        </div>
        <div>
            <h3 style="margin:0 0 0.5rem;font-size:1rem;">Loops</h3>
            <p style="margin:0;">A step can connect back to an earlier step — for example, "if the issue is not resolved, return to Step 4".</p>
        </div>
    </div>
</div>

<!-- ── Reading the diagram ────────────────────────────────────────── -->
<div class="card">
    <h2>Reading and exploring the diagram</h2>
    <p>
        The process map viewer is interactive — it is designed to be explored, not just read top-to-bottom.
        Here is what you can do on any diagram.
    </p>

    <h3 style="margin:1.25rem 0 0.5rem;font-size:1rem;">Navigating the map</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div>
            <strong>Scroll to zoom</strong>
            <p style="margin:0.2rem 0 0;font-size:0.875rem;">Use your mouse wheel or trackpad scroll to zoom in and out, centred on the cursor position.</p>
        </div>
        <div>
            <strong>Drag to pan</strong>
            <p style="margin:0.2rem 0 0;font-size:0.875rem;">Click and drag anywhere on the diagram background to move around a large map.</p>
        </div>
        <div>
            <strong>Fit button</strong>
            <p style="margin:0.2rem 0 0;font-size:0.875rem;">Click <strong>Fit</strong> to reset the view so the whole diagram fits in the available space.</p>
        </div>
        <div>
            <strong>Full screen</strong>
            <p style="margin:0.2rem 0 0;font-size:0.875rem;">Click <strong>Full screen</strong> to expand the diagram to fill your entire screen — useful for workshops and walkthroughs.</p>
        </div>
    </div>

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Multi-row layout</h3>
    <p style="margin:0 0 0.5rem;">
        Steps within each swimlane wrap into multiple rows when there are more than seven steps in a lane.
        This means even a 20-step process fits on screen without horizontal scrolling.
        A subtle dashed line separates the rows within a lane.
    </p>

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Connection colours</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div style="display:flex;align-items:flex-start;gap:0.6rem;">
            <span style="display:inline-block;width:28px;height:3px;background:#64748b;flex-shrink:0;margin-top:0.55rem;"></span>
            <div><strong>Grey arrow</strong><p style="margin:0.15rem 0 0;font-size:0.875rem;">Flow within the same swimlane — one step follows directly from another in the same team.</p></div>
        </div>
        <div style="display:flex;align-items:flex-start;gap:0.6rem;">
            <span style="display:inline-block;width:28px;height:3px;background:#3b82f6;flex-shrink:0;margin-top:0.55rem;"></span>
            <div><strong>Blue arrow</strong><p style="margin:0.15rem 0 0;font-size:0.875rem;">A cross-lane handoff — work passes from one team or role to another.</p></div>
        </div>
        <div style="display:flex;align-items:flex-start;gap:0.6rem;">
            <span style="display:inline-block;width:28px;height:3px;background:#f59e0b;border-top:3px dashed #f59e0b;flex-shrink:0;margin-top:0.55rem;"></span>
            <div><strong>Amber dashed arrow</strong><p style="margin:0.15rem 0 0;font-size:0.875rem;">A loop-back — the process returns to an earlier step, e.g. "if rejected, return to step 3".</p></div>
        </div>
        <div style="display:flex;align-items:flex-start;gap:0.6rem;">
            <span style="display:inline-block;width:28px;height:3px;background:#64748b;flex-shrink:0;margin-top:0.55rem;"></span>
            <div><strong>L-shaped arrow</strong><p style="margin:0.15rem 0 0;font-size:0.875rem;">When the flow wraps to the next row within a lane, an L-shaped arrow traces the path along the right margin then down.</p></div>
        </div>
    </div>

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Decision branches on separate rows</h3>
    <p style="margin:0;">
        When a decision step has multiple outgoing paths, each branch is placed on its own row so you can
        see clearly which path leads where. The branch with the most steps continues on the same row
        as the main flow; exception paths drop to rows below. This mirrors how a process analyst
        would draw a swimlane diagram by hand.
    </p>

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Connection style</h3>
    <p style="margin:0;">
        Use the <strong>Straight</strong> / <strong>Curved</strong> button in the toolbar to switch
        between orthogonal (right-angle) connections and smooth bezier curves. Straight is the default —
        right-angle elbows are easier to follow on complex maps. Your preference is saved and remembered
        across sessions.
    </p>

    <div style="display:grid;grid-template-columns:1fr 190px;gap:2rem;align-items:start;margin-top:1.5rem;">
        <div>
        <h3 style="margin:0 0 0.5rem;font-size:1rem;">Clicking a step to focus</h3>
        <p style="margin:0 0 0.75rem;">
            Click any step in the diagram to enter <strong>focus mode</strong>. A panel appears alongside the diagram showing:
        </p>
        <ul style="margin:0;padding-left:1.25rem;display:grid;gap:0.45rem;font-size:0.875rem;">
            <li>The clicked step and its immediate connections stay vivid; everything else fades.</li>
            <li>The diagram scrolls and zooms to bring all related steps into view at once.</li>
            <li>A <strong>mini flow diagram</strong> at the top of the panel shows the related steps and connections in a compact schematic — numbered shapes with arrows, no labels.</li>
            <li>A <strong>card list</strong> below gives the full title, description, action type, and linked systems for each related step.</li>
            <li><strong>Click a different step</strong> while the panel is open — the panel updates in place without moving.</li>
            <li>Drag by the grip icon to reposition the panel if it covers something.</li>
            <li>Press &times;, click the same step, or click the background to close and restore the full view.</li>
        </ul>
        </div>

        <!-- Static illustration of the card panel — click to enlarge -->
        <figure style="margin:0;">
            <button onclick="document.getElementById('panel-lightbox').showModal()"
                    style="background:none;border:none;padding:0;cursor:zoom-in;display:block;width:100%;"
                    title="Click to enlarge">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 320" role="img"
                 aria-label="Example of the focus card panel — click to enlarge"
                 style="width:100%;height:auto;display:block;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.08);transition:box-shadow .15s;"
                 onmouseover="this.style.boxShadow='0 6px 24px rgba(0,0,0,.18)'"
                 onmouseout="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'">
                <!-- Panel background -->
                <rect width="260" height="320" rx="10" fill="white"/>

                <!-- Header -->
                <rect width="260" height="36" rx="10" fill="white"/>
                <rect y="28" width="260" height="8" fill="white"/>
                <rect y="36" width="260" height="1" fill="#e2e8f0"/>
                <!-- Grip icon (dots) -->
                <circle cx="14" cy="18" r="1.5" fill="#94a3b8"/>
                <circle cx="14" cy="24" r="1.5" fill="#94a3b8"/>
                <circle cx="20" cy="18" r="1.5" fill="#94a3b8"/>
                <circle cx="20" cy="24" r="1.5" fill="#94a3b8"/>
                <text x="30" y="22" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="700" fill="#64748b" letter-spacing="0.06em">3 STEPS IN FOCUS</text>
                <!-- Close button -->
                <line x1="246" y1="13" x2="252" y2="19" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
                <line x1="252" y1="13" x2="246" y2="19" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>

                <!-- Mini flow diagram area -->
                <rect y="37" width="260" height="96" fill="#f8fafc"/>
                <!-- Step boxes: 4, 5 (selected), 6 -->
                <rect x="30" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
                <text x="53" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">4</text>
                <!-- Arrow 4→5 -->
                <line x1="76" y1="71" x2="92" y2="71" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha)"/>
                <!-- Step 5 (clicked - bold border) -->
                <rect x="94" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#374151" stroke-width="2"/>
                <text x="117" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#1f2937">5</text>
                <!-- Arrow 5→6 -->
                <line x1="140" y1="71" x2="156" y2="71" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha)"/>
                <!-- Step 6 -->
                <rect x="158" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
                <text x="181" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">6</text>
                <!-- Downward arrow from 5 to 7 -->
                <line x1="117" y1="85" x2="117" y2="96" stroke="#9ca3af" stroke-width="1.5"/>
                <line x1="117" y1="96" x2="158" y2="96" stroke="#9ca3af" stroke-width="1.5"/>
                <line x1="158" y1="96" x2="158" y2="105" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha)"/>
                <!-- Step 7 -->
                <rect x="136" y="107" width="46" height="20" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
                <text x="159" y="120" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">7</text>

                <!-- Arrowhead marker -->
                <defs>
                    <marker id="ha" markerWidth="6" markerHeight="5" refX="5" refY="2.5" orient="auto">
                        <polygon points="0 0,6 2.5,0 5" fill="#9ca3af"/>
                    </marker>
                </defs>

                <!-- Divider below mini diagram -->
                <rect y="133" width="260" height="1" fill="#e2e8f0"/>

                <!-- Card 1 -->
                <rect y="134" width="260" height="56" fill="white"/>
                <rect x="12" y="148" width="18" height="18" rx="9" fill="#f1f5f9"/>
                <text x="21" y="161" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="#475569">4</text>
                <text x="38" y="150" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="600" fill="#1f2937">What type of request?</text>
                <text x="38" y="163" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Is this a new repair, update,</text>
                <text x="38" y="174" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">reschedule, or cancellation?</text>
                <rect y="190" width="260" height="1" fill="#e2e8f0"/>

                <!-- Card 2 (highlighted, selected step) -->
                <rect y="191" width="260" height="62" fill="#f8fafc"/>
                <rect x="12" y="206" width="18" height="18" rx="9" fill="#1f2937"/>
                <text x="21" y="219" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="white">5</text>
                <text x="38" y="208" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="700" fill="#1f2937">Check property access</text>
                <text x="38" y="221" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Confirm access arrangements</text>
                <text x="38" y="232" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">and any vulnerabilities.</text>
                <!-- System tag -->
                <rect x="38" y="239" width="38" height="11" rx="3" fill="#e0f2fe"/>
                <text x="57" y="248" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="7.5" fill="#0369a1">NEC Housing</text>
                <rect y="253" width="260" height="1" fill="#e2e8f0"/>

                <!-- Card 3 -->
                <rect y="254" width="260" height="56" fill="white"/>
                <rect x="12" y="268" width="18" height="18" rx="9" fill="#f1f5f9"/>
                <text x="21" y="281" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="#475569">6</text>
                <text x="38" y="270" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="600" fill="#1f2937">Create repair job on NEC</text>
                <text x="38" y="283" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Raise a new repair, assign the</text>
                <text x="38" y="294" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">trade and record description.</text>
                <rect y="310" width="260" height="1" fill="#e2e8f0"/>

                <!-- Scroll indicator -->
                <rect x="252" y="137" width="5" height="172" rx="2.5" fill="#e2e8f0"/>
                <rect x="252" y="137" width="5" height="60" rx="2.5" fill="#94a3b8"/>
            </svg>
            </button>
            <figcaption style="margin-top:0.5rem;font-size:0.78rem;color:var(--muted);text-align:center;">
                The card panel — click to enlarge
            </figcaption>
        </figure>
    </div>
</div>

<!-- Lightbox dialog for the card panel illustration -->
<dialog id="panel-lightbox"
        style="border:none;border-radius:12px;padding:0;background:transparent;
               box-shadow:0 24px 64px rgba(0,0,0,0.3);max-width:90vw;max-height:90vh;"
        onclick="this.close()">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 320" role="img"
         aria-label="Card panel illustration (enlarged)"
         style="width:min(480px,85vw);height:auto;display:block;border-radius:12px;background:white;">
        <!-- Panel background -->
        <rect width="260" height="320" rx="10" fill="white"/>
        <!-- Header -->
        <rect width="260" height="36" rx="10" fill="white"/>
        <rect y="28" width="260" height="8" fill="white"/>
        <rect y="36" width="260" height="1" fill="#e2e8f0"/>
        <circle cx="14" cy="18" r="1.5" fill="#94a3b8"/>
        <circle cx="14" cy="24" r="1.5" fill="#94a3b8"/>
        <circle cx="20" cy="18" r="1.5" fill="#94a3b8"/>
        <circle cx="20" cy="24" r="1.5" fill="#94a3b8"/>
        <text x="30" y="22" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="700" fill="#64748b" letter-spacing="0.06em">3 STEPS IN FOCUS</text>
        <line x1="246" y1="13" x2="252" y2="19" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="252" y1="13" x2="246" y2="19" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round"/>
        <!-- Mini flow diagram -->
        <rect y="37" width="260" height="96" fill="#f8fafc"/>
        <rect x="30" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
        <text x="53" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">4</text>
        <line x1="76" y1="71" x2="92" y2="71" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha2)"/>
        <rect x="94" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#374151" stroke-width="2"/>
        <text x="117" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#1f2937">5</text>
        <line x1="140" y1="71" x2="156" y2="71" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha2)"/>
        <rect x="158" y="57" width="46" height="28" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
        <text x="181" y="74" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">6</text>
        <line x1="117" y1="85" x2="117" y2="96" stroke="#9ca3af" stroke-width="1.5"/>
        <line x1="117" y1="96" x2="158" y2="96" stroke="#9ca3af" stroke-width="1.5"/>
        <line x1="158" y1="96" x2="158" y2="105" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#ha2)"/>
        <rect x="136" y="107" width="46" height="20" rx="3" fill="#f3f4f6" stroke="#9ca3af" stroke-width="1"/>
        <text x="159" y="120" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="10" font-weight="700" fill="#374151">7</text>
        <defs>
            <marker id="ha2" markerWidth="6" markerHeight="5" refX="5" refY="2.5" orient="auto">
                <polygon points="0 0,6 2.5,0 5" fill="#9ca3af"/>
            </marker>
        </defs>
        <!-- Cards -->
        <rect y="133" width="260" height="1" fill="#e2e8f0"/>
        <rect y="134" width="260" height="56" fill="white"/>
        <rect x="12" y="148" width="18" height="18" rx="9" fill="#f1f5f9"/>
        <text x="21" y="161" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="#475569">4</text>
        <text x="38" y="150" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="600" fill="#1f2937">What type of request?</text>
        <text x="38" y="163" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Is this a new repair, update,</text>
        <text x="38" y="174" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">reschedule, or cancellation?</text>
        <rect y="190" width="260" height="1" fill="#e2e8f0"/>
        <rect y="191" width="260" height="62" fill="#f8fafc"/>
        <rect x="12" y="206" width="18" height="18" rx="9" fill="#1f2937"/>
        <text x="21" y="219" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="white">5</text>
        <text x="38" y="208" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="700" fill="#1f2937">Check property access</text>
        <text x="38" y="221" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Confirm access arrangements</text>
        <text x="38" y="232" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">and any vulnerabilities.</text>
        <rect x="38" y="239" width="38" height="11" rx="3" fill="#e0f2fe"/>
        <text x="57" y="248" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="7.5" fill="#0369a1">NEC Housing</text>
        <rect y="253" width="260" height="1" fill="#e2e8f0"/>
        <rect y="254" width="260" height="56" fill="white"/>
        <rect x="12" y="268" width="18" height="18" rx="9" fill="#f1f5f9"/>
        <text x="21" y="281" text-anchor="middle" font-family="IBM Plex Sans,sans-serif" font-size="9" font-weight="700" fill="#475569">6</text>
        <text x="38" y="270" font-family="IBM Plex Sans,sans-serif" font-size="9.5" font-weight="600" fill="#1f2937">Create repair job on NEC</text>
        <text x="38" y="283" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">Raise a new repair, assign the</text>
        <text x="38" y="294" font-family="IBM Plex Sans,sans-serif" font-size="8.5" fill="#64748b">trade and record description.</text>
        <rect y="310" width="260" height="1" fill="#e2e8f0"/>
        <rect x="252" y="137" width="5" height="172" rx="2.5" fill="#e2e8f0"/>
        <rect x="252" y="137" width="5" height="60" rx="2.5" fill="#94a3b8"/>
    </svg>
    <p style="text-align:center;margin:0.75rem 0 0;font-size:0.8rem;color:rgba(255,255,255,0.7);">
        Click anywhere to close
    </p>
</dialog>
<style>
    #panel-lightbox::backdrop { background:rgba(0,0,0,0.6);backdrop-filter:blur(3px); }
</style>

<!-- ── Tips ──────────────────────────────────────────────────────── -->
<div class="card">
    <h2>Useful tips</h2>
    <ul style="margin:0;padding-left:1.25rem;display:grid;gap:0.5rem;">
        <li>Step numbers do not need to be consecutive — you can use the same numbering as an existing Visio or printed diagram to make it easy to cross-reference.</li>
        <li>Keep step titles short (under 10 words). Use the Description field for detail.</li>
        <li>Add systems to a document first (in the <em>Systems &amp; tools</em> section of the Edit page) before assigning them to steps.</li>
        <li>The flow diagram is only generated from connections you have added. If the diagram looks empty, go to the Edit page and add connections between steps.</li>
        <li>The <strong>Print</strong> button on the View page opens a clean, navigation-free version that your browser can print or save as PDF.</li>
        <li>Status <em>Draft</em> means work in progress; <em>Published</em> means it has been signed off and is the current agreed picture.</li>
        <li>Use <strong>Click to focus</strong> during workshops — clicking a step centres the conversation on it, auto-scrolls to show all related steps, and gives everyone the description and connections at a glance.</li>
        <li>While the card panel is open, click any other step to switch focus instantly — the panel stays in place and updates with the new content. You don't need to close and reopen it.</li>
        <li>If the card panel is in the way, drag it by the grip icon in its header to move it anywhere on the screen.</li>
        <li>Switch between <strong>Straight</strong> and <strong>Curved</strong> connections using the toolbar button — straight right-angle lines are often easier to follow on complex branching diagrams.</li>
        <li>The <a href="/view.php?slug=sample-repair-quick">Housing Repair — Quick View</a> sample is the shortest example and shows all the key features: focus mode, cross-lane handoffs, multi-row layout, and both <strong>Subprocess</strong> and <strong>Parallel gateway</strong> step types.</li>
        <li>Use the <strong>Lane templates</strong> on the New AS-IS page to skip the lane setup step entirely for common process structures.</li>
        <li>The <strong>Clone</strong> button on any step creates a duplicate you can adjust — faster than re-entering everything for similar steps.</li>
    </ul>
</div>

<?php
render_layout('How to use', ob_get_clean() ?: '');
