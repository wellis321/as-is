<?php

declare(strict_types=1);

// ── Output escaping ───────────────────────────────────────────────────────────

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return hash_equals(csrf_token(), $token);
}

// ── Flash messages ────────────────────────────────────────────────────────────

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = match ($f['type']) {
            'success' => 'flash-success',
            'error'   => 'flash-error',
            default   => 'flash-info',
        };
        $out .= '<div class="flash ' . $cls . '">' . h($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);

    return $out;
}

function asset_url(string $path): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim($base, '/');

    if ($base === '' || $base === '.') {
        return '/' . ltrim($path, '/');
    }

    return $base . '/' . ltrim($path, '/');
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-') ?: 'as-is';
}

// ── Schema migrations ─────────────────────────────────────────────────────────

function ensure_slug_column(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM as_is_documents LIKE 'slug'")->fetch();
    if ($column) {
        return;
    }

    $pdo->exec('ALTER TABLE as_is_documents ADD COLUMN slug VARCHAR(255) NULL AFTER title');
    $pdo->exec('ALTER TABLE as_is_documents ADD UNIQUE KEY uniq_as_is_slug (slug)');

    $documents = $pdo->query('SELECT id, title, slug FROM as_is_documents')->fetchAll();
    foreach ($documents as $document) {
        if (!empty($document['slug'])) {
            continue;
        }

        $slug = slugify($document['title']);
        $candidate = $slug;
        $suffix = 2;

        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM as_is_documents WHERE slug = ? AND id <> ?');
            $stmt->execute([$candidate, $document['id']]);
            if (!$stmt->fetch()) {
                break;
            }
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        $update = $pdo->prepare('UPDATE as_is_documents SET slug = ? WHERE id = ?');
        $update->execute([$candidate, $document['id']]);
    }

    $pdo->exec('ALTER TABLE as_is_documents MODIFY slug VARCHAR(255) NOT NULL');
}

function ensure_global_systems(PDO $pdo): void
{
    $col = $pdo->query("SHOW COLUMNS FROM systems LIKE 'as_is_id'")->fetch();
    if (!$col) {
        return; // Already on global schema — nothing to do.
    }

    // Deduplicate: keep the lowest ID per unique (case-insensitive) name.
    $all = $pdo->query('SELECT id, name FROM systems ORDER BY id ASC')->fetchAll();
    $canonical = []; // lower-name → canonical id
    $remap     = []; // old id → canonical id

    foreach ($all as $row) {
        $key = strtolower(trim($row['name']));
        if (!isset($canonical[$key])) {
            $canonical[$key] = (int) $row['id'];
        }
        $remap[(int) $row['id']] = $canonical[$key];
    }

    // Re-point step_systems to canonical IDs then delete duplicates.
    foreach ($remap as $oldId => $newId) {
        if ($oldId === $newId) {
            continue;
        }
        $pdo->prepare(
            'INSERT IGNORE INTO step_systems (step_id, system_id)
             SELECT step_id, ? FROM step_systems WHERE system_id = ?'
        )->execute([$newId, $oldId]);
        $pdo->prepare('DELETE FROM step_systems WHERE system_id = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM systems WHERE id = ?')->execute([$oldId]);
    }

    // Drop the FK and as_is_id column.
    $fk = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'systems'
           AND COLUMN_NAME = 'as_is_id' AND REFERENCED_TABLE_NAME = 'as_is_documents'"
    )->fetch();
    if ($fk) {
        $pdo->exec('ALTER TABLE systems DROP FOREIGN KEY ' . $fk['CONSTRAINT_NAME']);
    }
    $pdo->exec('ALTER TABLE systems DROP COLUMN as_is_id');

    // Add unique constraint and created_at if not already present.
    $idx = $pdo->query("SHOW INDEX FROM systems WHERE Key_name = 'uniq_system_name'")->fetch();
    if (!$idx) {
        $pdo->exec('ALTER TABLE systems ADD UNIQUE KEY uniq_system_name (name)');
    }
    $ts = $pdo->query("SHOW COLUMNS FROM systems LIKE 'created_at'")->fetch();
    if (!$ts) {
        $pdo->exec("ALTER TABLE systems ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

// Adds the extended metadata columns to systems (category, hosting, vendor,
// owner, contact). Safe to call on every request — skips if already present.
function ensure_system_metadata(PDO $pdo): void
{
    $existing = array_column(
        $pdo->query('SHOW COLUMNS FROM systems')->fetchAll(),
        'Field'
    );

    $add = [];
    if (!in_array('category', $existing, true)) $add[] = "ADD COLUMN category VARCHAR(100)  NULL AFTER description";
    if (!in_array('hosting',  $existing, true)) $add[] = "ADD COLUMN hosting  ENUM('saas','cloud','on-premise','hybrid','unknown') NOT NULL DEFAULT 'unknown' AFTER category";
    if (!in_array('vendor',   $existing, true)) $add[] = "ADD COLUMN vendor   VARCHAR(255)   NULL AFTER hosting";
    if (!in_array('owner',    $existing, true)) $add[] = "ADD COLUMN owner    VARCHAR(255)   NULL AFTER vendor";
    if (!in_array('contact',  $existing, true)) $add[] = "ADD COLUMN contact  VARCHAR(255)   NULL AFTER owner";

    if ($add !== []) {
        $pdo->exec('ALTER TABLE systems ' . implode(', ', $add));
    }
}

// Single entry point for all incremental schema migrations.
// Call this instead of the individual ensure_* functions.
function ensure_auth_tables(PDO $pdo): void
{
    $users = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($users) {
        return;
    }

    $sqlPath = dirname(__DIR__) . '/sql/migrate_auth.sql';
    if (is_readable($sqlPath)) {
        run_sql_file($pdo, $sqlPath);
    }

    $adminUser = env('ADMIN_USER', '');
    $adminPass = env('ADMIN_PASS', '');
    if ($adminUser !== '' && $adminPass !== '') {
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $pdo->prepare(
            'INSERT INTO users (username, password_hash, auth_provider, app_role, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), app_role = VALUES(app_role), is_active = 1'
        )->execute([$adminUser, $hash, 'local', 'admin']);
    }
}

function ensure_schema(PDO $pdo): void
{
    ensure_auth_tables($pdo);
    ensure_slug_column($pdo);
    ensure_v2_columns($pdo);
    ensure_global_systems($pdo);
    ensure_system_metadata($pdo);
}

function ensure_v2_columns(PDO $pdo): void
{
    $col = $pdo->query("SHOW COLUMNS FROM steps LIKE 'action_type'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE steps ADD COLUMN action_type VARCHAR(30) NOT NULL DEFAULT 'general' AFTER step_type");
    }

    $col = $pdo->query("SHOW COLUMNS FROM as_is_documents LIKE 'owner'")->fetch();
    if (!$col) {
        $pdo->exec(
            "ALTER TABLE as_is_documents
                ADD COLUMN owner VARCHAR(255) NULL AFTER description,
                ADD COLUMN department VARCHAR(255) NULL AFTER owner,
                ADD COLUMN captured_date DATE NULL AFTER department,
                ADD COLUMN version VARCHAR(30) NULL AFTER captured_date"
        );
    }
}

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read SQL file: ' . $path);
    }

    // Strip -- line comments so semicolons inside comment text don't cause
    // incorrect splitting.
    $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

// ── Documents ─────────────────────────────────────────────────────────────────

function fetch_documents(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT d.*, COUNT(s.id) AS step_count
         FROM as_is_documents d
         LEFT JOIN steps s ON s.as_is_id = d.id
         GROUP BY d.id
         ORDER BY d.updated_at DESC'
    );

    return $stmt->fetchAll();
}

function fetch_document(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM as_is_documents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function fetch_document_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM as_is_documents WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function unique_slug(PDO $pdo, string $base, ?int $excludeId = null): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $suffix = 2;

    while (true) {
        $sql = 'SELECT id FROM as_is_documents WHERE slug = ?';
        $params = [$candidate];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

function create_document(
    PDO $pdo,
    string $title,
    string $description,
    string $status = 'draft',
    string $owner = '',
    string $department = '',
    string $capturedDate = '',
    string $version = ''
): array {
    $slug = unique_slug($pdo, $title);
    $stmt = $pdo->prepare(
        'INSERT INTO as_is_documents (title, slug, description, status, owner, department, captured_date, version)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $slug,
        $description,
        $status,
        $owner !== '' ? $owner : null,
        $department !== '' ? $department : null,
        $capturedDate !== '' ? $capturedDate : null,
        $version !== '' ? $version : null,
    ]);

    $document = fetch_document($pdo, (int) $pdo->lastInsertId());
    if ($document === null) {
        throw new RuntimeException('Could not load created document.');
    }

    return $document;
}

function update_document(
    PDO $pdo,
    int $id,
    string $title,
    string $slug,
    string $description,
    string $status,
    string $owner = '',
    string $department = '',
    string $capturedDate = '',
    string $version = ''
): void {
    $slug = slugify($slug !== '' ? $slug : $title);
    $slug = unique_slug($pdo, $slug, $id);

    $stmt = $pdo->prepare(
        'UPDATE as_is_documents
         SET title = ?, slug = ?, description = ?, status = ?,
             owner = ?, department = ?, captured_date = ?, version = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $title,
        $slug,
        $description,
        $status,
        $owner !== '' ? $owner : null,
        $department !== '' ? $department : null,
        $capturedDate !== '' ? $capturedDate : null,
        $version !== '' ? $version : null,
        $id,
    ]);
}

function delete_document(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM as_is_documents WHERE id = ?');
    $stmt->execute([$id]);
}

// ── Steps ─────────────────────────────────────────────────────────────────────

function fetch_step(PDO $pdo, int $stepId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM steps WHERE id = ?');
    $stmt->execute([$stepId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function create_step(
    PDO $pdo,
    int $asIsId,
    int $laneId,
    int $stepNumber,
    string $title,
    string $description,
    string $stepType,
    string $actionType = 'general'
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type, action_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$asIsId, $laneId, $stepNumber, $title, $description, $stepType, $actionType]);

    return (int) $pdo->lastInsertId();
}

function update_step(
    PDO $pdo,
    int $stepId,
    int $laneId,
    int $stepNumber,
    string $title,
    string $description,
    string $stepType,
    string $actionType = 'general'
): void {
    $stmt = $pdo->prepare(
        'UPDATE steps SET lane_id = ?, step_number = ?, title = ?, description = ?, step_type = ?, action_type = ?
         WHERE id = ?'
    );
    $stmt->execute([$laneId, $stepNumber, $title, $description, $stepType, $actionType, $stepId]);
}

function delete_step(PDO $pdo, int $stepId): void
{
    $stmt = $pdo->prepare('DELETE FROM steps WHERE id = ?');
    $stmt->execute([$stepId]);
}

// ── Lanes ─────────────────────────────────────────────────────────────────────

function create_lane(PDO $pdo, int $asIsId, string $name, string $color = '#e8f0fe'): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lanes WHERE as_is_id = ?');
    $stmt->execute([$asIsId]);
    $sortOrder = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO lanes (as_is_id, name, sort_order, color) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$asIsId, $name, $sortOrder, $color]);

    return (int) $pdo->lastInsertId();
}

function delete_lane(PDO $pdo, int $laneId): void
{
    $stmt = $pdo->prepare('DELETE FROM lanes WHERE id = ?');
    $stmt->execute([$laneId]);
}

function reorder_lane(PDO $pdo, int $laneId, int $asIsId, string $direction): void
{
    $lanes = fetch_lanes($pdo, $asIsId);
    $pos = null;

    foreach ($lanes as $i => $lane) {
        if ((int) $lane['id'] === $laneId) {
            $pos = $i;
            break;
        }
    }

    if ($pos === null) {
        return;
    }

    $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;

    if ($swapPos < 0 || $swapPos >= count($lanes)) {
        return;
    }

    $a = $lanes[$pos];
    $b = $lanes[$swapPos];

    $pdo->prepare('UPDATE lanes SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
    $pdo->prepare('UPDATE lanes SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
}

// ── Systems ───────────────────────────────────────────────────────────────────

function fetch_systems(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT s.*, COUNT(ss.step_id) AS step_count
         FROM systems s
         LEFT JOIN step_systems ss ON ss.system_id = s.id
         GROUP BY s.id
         ORDER BY s.name'
    );

    return $stmt->fetchAll();
}

function fetch_system(PDO $pdo, int $systemId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM systems WHERE id = ?');
    $stmt->execute([$systemId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function create_system(PDO $pdo, string $name, string $description = ''): int
{
    $stmt = $pdo->prepare('INSERT INTO systems (name, description) VALUES (?, ?)');
    $stmt->execute([$name, $description !== '' ? $description : null]);

    return (int) $pdo->lastInsertId();
}

function update_system(
    PDO $pdo,
    int $systemId,
    string $name,
    string $description,
    string $category = '',
    string $hosting  = 'unknown',
    string $vendor   = '',
    string $owner    = '',
    string $contact  = ''
): void {
    $validHosting = ['saas','cloud','on-premise','hybrid','unknown'];
    $hosting = in_array($hosting, $validHosting, true) ? $hosting : 'unknown';

    $pdo->prepare(
        'UPDATE systems SET name=?, description=?, category=?, hosting=?, vendor=?, owner=?, contact=?
         WHERE id=?'
    )->execute([
        $name,
        $description !== '' ? $description : null,
        $category    !== '' ? $category    : null,
        $hosting,
        $vendor      !== '' ? $vendor      : null,
        $owner       !== '' ? $owner       : null,
        $contact     !== '' ? $contact     : null,
        $systemId,
    ]);
}

function create_system_full(
    PDO $pdo,
    string $name,
    string $description = '',
    string $category    = '',
    string $hosting     = 'unknown',
    string $vendor      = '',
    string $owner       = '',
    string $contact     = ''
): int {
    $validHosting = ['saas','cloud','on-premise','hybrid','unknown'];
    $hosting = in_array($hosting, $validHosting, true) ? $hosting : 'unknown';

    $pdo->prepare(
        'INSERT INTO systems (name, description, category, hosting, vendor, owner, contact)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $name,
        $description !== '' ? $description : null,
        $category    !== '' ? $category    : null,
        $hosting,
        $vendor      !== '' ? $vendor      : null,
        $owner       !== '' ? $owner       : null,
        $contact     !== '' ? $contact     : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function hosting_label(string $hosting): string
{
    return match ($hosting) {
        'saas'        => 'SaaS',
        'cloud'       => 'Cloud',
        'on-premise'  => 'On-premise',
        'hybrid'      => 'Hybrid',
        default       => 'Unknown',
    };
}

function hosting_color(string $hosting): string
{
    return match ($hosting) {
        'saas'        => '#dbeafe',
        'cloud'       => '#e0f2fe',
        'on-premise'  => '#f1f5f9',
        'hybrid'      => '#f3e8ff',
        default       => '#f8fafc',
    };
}

function delete_system(PDO $pdo, int $systemId): void
{
    $pdo->prepare('DELETE FROM systems WHERE id = ?')->execute([$systemId]);
}

function fetch_step_system_ids(PDO $pdo, int $stepId): array
{
    $stmt = $pdo->prepare('SELECT system_id FROM step_systems WHERE step_id = ?');
    $stmt->execute([$stepId]);

    return array_column($stmt->fetchAll(), 'system_id');
}

function sync_step_systems(PDO $pdo, int $stepId, array $systemIds): void
{
    $pdo->prepare('DELETE FROM step_systems WHERE step_id = ?')->execute([$stepId]);

    foreach ($systemIds as $sysId) {
        $pdo->prepare('INSERT INTO step_systems (step_id, system_id) VALUES (?, ?)')->execute([$stepId, (int) $sysId]);
    }
}

// ── Connections ───────────────────────────────────────────────────────────────

function create_connection(PDO $pdo, int $fromStepId, int $toStepId, string $label = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES (?, ?, ?)'
    );
    $stmt->execute([$fromStepId, $toStepId, $label !== '' ? $label : null]);
}

function delete_connection(PDO $pdo, int $connectionId): void
{
    $pdo->prepare('DELETE FROM step_connections WHERE id = ?')->execute([$connectionId]);
}

// ── Queries ───────────────────────────────────────────────────────────────────

function fetch_lanes(PDO $pdo, int $asIsId): array
{
    $stmt = $pdo->prepare('SELECT * FROM lanes WHERE as_is_id = ? ORDER BY sort_order, id');
    $stmt->execute([$asIsId]);

    return $stmt->fetchAll();
}

function fetch_steps(PDO $pdo, int $asIsId): array
{
    $stmt = $pdo->prepare(
        'SELECT st.*, GROUP_CONCAT(sys.name ORDER BY sys.name SEPARATOR ", ") AS systems,
                GROUP_CONCAT(sys.id ORDER BY sys.name SEPARATOR ",") AS system_ids
         FROM steps st
         LEFT JOIN step_systems ss ON ss.step_id = st.id
         LEFT JOIN systems sys ON sys.id = ss.system_id
         WHERE st.as_is_id = ?
         GROUP BY st.id
         ORDER BY st.step_number'
    );
    $stmt->execute([$asIsId]);

    return $stmt->fetchAll();
}

function fetch_connections(PDO $pdo, int $asIsId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.*,
                fs.step_number AS from_number, fs.title AS from_title,
                ts.step_number AS to_number,   ts.title AS to_title
         FROM step_connections c
         INNER JOIN steps fs ON fs.id = c.from_step_id
         INNER JOIN steps ts ON ts.id = c.to_step_id
         WHERE fs.as_is_id = ?
         ORDER BY fs.step_number, ts.step_number'
    );
    $stmt->execute([$asIsId]);

    return $stmt->fetchAll();
}

// ── Request helpers ───────────────────────────────────────────────────────────

function resolve_document_request(PDO $pdo): ?array
{
    $slug = trim((string) ($_GET['slug'] ?? $_POST['slug'] ?? ''));

    if ($slug !== '') {
        return fetch_document_by_slug($pdo, $slug);
    }

    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id > 0) {
        return fetch_document($pdo, $id);
    }

    return null;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function valid_status(string $status): string
{
    return in_array($status, ['draft', 'published'], true) ? $status : 'draft';
}

function valid_step_type(string $type): string
{
    return in_array($type, ['start', 'task', 'decision', 'end'], true) ? $type : 'task';
}

function valid_action_type(string $type): string
{
    return array_key_exists($type, action_type_options()) ? $type : 'general';
}

// ── Labels & icons ────────────────────────────────────────────────────────────

function step_type_label(string $type): string
{
    return match ($type) {
        'start'    => 'Start',
        'decision' => 'Decision',
        'end'      => 'End',
        default    => 'Task',
    };
}

function action_type_options(): array
{
    return [
        'general'      => 'General task',
        'phone'        => 'Phone call',
        'document'     => 'Document',
        'email'        => 'Email',
        'letter'       => 'Letter / post',
        'wait'         => 'Wait / hold',
        'meeting'      => 'Meeting / approval',
        'data-entry'   => 'Data entry',
        'check'        => 'Check / review',
        'escalation'   => 'Escalation',
        'automated'    => 'Automated / system',
        'api-call'     => 'API call',
        'notification' => 'Notification / alert',
        'visit'        => 'Visit / inspection',
        'payment'      => 'Payment',
        'report'       => 'Report / record',
    ];
}

function action_type_descriptions(): array
{
    return [
        'general'      => 'A standard step with no specific action icon.',
        'phone'        => 'Someone makes or receives a telephone call.',
        'email'        => 'A formal email is sent or received.',
        'letter'       => 'A letter or document is sent by post.',
        'notification' => 'An alert, text message, or system notification is triggered.',
        'document'     => 'A form, record, or document is created or used.',
        'data-entry'   => 'A person enters information into a system.',
        'automated'    => 'The system performs this step automatically.',
        'api-call'     => 'One system calls another — an integration point.',
        'report'       => 'A formal report or output record is produced.',
        'check'        => 'Something is checked, verified, or reviewed.',
        'meeting'      => 'A discussion, sign-off, or formal approval.',
        'payment'      => 'A financial transaction — invoice, payment, or refund.',
        'visit'        => 'Someone visits a location for work or inspection.',
        'wait'         => 'The process pauses — waiting for a response or date.',
        'escalation'   => 'The task is passed to a more senior person or team.',
    ];
}

function action_type_icon(string $type): string
{
    $icon = match ($type) {
        'phone'        => 'phone',
        'document'     => 'file-text',
        'email'        => 'mail',
        'letter'       => 'mail-open',
        'wait'         => 'clock',
        'meeting'      => 'users',
        'data-entry'   => 'keyboard',
        'check'        => 'check-circle',
        'escalation'   => 'arrow-up-circle',
        'automated'    => 'cpu',
        'api-call'     => 'webhook',
        'notification' => 'bell',
        'visit'        => 'map-pin',
        'payment'      => 'credit-card',
        'report'       => 'clipboard-list',
        default        => '',
    };

    return $icon !== '' ? '<i data-lucide="' . $icon . '" class="licon"></i>' : '';
}

// ── Mermaid builder ───────────────────────────────────────────────────────────

function build_mermaid(array $lanes, array $steps, array $connections): string
{
    $lines = ['flowchart LR'];

    foreach ($lanes as $lane) {
        $laneKey = 'lane' . $lane['id'];
        $lines[] = '    subgraph ' . $laneKey . '["' . str_replace('"', "'", $lane['name']) . '"]';
        $lines[] = '        direction TB';

        foreach ($steps as $step) {
            if ((int) $step['lane_id'] !== (int) $lane['id']) {
                continue;
            }

            $nodeId = 's' . $step['step_number'];
            $icon = '';
            if (!empty($step['action_type']) && $step['action_type'] !== 'general') {
                $iconMap = [
                    'phone' => '☎', 'document' => '📄', 'email' => '✉', 'letter' => '✍',
                    'wait' => '⏳', 'meeting' => '👥', 'data-entry' => '💻', 'check' => '✓',
                    'escalation' => '⇧', 'automated' => '⚙', 'api-call' => '⇌', 'notification' => '△',
                    'visit' => '◉', 'payment' => '£', 'report' => '☰',
                ];
                $icon = ($iconMap[$step['action_type']] ?? '') . ' ';
            }
            $label = $icon . $step['step_number'] . '. ' . str_replace(['"', "\n"], ["'", ' '], $step['title']);

            if ($step['step_type'] === 'decision') {
                $lines[] = '        ' . $nodeId . '{"' . $label . '"}';
            } elseif ($step['step_type'] === 'start' || $step['step_type'] === 'end') {
                $lines[] = '        ' . $nodeId . '(("' . $label . '"))';
            } else {
                $lines[] = '        ' . $nodeId . '["' . $label . '"]';
            }
        }

        $lines[] = '    end';
    }

    foreach ($connections as $connection) {
        $from = 's' . $connection['from_number'];
        $to   = 's' . $connection['to_number'];
        $edge = '    ' . $from . ' -->';

        if (!empty($connection['label'])) {
            $edge .= '|' . str_replace('"', "'", $connection['label']) . '|';
        }

        $lines[] = $edge . ' ' . $to;
    }

    return implode("\n", $lines);
}

// ── Layout ────────────────────────────────────────────────────────────────────

function render_layout(string $title, string $content, array $options = []): void
{
    $isLanding = !empty($options['landing']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> · AS-IS</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%231a1e2e'/%3E%3Ctext x='16' y='23' font-family='Georgia%2C serif' font-size='19' font-style='italic' text-anchor='middle' fill='%2326c6da'%3EA%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Serif:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        /* ── Tokens ──────────────────────────────────────────── */
        :root {
            --bg:         oklch(97% 0.008 245);
            --surface:    oklch(100% 0 0);
            --text:       oklch(21% 0.04 245);
            --muted:      oklch(50% 0.04 245);
            --accent:     oklch(54% 0.14 200);
            --accent-dk:  oklch(46% 0.14 200);
            --border:     oklch(88% 0.015 245);
            --nav-bg:     oklch(19% 0.05 245);
            --nav-text:   oklch(95% 0.01 245);
            --success:    oklch(42% 0.14 155);
            --warning:    oklch(65% 0.15 68);
            --danger:     oklch(44% 0.17 25);
            --f-sans:     'IBM Plex Sans', sans-serif;
            --f-serif:    'IBM Plex Serif', serif;
            --r:          6px;
            --r-lg:       10px;
        }

        /* ── Reset ───────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--f-sans);
            font-size: 1rem;
            line-height: 1.6;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Skip link ───────────────────────────────────────── */
        .skip-link {
            position: absolute;
            top: -100%;
            left: 1rem;
            background: var(--accent);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0 0 var(--r) var(--r);
            font-weight: 600;
            z-index: 9999;
            text-decoration: none;
        }
        .skip-link:focus { top: 0; }

        /* ── Site navigation ─────────────────────────────────── */
        .site-nav-bar {
            background: var(--nav-bg);
            border-bottom: 3px solid var(--accent);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .site-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 54px;
            display: flex;
            align-items: center;
        }

        .site-logo {
            font-family: var(--f-serif);
            font-style: italic;
            font-weight: 400;
            font-size: 1.2rem;
            color: var(--nav-text);
            text-decoration: none;
            letter-spacing: -0.02em;
            padding-right: 2.5rem;
            flex-shrink: 0;
        }
        .site-logo:hover,
        .site-logo[aria-current="page"] { color: white; text-decoration: none; }

        .site-nav-links {
            display: flex;
            flex: 1;
        }

        .site-nav-links a {
            font-size: 0.875rem;
            color: oklch(72% 0.025 245);
            text-decoration: none;
            padding: 0 0.9rem;
            height: 54px;
            display: flex;
            align-items: center;
            border-bottom: 3px solid transparent;
            margin-bottom: -3px;
            transition: color 120ms, border-color 120ms;
        }
        .site-nav-links a:hover,
        .site-nav-links a[aria-current="page"] {
            color: white;
            border-bottom-color: var(--accent);
            text-decoration: none;
        }

        .site-nav-cta {
            font-family: var(--f-sans);
            font-size: 0.8125rem;
            font-weight: 600;
            background: var(--accent);
            color: white;
            padding: 0.45rem 0.9rem;
            border-radius: var(--r);
            text-decoration: none;
            flex-shrink: 0;
            margin-left: 1rem;
            transition: background 120ms;
        }
        .site-nav-cta:hover { background: var(--accent-dk); color: white; text-decoration: none; }

        .site-nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: auto;
            flex-shrink: 0;
        }

        .site-nav-user {
            font-size: 0.8125rem;
            color: oklch(82% 0.02 245);
            max-width: 12rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .site-nav-link-secondary {
            font-size: 0.8125rem;
            color: oklch(88% 0.02 245);
            text-decoration: none;
        }

        .site-nav-link-secondary:hover { color: white; text-decoration: underline; }

        .flash {
            padding: 0.75rem 1rem;
            border-radius: var(--r);
            margin-bottom: 1rem;
            font-size: 0.9375rem;
        }

        .flash-success { background: oklch(94% 0.04 155); color: oklch(32% 0.1 155); border: 1px solid oklch(82% 0.06 155); }
        .flash-error   { background: oklch(95% 0.04 25);  color: oklch(35% 0.12 25);  border: 1px solid oklch(85% 0.08 25); }
        .flash-info    { background: oklch(95% 0.02 245); color: var(--text); border: 1px solid var(--border); }

        .login-card {
            max-width: 24rem;
            margin: 2rem auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1.5rem;
            box-shadow: 0 2px 12px oklch(0% 0 0 / 0.06);
        }

        .login-card h1 { margin: 0 0 0.25rem; font-size: 1.35rem; }
        .login-card .login-sub { color: var(--muted); font-size: 0.875rem; margin-bottom: 1.25rem; }

        .btn--microsoft {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            background: #2f2f2f;
            color: #fff;
            border: 1px solid #2f2f2f;
            font-weight: 600;
            text-decoration: none;
            border-radius: var(--r);
            padding: 0.55rem 1rem;
            box-sizing: border-box;
        }

        .btn--microsoft:hover { background: #1a1a1a; border-color: #1a1a1a; color: #fff; text-decoration: none; }

        .login-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0 1rem;
            color: var(--muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── Wrap ────────────────────────────────────────────── */
        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
        }

        /* ── Focus ───────────────────────────────────────────── */
        :focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
            border-radius: 3px;
        }

        /* ── Typography ──────────────────────────────────────── */
        h1 {
            font-family: var(--f-serif);
            font-weight: 400;
            font-size: clamp(1.4rem, 2.5vw, 1.875rem);
            line-height: 1.2;
            letter-spacing: -0.02em;
            color: var(--text);
            margin: 0 0 0.3rem;
        }

        h2 {
            font-family: var(--f-sans);
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.3;
            color: var(--text);
            margin: 0 0 0.85rem;
        }

        p { margin: 0 0 1rem; color: var(--muted); line-height: 1.6; }

        a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
        a:hover { color: var(--accent-dk); }

        code {
            font-family: 'IBM Plex Mono', 'Courier New', monospace;
            font-size: 0.875em;
            background: oklch(93% 0.015 245);
            color: var(--text);
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
        }

        /* ── Page header ─────────────────────────────────────── */
        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── Actions ─────────────────────────────────────────── */
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        /* ── Buttons ─────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            font-family: var(--f-sans);
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.55rem 1rem;
            border-radius: var(--r);
            border: 2px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background 120ms, border-color 120ms, color 120ms;
            white-space: nowrap;
            background: var(--accent);
            color: white;
            line-height: 1;
        }
        .btn:hover { background: var(--accent-dk); color: white; text-decoration: none; }

        .btn-secondary {
            background: transparent;
            border-color: oklch(76% 0.03 245);
            color: var(--text);
        }
        .btn-secondary:hover {
            background: oklch(93% 0.01 245);
            border-color: oklch(62% 0.04 245);
            color: var(--text);
        }

        .btn-danger { background: var(--danger); border-color: var(--danger); color: white; }
        .btn-danger:hover { background: oklch(38% 0.17 25); color: white; }

        .btn-link {
            background: transparent;
            border-color: transparent;
            color: var(--accent);
            padding: 0.55rem 0;
            font-weight: 400;
        }
        .btn-link:hover { background: transparent; color: var(--accent-dk); text-decoration: underline; }

        .btn-sm { font-size: 0.8125rem; padding: 0.35rem 0.7rem; }

        /* ── Cards ───────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .help-ref-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            align-items: start;
        }

        @media (max-width: 768px) {
            .help-ref-grid { grid-template-columns: 1fr; }
        }

        .danger-zone {
            border-color: oklch(82% 0.06 25);
            background: oklch(98.5% 0.01 25);
        }

        /* ── Forms ───────────────────────────────────────────── */
        .form-grid { display: grid; gap: 1.25rem; }

        label {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.4rem;
            color: var(--text);
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            font-family: var(--f-sans);
            font-size: 1rem;
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 2px solid oklch(78% 0.025 245);
            border-radius: var(--r);
            background: var(--surface);
            color: var(--text);
            transition: border-color 120ms, box-shadow 120ms;
            appearance: auto;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px oklch(54% 0.14 200 / 0.15);
        }
        input::placeholder, textarea::placeholder { color: oklch(66% 0.025 245); }

        input[type="color"] {
            width: 52px;
            height: 40px;
            padding: 0.2rem;
            border: 2px solid oklch(78% 0.025 245);
            border-radius: var(--r);
            cursor: pointer;
            background: var(--surface);
        }

        textarea { min-height: 110px; resize: vertical; }

        .field-help {
            margin-top: 0.4rem;
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.4;
        }

        .inline-form { display: inline; }

        /* ── Tables ──────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; }

        th {
            text-align: left;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 0.65rem 0.75rem;
            border-bottom: 2px solid var(--border);
            vertical-align: bottom;
        }

        td {
            text-align: left;
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.9375rem;
        }

        tr:last-child td { border-bottom: none; }

        /* ── Badges ──────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            background: oklch(92% 0.03 200);
            color: oklch(35% 0.12 200);
        }

        .badge-start    { background: oklch(94% 0.04 155); color: oklch(33% 0.14 155); }
        .badge-end      { background: oklch(93% 0.04 25);  color: oklch(36% 0.14 25);  }
        .badge-decision { background: oklch(94% 0.05 68);  color: oklch(36% 0.15 68);  }
        .badge-task     { background: oklch(92% 0.03 200); color: oklch(35% 0.12 200); }

        /* ── Notices ─────────────────────────────────────────── */
        .notice {
            padding: 1rem 1.25rem;
            border-radius: var(--r);
            border-left: 4px solid var(--warning);
            background: oklch(97% 0.025 68);
            color: oklch(28% 0.1 68);
            margin-bottom: 1.25rem;
            font-size: 0.9375rem;
        }

        .notice-success {
            border-left-color: var(--success);
            background: oklch(97% 0.02 155);
            color: oklch(28% 0.1 155);
        }

        /* ── Home empty state ───────────────────────────────── */
        .home-empty {
            padding: 3.5rem 0 2rem;
            max-width: 560px;
        }

        .home-empty-eyebrow {
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--accent);
            margin: 0 0 0.85rem;
        }

        .home-empty-heading {
            font-family: var(--f-serif);
            font-weight: 400;
            font-size: clamp(1.5rem, 3vw, 2rem);
            letter-spacing: -0.02em;
            color: var(--text);
            margin: 0 0 1rem;
            line-height: 1.2;
        }

        .home-empty-body {
            font-size: 1rem;
            color: var(--muted);
            line-height: 1.65;
            margin: 0;
            max-width: 52ch;
        }

        /* Example document badge */
        .badge-example {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: oklch(50% 0.06 200);
            background: oklch(93% 0.025 200);
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Title link in document list */
        .doc-title-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
        }
        .doc-title-link:hover { color: var(--accent); text-decoration: none; }

        /* ── Marketing landing page ─────────────────────────── */
        body.is-landing .wrap { max-width: none; padding: 0; }
        body.is-landing main { padding: 0; }

        .landing-hero {
            background: linear-gradient(160deg, oklch(97% 0.02 245) 0%, oklch(94% 0.04 200) 100%);
            border-bottom: 1px solid var(--border);
        }

        .landing-hero-inner,
        .landing-section-inner,
        .landing-cta-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .landing-hero-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
            padding-top: 3.5rem;
            padding-bottom: 3.5rem;
        }

        .landing-eyebrow {
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            margin: 0 0 1rem;
        }

        .landing-title {
            font-family: var(--f-serif);
            font-weight: 400;
            font-size: clamp(2rem, 4.5vw, 3.1rem);
            line-height: 1.12;
            letter-spacing: -0.03em;
            color: var(--text);
            margin: 0 0 1.25rem;
        }

        .landing-lead {
            font-size: 1.0625rem;
            line-height: 1.65;
            color: var(--muted);
            margin: 0 0 1.75rem;
            max-width: 46ch;
        }

        .landing-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .btn-lg {
            font-size: 0.9375rem;
            padding: 0.7rem 1.25rem;
        }

        .landing-hero-visual { margin: 0; }

        .landing-hero-visual img,
        .landing-figure img,
        .landing-illustration svg {
            width: 100%;
            height: auto;
            display: block;
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: 0 12px 40px oklch(70% 0.04 245 / 0.15);
        }

        .landing-section { padding: 4rem 0; }

        .landing-section-alt {
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .landing-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .landing-split-reverse .landing-figure { order: -1; }

        .landing-h2 {
            font-family: var(--f-serif);
            font-weight: 400;
            font-size: clamp(1.5rem, 2.5vw, 2rem);
            letter-spacing: -0.02em;
            margin: 0 0 1rem;
            color: var(--text);
        }

        .landing-center { text-align: center; }
        .landing-intro { max-width: 62ch; margin: 0 auto 2.5rem; }

        .landing-figure { margin: 0; }

        .landing-figure figcaption {
            margin-top: 0.75rem;
            font-size: 0.8125rem;
            color: var(--muted);
            text-align: center;
        }

        .landing-list,
        .landing-checklist {
            margin: 0;
            padding-left: 1.2rem;
            color: var(--muted);
        }

        .landing-list li,
        .landing-checklist li {
            margin-bottom: 0.5rem;
            line-height: 1.55;
        }

        .landing-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        .landing-feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1.5rem;
        }

        .landing-feature-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            color: var(--text);
        }

        .landing-feature-card p {
            margin: 0;
            font-size: 0.9375rem;
        }

        .landing-expect {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 2.5rem;
            align-items: start;
        }

        .landing-expect-aside {
            background: oklch(97% 0.02 200);
            border: 1px solid oklch(88% 0.04 200);
            border-radius: var(--r-lg);
            padding: 1.5rem;
        }

        .landing-expect-aside h3 {
            margin: 0 0 0.75rem;
            font-size: 1rem;
        }

        .landing-expect-aside p { font-size: 0.9375rem; }

        .landing-cta {
            background: var(--nav-bg);
            color: var(--nav-text);
            padding: 3.5rem 0;
        }

        .landing-cta-inner { text-align: center; }

        .landing-cta-title {
            font-family: var(--f-serif);
            font-weight: 400;
            font-size: clamp(1.5rem, 3vw, 2.25rem);
            margin: 0 0 0.75rem;
            color: white;
        }

        .landing-cta p {
            color: oklch(78% 0.02 245);
            margin: 0 0 1.75rem;
        }

        .landing-cta .landing-hero-actions { justify-content: center; }

        .btn-on-dark { background: var(--accent); color: white; }

        .btn-on-dark-outline {
            background: transparent;
            border-color: oklch(55% 0.04 245);
            color: oklch(92% 0.02 245);
        }

        .btn-on-dark-outline:hover {
            background: oklch(28% 0.04 245);
            border-color: oklch(70% 0.04 245);
            color: white;
        }

        @media (max-width: 900px) {
            .landing-hero-inner,
            .landing-split,
            .landing-features,
            .landing-expect,
            .landing-action-grid { grid-template-columns: 1fr; }

            .landing-split-reverse .landing-figure { order: 0; }
        }

        .landing-action-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .landing-action-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1rem 1.1rem;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .landing-action-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 8px;
            background: oklch(96% 0.02 200);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .landing-action-icon svg.lucide {
            width: 1.1rem;
            height: 1.1rem;
        }

        .landing-action-card h3 {
            margin: 0 0 0.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text);
        }

        .landing-action-card p {
            margin: 0;
            font-size: 0.8125rem;
            line-height: 1.45;
        }

        @media (max-width: 1100px) {
            .landing-action-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── Build workflow tracker ─────────────────────────── */
        .build-tracker {
            display: flex;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .build-stage {
            flex: 1;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            color: inherit;
            border-right: 1px solid var(--border);
            transition: background 120ms;
            min-width: 0;
        }
        .build-stage:last-child { border-right: none; }
        .build-stage:hover { background: var(--bg); text-decoration: none; color: inherit; }

        .build-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background 120ms;
        }
        .build-stage.is-done    .build-num { background: var(--accent);           color: white; }
        .build-stage.is-current .build-num { background: var(--text);             color: white; }
        .build-stage.is-upcoming .build-num{ background: oklch(92% 0.01 245);     color: var(--muted); }

        .build-info { min-width: 0; }

        .build-name {
            font-size: 0.8125rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .build-stage.is-done    .build-name { color: var(--muted); }
        .build-stage.is-current .build-name { color: var(--text); }
        .build-stage.is-upcoming .build-name{ color: oklch(68% 0.025 245); }

        .build-status {
            font-size: 0.72rem;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 0.1rem;
        }
        .build-stage.is-current .build-status { color: var(--accent); font-weight: 500; }

        .build-tracker-cta {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.25rem;
            background: oklch(97% 0.02 200);
            border-left: 1px solid oklch(88% 0.04 200);
            flex-shrink: 0;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
            white-space: nowrap;
        }
        .build-tracker-cta:hover { background: oklch(94% 0.04 200); color: var(--accent-dk); text-decoration: none; }

        /* ── Workflow empty states ───────────────────────────── */
        .empty-state {
            padding: 1.5rem;
            background: var(--bg);
            border: 1px dashed oklch(82% 0.02 245);
            border-radius: var(--r);
            margin-bottom: 1.25rem;
        }

        .empty-state-title {
            font-weight: 600;
            font-size: 0.9375rem;
            margin: 0 0 0.4rem;
            color: var(--text);
        }

        .empty-state-body {
            font-size: 0.875rem;
            color: var(--muted);
            margin: 0 0 1rem;
            line-height: 1.5;
        }

        /* ── Doc metadata ────────────────────────────────────── */
        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            font-size: 0.8125rem;
            color: var(--muted);
            margin-top: 0.4rem;
        }
        .doc-meta span { display: flex; align-items: center; gap: 0.3rem; }
        .doc-meta svg.lucide { width: 0.95rem; height: 0.95rem; vertical-align: -0.1em; }

        /* ── Diagram viewer ──────────────────────────────────── */
        .diagram-wrap {
            overflow: auto;
            max-height: 70vh;
            min-height: 200px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            cursor: grab;
            -webkit-overflow-scrolling: touch;
            position: relative;
        }
        .diagram-wrap:active { cursor: grabbing; }
        .diagram-wrap .diagram { display: inline-block; min-width: 100%; padding: 1.5rem 2rem; border: none; border-radius: 0; }
        .diagram-wrap svg { max-width: none !important; display: block; }
        .diagram-wrap:-webkit-full-screen { background: white; max-height: 100vh; padding: 1rem; }
        .diagram-wrap:fullscreen           { background: white; max-height: 100vh; padding: 1rem; }

        .diagram-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.6rem;
        }

        .diagram-hint { font-size: 0.8rem; color: var(--muted); }
        .zoom-level   { font-size: 0.8rem; color: var(--muted); min-width: 3rem; text-align: center; }

        .diagram {
            overflow-x: auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1rem;
            min-height: 120px;
        }

        /* ── Step arrows (swimlane cards) ────────────────────── */
        .step-arrows { display: flex; flex-wrap: wrap; gap: 0.2rem; margin-top: 0.35rem; }

        .arrow-tag {
            font-size: 0.67rem;
            background: oklch(93% 0.04 200);
            color: oklch(35% 0.14 200);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            white-space: nowrap;
            font-weight: 500;
        }
        .arrow-tag.has-label { background: oklch(94% 0.05 68); color: oklch(36% 0.15 68); }

        /* ── Lane list (edit page) ───────────────────────────── */
        .lane-list { display: grid; gap: 1rem; }
        .lane { border-left: 3px solid var(--accent); padding-left: 1rem; }
        .step { border: 1px solid var(--border); border-radius: var(--r); padding: 0.75rem; margin-top: 0.5rem; background: var(--bg); }

        /* ── Connection form ─────────────────────────────────── */
        .connection-row { display: grid; grid-template-columns: 1fr auto 1fr 160px auto; gap: 0.5rem; align-items: end; }
        .arrow-label { font-size: 1.4rem; padding-bottom: 0.5rem; color: var(--muted); text-align: center; }

        /* ── Utilities ───────────────────────────────────────── */
        .grid { display: grid; gap: 1rem; }
        .inline-form { display: inline; }

        .lnk-danger { color: var(--danger); text-decoration: none; }
        .lnk-danger:hover { color: oklch(38% 0.17 25); text-decoration: underline; }

        /* Clip overflowing text — requires table-layout:fixed on the parent table */
        .td-clip {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Inline action group for table rows — never wraps */
        .row-actions {
            display: flex;
            gap: 0.35rem;
            align-items: center;
            flex-wrap: nowrap;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: 0ms !important; animation-duration: 0ms !important; }
        }

        .sys-tag {
            font-size: 0.7rem;
            background: oklch(93% 0.015 245);
            color: var(--muted);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
        }

        .step-num { font-size: 0.72rem; font-weight: 700; color: var(--muted); background: var(--bg); padding: 0.05rem 0.35rem; border-radius: 4px; white-space: nowrap; }
        .step-icon { font-size: 1rem; line-height: 1; }
        .step-title { font-weight: 600; color: var(--text); line-height: 1.3; }
        .step-sys-tags { display: flex; flex-wrap: wrap; gap: 0.2rem; margin-top: 0.3rem; }
        .step-card-top { display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem; }

        /* ── Lucide icons ────────────────────────────────────── */
        svg.lucide { width: 1em; height: 1em; vertical-align: -0.15em; flex-shrink: 0; }

        /* ── Print ───────────────────────────────────────────── */
        @media print {
            .site-nav-bar, .no-print { display: none !important; }
            .print-only-lanes { display: block !important; }
            body { background: white; }
            .wrap { padding: 0; max-width: 100%; }
            .card { border: 1px solid #ccc; page-break-inside: avoid; }
        }
    </style>
</head>
<body<?= $isLanding ? ' class="is-landing"' : '' ?>>
    <a href="#main" class="skip-link">Skip to main content</a>

    <?php
    $__pg = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $__nav = static function (string $page) use ($__pg): string {
        return $page === $__pg ? ' aria-current="page"' : '';
    };
    $__loggedIn = function_exists('is_logged_in') && is_logged_in();
    $__canEdit  = $__loggedIn && function_exists('can_edit_maps') && can_edit_maps();
    ?>
    <div class="site-nav-bar" role="banner">
        <div class="site-nav-inner">
            <a href="/index.php" class="site-logo">AS-IS</a>
            <nav class="site-nav-links" aria-label="Main navigation">
                <a href="/index.php"<?= $__nav('index.php') ?>>Home</a>
                <a href="/documents.php"<?= $__nav('documents.php') ?>>Process maps</a>
                <a href="/systems.php"<?= $__nav('systems.php') ?>>Systems</a>
                <a href="/help.php"<?= $__nav('help.php') ?>>Guidance</a>
            </nav>
            <div class="site-nav-actions">
                <?php if ($__loggedIn): ?>
                    <span class="site-nav-user" title="<?= h($_SESSION['admin_user'] ?? '') ?>"><?= h($_SESSION['admin_user'] ?? '') ?></span>
                    <?php if (function_exists('is_microsoft_user') && !is_microsoft_user()): ?>
                        <a href="/profile/change-password.php" class="site-nav-link-secondary">Password</a>
                    <?php endif; ?>
                    <a href="/logout.php" class="site-nav-link-secondary">Sign out</a>
                <?php else: ?>
                    <a href="/login.php" class="site-nav-link-secondary">Sign in</a>
                <?php endif; ?>
                <?php if ($__canEdit): ?>
                    <a href="/new.php" class="site-nav-cta">+ New AS-IS</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main id="main" class="wrap">
        <?= function_exists('render_flash') ? render_flash() : '' ?>
        <?= $content ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>lucide.createIcons();</script>

    <footer style="background:var(--nav-bg);border-top:2px solid var(--accent);margin-top:auto;padding:.65rem 1.5rem;">
        <div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;font-size:.75rem;">
            <span style="color:rgba(255,255,255,.45);font-weight:600;text-transform:uppercase;letter-spacing:.05em;font-size:.68rem;margin-right:.25rem;">ERC Digital Tools</span>
            <a href="<?= h(SOR_SITE_URL) ?>/" style="color:rgba(255,255,255,.75);text-decoration:none;padding:.15rem .45rem;border:1px solid rgba(255,255,255,.25);border-radius:3px;">SOR Management System</a>
            <a href="<?= h(ERC_SITE_URL) ?>/" style="color:rgba(255,255,255,.75);text-decoration:none;padding:.15rem .45rem;border:1px solid rgba(255,255,255,.25);border-radius:3px;">ERC Portal</a>
            <a href="<?= h(APP_URL) ?>/" style="color:rgba(255,255,255,.75);text-decoration:none;padding:.15rem .45rem;border:1px solid rgba(255,255,255,.25);border-radius:3px;">AS-IS Process Mapping</a>
        </div>
    </footer>
</body>
</html>
    <?php
}
