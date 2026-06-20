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
                <h3>Multi-row layout</h3>
                <p>Steps wrap within each swimlane — a 21-step process fits on screen without endless horizontal scrolling. Lanes grow to fit their content.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Click any step to focus</h3>
                <p>Click a step to highlight it and its immediate connections. Everything else fades to grey. A card panel shows the full description of every related step — drag it out of the way if needed.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Zoom, pan and navigate</h3>
                <p>Scroll to zoom, drag to pan, or hit <strong>Fit</strong> to see the whole picture. <strong>Full screen</strong> gives the most space to explore a detailed map.</p>
            </article>
        </div>
        <div class="landing-features" style="margin-top:1.5rem;">
            <article class="landing-feature-card">
                <h3>Colour-coded connection types</h3>
                <p>Grey arrows stay within a lane. Blue arrows show handoffs between teams. Amber dashed arrows show loop-backs. Each is visually distinct so you can trace the flow at a glance.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Step detail on demand</h3>
                <p>Clicking a step shows a card list of all steps in focus — including their description, action type icon, and any linked systems. No need to open a separate edit screen.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Print-ready</h3>
                <p>The <strong>Print</strong> button opens a clean, navigation-free version your browser can print or save as a PDF — useful for workshops and sign-off sessions.</p>
            </article>
        </div>
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
                <h3>From interviews to structure</h3>
                <p>Workshops and conversations still matter. This does not replace them — it gives what you learn a proper home.</p>
            </article>
            <article class="landing-feature-card">
                <h3>Built for communication</h3>
                <p>The goal is the same as a classic AS-IS: help people understand a complex thing. The difference is you can change it without redrawing everything.</p>
            </article>
            <article class="landing-feature-card">
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
                <li>Create and edit AS-IS documents with metadata (owner, department, version)</li>
                <li>Build colour-coded swimlanes, steps, system links, and connections</li>
                <li>View interactive swimlane diagrams with multi-row layout — no horizontal scrolling through 20 steps</li>
                <li>Click any step to focus on it and its connections — see descriptions and linked systems instantly</li>
                <li>Zoom, pan, and go full screen to explore even the most complex maps</li>
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
