<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

ob_start();
?>
<section class="landing-hero">
    <div class="landing-hero-inner">
        <div class="landing-hero-copy">
            <p class="landing-eyebrow">AS-IS process mapping</p>
            <h1 class="landing-title">Capture how work really happens — then make it clear</h1>
            <p class="landing-lead">
                When staff are interviewed or teams come together, the result is often a dense diagram
                that is hard to read and harder to maintain. This site turns that captured knowledge
                into structured, editable process maps anyone can follow.
            </p>
            <div class="landing-hero-actions">
                <a class="btn btn-lg" href="/documents.php">Open your process maps</a>
                <a class="btn btn-secondary btn-lg" href="/view.php?slug=sample-customer-first">See an example</a>
            </div>
        </div>
        <figure class="landing-hero-visual">
            <img src="/images/hero-swimlanes.svg" alt="Illustration of a swimlane process map with connected steps across three teams" width="640" height="420">
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
        <figure class="landing-figure">
            <img src="/images/diagram-before.svg" alt="Abstract illustration of a cluttered, hard-to-follow process diagram" width="520" height="320" loading="lazy">
            <figcaption>Dense static diagrams are hard to read and painful to update.</figcaption>
        </figure>
    </div>
</section>

<section class="landing-section landing-section-alt">
    <div class="landing-section-inner landing-split landing-split-reverse">
        <figure class="landing-figure">
            <img src="/images/diagram-after.svg" alt="Abstract illustration of a clean structured process map with swimlanes" width="520" height="320" loading="lazy">
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
                <li><strong>Swimlanes</strong> — who owns each part of the process</li>
                <li><strong>Steps</strong> — numbered actions, decisions, starts and ends</li>
                <li><strong>Systems</strong> — shared library of software and tools</li>
                <li><strong>Connections</strong> — flow between steps, with labels like Yes / No</li>
                <li><strong>Live diagrams</strong> — auto-generated maps you can view and print</li>
            </ul>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="landing-section-inner">
        <h2 class="landing-h2 landing-center">Why I built this</h2>
        <p class="landing-center landing-intro">
            I kept seeing the same pattern at work: valuable knowledge gathered from staff, poured into
            Visio-style diagrams that became the only record of how things worked. They communicated
            complexity, but they did not make it easy to work with.
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
                <li>Build swimlanes, steps, system links, and connections</li>
                <li>View horizontal swimlane maps and auto-generated flow diagrams</li>
                <li>Browse a shared systems library used across all maps</li>
                <li>Load sample maps to explore before building your own</li>
            </ul>
        </div>
        <div class="landing-expect-aside">
            <h3>Good to know</h3>
            <p>New here? Start with the sample <a href="/view.php?slug=sample-customer-first">Customer First</a> or <a href="/view.php?slug=sample-purchase-to-pay">Purchase to Pay</a> examples.</p>
            <p>Ready to build? <a href="/help.php">Read the guidance</a> for a step-by-step walkthrough.</p>
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
