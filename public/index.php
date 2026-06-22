<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/landing-illustrations.php';

ob_start();
?>
<section class="landing-hero">
    <div class="landing-hero-inner">
        <div class="landing-hero-copy">
            <p class="landing-eyebrow">AS-IS process mapping</p>
            <h1 class="landing-title">Capture how work really happens — then make it clear</h1>
            <p class="landing-lead">
                An AS-IS should show how work really flows — the people, the systems, and what happens next.
                Instead of a dense diagram that is hard to read and update, this site gives you structured,
                editable process maps anyone can follow.
            </p>
            <div class="landing-hero-actions">
                <a class="btn btn-lg" href="/documents.php">Open your process maps</a>
                <a class="btn btn-secondary btn-lg" href="/view.php?slug=sample-repair-quick">See a quick example</a>
            </div>
        </div>
        <figure class="landing-hero-visual landing-illustration" aria-hidden="false">
            <?= landing_illustration_hero() ?>
        </figure>
    </div>
</section>

<section class="landing-section">
    <div class="landing-section-inner landing-split">
        <div>
            <h2 class="landing-h2">The problem with traditional AS-IS diagrams</h2>
            <p>
                In many organisations, an <strong>AS-IS</strong> is the diagram that shows how a system
                or process works <em>today</em> — who does what, which systems are involved, and what
                happens next. They are usually built after workshops or interviews, then exported as
                a single large image.
            </p>
            <p>
                That works once. But when something changes — a new system, a new team, a tweaked handoff —
                the diagram becomes outdated, cluttered, and difficult for new people to understand.
            </p>
        </div>
        <figure class="landing-figure landing-illustration">
            <?= landing_illustration_before() ?>
            <figcaption>Dense static diagrams are hard to read and painful to update.</figcaption>
        </figure>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner landing-split landing-split-reverse">
        <figure class="landing-figure landing-illustration">
            <?= landing_illustration_after() ?>
            <figcaption>Structured data generates a clearer map — and stays editable.</figcaption>
        </figure>
        <div>
            <h2 class="landing-h2">What this tool does</h2>
            <p>
                Instead of drawing boxes and arrows by hand, you describe the process in plain terms:
                swimlanes for teams and roles, steps for actions, systems for the tools involved,
                and connections for what happens next.
            </p>
            <ul class="landing-list">
                <li><strong>Swimlanes</strong> — colour-coded bands, one per team or role</li>
                <li><strong>Steps</strong> — numbered actions, decisions, starts and ends</li>
                <li><strong>Systems</strong> — shared library of software and tools used across maps</li>
                <li><strong>Connections</strong> — flow arrows between steps, with labels like Yes / No</li>
                <li><strong>Live diagrams</strong> — auto-generated, interactive maps you can explore and print</li>
            </ul>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">Action types for every step</h2>
        <p class="landing-center landing-intro">
            When you add a step to a process map, you can tag it with an action type.
            Each one adds an icon to the diagram so people can see at a glance whether
            someone is calling, entering data, waiting, escalating, and so on.
        </p>
        <div class="landing-action-grid">
            <?php
            $descriptions = action_type_descriptions();
            foreach (action_type_options() as $type => $label):
                if ($type === 'general') {
                    continue;
                }
            ?>
                <article class="landing-action-card">
                    <div class="landing-action-icon" aria-hidden="true">
                        <?= action_type_icon($type) ?>
                    </div>
                    <div>
                        <h3><?= h($label) ?></h3>
                        <p><?= h($descriptions[$type] ?? '') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="landing-center" style="margin-top:1.5rem;margin-bottom:0;font-size:0.875rem;">
            Plus <strong>General task</strong> for steps that do not need a specific icon.
            Step types <strong>Start</strong>, <strong>Task</strong>, <strong>Decision</strong>, and <strong>End</strong>
            shape how each step appears in the flow.
        </p>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">An interactive diagram viewer</h2>
        <p class="landing-center landing-intro">
            The process map is built to be explored, not just printed. Steps wrap into rows within
            each swimlane so complex processes stay readable, and every step is interactive.
        </p>
        <div class="landing-features">
            <article class="landing-feature-card">
                <i data-lucide="layout-grid" class="lfc-icon"></i>
                <h3>Multi-row layout</h3>
                <p>Steps wrap within each swimlane — a 21-step process fits on screen without endless horizontal scrolling. Lanes grow to fit their content.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="crosshair" class="lfc-icon"></i>
                <h3>Click any step to focus</h3>
                <p>Click a step to highlight it and its connections. The diagram scrolls to show all related steps. A card panel opens with a mini flow diagram and full descriptions — click another step to switch without closing.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="zoom-in" class="lfc-icon"></i>
                <h3>Zoom, pan and navigate</h3>
                <p>Scroll to zoom, drag to pan, or hit <strong>Fit</strong> to see the whole picture. <strong>Full screen</strong> gives the most space to explore a detailed map.</p>
            </article>
        </div>
        <div class="landing-features" style="margin-top:1.5rem;">
            <article class="landing-feature-card">
                <i data-lucide="git-branch" class="lfc-icon"></i>
                <h3>Clear connections and decision branches</h3>
                <p>Grey same-lane arrows, blue cross-lane handoffs, amber loop-backs. Decision branches display on separate rows so each path is visually distinct. Toggle between straight and curved connections in the toolbar.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="panel-right" class="lfc-icon"></i>
                <h3>Step detail on demand</h3>
                <p>A panel opens showing a compact mini diagram of the related steps, then a card for each one — title, description, action type, and linked systems. Click any other step to switch instantly. See an example on the <a href="/help.php#focus">Guidance page</a>.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="file-json" class="lfc-icon"></i>
                <h3>Export and import JSON</h3>
                <p>Download any diagram as a clean JSON file. Review and edit it, then import it to create a new diagram instantly — on the same system or a different one.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="printer" class="lfc-icon"></i>
                <h3>Print-ready</h3>
                <p>The <strong>Print</strong> button opens a clean, navigation-free version your browser can print or save as a PDF — useful for workshops and sign-off sessions.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="maximize" class="lfc-icon"></i>
                <h3>Full screen for workshops</h3>
                <p>Expand any diagram to fill the entire screen. An exit button appears inside the diagram so you can leave full screen without hunting for controls — ideal for presenting in a meeting room.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="move-right" class="lfc-icon"></i>
                <h3>Straight or curved connections</h3>
                <p>Toggle between right-angle and smooth bezier connections in the toolbar. Straight lines are easier to follow on complex branching diagrams. Your preference is saved between sessions.</p>
            </article>
        </div>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">Build a diagram from a description</h2>
        <p class="landing-center landing-intro">
            You do not have to build a process map step by step. Describe what happens in plain language —
            who does what and in what order — and AI will read it, identify the swimlanes, create all the
            steps, and wire up the connections in one go.
        </p>
        <div class="landing-features">
            <article class="landing-feature-card">
                <i data-lucide="sparkles" class="lfc-icon"></i>
                <h3>Describe, generate, review</h3>
                <p>Open the <strong>Build diagram from description</strong> panel on the Edit page, type or paste a plain-English description, and click Generate. AI suggests lanes, steps, and connections. Review the preview, then click Create to build it all at once.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="wand-2" class="lfc-icon"></i>
                <h3>Refine an existing diagram</h3>
                <p>Once a diagram has steps, the <strong>Refine diagram with AI</strong> panel appears. Describe the change you want — "add a decision after step 3 if the repair is specialist" — and AI suggests what to add, what connections to rewire, and applies everything in one click.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="cpu" class="lfc-icon"></i>
                <h3>Flexible AI source</h3>
                <p>Add a free <strong>Groq</strong> or <strong>Gemini</strong> API key in AI settings for fast cloud generation with no local setup needed. Or run <strong>Ollama</strong> locally for fully offline use. The system automatically uses whichever is configured.</p>
            </article>
        </div>
        <p class="landing-center" style="margin-top:1.5rem;font-size:0.875rem;margin-bottom:0;">
            AI is an accelerator, not a replacement for the conversation. The description you give it
            is still grounded in what you learned from staff — it just removes the manual step-by-step data entry.
            Review the suggestions before applying them.
        </p>
    </div>
</section>

<section class="landing-section">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">Why I built this</h2>
        <p class="landing-center landing-intro">
            I kept seeing the same pattern at work: valuable knowledge gathered from staff, poured into
            Visio-style diagrams that became the only record of how things worked — static files that were
            slow to change and awkward to explore once the detail grew.
        </p>
        <div class="landing-features">
            <article class="landing-feature-card">
                <i data-lucide="users" class="lfc-icon"></i>
                <h3>From interviews to structure</h3>
                <p>Workshops and conversations still matter. This does not replace them — it gives what you learn a proper home.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="share-2" class="lfc-icon"></i>
                <h3>Built for communication</h3>
                <p>The goal is the same as a classic AS-IS: help people understand a complex thing. The difference is you can change it without redrawing everything.</p>
            </article>
            <article class="landing-feature-card">
                <i data-lucide="book-open" class="lfc-icon"></i>
                <h3>A living record</h3>
                <p>Publish maps when they are ready, edit them when reality shifts, and share links instead of emailing PDFs.</p>
            </article>
        </div>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner landing-expect">
        <div>
            <h2 class="landing-h2">What to expect</h2>
            <p>This is a working management system, not just a diagram viewer. Here is what you can do today:</p>
            <ul class="landing-checklist">
                <li>Describe a process in plain language and let AI generate the swimlanes, steps, and connections in one go — no manual step-by-step entry</li>
                <li>Refine an existing diagram by describing what to add or change — AI suggests the edits and applies them with a single click</li>
                <li>Connect Groq (free, no credit card) or Gemini for fast cloud generation, or run Ollama locally for fully offline use</li>
                <li>Export any diagram as a JSON file and re-import it elsewhere — share a process map with a colleague in a single file</li>
                <li>Create a new map with a lane template — Housing repairs, Procurement, or your own — so lanes are ready instantly</li>
                <li>Add steps with the next number pre-filled, clone similar steps in one click, and get prompted when connections are missing</li>
                <li>Build colour-coded swimlanes with six step types including <strong>Subprocess</strong> and <strong>Parallel gateway</strong></li>
                <li>View interactive diagrams with multi-row layout — even a 21-step process fits on screen</li>
                <li>Click any step to focus — diagram scrolls to show related steps, mini flow diagram appears, clicking another step updates the panel in place</li>
                <li>Decision branches display on their own rows so each path reads clearly</li>
                <li>Zoom, pan, go full screen, and switch between straight and curved connections</li>
                <li>Browse a shared systems library used across all maps</li>
                <li>Load sample maps to explore before building your own</li>
            </ul>
        </div>
        <div class="landing-expect-aside">
            <h3>Good to know</h3>
            <p>New here? The <a href="/view.php?slug=sample-repair-quick">Housing Repair — Quick View</a> is the shortest example and shows the diagram features clearly.</p>
            <p>For a fuller picture, try <a href="/view.php?slug=sample-customer-first">Customer First</a> (21 steps, 3 lanes) or <a href="/view.php?slug=sample-purchase-to-pay">Purchase to Pay</a> (20 steps, 4 lanes).</p>
            <p>Ready to build? <a href="/help.php">Read the guidance</a> for a step-by-step walkthrough including how to read the diagram.</p>
            <p style="margin-bottom:0;">This will keep growing — the aim is a practical tool for teams who live with real processes, not polished slide-deck fiction.</p>
        </div>
    </div>
</section>

<section class="landing-cta">
    <div class="landing-cta-inner">
        <h2 class="landing-cta-title">Start mapping how things work today</h2>
        <p>Open your process maps, try the examples, or create something new.</p>
        <div class="landing-hero-actions">
            <a class="btn btn-lg btn-on-dark" href="/documents.php">Your process maps</a>
            <a class="btn btn-secondary btn-lg btn-on-dark-outline" href="/new.php">Create an AS-IS</a>
            <a class="btn btn-secondary btn-lg btn-on-dark-outline" href="/view.php?slug=sample-purchase-to-pay">Another example</a>
        </div>
    </div>
</section>
<?php
render_layout('Home', ob_get_clean() ?: '', ['landing' => true]);
