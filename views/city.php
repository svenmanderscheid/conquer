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

// Build queue IDs for Cancel/Speedup buttons
$buildQueueIds = [];
foreach ($queue as $entry) {
    $buildQueueIds[$entry['building_code']] = (int)$entry['id'];
}

$fmt = static fn (int|string $n): string => number_format((int) $n, 0, '.', ',');

// Build JSON data for canvas labels / queue overlay
$buildingsForCanvas = [];
foreach ($buildings as $code => $building) {
    $queued = $inQueue[$code] ?? null;
    $buildingsForCanvas[$code] = [
        'level'      => (int) $building['level'],
        'inQueue'    => $queued !== null,
        'levelTo'    => $queued ? (int) $queued['level_to'] : null,
        'finishesAt' => $queued ? strtotime($queued['finishes_at']) : null,
        'queueId'    => $queued ? (int) $queued['id'] : null,
    ];
}

// Research queue
$researchQueue = [];
try {
    $rq = \Conquer\Db\Connection::getInstance()->query(
        'SELECT id, research_code, level_to, finishes_at FROM research_queue
         WHERE player_id = ? AND is_processed = 0 AND finishes_at > UTC_TIMESTAMP()
         ORDER BY finishes_at ASC LIMIT 1',
        [(int)$session['player_id']]
    )->fetch();
    if ($rq) {
        $researchQueue = [
            'id'         => (int)$rq['id'],
            'code'       => $rq['research_code'],
            'levelTo'    => (int)$rq['level_to'],
            'finishesAt' => strtotime($rq['finishes_at']),
        ];
    }
} catch (\Throwable) {}

// Troop queue
$troopQueueForJs = [];
$troopQueue = $state['troop_queue'] ?? [];
foreach ($troopQueue as $tq) {
    $troopQueueForJs[] = [
        'id'         => (int)$tq['id'],
        'code'       => $tq['troop_code'],
        'count'      => (int)$tq['count'],
        'finishesAt' => strtotime($tq['finishes_at']),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Conquer — <?= htmlspecialchars($city['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      var(--c-bg, #f0e8d0);
            --surface: var(--c-panel, #f4e4c1);
            --border:  var(--c-border, rgba(139,90,43,0.35));
            --text:    var(--c-text, #4a3520);
            --muted:   var(--c-muted, #8b6f47);
            --accent:  var(--c-gold, #c08858);
            --gold:    var(--c-gold-l, #d4a070);
            --green:   #7fb069;
            --red:     #c0604d;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 1280px;
            height: calc(100% - 52px);
            margin-top: 52px;
            padding-bottom: 70px;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        /* ── Top bar ── */
        .topbar {
            flex: 0 0 48px;
            height: 48px;
            background: var(--c-panel2, #ede0c4);
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--c-wood-dark, #8b5a2b);
            white-space: nowrap;
            text-decoration: none;
            letter-spacing: 0.03em;
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
            background: var(--c-panel, #f4e4c1);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 5px;
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            color: var(--c-text, #4a3520);
            font-weight: 600;
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
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel, #f4e4c1);
            color: var(--c-muted, #8b6f47);
            text-decoration: none;
            transition: border-color 0.15s, color 0.15s;
        }
        .btn:hover { border-color: var(--c-wood-dark, #8b5a2b); color: var(--c-wood-dark, #8b5a2b); }

        /* ── Canvas wrapper ── */
        #city-wrap {
            flex: 1;
            overflow: auto;
            background: var(--c-bg, #f0e8d0);
            position: relative;
        }

        #city-canvas {
            display: block;
            image-rendering: pixelated;
            cursor: default;
        }

        /* ── Tooltip ── */
        #city-tooltip {
            position: fixed;
            background: var(--c-panel, #f4e4c1);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 8px;
            padding: 0.4rem 0.9rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--c-text, #4a3520);
            pointer-events: none;
            display: none;
            z-index: 30;
            white-space: nowrap;
            box-shadow: 0 4px 16px var(--c-shadow, rgba(139,90,43,0.18));
        }

        /* ── Building Popup (three-part: above / spacer / below) ── */
        #building-popup {
            position: fixed;
            display: none;
            z-index: 200;
            text-align: center;
            pointer-events: none; /* children re-enable where needed */
        }

        /* Name + level banner — floats ABOVE the building */
        #bp-above {
            pointer-events: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
        }
        #bp-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--c-panel, #f4e4c1);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 20px;
            padding: 6px 18px;
            box-shadow: 0 4px 16px var(--c-shadow, rgba(139,90,43,0.18));
        }
        #bp-name {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--c-wood-dark, #8b5a2b);
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        #bp-badge {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--c-wood-dark, #8b5a2b);
            background: rgba(139,90,43,0.12);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 10px;
            padding: 1px 8px;
            white-space: nowrap;
        }

        /* Transparent spacer — height set by JS to match building height */
        #bp-spacer {
            width: 1px;
            height: 96px; /* default, overridden by JS */
        }

        /* Buttons row — appears BELOW the building */
        #bp-below {
            pointer-events: auto;
        }
        #bp-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        /* Hex button — all same size, center distinguished by color */
        .hex-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .hex-shape {
            width: 52px;
            height: 58px;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            background: linear-gradient(160deg, #c9925a 0%, #9a6535 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 3px 6px rgba(139,90,43,0.5));
            transition: filter 0.12s, transform 0.12s;
        }
        .hex-wrap.hex-primary .hex-shape {
            background: linear-gradient(160deg, #d4a070 0%, #a06830 100%);
            filter: drop-shadow(0 3px 8px rgba(192,136,88,0.6));
        }
        .hex-wrap:hover .hex-shape {
            filter: drop-shadow(0 3px 14px rgba(139,90,43,0.7)) brightness(1.12);
            transform: scale(1.08);
        }
        .hex-icon { font-size: 1.05rem; line-height: 1; }
        .hex-label {
            font-size: 0.6rem;
            color: var(--c-wood-dark, #8b5a2b);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 1px 3px rgba(255,255,255,0.6);
            white-space: nowrap;
        }

        /* ── Activity badges over buildings (DOM overlay) ── */
        #city-badges {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }

        .city-badge {
            position: absolute;
            transform: translate(-50%, -100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            pointer-events: none;
        }

        .city-badge-inner {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--c-panel, #f4e4c1);
            border: 1px solid rgba(127,176,105,0.6);
            border-radius: 12px;
            padding: 3px 8px 3px 6px;
            box-shadow: 0 0 8px rgba(127,176,105,0.3), 0 2px 6px var(--c-shadow, rgba(139,90,43,0.18));
            animation: badge-pulse 2s ease-in-out infinite;
        }

        @keyframes badge-pulse {
            0%, 100% { box-shadow: 0 0 6px rgba(127,176,105,0.2), 0 2px 6px var(--c-shadow, rgba(139,90,43,0.18)); }
            50%       { box-shadow: 0 0 14px rgba(127,176,105,0.55), 0 2px 8px var(--c-shadow, rgba(139,90,43,0.25)); }
        }

        .city-badge-icon {
            font-size: 0.75rem;
            line-height: 1;
        }

        .city-badge-timer {
            font-size: 0.68rem;
            font-family: monospace;
            font-variant-numeric: tabular-nums;
            color: var(--c-success, #7fb069);
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        /* Research badge uses info tint */
        .city-badge.badge-research .city-badge-inner {
            border-color: rgba(95,158,160,0.6);
            box-shadow: 0 0 8px rgba(95,158,160,0.3), 0 2px 6px var(--c-shadow, rgba(139,90,43,0.18));
            animation: badge-pulse-info 2s ease-in-out infinite;
        }
        .city-badge.badge-research .city-badge-timer { color: var(--c-info, #5f9ea0); }

        @keyframes badge-pulse-info {
            0%, 100% { box-shadow: 0 0 6px rgba(95,158,160,0.2), 0 2px 6px var(--c-shadow, rgba(139,90,43,0.18)); }
            50%       { box-shadow: 0 0 14px rgba(95,158,160,0.55), 0 2px 8px var(--c-shadow, rgba(139,90,43,0.25)); }
        }

        /* ── Left side activity panel ── */
        #city-activity-panel {
            position: fixed;
            left: 12px;
            bottom: 80px;
            z-index: 150;
            display: flex;
            flex-direction: column;
            gap: 5px;
            pointer-events: none;
        }

        .cap-row {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--c-panel, #f4e4c1);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 10px;
            padding: 5px 10px 5px 6px;
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 12px var(--c-shadow, rgba(139,90,43,0.18));
            min-width: 175px;
        }

        .cap-icon-wrap {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .cap-icon-wrap.cap-build    { background: rgba(212,130,77,0.2); border: 1px solid rgba(212,130,77,0.45); }
        .cap-icon-wrap.cap-research { background: rgba(95,158,160,0.15); border: 1px solid rgba(95,158,160,0.4); }
        .cap-icon-wrap.cap-troop    { background: rgba(127,176,105,0.2); border: 1px solid rgba(127,176,105,0.4); }

        .cap-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1px;
            overflow: hidden;
        }

        .cap-name {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--c-text, #4a3520);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cap-timer {
            font-size: 0.64rem;
            font-family: monospace;
            font-variant-numeric: tabular-nums;
            color: var(--c-muted, #8b6f47);
            white-space: nowrap;
        }

        .cap-timer.cap-timer-build    { color: var(--c-warning, #d4824d); }
        .cap-timer.cap-timer-research { color: var(--c-info, #5f9ea0); }
        .cap-timer.cap-timer-troop    { color: var(--c-success, #7fb069); }

        .cap-actions {
            display: flex;
            gap: 4px;
            margin-top: 3px;
        }
        .cap-action-btn {
            font-size: 0.58rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            line-height: 1.5;
            transition: filter 0.12s;
            pointer-events: auto;
        }
        .cap-action-btn:hover { filter: brightness(1.15); }
        .cap-action-btn.cancel {
            background: rgba(192,96,77,0.15);
            color: var(--c-danger, #c0604d);
            border: 1px solid rgba(192,96,77,0.4);
        }
        .cap-action-btn.speedup {
            background: rgba(192,136,88,0.15);
            color: var(--c-gold, #c08858);
            border: 1px solid rgba(192,136,88,0.4);
        }

        /* ── Building modal overlay ── */
        #bldg-overlay {
            position: fixed;
            inset: 0;
            background: rgba(74,53,32,0.6);
            z-index: 4000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 60px 8px 20px;
            backdrop-filter: blur(2px);
            animation: overlay-fade-in 0.18s ease;
        }
        #bldg-wrap {
            width: 100%;
            max-width: 960px;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 100px);
            border-radius: 12px;
            box-shadow: 0 24px 80px var(--c-shadow, rgba(139,90,43,0.18)), 0 0 0 1px var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel, #f4e4c1);
            animation: modal-slide-in 0.22s cubic-bezier(0.34, 1.3, 0.64, 1);
        }
    </style>
</head>
<body>
<?php $hudCurrentView = 'city'; require __DIR__ . '/partials/hud.php'; ?>
<div id="game">

<div id="city-wrap">
    <canvas id="city-canvas"></canvas>
    <!-- Activity badges overlay (positioned over canvas) -->
    <div id="city-badges"></div>
</div>

<div id="city-tooltip"></div>

<!-- Building action popup (three-part layout) -->
<div id="building-popup">
    <div id="bp-above">
        <div id="bp-header">
            <span id="bp-name">—</span>
            <span id="bp-badge">Lv 1</span>
        </div>
    </div>
    <div id="bp-spacer"></div>
    <div id="bp-below">
        <div id="bp-actions"></div>
    </div>
</div>

<!-- Left side activity panel -->
<div id="city-activity-panel"></div>

<!-- Hospital wounded panel (Alpine.js, only visible when wounded > 0) -->
<div id="hospital-panel"
     x-data="hospitalApp()" x-init="loadHospital()"
     x-show="hospitalWounded.length > 0" x-cloak
     style="position:fixed;right:12px;bottom:80px;z-index:150;width:220px">
    <div style="background:var(--c-panel,#f4e4c1);border:1px solid rgba(192,96,77,0.4);border-radius:10px;padding:10px 12px;backdrop-filter:blur(8px);box-shadow:0 2px 12px var(--c-shadow,rgba(139,90,43,0.18))">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:0.68rem;font-weight:800;color:var(--c-danger,#c0604d);text-transform:uppercase;letter-spacing:0.06em">🏥 Hospital</div>
            <button style="font-size:0.62rem;padding:3px 9px;border-radius:5px;background:linear-gradient(180deg,#c0604d,#9a3e30);border:none;border-bottom:2px solid #7a2e22;color:#fff8ec;cursor:pointer;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;box-shadow:0 2px 6px var(--c-shadow,rgba(139,90,43,0.18))"
                    @click="instantHeal()">
                Sofort (50💎)
            </button>
        </div>
        <template x-for="w in hospitalWounded" :key="w.troop_code">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid var(--c-border,rgba(139,90,43,0.35));font-size:0.72rem">
                <span style="color:var(--c-text,#4a3520);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px" x-text="w.troop_name ?? w.troop_code"></span>
                <span style="color:var(--c-danger,#c0604d);font-weight:700;white-space:nowrap;margin-left:6px" x-text="Number(w.count).toLocaleString()"></span>
            </div>
        </template>
    </div>
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

// Queue data from PHP
const RESEARCH_QUEUE = <?= json_encode($researchQueue) ?>;
const TROOP_QUEUE    = <?= json_encode($troopQueueForJs) ?>;
const CSRF_TOKEN     = <?= json_encode($session['csrf_token'] ?? '') ?>;

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
window.addEventListener('resize', () => {
    resizeCanvas();
    renderBadges();
});

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
    academy:          { icon: '🔬', label: 'Research', tab: 'forschung' },
    barrack:          { icon: '⚔️',  label: 'Training', tab: 'truppen' },
    hospital:         { icon: '💊', label: 'Heal',     tab: 'heilen' },
    trading_post:     { icon: '📦', label: 'Trade',    tab: 'caravan' },
    hall_of_alliance: { icon: '🤝', label: 'Alliance', tab: 'allianz' },
};

// ---------------------------------------------------------------------------
// Shared countdown formatter
// ---------------------------------------------------------------------------
function fmtCountdown(ms) {
    if (ms <= 0) return '00:00:00';
    const totalSec = Math.floor(ms / 1000);
    const d = Math.floor(totalSec / 86400);
    const h = Math.floor((totalSec % 86400) / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    const hms = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    return d > 0 ? `${d}d ${hms}` : hms;
}

// ---------------------------------------------------------------------------
// Popup helpers
// ---------------------------------------------------------------------------
const popup     = document.getElementById('building-popup');
const bpAbove   = document.getElementById('bp-above');
const bpSpacer  = document.getElementById('bp-spacer');
const bpBelow   = document.getElementById('bp-below');
const bpName    = document.getElementById('bp-name');
const bpBadge   = document.getElementById('bp-badge');
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

    bpName.textContent  = name.toUpperCase();
    bpBadge.textContent = inQueue ? `Lv ${level} → ${data.levelTo}` : `Lv ${level}`;

    // Clear any old popup timer (timer is now in the activity panel, not popup)
    clearInterval(popupTimer);

    // Action buttons
    bpActions.innerHTML = '';
    const btns = [];
    btns.push(makeHexBtn('📋', 'Details', () => openBuildingModal(building.code)));
    btns.push(makeHexBtn(
        inQueue ? '⚡' : '⬆',
        inQueue ? 'Speedup' : 'Upgrade',
        () => openBuildingModal(building.code),
        true   // primary = larger center button
    ));
    if (func) {
        if (func.tab) {
            btns.push(makeHexBtn(func.icon, func.label, () => openBuildingModal(building.code, func.tab)));
        } else {
            btns.push(makeHexBtn(func.icon, func.label, () => { window.location.href = func.url; }));
        }
    }
    btns.forEach(b => bpActions.appendChild(b));

    // Arc: center button drops down, side buttons stay at baseline
    if (btns.length >= 3) {
        const arcDrop = 18;
        const mid = (btns.length - 1) / 2;
        btns.forEach((b, i) => {
            const dist   = i - mid;
            const factor = 1 - (dist / mid) ** 2;
            b.style.transform = `translateY(${(factor * arcDrop).toFixed(1)}px)`;
        });
    }

    // Position: top of popup = top of building (bTopY).
    // #bp-above floats above, #bp-spacer covers the building, #bp-below holds buttons.
    popup.style.display = 'block';

    const rect   = canvas.getBoundingClientRect();
    const scaleX = rect.width  / canvas.width;
    const scaleY = rect.height / canvas.height;

    const bCenterX   = rect.left + (building.x + building.size / 2) * scaleX;
    const bTopY      = rect.top  + building.y * scaleY;
    const buildingPx = building.size * scaleY;

    // Set spacer height to match building height on screen
    bpSpacer.style.height = Math.round(buildingPx) + 'px';

    // Measure popup width after display
    const pw = popup.offsetWidth;
    let left = bCenterX - pw / 2;
    left = Math.max(8, Math.min(window.innerWidth - pw - 8, left));

    // Top anchor = building top; clamp so #bp-above doesn't go off-screen
    let top = bTopY - bpAbove.offsetHeight - 4;
    if (top < 56) top = 56; // below fixed topbar

    // Recompute: popup top = where bp-above starts
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
// Activity badges — DOM overlay above the canvas
// ---------------------------------------------------------------------------
const badgesContainer = document.getElementById('city-badges');

function renderBadges() {
    // Collect all active queues to show badges for
    const badgeItems = [];

    // Building queues
    for (const b of BUILDING_DEFS) {
        const data = BUILDINGS_DATA[b.code];
        if (data?.inQueue && data.finishesAt) {
            badgeItems.push({
                type:       'build',
                code:       b.code,
                icon:       '🔨',
                finishesAt: data.finishesAt,
                building:   b,
            });
        }
    }

    // Research queue — show on academy building
    if (RESEARCH_QUEUE && RESEARCH_QUEUE.finishesAt) {
        const academy = BUILDING_DEFS.find(b => b.code === 'academy');
        if (academy) {
            badgeItems.push({
                type:       'research',
                code:       'academy_research',
                icon:       '🔬',
                finishesAt: RESEARCH_QUEUE.finishesAt,
                building:   academy,
            });
        }
    }

    // Troop queue — show on barracks building
    if (TROOP_QUEUE && TROOP_QUEUE.length > 0) {
        const barrack = BUILDING_DEFS.find(b => b.code === 'barrack');
        if (barrack) {
            // Use the nearest finish time
            const nearest = TROOP_QUEUE.reduce((a, b) => a.finishesAt < b.finishesAt ? a : b);
            badgeItems.push({
                type:       'troop',
                code:       'barrack_troop',
                icon:       '⚔',
                finishesAt: nearest.finishesAt,
                building:   barrack,
            });
        }
    }

    // Clear and rebuild badge DOM
    badgesContainer.innerHTML = '';

    const rect   = canvas.getBoundingClientRect();
    const scaleX = rect.width  / canvas.width;
    const scaleY = rect.height / canvas.height;

    for (const item of badgeItems) {
        const b = item.building;

        // Position: center-bottom of building in canvas-relative % coords
        const centerX = (b.x + b.size / 2) * scaleX;
        const bottomY = (b.y + b.size - 8) * scaleY; // slightly above bottom edge

        const badge = document.createElement('div');
        badge.className = 'city-badge' + (item.type === 'research' ? ' badge-research' : '');
        badge.dataset.code = item.code;
        badge.style.left = centerX + 'px';
        badge.style.top  = bottomY + 'px';

        const timerText = fmtCountdown(item.finishesAt * 1000 - Date.now());

        badge.innerHTML =
            `<div class="city-badge-inner">
                <span class="city-badge-icon">${item.icon}</span>
                <span class="city-badge-timer" data-finishes="${item.finishesAt}">${timerText}</span>
             </div>`;

        badgesContainer.appendChild(badge);
    }
}

// ---------------------------------------------------------------------------
// Activity panel — left sidebar
// ---------------------------------------------------------------------------
const activityPanel = document.getElementById('city-activity-panel');

const TROOP_NAMES = {};  // populated on demand — troop codes shown as-is for now

function renderActivityPanel() {
    const rows = [];

    // Building queues
    for (const b of BUILDING_DEFS) {
        const data = BUILDINGS_DATA[b.code];
        if (data?.inQueue && data.finishesAt) {
            const name = BUILDING_NAMES[b.code] ?? b.code;
            rows.push({
                type:       'build',
                icon:       '🔨',
                name:       name + ' → Lv ' + data.levelTo,
                finishesAt: data.finishesAt,
                queueId:    data.queueId,
                cancelUrl:  data.queueId ? '/api/city/cancel-build/' + data.queueId : null,
                speedupUrl: data.queueId ? '/api/city/speedup-build/' + data.queueId : null,
            });
        }
    }

    // Research queue
    if (RESEARCH_QUEUE && RESEARCH_QUEUE.finishesAt) {
        const resName = RESEARCH_QUEUE.code.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        rows.push({
            type:       'research',
            icon:       '🔬',
            name:       resName + ' Lv ' + RESEARCH_QUEUE.levelTo,
            finishesAt: RESEARCH_QUEUE.finishesAt,
            queueId:    RESEARCH_QUEUE.id,
            cancelUrl:  RESEARCH_QUEUE.id ? '/api/research/cancel/' + RESEARCH_QUEUE.id : null,
            speedupUrl: RESEARCH_QUEUE.id ? '/api/research/speedup/' + RESEARCH_QUEUE.id : null,
        });
    }

    // Troop queue (one row per batch)
    if (TROOP_QUEUE && TROOP_QUEUE.length > 0) {
        TROOP_QUEUE.forEach(tq => {
            const troopName = String(tq.code).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            rows.push({
                type:       'troop',
                icon:       '⚔',
                name:       tq.count + '× ' + troopName,
                finishesAt: tq.finishesAt,
                queueId:    tq.id,
                cancelUrl:  tq.id ? '/api/troops/cancel-train/' + tq.id : null,
                speedupUrl: tq.id ? '/api/troops/speedup-train/' + tq.id : null,
            });
        });
    }

    // Rebuild DOM if row count changed; otherwise just update timers
    const existing = activityPanel.querySelectorAll('.cap-row');
    if (existing.length !== rows.length) {
        activityPanel.innerHTML = '';
        for (const row of rows) {
            const div = document.createElement('div');
            div.className = 'cap-row';
            div.style.pointerEvents = 'auto';
            const cancelBtn = row.cancelUrl
                ? `<button class="cap-action-btn cancel" onclick="capCancel('${row.cancelUrl}','${row.type}')">✕ Abbruch</button>`
                : '';
            const speedupBtn = row.speedupUrl
                ? `<button class="cap-action-btn speedup" onclick="capSpeedup('${row.speedupUrl}','${row.type}')">⏩ Speedup</button>`
                : '';
            div.innerHTML =
                `<div class="cap-icon-wrap cap-${row.type}">${row.icon}</div>
                 <div class="cap-text">
                     <span class="cap-name">${row.name}</span>
                     <span class="cap-timer cap-timer-${row.type}" data-finishes="${row.finishesAt}">
                         ${fmtCountdown(row.finishesAt * 1000 - Date.now())}
                     </span>
                     <div class="cap-actions">${cancelBtn}${speedupBtn}</div>
                 </div>`;
            activityPanel.appendChild(div);
        }
    } else {
        // Just update timer text
        existing.forEach((row, i) => {
            const timerEl = row.querySelector('.cap-timer');
            if (timerEl && rows[i]) {
                timerEl.textContent = fmtCountdown(rows[i].finishesAt * 1000 - Date.now());
            }
        });
    }
}

// ---------------------------------------------------------------------------
// Activity panel — Cancel / Speedup helpers
// ---------------------------------------------------------------------------
async function capCancel(url, type) {
    const label = type === 'research'
        ? 'Forschung abbrechen? (keine Ressourcen-Rückgabe)'
        : type === 'build'
            ? 'Bau abbrechen? Ressourcen werden zurückerstattet.'
            : 'Training abbrechen? Anteilige Ressourcen werden zurückerstattet.';
    if (!confirm(label)) return;
    try {
        const r = await fetch(url, {
            method:  'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN, 'Content-Type': 'application/json' },
        });
        const j = await r.json();
        if (j.ok) {
            setTimeout(() => window.location.reload(), 400);
        } else {
            alert(j.message ?? j.error ?? 'Fehler');
        }
    } catch { alert('Netzwerkfehler'); }
}

async function capSpeedup(url, type) {
    // Show a simple prompt to input the item_code for now.
    // In a future iteration this would be a full item-picker modal.
    const itemMap = {
        build:    [
            { code: 10103011, name: 'Bau +1h' },
            { code: 10103012, name: 'Bau +3h' },
            { code: 10103013, name: 'Bau +8h' },
            { code: 10103001, name: 'Generic +5m' },
            { code: 10103003, name: 'Generic +1h' },
        ],
        research: [
            { code: 10103021, name: 'Forschung +1h' },
            { code: 10103022, name: 'Forschung +3h' },
            { code: 10103023, name: 'Forschung +8h' },
            { code: 10103001, name: 'Generic +5m' },
            { code: 10103003, name: 'Generic +1h' },
        ],
        troop:    [
            { code: 10103031, name: 'Training +1h' },
            { code: 10103032, name: 'Training +3h' },
            { code: 10103001, name: 'Generic +5m' },
            { code: 10103003, name: 'Generic +1h' },
        ],
    };
    const items = itemMap[type] ?? itemMap.build;
    const itemList = items.map((it, i) => (i + 1) + '. ' + it.name + ' (' + it.code + ')').join('\n');
    const choice = prompt('Speedup-Item auswählen:\n' + itemList + '\n\nItem-Code eingeben:');
    if (!choice) return;
    const itemCode = parseInt(choice.trim(), 10);
    if (!itemCode) { alert('Ungültiger Item-Code.'); return; }
    try {
        const r = await fetch(url, {
            method:  'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN, 'Content-Type': 'application/json' },
            body:    JSON.stringify({ item_code: itemCode }),
        });
        const j = await r.json();
        if (j.ok) {
            const msg = j.data.instantly_finished ? 'Sofort fertig!' : 'Speedup angewendet!';
            setTimeout(() => window.location.reload(), 400);
        } else {
            alert(j.message ?? j.error ?? 'Fehler');
        }
    } catch { alert('Netzwerkfehler'); }
}

// ---------------------------------------------------------------------------
// Live countdown tick (badges + panel, every second)
// ---------------------------------------------------------------------------
function tickCountdowns() {
    // Update badge timers in-place
    badgesContainer.querySelectorAll('.city-badge-timer').forEach(el => {
        const finishesAt = parseInt(el.dataset.finishes, 10);
        if (finishesAt) {
            el.textContent = fmtCountdown(finishesAt * 1000 - Date.now());
        }
    });

    // Update panel timers in-place (or rebuild if structure changed)
    renderActivityPanel();
}

// Initial render + start interval
renderBadges();
renderActivityPanel();
setInterval(tickCountdowns, 1000);

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
            ctx.fillStyle = 'rgba(240,232,208,0.9)';
            ctx.fillRect(badgeX - tw/2 - 4, badgeY - fontSize - 2, tw + 8, fontSize + 6);
            ctx.fillStyle = inQueue ? '#d4824d' : '#4a3520';
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
            ctx.fillStyle = 'rgba(240,232,208,0.82)';
            ctx.fillRect(lx - tw2/2 - 3, ly - fs - 1, tw2 + 6, fs + 4);
            ctx.fillStyle    = '#8b6f47';
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
async function openBuildingModal(code, tab = 'upgrade') {
    closePopup();
    const overlay = document.getElementById('bldg-overlay');
    const wrap    = document.getElementById('bldg-wrap');
    wrap.innerHTML = '<p style="color:var(--c-muted,#8b6f47);text-align:center;padding:60px 0;font-family:system-ui">Laden…</p>';
    overlay.style.display = 'flex';

    const r    = await fetch(`/city/building/${code}?modal=1&tab=${encodeURIComponent(tab)}`);
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
window.closeBldgModal    = closeBldgModal;
window.openBuildingModal = openBuildingModal;
</script>

<div id="bldg-overlay" style="display:none" onclick="closeBldgModal(event)">
    <div id="bldg-wrap"></div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function hospitalApp() {
    return {
        hospitalWounded: [],

        async loadHospital() {
            try {
                const r = await fetch('/api/hospital/status');
                const j = await r.json();
                if (j.ok) this.hospitalWounded = j.data.wounded ?? [];
            } catch {}
        },

        async instantHeal() {
            if (!confirm('50 Gems für Sofort-Heilung ausgeben?')) return;
            try {
                const r = await fetch('/api/hospital/instant-heal', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ csrf_token: <?= json_encode($session['csrf_token'] ?? '') ?> }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.hospitalWounded = [];
                } else {
                    alert(j.message || j.error || 'Fehler');
                }
            } catch { alert('Netzwerkfehler'); }
        },
    };
}
</script>
</body>
</html>
