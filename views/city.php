<?php
declare(strict_types=1);

/**
 * City View — illustrated canvas (SPEC §4.8)
 *
 * Full-width canvas showing the village using Kenney Tiny Town sprites.
 * Clicking a building navigates to /city/building/{code} for upgrades.
 *
 * Variables provided by index.php:
 *   $session  array  — current session row
 *   $state    array  — CityState::loadForPlayer() result
 */

use Conquer\Game\City\BuildingData;
use Conquer\Game\City\CityState;

$city      = $state['city'];
$buildings = $state['buildings'];
$queue     = $state['build_queue'];

$inQueue = [];
foreach ($queue as $entry) {
    $inQueue[$entry['building_code']] = $entry;
}

$fmt = static fn (int|string $n): string => number_format((int) $n, 0, '.', ',');

// Build JSON data for canvas labels / queue overlay
$buildingsForCanvas = [];
foreach ($buildings as $code => $building) {
    $queued = $inQueue[$code] ?? null;
    $buildingsForCanvas[$code] = [
        'level'   => (int) $building['level'],
        'inQueue' => $queued !== null,
        'levelTo' => $queued ? (int) $queued['level_to'] : null,
        'finishesAt' => $queued ? strtotime($queued['finishes_at']) : null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Conquer — <?= htmlspecialchars($city['name']) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f172a;
            --surface: #1e293b;
            --border:  #334155;
            --text:    #e2e8f0;
            --muted:   #94a3b8;
            --accent:  #0ea5e9;
            --gold:    #f59e0b;
            --green:   #22c55e;
            --red:     #ef4444;
        }

        html, body {
            height: 100%;
            background: #000;
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 1280px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        /* ── Top bar ── */
        .topbar {
            flex: 0 0 48px;
            height: 48px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--accent);
            white-space: nowrap;
            text-decoration: none;
        }

        .resources {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            flex: 1;
        }

        .res {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 0.2rem 0.5rem;
            font-size: 0.78rem;
        }

        .topbar-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn {
            padding: 0.25rem 0.65rem;
            border-radius: 5px;
            font-size: 0.78rem;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--muted);
            text-decoration: none;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }

        /* ── Canvas wrapper ── */
        #city-wrap {
            flex: 1;
            overflow: auto;
            background: var(--bg);
        }

        #city-canvas {
            display: block;
            image-rendering: pixelated;
            cursor: default;
        }

        /* ── Tooltip ── */
        #city-tooltip {
            position: fixed;
            background: var(--surface);
            border: 1px solid var(--gold);
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            color: var(--text);
            pointer-events: none;
            display: none;
            z-index: 30;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div id="game">

<header class="topbar">
    <a href="/city" class="topbar-title">⚔ <?= htmlspecialchars($city['name']) ?></a>

    <div class="resources">
        <div class="res">🌾 <?= $fmt($city['food']) ?></div>
        <div class="res">🪵 <?= $fmt($city['lumber']) ?></div>
        <div class="res">🪨 <?= $fmt($city['stone']) ?></div>
        <div class="res">💰 <?= $fmt($city['gold']) ?></div>
    </div>

    <div class="topbar-actions">
        <a href="/map" class="btn">🗺 Map</a>
        <span style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($session['username']) ?></span>
        <form method="post" action="/auth/logout" style="display:inline">
            <button type="submit" class="btn">Logout</button>
        </form>
    </div>
</header>

<div id="city-wrap">
    <canvas id="city-canvas"></canvas>
</div>

<div id="city-tooltip"></div>

</div><!-- #game -->

<script>
'use strict';

// ---------------------------------------------------------------------------
// Atlas constants — Kenney Tiny Town tilemap_packed.png
// 16×16 tiles, 1px gap → step = 17px, 12 cols
// ---------------------------------------------------------------------------
const ATLAS_SRC  = '/assets/sprites/kenney-tiny-town/Tilemap/tilemap_packed.png';
const ATLAS_TILE = 16;
const ATLAS_STEP = 17;
const ATLAS_COLS = 12;

function tileCoords(idx) {
    return {
        sx: (idx % ATLAS_COLS) * ATLAS_STEP,
        sy: Math.floor(idx / ATLAS_COLS) * ATLAS_STEP,
    };
}

// ---------------------------------------------------------------------------
// Building definitions — position + tile
// tile: single index OR [tl,tr,bl,br] for 2×2 composite
// ---------------------------------------------------------------------------
const CANVAS_W  = 1280;
const CANVAS_H  = 600;
const BLDG_SIZE = 96;    // px for regular buildings (6× zoom from 16px)
const CAST_SIZE = 192;   // castle 2×2 composite (each sub-tile = 96px)

const BUILDING_DEFS = [
    // Castle — center, 2×2 composite
    { code: 'castle',             tile: [96, 97, 108, 109], x: 544, y: 204, size: CAST_SIZE },

    // Top row
    { code: 'farm',               tile:   5, x:   80, y:  60, size: BLDG_SIZE },
    { code: 'storage',            tile:  36, x:  248, y:  60, size: BLDG_SIZE },
    { code: 'treasure_house',     tile: 122, x:  936, y:  60, size: BLDG_SIZE },
    { code: 'quarry',             tile:  12, x: 1104, y:  60, size: BLDG_SIZE },

    // Middle flanks
    { code: 'academy',            tile: 110, x:   80, y: 248, size: BLDG_SIZE },
    { code: 'wall',               tile:  17, x:  248, y: 248, size: BLDG_SIZE },
    { code: 'trading_post',       tile:  84, x:  936, y: 248, size: BLDG_SIZE },
    { code: 'gold_mine',          tile: 120, x: 1104, y: 248, size: BLDG_SIZE },

    // Bottom row
    { code: 'lumber_camp',        tile:   6, x:   80, y: 452, size: BLDG_SIZE },
    { code: 'barrack',            tile:  44, x:  248, y: 452, size: BLDG_SIZE },
    { code: 'hall_of_alliance',   tile:  49, x:  592, y: 456, size: BLDG_SIZE },
    { code: 'hospital',           tile:  45, x:  936, y: 452, size: BLDG_SIZE },
];

// Grass tile variants for background
const GRASS_TILES = [0, 1, 2];

// Building data from PHP (levels, queue status)
const BUILDINGS_DATA = <?= json_encode($buildingsForCanvas) ?>;

// ---------------------------------------------------------------------------
// Canvas setup
// ---------------------------------------------------------------------------
const wrap    = document.getElementById('city-wrap');
const canvas  = document.getElementById('city-canvas');
const ctx     = canvas.getContext('2d');
const tooltip = document.getElementById('city-tooltip');

// Set canvas to at least full viewport or CANVAS_W
function resizeCanvas() {
    canvas.width  = Math.max(CANVAS_W, wrap.clientWidth);
    canvas.height = Math.max(CANVAS_H, wrap.clientHeight);
}
resizeCanvas();
window.addEventListener('resize', () => { resizeCanvas(); });

// ---------------------------------------------------------------------------
// Atlas load
// ---------------------------------------------------------------------------
const atlas = new Image();
let atlasReady = false;
atlas.onload = () => { atlasReady = true; };
atlas.src = ATLAS_SRC;

// ---------------------------------------------------------------------------
// Interaction state
// ---------------------------------------------------------------------------
let hoveredCode = null;
let animFrame   = 0;

canvas.addEventListener('mousemove', (e) => {
    const rect = canvas.getBoundingClientRect();
    const mx   = (e.clientX - rect.left) * (canvas.width  / rect.width);
    const my   = (e.clientY - rect.top)  * (canvas.height / rect.height);
    const hit  = hitTest(mx, my);

    if (hit) {
        canvas.style.cursor = 'pointer';
        hoveredCode = hit.code;
        const data = BUILDINGS_DATA[hit.code];
        tooltip.style.display = 'block';
        tooltip.style.left  = (e.clientX + 14) + 'px';
        tooltip.style.top   = (e.clientY - 10) + 'px';
        const name  = hit.code.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const level = data ? 'LV.' + data.level : '';
        const queue = data?.inQueue ? ' ⏳→' + data.levelTo : '';
        tooltip.textContent = name + '  ' + level + queue + '  — click to manage';
    } else {
        canvas.style.cursor = 'default';
        hoveredCode = null;
        tooltip.style.display = 'none';
    }
});

canvas.addEventListener('mouseleave', () => {
    hoveredCode = null;
    tooltip.style.display = 'none';
    canvas.style.cursor = 'default';
});

canvas.addEventListener('click', (e) => {
    const rect = canvas.getBoundingClientRect();
    const mx   = (e.clientX - rect.left) * (canvas.width  / rect.width);
    const my   = (e.clientY - rect.top)  * (canvas.height / rect.height);
    const hit  = hitTest(mx, my);
    if (hit) {
        window.location.href = '/city/building/' + hit.code;
    }
});

function hitTest(mx, my) {
    // Iterate in reverse so top-painted buildings are hit first
    for (let i = BUILDING_DEFS.length - 1; i >= 0; i--) {
        const b = BUILDING_DEFS[i];
        if (mx >= b.x && mx < b.x + b.size && my >= b.y && my < b.y + b.size) {
            return b;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
function drawTile(idx, dx, dy, size) {
    const { sx, sy } = tileCoords(idx);
    ctx.drawImage(atlas, sx, sy, ATLAS_TILE, ATLAS_TILE, Math.round(dx), Math.round(dy), size, size);
}

function render() {
    animFrame++;
    ctx.imageSmoothingEnabled = false;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const tileW = Math.ceil(canvas.width  / 32) + 1;
    const tileH = Math.ceil(canvas.height / 32) + 1;

    // Grass background
    for (let ty = 0; ty < tileH; ty++) {
        for (let tx = 0; tx < tileW; tx++) {
            const variant = GRASS_TILES[(tx * 7 + ty * 13) % 3];
            drawTile(variant, tx * 32, ty * 32, 32);
        }
    }

    if (!atlasReady) {
        requestAnimationFrame(render);
        return;
    }

    // Buildings
    for (const b of BUILDING_DEFS) {
        const data     = BUILDINGS_DATA[b.code];
        const isHover  = hoveredCode === b.code;
        const inQueue  = data?.inQueue ?? false;

        // Shadow circle under building
        ctx.save();
        ctx.beginPath();
        ctx.ellipse(b.x + b.size / 2, b.y + b.size - 6, b.size * 0.38, b.size * 0.12, 0, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(0,0,0,0.3)';
        ctx.fill();
        ctx.restore();

        // Draw sprite
        if (Array.isArray(b.tile)) {
            // 2×2 composite (castle)
            const half = b.size / 2;
            drawTile(b.tile[0], b.x,        b.y,        half);
            drawTile(b.tile[1], b.x + half,  b.y,        half);
            drawTile(b.tile[2], b.x,        b.y + half,  half);
            drawTile(b.tile[3], b.x + half,  b.y + half,  half);
        } else {
            drawTile(b.tile, b.x, b.y, b.size);
        }

        // Queue pulse outline
        if (inQueue) {
            const alpha = 0.5 + 0.5 * Math.sin(animFrame * 0.08);
            ctx.save();
            ctx.strokeStyle = `rgba(245,158,11,${alpha})`;
            ctx.lineWidth   = 3;
            ctx.strokeRect(b.x - 1, b.y - 1, b.size + 2, b.size + 2);
            ctx.restore();
        }

        // Hover highlight
        if (isHover) {
            ctx.save();
            ctx.shadowColor  = '#f59e0b';
            ctx.shadowBlur   = 16;
            ctx.strokeStyle  = '#f59e0b';
            ctx.lineWidth    = 2;
            ctx.strokeRect(b.x - 1, b.y - 1, b.size + 2, b.size + 2);
            ctx.restore();
        }

        // Level badge
        if (data) {
            const label = inQueue
                ? 'LV.' + data.level + '→' + data.levelTo
                : 'LV.' + data.level;
            const badgeX = b.x + b.size / 2;
            const badgeY = b.y - 4;
            const fontSize = b.size >= 160 ? 13 : 11;
            ctx.font      = `bold ${fontSize}px monospace`;
            const tw = ctx.measureText(label).width;
            ctx.fillStyle = 'rgba(15,23,42,0.85)';
            ctx.fillRect(badgeX - tw/2 - 4, badgeY - fontSize - 2, tw + 8, fontSize + 6);
            ctx.fillStyle = inQueue ? '#f59e0b' : '#e2e8f0';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(label, badgeX, badgeY);
        }

        // Building name label below
        {
            const name  = b.code.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const lx    = b.x + b.size / 2;
            const ly    = b.y + b.size + 14;
            const fs    = b.size >= 160 ? 12 : 10;
            ctx.font      = `${fs}px system-ui`;
            const tw2 = ctx.measureText(name).width;
            ctx.fillStyle = 'rgba(15,23,42,0.75)';
            ctx.fillRect(lx - tw2/2 - 3, ly - fs - 1, tw2 + 6, fs + 4);
            ctx.fillStyle    = '#94a3b8';
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(name, lx, ly);
        }
    }

    requestAnimationFrame(render);
}

render();

// Refresh every 30s to update resource counts in topbar
setTimeout(() => window.location.reload(), 30_000);
</script>

</body>
</html>
