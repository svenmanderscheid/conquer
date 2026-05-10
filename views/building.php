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

// Gem cost for instant-build: 1 gem per minute remaining (min 1)
$instantGemCost = 0;
if ($queueEntry !== null) {
    $secsLeft       = max(0, strtotime($queueEntry['finishes_at']) - time());
    $instantGemCost = max(1, (int) ceil($secsLeft / 60));
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

// Custom pixel sprites (assets/sprites/pixel/buildings/)
$spriteFiles = [
    'castle'           => 'castle.png',
    'farm'             => 'farm.png',
    'storage'          => 'storage.png',
    'treasure_house'   => 'treasure_house.png',
    'quarry'           => 'quarry.png',
    'academy'          => 'academy.png',
    'wall'             => 'wall_corner.png',
    'trading_post'     => 'trading_post.png',
    'gold_mine'        => 'gold_mine.png',
    'lumber_camp'      => 'lumber_camp.png',
    'barrack'          => 'barracks.png',
    'hall_of_alliance' => 'hall_of_alliance.png',
    'hospital'         => 'hospital.png',
];

$spriteBase = '/assets/sprites/pixel/buildings/';
$spriteSrc  = $spriteBase . ($spriteFiles[$buildingCode] ?? 'castle.png');

$buildingSpriteSrc = static fn(string $code): string =>
    '/assets/sprites/pixel/buildings/' . ($spriteFiles[$code] ?? 'castle.png');

// ---------------------------------------------------------------------------
// Building descriptions
// ---------------------------------------------------------------------------
$bldgDesc = [
    'castle'           => 'Das Herz deiner Stadt. Das Castle-Level bestimmt das maximale Level aller anderen Gebäude.',
    'academy'          => 'Ort des Wissens. Ermöglicht Forschungen zur Stärkung von Truppen und Produktion.',
    'barrack'          => 'Hier werden deine Truppen ausgebildet. Höheres Level entsperrt stärkere Einheiten.',
    'farm'             => 'Produziert stetig Nahrung für Stadt und Truppen.',
    'lumber_camp'      => 'Schlägt Holz für Bauten und Forschungen.',
    'quarry'           => 'Fördert Stein für alle Upgrades.',
    'gold_mine'        => 'Baut Gold ab — die wichtigste Währung im Spiel.',
    'storage'          => 'Erhöht die maximale Lagerkapazität deiner Ressourcen.',
    'treasure_house'   => 'Schützt Ressourcen vor feindlichen Überfällen.',
    'wall'             => 'Erste Verteidigungslinie deiner Stadt.',
    'trading_post'     => 'Ermöglicht Handel und Ressourcentausch.',
    'hall_of_alliance' => 'Zugang zu Allianzen und gemeinsamen Aktionen.',
    'hospital'         => 'Heilt verwundete Truppen nach Kämpfen.',
];
$desc = $bldgDesc[$buildingCode] ?? 'Gebäude deiner Stadt.';

// Key bonus per building at a given level [label, value]
function bldgBonuses(string $code, int $lv): array {
    if ($lv <= 0) return [];
    return match($code) {
        'academy'          => [['Forschungsgeschwindigkeit', '+' . $lv . '.0%'],   ['Power', number_format($lv * 740)]],
        'castle'           => [['Max. Gebäudelevel',         (string)$lv],          ['Power', number_format($lv * 2000)]],
        'farm'             => [['Nahrungsproduktion',         number_format($lv * 500) . '/h'], ['Power', number_format($lv * 300)]],
        'lumber_camp'      => [['Holzproduktion',             number_format($lv * 500) . '/h'], ['Power', number_format($lv * 300)]],
        'quarry'           => [['Steinproduktion',            number_format($lv * 500) . '/h'], ['Power', number_format($lv * 300)]],
        'gold_mine'        => [['Goldproduktion',             number_format($lv * 250) . '/h'], ['Power', number_format($lv * 400)]],
        'storage'          => [['Lagerkapazität',             '+' . number_format($lv * 50000)],['Power', number_format($lv * 200)]],
        'barrack'          => [['Trainingsslots',             (string)$lv],          ['Power', number_format($lv * 800)]],
        'wall'             => [['Stadtverteidigung',          '+' . ($lv * 200)],    ['Power', number_format($lv * 600)]],
        'hospital'         => [['Heilungskapazität',          number_format($lv * 1000)], ['Power', number_format($lv * 350)]],
        'treasure_house'   => [['Ressourcenschutz',           number_format($lv * 10000)],['Power', number_format($lv * 250)]],
        'trading_post'     => [['Handelslimit',               number_format($lv * 5000) . '/h'], ['Power', number_format($lv * 300)]],
        'hall_of_alliance' => [['Allianzkapazität',           (string)($lv * 5)],    ['Power', number_format($lv * 500)]],
        default            => [['Power', number_format($lv * 500)]],
    };
}

// K-format helper
function fmtK(int|float $n): string {
    if ($n >= 1000) return number_format($n / 1000, 1) . 'k';
    return (string)(int)$n;
}

$currentBonuses = bldgBonuses($buildingCode, $currentLevel);
$nextBonuses    = bldgBonuses($buildingCode, $nextLevel);

// Resource rows for the upgrade requirements panel
$resRows = [
    ['emoji' => '🌾', 'name' => 'Nahrung',  'have' => (int)$city['food'],   'need' => (int)$cost['food']],
    ['emoji' => '🪵', 'name' => 'Holz',     'have' => (int)$city['lumber'], 'need' => (int)$cost['lumber']],
    ['emoji' => '🪨', 'name' => 'Stein',    'have' => (int)$city['stone'],  'need' => (int)$cost['stone']],
    ['emoji' => '💰', 'name' => 'Gold',     'have' => (int)$city['gold'],   'need' => (int)$cost['gold']],
];

// Seconds remaining in queue
$secsLeft = 0;
if ($queueEntry !== null) {
    $secsLeft = max(0, strtotime($queueEntry['finishes_at']) - time());
}

$isModal = isset($_GET['modal']);

// ── Tab detection ──────────────────────────────────────────────────────────
$tabs = ['upgrade' => 'Level Up'];
if ($buildingCode === 'barrack')          $tabs['truppen']   = 'Truppen';
if ($buildingCode === 'trading_post')     $tabs['caravan']   = 'Caravan';
if ($buildingCode === 'hall_of_alliance') $tabs['allianz']   = 'Allianz';
if ($buildingCode === 'academy')          $tabs['forschung'] = 'Forschung';
if ($buildingCode === 'hospital')         $tabs['heilen']    = 'Heilen';

$activeTab = $_GET['tab'] ?? 'upgrade';
if (!array_key_exists($activeTab, $tabs)) $activeTab = 'upgrade';
?>
<?php if (!$isModal): ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — <?= htmlspecialchars($name) ?></title>
<?php endif ?>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
<?php if (!$isModal): ?>
    html, body {
        min-height: 100vh;
        background: #060c18;
        color: #e2e8f0;
        font-family: system-ui, -apple-system, sans-serif;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    /* ── Page wrapper ── */
    .page-wrap {
        width: 100%;
        margin-top: 84px; /* nav 72px + 12px gap */
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 8px;
    }
<?php endif ?>

        /* ── Modal card ── */
        .modal-card {
            width: 100%;
            max-width: min(960px, calc(100vw - 16px));
            background: #111827;
            border: 1px solid #2a3a55;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* When rendered inside the city overlay, fill the bldg-wrap height */
        .modal-card.is-overlay {
            flex: 1;
            min-height: 0;
        }

        /* ── Two-panel body ── */
        .modal-body {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* ────────────────────────────────────────
           LEFT PANEL
        ──────────────────────────────────────── */
        .left-panel {
            width: 280px;
            flex-shrink: 0;
            background: #0c1220;
            border-right: 1px solid #2a3a55;
            display: flex;
            flex-direction: column;
        }

        /* Red name banner */
        .bldg-banner {
            background: linear-gradient(180deg, #b91c1c 0%, #7f1d1d 100%);
            border-bottom: 2px solid #d4a017;
            padding: 10px 14px;
            text-align: center;
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1.3;
        }

        /* Large canvas area */
        .bldg-canvas-wrap {
            display: flex;
            justify-content: center;
            padding: 20px 0 10px;
        }

        #bldg-img {
            image-rendering: pixelated;
            width: 160px;
            height: 160px;
            object-fit: contain;
            background: rgba(255,255,255,0.02);
            border-radius: 4px;
        }

        /* Level badge */
        .level-badge-wrap {
            display: flex;
            justify-content: center;
            margin: 8px 0 6px;
        }

        .level-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 18px;
            clip-path: polygon(12px 0%, calc(100% - 12px) 0%, 100% 50%, calc(100% - 12px) 100%, 12px 100%, 0% 50%);
            color: #fbbf24;
            background: #0c1220;
            border: 2px solid #d4a017;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: .05em;
            white-space: nowrap;
        }

        /* Description */
        .bldg-desc {
            font-size: 0.74rem;
            color: #64748b;
            padding: 6px 14px 10px;
            line-height: 1.5;
            text-align: center;
        }

        /* Divider */
        .left-divider {
            border: none;
            border-top: 1px solid #1e2d42;
            margin: 0 10px;
        }

        /* Current bonuses */
        .bonus-section {
            padding: 10px 14px 8px;
            flex: 1;
        }

        .bonus-section-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
            margin-bottom: 6px;
        }

        .bonus-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 3px 0;
            font-size: 0.76rem;
        }

        .bonus-label { color: #94a3b8; }
        .bonus-val   { color: #22c55e; font-weight: 600; font-variant-numeric: tabular-nums; }

        .no-bonus {
            font-size: 0.73rem;
            color: #475569;
            font-style: italic;
        }

        /* Back button at bottom of left panel */
        .left-back {
            padding: 10px 14px;
            border-top: 1px solid #1e2d42;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.78rem;
            transition: color .15s;
        }

        .btn-back:hover { color: #94a3b8; }

        /* ────────────────────────────────────────
           RIGHT PANEL
        ──────────────────────────────────────── */
        .right-panel {
            flex: 1;
            min-width: 0;
            background: #111827;
            display: flex;
            flex-direction: column;
        }

        /* Tab bar */
        .tab-bar {
            height: 40px;
            background: #0c1220;
            border-bottom: 1px solid #2a3a55;
            display: flex;
            align-items: stretch;
        }

        .tab-item {
            display: inline-flex;
            align-items: center;
            padding: 0 18px;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 2px solid transparent;
            border-top: none; border-left: none; border-right: none;
            background: none;
            cursor: pointer;
            white-space: nowrap;
            transition: color .15s;
        }

        .tab-item.tab-active {
            color: #fbbf24;
            border-bottom-color: #fbbf24;
        }

        .tab-item:hover:not(.tab-active) { color: #94a3b8; }

        .tab-spacer { flex: 1; }

        /* Tab pane container */
        .tab-pane {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        .tab-pane.tab-scroll {
            overflow-y: auto;
        }

        .tab-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            color: #64748b;
            text-decoration: none;
            font-size: 1.1rem;
            border-left: 1px solid #2a3a55;
            transition: color .15s, background .15s;
        }

        .tab-close:hover { color: #ef4444; background: rgba(239,68,68,0.08); }

        /* Main content area */
        .right-content {
            flex: 1;
            background: #0f172a;
            display: flex;
            min-height: 0;
        }

        /* ── Left sub-column (info) ── */
        .sub-left {
            width: 220px;
            flex-shrink: 0;
            padding: 18px 16px;
            border-right: 1px solid #1e2d42;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .icon-canvas-wrap {
            display: flex;
            justify-content: center;
        }

        #icon-img {
            image-rendering: pixelated;
            width: 80px;
            height: 80px;
            object-fit: contain;
            background: rgba(255,255,255,0.02);
            border-radius: 4px;
            border: 1px solid #2a3a55;
        }

        .sub-bldg-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #e2e8f0;
            text-align: center;
        }

        .level-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #94a3b8;
        }

        .level-arrow .arrow {
            color: #fbbf24;
            font-size: 1rem;
        }

        .level-num { color: #e2e8f0; }

        .new-bonuses-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
            margin-top: 4px;
        }

        /* In-queue state sub-left */
        .queue-label {
            font-size: 0.78rem;
            color: #fbbf24;
            font-weight: 600;
            text-align: center;
        }

        .queue-cd-wrap {
            text-align: center;
        }

        #countdown {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fbbf24;
            font-variant-numeric: tabular-nums;
        }

        /* ── Right sub-column (requirements) ── */
        .sub-right {
            flex: 1;
            min-width: 0;
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .reqs-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reqs-title::before {
            content: '≡';
            font-size: 1rem;
            color: #475569;
        }

        /* Resource rows */
        .res-req-row {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 5px;
            border: 1px solid #2a3a55;
            font-size: 0.8rem;
            background: #111827;
        }

        .res-req-row.ok  { border-color: #166534; background: rgba(34,197,94,0.06); }
        .res-req-row.bad { border-color: #7f1d1d; background: rgba(239,68,68,0.06); }

        .res-check { font-size: 0.85rem; width: 16px; text-align: center; flex-shrink: 0; }
        .res-emoji { font-size: 1rem; flex-shrink: 0; }
        .res-name  { color: #94a3b8; flex: 1; }

        .res-amounts {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-size: 0.78rem;
        }

        .res-req-row.ok  .res-amounts { color: #22c55e; }
        .res-req-row.bad .res-amounts { color: #ef4444; }

        /* Castle prereq cards */
        .prereq-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            margin-top: 4px;
        }

        .prereq-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 6px;
            border-radius: 5px;
            border: 1px solid #2a3a55;
            background: #111827;
            text-decoration: none;
            transition: border-color .15s, background .15s;
            cursor: pointer;
        }

        .prereq-card:hover { border-color: #3d5278; background: #162032; }
        .prereq-card.met   { border-color: #166534; }
        .prereq-card.unmet { border-color: #7f1d1d; }

        .prereq-canvas-wrap img {
            image-rendering: pixelated;
            width: 36px;
            height: 36px;
            object-fit: contain;
            display: block;
        }

        .prereq-card-name {
            font-size: 0.65rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.2;
        }

        .prereq-lv-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 3px;
        }

        .prereq-lv-badge.met   { background: #166534; color: #86efac; }
        .prereq-lv-badge.unmet { background: #7f1d1d; color: #fca5a5; }

        /* ────────────────────────────────────────
           BOTTOM ACTION BAR
        ──────────────────────────────────────── */
        .action-bar {
            height: 68px;
            border-top: 1px solid #2a3a55;
            display: flex;
            background: #0c1220;
            overflow: hidden;
        }

        .action-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            border: none;
            cursor: pointer;
            padding: 0 12px;
            transition: opacity .15s, filter .15s;
            font-family: inherit;
        }

        .action-btn:hover:not(:disabled) { filter: brightness(1.12); }
        .action-btn:disabled { opacity: 0.4; cursor: not-allowed; filter: grayscale(0.5); }

        .action-btn + .action-btn {
            border-left: 1px solid #2a3a55;
        }

        .action-btn-top {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.75);
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .action-btn-label {
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: .06em;
            color: #fff;
            white-space: nowrap;
        }

        /* Instant button — orange */
        .btn-instant {
            background: linear-gradient(180deg, #f97316 0%, #c2410c 100%);
        }

        /* Upgrade button — blue */
        .btn-upgrade-action {
            background: linear-gradient(180deg, #2563eb 0%, #1e3a8a 100%);
        }

        /* ────────────────────────────────────────
           CARAVAN SECTION (below modal)
        ──────────────────────────────────────── */
        .caravan-section {
            width: 100%;
            max-width: min(960px, calc(100vw - 16px));
            margin-top: 16px;
            background: #111827;
            border: 1px solid #2a3a55;
            border-radius: 10px;
            overflow: hidden;
        }

        .caravan-header {
            background: #0c1220;
            border-bottom: 1px solid #2a3a55;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
        }

        .caravan-header-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
        }

        .caravan-refresh-cd {
            font-size: 0.72rem;
            color: #fbbf24;
            font-variant-numeric: tabular-nums;
        }

        .caravan-inner {
            padding: 12px 16px 16px;
        }

        .caravan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
        }

        .caravan-slot {
            background: #0f172a;
            border: 1px solid #2a3a55;
            border-radius: 7px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: border-color .15s, opacity .2s;
        }

        .caravan-slot.bought {
            opacity: 0.45;
            border-color: #1e2d42;
        }

        .caravan-slot-top {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .caravan-discount {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 3px;
            background: #16a34a;
            color: #fff;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .caravan-slot-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #e2e8f0;
            line-height: 1.2;
        }

        .caravan-slot-price {
            font-size: 0.76rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .caravan-slot-price strong {
            color: #e2e8f0;
            font-variant-numeric: tabular-nums;
        }

        .btn-caravan-buy {
            margin-top: auto;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: linear-gradient(180deg, #0ea5e9, #0369a1);
            color: #fff;
            transition: opacity .15s;
            white-space: nowrap;
        }

        .btn-caravan-buy:hover:not(:disabled) { opacity: 0.85; }
        .btn-caravan-buy:disabled {
            background: #1e293b;
            color: #475569;
            cursor: not-allowed;
        }

        .caravan-loading {
            font-size: 0.8rem;
            color: #475569;
            padding: 12px 0;
            text-align: center;
        }

        /* ────────────────────────────────────────
           BARRACK SECTION (below modal)
        ──────────────────────────────────────── */
        .barrack-section {
            width: 100%;
            max-width: min(960px, calc(100vw - 16px));
            margin-top: 16px;
            background: #111827;
            border: 1px solid #2a3a55;
            border-radius: 10px;
            overflow: hidden;
        }

        .barrack-section-header {
            background: #0c1220;
            border-bottom: 1px solid #2a3a55;
            padding: 10px 16px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
        }

        .barrack-inner {
            padding: 12px 16px 16px;
        }

        .troop-row {
            background: #0f172a;
            border: 1px solid #2a3a55;
            border-radius: 7px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }

        .troop-row.locked { opacity: 0.45; }

        .troop-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .troop-badge {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 3px;
            background: #0ea5e9;
            color: #fff;
        }

        .troop-badge.cav { background: #8b5cf6; }
        .troop-badge.rgd { background: #22c55e; }

        .troop-name  { font-weight: 600; font-size: 0.9rem; flex: 1; }
        .troop-count { font-size: 0.78rem; color: #64748b; }

        .troop-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .troop-stats span { color: #e2e8f0; font-weight: 600; }

        .troop-cost {
            display: flex;
            gap: 8px;
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .troop-cost span { color: #e2e8f0; }

        .train-row {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .train-input {
            width: 76px;
            padding: 4px 8px;
            border-radius: 5px;
            border: 1px solid #2a3a55;
            background: #1e293b;
            color: #e2e8f0;
            font-size: 0.83rem;
        }

        .btn-train {
            flex: 1;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: linear-gradient(180deg, #0ea5e9, #0369a1);
            color: #fff;
            transition: opacity .15s;
        }

        .btn-train:hover:not(:disabled) { opacity: 0.85; }
        .btn-train:disabled { background: #1e293b; color: #475569; cursor: not-allowed; }

        .lock-msg {
            font-size: 0.73rem;
            color: #475569;
            font-style: italic;
        }

        .troop-queue-list { margin-top: 14px; }

        .troop-queue-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
            margin-bottom: 6px;
        }

        .queue-item {
            background: rgba(251,191,36,0.06);
            border: 1px solid #d4a017;
            border-radius: 5px;
            padding: 7px 10px;
            margin-bottom: 5px;
            font-size: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .queue-item-cd { color: #fbbf24; font-weight: 600; font-variant-numeric: tabular-nums; }

        /* ────────────────────────────────────────
           BARRACK — LoK-Style Troop UI
        ──────────────────────────────────────── */

        /* Type sub-tabs (INFANTERIE | FERNKAMPF | KAVALLERIE) */
        .brk-type-tabs {
            display: flex;
            background: #0a1220;
            border-bottom: 1px solid #2a3a55;
        }

        .brk-type-tab {
            flex: 1;
            padding: 9px 4px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #475569;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
            margin-bottom: -1px;
        }

        .brk-type-tab:hover     { color: #94a3b8; }
        .brk-type-tab.active    { color: #f0d080; border-bottom-color: #d4a017; }

        /* Tier card row */
        .brk-tier-row {
            display: flex;
            gap: 6px;
            padding: 10px 12px;
            overflow-x: auto;
            background: #0d1525;
            border-bottom: 1px solid #2a3a55;
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }

        .brk-tier-card {
            flex: 0 0 72px;
            height: 88px;
            border-radius: 8px;
            border: 2px solid #2a3a55;
            background: #111827;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 6px 4px 5px;
            gap: 4px;
            position: relative;
            transition: border-color 0.15s, box-shadow 0.15s;
            flex-shrink: 0;
        }

        .brk-tier-card:hover:not(.locked) {
            border-color: #475569;
        }

        .brk-tier-card.selected {
            border-color: var(--brk-color, #0ea5e9);
            box-shadow: 0 0 10px var(--brk-color, #0ea5e9);
        }

        .brk-tier-card.locked {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .brk-tier-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            font-size: 0.6rem;
            font-weight: 900;
            padding: 1px 5px;
            border-radius: 3px;
            color: #fff;
            line-height: 1.5;
        }

        .brk-tier-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-top: 4px;
        }

        .brk-tier-count {
            font-size: 0.68rem;
            font-weight: 700;
            color: #e2e8f0;
            font-variant-numeric: tabular-nums;
        }

        /* Detail panel */
        .brk-detail {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .brk-detail-name {
            padding: 8px 14px;
            font-size: 0.92rem;
            font-weight: 800;
            color: #f0d080;
            background: linear-gradient(90deg, #1a1000, #0d1525);
            border-bottom: 1px solid #2a3a55;
            letter-spacing: 0.03em;
        }

        .brk-stats-list {
            flex: 1;
            overflow-y: auto;
            padding: 6px 0;
        }

        .brk-stat-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.8rem;
        }

        .brk-stat-icon { flex: 0 0 18px; text-align: center; font-size: 0.9rem; }
        .brk-stat-label { flex: 1; color: #64748b; }
        .brk-stat-val { font-weight: 700; color: #e2e8f0; font-variant-numeric: tabular-nums; }

        /* Train section */
        .brk-train-section {
            display: flex;
            gap: 8px;
            padding: 10px 14px;
            border-top: 1px solid #2a3a55;
            background: #0a1220;
            flex-shrink: 0;
        }

        .brk-train-input {
            width: 80px;
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid #2a3a55;
            background: #111827;
            color: #e2e8f0;
            font-size: 0.88rem;
            font-weight: 700;
            text-align: center;
        }

        .brk-train-btn {
            flex: 1;
            padding: 8px;
            border-radius: 6px;
            border: none;
            background: linear-gradient(180deg, #3b82f6, #1d4ed8);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            cursor: pointer;
            transition: opacity 0.15s;
        }

        .brk-train-btn:hover:not(:disabled) { opacity: 0.88; }
        .brk-train-btn:disabled { background: #1e293b; color: #475569; cursor: not-allowed; }

        /* Queue bar */
        .brk-queue-bar {
            background: rgba(251,191,36,0.05);
            border-top: 1px solid #2a3a55;
            padding: 8px 14px;
            flex-shrink: 0;
        }

        .brk-queue-title {
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #d4a017;
            margin-bottom: 6px;
        }

        .brk-queue-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            background: rgba(251,191,36,0.06);
            border: 1px solid rgba(212,160,23,0.3);
            border-radius: 5px;
            margin-bottom: 4px;
            font-size: 0.78rem;
        }

        .brk-queue-icon { font-size: 1rem; flex-shrink: 0; }
        .brk-queue-info { flex: 1; color: #e2e8f0; }
        .brk-queue-cd   { color: #fbbf24; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }

        /* #tab-truppen must fill the right panel height */
        #tab-truppen {
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        /* ────────────────────────────────────────
           TOAST
        ──────────────────────────────────────── */
        #bm-toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 0.85rem;
            display: none;
            z-index: 9000;
            white-space: nowrap;
        }

        #bm-toast.ok  { border-color: #22c55e; color: #22c55e; }
        #bm-toast.err { border-color: #ef4444; color: #ef4444; }

        /* ────────────────────────────────────────
           RESPONSIVE — stack panels on narrow
        ──────────────────────────────────────── */
        @media (max-width: 600px) {
            .modal-body { flex-direction: column; }

            .left-panel {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #2a3a55;
            }

            .bldg-canvas-wrap { padding: 14px 0 8px; }

            .right-content { flex-direction: column; }

            .sub-left {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #1e2d42;
            }

            .action-btn-label { font-size: 0.76rem; }
        }
    </style>
<?php if (!$isModal): ?>
</head>
<body>
<?php $hudCurrentView = 'city'; require __DIR__ . '/partials/hud.php'; ?>
<div class="page-wrap">
<?php endif ?>
    <div class="modal-card<?= $isModal ? ' is-overlay' : '' ?>">

        <!-- ── Modal body: two panels ── -->
        <div class="modal-body">

            <!-- ════════════════════════════════
                 LEFT PANEL
            ════════════════════════════════ -->
            <div class="left-panel">

                <!-- Red name banner -->
                <div class="bldg-banner"><?= htmlspecialchars($name) ?></div>

                <!-- Large sprite -->
                <div class="bldg-canvas-wrap">
                    <img id="bldg-img" src="<?= htmlspecialchars($spriteSrc) ?>" alt="<?= htmlspecialchars($name) ?>">
                </div>

                <!-- Level badge -->
                <div class="level-badge-wrap">
                    <div class="level-badge">◆ Lv.<?= $currentLevel ?> ◆</div>
                </div>

                <!-- Description -->
                <p class="bldg-desc"><?= htmlspecialchars($desc) ?></p>

                <hr class="left-divider">

                <!-- Current bonuses -->
                <div class="bonus-section">
                    <div class="bonus-section-title">Aktuelle Boni</div>
                    <?php if ($currentLevel > 0 && !empty($currentBonuses)): ?>
                        <?php foreach ($currentBonuses as [$label, $val]): ?>
                        <div class="bonus-row">
                            <span class="bonus-label"><?= htmlspecialchars($label) ?></span>
                            <span class="bonus-val"><?= htmlspecialchars($val) ?></span>
                        </div>
                        <?php endforeach ?>
                    <?php else: ?>
                        <div class="no-bonus">Kein aktiver Bonus (Lv.0)</div>
                    <?php endif ?>
                </div>

                <!-- Back link -->
                <div class="left-back">
                    <?php if ($isModal): ?>
                    <button class="btn-back" onclick="window.closeBldgModal?.()" style="background:none;border:none;cursor:pointer;color:#e2e8f0">← Zurück</button>
                    <?php else: ?>
                    <a href="/city" class="btn-back">← Zurück</a>
                    <?php endif ?>
                </div>

            </div><!-- /left-panel -->

            <!-- ════════════════════════════════
                 RIGHT PANEL
            ════════════════════════════════ -->
            <div class="right-panel">

                <!-- Tab bar -->
                <div class="tab-bar">
                    <?php foreach ($tabs as $tabId => $tabLabel): ?>
                    <button class="tab-item<?= $activeTab === $tabId ? ' tab-active' : '' ?>"
                            data-tab="<?= htmlspecialchars($tabId) ?>">
                        <?php if ($tabId === 'upgrade' && $queueEntry !== null): ?>UPGRADE LÄUFT<?php else: ?><?= htmlspecialchars(strtoupper($tabLabel)) ?><?php endif ?>
                    </button>
                    <?php endforeach ?>
                    <div class="tab-spacer"></div>
                    <?php if ($isModal): ?>
                    <button class="tab-close" onclick="window.closeBldgModal?.()" title="Schließen" style="background:none;border:none;cursor:pointer">✕</button>
                    <?php else: ?>
                    <a href="/city" class="tab-close" title="Schließen">✕</a>
                    <?php endif ?>
                </div>

                <!-- ── Tab pane: upgrade ── -->
                <div id="tab-upgrade" class="tab-pane"<?= $activeTab !== 'upgrade' ? ' style="display:none"' : '' ?>>
                <!-- Content: two sub-columns -->
                <div class="right-content">

                    <!-- Left sub-column: icon + level transition + new bonuses -->
                    <div class="sub-left">
                        <div class="icon-canvas-wrap">
                            <img id="icon-img" src="<?= htmlspecialchars($spriteSrc) ?>" alt="<?= htmlspecialchars($name) ?>">
                        </div>

                        <div class="sub-bldg-name"><?= htmlspecialchars($name) ?></div>

                        <div class="level-arrow">
                            <span class="level-num">Lv.<?= $currentLevel ?></span>
                            <span class="arrow">→</span>
                            <span class="level-num">Lv.<?= $nextLevel ?></span>
                        </div>

                        <?php if ($queueEntry !== null): ?>
                            <div class="queue-label">In Bau...</div>
                            <div class="queue-cd-wrap">
                                <div id="countdown"
                                     data-finish="<?= strtotime($queueEntry['finishes_at']) ?>">—</div>
                            </div>
                        <?php else: ?>
                            <div class="new-bonuses-title">Neue Boni:</div>
                            <?php foreach ($nextBonuses as [$label, $val]): ?>
                            <div class="bonus-row">
                                <span class="bonus-label" style="font-size:.74rem"><?= htmlspecialchars($label) ?></span>
                                <span class="bonus-val" style="font-size:.74rem"><?= htmlspecialchars($val) ?></span>
                            </div>
                            <?php endforeach ?>
                        <?php endif ?>
                    </div>

                    <!-- Right sub-column: requirements -->
                    <div class="sub-right">
                        <div class="reqs-title">Bauvoraussetzung</div>

                        <?php foreach ($resRows as $r):
                            $ok = ($r['have'] >= $r['need']);
                            $cls = $ok ? 'ok' : 'bad';
                            $check = $ok ? '✓' : '✗';
                        ?>
                        <div class="res-req-row <?= $cls ?>">
                            <span class="res-check" style="color:<?= $ok ? '#22c55e' : '#ef4444' ?>"><?= $check ?></span>
                            <span class="res-emoji"><?= $r['emoji'] ?></span>
                            <span class="res-name"><?= $r['name'] ?></span>
                            <span class="res-amounts">
                                <?= fmtK($r['have']) ?> / <?= fmtK($r['need']) ?>
                            </span>
                        </div>
                        <?php endforeach ?>

                        <?php if (!empty($castleReqs)): ?>
                        <div class="new-bonuses-title" style="margin-top:6px">Gebäude-Voraussetzungen</div>
                        <div class="prereq-cards">
                            <?php foreach ($castleReqs as $reqCode => $reqLevel):
                                $hasLevel = (int)($buildings[$reqCode]['level'] ?? 0);
                                $isMetCard = $hasLevel >= $reqLevel;
                                $cardCls   = $isMetCard ? 'met' : 'unmet';
                                $reqName   = CityState::BUILDING_NAMES[$reqCode] ?? ucwords(str_replace('_', ' ', $reqCode));
                                $reqSprite = $buildingSpriteSrc($reqCode);
                            ?>
                            <?php if ($isModal): ?>
                            <button class="prereq-card <?= $cardCls ?>"
                                    onclick="window.openBuildingModal?.('<?= htmlspecialchars($reqCode) ?>')"
                                    style="background:none;border:none;cursor:pointer;text-align:center">
                            <?php else: ?>
                            <a href="/city/building/<?= htmlspecialchars($reqCode) ?>"
                               class="prereq-card <?= $cardCls ?>">
                            <?php endif ?>
                                <div class="prereq-canvas-wrap">
                                    <img src="<?= htmlspecialchars($reqSprite) ?>"
                                         alt="<?= htmlspecialchars($reqName) ?>">
                                </div>
                                <div class="prereq-card-name"><?= htmlspecialchars($reqName) ?></div>
                                <div class="prereq-lv-badge <?= $cardCls ?>">
                                    <?= $isMetCard ? '✓' : '✗' ?> Lv.<?= $reqLevel ?>
                                </div>
                            <?php if ($isModal): ?>
                            </button>
                            <?php else: ?>
                            </a>
                            <?php endif ?>
                            <?php endforeach ?>
                        </div>
                        <?php endif ?>

                    </div><!-- /sub-right -->

                </div><!-- /right-content -->

                <!-- ── Bottom action bar ── -->
                <div class="action-bar">
                    <button
                        id="btn-instant"
                        class="action-btn btn-instant"
                        <?= $queueEntry === null ? 'disabled' : '' ?>
                        data-queue-id="<?= $queueEntry !== null ? (int)$queueEntry['id'] : '' ?>"
                        data-gem-cost="<?= $instantGemCost ?>"
                    >
                        <span class="action-btn-top">
                            💎 <?= $queueEntry !== null ? number_format($instantGemCost) : '—' ?> Gems
                        </span>
                        <span class="action-btn-label">SOFORT UPGRADEN</span>
                    </button>

                    <?php
                    // Upgrade button: disabled if in queue, can't afford, or reqs not met
                    $upgradeDisabled = ($queueEntry !== null) || !$canAfford || !$reqsMet;
                    $upgradeLabel = 'UPGRADE STARTEN';
                    $upgradeTopLine = '⏱ ' . fmtTime($buildSec);
                    if ($queueEntry !== null) {
                        $upgradeLabel   = 'FERTIG IN';
                        $upgradeTopLine = '⏱ ' . fmtTime($secsLeft);
                    } elseif (!$canAfford) {
                        $upgradeLabel   = 'ZU WENIG RESSOURCEN';
                        $upgradeTopLine = '⏱ ' . fmtTime($buildSec);
                    } elseif (!$reqsMet) {
                        $upgradeLabel   = 'VORAUSSETZUNGEN FEHLEN';
                        $upgradeTopLine = '⏱ ' . fmtTime($buildSec);
                    }
                    ?>
                    <button
                        id="btn-upgrade"
                        class="action-btn btn-upgrade-action"
                        <?= $upgradeDisabled ? 'disabled' : '' ?>
                        data-code="<?= htmlspecialchars($buildingCode) ?>"
                    >
                        <span class="action-btn-top"><?= htmlspecialchars($upgradeTopLine) ?></span>
                        <span class="action-btn-label"><?= htmlspecialchars($upgradeLabel) ?></span>
                    </button>
                </div><!-- /action-bar -->
                </div><!-- /tab-upgrade -->

                <!-- ── Tab pane: truppen (barrack only) ── -->
                <?php if ($buildingCode === 'barrack'):
                    $academyLevel = (int) ($buildings['academy']['level'] ?? 1);
                    $barrackLevel = (int) ($buildings['barrack']['level'] ?? 1);
                    $allTroops    = TroopData::all();
                    $troopsData   = array_values(array_map(function($t) use ($barrackLevel, $academyLevel, $troops) {
                        return [
                            'code'          => (int)$t['code'],
                            'name'          => $t['name'],
                            'type'          => (int)$t['type'],
                            'tier'          => (int)$t['tier'],
                            'hp'            => (int)$t['hp'],
                            'attack'        => (int)$t['attack'],
                            'defense'       => (int)$t['defense'],
                            'speed'         => (int)$t['speed'],
                            'need_food'     => (int)$t['need_food'],
                            'need_lumber'   => (int)$t['need_lumber'],
                            'need_stone'    => (int)$t['need_stone'],
                            'need_gold'     => (int)$t['need_gold'],
                            'time'          => (int)$t['time'],
                            'unlock_academy'=> (int)$t['unlock_academy'],
                            'unlocked'      => TroopData::isUnlocked((int)$t['code'], $barrackLevel, $academyLevel),
                            'in_city'       => (int)($troops[(int)$t['code']] ?? 0),
                        ];
                    }, $allTroops));
                    $queueData = array_map(function($qe) {
                        $qt = TroopData::get((int)$qe['troop_code']);
                        return [
                            'slot'        => (int)$qe['barrack_slot'],
                            'name'        => $qt['name'] ?? 'Einheit',
                            'count'       => (int)$qe['count'],
                            'finishes_at' => strtotime($qe['finishes_at']),
                        ];
                    }, $troopQueue);
                ?>
                <div id="tab-truppen" class="tab-pane"<?= $activeTab !== 'truppen' ? ' style="display:none"' : '' ?>>
                    <!-- Type sub-tabs -->
                    <div class="brk-type-tabs">
                        <button class="brk-type-tab active" data-btype="1">INFANTERIE</button>
                        <button class="brk-type-tab" data-btype="2">FERNKAMPF</button>
                        <button class="brk-type-tab" data-btype="3">KAVALLERIE</button>
                    </div>
                    <!-- Tier card row (filled by JS) -->
                    <div class="brk-tier-row" id="brk-tier-row"></div>
                    <!-- Detail + train panel -->
                    <div class="brk-detail" id="brk-detail" style="display:none">
                        <div class="brk-detail-name" id="brk-detail-name"></div>
                        <div class="brk-stats-list" id="brk-stats-list"></div>
                        <div class="brk-train-section">
                            <input type="number" id="brk-count" class="brk-train-input" value="100" min="1" max="9999">
                            <button id="brk-train-btn" class="brk-train-btn">AUSBILDEN</button>
                        </div>
                    </div>
                    <!-- Queue bar (filled by JS) -->
                    <div class="brk-queue-bar" id="brk-queue-bar" style="display:none"></div>
                </div><!-- /tab-truppen -->
                <?php endif ?>

                <!-- ── Tab pane: caravan (trading_post only) ── -->
                <?php if ($buildingCode === 'trading_post'): ?>
                <div id="tab-caravan" class="tab-pane tab-scroll"<?= $activeTab !== 'caravan' ? ' style="display:none"' : '' ?>
                     x-data="caravanApp()" x-init="boot()">
                    <div style="padding:10px 16px;border-bottom:1px solid #2a3a55;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b">Caravan — Rotierender Markt</div>
                        <div style="font-size:.72rem;color:#fbbf24;font-variant-numeric:tabular-nums">
                            Nächster Refresh: <span x-text="refreshLabel">…</span>
                        </div>
                    </div>
                    <div style="padding:12px 16px 16px">
                        <template x-if="loading">
                            <div class="caravan-loading">Lade Caravan…</div>
                        </template>
                        <template x-if="!loading && error">
                            <div class="caravan-loading" style="color:#ef4444" x-text="error"></div>
                        </template>
                        <template x-if="!loading && !error">
                            <div class="caravan-grid">
                                <template x-for="(slot, idx) in slots" :key="slot.idx">
                                    <div class="caravan-slot" :class="{ bought: slot.bought }">
                                        <div class="caravan-slot-top">
                                            <span class="caravan-discount" x-text="slot.discount + '%'"></span>
                                            <span class="caravan-slot-label" x-text="slot.label"></span>
                                        </div>
                                        <div class="caravan-slot-price">
                                            <span x-text="currencyIcon(slot.currency)"></span>
                                            <strong x-text="fmt(slot.price)"></strong>
                                            <span x-text="currencyLabel(slot.currency)"></span>
                                        </div>
                                        <button class="btn-caravan-buy"
                                                :disabled="slot.bought || buying === slot.idx"
                                                @click="buy(slot.idx)">
                                            <span x-text="slot.bought ? 'Gekauft' : (buying === slot.idx ? '…' : 'Kaufen')"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div><!-- /tab-caravan -->
                <?php endif ?>

                <!-- ── Tab pane: allianz (hall_of_alliance only) ── -->
                <?php if ($buildingCode === 'hall_of_alliance'):
                    $__db          = \Conquer\Db\Connection::getInstance();
                    $__playerId    = (int) $session['player_id'];
                    $__member      = $__db->query(
                        'SELECT am.role, a.name, a.tag
                         FROM   alliance_members am
                         JOIN   alliances a ON a.id = am.alliance_id
                         WHERE  am.player_id = ?',
                        [$__playerId],
                    )->fetch() ?: null;
                ?>
                <div id="tab-allianz" class="tab-pane tab-scroll"<?= $activeTab !== 'allianz' ? ' style="display:none"' : '' ?>>
                    <div style="padding:20px 16px">
                    <?php if ($__member === null): ?>
                        <div style="text-align:center;padding:20px 0">
                            <p style="font-size:.85rem;color:#94a3b8;margin-bottom:14px">
                                Du bist derzeit in keiner Allianz. Tritt einer Allianz bei oder gründe deine eigene,
                                um von gemeinsamen Buffs und koordiniertem Spiel zu profitieren.
                            </p>
                            <a href="/alliance"
                               style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;
                                      border-radius:6px;background:linear-gradient(180deg,#2563eb,#1e3a8a);
                                      color:#fff;font-size:.82rem;font-weight:700;text-decoration:none">
                                ⚔ Allianz beitreten oder gründen
                            </a>
                        </div>
                    <?php else:
                        $__roleLabelMap = [
                            'leader'      => 'Leader',
                            'vice_leader' => 'Vize-Leader',
                            'officer'     => 'Offizier',
                            'veteran'     => 'Veteran',
                            'member'      => 'Mitglied',
                        ];
                        $__roleLabel = $__roleLabelMap[$__member['role']] ?? $__member['role'];
                    ?>
                        <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
                            <div style="flex-shrink:0;width:52px;height:52px;border-radius:7px;background:#0f172a;
                                        border:2px solid #fbbf24;display:flex;align-items:center;justify-content:center;
                                        font-size:.78rem;font-weight:900;color:#fbbf24;letter-spacing:.04em">
                                [<?= htmlspecialchars((string)$__member['tag']) ?>]
                            </div>
                            <div>
                                <div style="font-size:1.05rem;font-weight:800;color:#e2e8f0">
                                    <?= htmlspecialchars((string)$__member['name']) ?>
                                </div>
                                <div style="font-size:.75rem;color:#64748b;margin-top:2px">
                                    Deine Rolle: <strong style="color:#fbbf24"><?= htmlspecialchars($__roleLabel) ?></strong>
                                </div>
                            </div>
                        </div>
                        <a href="/alliance"
                           style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;
                                  border-radius:6px;background:#1e293b;border:1px solid #334155;
                                  color:#e2e8f0;font-size:.8rem;font-weight:600;text-decoration:none;
                                  transition:background .15s"
                           onmouseover="this.style.background='#293548'"
                           onmouseout="this.style.background='#1e293b'">
                            ⚔ Zur Allianz-Übersicht
                        </a>
                    <?php endif ?>
                    </div>
                </div><!-- /tab-allianz -->
                <?php endif ?>

                <!-- ── Tab pane: forschung (academy only) ── -->
                <?php if ($buildingCode === 'academy'): ?>
                <div id="tab-forschung" class="tab-pane"<?= $activeTab !== 'forschung' ? ' style="display:none"' : '' ?>>
                    <iframe id="research-iframe"
                            src="<?= $activeTab === 'forschung' ? '/research?embed=1' : '' ?>"
                            style="border:none;width:100%;flex:1;min-height:0;display:block"
                            data-src="/research?embed=1">
                    </iframe>
                </div><!-- /tab-forschung -->
                <?php endif ?>

                <!-- ── Tab pane: heilen (hospital only) ── -->
                <?php if ($buildingCode === 'hospital'): ?>
                <div id="tab-heilen" class="tab-pane tab-scroll"<?= $activeTab !== 'heilen' ? ' style="display:none"' : '' ?>>
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:40px;text-align:center;gap:12px">
                        <div style="font-size:3rem">🏥</div>
                        <div style="font-size:.95rem;font-weight:700;color:#e2e8f0">Heilung — Demnächst</div>
                        <div style="font-size:.8rem;color:#64748b;max-width:300px;line-height:1.6">
                            Verwundete Truppen werden automatisch geheilt. Das Heilungs-System wird in einem kommenden Sprint implementiert.
                        </div>
                    </div>
                </div><!-- /tab-heilen -->
                <?php endif ?>

            </div><!-- /right-panel -->

        </div><!-- /modal-body -->

    </div><!-- /modal-card -->




<?php if (!$isModal): ?>
</div><!-- /page-wrap -->
<?php endif ?>

<div id="bm-toast"></div>

<script>
(function () {
'use strict';

// ---------------------------------------------------------------------------
// Upgrade button
// ---------------------------------------------------------------------------
const btnUpgrade = document.getElementById('btn-upgrade');
const CSRF       = <?= json_encode($session['csrf_token']) ?>;

if (btnUpgrade) {
    btnUpgrade.addEventListener('click', async () => {
        btnUpgrade.disabled = true;
        const lbl = btnUpgrade.querySelector('.action-btn-label');
        if (lbl) lbl.textContent = 'WIRD GESTARTET…';

        try {
            const res  = await fetch('/api/city/upgrade-building', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF,
                },
                body: JSON.stringify({ building_code: btnUpgrade.dataset.code }),
            });
            const json = await res.json();

            if (json.ok) {
                showToast('Upgrade gestartet!', 'ok');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                btnUpgrade.disabled = false;
                if (lbl) lbl.textContent = 'UPGRADE STARTEN';
            }
        } catch (e) {
            showToast('Netzwerkfehler', 'err');
            btnUpgrade.disabled = false;
            if (lbl) lbl.textContent = 'UPGRADE STARTEN';
        }
    });
}

// ---------------------------------------------------------------------------
// Queue countdown (build)
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
        const lbl = btnInstant.querySelector('.action-btn-label');
        if (lbl) lbl.textContent = '…';

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
                if (lbl) lbl.textContent = 'SOFORT UPGRADEN';
            }
        } catch (e) {
            showToast('Netzwerkfehler', 'err');
            btnInstant.disabled = false;
            if (lbl) lbl.textContent = 'SOFORT UPGRADEN';
        }
    });
}

// ---------------------------------------------------------------------------
// Barrack LoK-style UI
// ---------------------------------------------------------------------------
<?php if ($buildingCode === 'barrack'): ?>
(function () {
    const BRK_TROOPS = <?= json_encode($troopsData) ?>;
    const BRK_QUEUE  = <?= json_encode($queueData) ?>;
    const ROMAN      = ['', 'I', 'II', 'III', 'IV', 'V'];
    const TYPE_COLOR = { 1: '#0ea5e9', 2: '#22c55e', 3: '#8b5cf6' };
    const STAT_ICONS = { hp: '❤️', attack: '⚔️', defense: '🛡️', speed: '⚡' };
    const STAT_LABELS= { hp: 'HP', attack: 'Angriff', defense: 'Verteidigung', speed: 'Geschw.' };
    const RES_ICONS  = { need_food: '🌾', need_lumber: '🪵', need_stone: '🪨', need_gold: '💰' };
    const RES_LABELS = { need_food: 'Nahrung', need_lumber: 'Holz', need_stone: 'Stein', need_gold: 'Gold' };

    let brkCurrentType = 1;
    let brkSelectedCode = null;

    function fmtNum(n) {
        return Number(n).toLocaleString('de-DE');
    }
    function fmtTimeSec(s) {
        if (s < 60)  return s + 's';
        if (s < 3600) return Math.floor(s/60) + 'm ' + (s%60) + 's';
        return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm';
    }

    function brkRenderCards() {
        const row = document.getElementById('brk-tier-row');
        if (!row) return;
        const filtered = BRK_TROOPS.filter(t => t.type === brkCurrentType);
        row.innerHTML = '';
        filtered.forEach(t => {
            const color  = TYPE_COLOR[t.type] || '#0ea5e9';
            const roman  = ROMAN[t.tier] || t.tier;
            const locked = !t.unlocked;
            const sel    = t.code === brkSelectedCode;

            const card = document.createElement('div');
            card.className = 'brk-tier-card' + (locked ? ' locked' : '') + (sel ? ' selected' : '');
            card.style.setProperty('--brk-color', color);
            card.innerHTML =
                '<div class="brk-tier-badge" style="background:' + color + '">' + roman + '</div>' +
                '<div class="brk-tier-icon">' + (locked ? '🔒' : '⚔') + '</div>' +
                '<div class="brk-tier-count">' + fmtNum(t.in_city) + '</div>';

            if (!locked) {
                card.addEventListener('click', () => {
                    brkSelectedCode = t.code;
                    brkRenderCards();
                    brkShowDetail(t);
                });
            }
            row.appendChild(card);
        });

        // If nothing selected yet, auto-select first unlocked
        if (brkSelectedCode === null) {
            const first = filtered.find(t => t.unlocked);
            if (first) {
                brkSelectedCode = first.code;
                brkRenderCards();
                brkShowDetail(first);
                return;
            }
        }
        // Re-show detail for currently selected
        const sel = filtered.find(t => t.code === brkSelectedCode);
        if (sel) brkShowDetail(sel);
    }

    function brkShowDetail(t) {
        const detail = document.getElementById('brk-detail');
        const nameEl = document.getElementById('brk-detail-name');
        const statsEl= document.getElementById('brk-stats-list');
        if (!detail || !nameEl || !statsEl) return;

        detail.style.display = '';
        nameEl.textContent = t.name + ' (Tier ' + (ROMAN[t.tier] || t.tier) + ')';

        let html = '';
        ['hp','attack','defense','speed'].forEach(k => {
            html += '<div class="brk-stat-row">' +
                '<span class="brk-stat-icon">' + (STAT_ICONS[k]||'') + '</span>' +
                '<span class="brk-stat-label">' + (STAT_LABELS[k]||k) + '</span>' +
                '<span class="brk-stat-val">' + fmtNum(t[k]) + '</span>' +
                '</div>';
        });
        // Cost rows
        html += '<div class="brk-stat-row" style="margin-top:6px;border-top:1px solid #1e2d42;padding-top:6px">' +
            '<span class="brk-stat-icon">⏱</span>' +
            '<span class="brk-stat-label">Zeit/Einheit</span>' +
            '<span class="brk-stat-val">' + fmtTimeSec(t.time) + '</span>' +
            '</div>';
        ['need_food','need_lumber','need_stone','need_gold'].forEach(k => {
            if (!t[k]) return;
            html += '<div class="brk-stat-row">' +
                '<span class="brk-stat-icon">' + (RES_ICONS[k]||'') + '</span>' +
                '<span class="brk-stat-label">' + (RES_LABELS[k]||k) + '</span>' +
                '<span class="brk-stat-val">' + fmtNum(t[k]) + '</span>' +
                '</div>';
        });
        // In city count
        html += '<div class="brk-stat-row" style="border-top:1px solid #1e2d42;padding-top:6px;margin-top:6px">' +
            '<span class="brk-stat-icon">🏰</span>' +
            '<span class="brk-stat-label">In Stadt</span>' +
            '<span class="brk-stat-val">' + fmtNum(t.in_city) + '</span>' +
            '</div>';
        statsEl.innerHTML = html;

        // Update train button
        const trainBtn = document.getElementById('brk-train-btn');
        if (trainBtn) {
            trainBtn.dataset.code = t.code;
            trainBtn.dataset.name = t.name;
            if (!t.unlocked) {
                trainBtn.disabled = true;
                trainBtn.textContent = '🔒 Academy Lv.' + t.unlock_academy;
            } else {
                trainBtn.disabled = false;
                trainBtn.textContent = 'AUSBILDEN';
            }
        }
    }

    function brkRenderQueue() {
        const bar = document.getElementById('brk-queue-bar');
        if (!bar) return;
        if (!BRK_QUEUE.length) { bar.style.display = 'none'; return; }
        bar.style.display = '';
        let html = '<div class="brk-queue-title">Trainings-Queue</div>';
        BRK_QUEUE.forEach(q => {
            html += '<div class="brk-queue-item">' +
                '<div class="brk-queue-icon">⚔</div>' +
                '<div class="brk-queue-info"><span>' + q.count + '× ' + q.name + '</span></div>' +
                '<div class="brk-queue-cd" data-finish="' + q.finishes_at + '">—</div>' +
                '</div>';
        });
        bar.innerHTML = html;
    }

    // Type tab clicks
    document.querySelectorAll('.brk-type-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.brk-type-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            brkCurrentType = parseInt(btn.dataset.btype, 10);
            brkSelectedCode = null;
            brkRenderCards();
        });
    });

    // Train button
    const brkTrainBtn = document.getElementById('brk-train-btn');
    if (brkTrainBtn) {
        brkTrainBtn.addEventListener('click', async () => {
            const code  = parseInt(brkTrainBtn.dataset.code, 10);
            const name  = brkTrainBtn.dataset.name;
            const count = parseInt(document.getElementById('brk-count')?.value ?? '0', 10);
            if (!count || count < 1) { showToast('Ungültige Anzahl', 'err'); return; }

            brkTrainBtn.disabled = true;
            brkTrainBtn.textContent = '…';

            try {
                const res  = await fetch('/api/troops/train', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ troop_code: code, count }),
                });
                const json = await res.json();
                if (json.ok) {
                    showToast('Training gestartet: ' + count + '× ' + name, 'ok');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    showToast(json.error?.message ?? json.error ?? 'Fehler', 'err');
                    brkTrainBtn.disabled = false;
                    brkTrainBtn.textContent = 'AUSBILDEN';
                }
            } catch (e) {
                showToast('Netzwerkfehler', 'err');
                brkTrainBtn.disabled = false;
                brkTrainBtn.textContent = 'AUSBILDEN';
            }
        });
    }

    // Queue countdowns (reuse existing updateQueueCountdowns via .brk-queue-cd)
    function updateBrkQueueCd() {
        document.querySelectorAll('.brk-queue-cd').forEach(el => {
            const finish = parseInt(el.dataset.finish, 10) * 1000;
            const rem    = Math.max(0, Math.ceil((finish - Date.now()) / 1000));
            if (rem === 0) { el.textContent = 'fertig!'; return; }
            el.textContent = fmtTimeSec(rem);
        });
    }
    setInterval(updateBrkQueueCd, 1000);

    // Init
    brkRenderCards();
    brkRenderQueue();
    updateBrkQueueCd();
})();
<?php endif ?>

// ---------------------------------------------------------------------------
// Queue countdowns (training queue)
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
// Tab switching
// ---------------------------------------------------------------------------
document.querySelectorAll('.tab-item[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        document.querySelectorAll('.tab-pane').forEach(p => { p.style.display = 'none'; });
        document.querySelectorAll('.tab-item').forEach(b => b.classList.remove('tab-active'));
        const pane = document.getElementById('tab-' + target);
        if (pane) pane.style.display = '';
        btn.classList.add('tab-active');

        // Lazy-load iframe tabs (e.g. research)
        if (pane) {
            const iframe = pane.querySelector('iframe[data-src]');
            if (iframe && !iframe.src.includes(iframe.dataset.src.split('?')[0])) {
                iframe.src = iframe.dataset.src;
            }
        }
    });
});

// ---------------------------------------------------------------------------
// Toast helper
// ---------------------------------------------------------------------------
function showToast(msg, type) {
    const t = document.getElementById('bm-toast');
    t.textContent = msg;
    t.className   = type;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}
})();
</script>

<?php if ($buildingCode === 'trading_post'): ?>
<!-- Alpine.js CDN — only loaded for the Trading Post view -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function caravanApp() {
    return {
        loading:      true,
        error:        null,
        slots:        [],
        nextRefresh:  null,   // ISO-8601 string from server
        refreshLabel: '…',
        buying:       null,   // slot idx currently being purchased
        _cdTimer:     null,

        async boot() {
            try {
                const res  = await fetch('/api/trading/caravan');
                const json = await res.json();

                if (!json.ok) {
                    this.error = json.error?.message ?? json.error?.code ?? 'Ladefehler';
                    return;
                }

                this.slots       = json.data.slots;
                this.nextRefresh = json.data.next_refresh_at;
                this._startCountdown();
            } catch (e) {
                this.error = 'Netzwerkfehler beim Laden des Caravans.';
            } finally {
                this.loading = false;
            }
        },

        async buy(slotIdx) {
            if (this.buying !== null) return;
            this.buying = slotIdx;

            try {
                const res  = await fetch('/api/trading/caravan/buy', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': <?= json_encode($session['csrf_token']) ?>,
                    },
                    body: JSON.stringify({ slot_idx: slotIdx }),
                });
                const json = await res.json();

                if (json.ok) {
                    // Mark the slot as bought locally — avoids a full reload.
                    const idx = this.slots.findIndex(s => s.idx === slotIdx);
                    if (idx !== -1) this.slots[idx].bought = true;
                    this._toast(json.data.slot.label + ' gekauft!', 'ok');
                } else {
                    this._toast(json.error?.message ?? json.error?.code ?? 'Kauf fehlgeschlagen', 'err');
                }
            } catch (e) {
                this._toast('Netzwerkfehler', 'err');
            } finally {
                this.buying = null;
            }
        },

        // ---- helpers --------------------------------------------------------

        fmt(n) {
            return Number(n).toLocaleString('de-DE');
        },

        currencyIcon(cur) {
            const map = { gems: '💎', food: '🌾', lumber: '🪵', stone: '🪨', gold: '💰' };
            return map[cur] ?? cur;
        },

        currencyLabel(cur) {
            const map = { gems: 'Gems', food: 'Nahrung', lumber: 'Holz', stone: 'Stein', gold: 'Gold' };
            return map[cur] ?? cur;
        },

        _startCountdown() {
            if (this._cdTimer) clearInterval(this._cdTimer);

            const tick = () => {
                if (!this.nextRefresh) return;

                const target = new Date(this.nextRefresh.replace(' ', 'T') + 'Z').getTime();
                const rem    = Math.max(0, Math.ceil((target - Date.now()) / 1000));

                if (rem === 0) {
                    this.refreshLabel = 'jetzt!';
                    clearInterval(this._cdTimer);
                    // Auto-reload caravan after a brief pause.
                    setTimeout(() => {
                        this.loading = true;
                        this.error   = null;
                        this.boot();
                    }, 1500);
                    return;
                }

                const h = Math.floor(rem / 3600);
                const m = Math.floor((rem % 3600) / 60);
                const s = rem % 60;
                const pad = n => String(n).padStart(2, '0');

                if (h > 0) this.refreshLabel = h + 'h ' + pad(m) + 'm ' + pad(s) + 's';
                else if (m > 0) this.refreshLabel = m + 'm ' + pad(s) + 's';
                else this.refreshLabel = s + 's';
            };

            tick();
            this._cdTimer = setInterval(tick, 1000);
        },

        _toast(msg, type) {
            const t = document.getElementById('bm-toast');
            if (!t) return;
            t.textContent    = msg;
            t.className      = type;
            t.style.display  = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 3200);
        },
    };
}
</script>
<?php endif ?>

<?php if (!$isModal): ?>
</body>
</html>
<?php endif ?>
