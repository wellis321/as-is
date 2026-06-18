<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$messages     = [];
$sampleLoaded = false;
$action       = $_POST['action'] ?? '';

if ($action === 'load_samples') {
    // Load sample documents only — database must already exist.
    try {
        $pdo = db();
        ensure_schema($pdo);
        run_sql_file($pdo, dirname(__DIR__) . '/sql/seed_samples.sql');
        $sampleLoaded = true;
        $messages[]   = 'Sample documents loaded. You can view them on the home page.';
    } catch (Throwable $e) {
        $messages[] = 'Could not load samples: ' . $e->getMessage();
    }
} else {
    // Full setup: create database, run schema, seed basic data.
    try {
        $config = db_config();
        ensure_database();

        $pdo = db();
        run_sql_file($pdo, dirname(__DIR__) . '/sql/schema.sql');
        ensure_schema($pdo);
        run_sql_file($pdo, dirname(__DIR__) . '/sql/seed.sql');
        $messages[] = 'Database `' . $config['database'] . '` is ready.';
    } catch (Throwable $e) {
        $messages[] = 'Setup failed: ' . $e->getMessage();
    }
}

ob_start();
?>
<header>
    <div>
        <h1>Setup</h1>
        <p>Database initialisation and sample data.</p>
    </div>
</header>

<?php foreach ($messages as $msg): ?>
    <div class="notice <?= $sampleLoaded ? 'notice-success' : '' ?>"><?= h($msg) ?></div>
<?php endforeach; ?>

<!-- ── Full setup ────────────────────────────────────────────────── -->
<div class="card">
    <h2>First-time setup</h2>
    <p>
        Creates the <code><?= h(db_config()['database'] ?? 'as_is') ?></code> database from your <code>.env</code>,
        runs the schema, and loads a minimal starter document.
        Safe to re-run — existing documents are not overwritten.
    </p>
    <form method="post" style="margin-top:0.5rem;">
        <button class="btn" type="submit">Run setup</button>
    </form>
</div>

<!-- ── Sample data ───────────────────────────────────────────────── -->
<div class="card" id="samples">
    <h2>Load sample documents</h2>
    <p>
        Adds two fully worked example AS-IS documents so you can explore the app before building your own:
    </p>
    <ul style="margin:0.5rem 0 1rem;padding-left:1.25rem;display:grid;gap:0.3rem;">
        <li>
            <strong>Customer First — Housing Repairs</strong>
            <span style="color:var(--muted);font-size:0.875rem;"> — 3 lanes, 21 steps, 4 systems, decision branches, parallel escalation paths</span>
        </li>
        <li>
            <strong>Purchase to Pay — Procurement</strong>
            <span style="color:var(--muted);font-size:0.875rem;"> — 4 lanes, 20 steps, 2 systems, approval loops, delivery dispute handling</span>
        </li>
    </ul>
    <p style="margin-bottom:1rem;">
        Safe to run multiple times — it replaces only these two sample documents and never touches your own work.
        Requires setup to have been run at least once first.
    </p>
    <form method="post">
        <input type="hidden" name="action" value="load_samples">
        <button class="btn btn-secondary" type="submit">Load sample documents</button>
    </form>
</div>

<?php if ($sampleLoaded): ?>
<div class="card">
    <p>The samples are ready to explore.</p>
    <div class="actions">
        <a class="btn" href="/view.php?slug=sample-customer-first">View Customer First</a>
        <a class="btn btn-secondary" href="/view.php?slug=sample-purchase-to-pay">View Purchase to Pay</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <a class="btn" href="/index.php">Open AS-IS list</a>
</div>
<?php endif; ?>
<?php
render_layout('Setup', ob_get_clean() ?: '');
