<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_min_role('editor');

$pdo = db();
ensure_schema($pdo);

$error       = null;
$title       = '';
$description = '';
$status      = 'draft';
$owner       = '';
$department  = '';
$capturedDate = '';
$version     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { redirect('/documents.php'); }
    $title        = trim((string) ($_POST['title']        ?? ''));
    $description  = trim((string) ($_POST['description']  ?? ''));
    $status       = valid_status((string) ($_POST['status'] ?? 'draft'));
    $owner        = trim((string) ($_POST['owner']        ?? ''));
    $department   = trim((string) ($_POST['department']   ?? ''));
    $capturedDate = trim((string) ($_POST['captured_date'] ?? ''));
    $version      = 'v1.0'; // always starts at v1.0; can be changed on the Edit page

    $laneTemplate = $_POST['lane_template'] ?? 'none';
    // Predefined lane sets: [name, color]
    $laneTemplates = [
        'housing'     => [['Tenant','#fff3e0'],['Customer First','#e8f5e9'],['Technical Officer','#e3f2fd']],
        'procurement' => [['Budget Holder','#fce4ec'],['Procurement','#e8eaf6'],['Finance','#e0f2f1'],['Supplier','#fff8e1']],
        'two'         => [['Team A','#e8f5e9'],['Team B','#e3f2fd']],
        'three'       => [['Lane 1','#fff3e0'],['Lane 2','#e8f5e9'],['Lane 3','#e3f2fd']],
    ];

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        try {
            $document = create_document($pdo, $title, $description, $status,
                                        $owner, $department, $capturedDate, $version);
            // Create starter lanes if a template was chosen
            if (isset($laneTemplates[$laneTemplate])) {
                foreach ($laneTemplates[$laneTemplate] as $i => [$laneName, $laneColor]) {
                    $pdo->prepare(
                        'INSERT INTO lanes (as_is_id, name, sort_order, color) VALUES (?, ?, ?, ?)'
                    )->execute([(int)$document['id'], $laneName, $i + 1, $laneColor]);
                }
            }
            redirect('/edit.php?slug=' . rawurlencode($document['slug']));
        } catch (Throwable $e) {
            $error = 'Could not create this map. Please check your details and try again.';
        }
    }
}

ob_start();
?>
<header>
    <div>
        <h1>New AS-IS</h1>
        <p>Create a new process map.</p>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice"><?= h($error) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= h($title) ?>" required placeholder="e.g. Customer First — Repairs Intake">
        </div>

        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Brief summary of what this process covers"><?= h($description) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div>
                <label for="owner">Owner</label>
                <input type="text" id="owner" name="owner" value="<?= h($owner) ?>" placeholder="Name or team">
            </div>
            <div>
                <label for="department">Department</label>
                <input type="text" id="department" name="department" value="<?= h($department) ?>" placeholder="e.g. Housing">
            </div>
            <div>
                <label for="captured_date">Date captured</label>
                <input type="date" id="captured_date" name="captured_date" value="<?= h($capturedDate) ?>">
            </div>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status" style="width:auto;">
                <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>

        <div>
            <label>Starter swimlanes</label>
            <p class="field-help" style="margin-bottom:0.75rem;">
                Choose a template to create lanes automatically — or add them manually on the next page.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:0.5rem;">
                <?php
                $templateOptions = [
                    'none'        => ['None — I\'ll add lanes manually',  '',        ''],
                    'housing'     => ['Housing repairs',                   'Tenant / Customer First / Technical Officer', '#fff3e0'],
                    'procurement' => ['Procurement',                       'Budget Holder / Procurement / Finance / Supplier', '#fce4ec'],
                    'two'         => ['Two teams',                         'Team A / Team B', '#e8f5e9'],
                    'three'       => ['Three teams',                       'Lane 1 / Lane 2 / Lane 3', '#e3f2fd'],
                ];
                $selectedTemplate = $_POST['lane_template'] ?? 'housing';
                foreach ($templateOptions as $val => [$label, $sublabel, $swatch]):
                ?>
                <label style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.6rem 0.75rem;
                              border:2px solid <?= $selectedTemplate === $val ? 'var(--accent)' : 'var(--border)' ?>;
                              border-radius:var(--r);cursor:pointer;background:var(--surface);
                              transition:border-color .15s;"
                       onmouseover="this.style.borderColor='var(--accent)'"
                       onmouseout="this.style.borderColor=document.querySelector('[name=lane_template]:checked')?.value==='<?= $val ?>'?'var(--accent)':'var(--border)'">
                    <input type="radio" name="lane_template" value="<?= $val ?>"
                           <?= $selectedTemplate === $val ? 'checked' : '' ?>
                           style="margin-top:0.2rem;accent-color:var(--accent);"
                           onchange="document.querySelectorAll('[data-tmpl]').forEach(e=>e.style.borderColor='var(--border)');this.closest('[data-tmpl]').style.borderColor='var(--accent)'">
                    <div>
                        <div style="font-weight:600;font-size:0.875rem;"><?= h($label) ?></div>
                        <?php if ($sublabel): ?>
                            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.1rem;"><?= h($sublabel) ?></div>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Create process map</button>
        </div>
    </form>
</div>
<?php
render_layout('New AS-IS', ob_get_clean() ?: '');
