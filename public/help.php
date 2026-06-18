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
            ['6', 'View &amp; share', 'Click <strong>View</strong> to see the horizontal swimlane map and the auto-generated flow diagram. Use the <strong>Print</strong> button to get a clean printable version.'],
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

<!-- ── Two-column reference cards ───────────────────────────────── -->
<div class="help-ref-grid">

    <!-- Step types -->
    <div class="card">
        <h2>Step types</h2>
        <p>Step type controls how the step appears in the diagram.</p>
        <table>
            <tbody>
                <tr>
                    <td style="width:100px;"><span class="badge badge-start">Start</span></td>
                    <td>The first step in the process — where it begins.</td>
                </tr>
                <tr>
                    <td><span class="badge badge-task">Task</span></td>
                    <td>A regular action performed by someone in the lane. This is the default.</td>
                </tr>
                <tr>
                    <td><span class="badge badge-decision">Decision</span></td>
                    <td>A yes/no or branching point. Give the outgoing connections labels like "Yes" and "No".</td>
                </tr>
                <tr>
                    <td><span class="badge badge-end">End</span></td>
                    <td style="border-bottom:none;">The final step — where the process concludes.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Action types -->
    <div class="card">
        <h2>Action types</h2>
        <p>Action type adds a small icon to the step card to show <em>what kind</em> of action it is at a glance.</p>
        <table>
            <tbody>
                <?php
                $icons = [
                    ['phone',        'Phone call',          'Someone makes or receives a telephone call.'],
                    ['email',        'Email',               'A formal email is sent or received.'],
                    ['letter',       'Letter / post',       'A formal letter or document is sent by post — statutory notices, formal correspondence.'],
                    ['notification', 'Notification / alert','An automated alert, text message, or system notification is triggered.'],
                    ['document',     'Document',            'A form, record, or document is created, completed, or used.'],
                    ['data-entry',   'Data entry',          'A person enters information into a system.'],
                    ['automated',    'Automated / system',  'The system performs this step automatically — no human action required.'],
                    ['api-call',     'API call',            'One system makes a programmatic call to another — a system-to-system integration point, often where delays or failures occur.'],
                    ['report',       'Report / record',     'A formal report or output record is produced at the end of a process step.'],
                    ['check',        'Check / review',      'Something is checked, verified, or quality-reviewed.'],
                    ['meeting',      'Meeting / approval',  'A discussion, sign-off, or formal approval is required.'],
                    ['payment',      'Payment',             'A financial transaction takes place — raising an invoice, processing payment, or issuing a refund.'],
                    ['visit',        'Visit / inspection',  'Someone travels to a physical location to carry out work, a survey, or an inspection.'],
                    ['wait',         'Wait / hold',         'The process pauses — waiting for a response, a date, or an action from elsewhere.'],
                    ['escalation',   'Escalation',          'The task or decision is passed to a more senior person or team.'],
                ];
                $last = end($icons)[0];
                foreach ($icons as [$type, $label, $desc]):
                ?>
                    <tr>
                        <td style="width:36px;text-align:center;font-size:1.1rem;"><?= action_type_icon($type) ?></td>
                        <td style="white-space:nowrap;font-weight:600;"><?= h($label) ?></td>
                        <td style="font-size:0.85rem;color:var(--muted);<?= $type === $last ? 'border-bottom:none;' : '' ?>"><?= h($desc) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
    </ul>
</div>

<?php
render_layout('How to use', ob_get_clean() ?: '');
