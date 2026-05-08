<?php
declare(strict_types=1);

/**
 * Building detail / upgrade page — SPEC §4
 *
 * Variables provided by index.php:
 *   $session       array  — current session row
 *   $state         array  — CityState::loadForPlayer() result
 *   $buildingCode  string — validated building code
 */

use Conquer\Game\City\BuildingData;
use Conquer\Game\City\CityState;
use Conquer\Game\City\TroopData;

$city       = $state['city'];
$buildings  = $state['buildings'];
$queue      = $state['build_queue'];
$troops     = $state['troops']     ?? [];
$troopQueue = $state['troop_queue'] ?? [];

// Gem cost for instant-build: 1 gem per minute remaining (min 1)
$instantGemCost = 0;
if ($queueEntry !== null) {
    $secsLeft       = max(0, strtotime($queueEntry['finishes_at']) - time());
    $instantGemCost = max(1, (int) ceil($secsLeft / 60));
}

// Player gems (from session)
$playerGems = (int) ($session['gems'] ?? 0);

$building = $buildings[$buildingCode] ?? null;
if ($building === null) {
    header('Location: /city');
    exit;
}

$currentLevel = (int) $building['level'];
$nextLevel    = $currentLevel + 1;

// Queue entry for this building (if any)
$queueEntry = null;
foreach ($queue as $entry) {
    if ($entry['building_code'] === $buildingCode) {
        $queueEntry = $entry;
        break;
    }
}

$name     = CityState::BUILDING_NAMES[$buildingCode] ?? ucwords(str_replace('_', ' ', $buildingCode));
$cost     = BuildingData::getCost($buildingCode, $nextLevel);
$buildSec = BuildingData::getBuildTime($buildingCode, $nextLevel);
$castleReqs = $buildingCode === 'castle' ? BuildingData::getCastleRequirements($nextLevel) : [];

$fmt = static fn (int|string $n): string => number_format((int) $n, 0, '.', ',');

// Format seconds → "1h 4m" etc.
function fmtTime(int $sec): string {
    if ($sec < 60)  return $sec . 's';
    if ($sec < 3600) return floor($sec / 60) . 'm ' . ($sec % 60) . 's';
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    return $h . 'h ' . $m . 'm';
}

// Check if player has enough resources
$canAfford =
    $city['food']   >= $cost['food']   &&
    $city['lumber'] >= $cost['lumber'] &&
    $city['stone']  >= $cost['stone']  &&
    $city['gold']   >= $cost['gold'];

// Check castle requirements are met
$reqsMet = true;
$reqsUnmet = [];
foreach ($castleReqs as $reqCode => $reqLevel) {
    $has = (int) ($buildings[$reqCode]['level'] ?? 0);
    if ($has < $reqLevel) {
        $reqsMet = false;
        $reqsUnmet[$reqCode] = ['need' => $reqLevel, 'have' => $has];
    }
}

// Tile definitions (must match city.php)
$tileDefs = [
    'castle'           => [96, 97, 108, 109],   // 2×2 composite
    'farm'             => 5,
    'storage'          => 36,
    'treasure_house'   => 122,
    'quarry'           => 12,
    'academy'          => 110,
    'wall'             => 17,
    'trading_post'     => 84,
    'gold_mine'        => 120,
    'lumber_camp'      => 6,
    'barrack'          => 44,
    'hall_of_alliance' => 49,
    'hospital'         => 45,
];
$tile = $tileDefs[$buildingCode] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — <?= htmlspecialchars($name) ?></title>
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
            min-height: 100%;
            background: #000;
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 1280px;
            min-height: 100vh;
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
            display: inline-block;
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); }

        /* ── Main layout ── */
        .main {
            flex: 1;
            padding-top: 2rem;
            padding-bottom: 2rem;
            display: flex;
            justify-content: center;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 480px;
            margin: 0 1rem;
        }

        /* ── Building sprite ── */
        .sprite-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }

        #bldg-canvas {
            image-rendering: pixelated;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
        }

        .bldg-name {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .bldg-level {
            font-size: 0.9rem;
            color: var(--muted);
        }

        /* ── Section headings ── */
        .section-title {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            margin-bottom: 0.6rem;
        }

        /* ── Cost grid ── */
        .costs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .cost-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.45rem 0.65rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
        }

        .cost-item.lacking {
            border-color: var(--red);
            background: rgba(239,68,68,0.08);
        }

        .cost-label { color: var(--muted); }
        .cost-value { font-weight: 600; font-variant-numeric: tabular-nums; }
        .cost-item.lacking .cost-value { color: var(--red); }

        /* ── Build time ── */
        .build-time {
            text-align: center;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 1.5rem;
        }

        .build-time span {
            color: var(--accent);
            font-weight: 600;
        }

        /* ── Requirements ── */
        .reqs {
            background: rgba(239,68,68,0.07);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.82rem;
        }

        .reqs p { margin-bottom: 0.3rem; color: var(--muted); }
        .reqs ul { list-style: none; padding: 0; }
        .reqs li { color: var(--red); padding: 0.15rem 0; }
        .reqs li::before { content: '✗  '; }

        /* ── Upgrade button ── */
        .btn-upgrade {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: var(--accent);
            color: #fff;
            transition: opacity .15s;
        }

        .btn-upgrade:hover:not(:disabled) { opacity: 0.85; }
        .btn-upgrade:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; }

        /* ── Queue banner ── */
        .queue-banner {
            background: rgba(245,158,11,0.1);
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            text-align: center;
            font-size: 0.88rem;
            color: var(--gold);
        }

        .queue-banner strong { display: block; font-size: 1rem; margin-bottom: 0.2rem; }
        #countdown { font-weight: 600; }

        /* ── Toast ── */
        #toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
            display: none;
            z-index: 50;
            white-space: nowrap;
        }
        #toast.ok  { border-color: var(--green); color: var(--green); }
        #toast.err { border-color: var(--red);   color: var(--red);   }

        /* ── Troop training section ── */
        .troop-section {
            margin-top: 2rem;
        }

        .troop-row {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.9rem 1rem;
            margin-bottom: 0.6rem;
        }

        .troop-row.locked {
            opacity: 0.5;
        }

        .troop-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.6rem;
        }

        .troop-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            background: var(--accent);
            color: #fff;
        }

        .troop-badge.cav { background: #8b5cf6; }
        .troop-badge.rgd { background: #22c55e; }

        .troop-name {
            font-weight: 600;
            font-size: 0.92rem;
            flex: 1;
        }

        .troop-count {
            font-size: 0.82rem;
            color: var(--muted);
        }

        .troop-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.3rem;
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
        }

        .troop-stat span { color: var(--text); font-weight: 600; }

        .troop-cost {
            display: flex;
            gap: 0.5rem;
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .troop-cost span { color: var(--text); }

        .train-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .train-input {
            width: 80px;
            padding: 0.3rem 0.5rem;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 0.85rem;
        }

        .btn-train {
            flex: 1;
            padding: 0.35rem 0.8rem;
            border-radius: 5px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: var(--accent);
            color: #fff;
        }

        .btn-train:hover:not(:disabled) { opacity: 0.85; }
        .btn-train:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; }

        .lock-msg {
            font-size: 0.75rem;
            color: var(--muted);
            font-style: italic;
        }

        .troop-queue-list {
            margin-top: 1.2rem;
        }

        .queue-item {
            background: rgba(245,158,11,0.08);
            border: 1px solid var(--gold);
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            margin-bottom: 0.5rem;
            font-size: 0.82rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .queue-item-cd { color: var(--gold); font-weight: 600; }
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

<div class="main">
    <div class="card">

        <div style="margin-bottom:1.25rem">
            <a href="/city" class="btn">← Zurück zur Stadt</a>
        </div>

        <!-- Building sprite + name -->
        <div class="sprite-wrap">
            <canvas id="bldg-canvas" width="192" height="192"></canvas>
            <div class="bldg-name"><?= htmlspecialchars($name) ?></div>
            <div class="bldg-level">Level <?= $currentLevel ?><?= $queueEntry ? ' → ' . (int)$queueEntry['level_to'] . ' (im Bau)' : '' ?></div>
        </div>

        <?php if ($queueEntry !== null): ?>
        <!-- In der Queue -->
        <div class="queue-banner">
            <strong>Upgrade läuft...</strong>
            Level <?= (int)$queueEntry['level_to'] ?> fertig in
            <span id="countdown" data-finish="<?= strtotime($queueEntry['finishes_at']) ?>">—</span>
        </div>

        <button
            id="btn-instant"
            class="btn-upgrade"
            style="margin-top:0.75rem;background:#8b5cf6"
            data-queue-id="<?= (int)$queueEntry['id'] ?>"
            data-gem-cost="<?= $instantGemCost ?>"
            <?= $playerGems < $instantGemCost ? 'disabled' : '' ?>
        >
            <?php if ($playerGems < $instantGemCost): ?>
                💎 <?= number_format($instantGemCost) ?> Gems fehlen (du: <?= number_format($playerGems) ?>)
            <?php else: ?>
                💎 Sofort fertig (<?= number_format($instantGemCost) ?> Gems)
            <?php endif ?>
        </button>

        <?php else: ?>
        <!-- Upgrade-Infos -->
        <div class="section-title">Upgrade auf Level <?= $nextLevel ?></div>

        <div class="costs">
            <div class="cost-item<?= $city['food']   < $cost['food']   ? ' lacking' : '' ?>">
                <span class="cost-label">🌾 Nahrung</span>
                <span class="cost-value"><?= $fmt($cost['food']) ?></span>
            </div>
            <div class="cost-item<?= $city['lumber'] < $cost['lumber'] ? ' lacking' : '' ?>">
                <span class="cost-label">🪵 Holz</span>
                <span class="cost-value"><?= $fmt($cost['lumber']) ?></span>
            </div>
            <div class="cost-item<?= $city['stone']  < $cost['stone']  ? ' lacking' : '' ?>">
                <span class="cost-label">🪨 Stein</span>
                <span class="cost-value"><?= $fmt($cost['stone']) ?></span>
            </div>
            <div class="cost-item<?= $city['gold']   < $cost['gold']   ? ' lacking' : '' ?>">
                <span class="cost-label">💰 Gold</span>
                <span class="cost-value"><?= $fmt($cost['gold']) ?></span>
            </div>
        </div>

        <div class="build-time">Bauzeit: <span><?= fmtTime($buildSec) ?></span></div>

        <?php if (!$reqsMet): ?>
        <div class="reqs">
            <p>Voraussetzungen nicht erfüllt:</p>
            <ul>
                <?php foreach ($reqsUnmet as $rc => $ri): ?>
                <li><?= htmlspecialchars(CityState::BUILDING_NAMES[$rc] ?? $rc) ?> auf Level <?= $ri['need'] ?> (du: <?= $ri['have'] ?>)</li>
                <?php endforeach ?>
            </ul>
        </div>
        <?php endif ?>

        <button
            id="btn-upgrade"
            class="btn-upgrade"
            <?= (!$canAfford || !$reqsMet) ? 'disabled' : '' ?>
            data-code="<?= htmlspecialchars($buildingCode) ?>"
        >
            <?php if (!$canAfford): ?>
                Zu wenig Ressourcen
            <?php elseif (!$reqsMet): ?>
                Voraussetzungen fehlen
            <?php else: ?>
                Upgrade starten
            <?php endif ?>
        </button>

        <?php endif ?>

    </div>
</div>

<?php if ($buildingCode === 'barrack'):
    $academyLevel = (int) ($buildings['academy']['level'] ?? 1);
    $barrackLevel = (int) ($buildings['barrack']['level'] ?? 1);
    $allTroops    = TroopData::all();
    $typeName     = [1 => 'INF', 2 => 'RGD', 3 => 'CAV'];
    $typeClass    = [1 => '', 2 => 'rgd', 3 => 'cav'];
?>
<div style="width:100%;max-width:480px;margin:1.5rem auto 0;padding:0 1rem">

    <div class="section-title">Truppen ausbilden</div>

    <?php foreach ($allTroops as $t):
        $unlocked = TroopData::isUnlocked((int)$t['code'], $barrackLevel, $academyLevel);
        $inCity   = (int) ($troops[(int)$t['code']] ?? 0);
        $badge    = $typeClass[$t['type']] ?? '';
    ?>
    <div class="troop-row<?= $unlocked ? '' : ' locked' ?>">
        <div class="troop-header">
            <span class="troop-badge <?= $badge ?>"><?= $typeName[$t['type']] ?> T<?= (int)$t['tier'] ?></span>
            <span class="troop-name"><?= htmlspecialchars($t['name']) ?></span>
            <span class="troop-count">In Stadt: <?= number_format($inCity, 0, '.', ',') ?></span>
        </div>

        <div class="troop-stats">
            <div>HP <span><?= (int)$t['hp'] ?></span></div>
            <div>ATK <span><?= (int)$t['attack'] ?></span></div>
            <div>DEF <span><?= (int)$t['defense'] ?></span></div>
            <div>SPD <span><?= (int)$t['speed'] ?></span></div>
        </div>

        <div class="troop-cost">
            <?php if ($t['need_food']   > 0): ?><span>🌾 <?= (int)$t['need_food'] ?></span><?php endif ?>
            <?php if ($t['need_lumber'] > 0): ?><span>🪵 <?= (int)$t['need_lumber'] ?></span><?php endif ?>
            <?php if ($t['need_stone']  > 0): ?><span>🪨 <?= (int)$t['need_stone'] ?></span><?php endif ?>
            <?php if ($t['need_gold']   > 0): ?><span>💰 <?= (int)$t['need_gold'] ?></span><?php endif ?>
            <span>⏱ <?= fmtTime((int)$t['time']) ?>/Einheit</span>
        </div>

        <?php if ($unlocked): ?>
        <div class="train-row">
            <input type="number" class="train-input" min="1" max="9999" value="100"
                   id="count-<?= (int)$t['code'] ?>">
            <button class="btn-train"
                    data-code="<?= (int)$t['code'] ?>"
                    data-name="<?= htmlspecialchars($t['name']) ?>">
                Ausbilden
            </button>
        </div>
        <?php else: ?>
        <div class="lock-msg">🔒 Erfordert Academy Level <?= (int)$t['unlock_academy'] ?></div>
        <?php endif ?>
    </div>
    <?php endforeach ?>

    <?php if (!empty($troopQueue)): ?>
    <div class="troop-queue-list">
        <div class="section-title" style="margin-top:1.25rem">Trainings-Queue</div>
        <?php foreach ($troopQueue as $qe):
            $qTroop = TroopData::get((int)$qe['troop_code']);
            $qName  = $qTroop['name'] ?? ('Code ' . $qe['troop_code']);
        ?>
        <div class="queue-item">
            <div><?= (int)$qe['count'] ?>× <?= htmlspecialchars($qName) ?> (Slot <?= (int)$qe['barrack_slot'] ?>)</div>
            <div class="queue-item-cd"
                 data-finish="<?= strtotime($qe['finishes_at']) ?>">—</div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

</div>
<?php endif ?>

<div id="toast"></div>

</div><!-- #game -->

<script>
'use strict';

// ---------------------------------------------------------------------------
// Atlas sprite rendering
// ---------------------------------------------------------------------------
const ATLAS_SRC  = '/assets/sprites/kenney-tiny-town/Tilemap/tilemap_packed.png';
const ATLAS_TILE = 16;
const ATLAS_STEP = 17;
const ATLAS_COLS = 12;

const canvas = document.getElementById('bldg-canvas');
const ctx    = canvas.getContext('2d');
ctx.imageSmoothingEnabled = false;

const atlas = new Image();

const TILE   = <?= is_array($tile) ? json_encode($tile) : (int) $tile ?>;
const IS_2X2 = Array.isArray(TILE);

atlas.onload = () => {
    ctx.clearRect(0, 0, 192, 192);

    function drawTile(idx, dx, dy, size) {
        const sx = (idx % ATLAS_COLS) * ATLAS_STEP;
        const sy = Math.floor(idx / ATLAS_COLS) * ATLAS_STEP;
        ctx.drawImage(atlas, sx, sy, ATLAS_TILE, ATLAS_TILE, dx, dy, size, size);
    }

    if (IS_2X2) {
        drawTile(TILE[0],  0,  0, 96);
        drawTile(TILE[1], 96,  0, 96);
        drawTile(TILE[2],  0, 96, 96);
        drawTile(TILE[3], 96, 96, 96);
    } else {
        drawTile(TILE, 0, 0, 192);
    }
};
atlas.src = ATLAS_SRC;

// ---------------------------------------------------------------------------
// Upgrade button
// ---------------------------------------------------------------------------
const btn      = document.getElementById('btn-upgrade');
const CSRF     = <?= json_encode($session['csrf_token']) ?>;

if (btn) {
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.textContent = 'Wird gestartet…';

        try {
            const res  = await fetch('/api/city/upgrade-building', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                },
                body: JSON.stringify({ building_code: btn.dataset.code }),
            });
            const json = await res.json();

            if (json.ok) {
                showToast('Upgrade gestartet!', 'ok');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                btn.disabled = false;
                btn.textContent = 'Upgrade starten';
            }
        } catch (e) {
            showToast('Netzwerkfehler', 'err');
            btn.disabled = false;
            btn.textContent = 'Upgrade starten';
        }
    });
}

// ---------------------------------------------------------------------------
// Queue countdown
// ---------------------------------------------------------------------------
const cdEl = document.getElementById('countdown');

function updateCountdown() {
    if (!cdEl) return;
    const finish = parseInt(cdEl.dataset.finish, 10) * 1000;
    const rem    = Math.max(0, Math.ceil((finish - Date.now()) / 1000));

    if (rem === 0) {
        cdEl.textContent = 'fertig!';
        setTimeout(() => window.location.reload(), 1500);
        return;
    }

    const h = Math.floor(rem / 3600);
    const m = Math.floor((rem % 3600) / 60);
    const s = rem % 60;

    if (h > 0) cdEl.textContent = h + 'h ' + m + 'm ' + s + 's';
    else if (m > 0) cdEl.textContent = m + 'm ' + s + 's';
    else cdEl.textContent = s + 's';
}

if (cdEl) {
    updateCountdown();
    setInterval(updateCountdown, 1000);
}

// ---------------------------------------------------------------------------
// Instant build button
// ---------------------------------------------------------------------------
const btnInstant = document.getElementById('btn-instant');
if (btnInstant) {
    btnInstant.addEventListener('click', async () => {
        const queueId = btnInstant.dataset.queueId;
        const cost    = parseInt(btnInstant.dataset.gemCost, 10);

        if (!confirm('💎 ' + cost.toLocaleString() + ' Gems verwenden um sofort fertig zu bauen?')) return;

        btnInstant.disabled = true;
        btnInstant.textContent = '…';

        try {
            const res  = await fetch('/api/city/instant-build/' + queueId, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
            });
            const json = await res.json();

            if (json.ok) {
                showToast('💎 Sofort fertiggestellt! ' + json.data.gems_spent + ' Gems verwendet.', 'ok');
                setTimeout(() => window.location.reload(), 900);
            } else {
                showToast(json.error?.message ?? json.error ?? 'Fehler', 'err');
                btnInstant.disabled = false;
                btnInstant.textContent = '💎 Sofort fertig (' + cost.toLocaleString() + ' Gems)';
            }
        } catch (e) {
            showToast('Netzwerkfehler', 'err');
            btnInstant.disabled = false;
        }
    });
}

// ---------------------------------------------------------------------------
// Troop training buttons
// ---------------------------------------------------------------------------
document.querySelectorAll('.btn-train').forEach(btn => {
    btn.addEventListener('click', async () => {
        const code  = parseInt(btn.dataset.code, 10);
        const name  = btn.dataset.name;
        const input = document.getElementById('count-' + code);
        const count = parseInt(input?.value ?? '0', 10);

        if (!count || count < 1) {
            showToast('Ungültige Anzahl', 'err');
            return;
        }

        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '…';

        try {
            const res  = await fetch('/api/troops/train', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                },
                body: JSON.stringify({ troop_code: code, count }),
            });
            const json = await res.json();

            if (json.ok) {
                showToast('Training gestartet: ' + count + '× ' + name, 'ok');
                setTimeout(() => window.location.reload(), 900);
            } else {
                showToast(json.error?.message ?? json.error ?? 'Fehler', 'err');
                btn.disabled = false;
                btn.textContent = orig;
            }
        } catch (e) {
            showToast('Netzwerkfehler', 'err');
            btn.disabled = false;
            btn.textContent = orig;
        }
    });
});

// ---------------------------------------------------------------------------
// Queue countdowns (training queue items)
// ---------------------------------------------------------------------------
function updateQueueCountdowns() {
    document.querySelectorAll('.queue-item-cd').forEach(el => {
        const finish = parseInt(el.dataset.finish, 10) * 1000;
        const rem    = Math.max(0, Math.ceil((finish - Date.now()) / 1000));
        if (rem === 0) {
            el.textContent = 'fertig!';
            setTimeout(() => window.location.reload(), 1200);
            return;
        }
        const h = Math.floor(rem / 3600);
        const m = Math.floor((rem % 3600) / 60);
        const s = rem % 60;
        if (h > 0) el.textContent = h + 'h ' + m + 'm ' + s + 's';
        else if (m > 0) el.textContent = m + 'm ' + s + 's';
        else el.textContent = s + 's';
    });
}

updateQueueCountdowns();
setInterval(updateQueueCountdowns, 1000);

// ---------------------------------------------------------------------------
// Toast helper
// ---------------------------------------------------------------------------
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = type;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}
</script>

</body>
</html>
