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
            ['1', 'Create a new AS-IS', 'Click <strong>+ New AS-IS</strong> in the navigation (or go to <strong>Process maps</strong>). Give it a title, a description, and optional metadata (owner, department, date, version). Save it — you will land on the Edit page.'],
            ['2', 'Add swimlanes', 'Swimlanes represent the <em>people or teams</em> involved in the process — for example "Tenant", "Customer First", "Technical Officer". Add one lane per actor. Use the colour picker to colour-code them. Use the ↑ ↓ arrows to put them in the right order.'],
            ['3', 'Add systems &amp; tools', 'List the software systems or tools used in this process (e.g. Liberty Converse, NEC, DRS). You can then attach them to individual steps so it is clear which system each action takes place in.'],
            ['4', 'Add steps', 'Each step is one action in the process. Give it a step number (use the same numbering as your source diagram if you have one), choose which swimlane it belongs to, give it a title, and pick a <strong>step type</strong> and an <strong>action type</strong>. Tick any systems used at that step.'],
            ['5', 'Add connections', 'Connections are the flow arrows between steps — they are what makes the diagram meaningful. In the <em>Connections</em> section on the Edit page, pick a From step and a To step, add an optional label (e.g. "Yes", "No", "New job"), and click Add connection.'],
            ['6', 'View &amp; share', 'Click <strong>View</strong> to open the interactive swimlane diagram. Scroll to zoom, drag to pan, and click any step to explore its connections. Use the <strong>Print</strong> button for a clean printable version.'],
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

<!-- ── Step types ────────────────────────────────────────────────── -->
<div class="card">
    <h2>Step types</h2>
    <p>Step type controls how the step appears in the diagram.</p>
    <div class="help-step-types-grid">
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge badge-start">Start</span></div>
            <p style="margin:0;font-size:0.875rem;">The first step in the process — where it begins. Shown as a pill shape with a green border.</p>
        </div>
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge badge-task">Task</span></div>
            <p style="margin:0;font-size:0.875rem;">A regular action performed by someone in the lane. The default for most steps.</p>
        </div>
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge badge-decision">Decision</span></div>
            <p style="margin:0;font-size:0.875rem;">A branching point — one path or another is taken. Give outgoing connections labels like "Yes" and "No".</p>
        </div>
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge" style="background:#eff6ff;color:#1e3a8a;border-color:#2563eb;">Subprocess</span></div>
            <p style="margin:0;font-size:0.875rem;">A step that represents a whole separate process. Shown as a rectangle with a small <strong>+</strong> badge — indicating there is more detail to explore. Use this when a single step is itself too complex to show in-line.</p>
        </div>
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge" style="background:#fdf4ff;color:#581c87;border-color:#9333ea;">Parallel gateway</span></div>
            <p style="margin:0;font-size:0.875rem;">Multiple things happen <em>simultaneously</em> — all outgoing paths run at the same time, not one-or-the-other. Shown as a diamond with a <strong>+</strong> inside. Use this for automated system actions running in parallel with human steps.</p>
        </div>
        <div>
            <div style="margin-bottom:0.5rem;"><span class="badge badge-end">End</span></div>
            <p style="margin:0;font-size:0.875rem;">The final step — where the process concludes. Shown as a pill shape with a red border.</p>
        </div>
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

    <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem;">Clicking a step to focus</h3>
    <p style="margin:0 0 0.75rem;">
        Click any step in the diagram to enter <strong>focus mode</strong>:
    </p>
    <ul style="margin:0;padding-left:1.25rem;display:grid;gap:0.4rem;font-size:0.9rem;">
        <li>The clicked step and its immediate connections stay vivid.</li>
        <li>Everything else fades to grey, making the local flow much easier to read.</li>
        <li>A card panel appears showing the full description, action type, and linked systems for every step in focus.</li>
        <li>The panel opens in the corner furthest from your click — if it still covers something, <strong>drag it by the header</strong> to move it anywhere on the diagram.</li>
        <li>Scroll inside the card panel to read longer descriptions; this scrolls the list, not the diagram.</li>
        <li>Click the same step again, press &times; on the panel, or click the diagram background to clear the focus and restore the full view.</li>
    </ul>
</div>

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
        <li>Use <strong>Click to focus</strong> during workshops — clicking a step centres the conversation on exactly what that step does and what comes before and after it.</li>
        <li>If the step card panel is in the way after clicking, drag it by its header (the ⠿⠿ icon) to move it to a clear area of the diagram.</li>
        <li>The <a href="/view.php?slug=sample-repair-quick">Housing Repair — Quick View</a> sample is the simplest example to try first — it shows focus mode, cross-lane handoffs, and the multi-row layout clearly.</li>
    </ul>
</div>

<?php
render_layout('How to use', ob_get_clean() ?: '');
