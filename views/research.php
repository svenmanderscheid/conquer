<?php
declare(strict_types=1);
/**
 * Research view — /research
 * LoK-style tree layout: phases with horizontal node rows, vertical scroll.
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

// ── Tree layout definition ───────────────────────────────────────────────────
// Each phase: label, required academy level, rows of node codes.
// Nodes within a row are connected left→right with arrows.
$treeLayouts = [

    'battle' => [
        ['label' => 'Basis-Stats',              'academy' => 1, 'rows' => [
            ['infantry_hp', 'infantry_def', 'infantry_atk', 'infantry_spd'],
            ['ranged_hp',   'ranged_def',   'ranged_atk',   'ranged_spd'],
            ['cavalry_hp',  'cavalry_def',  'cavalry_atk',  'cavalry_spd'],
            ['troops_storage'],
        ]],
        ['label' => 'T2-Truppen & Training',    'academy' => 10, 'rows' => [
            ['warrior',     'training_amount_infantry', 'training_speed_infantry'],
            ['longbow_man', 'training_amount_ranged',   'training_speed_ranged'],
            ['horseman',    'training_amount_cavalry',  'training_speed_cavalry'],
        ]],
        ['label' => 'Marsch',                   'academy' => 14, 'rows' => [
            ['march_size', 'march_limit'],
        ]],
        ['label' => 'T3-Truppen',               'academy' => 16, 'rows' => [
            ['knight'], ['ranger'], ['heavy_cavalry'],
        ]],
        ['label' => 'Allgemeine Kampfstats',    'academy' => 17, 'rows' => [
            ['troops_hp', 'troops_atk', 'troops_def', 'troops_spd'],
            ['hospital_capacity', 'healing_time_reduced'],
        ]],
        ['label' => 'T4-Truppen',               'academy' => 23, 'rows' => [
            ['guardian'], ['crossbow_man'], ['iron_cavalry'],
        ]],
        ['label' => 'Erweiterte Kampfstats',    'academy' => 24, 'rows' => [
            ['advanced_infantry_hp', 'advanced_infantry_atk', 'advanced_infantry_def'],
            ['advanced_ranged_hp',   'advanced_ranged_atk',   'advanced_ranged_def'],
            ['advanced_cavalry_hp',  'advanced_cavalry_atk',  'advanced_cavalry_def'],
            ['rally_attack_amount'],
        ]],
        ['label' => 'T5-Truppen',               'academy' => 30, 'rows' => [
            ['crusader'], ['sniper'], ['dragoon'],
        ]],
    ],

    'production' => [
        ['label' => 'Produktion', 'academy' => 1, 'rows' => [
            ['food_production', 'lumber_production', 'stone_production', 'gold_production'],
        ]],
        ['label' => 'Support',    'academy' => 1, 'rows' => [
            ['hospital_capacity', 'healing_speed', 'construction_speed', 'research_speed'],
        ]],
    ],

    'advanced' => [
        ['label' => 'Ressourcen',       'academy' => 23, 'rows' => [
            ['resource_production', 'resource_capacity', 'resource_protect_adv'],
        ]],
        ['label' => 'Konter-Buffs',     'academy' => 23, 'rows' => [
            ['infantry_vs_ranged_hp',  'infantry_vs_ranged_def',  'infantry_vs_ranged_atk'],
            ['ranged_vs_cavalry_hp',   'ranged_vs_cavalry_def',   'ranged_vs_cavalry_atk'],
            ['cavalry_vs_infantry_hp', 'cavalry_vs_infantry_def', 'cavalry_vs_infantry_atk'],
        ]],
        ['label' => 'Burg-Verteidigung','academy' => 25, 'rows' => [
            ['castle_def_infantry_hp', 'castle_def_infantry_atk'],
            ['castle_def_ranged_hp',   'castle_def_ranged_atk'],
            ['castle_def_cavalry_hp',  'castle_def_cavalry_atk'],
        ]],
        ['label' => 'Einzel-Typ-Marsch','academy' => 26, 'rows' => [
            ['infantry_composed_atk'], ['ranged_composed_atk'], ['cavalry_composed_atk'],
        ]],
        ['label' => 'Rally',            'academy' => 27, 'rows' => [
            ['atk_in_rally', 'def_in_rally', 'hp_in_rally', 'troop_spd_in_rally'],
        ]],
    ],
];

// ── Node meta: icon + color per category/stat ────────────────────────────────
function nodeIcon(array $node): string {
    if ($node['type'] === 'unlock') return '🔓';
    return match ($node['stat'] ?? '') {
        'hp'              => '❤',
        'atk', 'attack'   => '⚔',
        'def', 'defense'  => '🛡',
        'spd', 'speed'    => '⚡',
        'storage'         => '📦',
        'march_size'      => '⚔',
        'march_limit'     => '➕',
        'hospital_capacity' => '🏥',
        'healing_time_reduced', 'healing_speed' => '💊',
        'construction_speed' => '🔨',
        'research_speed'  => '📚',
        default           => '🔬',
    };
}

function nodeColor(array $node): string {
    if ($node['type'] === 'unlock') return '#4c1d95';
    return match ($node['category'] ?? '') {
        'infantry'   => '#1e3a8a',
        'ranged'     => '#14532d',
        'cavalry'    => '#7c2d12',
        'general'    => '#78350f',
        'production' => '#0e4f5c',
        'counter', 'castle_defense', 'composed', 'rally' => '#1e1b4b',
        default      => '#1e293b',
    };
}

function nodeBorderColor(array $node): string {
    if ($node['type'] === 'unlock') return '#7c3aed';
    return match ($node['category'] ?? '') {
        'infantry'   => '#3b82f6',
        'ranged'     => '#22c55e',
        'cavalry'    => '#f97316',
        'general'    => '#f59e0b',
        'production' => '#06b6d4',
        default      => '#6366f1',
    };
}

// Preload all node definitions for the view
$allNodes = ResearchData::allNodes();

$fmt = fn(mixed $n): string => number_format((int) $n, 0, '.', ',');
$fmtTime = function(int $sec): string {
    if ($sec < 60) return $sec . 's';
    if ($sec < 3600) return floor($sec / 60) . 'm';
    $h = floor($sec / 3600); $m = floor(($sec % 3600) / 60);
    return $h . 'h' . ($m ? ' ' . $m . 'm' : '');
};
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

        /* ── Scroll area ── */
        .tree-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 1.25rem 2rem;
            scrollbar-width: thin;
            scrollbar-color: #1e3a5f var(--bg);
        }
        .tree-scroll::-webkit-scrollbar { width: 6px; }
        .tree-scroll::-webkit-scrollbar-track { background: var(--bg); }
        .tree-scroll::-webkit-scrollbar-thumb { background: #1e3a5f; border-radius: 3px; }

        /* ── Phase section ── */
        .phase {
            margin-bottom: 1.75rem;
        }
        .phase-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.85rem;
        }
        .phase-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #38bdf8;
        }
        .phase-acad {
            font-size: 0.62rem;
            color: var(--muted);
            background: rgba(30,58,95,.5);
            padding: 0.1rem 0.45rem;
            border-radius: 3px;
            border: 1px solid var(--border);
        }
        .phase-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--border) 0%, transparent 100%);
        }

        .phase-rows {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        /* ── Tree row ── */
        .tree-row {
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: nowrap;
        }

        /* ── Connector ── */
        .connector {
            flex-shrink: 0;
            width: 32px;
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
            border-left: 7px solid #0891b2;
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
        }

        /* ── Node card ── */
        .node-card {
            flex-shrink: 0;
            width: 154px;
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 7px;
            display: flex;
            align-items: stretch;
            gap: 0;
            overflow: hidden;
            cursor: pointer;
            transition: border-color .15s, transform .1s;
            position: relative;
        }
        .node-card:hover { transform: translateY(-1px); }
        .node-card.state-maxed  { border-color: #d97706; opacity: .85; }
        .node-card.state-locked { opacity: .45; cursor: default; }
        .node-card.state-locked:hover { transform: none; }
        .node-card.state-active { border-color: #3b82f6; }
        .node-card.state-selected { border-color: #f0c040 !important; box-shadow: 0 0 0 2px rgba(240,192,64,.25); }

        /* Icon area */
        .node-icon {
            flex-shrink: 0;
            width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            line-height: 1;
        }

        /* Info area */
        .node-info {
            flex: 1;
            padding: 0.35rem 0.45rem 0.35rem 0;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            min-width: 0;
        }
        .node-name {
            font-size: 0.67rem;
            font-weight: 700;
            line-height: 1.25;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Level bar */
        .level-bar {
            height: 14px;
            background: #0a1628;
            border-radius: 3px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.07);
        }
        .level-fill {
            height: 100%;
            background: linear-gradient(90deg, #5b21b6, #7c3aed, #8b5cf6);
            border-radius: 3px;
            transition: width .4s ease;
        }
        .level-fill.maxed {
            background: linear-gradient(90deg, #b45309, #d97706, #f59e0b);
        }
        .level-text {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.58rem;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,.8);
        }

        /* ── Detail panel (shown below selected node row) ── */
        .detail-panel {
            background: #0d2040;
            border: 1px solid #1e4a8f;
            border-radius: 7px;
            padding: 0.85rem 1rem;
            margin-top: 0.5rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.75rem;
            align-items: center;
        }
        .dp-title { font-size: 0.78rem; font-weight: 700; color: var(--gold2); margin-bottom: 0.4rem; }
        .dp-effect { font-size: 0.72rem; color: var(--green); margin-bottom: 0.5rem; }
        .dp-costs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            font-size: 0.68rem;
            color: var(--muted);
        }
        .dp-costs span strong { color: var(--text); }
        .dp-time { font-size: 0.68rem; color: var(--muted); margin-top: 0.3rem; }
        .dp-lock { font-size: 0.72rem; color: #ef4444; }
        .btn-start {
            padding: 0.55rem 1.1rem;
            border-radius: 6px;
            border: 1px solid #2563a8;
            background: #1e4080;
            color: #7ab4e0;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s;
        }
        .btn-start:hover:not(:disabled) { background: #2563a8; color: #e2f0ff; }
        .btn-start:disabled { opacity: .4; cursor: not-allowed; }

        /* ── Buffs tab ── */
        .buffs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
            padding: 1rem 1.25rem;
        }
        .buff-group-title {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--gold);
            margin-bottom: 0.5rem;
        }
        .buff-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.73rem;
            padding: 0.2rem 0;
            border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .buff-row:last-child { border-bottom: none; }
        .buff-label { color: var(--muted); }
        .buff-val { font-weight: 700; }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 7px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 9000;
            pointer-events: none;
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
        <a href="/city" class="topbar-back">← Stadt</a>
        <span class="topbar-title">🔬 Akademie — Forschung</span>
        <div class="topbar-acad">Akademie Lv <strong><?= $academyLevel ?></strong></div>
    </div>

    <!-- Queue banner -->
    <template x-if="queue && !queue.done">
        <div class="queue-banner">
            <div class="qb-dot"></div>
            <span style="color:var(--muted);font-size:.65rem;text-transform:uppercase;font-weight:800">In Forschung</span>
            <span class="qb-name" x-text="queue.name + ' → Lv ' + queue.level_to"></span>
            <span class="qb-eta">⏱ <span x-text="fmtEta(queue.finishes_at)"></span></span>
            <button class="btn-instant" @click="instantFinish()">💎 Sofort</button>
        </div>
    </template>

    <!-- Tab bar -->
    <div class="tab-bar">
        <button class="tab-btn" :class="{active:tab==='battle'}"     @click="tab='battle';selected=null">⚔ Kampf</button>
        <button class="tab-btn" :class="{active:tab==='production'}" @click="tab='production';selected=null">🌾 Produktion</button>
        <button class="tab-btn" :class="{active:tab==='advanced'}"   @click="tab='advanced';selected=null">🔮 Erweitert</button>
        <button class="tab-btn" :class="{active:tab==='buffs'}"      @click="tab='buffs';selected=null">📊 Buffs</button>
    </div>

    <!-- Scrollable tree content -->
    <div class="tree-scroll" x-show="tab !== 'buffs'">

        <?php foreach ($treeLayouts as $treeName => $phases): ?>
        <div x-show="tab === '<?= $treeName ?>'">
            <?php foreach ($phases as $phase): ?>
            <div class="phase">
                <div class="phase-header">
                    <div class="phase-label"><?= htmlspecialchars($phase['label']) ?></div>
                    <div class="phase-acad">Akademie Lv <?= $phase['academy'] ?></div>
                    <div class="phase-line"></div>
                </div>

                <div class="phase-rows">
                    <?php foreach ($phase['rows'] as $row): ?>
                    <div>
                        <div class="tree-row">
                            <?php foreach ($row as $i => $code):
                                $node = $allNodes[$code] ?? null;
                                if ($node === null) continue;
                                $maxLv  = (int) $node['max_level'];
                                $curLv  = $researchLevels[$code] ?? 0;
                                $iconCh = nodeIcon($node);
                                $bgCol  = nodeColor($node);
                                $bdCol  = nodeBorderColor($node);
                            ?>
                            <?php if ($i > 0): ?>
                            <div class="connector"></div>
                            <?php endif ?>

                            <div class="node-card"
                                 :class="nodeClass('<?= $code ?>')"
                                 @click="toggleSelect('<?= $code ?>')"
                                 style="border-color: <?= $bdCol ?>33"
                                 :style="selected === '<?= $code ?>' ? 'border-color:var(--gold2);box-shadow:0 0 0 2px rgba(240,192,64,.2)' : ''">
                                <div class="node-icon" style="background:<?= $bgCol ?>">
                                    <?= $iconCh ?>
                                </div>
                                <div class="node-info">
                                    <div class="node-name"><?= htmlspecialchars($node['name']) ?></div>
                                    <div class="level-bar">
                                        <div class="level-fill"
                                             :class="{'maxed': (research['<?= $code ?>']||0) >= <?= $maxLv ?>}"
                                             :style="{width: ((research['<?= $code ?>']||0) / <?= $maxLv ?> * 100) + '%'}">
                                        </div>
                                        <div class="level-text">
                                            <span x-text="(research['<?= $code ?>']||0) + '/<?= $maxLv ?>'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php endforeach ?>
                        </div>

                        <!-- Detail panel appears below this row when a node in it is selected -->
                        <?php foreach ($row as $code):
                            $node = $allNodes[$code] ?? null;
                            if ($node === null) continue;
                            $maxLv = (int) $node['max_level'];

                            // Precompute level entries for JS
                            $levelsJson = json_encode(
                                array_values(array_map(fn($e) => [
                                    'level'         => (int)$e['level'],
                                    'ability_value' => $e['ability_value'],
                                    'time'          => (int)$e['time'],
                                    'resources'     => $e['resources'],
                                    'requirements'  => $e['requirements'] ?? [],
                                ], $node['levels'])),
                                JSON_THROW_ON_ERROR
                            );
                        ?>
                        <template x-if="selected === '<?= $code ?>'">
                            <div class="detail-panel"
                                 x-data="nodeDetail('<?= $code ?>', <?= $maxLv ?>, <?= $levelsJson ?>)">
                                <div>
                                    <div class="dp-title"><?= htmlspecialchars($node['name']) ?></div>
                                    <template x-if="!isMaxed">
                                        <div>
                                            <div class="dp-effect" x-text="effectText"></div>
                                            <template x-if="canStart">
                                                <div class="dp-costs" x-html="costsHtml"></div>
                                            </template>
                                            <template x-if="!canStart">
                                                <div class="dp-lock" x-text="lockReason"></div>
                                            </template>
                                            <div class="dp-time" x-text="'⏱ ' + timeText"></div>
                                        </div>
                                    </template>
                                    <template x-if="isMaxed">
                                        <div style="color:var(--gold2);font-size:.72rem">✓ Maximal erforscht</div>
                                    </template>
                                </div>
                                <template x-if="!isMaxed">
                                    <button class="btn-start"
                                            :disabled="!canStart || !!$root.queue || $root.loading"
                                            @click="$root.startResearch('<?= $code ?>')">
                                        Erforschen
                                    </button>
                                </template>
                            </div>
                        </template>
                        <?php endforeach ?>

                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <?php endforeach ?>
    </div>

    <!-- Buffs tab -->
    <div class="tree-scroll" x-show="tab === 'buffs'">
        <div class="buffs-grid">
            <?php
            $boostGroups = [
                'Allgemein'    => ['Truppen HP' => 'troops_hp', 'Truppen ATK' => 'troops_atk', 'Truppen DEF' => 'troops_def', 'Truppen SPD' => 'troops_spd'],
                'Infanterie'   => ['HP' => 'infantry_hp', 'ATK' => 'infantry_atk', 'DEF' => 'infantry_def', 'SPD' => 'infantry_spd'],
                'Fernkämpfer'  => ['HP' => 'ranged_hp', 'ATK' => 'ranged_atk', 'DEF' => 'ranged_def', 'SPD' => 'ranged_spd'],
                'Kavallerie'   => ['HP' => 'cavalry_hp', 'ATK' => 'cavalry_atk', 'DEF' => 'cavalry_def', 'SPD' => 'cavalry_spd'],
                'Sonstiges'    => ['Marschgröße' => 'march_size', 'Krankenhaus' => 'hospital_capacity', 'Heilung' => 'healing_speed', 'Bau' => 'construction_speed', 'Forschung' => 'research_speed'],
            ];
            foreach ($boostGroups as $gName => $items):
            ?>
            <div>
                <div class="buff-group-title"><?= $gName ?></div>
                <?php foreach ($items as $label => $key):
                    $raw = $buffs[$key] ?? 0;
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

    <!-- Toast -->
    <template x-if="toast.msg">
        <div class="toast" :class="toast.type" x-text="toast.msg"></div>
    </template>

</div>

<script>
const _research  = <?= json_encode($researchLevels, JSON_THROW_ON_ERROR) ?>;
const _academyLv = <?= $academyLevel ?>;
const _queue     = <?= $queueRow ? json_encode([
    'code'        => $queueRow['research_code'],
    'name'        => $allNodes[$queueRow['research_code']]['name'] ?? $queueRow['research_code'],
    'level_to'    => (int)$queueRow['level_to'],
    'finishes_at' => $queueRow['finishes_at'],
    'done'        => false,
], JSON_THROW_ON_ERROR) : 'null' ?>;

function researchApp() {
    return {
        tab: 'battle',
        selected: null,
        loading: false,
        toast: { msg: '', type: 'ok' },
        tick: 0,
        research: { ..._research },
        academyLevel: _academyLv,
        queue: _queue ? { ..._queue } : null,

        boot() {
            setInterval(() => this.tick++, 1000);
            setInterval(() => this.pollState(), 15000);
        },

        toggleSelect(code) {
            this.selected = this.selected === code ? null : code;
        },

        nodeClass(code) {
            const cur = this.research[code] || 0;
            const max = parseInt(document.querySelector(`[\\@click="toggleSelect('${code}')"] .level-text`)?.textContent?.split('/')[1] || '1');
            if (this.queue && !this.queue.done && this.queue.code === code) return 'state-active';
            return '';
        },

        fmtSec(s) {
            s = parseInt(s);
            if (s < 60)   return s + 's';
            if (s < 3600) return Math.floor(s/60) + 'm ' + (s%60) + 's';
            const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
            return h + 'h ' + (m ? m + 'm' : '');
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
                    headers: {'Content-Type':'application/json'},
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
            setTimeout(() => this.toast = { msg:'', type:'ok' }, 3500);
        },
    };
}

// Per-node detail panel component (injected per PHP-rendered node)
function nodeDetail(code, maxLevel, levels) {
    return {
        code, maxLevel, levels,

        get curLevel()  { return this.$root.research[code] || 0; },
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
            if (!this.entry) return '';
            for (const req of (this.entry.requirements ?? [])) {
                if (req.type === 'academy' && this.$root.academyLevel < req.level)
                    return `Akademie Lv ${req.level} benötigt (aktuell: ${this.$root.academyLevel})`;
                if (req.type === 'research') {
                    if ((this.$root.research[req.code] || 0) < (req.level ?? 1))
                        return `Voraussetzung: ${req.code} Lv ${req.level ?? 1}`;
                }
            }
            return 'Gesperrt';
        },

        get effectText() {
            if (!this.entry) return '';
            const v = this.entry.ability_value;
            if (['march_size','hospital_capacity','march_limit','troops_storage'].includes(code.split('_').slice(-1)[0])) {
                const intV = parseInt(v);
                return `+${intV.toLocaleString('de')} (Lv ${this.nextLevel})`;
            }
            return `+${(v * 100).toFixed(1)}% (Lv ${this.nextLevel})`;
        },

        get timeText() {
            if (!this.entry) return '';
            return this.$root.fmtSec(this.entry.time ?? 0);
        },

        get costsHtml() {
            if (!this.entry) return '';
            const r = this.entry.resources ?? {};
            const fmt = n => parseInt(n).toLocaleString('de');
            return `<span>🌾 <strong>${fmt(r.food??0)}</strong></span>` +
                   `<span>🪵 <strong>${fmt(r.lumber??0)}</strong></span>` +
                   `<span>🪨 <strong>${fmt(r.stone??0)}</strong></span>` +
                   `<span>🪙 <strong>${fmt(r.gold??0)}</strong></span>`;
        },
    };
}
</script>
</body>
</html>
