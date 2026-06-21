<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_login();

$slug  = trim((string) ($_GET['slug'] ?? ''));
$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$print = !empty($_GET['print']);

$error = $document = null;
$lanes = $steps = $connections = [];

$pdo = db();
ensure_schema($pdo);

try {
    if ($slug !== '') {
        $document = fetch_document_by_slug($pdo, $slug);
    } elseif ($id > 0) {
        $document = fetch_document($pdo, $id);
        if ($document !== null && !empty($document['slug'])) {
            header('Location: /view.php?slug=' . rawurlencode($document['slug']), true, 302);
            exit;
        }
    }

    if ($document === null) {
        $error = 'AS-IS document not found.';
    } else {
        $asIsId      = (int) $document['id'];
        $lanes       = fetch_lanes($pdo, $asIsId);
        $steps       = fetch_steps($pdo, $asIsId);
        $connections = fetch_connections($pdo, $asIsId);
    }
} catch (Throwable $e) {
    $error = 'This map could not be loaded. Please try again.';
}

// JSON payload for the client-side SVG renderer.
$diagramJson = '{}';
if ($document !== null) {
    $diagramJson = json_encode([
        'lanes' => array_values(array_map(fn($l) => [
            'id'    => (int) $l['id'],
            'name'  => $l['name'],
            'color' => $l['color'],
        ], $lanes)),
        'steps' => array_values(array_map(fn($s) => [
            'id'          => (int) $s['id'],
            'lane_id'     => (int) $s['lane_id'],
            'step_number' => (int) $s['step_number'],
            'title'       => $s['title'],
            'description' => (string) ($s['description'] ?? ''),
            'step_type'   => $s['step_type'],
            'action_type' => $s['action_type'] ?? 'general',
            'systems'     => $s['systems'] ?? '',
        ], $steps)),
        'connections' => array_values(array_map(fn($c) => [
            'from'  => (int) $c['from_step_id'],
            'to'    => (int) $c['to_step_id'],
            'label' => $c['label'],
        ], $connections)),
    ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
}

ob_start();

if ($error || $document === null):
?>
<header>
    <div><h1>Not found</h1><p><?= h($error ?? 'Unknown error') ?></p></div>
    <a class="btn btn-secondary btn-sm" href="/documents.php">Back</a>
</header>
<?php
    render_layout('Not found', ob_get_clean() ?: '');
    return;
endif;
?>

<!-- ── Document header ───────────────────────────────────────────── -->
<header>
    <div>
        <h1><?= h($document['title']) ?></h1>
        <?php if (!empty($document['description'])): ?>
            <p><?= h($document['description']) ?></p>
        <?php endif; ?>

        <?php
        $meta = [];
        if (!empty($document['owner']))        $meta[] = '<span><i data-lucide="user" class="licon"></i> '       . h($document['owner'])        . '</span>';
        if (!empty($document['department']))   $meta[] = '<span><i data-lucide="building-2" class="licon"></i> ' . h($document['department'])   . '</span>';
        if (!empty($document['captured_date']))$meta[] = '<span><i data-lucide="calendar" class="licon"></i> '   . h($document['captured_date']) . '</span>';
        if (!empty($document['version']))      $meta[] = '<span><i data-lucide="tag" class="licon"></i> '        . h($document['version'])       . '</span>';
        if ($meta):
        ?>
            <div class="doc-meta"><?= implode('', $meta) ?></div>
        <?php endif; ?>
    </div>
    <div class="actions no-print">
        <a class="btn btn-secondary btn-sm" href="/view.php?slug=<?= rawurlencode($document['slug']) ?>&print=1" target="_blank">Print</a>
        <a class="btn btn-sm" href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Edit</a>
        <a class="btn btn-secondary btn-sm" href="/documents.php">Back</a>
    </div>
</header>

<?php if ($steps === []): ?>
<div class="card">
    <p style="color:var(--muted);margin:0;">
        No steps added yet. <a href="/edit.php?slug=<?= rawurlencode($document['slug']) ?>">Edit this document</a> to add steps.
    </p>
</div>
<?php else: ?>

<!-- ── Integrated process map ────────────────────────────────────── -->
<section class="card no-print" style="padding:0;overflow:hidden;">

    <!-- Toolbar -->
    <div style="display:flex;justify-content:space-between;align-items:center;
                padding:0.85rem 1.25rem;border-bottom:1px solid var(--border);
                flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h2 style="margin:0 0 0.1rem;">Process map</h2>
            <span class="diagram-hint">
                Scroll to zoom &middot; Drag to pan
                &middot; <strong style="color:var(--text);">Click a step to explore</strong>
            </span>
        </div>
        <div class="actions" style="gap:0.35rem;">
            <button class="btn btn-secondary btn-sm" id="btnZoomIn"  title="Zoom in">+</button>
            <span   class="zoom-level"               id="zoomLabel" >100%</span>
            <button class="btn btn-secondary btn-sm" id="btnZoomOut" title="Zoom out">−</button>
            <button class="btn btn-secondary btn-sm" id="btnFit"     title="Fit to window">Fit</button>
            <button class="btn btn-secondary btn-sm" id="btnConnStyle" title="Toggle connection style">Curved</button>
            <button class="btn btn-secondary btn-sm" id="btnFull"    title="Full screen">Full screen</button>
        </div>
    </div>

    <!-- SVG canvas + floating step detail -->
    <div class="diagram-wrap" id="diagramWrap" style="border:none;border-radius:0;padding:0;position:relative;">
        <button id="btnExitFull"
                onclick="document.exitFullscreen?.()"
                style="display:none;position:absolute;top:0.75rem;right:0.75rem;z-index:300;
                       align-items:center;gap:0.4rem;
                       background:rgba(0,0,0,0.55);color:#fff;border:none;border-radius:6px;
                       padding:0.45rem 0.8rem;font-size:0.8rem;font-weight:600;
                       font-family:var(--f-sans);cursor:pointer;backdrop-filter:blur(4px);">
            <i data-lucide="minimize" style="width:14px;height:14px;"></i>
            Exit full screen
        </button>
        <div id="swimlane-canvas"></div>
        <div id="step-detail"
             style="display:none;position:fixed;z-index:500;
                    flex-direction:column;overflow-y:hidden;
                    background:var(--surface);
                    border:1px solid var(--border);
                    border-radius:var(--r-lg);
                    box-shadow:0 6px 24px rgba(0,0,0,0.13);
                    padding:0.85rem 1rem;
                    font-size:0.875rem;
                    max-width:300px;min-width:200px;
                    pointer-events:all;"></div>
    </div>

    <!-- Colour legend -->
    <div style="padding:0.45rem 1.25rem;border-top:1px solid var(--border);
                display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;font-size:0.72rem;color:var(--muted);">
        <strong style="color:var(--text);">Key:</strong>
        <?php
        // Full colour map — stroke colours match the JS ACTION_COL object
        $allTypeColours = [
            'phone'        => ['#f59e0b', 'Phone'],
            'document'     => ['#3b82f6', 'Document'],
            'email'        => ['#10b981', 'Email'],
            'letter'       => ['#0284c7', 'Letter'],
            'wait'         => ['#94a3b8', 'Wait'],
            'meeting'      => ['#a855f7', 'Meeting'],
            'data-entry'   => ['#6366f1', 'Data entry'],
            'check'        => ['#22c55e', 'Check'],
            'escalation'   => ['#f43f5e', 'Escalation'],
            'automated'    => ['#64748b', 'Automated'],
            'api-call'     => ['#0891b2', 'API call'],
            'notification' => ['#ea580c', 'Notification'],
            'visit'        => ['#0d9488', 'Visit'],
            'payment'      => ['#be185d', 'Payment'],
            'report'       => ['#92400e', 'Report'],
        ];
        // Only show types that actually appear in this document
        $shownSwatches = [];
        foreach ($steps as $s) {
            $t = $s['action_type'] ?? 'general';
            if ($t !== 'general' && isset($allTypeColours[$t]) && !isset($shownSwatches[$t])) {
                $shownSwatches[$t] = $allTypeColours[$t];
            }
        }
        foreach ($shownSwatches as [$c, $lbl]):
        ?>
        <span style="display:flex;align-items:center;gap:0.25rem;">
            <span style="display:inline-block;width:9px;height:9px;background:<?= $c ?>;border-radius:2px;"></span>
            <?= $lbl ?>
        </span>
        <?php endforeach; ?>
        <span style="margin-left:0.4rem;padding-left:0.6rem;border-left:1px solid var(--border);display:flex;gap:0.6rem;">
            <span>&#8212; same lane</span>
            <span style="color:#3b82f6;">&#8212; handoff</span>
            <span style="color:#f59e0b;">- - loop</span>
        </span>
    </div>
</section>

<!-- Print-only: flat lane/step list (no JS required) -->
<div class="print-only-lanes" style="display:none;">
    <?php foreach ($lanes as $lane):
        $ls = array_filter($steps, fn($s) => (int)$s['lane_id'] === (int)$lane['id']);
    ?>
    <div style="margin-bottom:1rem;">
        <strong><?= h($lane['name']) ?></strong>
        <div style="margin-top:0.3rem;display:flex;flex-wrap:wrap;gap:0.3rem;">
            <?php foreach ($ls as $s): ?>
                <span style="font-size:0.8rem;border:1px solid #ccc;border-radius:4px;padding:0.15rem 0.4rem;">
                    <?= (int)$s['step_number'] ?>. <?= h($s['title']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Diagram data for JS renderer -->
<script id="swimlane-data" type="application/json"><?= $diagramJson ?></script>

<?php if ($print): ?>
<style>
    .no-print { display:none !important; }
    .print-only-lanes { display:block !important; }
</style>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
<?php endif; ?>

<script>
(function () {

/* ── SVG swimlane renderer ─────────────────────────────────────────────────
   Draws an integrated swimlane process map:
   - Horizontal lane bands (coloured by lane, auto-palette if default)
   - Step nodes inside each lane, left-to-right by step number
   - Bezier arrows between nodes (grey=same lane, blue=cross-lane, amber=loop)
   - Nodes coloured by action type for instant visual scanning
   - Click a node to see its full detail in the panel below
──────────────────────────────────────────────────────────────────────────── */

function renderSwimlane(data, canvasEl) {
    const { lanes, steps, connections } = data;
    if (!lanes.length || !steps.length) return null;

    // ── Layout constants ─────────────────────────────────────────
    const LEFT_PAD   = 20;  // left padding
    const RIGHT_PAD  = 20;  // right padding
    const LANE_HDR   = 28;  // header strip height at top of each lane
    const LANE_V_PAD = 48;  // padding above and below node rows within a lane
    const NODE_W     = 152; // node width
    const NODE_H     = 66;  // node height
    const H_GAP      = 28;  // horizontal gap between columns
    const V_GAP      = 44;  // vertical gap between rows — generous so cross-row arrows have room
    const MAX_COLS   = 7;   // steps per row before wrapping to a new row
    const TOP_PAD    = 52;  // space above all lanes (for loop-back arcs)
    const BOT_PAD    = 20;
    const ROW_H      = NODE_H + V_GAP; // height of one row of nodes (110px)

    // ── Step layout: graph-aware BFS per lane ────────────────────────────────
    // Follows the actual connection flow rather than step_number order.
    // At decision steps with multiple outgoing paths, each branch gets its own
    // row — the branch with the most steps reachable stays on the primary row,
    // exception/short branches drop to rows below.
    const stepLayout = new Map(); // step.id → { lane_row, lane_col }
    const laneRows   = new Map(); // lane.id → number of rows used

    const laneStepsOf = new Map(lanes.map(l => [l.id, []]));
    steps.forEach(s => laneStepsOf.get(s.lane_id)?.push(s));

    lanes.forEach(lane => {
        const ls    = (laneStepsOf.get(lane.id) || []).sort((a, b) => a.step_number - b.step_number);
        const lsIds = new Set(ls.map(s => s.id));

        // Within-lane adjacency
        const outEdges = new Map(ls.map(s => [s.id, []]));
        const inCount  = new Map(ls.map(s => [s.id, 0]));
        connections.forEach(c => {
            if (lsIds.has(c.from) && lsIds.has(c.to)) {
                outEdges.get(c.from).push(c.to);
                inCount.set(c.to, (inCount.get(c.to) ?? 0) + 1);
            }
        });

        // Count steps reachable within this lane from a given step.
        // Used to identify the "primary" branch (most steps = main flow).
        const reach = startId => {
            const visited = new Set(), stack = [startId];
            while (stack.length) {
                const id = stack.pop();
                if (visited.has(id)) continue;
                visited.add(id);
                (outEdges.get(id) || []).forEach(n => stack.push(n));
            }
            return visited.size;
        };

        // BFS layout — place each root's subtree before starting the next root
        const assigned   = new Map(); // step.id → { lane_row, lane_col }
        const rowNextCol = new Map(); // row → next free column in that row

        // Sort roots by step_number so the primary flow (lowest number) goes first
        const roots = ls
            .filter(s => (inCount.get(s.id) ?? 0) === 0)
            .sort((a, b) => a.step_number - b.step_number);

        // Process each root's full subtree before moving to the next root.
        // This prevents cross-lane entry points from interleaving with the main flow.
        roots.forEach(root => {
            const queue = [{ id: root.id, row: 0, col: rowNextCol.get(0) ?? 0 }];

            while (queue.length > 0) {
                const { id, row, col } = queue.shift();
                if (assigned.has(id)) continue;

                const actualCol = Math.max(col, rowNextCol.get(row) ?? 0);
                assigned.set(id, { lane_row: row, lane_col: actualCol });
                rowNextCol.set(row, actualCol + 1);

                // Sort outgoing: most-reachable first → that branch stays on the same row
                const outs = (outEdges.get(id) || [])
                    .slice()
                    .sort((a, b) => reach(b) - reach(a));

                outs.forEach((nextId, i) => {
                    if (!assigned.has(nextId)) {
                        queue.push({
                            id:  nextId,
                            row: row + i,       // primary (i=0): same row; branches: +1, +2…
                            col: actualCol + 1,
                        });
                    }
                });
            }
        });

        // Fallback: place any unvisited steps (disconnected or pure cycles) at the end
        let maxAssignedRow = [...assigned.values()].reduce((m, v) => Math.max(m, v.lane_row), 0);
        ls.forEach(s => {
            if (!assigned.has(s.id)) {
                const fbRow = maxAssignedRow + 1;
                const fbCol = rowNextCol.get(fbRow) ?? 0;
                assigned.set(s.id, { lane_row: fbRow, lane_col: fbCol });
                rowNextCol.set(fbRow, fbCol + 1);
            }
        });

        const numRows = [...assigned.values()].reduce((m, v) => Math.max(m, v.lane_row), 0) + 1;
        laneRows.set(lane.id, numRows);
        assigned.forEach((pos, stepId) => stepLayout.set(stepId, pos));
    });

    // Lane heights vary based on the number of rows each lane needs
    // height = header + top-pad + rows*NODE_H + (rows-1)*V_GAP + bot-pad
    const laneHeight = lane =>
        LANE_HDR + LANE_V_PAD + (laneRows.get(lane.id) || 1) * NODE_H
        + ((laneRows.get(lane.id) || 1) - 1) * V_GAP + LANE_V_PAD;

    // Cumulative Y start for each lane
    const laneYStart = new Map();
    let _y = TOP_PAD;
    lanes.forEach(lane => { laneYStart.set(lane.id, _y); _y += laneHeight(lane); });

    // Width based on actual columns used — BFS layout may exceed MAX_COLS
    const maxCol = [...stepLayout.values()].reduce((m, v) => Math.max(m, v.lane_col), 0);
    const totalW = LEFT_PAD + (maxCol + 1) * NODE_W + maxCol * H_GAP + RIGHT_PAD;
    const totalH = _y + BOT_PAD;

    // ── Step centre positions ─────────────────────────────────────
    const pos = new Map();
    steps.forEach(s => {
        const sp   = stepLayout.get(s.id) || { lane_row: 0, lane_col: 0 };
        const yBase = laneYStart.get(s.lane_id) || 0;
        const cx   = LEFT_PAD + sp.lane_col * (NODE_W + H_GAP) + NODE_W / 2;
        const cy   = yBase + LANE_HDR + LANE_V_PAD + sp.lane_row * ROW_H + NODE_H / 2;
        pos.set(s.id, { cx, cy, x: cx - NODE_W / 2, y: cy - NODE_H / 2,
                         lr: sp.lane_row, lc: sp.lane_col });
    });

    // ── Colour palettes ──────────────────────────────────────────

    // Step node colours by action type {fill, stroke, text}
    const ACTION_COL = {
        phone:         { fill:'#fff8e6', stroke:'#f59e0b', text:'#78350f' },
        document:      { fill:'#eff6ff', stroke:'#3b82f6', text:'#1e40af' },
        email:         { fill:'#ecfdf5', stroke:'#10b981', text:'#065f46' },
        letter:        { fill:'#f0f9ff', stroke:'#0284c7', text:'#0c4a6e' },
        wait:          { fill:'#f8fafc', stroke:'#94a3b8', text:'#475569' },
        meeting:       { fill:'#faf5ff', stroke:'#a855f7', text:'#6b21a8' },
        'data-entry':  { fill:'#eef2ff', stroke:'#6366f1', text:'#3730a3' },
        check:         { fill:'#f0fdf4', stroke:'#22c55e', text:'#14532d' },
        escalation:    { fill:'#fff1f2', stroke:'#f43f5e', text:'#9f1239' },
        automated:     { fill:'#f1f5f9', stroke:'#64748b', text:'#1e293b' },
        'api-call':    { fill:'#ecfeff', stroke:'#0891b2', text:'#164e63' },
        notification:  { fill:'#fff7ed', stroke:'#ea580c', text:'#431407' },
        visit:         { fill:'#f0fdfa', stroke:'#0d9488', text:'#134e4a' },
        payment:       { fill:'#fdf2f8', stroke:'#be185d', text:'#831843' },
        report:        { fill:'#fef3c7', stroke:'#92400e', text:'#451a03' },
        general:       { fill:'#f8fafc', stroke:'#cbd5e1', text:'#374151' },
    };
    // These step types use fixed colours regardless of action type
    const TYPE_OVERRIDE = {
        start:      { fill:'#dcfce7', stroke:'#16a34a', text:'#14532d' },
        end:        { fill:'#fef2f2', stroke:'#dc2626', text:'#991b1b' },
        subprocess: { fill:'#eff6ff', stroke:'#2563eb', text:'#1e3a8a' },
        parallel:   { fill:'#fdf4ff', stroke:'#9333ea', text:'#581c87' },
    };
    // ACTION_ICON_NODES — hardcoded path arrays, defined below renderSwimlane.

    // ── SVG helpers ──────────────────────────────────────────────
    const NS  = 'http://www.w3.org/2000/svg';
    const el  = (tag, attrs) => {
        const e = document.createElementNS(NS, tag);
        if (attrs) Object.entries(attrs).forEach(([k, v]) => v != null && e.setAttribute(k, v));
        return e;
    };
    const txt = (x, y, content, attrs) => {
        const t = el('text', { x, y, 'text-anchor':'middle', 'dominant-baseline':'middle', ...attrs });
        t.textContent = content;
        return t;
    };

    // Monochrome alternating lanes: first lane darker, second lighter, repeating.
    // Stored lane colours are ignored — consistent neutral palette across all diagrams.
    function parseLaneColor(hex, idx) {
        const fill   = idx % 2 === 0 ? '#e5e7eb' : '#f3f4f6';
        const stroke = '#374151'; // consistent dark charcoal for all lane labels
        return { fill, stroke };
    }

    const svg = el('svg', { width: totalW, height: totalH, viewBox: `0 0 ${totalW} ${totalH}` });

    // Subtle off-white canvas background
    svg.appendChild(el('rect', { x:0, y:0, width:totalW, height:totalH, fill:'#f6f8fa' }));

    // ── Arrow markers ─────────────────────────────────────────────
    const defs = el('defs');
    // markerUnits="userSpaceOnUse" gives a fixed pixel size independent of
    // stroke-width. refX=0 places the arrowhead BACK at the path endpoint,
    // so the stroke ends where the arrowhead body starts — no overlap.
    const mkMarker = (id, color) => {
        const m = el('marker', {
            id, markerUnits:'userSpaceOnUse',
            markerWidth:10, markerHeight:7,
            refX:0, refY:3.5, orient:'auto',
        });
        m.appendChild(el('polygon', { points:'0 0,10 3.5,0 7', fill:color }));
        return m;
    };
    defs.appendChild(mkMarker('aFwd',   '#64748b'));
    defs.appendChild(mkMarker('aCross', '#3b82f6'));
    defs.appendChild(mkMarker('aBack',  '#f59e0b'));
    svg.appendChild(defs);

    // ── Lane bands ────────────────────────────────────────────────
    // Each lane has a variable height based on its row count.
    lanes.forEach((lane, i) => {
        const { fill, stroke } = parseLaneColor(lane.color, i);
        const y  = laneYStart.get(lane.id) || 0;
        const lh = laneHeight(lane);

        const hr = parseInt(stroke.slice(1,3), 16);
        const hg = parseInt(stroke.slice(3,5), 16);
        const hb = parseInt(stroke.slice(5,7), 16);

        // Full band background
        svg.appendChild(el('rect', { x:0, y, width:totalW, height:lh, fill }));

        // Header strip
        svg.appendChild(el('rect', {
            x:0, y, width:totalW, height:LANE_HDR,
            fill: `rgba(${hr},${hg},${hb},0.13)`,
        }));
        svg.appendChild(el('line', {
            x1:0, y1:y+LANE_HDR, x2:totalW, y2:y+LANE_HDR,
            stroke:`rgba(${hr},${hg},${hb},0.25)`, 'stroke-width':1,
        }));

        // Row separators (subtle dashed lines between rows within a lane)
        const nr = laneRows.get(lane.id) || 1;
        for (let r = 1; r < nr; r++) {
            const ry = y + LANE_HDR + LANE_V_PAD + r * ROW_H - V_GAP / 2;
            svg.appendChild(el('line', {
                x1: LEFT_PAD, y1: ry, x2: totalW - RIGHT_PAD, y2: ry,
                stroke: `rgba(${hr},${hg},${hb},0.18)`,
                'stroke-width': 1, 'stroke-dasharray': '4 4',
            }));
        }

        // Bottom band separator
        svg.appendChild(el('line', {
            x1:0, y1:y+lh, x2:totalW, y2:y+lh,
            stroke:'#d1d5db', 'stroke-width':1,
        }));

        // Lane name in header
        const lbl = el('text', {
            x: LEFT_PAD, y: y + LANE_HDR / 2,
            'text-anchor': 'start', 'dominant-baseline': 'middle',
            'font-family': "'IBM Plex Serif', Georgia, serif",
            'font-size': 14, 'font-weight': 600, 'letter-spacing': '0.01em',
            fill: stroke,
        });
        lbl.textContent = lane.name;
        svg.appendChild(lbl);
    });

    // ── Connections (paths drawn before nodes; labels queued for after) ──
    const stepById   = new Map(steps.map(s => [s.id, s]));
    const labelQueue = []; // filled during connection loop, rendered after nodes
    const connEls    = []; // { from, to, pathEl, labelEls[] } — for highlight

    connections.forEach(conn => {
        const fp = pos.get(conn.from);
        const tp = pos.get(conn.to);
        if (!fp || !tp) return;

        const fs  = stepById.get(conn.from);
        const ts  = stepById.get(conn.to);

        const sameLane = fs && ts && fs.lane_id === ts.lane_id;
        const sameRow  = sameLane && fp.lr === tp.lr;
        const dropDown = sameLane && fp.lr < tp.lr;
        const loopUp   = sameLane && fp.lr > tp.lr;
        // isBack only applies within the same lane — cross-lane connections
        // are always forward flow, never backward arcs.
        const isBack  = (sameRow && fp.cx > tp.cx + 4) || loopUp;
        const isCross = !isBack && !sameLane;
        const isWrap  = dropDown && tp.lc < fp.lc;

        let d, dStraight, stroke, markerId, dash = null;
        let lx, ly;
        const ARROW_LEN = 10;

        if (isBack) {
            const laneY   = laneYStart.get(fs?.lane_id) ?? TOP_PAD;
            const minRow  = Math.min(fp.lr ?? 0, tp.lr ?? 0);
            const rowTopY = laneY + LANE_HDR + LANE_V_PAD + minRow * ROW_H - V_GAP * 0.5;
            const arcY    = Math.max(laneY + LANE_HDR + 6, rowTopY);
            d         = `M${fp.cx},${fp.y} C${fp.cx},${arcY} ${tp.cx},${arcY} ${tp.cx},${tp.y - ARROW_LEN - 1}`;
            dStraight = `M${fp.cx},${fp.y} L${fp.cx},${arcY} L${tp.cx},${arcY} L${tp.cx},${tp.y - ARROW_LEN - 1}`;
            stroke    = '#f59e0b'; markerId = 'aBack'; dash = '6 3';
            lx = (fp.cx + tp.cx) / 2; ly = arcY - 12;

        } else if (isWrap) {
            const x1     = fp.cx + NODE_W / 2;
            const x2     = tp.cx - NODE_W / 2 - ARROW_LEN - 1;
            const rightM = totalW - RIGHT_PAD / 2 + 4;
            const r      = 10;
            d = `M${x1},${fp.cy}` +
                ` L${rightM-r},${fp.cy} Q${rightM},${fp.cy} ${rightM},${fp.cy+r}` +
                ` L${rightM},${tp.cy-r} Q${rightM},${tp.cy} ${rightM-r},${tp.cy}` +
                ` L${x2},${tp.cy}`;
            dStraight = `M${x1},${fp.cy} L${rightM},${fp.cy} L${rightM},${tp.cy} L${x2},${tp.cy}`;
            stroke = '#64748b'; markerId = 'aFwd';
            lx = rightM + 6; ly = (fp.cy + tp.cy) / 2;

        } else if (dropDown) {
            const bx1 = fp.cx, by1 = fp.y + NODE_H;
            const bx2 = tp.cx, by2 = tp.y - ARROW_LEN - 1;
            const mid = (by1 + by2) / 2;
            d         = `M${bx1},${by1} C${bx1},${mid} ${bx2},${mid} ${bx2},${by2}`;
            dStraight = `M${bx1},${by1} L${bx1},${mid} L${bx2},${mid} L${bx2},${by2}`;
            stroke = '#64748b'; markerId = 'aFwd';
            lx = bx1 + 8; ly = mid;

        } else if (isCross) {
            const laneYSrc = laneYStart.get(fs?.lane_id) ?? 0;
            const laneYTgt = laneYStart.get(ts?.lane_id) ?? 0;
            let bx1, by1, bx2, by2;
            if (laneYTgt >= laneYSrc) {
                bx1 = fp.cx; by1 = fp.y + NODE_H;
                bx2 = tp.cx; by2 = tp.y - ARROW_LEN - 1;
            } else {
                bx1 = fp.cx; by1 = fp.y;
                bx2 = tp.cx; by2 = tp.y + NODE_H + ARROW_LEN;
            }
            const mid  = (by1 + by2) / 2;
            d         = `M${bx1},${by1} C${bx1},${mid} ${bx2},${mid} ${bx2},${by2}`;
            // Straight: keep the horizontal crossing segment inside the SOURCE lane's
            // padding so it never falls on or between lane boundaries.
            // Downward → elbow in source lane's bottom padding; upward → top padding.
            const elbowY = laneYTgt >= laneYSrc
                ? by1 + LANE_V_PAD * 0.55   // inside source lane bottom buffer
                : by1 - LANE_V_PAD * 0.55;  // inside source lane top buffer
            dStraight = `M${bx1},${by1} L${bx1},${elbowY} L${bx2},${elbowY} L${bx2},${by2}`;
            stroke = '#3b82f6'; markerId = 'aCross';
            lx = (bx1 + bx2) / 2 + 8; ly = elbowY;

        } else {
            // Same-row forward — y1 === y2 so the bezier is already straight
            const x1 = fp.cx + NODE_W / 2, y1 = fp.cy;
            const x2 = tp.cx - NODE_W / 2 - ARROW_LEN - 1, y2 = tp.cy;
            const mx = (x1 + x2) / 2;
            d         = `M${x1},${y1} C${mx},${y1} ${mx},${y2} ${x2},${y2}`;
            dStraight = `M${x1},${y1} L${x2},${y2}`;
            stroke = '#64748b'; markerId = 'aFwd';
            lx = fp.cx + (tp.cx - fp.cx) * 0.35;
            ly = Math.min(fp.y, tp.y) - 10;
        }

        const pathEl = el('path', {
            d, fill:'none', stroke,
            'stroke-width':    (isCross || isWrap || dropDown) ? 2 : 1.5,
            'stroke-dasharray': dash,
            'marker-end':      `url(#${markerId})`,
            opacity:           0.8,
        });
        svg.appendChild(pathEl);
        connEls.push({ from: conn.from, to: conn.to, pathEl, labelEls: [], dCurved: d, dStraight });

        if (conn.label) {
            labelQueue.push({ lx, ly, text: conn.label, stroke, ci: connEls.length - 1 });
        }
    });

    // ── Step nodes ────────────────────────────────────────────────
    const clickHandlers = [];
    const nodeGroups    = new Map(); // step.id → SVG <g> element

    steps.forEach(step => {
        const p = pos.get(step.id);
        if (!p) return;
        const { cx, cy, x, y } = p;

        const col = TYPE_OVERRIDE[step.step_type]
            || ACTION_COL[step.action_type]
            || ACTION_COL.general;

        const g = el('g', { style:'cursor:pointer;' });

        // Native tooltip (shown on hover by browser)
        const tip = document.createElementNS(NS, 'title');
        tip.textContent = `${step.step_number}. ${step.title}${step.description ? '\n\n' + step.description : ''}`;
        g.appendChild(tip);

        // Node shape
        if (step.step_type === 'decision' || step.step_type === 'parallel') {
            g.appendChild(el('polygon', {
                points: `${cx},${y} ${x+NODE_W},${cy} ${cx},${y+NODE_H} ${x},${cy}`,
                fill: col.fill, stroke: col.stroke, 'stroke-width': 1.5,
            }));
            // Parallel gateway: + symbol inside the diamond
            if (step.step_type === 'parallel') {
                g.appendChild(txt(cx, cy + 1, '+', {
                    'font-size': 20, 'font-weight': 700,
                    fill: col.stroke, opacity: 0.75,
                }));
            }
        } else if (step.step_type === 'start' || step.step_type === 'end') {
            g.appendChild(el('rect', { x, y, width:NODE_W, height:NODE_H, rx:NODE_H/2,
                fill:col.fill, stroke:col.stroke, 'stroke-width':2 }));
        } else {
            g.appendChild(el('rect', { x, y, width:NODE_W, height:NODE_H, rx:7,
                fill:col.fill, stroke:col.stroke, 'stroke-width':1.5 }));
            // Subprocess: small ⊕ badge at bottom-centre (conventional notation)
            if (step.step_type === 'subprocess') {
                const bw = 18, bh = 14;
                g.appendChild(el('rect', {
                    x: cx - bw / 2, y: y + NODE_H - bh - 3,
                    width: bw, height: bh, rx: 3,
                    fill: 'white', stroke: col.stroke, 'stroke-width': 1.2,
                }));
                g.appendChild(txt(cx, y + NODE_H - 3 - bh / 2, '+', {
                    'font-size': 11, 'font-weight': 700, fill: col.stroke,
                }));
            }
        }

        // Step number badge — centred for pill nodes, top-left for rectangles and diamonds.
        const isPill = step.step_type === 'start' || step.step_type === 'end';
        const badgeX = isPill ? cx - 11 : x + 5;
        const badgeTX = isPill ? cx      : x + 16;
        const badgeY  = isPill ? y + 7   : y + 5;
        const badgeTY = isPill ? y + 14  : y + 12;
        g.appendChild(el('rect', { x:badgeX, y:badgeY, width:22, height:14, rx:3, fill:col.stroke, opacity:0.2 }));
        g.appendChild(txt(badgeTX, badgeTY, step.step_number, {
            'font-family':"'IBM Plex Sans',system-ui,sans-serif",
            'font-size':9, 'font-weight':700, fill:col.text,
        }));

        // Title — simple word-wrap, max 2 lines
        const words = step.title.split(' ');
        const lines = [];
        let cur = '';
        words.forEach(w => {
            const test = cur ? cur + ' ' + w : w;
            if (test.length > 20 && cur) { lines.push(cur); cur = w; }
            else cur = test;
        });
        if (cur) lines.push(cur);
        const dl = lines.slice(0, 2);
        if (lines.length > 2) dl[1] = (dl[1] || '').slice(0, 17) + '…';

        const LH  = 13;
        const ty0 = cy - (dl.length - 1) * LH / 2 + 4;
        dl.forEach((line, li) => {
            g.appendChild(txt(cx, ty0 + li * LH, line, {
                'font-family':"'IBM Plex Sans',system-ui,sans-serif", 'font-size':11, fill:col.text,
            }));
        });

        // Action icon — shown on task and subprocess nodes
        const iconDs = ACTION_ICON_NODES[step.action_type];
        if (iconDs && (step.step_type === 'task' || step.step_type === 'subprocess')) {
            const iconPx = 14, scale = iconPx / 24;
            const ig = el('g', {
                transform: `translate(${x + NODE_W - iconPx - 5},${y + NODE_H - iconPx - 5}) scale(${scale.toFixed(5)})`,
                stroke: col.text, 'stroke-width': '2',
                'stroke-linecap': 'round', 'stroke-linejoin': 'round',
                fill: 'none', opacity: '0.65',
            });
            iconDs.forEach(d => ig.appendChild(el('path', { d })));
            g.appendChild(ig);
        }

        svg.appendChild(g);
        nodeGroups.set(step.id, g);
        clickHandlers.push({ el: g, step });
    });

    // ── Connection labels (rendered last so they sit above all nodes) ────
    labelQueue.forEach(({ lx, ly, text, stroke, ci }) => {
        const lw = text.length * 6.2 + 14;
        const rectEl = el('rect', {
            x: lx - lw / 2, y: ly - 9, width: lw, height: 17,
            rx: 3, fill: 'white', stroke, 'stroke-width': 0.5, opacity: 0.92,
        });
        const textEl = txt(lx, ly, text, {
            'font-family': "'IBM Plex Sans',system-ui,sans-serif",
            'font-size': 10, fill: stroke, 'font-weight': 500,
        });
        svg.appendChild(rectEl);
        svg.appendChild(textEl);
        if (ci !== undefined && connEls[ci]) {
            connEls[ci].labelEls.push(rectEl, textEl);
        }
    });

    canvasEl.appendChild(svg);
    return { svg, clickHandlers, nodeGroups, connEls, stepLayout };
}

// ── Hardcoded Lucide icon paths for diagram nodes ─────────────────────────────
// Each value is an array of SVG path `d` strings (viewBox 0 0 24 24, stroke).
// Hardcoded to avoid any Lucide UMD API dependency.
const ACTION_ICON_NODES = {
    'phone':        ['M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.36C1.6 2.29 2.47 1.42 3.54 1.5H6.54a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.57 9.32a16 16 0 0 0 6.11 6.11l1.29-1.29a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z'],
    'document':     ['M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z','M14 2v4a2 2 0 0 0 2 2h4','M10 9H8','M16 13H8','M16 17H8'],
    'email':        ['M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z','M22 6l-10 7L2 6'],
    'letter':       ['M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6z','M22 10l-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 10'],
    'wait':         ['M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z','M12 6v6l4 2'],
    'meeting':      ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2','M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z','M23 21v-2a4 4 0 0 0-3-3.87','M16 3.13a4 4 0 0 1 0 7.75'],
    'data-entry':   ['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z','M2 10h20','M7 16h10'],
    'check':        ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z','M9 12l2 2 4-4'],
    'escalation':   ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z','M16 12l-4-4-4 4','M12 16V8'],
    'automated':    ['M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z','M10 9h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z','M15 2v2 M9 2v2 M15 20v2 M9 20v2 M2 9h2 M2 15h2 M20 9h2 M20 15h2'],
    'api-call':     ['M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.57-2','m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.89-4.06','m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8'],
    'notification': ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9','M13.73 21a2 2 0 0 1-3.46 0'],
    'visit':        ['M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z','M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
    'payment':      ['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z','M2 10h20'],
    'report':       ['M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2','M9 2h6a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z','M12 11h4','M12 16h4','M8 11h.01','M8 16h.01'],
};

// ── Bootstrap ────────────────────────────────────────────────────────────────
const dataEl = document.getElementById('swimlane-data');
const canvas = document.getElementById('swimlane-canvas');
const detail = document.getElementById('step-detail');
const wrap   = document.getElementById('diagramWrap');
const label  = document.getElementById('zoomLabel');

if (!dataEl || !canvas) return;

const data = JSON.parse(dataEl.textContent);
if (!data.steps || !data.steps.length) return;

const result = renderSwimlane(data, canvas);
if (!result) return;

const { svg, clickHandlers, nodeGroups, connEls, stepLayout: mainStepLayout } = result;

// Escape HTML for safe innerHTML use
const esc = s => String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');

const ACTION_LABELS = {
    phone:'Phone call', document:'Document', email:'Email',
    wait:'Wait / hold', meeting:'Meeting / approval',
    'data-entry':'Data entry', check:'Check / review',
    escalation:'Escalation', general:'',
};

// Stroke colours for action type icons in the popup (matches ACTION_COL in renderer)
const ACTION_ICON_COLORS = {
    phone:'#f59e0b', document:'#3b82f6', email:'#10b981', letter:'#0284c7',
    wait:'#94a3b8', meeting:'#a855f7', 'data-entry':'#6366f1', check:'#22c55e',
    escalation:'#f43f5e', automated:'#64748b', 'api-call':'#0891b2',
    notification:'#ea580c', visit:'#0d9488', payment:'#be185d', report:'#92400e',
};

function popupIconHtml(actionType) {
    const paths = ACTION_ICON_NODES[actionType];
    const color  = ACTION_ICON_COLORS[actionType];
    if (!paths || !color) return '';
    const pHtml = paths.map(d => `<path d="${d}"/>`).join('');
    return `<svg width="15" height="15" viewBox="0 0 24 24" fill="none"
        stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        style="flex-shrink:0;vertical-align:middle">${pHtml}</svg>`;
}

// ── Mini-flow diagram ─────────────────────────────────────────────────────────
// Renders a compact inline SVG showing the related steps and connections
// without lane bands or empty space — used in the card panel header.
function renderMiniFlow(relSteps, clickedId, relConns, mainLayout) {
    if (relSteps.length < 2) return '';

    // Mirror the main diagram spatial layout exactly.
    // Each step's position comes from mainLayout (stepLayout from renderSwimlane):
    //   lr = lane_row within the lane  (captures decision branches on separate rows)
    //   lc = lane_col within the lane
    // Row in mini = compound key (lane_index, lane_row) → compressed to 0,1,2…
    // Col in mini = lane_col → compressed to 0,1,2…
    const allLaneOrder = data.lanes.map(l => l.id);
    const relLaneIds   = [...new Set(relSteps.map(s => s.lane_id))]
        .sort((a, b) => allLaneOrder.indexOf(a) - allLaneOrder.indexOf(b));
    const laneIdx = new Map(relLaneIds.map((lid, i) => [lid, i]));

    const rowKeyOf = s => {
        const li = laneIdx.get(s.lane_id) ?? 0;
        const lr = mainLayout?.get(s.id)?.lane_row ?? 0;
        return `${li},${lr}`;
    };
    const rowKeys = [...new Set(relSteps.map(rowKeyOf))].sort((a, b) => {
        const [li1, lr1] = a.split(',').map(Number);
        const [li2, lr2] = b.split(',').map(Number);
        return li1 !== li2 ? li1 - li2 : lr1 - lr2;
    });
    const miniRowOf = new Map(rowKeys.map((k, i) => [k, i]));

    const colKeys = [...new Set(relSteps.map(s => mainLayout?.get(s.id)?.lane_col ?? 0))]
        .sort((a, b) => a - b);
    const miniColOf = new Map(colKeys.map((k, i) => [k, i]));

    const layout = new Map();
    relSteps.forEach(s => layout.set(s.id, {
        row: miniRowOf.get(rowKeyOf(s)) ?? 0,
        col: miniColOf.get(mainLayout?.get(s.id)?.lane_col ?? 0) ?? 0,
    }));

    const NW = 48, NH = 28, CG = 28, RG = 36, P = 8;
    const cols = [...layout.values()].reduce((m, v) => Math.max(m, v.col), 0) + 1;
    const rows = [...layout.values()].reduce((m, v) => Math.max(m, v.row), 0) + 1;
    const W    = P + cols * NW + (cols - 1) * CG + P;
    const H    = P + rows * NH + (rows - 1) * RG + P;

    const nx = c  => P + c * (NW + CG);
    const ny = r  => P + r * (NH + RG);
    const cx = c  => nx(c) + NW / 2;
    const cy = r  => ny(r) + NH / 2;

    // Monochrome — shape only, no action-type colour
    const FILL   = '#f3f4f6';
    const STROKE = '#9ca3af';
    const CLICKED_STROKE = '#374151';

    const LINE_C = '#9ca3af'; // single neutral colour for all connectors
    const mk = c => `<marker id="mf${c.replace('#','')}" markerWidth="7" markerHeight="5"
        refX="6" refY="2.5" orient="auto"><polygon points="0 0,7 2.5,0 5" fill="${c}"/></marker>`;

    let defs = `<defs>${mk(LINE_C)}</defs>`;
    let lines = '', shapes = '';

    // Connections — all the same colour, loop-backs dashed
    relConns.forEach(c => {
        const f = layout.get(c.from), t = layout.get(c.to);
        if (!f || !t) return;
        const isBack = f.col > t.col || (f.col === t.col && f.row >= t.row);
        const da = isBack ? '4 2' : 'none';
        let pathD, lx, ly;
        if (f.row === t.row) {
            // Same row — straight horizontal
            const x1 = nx(f.col) + NW, y1 = cy(f.row);
            const x2 = nx(t.col) - 6,  y2 = cy(t.row);
            pathD = `M${x1},${y1} L${x2},${y2}`;
            lx = (x1+x2)/2; ly = y1 - 8;
        } else if (f.col === t.col) {
            // Same column — straight vertical
            const x1 = cx(f.col), y1 = ny(f.row) + NH;
            const x2 = cx(t.col), y2 = ny(t.row) - 6;
            pathD = `M${x1},${y1} L${x2},${y2}`;
            lx = x1 + 4; ly = (y1+y2)/2;
        } else {
            // Different row and column — orthogonal L-shape
            // Exit bottom of source → across at midpoint y → down to target
            const sx = cx(f.col), sy = ny(f.row) + NH;
            const tx_ = cx(t.col), ty_ = ny(t.row) - 6;
            const midY = (sy + ty_) / 2;
            pathD = `M${sx},${sy} L${sx},${midY} L${tx_},${midY} L${tx_},${ty_}`;
            lx = (sx + tx_) / 2; ly = midY - 8;
        }
        lines += `<path d="${pathD}" fill="none"
            stroke="${LINE_C}" stroke-width="1.5" stroke-dasharray="${da}"
            marker-end="url(#mf${LINE_C.replace('#','')})"/>`;
        // Labels omitted — step numbers in the card list below serve as the key
    });

    // Nodes
    relSteps.forEach(s => {
        const pos = layout.get(s.id);
        if (!pos) return;
        const x = nx(pos.col), y = ny(pos.row);
        const isClicked = s.id === clickedId;
        const st = isClicked ? CLICKED_STROKE : STROKE;
        const sw = isClicked ? 2 : 1;

        if (s.step_type === 'decision' || s.step_type === 'parallel') {
            const mx = x + NW/2, my = y + NH/2;
            shapes += `<polygon points="${mx},${y} ${x+NW},${my} ${mx},${y+NH} ${x},${my}"
                fill="${FILL}" stroke="${st}" stroke-width="${sw}"/>`;
        } else {
            const rx = (s.step_type==='start'||s.step_type==='end') ? NH/2 : 3;
            shapes += `<rect x="${x}" y="${y}" width="${NW}" height="${NH}" rx="${rx}"
                fill="${FILL}" stroke="${st}" stroke-width="${sw}"/>`;
        }

        // Step number centred in the shape — title is in the card list below
        shapes += `<text x="${cx(pos.col)}" y="${cy(pos.row)+1}"
            text-anchor="middle" dominant-baseline="middle"
            font-family="IBM Plex Sans,sans-serif"
            font-size="10" font-weight="700" fill="${st}">${s.step_number}</text>`;
    });

    // Scale to fit within both max-width and max-height simultaneously
    // (same logic as CSS object-fit:contain) — never overflows the panel.
    const MAX_H = 140;
    const MAX_W = 278; // panel inner width (310px - padding)
    const scale  = Math.min(MAX_W / W, MAX_H / H);
    const rW     = Math.round(W * scale);
    const rH     = Math.round(H * scale);

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}"
        width="${rW}" height="${rH}"
        style="display:block;max-width:100%;">${defs}${lines}${shapes}</svg>`;
}

// ── Focus highlight ───────────────────────────────────────────────────────────
let activeStepId = null;

function applyHighlight(stepId) {
    activeStepId = stepId;
    const related = new Set([stepId]);
    connEls.forEach(({ from, to }) => {
        if (from === stepId) related.add(to);
        if (to   === stepId) related.add(from);
    });
    nodeGroups.forEach((g, id) => {
        if (related.has(id)) { g.style.opacity = '1';    g.style.filter = ''; }
        else                  { g.style.opacity = '0.12'; g.style.filter = 'grayscale(100%)'; }
    });
    connEls.forEach(({ from, to, pathEl, labelEls }) => {
        const active = from === stepId || to === stepId;
        pathEl.style.opacity = active ? '0.9' : '0.06';
        labelEls.forEach(el => { el.style.opacity = active ? '1' : '0'; });
    });
    return related;
}

function clearHighlight() {
    activeStepId = null;
    nodeGroups.forEach(g => { g.style.opacity = ''; g.style.filter = ''; });
    connEls.forEach(({ pathEl, labelEls }) => {
        pathEl.style.opacity = '';
        labelEls.forEach(el => { el.style.opacity = ''; });
    });
}

// Scroll (and zoom mildly if needed) so highlighted steps are visible.
// clickedId: the step actually clicked — used as fallback when span is too large.
function scrollToHighlighted(related, clickedId) {
    const padding   = 150; // clears fixed header (~130px) + breathing room
    const vpH       = window.innerHeight;
    const available = vpH - padding * 2;

    const getRange = () => {
        let minY = Infinity, maxY = -Infinity;
        related.forEach(id => {
            const g = nodeGroups.get(id);
            if (!g) return;
            const r = g.getBoundingClientRect();
            minY = Math.min(minY, r.top    + window.scrollY);
            maxY = Math.max(maxY, r.bottom + window.scrollY);
        });
        return { minY, maxY };
    };

    const { minY, maxY } = getRange();
    if (minY === Infinity) return;

    const rangeH     = maxY - minY;
    const neededZoom = zoom * available / rangeH;

    if (rangeH > available && neededZoom < zoom * 0.65) {
        // Steps span too much — zooming out would make nodes tiny and leave
        // large empty lanes visible. Instead, centre on the CLICKED step and
        // let the card panel explain what's connected elsewhere.
        const g = nodeGroups.get(clickedId);
        if (g) {
            const r = g.getBoundingClientRect();
            window.scrollTo({ top: Math.max(0, r.top + window.scrollY - vpH / 2 + 40), behavior: 'smooth' });
        }
        return;
    }

    if (rangeH > available) {
        // Mild zoom-out (≤35% reduction) — acceptable
        applyZoom(Math.max(0.2, neededZoom));
        requestAnimationFrame(() => {
            const { minY: y0, maxY: y1 } = getRange();
            const h = y1 - y0;
            window.scrollTo({ top: Math.max(0, y0 - Math.max(padding, (vpH - h) / 2)), behavior: 'smooth' });
        });
    } else {
        // All steps already fit — just centre the group in the viewport
        window.scrollTo({
            top:      Math.max(0, minY - (vpH - rangeH) / 2),
            behavior: 'smooth',
        });
    }
}

// ── Draggable detail panel ────────────────────────────────────────────────────
// Mousedown on the [data-drag-handle] header starts a drag; the panel can
// then be repositioned anywhere over the diagram.
let _dd = false, _ddEnded = false, _dox = 0, _doy = 0;

detail?.addEventListener('mousedown', e => {
    if (!e.target.closest('[data-drag-handle]')) return;
    if (e.button !== 0) return;
    _dd = true;
    const r = detail.getBoundingClientRect();
    _dox = e.clientX - r.left;
    _doy = e.clientY - r.top;
    detail.style.cursor = 'grabbing';
    e.preventDefault();  // prevent wrap drag-to-pan from activating
    e.stopPropagation();
});

document.addEventListener('mousemove', e => {
    if (!_dd || !detail) return;
    // Panel is position:fixed — drag in viewport coordinates
    detail.style.left = Math.max(0, e.clientX - _dox) + 'px';
    detail.style.top  = Math.max(0, e.clientY - _doy) + 'px';
});

document.addEventListener('mouseup', () => {
    if (!_dd) return;
    _dd = false;
    _ddEnded = true;
    setTimeout(() => { _ddEnded = false; }, 80); // suppress the following click
    if (detail) detail.style.cursor = '';
});

// Render a single step as a compact card row
function stepCardHtml(s, isLast) {
    const sys = s.systems
        ? s.systems.split(', ').map(t => `<span class="sys-tag">${esc(t.trim())}</span>`).join(' ')
        : '';
    const lbl  = ACTION_LABELS[s.action_type] || '';
    const icon = popupIconHtml(s.action_type);
    return `<div style="display:flex;gap:0.55rem;align-items:flex-start;
                        padding:0.55rem 0;${isLast ? '' : 'border-bottom:1px solid var(--border);'}">
        <span style="font-size:0.68rem;font-weight:700;background:#e2e8f0;color:#475569;
                     border-radius:3px;padding:0.1rem 0.3rem;flex-shrink:0;margin-top:0.15rem;">
            ${s.step_number}</span>
        <div>
            <div style="font-weight:600;font-size:0.82rem;line-height:1.3;">${esc(s.title)}</div>
            ${s.description ? `<p style="margin:0.2rem 0 0;color:var(--muted);font-size:0.76rem;line-height:1.4;">${esc(s.description)}</p>` : ''}
            ${lbl || sys ? `<div style="margin-top:0.3rem;display:flex;flex-wrap:wrap;gap:0.25rem;align-items:center;">
                ${lbl ? `<span class="badge" style="display:inline-flex;gap:0.25rem;align-items:center;font-size:0.7rem;">${icon}${esc(lbl)}</span>` : ''}
                ${sys}
            </div>` : ''}
        </div>
    </div>`;
}

// Step click → highlight + multi-step card list
clickHandlers.forEach(({ el: g, step }) => {
    g.addEventListener('click', e => {
        if (!wrap) return;
        e.stopPropagation();

        if (activeStepId === step.id) {
            clearHighlight();
            if (detail) detail.style.display = 'none';
            return;
        }
        const related = applyHighlight(step.id);
        scrollToHighlighted(related, step.id);

        if (!detail) return;

        // Gather all related steps, sorted by step_number
        const relSteps = data.steps
            .filter(s => related.has(s.id))
            .sort((a, b) => a.step_number - b.step_number);

        const count    = relSteps.length;
        const cards    = relSteps.map((s, i) => stepCardHtml(s, i === count - 1)).join('');

        // Mini flow: connections between related steps only
        const relConns = data.connections.filter(c => related.has(c.from) && related.has(c.to));
        const miniSvg  = renderMiniFlow(relSteps, step.id, relConns, mainStepLayout);

        // Panel uses flex-column so the card list fills all remaining space
        // without a competing hardcoded max-height — works at any viewport size.
        detail.innerHTML = `
            <div data-drag-handle style="display:flex;justify-content:space-between;align-items:center;
                        padding-bottom:0.45rem;margin-bottom:0.1rem;
                        border-bottom:2px solid var(--border);
                        cursor:grab;user-select:none;flex-shrink:0;">
                <span style="display:flex;align-items:center;gap:0.4rem;">
                    <i data-lucide="grip-vertical" style="width:0.9rem;height:0.9rem;color:var(--muted);flex-shrink:0;"></i>
                    <span style="font-size:0.7rem;font-weight:700;color:var(--muted);
                                 text-transform:uppercase;letter-spacing:0.06em;">
                        ${count} step${count !== 1 ? 's' : ''} in focus</span>
                </span>
                <button id="closeDetail" style="background:none;border:none;cursor:pointer;
                        color:var(--muted);padding:0;line-height:1;display:flex;">
                    <i data-lucide="x" style="width:1rem;height:1rem;"></i></button>
            </div>
            ${miniSvg ? `<div style="padding:0.6rem 0;border-bottom:1px solid var(--border);
                                     background:var(--bg);border-radius:6px;margin-bottom:0.4rem;flex-shrink:0;">
                ${miniSvg}</div>` : ''}
            <div style="overflow-y:auto;min-height:0;flex:1;">${cards}</div>`;

        // Position the panel within the VISIBLE VIEWPORT, not the full wrap height.
        // Since the page scrolls (not the wrap), we work in viewport coordinates
        const PANEL_W  = 310;
        const vpW      = window.innerWidth;
        const vpH      = window.innerHeight;
        const TOP_MIN  = 150;
        const panelOpen = detail.style.display === 'flex';

        if (!panelOpen) {
            // First click — position the panel in the opposite corner from the click
            const vpX = e.clientX > vpW / 2
                ? Math.max(10, e.clientX - PANEL_W - 20)
                : Math.min(vpW - PANEL_W - 10, e.clientX + 20);
            const vpY = Math.max(TOP_MIN,
                e.clientY > vpH * 0.6
                    ? Math.max(TOP_MIN, e.clientY - 420)
                    : e.clientY + 20
            );
            const availH = vpH - vpY - 20;
            detail.style.left          = Math.max(0, vpX) + 'px';
            detail.style.top           = vpY + 'px';
            detail.style.width         = PANEL_W + 'px';
            detail.style.height        = availH + 'px'; // fill available height so card list expands
            detail.style.maxHeight     = availH + 'px';
            detail.style.overflowY     = 'hidden';
            detail.style.flexDirection = 'column';
        }
        // Subsequent clicks on different steps: keep position, just refresh content.

        detail.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons({ nameAttr: 'data-lucide', nodes: [detail] });

        document.getElementById('closeDetail')
            ?.addEventListener('click', () => { detail.style.display = 'none'; detail.style.height = ''; clearHighlight(); });
    });
});

// Click on background → dismiss panel and clear highlight
// (but not if the user just finished dragging the panel)
wrap?.addEventListener('click', e => {
    if (_ddEnded) return; // suppress click that fires immediately after drag ends
    if (e.target === wrap || e.target.closest('#swimlane-canvas svg')) {
        if (detail) detail.style.display = 'none';
        clearHighlight();
    }
});

// ── Zoom / pan ───────────────────────────────────────────────────────────────
const naturalW = parseFloat(svg.getAttribute('width'));
const naturalH = parseFloat(svg.getAttribute('height'));
let zoom = 1;

function applyZoom(z) {
    zoom = Math.max(0.1, Math.min(6, z));
    const svgW = Math.round(naturalW * zoom);
    const svgH = Math.round(naturalH * zoom);
    svg.setAttribute('width',  svgW);
    svg.setAttribute('height', svgH);
    if (label) label.textContent = Math.round(zoom * 100) + '%';
    // Size the wrap to exactly the SVG so the page scroll bar is used, not the container's
    if (wrap && !document.fullscreenElement) {
        wrap.style.height = svgH + 'px';
        wrap.style.width  = '100%'; // always fill the card width
    }
}

function fitToWrap() {
    if (!wrap) return;
    // Always scale to fill the full container width.
    // Tall diagrams scroll vertically — height is never the constraint.
    applyZoom((wrap.clientWidth - 48) / naturalW);
}

document.getElementById('btnZoomIn') ?.addEventListener('click', () => applyZoom(zoom * 1.25));
document.getElementById('btnZoomOut')?.addEventListener('click', () => applyZoom(zoom * 0.8));
document.getElementById('btnFit')    ?.addEventListener('click', fitToWrap);

// ── Connection style toggle (curved ↔ straight) ───────────────────────────────
let connStyle = localStorage.getItem('asis-conn-style') || 'straight';

function applyConnStyle(style) {
    connStyle = style;
    localStorage.setItem('asis-conn-style', style);
    const btn = document.getElementById('btnConnStyle');
    if (btn) {
        btn.textContent = style === 'straight' ? 'Straight' : 'Curved';
        btn.title = style === 'straight'
            ? 'Switch to curved connections'
            : 'Switch to straight connections';
        btn.style.fontWeight = style === 'straight' ? '700' : '';
    }
    connEls.forEach(ce => {
        ce.pathEl.setAttribute('d', style === 'straight' ? ce.dStraight : ce.dCurved);
    });
}

document.getElementById('btnConnStyle')?.addEventListener('click', () => {
    applyConnStyle(connStyle === 'curved' ? 'straight' : 'curved');
});

// Apply saved preference on load
applyConnStyle(connStyle);

document.getElementById('btnFull')?.addEventListener('click', () => {
    if (!document.fullscreenElement) wrap?.requestFullscreen?.();
    else document.exitFullscreen?.();
});

// Re-fit the SVG whenever fullscreen state changes so the diagram fills the screen.
function fitToScreen() {
    if (!wrap) return;
    // In fullscreen we fit to both dimensions (no 100% cap — SVG scales crisply).
    const pad  = 32;
    const zoomW = (wrap.clientWidth  - pad) / naturalW;
    const zoomH = (wrap.clientHeight - pad) / naturalH;
    applyZoom(Math.min(zoomW, zoomH));
    wrap.scrollLeft = 0;
    wrap.scrollTop  = 0;
}

document.addEventListener('fullscreenchange', () => {
    requestAnimationFrame(() => {
        const inFull = !!document.fullscreenElement;
        if (inFull) {
            // In fullscreen the CSS sets height:100vh; clear the JS-set height first
            if (wrap) wrap.style.height = '';
            fitToScreen();
        } else {
            fitToWrap(); // restores JS-controlled height via applyZoom
        }
        // Update toolbar button label
        const btnFull = document.getElementById('btnFull');
        if (btnFull) btnFull.textContent = inFull ? 'Exit full screen' : 'Full screen';
        // Initialise Lucide icon in the exit button if entering fullscreen
        if (inFull && typeof lucide !== 'undefined') {
            lucide.createIcons({ nodes: [document.getElementById('btnExitFull')] });
        }
    });
});

// Wheel events inside the detail panel should scroll the card list, not zoom.
// Stop propagation here so the event never reaches the wrap's zoom handler.
detail?.addEventListener('wheel', e => { e.stopPropagation(); }, { passive: true });

// Scroll wheel → zoom, centred on the cursor position.
wrap?.addEventListener('wheel', e => {
    e.preventDefault();
    const factor   = e.deltaY < 0 ? 1.1 : 0.91;
    const oldZoom  = zoom;
    const newZoom  = Math.max(0.1, Math.min(6, zoom * factor));

    // Cursor position relative to the scrollable content before zoom.
    const rect  = wrap.getBoundingClientRect();
    const ptX   = e.clientX - rect.left + wrap.scrollLeft;
    const ptY   = e.clientY - rect.top  + wrap.scrollTop;

    zoom = newZoom;
    svg.setAttribute('width',  Math.round(naturalW * zoom));
    svg.setAttribute('height', Math.round(naturalH * zoom));
    if (label) label.textContent = Math.round(zoom * 100) + '%';

    // Shift scroll so the point under the cursor stays fixed.
    const scale       = newZoom / oldZoom;
    wrap.scrollLeft   = ptX * scale - (e.clientX - rect.left);
    wrap.scrollTop    = ptY * scale - (e.clientY - rect.top);
}, { passive: false });

// Drag to pan.
if (wrap) {
    let dragging = false, ox = 0, oy = 0, sx = 0, sy = 0;

    wrap.addEventListener('mousedown', e => {
        // Only start drag on left-button and not on SVG interactive elements.
        if (e.button !== 0) return;
        dragging = true;
        ox = e.clientX; oy = e.clientY;
        sx = wrap.scrollLeft; sy = wrap.scrollTop;
        wrap.style.cursor = 'grabbing';
        e.preventDefault();
    });

    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        wrap.scrollLeft = sx - (e.clientX - ox);
        wrap.scrollTop  = sy - (e.clientY - oy);
    });

    document.addEventListener('mouseup', () => {
        if (!dragging) return;
        dragging = false;
        wrap.style.cursor = 'grab';
    });
}

fitToWrap();

})();
</script>
<?php
render_layout($document['title'], ob_get_clean() ?: '');
