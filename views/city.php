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
            height: calc(100% - 72px);
            margin-top: 72px;
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

        /* ── Building Popup ── */
        #building-popup {
            position: fixed;
            display: none;
            z-index: 200;
            text-align: center;
            pointer-events: auto;
        }
        #bp-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(15,23,42,0.92);
            border: 1px solid #f59e0b;
            border-radius: 20px;
            padding: 5px 16px;
            margin-bottom: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.6);
        }
        #bp-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #e2e8f0;
            white-space: nowrap;
        }
        #bp-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: #f59e0b;
            background: rgba(245,158,11,0.15);
            border-radius: 10px;
            padding: 1px 7px;
            white-space: nowrap;
        }
        #bp-timer {
            display: none;
            background: rgba(15,23,42,0.92);
            border: 1px solid #22c55e;
            border-radius: 20px;
            padding: 4px 16px;
            font-size: 0.82rem;
            color: #22c55e;
            font-family: monospace;
            font-variant-numeric: tabular-nums;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        #bp-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: flex-end;
        }
        /* Hex button */
        .hex-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .hex-wrap:hover .hex-shape { filter: brightness(1.25) drop-shadow(0 0 6px rgba(245,158,11,0.5)); }
        .hex-shape {
            width: 58px;
            height: 64px;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            background: linear-gradient(170deg, #a16207 0%, #78350f 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            filter: drop-shadow(0 3px 4px rgba(0,0,0,0.6));
            transition: filter 0.15s;
        }
        .hex-wrap.hex-primary .hex-shape {
            width: 68px;
            height: 76px;
            background: linear-gradient(170deg, #b45309 0%, #92400e 100%);
        }
        .hex-icon { font-size: 1.3rem; line-height: 1; }
        .hex-wrap.hex-primary .hex-icon { font-size: 1.6rem; }
        .hex-label {
            font-size: 0.58rem;
            color: #fde68a;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 1px 2px rgba(0,0,0,0.7);
            white-space: nowrap;
        }

        /* ── Building modal overlay ── */
        #bldg-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.72);
            z-index: 4000;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 20px 8px;
        }
        #bldg-wrap {
            width: 100%;
            max-width: 960px;
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>
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

<!-- Building action popup -->
<div id="building-popup">
    <div id="bp-header">
        <span id="bp-name">—</span>
        <span id="bp-badge">Lv 1</span>
    </div>
    <div id="bp-timer">00:00:00</div>
    <div id="bp-actions"></div>
</div>

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
    // Castle — center
    { code: 'castle',           sprite: 'castle.png',           x: 544, y: 204, size: CAST_SIZE },

    // Top row
    { code: 'farm',             sprite: 'farm.png',             x:   80, y:  60, size: BLDG_SIZE },
    { code: 'storage',          sprite: 'storage.png',          x:  248, y:  60, size: BLDG_SIZE },
    { code: 'treasure_house',   sprite: 'treasure_house.png',   x:  936, y:  60, size: BLDG_SIZE },
    { code: 'quarry',           sprite: 'quarry.png',           x: 1104, y:  60, size: BLDG_SIZE },

    // Middle flanks
    { code: 'academy',          sprite: 'academy.png',          x:   80, y: 248, size: BLDG_SIZE },
    { code: 'wall',             sprite: 'wall_corner.png',      x:  248, y: 248, size: BLDG_SIZE },
    { code: 'trading_post',     sprite: 'trading_post.png',     x:  936, y: 248, size: BLDG_SIZE },
    { code: 'gold_mine',        sprite: 'gold_mine.png',        x: 1104, y: 248, size: BLDG_SIZE },

    // Bottom row
    { code: 'lumber_camp',      sprite: 'lumber_camp.png',      x:   80, y: 452, size: BLDG_SIZE },
    { code: 'barrack',          sprite: 'barracks.png',         x:  248, y: 452, size: BLDG_SIZE },
    { code: 'hall_of_alliance', sprite: 'hall_of_alliance.png', x:  592, y: 456, size: BLDG_SIZE },
    { code: 'hospital',         sprite: 'hospital.png',         x:  936, y: 452, size: BLDG_SIZE },
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
// Atlas load (grass background only)
// ---------------------------------------------------------------------------
const atlas = new Image();
let atlasReady = false;
atlas.onload = () => { atlasReady = true; };
atlas.src = ATLAS_SRC;

// ---------------------------------------------------------------------------
// Building sprites — preload all custom PNGs
// ---------------------------------------------------------------------------
const SPRITE_BASE = '/assets/sprites/pixel/buildings/';
const sprites = {};
BUILDING_DEFS.forEach(b => {
    const img = new Image();
    img.src = SPRITE_BASE + b.sprite;
    sprites[b.code] = img;
});

// ---------------------------------------------------------------------------
// Building display names & function actions
// ---------------------------------------------------------------------------
const BUILDING_NAMES = {
    castle:           'Castle',
    farm:             'Farm',
    storage:          'Storage',
    treasure_house:   'Treasure House',
    quarry:           'Quarry',
    academy:          'Academy',
    wall:             'Wall',
    trading_post:     'Trading Post',
    gold_mine:        'Gold Mine',
    lumber_camp:      'Lumber Camp',
    barrack:          'Barracks',
    hall_of_alliance: 'Hall of Alliance',
    hospital:         'Hospital',
};

// Buildings with a dedicated "function" button (3rd icon).
const BUILDING_FUNCS = {
    academy:          { icon: '🔬', label: 'Research', url: '/research' },
    barrack:          { icon: '⚔️',  label: 'Training', url: '/city/building/barrack' },
    hospital:         { icon: '💊', label: 'Heal',     url: '/city/building/hospital' },
    trading_post:     { icon: '📦', label: 'Trade',    url: '/city/building/trading_post' },
    hall_of_alliance: { icon: '🤝', label: 'Alliance', url: '/city/building/hall_of_alliance' },
};

// ---------------------------------------------------------------------------
// Popup helpers
// ---------------------------------------------------------------------------
const popup     = document.getElementById('building-popup');
const bpName    = document.getElementById('bp-name');
const bpBadge   = document.getElementById('bp-badge');
const bpTimer   = document.getElementById('bp-timer');
const bpActions = document.getElementById('bp-actions');

let popupTimer = null;

function makeHexBtn(icon, label, onClick, primary = false) {
    const wrap = document.createElement('button');
    wrap.className = primary ? 'hex-wrap hex-primary' : 'hex-wrap';
    wrap.innerHTML =
        `<div class="hex-shape">
            <span class="hex-icon">${icon}</span>
         </div>
         <span class="hex-label">${label}</span>`;
    wrap.addEventListener('click', (e) => { e.stopPropagation(); onClick(); });
    return wrap;
}

function openPopup(building) {
    const data    = BUILDINGS_DATA[building.code];
    const level   = data?.level ?? 1;
    const inQueue = data?.inQueue ?? false;
    const name    = BUILDING_NAMES[building.code] ?? building.code.replace(/_/g, ' ');
    const func    = BUILDING_FUNCS[building.code] ?? null;

    bpName.textContent  = name;
    bpBadge.textContent = inQueue ? `Lv ${level} → ${data.levelTo}` : `Lv ${level}`;

    // Timer
    clearInterval(popupTimer);
    if (inQueue && data.finishesAt) {
        bpTimer.style.display = 'block';
        const tick = () => {
            const left = Math.max(0, data.finishesAt * 1000 - Date.now());
            const h = Math.floor(left / 3600000);
            const m = Math.floor((left % 3600000) / 60000);
            const s = Math.floor((left % 60000) / 1000);
            bpTimer.textContent = `⏳ ${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        };
        tick();
        popupTimer = setInterval(tick, 1000);
    } else {
        bpTimer.style.display = 'none';
    }

    // Action buttons
    bpActions.innerHTML = '';
    bpActions.appendChild(makeHexBtn('📋', 'Details', () => openBuildingModal(building.code)));
    bpActions.appendChild(makeHexBtn(
        inQueue ? '⚡' : '⬆',
        inQueue ? 'Speedup' : 'Upgrade',
        () => openBuildingModal(building.code),
        true   // primary = larger center button
    ));
    if (func) {
        bpActions.appendChild(makeHexBtn(func.icon, func.label, () => { window.location.href = func.url; }));
    }

    // Position centered below the building sprite
    popup.style.display = 'block';
    const rect   = canvas.getBoundingClientRect();
    const scaleX = rect.width  / canvas.width;
    const scaleY = rect.height / canvas.height;

    const bCenterX = rect.left + (building.x + building.size / 2) * scaleX;
    const bBottomY = rect.top  + (building.y + building.size)     * scaleY + 10;

    const pw = popup.offsetWidth;
    let left = bCenterX - pw / 2;
    let top  = bBottomY;

    left = Math.max(8, Math.min(window.innerWidth  - pw - 8, left));
    top  = Math.min(window.innerHeight - popup.offsetHeight - 8, top);
    if (top < 80) top = 80;

    popup.style.left = left + 'px';
    popup.style.top  = top  + 'px';
}

function closePopup() {
    clearInterval(popupTimer);
    popup.style.display = 'none';
}

// Close popup when clicking outside of it (not on canvas—canvas handles itself)
document.addEventListener('click', (e) => {
    if (popup.style.display !== 'none' && !popup.contains(e.target) && e.target !== canvas) {
        closePopup();
    }
});

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
        const data  = BUILDINGS_DATA[hit.code];
        const name  = BUILDING_NAMES[hit.code] ?? hit.code.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const level = data ? 'Lv.' + data.level : '';
        const queue = data?.inQueue ? ' ⏳→' + data.levelTo : '';
        tooltip.style.display = 'block';
        tooltip.style.left  = (e.clientX + 14) + 'px';
        tooltip.style.top   = (e.clientY - 10) + 'px';
        tooltip.textContent = name + '  ' + level + queue;
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
        tooltip.style.display = 'none';
        openPopup(hit);
    } else {
        closePopup();
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

    // Grass background (atlas)
    if (atlasReady) {
        for (let ty = 0; ty < tileH; ty++) {
            for (let tx = 0; tx < tileW; tx++) {
                const variant = GRASS_TILES[(tx * 7 + ty * 13) % 3];
                drawTile(variant, tx * 32, ty * 32, 32);
            }
        }
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

        // Draw custom sprite
        const img = sprites[b.code];
        if (img?.complete && img.naturalWidth > 0) {
            ctx.drawImage(img, b.x, b.y, b.size, b.size);
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

// ---------------------------------------------------------------------------
// Building modal overlay
// ---------------------------------------------------------------------------
async function openBuildingModal(code) {
    closePopup();
    const overlay = document.getElementById('bldg-overlay');
    const wrap    = document.getElementById('bldg-wrap');
    wrap.innerHTML = '<p style="color:#64748b;text-align:center;padding:60px 0;font-family:system-ui">Laden…</p>';
    overlay.style.display = 'flex';

    const r    = await fetch(`/city/building/${code}?modal=1`);
    const html = await r.text();

    const parser = new DOMParser();
    const doc    = parser.parseFromString(html, 'text/html');

    wrap.innerHTML = '';

    // DOMParser moves <style> to <head> — copy it back first
    doc.head.querySelectorAll('style').forEach(s => {
        const ns = document.createElement('style');
        ns.textContent = s.textContent;
        wrap.appendChild(ns);
    });

    // Body content (skip scripts — they need re-creation to execute)
    doc.body.childNodes.forEach(node => {
        if (node.tagName !== 'SCRIPT') {
            wrap.appendChild(document.importNode(node, true));
        }
    });

    // Re-create scripts so they actually execute
    doc.querySelectorAll('script').forEach(s => {
        const ns = document.createElement('script');
        ns.textContent = s.textContent;
        wrap.appendChild(ns);
    });
}

function closeBldgModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('bldg-overlay').style.display = 'none';
    document.getElementById('bldg-wrap').innerHTML = '';
}
window.closeBldgModal   = closeBldgModal;
window.openBuildingModal = openBuildingModal;
</script>

<div id="bldg-overlay" style="display:none" onclick="closeBldgModal(event)">
    <div id="bldg-wrap"></div>
</div>
</body>
</html>
