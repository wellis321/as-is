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
                <?= count($steps) ?> steps &middot; <?= count($lanes) ?> lanes
                &middot; <?= count($connections) ?> connections
                &middot; Scroll to zoom &middot; Drag to pan
                &middot; <strong style="color:var(--text);">Click a step to explore</strong>
            </span>
        </div>
        <div class="actions" style="gap:0.35rem;">
            <button class="btn btn-secondary btn-sm" id="btnZoomIn"  title="Zoom in">+</button>
            <span   class="zoom-level"               id="zoomLabel" >100%</span>
            <button class="btn btn-secondary btn-sm" id="btnZoomOut" title="Zoom out">−</button>
            <button class="btn btn-secondary btn-sm" id="btnFit"     title="Fit to window">Fit</button>
            <button class="btn btn-secondary btn-sm" id="btnFull"    title="Full screen">Full screen</button>
        </div>
    </div>

    <!-- SVG canvas + floating step detail -->
    <div class="diagram-wrap" id="diagramWrap" style="border:none;border-radius:0;padding:0;position:relative;">
        <div id="swimlane-canvas"></div>
        <div id="step-detail" hidden
             style="position:absolute;z-index:200;
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
    const LANE_V_PAD = 24;  // padding above and below node rows within a lane
    const NODE_W     = 152; // node width
    const NODE_H     = 66;  // node height
    const H_GAP      = 28;  // horizontal gap between columns
    const V_GAP      = 28;  // vertical gap between rows within a lane
    const MAX_COLS   = 7;   // steps per row before wrapping to a new row
    const TOP_PAD    = 52;  // space above all lanes (for loop-back arcs)
    const BOT_PAD    = 20;
    const ROW_H      = NODE_H + V_GAP; // height of one row of nodes (94px)

    // ── Step layout: assign each step a (lane_row, lane_col) ─────
    // Steps within each lane are sorted by step_number and arranged
    // left-to-right, wrapping to a new row every MAX_COLS steps.
    const stepLayout = new Map(); // step.id → { lane_row, lane_col }
    const laneRows   = new Map(); // lane.id → number of rows used

    lanes.forEach(lane => {
        const laneSteps = steps
            .filter(s => s.lane_id === lane.id)
            .sort((a, b) => a.step_number - b.step_number);
        const nr = Math.max(1, Math.ceil(laneSteps.length / MAX_COLS));
        laneRows.set(lane.id, nr);
        laneSteps.forEach((s, i) => {
            stepLayout.set(s.id, {
                lane_row: Math.floor(i / MAX_COLS),
                lane_col: i % MAX_COLS,
            });
        });
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

    const totalW = LEFT_PAD + MAX_COLS * NODE_W + (MAX_COLS - 1) * H_GAP + RIGHT_PAD;
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
    // Start/End always use distinctive colours regardless of action type
    const TYPE_OVERRIDE = {
        start: { fill:'#dcfce7', stroke:'#16a34a', text:'#14532d' },
        end:   { fill:'#fef2f2', stroke:'#dc2626', text:'#991b1b' },
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

    // Lane colours are stored as light tint backgrounds (e.g. #fff3e0).
    // Use them directly for the band fill; pair with a pre-set deep label colour.
    const LANE_LABEL_COLORS = ['#6b4f2a', '#2f5c3a', '#1a4469', '#5c1fa0', '#7a1f28', '#4a5568'];
    const LANE_FILL_FALLBACK = ['#fff3e0', '#e8f5e9', '#e3f2fd', '#f3e8ff', '#fde8e8', '#f0f4f8'];
    function parseLaneColor(hex, idx) {
        // #ffffff means "not set" (edit page default) — use the palette instead
        const isReal = hex && /^#[0-9a-fA-F]{6}$/.test(hex) && hex.toLowerCase() !== '#ffffff';
        const fill   = isReal ? hex : LANE_FILL_FALLBACK[idx % LANE_FILL_FALLBACK.length];
        const stroke = LANE_LABEL_COLORS[idx % LANE_LABEL_COLORS.length];
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

        let d, stroke, markerId, dash = null;
        let lx, ly; // label position
        const ARROW_LEN = 10;

        if (isBack) {
            // Loop-back / backward: amber dashed arc above the earlier row
            const laneY  = laneYStart.get(fs?.lane_id) ?? TOP_PAD;
            const minRow = Math.min(fp.lr ?? 0, tp.lr ?? 0);
            // Arc Y sits just above the top of the relevant row (in the gap / header space)
            const rowTopY = laneY + LANE_HDR + LANE_V_PAD + minRow * ROW_H - V_GAP * 0.5;
            const arcY    = Math.max(laneY + LANE_HDR + 6, rowTopY);
            d        = `M${fp.cx},${fp.y} C${fp.cx},${arcY} ${tp.cx},${arcY} ${tp.cx},${tp.y - ARROW_LEN - 1}`;
            stroke   = '#f59e0b';
            markerId = 'aBack';
            dash     = '6 3';
            lx = (fp.cx + tp.cx) / 2;
            ly = arcY - 12;

        } else if (isWrap) {
            // Natural row-wrap: clean L-shape along the right margin
            // Right side → drop down → left to target  (like a typewriter carriage return)
            const x1    = fp.cx + NODE_W / 2;
            const x2    = tp.cx - NODE_W / 2 - ARROW_LEN - 1;
            const rightM = totalW - RIGHT_PAD / 2 + 4;
            const r      = 10; // corner radius
            d = `M${x1},${fp.cy}` +
                ` L${rightM - r},${fp.cy} Q${rightM},${fp.cy} ${rightM},${fp.cy + r}` +
                ` L${rightM},${tp.cy - r} Q${rightM},${tp.cy} ${rightM - r},${tp.cy}` +
                ` L${x2},${tp.cy}`;
            stroke   = '#64748b';
            markerId = 'aFwd';
            lx = rightM + 6;
            ly = (fp.cy + tp.cy) / 2;

        } else if (dropDown) {
            // Cross-row branch: exit bottom of source, enter top of target
            // Keeps the arrow within the column area rather than crossing nodes horizontally
            const bx1 = fp.cx;
            const by1 = fp.y + NODE_H;
            const bx2 = tp.cx;
            const by2 = tp.y - ARROW_LEN - 1;
            const mid = (by1 + by2) / 2;
            d        = `M${bx1},${by1} C${bx1},${mid} ${bx2},${mid} ${bx2},${by2}`;
            stroke   = '#64748b';
            markerId = 'aFwd';
            lx = bx1 + 8;
            ly = mid;

        } else if (isCross) {
            // Cross-lane: route vertically. Direction depends on whether the
            // target lane is above or below the source lane in the diagram.
            const laneYSrc = laneYStart.get(fs?.lane_id) ?? 0;
            const laneYTgt = laneYStart.get(ts?.lane_id) ?? 0;
            let bx1, by1, bx2, by2;
            if (laneYTgt >= laneYSrc) {
                // Downward — exit bottom of source, enter top of target
                bx1 = fp.cx; by1 = fp.y + NODE_H;
                bx2 = tp.cx; by2 = tp.y - ARROW_LEN - 1;
            } else {
                // Upward — exit top of source, enter bottom of target
                // (marker tip lands exactly on the node bottom edge)
                bx1 = fp.cx; by1 = fp.y;
                bx2 = tp.cx; by2 = tp.y + NODE_H + ARROW_LEN;
            }
            const mid  = (by1 + by2) / 2;
            d        = `M${bx1},${by1} C${bx1},${mid} ${bx2},${mid} ${bx2},${by2}`;
            stroke   = '#3b82f6';
            markerId = 'aCross';
            lx = (bx1 + bx2) / 2 + 8;
            ly = mid;

        } else {
            // Forward same-row: horizontal S-curve
            const x1 = fp.cx + NODE_W / 2, y1 = fp.cy;
            const x2 = tp.cx - NODE_W / 2 - ARROW_LEN - 1, y2 = tp.cy;
            const mx = (x1 + x2) / 2;
            d        = `M${x1},${y1} C${mx},${y1} ${mx},${y2} ${x2},${y2}`;
            stroke   = '#64748b';
            markerId = 'aFwd';
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
        connEls.push({ from: conn.from, to: conn.to, pathEl, labelEls: [] });

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
        if (step.step_type === 'decision') {
            g.appendChild(el('polygon', {
                points: `${cx},${y} ${x+NODE_W},${cy} ${cx},${y+NODE_H} ${x},${cy}`,
                fill: col.fill, stroke: col.stroke, 'stroke-width': 1.5,
            }));
        } else if (step.step_type === 'start' || step.step_type === 'end') {
            g.appendChild(el('rect', { x, y, width:NODE_W, height:NODE_H, rx:NODE_H/2,
                fill:col.fill, stroke:col.stroke, 'stroke-width':2 }));
        } else {
            g.appendChild(el('rect', { x, y, width:NODE_W, height:NODE_H, rx:7,
                fill:col.fill, stroke:col.stroke, 'stroke-width':1.5 }));
        }

        // Step number badge — centred for pill nodes (large rx cuts off the corner),
        // top-left for rectangles and diamonds.
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

        // Action icon — Lucide icon drawn from node data using el() (task nodes only)
        const iconDs = ACTION_ICON_NODES[step.action_type];
        if (iconDs && step.step_type === 'task') {
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
    return { svg, clickHandlers, nodeGroups, connEls };
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

const { svg, clickHandlers, nodeGroups, connEls } = result;

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
    if (!_dd || !wrap || !detail) return;
    const wr = wrap.getBoundingClientRect();
    detail.style.left = Math.max(0, e.clientX - wr.left + wrap.scrollLeft - _dox) + 'px';
    detail.style.top  = Math.max(0, e.clientY - wr.top  + wrap.scrollTop  - _doy) + 'px';
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
            if (detail) detail.hidden = true;
            return;
        }
        const related = applyHighlight(step.id);

        if (!detail) return;

        // Gather all related steps, sorted by step_number
        const relSteps = data.steps
            .filter(s => related.has(s.id))
            .sort((a, b) => a.step_number - b.step_number);

        const count = relSteps.length;
        const cards = relSteps.map((s, i) => stepCardHtml(s, i === count - 1)).join('');

        detail.innerHTML = `
            <div data-drag-handle style="display:flex;justify-content:space-between;align-items:center;
                        padding-bottom:0.45rem;margin-bottom:0.1rem;
                        border-bottom:2px solid var(--border);
                        cursor:grab;user-select:none;">
                <span style="display:flex;align-items:center;gap:0.4rem;">
                    <span style="color:var(--muted);font-size:1rem;line-height:1;letter-spacing:1px;">&#8942;&#8942;</span>
                    <span style="font-size:0.7rem;font-weight:700;color:var(--muted);
                                 text-transform:uppercase;letter-spacing:0.06em;">
                        ${count} step${count !== 1 ? 's' : ''} in focus</span>
                </span>
                <button id="closeDetail" style="background:none;border:none;cursor:pointer;
                        font-size:1.2rem;color:var(--muted);padding:0;line-height:1;">&#215;</button>
            </div>
            <div style="overflow-y:auto;max-height:340px;">${cards}</div>`;

        // Opposite-corner positioning so the panel never covers the mini-flow
        const wrapRect   = wrap.getBoundingClientRect();
        const PANEL_W    = 310;
        const clickFracX = (e.clientX - wrapRect.left) / wrap.clientWidth;
        const clickFracY = (e.clientY - wrapRect.top)  / wrap.clientHeight;
        const px = clickFracX > 0.5
            ? wrap.scrollLeft + 12
            : wrap.scrollLeft + wrap.clientWidth - PANEL_W - 12;
        const py = clickFracY > 0.5
            ? wrap.scrollTop  + 12
            : wrap.scrollTop  + wrap.clientHeight - 380 - 12;
        detail.style.left  = Math.max(wrap.scrollLeft + 6, px) + 'px';
        detail.style.top   = Math.max(wrap.scrollTop  + 6, py) + 'px';
        detail.style.width = PANEL_W + 'px';
        detail.hidden = false;

        document.getElementById('closeDetail')
            ?.addEventListener('click', () => { detail.hidden = true; clearHighlight(); });
    });
});

// Click on background → dismiss panel and clear highlight
// (but not if the user just finished dragging the panel)
wrap?.addEventListener('click', e => {
    if (_ddEnded) return; // suppress click that fires immediately after drag ends
    if (e.target === wrap || e.target.closest('#swimlane-canvas svg')) {
        if (detail) detail.hidden = true;
        clearHighlight();
    }
});

// ── Zoom / pan ───────────────────────────────────────────────────────────────
const naturalW = parseFloat(svg.getAttribute('width'));
const naturalH = parseFloat(svg.getAttribute('height'));
let zoom = 1;

function applyZoom(z) {
    zoom = Math.max(0.1, Math.min(6, z));
    svg.setAttribute('width',  Math.round(naturalW * zoom));
    svg.setAttribute('height', Math.round(naturalH * zoom));
    if (label) label.textContent = Math.round(zoom * 100) + '%';
}

function fitToWrap() {
    if (!wrap) return;
    const available = wrap.clientWidth - 48;
    applyZoom(Math.min(available / naturalW, 1)); // fit but never enlarge past 100%
}

document.getElementById('btnZoomIn') ?.addEventListener('click', () => applyZoom(zoom * 1.25));
document.getElementById('btnZoomOut')?.addEventListener('click', () => applyZoom(zoom * 0.8));
document.getElementById('btnFit')    ?.addEventListener('click', fitToWrap);
document.getElementById('btnFull')   ?.addEventListener('click', () => {
    if (!document.fullscreenElement) wrap?.requestFullscreen?.();
    else document.exitFullscreen?.();
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
