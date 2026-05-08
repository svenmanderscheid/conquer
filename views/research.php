<?php
declare(strict_types=1);
/**
 * Research view — /research
 * Three horizontal row lanes (Infantry / Ranged / Cavalry) for Battle tab,
 * similar lanes for Production and Advanced tabs.
 */

use Conquer\Db\Connection;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchProcessor;

$db       = Connection::getInstance();
$playerId = (int) $session['player_id'];

ResearchProcessor::processQueue($playerId);

$academyRow   = $db->query(
    "SELECT cb.level FROM city_buildings cb
     JOIN cities c ON c.id = cb.city_id
     WHERE c.player_id = ? AND cb.building_code = 'academy' LIMIT 1",
    [$playerId],
)->fetch();
$academyLevel = $academyRow ? (int) $academyRow['level'] : 0;

$researchLevels = [];
foreach ($db->query(
    'SELECT research_code, level FROM player_research WHERE player_id = ? AND world_id = 1',
    [$playerId],
)->fetchAll() as $r) {
    $researchLevels[$r['research_code']] = (int) $r['level'];
}

$queueRow = $db->query(
    "SELECT * FROM research_queue
     WHERE player_id = ? AND is_processed = 0 ORDER BY id DESC LIMIT 1",
    [$playerId],
)->fetch() ?: null;

$cityRow = $db->query(
    'SELECT food, lumber, stone, gold FROM cities WHERE player_id = ? LIMIT 1',
    [$playerId],
)->fetch() ?: ['food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0];

$buffs = BuffEngine::getBuffs($playerId);

// ── Row definitions per tab ──────────────────────────────────────────────────

$battleRows = [
    ['id' => 'infantry', 'label' => 'Infantry', 'color' => '#3b82f6',
     'codes' => ['infantry_hp','infantry_def','infantry_atk','infantry_spd','warrior',
                 'infantry_training_amount','infantry_training_speed','infantry_training_cost',
                 'knight','guardian','advanced_infantry_hp','advanced_infantry_def',
                 'advanced_infantry_atk','advanced_infantry_spd','crusader']],
    ['id' => 'ranged',   'label' => 'Ranged',   'color' => '#22c55e',
     'codes' => ['ranged_hp','ranged_def','ranged_atk','ranged_spd','longbow_man',
                 'ranged_training_amount','ranged_training_speed','ranged_training_cost',
                 'ranger','crossbow_man','advanced_ranged_hp','advanced_ranged_def',
                 'advanced_ranged_atk','advanced_ranged_spd','sniper']],
    ['id' => 'cavalry',  'label' => 'Cavalry',  'color' => '#f59e0b',
     'codes' => ['cavalry_hp','cavalry_def','cavalry_atk','cavalry_spd','horseman',
                 'cavalry_training_amount','cavalry_training_speed','cavalry_training_cost',
                 'heavy_cavalry','iron_cavalry','advanced_cavalry_hp','advanced_cavalry_def',
                 'advanced_cavalry_atk','advanced_cavalry_spd','dragoon']],
];
$battleGeneralCodes = ['troops_storage','march_size','march_limit','troops_hp','troops_atk',
                       'troops_def','troops_spd','hospital_capacity','healing_time_reduced','rally_attack_amount'];

$productionRows = [
    ['id' => 'food',  'label' => 'Food',    'color' => '#84cc16',
     'codes' => ['food_production','food_capacity','food_gathering_speed',
                 'advanced_food_production','advanced_food_capacity','advanced_food_gathering_speed']],
    ['id' => 'wood',  'label' => 'Wood',    'color' => '#78716c',
     'codes' => ['wood_production','wood_capacity','wood_gathering_speed',
                 'advanced_wood_production','advanced_wood_capacity','advanced_wood_gathering_speed']],
    ['id' => 'stone', 'label' => 'Stone',   'color' => '#94a3b8',
     'codes' => ['stone_production','stone_capacity','stone_gathering_speed',
                 'advanced_stone_production','advanced_stone_capacity','advanced_stone_gathering_speed']],
];
$productionGeneralCodes = ['gold_production','gold_capacity','gold_gathering_speed','crystal_gathering_speed',
                           'infantry_storage','ranged_storage','cavalry_storage','resource_protect',
                           'research_speed','construction_speed',
                           'advanced_gold_production','advanced_gold_capacity','advanced_gold_gathering_speed',
                           'advanced_crystal_gathering_speed','advanced_research_speed','advanced_construction_speed'];

$advancedRows = [
    ['id' => 'counter',    'label' => 'Counter',     'color' => '#c084fc',
     'codes' => ['infantry_hp_against_archer','infantry_def_against_archer','infantry_atk_against_archer',
                 'archer_hp_against_cavalry','archer_def_against_cavalry','archer_atk_against_cavalry',
                 'cavalry_hp_against_infantry','cavalry_def_against_infantry','cavalry_atk_against_infantry']],
    ['id' => 'castle_def', 'label' => 'Castle Def',  'color' => '#fb923c',
     'codes' => ['castle_defending_infantrys_hp','castle_defending_infantrys_def','castle_defending_infantrys_atk',
                 'castle_defending_archers_hp','castle_defending_archers_def','castle_defending_archers_atk',
                 'castle_defending_cavalrys_hp','castle_defending_cavalrys_def','castle_defending_cavalrys_atk']],
    ['id' => 'composed',   'label' => 'Single-Type',  'color' => '#34d399',
     'codes' => ['infantrys_hp_when_composed_of_infantry_only','infantrys_def_when_composed_of_infantry_only',
                 'infantrys_atk_when_composed_of_infantry_only',
                 'archers_hp_when_composed_of_archer_only','archers_def_when_composed_of_archer_only',
                 'archers_atk_when_composed_of_archer_only',
                 'cavalrys_hp_when_composed_of_cavalry_only','cavalrys_def_when_composed_of_cavalry_only',
                 'cavalrys_atk_when_composed_of_cavalry_only']],
];
$advancedGeneralCodes = ['resource_production','resource_capacity','resource_protect',
                         'troop_speed_when_participating_a_rally',
                         'infantrys_hp_when_participating_a_rally','infantrys_def_when_participating_a_rally',
                         'infantrys_atk_when_participating_a_rally',
                         'archers_hp_when_participating_a_rally','archers_def_when_participating_a_rally',
                         'archers_atk_when_participating_a_rally',
                         'cavalrys_hp_when_participating_a_rally','cavalrys_def_when_participating_a_rally',
                         'cavalrys_atk_when_participating_a_rally'];

// All node definitions
$allNodes = ResearchData::allNodes();

// ── Helper: icon / color ─────────────────────────────────────────────────────

function nodeIcon(array $node): string {
    if ($node['type'] === 'unlock') return '&#x1F513;'; // 🔓
    return match ($node['stat'] ?? '') {
        'hp'                        => '&#x2764;',      // ❤
        'atk'                       => '&#x2694;',      // ⚔
        'def'                       => '&#x1F6E1;',     // 🛡
        'spd'                       => '&#x26A1;',      // ⚡
        'hospital_capacity'         => '&#x1F3E5;',     // 🏥
        'healing_time_reduced'      => '&#x1F48A;',     // 💊
        'march_size'                => '&#x1F4CF;',     // 📏
        default                     => '&#x1F52C;',     // 🔬
    };
}

function nodeColor(array $node): string {
    if ($node['type'] === 'unlock') return '#4c1d95';
    return match ($node['category'] ?? '') {
        'infantry'      => '#1e3a8a',
        'ranged'        => '#14532d',
        'cavalry'       => '#7c2d12',
        'general'       => '#78350f',
        'production'    => '#0e4f5c',
        'training'      => '#1e3a6e',
        'counter','castle_defense','composed','rally' => '#1e1b4b',
        default         => '#1e293b',
    };
}

function nodeBorderColor(array $node): string {
    if ($node['type'] === 'unlock') return '#7c3aed';
    return match ($node['category'] ?? '') {
        'infantry'      => '#3b82f6',
        'ranged'        => '#22c55e',
        'cavalry'       => '#f59e0b',
        'general'       => '#f59e0b',
        'production'    => '#06b6d4',
        'training'      => '#60a5fa',
        default         => '#6366f1',
    };
}

// Precompute levels JSON for all nodes used in the view
function levelsJson(array $node): string {
    return json_encode(
        array_values(array_map(fn($e) => [
            'level'         => (int)$e['level'],
            'ability_value' => $e['ability_value'],
            'time'          => (int)$e['time'],
            'resources'     => $e['resources'],
            'requirements'  => $e['requirements'] ?? [],
        ], $node['levels'])),
        JSON_THROW_ON_ERROR
    );
}

// Build a flat nodes-meta map for JS (code -> {name, max_level})
$nodesForJs = [];
foreach ($allNodes as $code => $node) {
    $nodesForJs[$code] = [
        'name'      => $node['name'],
        'max_level' => (int)$node['max_level'],
        'type'      => $node['type'],
        'stat'      => $node['stat'] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Forschung</title>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f1929;
            --surface: #162033;
            --surface2: #1a2a42;
            --border:  #1e3a5f;
            --text:    #c8daea;
            --muted:   #5a7a9a;
            --gold:    #d4a017;
            --gold2:   #f0c040;
            --green:   #22c55e;
            --red:     #ef4444;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            overflow: hidden;
        }

        #game {
            height: 100%;
            display: flex;
            flex-direction: column;
            padding-top: 72px;
        }

        /* ── Top bar ── */
        .topbar {
            flex-shrink: 0;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 1rem;
            height: 44px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .topbar-back {
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
            font-size: 0.75rem;
        }
        .topbar-back:hover { color: var(--gold2); border-color: var(--gold); }
        .topbar-title { font-weight: 700; color: var(--gold2); font-size: 0.88rem; }
        .topbar-acad  { margin-left: auto; font-size: 0.73rem; color: var(--muted); }
        .topbar-acad strong { color: var(--text); }

        /* ── Queue banner ── */
        .queue-banner {
            flex-shrink: 0;
            height: 38px;
            background: linear-gradient(90deg, #0d2a4f 0%, #0a1e38 100%);
            border-bottom: 1px solid #1e4a8f;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.78rem;
        }
        .qb-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .qb-name { color: var(--gold2); font-weight: 700; }
        .qb-eta  { color: var(--muted); margin-left: auto; }
        .btn-instant {
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            border: 1px solid rgba(139,92,246,.5);
            background: rgba(139,92,246,.1);
            color: #c4b5fd;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-instant:hover { background: rgba(139,92,246,.25); }

        /* ── Tab bar ── */
        .tab-bar {
            flex-shrink: 0;
            height: 38px;
            background: #0d1e32;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: stretch;
            padding: 0 0.75rem;
            gap: 0.25rem;
        }
        .tab-btn {
            padding: 0 1rem;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: color .15s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { color: #38bdf8; border-bottom-color: #38bdf8; }

        /* ── Tree area wrapper ── */
        .tree-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        /* Horizontally scrollable lane container */
        .tree-scroll-x {
            flex: 1;
            overflow-x: auto;
            overflow-y: auto;
            padding: 12px 0 12px 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
            scrollbar-width: thin;
            scrollbar-color: #1e3a5f var(--bg);
        }
        .tree-scroll-x::-webkit-scrollbar { height: 6px; width: 6px; }
        .tree-scroll-x::-webkit-scrollbar-track { background: var(--bg); }
        .tree-scroll-x::-webkit-scrollbar-thumb { background: #1e3a5f; border-radius: 3px; }

        /* ── Tree row (one lane) ── */
        .tree-row {
            display: flex;
            align-items: center;
            gap: 0;
            min-height: 118px;
            flex-shrink: 0;
        }
        .tree-row-general {
            min-height: 88px;
            border-top: 1px solid #334155;
            padding-top: 6px;
            margin-top: 2px;
        }

        /* Row label — sticky on the left */
        .row-label {
            width: 76px;
            min-width: 76px;
            font-weight: 700;
            font-size: 0.72rem;
            text-align: right;
            padding-right: 10px;
            position: sticky;
            left: 0;
            z-index: 10;
            background: var(--bg);
            align-self: stretch;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        /* Container of node cards + connectors */
        .row-nodes {
            display: flex;
            align-items: center;
            gap: 0;
            padding-right: 24px;
        }

        /* ── Connector arrow ── */
        .connector {
            flex-shrink: 0;
            width: 24px;
            height: 3px;
            background: #0891b2;
            position: relative;
        }
        .connector::after {
            content: '';
            position: absolute;
            right: -1px;
            top: 50%;
            transform: translateY(-50%);
            border-left: 6px solid #0891b2;
            border-top: 4px solid transparent;
            border-bottom: 4px solid transparent;
        }

        /* ── Node card ── */
        .node-card {
            flex-shrink: 0;
            width: 88px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 6px 4px 5px;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: border-color .15s, background .15s;
            position: relative;
        }
        .node-card:hover { background: rgba(255,255,255,.04); }
        .node-card.is-selected {
            border-color: #f0c040 !important;
            background: rgba(240,192,64,.07);
        }
        .node-card.is-locked { opacity: .42; cursor: default; }
        .node-card.is-locked:hover { background: transparent; }
        .node-card.is-active { /* queue active */ }

        .nc-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .nc-name {
            font-size: 0.58rem;
            color: #cbd5e1;
            text-align: center;
            line-height: 1.2;
            max-width: 80px;
            min-height: 2.4em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nc-bar-wrap {
            width: 62px;
            height: 14px;
            background: #0a1628;
            border-radius: 3px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.07);
        }
        .nc-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #5b21b6, #7c3aed, #8b5cf6);
            transition: width .4s ease;
        }
        .nc-bar-fill.maxed {
            background: linear-gradient(90deg, #b45309, #d97706, #f59e0b);
        }
        .nc-bar-text {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.56rem;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,.8);
        }

        /* ── Detail panel (fixed at bottom of tree-area) ── */
        .detail-panel {
            flex-shrink: 0;
            background: #0d1e35;
            border-top: 1px solid #1e4a8f;
            padding: 10px 16px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            min-height: 80px;
        }
        .dp-title  { font-size: 0.8rem; font-weight: 700; color: var(--gold2); margin-bottom: 3px; }
        .dp-effect { font-size: 0.72rem; color: var(--green); margin-bottom: 4px; }
        .dp-costs  {
            display: flex; flex-wrap: wrap; gap: 0.4rem;
            font-size: 0.67rem; color: var(--muted);
        }
        .dp-costs span strong { color: var(--text); }
        .dp-time   { font-size: 0.67rem; color: var(--muted); margin-top: 3px; }
        .dp-lock   { font-size: 0.7rem; color: var(--red); }
        .btn-start {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #2563a8;
            background: #1e4080;
            color: #7ab4e0;
            font-size: 0.76rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s;
        }
        .btn-start:hover:not(:disabled) { background: #2563a8; color: #e2f0ff; }
        .btn-start:disabled { opacity: .4; cursor: not-allowed; }

        /* ── Buffs tab ── */
        .buffs-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.25rem 2rem;
            scrollbar-width: thin;
            scrollbar-color: #1e3a5f var(--bg);
        }
        .buffs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .buff-group-title {
            font-size: 0.62rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .08em;
            color: var(--gold); margin-bottom: 0.5rem;
        }
        .buff-row {
            display: flex; justify-content: space-between;
            font-size: 0.73rem; padding: 0.2rem 0;
            border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .buff-row:last-child { border-bottom: none; }
        .buff-label { color: var(--muted); }
        .buff-val   { font-weight: 700; }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 1.5rem; right: 1.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 7px;
            font-size: 0.8rem; font-weight: 600;
            z-index: 9000; pointer-events: none;
        }
        .toast.ok  { background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.4); color:#22c55e; }
        .toast.err { background: rgba(239,68,68,.12);  border:1px solid rgba(239,68,68,.4);  color:#ef4444; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>

<div id="game" x-data="researchApp()" x-init="boot()">

    <!-- Top bar -->
    <div class="topbar">
        <a href="/city" class="topbar-back">&larr; Stadt</a>
        <span class="topbar-title">&#x1F52C; Akademie &mdash; Forschung</span>
        <div class="topbar-acad">Akademie Lv <strong><?= $academyLevel ?></strong></div>
    </div>

    <!-- Queue banner -->
    <template x-if="queue && !queue.done">
        <div class="queue-banner">
            <div class="qb-dot"></div>
            <span style="color:var(--muted);font-size:.65rem;text-transform:uppercase;font-weight:800">In Forschung</span>
            <span class="qb-name" x-text="queue.name + ' \u2192 Lv ' + queue.level_to"></span>
            <span class="qb-eta">&#x23F1; <span x-text="fmtEta(queue.finishes_at)"></span></span>
            <button class="btn-instant" @click="instantFinish()">&#x1F48E; Sofort</button>
        </div>
    </template>

    <!-- Tab bar -->
    <div class="tab-bar">
        <button class="tab-btn" :class="{active:tab==='battle'}"     @click="tab='battle';selected=null">&#x2694; Kampf</button>
        <button class="tab-btn" :class="{active:tab==='production'}" @click="tab='production';selected=null">&#x1F33E; Produktion</button>
        <button class="tab-btn" :class="{active:tab==='advanced'}"   @click="tab='advanced';selected=null">&#x1F52E; Erweitert</button>
        <button class="tab-btn" :class="{active:tab==='buffs'}"      @click="tab='buffs';selected=null">&#x1F4CA; Buffs</button>
    </div>

    <!-- ── BATTLE TAB ── -->
    <div class="tree-area" x-show="tab==='battle'">
        <div class="tree-scroll-x">

            <?php foreach ($battleRows as $lane): ?>
            <div class="tree-row">
                <div class="row-label" style="color:<?= $lane['color'] ?>"><?= htmlspecialchars($lane['label']) ?></div>
                <div class="row-nodes">
                    <?php foreach ($lane['codes'] as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                        $lj     = levelsJson($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>

            <!-- General row -->
            <div class="tree-row tree-row-general">
                <div class="row-label" style="color:#94a3b8">Allgemein</div>
                <div class="row-nodes">
                    <?php foreach ($battleGeneralCodes as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>

        </div><!-- .tree-scroll-x -->

        <!-- Detail panel -->
        <template x-if="selected !== null && tab==='battle'">
            <div class="detail-panel" x-data="nodeDetail(selected, _allNodes, _levels)">
                <div>
                    <div class="dp-title" x-text="nodeName"></div>
                    <template x-if="!isMaxed">
                        <div>
                            <div class="dp-effect" x-text="effectText"></div>
                            <template x-if="canStart">
                                <div class="dp-costs" x-html="costsHtml"></div>
                            </template>
                            <template x-if="!canStart">
                                <div class="dp-lock" x-text="lockReason"></div>
                            </template>
                            <div class="dp-time" x-text="'&#x23F1; ' + timeText + '  &nbsp;|&nbsp;  Lv ' + curLevel + ' / ' + maxLevel"></div>
                        </div>
                    </template>
                    <template x-if="isMaxed">
                        <div style="color:var(--gold2);font-size:.72rem">&#x2713; Maximal erforscht</div>
                    </template>
                </div>
                <template x-if="!isMaxed">
                    <button class="btn-start"
                            :disabled="!canStart || !!$root.queue || $root.loading"
                            @click="$root.startResearch(selected)">
                        Erforschen
                    </button>
                </template>
            </div>
        </template>
    </div><!-- battle tab -->

    <!-- ── PRODUCTION TAB ── -->
    <div class="tree-area" x-show="tab==='production'">
        <div class="tree-scroll-x">

            <?php foreach ($productionRows as $lane): ?>
            <div class="tree-row">
                <div class="row-label" style="color:<?= $lane['color'] ?>"><?= htmlspecialchars($lane['label']) ?></div>
                <div class="row-nodes">
                    <?php foreach ($lane['codes'] as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>

            <!-- General row -->
            <div class="tree-row tree-row-general">
                <div class="row-label" style="color:#94a3b8">Sonstiges</div>
                <div class="row-nodes">
                    <?php foreach ($productionGeneralCodes as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>

        </div><!-- .tree-scroll-x -->

        <template x-if="selected !== null && tab==='production'">
            <div class="detail-panel" x-data="nodeDetail(selected, _allNodes, _levels)">
                <div>
                    <div class="dp-title" x-text="nodeName"></div>
                    <template x-if="!isMaxed">
                        <div>
                            <div class="dp-effect" x-text="effectText"></div>
                            <template x-if="canStart">
                                <div class="dp-costs" x-html="costsHtml"></div>
                            </template>
                            <template x-if="!canStart">
                                <div class="dp-lock" x-text="lockReason"></div>
                            </template>
                            <div class="dp-time" x-text="'&#x23F1; ' + timeText + '  &nbsp;|&nbsp;  Lv ' + curLevel + ' / ' + maxLevel"></div>
                        </div>
                    </template>
                    <template x-if="isMaxed">
                        <div style="color:var(--gold2);font-size:.72rem">&#x2713; Maximal erforscht</div>
                    </template>
                </div>
                <template x-if="!isMaxed">
                    <button class="btn-start"
                            :disabled="!canStart || !!$root.queue || $root.loading"
                            @click="$root.startResearch(selected)">
                        Erforschen
                    </button>
                </template>
            </div>
        </template>
    </div><!-- production tab -->

    <!-- ── ADVANCED TAB ── -->
    <div class="tree-area" x-show="tab==='advanced'">
        <div class="tree-scroll-x">

            <?php foreach ($advancedRows as $lane): ?>
            <div class="tree-row">
                <div class="row-label" style="color:<?= $lane['color'] ?>"><?= htmlspecialchars($lane['label']) ?></div>
                <div class="row-nodes">
                    <?php foreach ($lane['codes'] as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>

            <!-- Rally / General row -->
            <div class="tree-row tree-row-general">
                <div class="row-label" style="color:#94a3b8">Rally</div>
                <div class="row-nodes">
                    <?php foreach ($advancedGeneralCodes as $i => $code):
                        $node = $allNodes[$code] ?? null;
                        if ($node === null) continue;
                        $maxLv  = (int)$node['max_level'];
                        $bgCol  = nodeColor($node);
                        $bdCol  = nodeBorderColor($node);
                        $icon   = nodeIcon($node);
                    ?>
                    <?php if ($i > 0): ?><div class="connector"></div><?php endif ?>
                    <div class="node-card"
                         :class="nodeCardClass('<?= $code ?>')"
                         @click="toggleSelect('<?= $code ?>')"
                         style="border-color:<?= $bdCol ?>33">
                        <div class="nc-icon" style="background:<?= $bgCol ?>;border-color:<?= $bdCol ?>"><?= $icon ?></div>
                        <div class="nc-name"><?= htmlspecialchars($node['name']) ?></div>
                        <div class="nc-bar-wrap">
                            <div class="nc-bar-fill"
                                 :class="{'maxed':(research['<?= $code ?>']||0)>=<?= $maxLv ?>}"
                                 :style="{width:((research['<?= $code ?>']||0)/<?= $maxLv ?>*100)+'%'}"></div>
                            <div class="nc-bar-text" x-text="(research['<?= $code ?>']||0)+'/<?= $maxLv ?>'"></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>

        </div><!-- .tree-scroll-x -->

        <template x-if="selected !== null && tab==='advanced'">
            <div class="detail-panel" x-data="nodeDetail(selected, _allNodes, _levels)">
                <div>
                    <div class="dp-title" x-text="nodeName"></div>
                    <template x-if="!isMaxed">
                        <div>
                            <div class="dp-effect" x-text="effectText"></div>
                            <template x-if="canStart">
                                <div class="dp-costs" x-html="costsHtml"></div>
                            </template>
                            <template x-if="!canStart">
                                <div class="dp-lock" x-text="lockReason"></div>
                            </template>
                            <div class="dp-time" x-text="'&#x23F1; ' + timeText + '  &nbsp;|&nbsp;  Lv ' + curLevel + ' / ' + maxLevel"></div>
                        </div>
                    </template>
                    <template x-if="isMaxed">
                        <div style="color:var(--gold2);font-size:.72rem">&#x2713; Maximal erforscht</div>
                    </template>
                </div>
                <template x-if="!isMaxed">
                    <button class="btn-start"
                            :disabled="!canStart || !!$root.queue || $root.loading"
                            @click="$root.startResearch(selected)">
                        Erforschen
                    </button>
                </template>
            </div>
        </template>
    </div><!-- advanced tab -->

    <!-- ── BUFFS TAB ── -->
    <div class="tree-area" x-show="tab==='buffs'">
        <div class="buffs-scroll">
            <div class="buffs-grid">
                <?php
                $boostGroups = [
                    'Allgemein'    => ['Truppen HP' => 'troops_hp', 'Truppen ATK' => 'troops_atk', 'Truppen DEF' => 'troops_def', 'Truppen SPD' => 'troops_spd'],
                    'Infanterie'   => ['HP' => 'infantry_hp', 'ATK' => 'infantry_atk', 'DEF' => 'infantry_def', 'SPD' => 'infantry_spd'],
                    'Fernkämpfer'  => ['HP' => 'ranged_hp', 'ATK' => 'ranged_atk', 'DEF' => 'ranged_def', 'SPD' => 'ranged_spd'],
                    'Kavallerie'   => ['HP' => 'cavalry_hp', 'ATK' => 'cavalry_atk', 'DEF' => 'cavalry_def', 'SPD' => 'cavalry_spd'],
                    'Sonstiges'    => ['Marschgröße' => 'march_size', 'Krankenhaus' => 'hospital_capacity', 'Heilung' => 'healing_time_reduced', 'Bau' => 'construction_speed', 'Forschung' => 'research_speed'],
                ];
                foreach ($boostGroups as $gName => $items):
                ?>
                <div>
                    <div class="buff-group-title"><?= $gName ?></div>
                    <?php foreach ($items as $label => $key):
                        $raw  = $buffs[$key] ?? 0;
                        $flat = in_array($key, ['march_size','hospital_capacity'], true);
                        $disp = $flat ? '+' . (int)$raw : '+' . round((float)$raw * 100, 1) . '%';
                        $active = $raw > 0;
                    ?>
                    <div class="buff-row">
                        <span class="buff-label"><?= $label ?></span>
                        <span class="buff-val" style="color:<?= $active ? 'var(--green)' : 'var(--muted)' ?>"><?= $disp ?></span>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div><!-- buffs tab -->

    <!-- Toast -->
    <template x-if="toast.msg">
        <div class="toast" :class="toast.type" x-text="toast.msg"></div>
    </template>

</div><!-- #game -->

<script>
// ── Server-side data injected into JS ────────────────────────────────────────
const _research  = <?= json_encode($researchLevels, JSON_THROW_ON_ERROR) ?>;
const _academyLv = <?= $academyLevel ?>;
const _queue     = <?= $queueRow ? json_encode([
    'code'        => $queueRow['research_code'],
    'name'        => $allNodes[$queueRow['research_code']]['name'] ?? $queueRow['research_code'],
    'level_to'    => (int)$queueRow['level_to'],
    'finishes_at' => $queueRow['finishes_at'],
    'done'        => false,
], JSON_THROW_ON_ERROR) : 'null' ?>;

// Flat map of all node meta (no level details — those are loaded on-demand)
const _allNodes  = <?= json_encode($nodesForJs, JSON_THROW_ON_ERROR) ?>;

// All level data, indexed by code — used by the detail panel
const _levels    = <?= json_encode(
    array_map(fn($node) => array_map(fn($e) => [
        'level'         => (int)$e['level'],
        'ability_value' => $e['ability_value'],
        'time'          => (int)$e['time'],
        'resources'     => $e['resources'],
        'requirements'  => $e['requirements'] ?? [],
    ], $node['levels']), $allNodes),
    JSON_THROW_ON_ERROR
) ?>;

// ── Main Alpine component ─────────────────────────────────────────────────────
function researchApp() {
    return {
        tab:         'battle',
        selected:    null,
        loading:     false,
        toast:       { msg: '', type: 'ok' },
        tick:        0,
        research:    { ..._research },
        academyLevel: _academyLv,
        queue:       _queue ? { ..._queue } : null,

        boot() {
            setInterval(() => this.tick++, 1000);
            setInterval(() => this.pollState(), 15000);
        },

        toggleSelect(code) {
            this.selected = this.selected === code ? null : code;
        },

        nodeCardClass(code) {
            const cur  = this.research[code] || 0;
            const meta = _allNodes[code];
            const max  = meta ? meta.max_level : 1;
            const classes = [];
            if (this.selected === code)                          classes.push('is-selected');
            if (this.queue && !this.queue.done && this.queue.code === code) classes.push('is-active');
            // Check locked state: look at level 1 requirements
            if (this.isNodeLocked(code)) classes.push('is-locked');
            return classes.join(' ');
        },

        isNodeLocked(code) {
            const nodeLevels = _levels[code];
            if (!nodeLevels || nodeLevels.length === 0) return false;
            const firstEntry = nodeLevels[0];
            for (const req of (firstEntry.requirements || [])) {
                if (req.type === 'academy' && this.academyLevel < req.level) return true;
                if (req.type === 'research') {
                    if ((this.research[req.code] || 0) < (req.level ?? 1)) return true;
                }
            }
            return false;
        },

        fmtSec(s) {
            s = parseInt(s);
            if (s < 60)   return s + 's';
            if (s < 3600) return Math.floor(s/60) + 'm ' + (s%60) + 's';
            const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
            return h + 'h' + (m ? ' ' + m + 'm' : '');
        },

        fmtEta(ts) {
            const _ = this.tick;
            if (!ts) return '';
            const diff = Math.max(0, Math.floor((new Date(ts.replace(' ','T')+'Z') - Date.now()) / 1000));
            if (diff === 0) { if (this.queue) this.queue.done = true; this.pollState(); return 'Fertig!'; }
            return this.fmtSec(diff);
        },

        async startResearch(code) {
            if (this.loading || this.queue) return;
            this.loading = true;
            try {
                const r = await fetch('/api/research/start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.queue    = j.data.queue;
                    this.selected = null;
                    this.showToast('Forschung gestartet!', 'ok');
                } else {
                    this.showToast(j.message ?? j.error, 'err');
                }
            } catch(e) {
                this.showToast('Fehler: ' + e.message, 'err');
            } finally {
                this.loading = false;
            }
        },

        async instantFinish() {
            if (!this.queue) return;
            const r = await fetch('/api/research/instant', { method: 'POST' });
            const j = await r.json();
            if (j.ok) {
                this.research[this.queue.code] = this.queue.level_to;
                this.queue = null;
                this.showToast('Forschung abgeschlossen!', 'ok');
            } else {
                this.showToast(j.message ?? j.error, 'err');
            }
        },

        async pollState() {
            try {
                const r = await fetch('/api/research/state');
                const j = await r.json();
                if (!j.ok) return;
                this.research     = j.data.research;
                this.queue        = j.data.queue ?? null;
                this.academyLevel = j.data.academy_level ?? this.academyLevel;
            } catch {}
        },

        showToast(msg, type) {
            this.toast = { msg, type };
            setTimeout(() => this.toast = { msg: '', type: 'ok' }, 3500);
        },
    };
}

// ── Detail panel component ────────────────────────────────────────────────────
// Receives selected code + shared references to _allNodes and _levels.
function nodeDetail(code, allNodes, allLevels) {
    return {
        get code()      { return this.$root.selected; },
        get meta()      { return allNodes[this.$root.selected] ?? null; },
        get levels()    { return allLevels[this.$root.selected] ?? []; },
        get nodeName()  { return this.meta ? this.meta.name : ''; },
        get maxLevel()  { return this.meta ? this.meta.max_level : 1; },
        get curLevel()  { return this.$root.research[this.$root.selected] || 0; },
        get nextLevel() { return this.curLevel + 1; },
        get isMaxed()   { return this.curLevel >= this.maxLevel; },
        get entry()     { return this.levels.find(e => e.level === this.nextLevel) ?? null; },

        get canStart() {
            if (this.isMaxed || !this.entry) return false;
            for (const req of (this.entry.requirements ?? [])) {
                if (req.type === 'academy' && this.$root.academyLevel < req.level) return false;
                if (req.type === 'research') {
                    if ((this.$root.research[req.code] || 0) < (req.level ?? 1)) return false;
                }
            }
            return true;
        },

        get lockReason() {
            if (!this.entry) return 'Keine Daten';
            for (const req of (this.entry.requirements ?? [])) {
                if (req.type === 'academy' && this.$root.academyLevel < req.level)
                    return `Akademie Lv ${req.level} benötigt (aktuell: ${this.$root.academyLevel})`;
                if (req.type === 'research') {
                    if ((this.$root.research[req.code] || 0) < (req.level ?? 1)) {
                        const name = (allNodes[req.code] || {}).name || req.code;
                        return `Voraussetzung: ${name} Lv ${req.level ?? 1}`;
                    }
                }
            }
            return 'Gesperrt';
        },

        get effectText() {
            if (!this.entry) return '';
            const v    = parseFloat(this.entry.ability_value);
            const code = this.$root.selected;
            const stat = this.meta ? this.meta.stat : '';
            // Flat bonus types
            const flatStats = ['march_size','hospital_capacity','troops_storage',
                               'infantry_storage','ranged_storage','cavalry_storage'];
            if (flatStats.includes(stat) || flatStats.includes(code)) {
                return '+' + parseInt(v).toLocaleString('de') + ' (Lv ' + this.nextLevel + ')';
            }
            if (this.meta && this.meta.type === 'unlock') {
                return 'Schaltet Einheit frei (Lv ' + this.nextLevel + ')';
            }
            return '+' + (v * 100).toFixed(1) + '% (Lv ' + this.nextLevel + ')';
        },

        get timeText() {
            if (!this.entry) return '';
            return this.$root.fmtSec(this.entry.time ?? 0);
        },

        get costsHtml() {
            if (!this.entry) return '';
            const r   = this.entry.resources ?? {};
            const fmt = n => parseInt(n).toLocaleString('de');
            return `<span>&#x1F33E; <strong>${fmt(r.food ?? 0)}</strong></span>` +
                   `<span>&#x1FAB5; <strong>${fmt(r.lumber ?? 0)}</strong></span>` +
                   `<span>&#x1FAA8; <strong>${fmt(r.stone ?? 0)}</strong></span>` +
                   `<span>&#x1FA99; <strong>${fmt(r.gold ?? 0)}</strong></span>`;
        },
    };
}
</script>
</body>
</html>
