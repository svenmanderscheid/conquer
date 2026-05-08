<?php
declare(strict_types=1);
/**
 * Research view — /research
 * LoK-style tree with absolute-positioned nodes, SVG connector lines,
 * horizontal scroll, three tabs (Battle / Production / Advanced) + Buffs.
 */

use Conquer\Db\Connection;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchProcessor;

$db       = Connection::getInstance();
$playerId = (int) $session['player_id'];

ResearchProcessor::processQueue($playerId);

$academyRow = $db->query(
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
    "SELECT * FROM research_queue WHERE player_id = ? AND is_processed = 0 ORDER BY id DESC LIMIT 1",
    [$playerId],
)->fetch() ?: null;

$cityRow = $db->query(
    'SELECT food, lumber, stone, gold FROM cities WHERE player_id = ? LIMIT 1',
    [$playerId],
)->fetch() ?: ['food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0];

$buffs = BuffEngine::getBuffs($playerId);

// Load all node definitions indexed by code
$allNodes = [];
foreach (['battle', 'production', 'advanced'] as $treeName) {
    foreach (ResearchData::tree($treeName) as $n) {
        $allNodes[$n['code']] = $n;
    }
}

// ── Grid position arrays ──────────────────────────────────────────────────────

$battleGrid = [
    // Row 0 — Infantry
    'infantry_hp'              => [0,  0],
    'infantry_def'             => [1,  0],
    'infantry_atk'             => [2,  0],
    'infantry_spd'             => [3,  0],
    'warrior'                  => [5,  0],
    'infantry_training_amount' => [6,  0],
    'infantry_training_speed'  => [7,  0],
    'infantry_training_cost'   => [8,  0],
    'knight'                   => [11, 0],
    'troops_hp'                => [13, 0],
    'guardian'                 => [16, 0],
    'advanced_infantry_hp'     => [18, 0],
    'advanced_infantry_def'    => [19, 0],
    'advanced_infantry_atk'    => [20, 0],
    'advanced_infantry_spd'    => [21, 0],
    'crusader'                 => [22, 0],

    // Row 1 — Ranged + shared
    'ranged_hp'                => [0,  1],
    'ranged_def'               => [1,  1],
    'ranged_atk'               => [2,  1],
    'ranged_spd'               => [3,  1],
    'troops_storage'           => [4,  1],
    'longbow_man'              => [5,  1],
    'ranged_training_amount'   => [6,  1],
    'ranged_training_speed'    => [7,  1],
    'ranged_training_cost'     => [8,  1],
    'march_size'               => [9,  1],
    'march_limit'              => [10, 1],
    'ranger'                   => [11, 1],
    'troops_spd'               => [12, 1],
    'troops_atk'               => [13, 1],
    'hospital_capacity'        => [14, 1],
    'healing_time_reduced'     => [15, 1],
    'crossbow_man'             => [16, 1],
    'rally_attack_amount'      => [17, 1],
    'advanced_ranged_hp'       => [18, 1],
    'advanced_ranged_def'      => [19, 1],
    'advanced_ranged_atk'      => [20, 1],
    'advanced_ranged_spd'      => [21, 1],
    'sniper'                   => [22, 1],

    // Row 2 — Cavalry
    'cavalry_hp'               => [0,  2],
    'cavalry_def'              => [1,  2],
    'cavalry_atk'              => [2,  2],
    'cavalry_spd'              => [3,  2],
    'horseman'                 => [5,  2],
    'cavalry_training_amount'  => [6,  2],
    'cavalry_training_speed'   => [7,  2],
    'cavalry_training_cost'    => [8,  2],
    'heavy_cavalry'            => [11, 2],
    'troops_def'               => [13, 2],
    'iron_cavalry'             => [16, 2],
    'advanced_cavalry_hp'      => [18, 2],
    'advanced_cavalry_def'     => [19, 2],
    'advanced_cavalry_atk'     => [20, 2],
    'advanced_cavalry_spd'     => [21, 2],
    'dragoon'                  => [22, 2],
];

$productionGrid = [
    // Row 0 — Food
    'food_production'                  => [0,  0],
    'food_capacity'                    => [1,  0],
    'food_gathering_speed'             => [2,  0],
    'advanced_food_production'         => [11, 0],
    'advanced_food_capacity'           => [12, 0],
    'advanced_food_gathering_speed'    => [13, 0],

    // Row 1 — Wood
    'wood_production'                  => [0,  1],
    'wood_capacity'                    => [1,  1],
    'wood_gathering_speed'             => [2,  1],
    'advanced_wood_production'         => [11, 1],
    'advanced_wood_capacity'           => [12, 1],
    'advanced_wood_gathering_speed'    => [13, 1],

    // Row 2 — Stone
    'stone_production'                 => [0,  2],
    'stone_capacity'                   => [1,  2],
    'stone_gathering_speed'            => [2,  2],
    'advanced_stone_production'        => [11, 2],
    'advanced_stone_capacity'          => [12, 2],
    'advanced_stone_gathering_speed'   => [13, 2],

    // Row 3 — General / shared
    'gold_production'                  => [3,  3],
    'gold_capacity'                    => [4,  3],
    'gold_gathering_speed'             => [5,  3],
    'crystal_gathering_speed'          => [6,  3],
    'infantry_storage'                 => [7,  1],
    'ranged_storage'                   => [7,  2],
    'cavalry_storage'                  => [7,  3],
    'research_speed'                   => [8,  3],
    'construction_speed'               => [9,  3],
    'resource_protect'                 => [10, 3],
    'advanced_gold_production'         => [14, 3],
    'advanced_gold_capacity'           => [15, 3],
    'advanced_gold_gathering_speed'    => [16, 3],
    'advanced_crystal_gathering_speed' => [17, 3],
    'advanced_research_speed'          => [18, 3],
    'advanced_construction_speed'      => [19, 3],
];

$advancedGrid = [
    // Row 0 — Infantry counter / castle / composed / rally
    'infantry_hp_against_archer'                    => [1,  0],
    'infantry_def_against_archer'                   => [2,  0],
    'infantry_atk_against_archer'                   => [3,  0],
    'castle_defending_infantrys_hp'                 => [5,  0],
    'castle_defending_infantrys_def'                => [6,  0],
    'castle_defending_infantrys_atk'                => [7,  0],
    'infantrys_hp_when_composed_of_infantry_only'   => [9,  0],
    'infantrys_def_when_composed_of_infantry_only'  => [10, 0],
    'infantrys_atk_when_composed_of_infantry_only'  => [11, 0],
    'infantrys_hp_when_participating_a_rally'        => [13, 0],
    'infantrys_def_when_participating_a_rally'       => [14, 0],
    'infantrys_atk_when_participating_a_rally'       => [15, 0],

    // Row 1 — Ranged + shared
    'resource_production'                           => [0,  1],
    'archer_hp_against_cavalry'                     => [1,  1],
    'archer_def_against_cavalry'                    => [2,  1],
    'archer_atk_against_cavalry'                    => [3,  1],
    'resource_capacity'                             => [4,  1],
    'castle_defending_archers_hp'                   => [5,  1],
    'castle_defending_archers_def'                  => [6,  1],
    'castle_defending_archers_atk'                  => [7,  1],
    'resource_protect'                              => [8,  1],
    'archers_hp_when_composed_of_archer_only'       => [9,  1],
    'archers_def_when_composed_of_archer_only'      => [10, 1],
    'archers_atk_when_composed_of_archer_only'      => [11, 1],
    'troop_speed_when_participating_a_rally'        => [12, 1],
    'archers_hp_when_participating_a_rally'         => [13, 1],
    'archers_def_when_participating_a_rally'        => [14, 1],
    'archers_atk_when_participating_a_rally'        => [15, 1],

    // Row 2 — Cavalry
    'cavalry_hp_against_infantry'                   => [1,  2],
    'cavalry_def_against_infantry'                  => [2,  2],
    'cavalry_atk_against_infantry'                  => [3,  2],
    'castle_defending_cavalrys_hp'                  => [5,  2],
    'castle_defending_cavalrys_def'                 => [6,  2],
    'castle_defending_cavalrys_atk'                 => [7,  2],
    'cavalrys_hp_when_composed_of_cavalry_only'     => [9,  2],
    'cavalrys_def_when_composed_of_cavalry_only'    => [10, 2],
    'cavalrys_atk_when_composed_of_cavalry_only'    => [11, 2],
    'cavalrys_hp_when_participating_a_rally'        => [13, 2],
    'cavalrys_def_when_participating_a_rally'       => [14, 2],
    'cavalrys_atk_when_participating_a_rally'       => [15, 2],
];

// ── Build connections from level-1 requirements ───────────────────────────────

/**
 * Build edges: [from_code, to_code] pairs where 'to' has 'from' as research prerequisite.
 */
function buildConnections(array $grid, array $allNodes): array
{
    $connections = [];
    foreach (array_keys($grid) as $code) {
        $node = $allNodes[$code] ?? null;
        if (!$node) {
            continue;
        }
        foreach ($node['levels'][0]['requirements'] ?? [] as $req) {
            if ($req['type'] === 'research' && isset($req['code']) && isset($grid[$req['code']])) {
                $connections[] = [$req['code'], $code];
            }
        }
    }
    return $connections;
}

$battleConnections     = buildConnections($battleGrid, $allNodes);
$productionConnections = buildConnections($productionGrid, $allNodes);
$advancedConnections   = buildConnections($advancedGrid, $allNodes);

// ── PHP helper functions ──────────────────────────────────────────────────────

/**
 * Returns bg/border/text color triplet for a node card.
 *
 * @return array{bg:string,border:string,text:string}
 */
function nodeColor(array $node): array
{
    $cat  = $node['category'] ?? '';
    $type = $node['type'] ?? 'buff';
    if ($type === 'unlock') {
        return ['bg' => '#2e1065', 'border' => '#a855f7', 'text' => '#d8b4fe'];
    }
    return match (true) {
        str_starts_with($cat, 'infantry') => ['bg' => '#1e3a5f', 'border' => '#3b82f6', 'text' => '#93c5fd'],
        str_starts_with($cat, 'ranged')   => ['bg' => '#14532d', 'border' => '#22c55e', 'text' => '#86efac'],
        str_starts_with($cat, 'cavalry')  => ['bg' => '#451a03', 'border' => '#f59e0b', 'text' => '#fcd34d'],
        $cat === 'production'             => ['bg' => '#1a3a2a', 'border' => '#10b981', 'text' => '#6ee7b7'],
        $cat === 'counter'                => ['bg' => '#3b1515', 'border' => '#ef4444', 'text' => '#fca5a5'],
        $cat === 'castle_defense'         => ['bg' => '#1e1b4b', 'border' => '#6366f1', 'text' => '#a5b4fc'],
        $cat === 'composed'               => ['bg' => '#1c1917', 'border' => '#78716c', 'text' => '#d6d3d1'],
        $cat === 'rally'                  => ['bg' => '#1a1a2e', 'border' => '#e879f9', 'text' => '#f0abfc'],
        default                           => ['bg' => '#1e293b', 'border' => '#64748b', 'text' => '#94a3b8'],
    };
}

/**
 * Returns an HTML entity icon for a node.
 */
function nodeIcon(array $node): string
{
    $type = $node['type'] ?? 'buff';
    if ($type === 'unlock') {
        return '&#x1F513;'; // 🔓
    }
    $stat = $node['stat'] ?? '';
    $cat  = $node['category'] ?? '';
    if (str_contains($stat, 'hp'))  {
        return '&#x2764;&#xFE0F;'; // ❤️
    }
    if (str_contains($stat, 'atk')) {
        return '&#x2694;&#xFE0F;'; // ⚔️
    }
    if (str_contains($stat, 'def')) {
        return '&#x1F6E1;&#xFE0F;'; // 🛡️
    }
    if (str_contains($stat, 'spd')) {
        return '&#x1F4A8;'; // 💨
    }
    if ($cat === 'production')      {
        return '&#x2699;&#xFE0F;'; // ⚙️
    }
    if ($cat === 'counter')         {
        return '&#x1F3AF;'; // 🎯
    }
    if ($cat === 'castle_defense')  {
        return '&#x1F3F0;'; // 🏰
    }
    if ($cat === 'rally')           {
        return '&#x1F6A9;'; // 🚩
    }
    if ($cat === 'composed')        {
        return '&#x26A1;'; // ⚡
    }
    return '&#x1F4CA;'; // 📊
}

/**
 * Shorten very long node names so they fit in a 190px card.
 */
function shortName(string $name): string
{
    return str_replace(
        [
            ' When Composed Of Infantry Only',
            ' When Composed Of Archer Only',
            ' When Composed Of Cavalry Only',
            ' When Participating A Rally',
            'Castle Defending ',
            ' Against Archer',
            ' Against Cavalry',
            ' Against Infantry',
            'Advanced ',
        ],
        [
            ' (Mono)',
            ' (Mono)',
            ' (Mono)',
            ' (Rally)',
            '',
            ' vs Archer',
            ' vs Cavalry',
            ' vs Inf.',
            'Adv. ',
        ],
        $name
    );
}

/**
 * Determine whether a node is locked for the current player
 * (only checks level-1 / first-level Academy requirement).
 */
function isNodeLocked(string $code, array $allNodes, int $academyLevel): bool
{
    $node = $allNodes[$code] ?? null;
    if (!$node) {
        return false;
    }
    foreach ($node['levels'][0]['requirements'] ?? [] as $req) {
        if ($req['type'] === 'academy' && $academyLevel < (int) $req['level']) {
            return true;
        }
    }
    return false;
}

// ── Canvas dimension constants ────────────────────────────────────────────────
// NODE_W=190, NODE_H=70, COL_W=260, ROW_H=110
// Battle:  maxCol=22, maxRow=2  → width=22*260+190=5910,  height=2*110+70=290
// Production: maxCol=19, maxRow=3 → width=19*260+190=5130, height=3*110+70=400
// Advanced: maxCol=15, maxRow=2  → width=15*260+190=4090, height=2*110+70=290

/**
 * Render all node-card divs for a grid array.
 * Returns HTML string (called inside heredoc sections).
 */
function renderNodes(array $grid, array $allNodes, array $researchLevels, ?array $queueRow, int $academyLevel): string
{
    $html = '';
    foreach ($grid as $code => [$col, $row]) {
        $node = $allNodes[$code] ?? null;
        if (!$node) {
            continue;
        }
        $maxLv   = (int) $node['max_level'];
        $curLv   = $researchLevels[$code] ?? 0;
        $pct     = $maxLv > 0 ? round($curLv / $maxLv * 100) : 0;
        $isMaxed = $curLv >= $maxLv;
        $isActive = $queueRow && $queueRow['research_code'] === $code;
        $isLocked = isNodeLocked($code, $allNodes, $academyLevel);
        $color    = nodeColor($node);
        $icon     = nodeIcon($node);
        $left     = $col * 260;
        $top      = $row * 110;
        $name     = htmlspecialchars(shortName($node['name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $codeEsc  = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $classes = 'node-card';
        if ($isLocked) {
            $classes .= ' locked';
        } elseif ($isMaxed) {
            $classes .= ' maxed';
        }

        $fillClass = $isMaxed ? 'nc-fill maxed' : 'nc-fill';

        $activeDot = $isActive
            ? '<div class="nc-active-dot"></div>'
            : '';

        $html .= <<<HTML
<div class="{$classes}" data-code="{$codeEsc}" style="left:{$left}px;top:{$top}px" onclick="selectNode('{$codeEsc}')">
    <div class="nc-icon" style="background:{$color['bg']};border-color:{$color['border']}">{$icon}</div>
    <div class="nc-right">
        <div class="nc-name">{$name}</div>
        <div class="nc-bar">
            <div class="{$fillClass}" style="width:{$pct}%"></div>
            <span class="nc-bar-text">{$curLv}/{$maxLv}</span>
        </div>
    </div>
    {$activeDot}
</div>
HTML;
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Forschung</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            overflow: hidden;
            background: #0f172a;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, sans-serif;
        }

        #game {
            height: calc(100% - 72px);
            margin-top: 72px;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ── */
        .topbar {
            flex-shrink: 0;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 0 1rem;
            height: 44px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .topbar-back {
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            background: rgba(255,255,255,.05);
            border: 1px solid #334155;
            color: #64748b;
            text-decoration: none;
            font-size: 0.75rem;
        }
        .topbar-back:hover { color: #f0c040; border-color: #d4a017; }
        .topbar-title { font-weight: 700; color: #fbbf24; font-size: 0.88rem; }
        .topbar-acad  { margin-left: auto; font-size: 0.73rem; color: #64748b; }
        .topbar-acad strong { color: #e2e8f0; }

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
        .qb-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #3b82f6;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.35} }
        .qb-name { color: #fbbf24; font-weight: 700; }
        .qb-eta  { color: #64748b; margin-left: auto; }
        .btn-instant-banner {
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            border: 1px solid rgba(139,92,246,.5);
            background: rgba(139,92,246,.1);
            color: #c4b5fd;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-instant-banner:hover { background: rgba(139,92,246,.25); }

        /* ── Tab bar ── */
        .tab-bar {
            flex-shrink: 0;
            display: flex;
            background: #1e293b;
            border-bottom: 2px solid #334155;
        }
        .tab-btn {
            padding: 10px 24px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            border: none;
            background: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .15s;
        }
        .tab-btn:hover { color: #e2e8f0; }
        .tab-btn.active { color: #e2e8f0; border-bottom-color: #0ea5e9; }

        /* ── Tree scroll area ── */
        .tree-wrap {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .tree-scroll {
            flex: 1;
            overflow-x: auto;
            overflow-y: auto;
            padding: 24px;
            scrollbar-width: thin;
            scrollbar-color: #334155 #0f172a;
        }
        .tree-scroll::-webkit-scrollbar { height: 8px; width: 8px; }
        .tree-scroll::-webkit-scrollbar-track { background: #0f172a; }
        .tree-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .tree-canvas {
            position: relative;
        }
        .tree-svg {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Node cards ── */
        .node-card {
            position: absolute;
            width: 190px;
            height: 70px;
            background: #1a2740;
            border: 2px solid #2d4060;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            cursor: pointer;
            z-index: 1;
            transition: border-color .15s, box-shadow .15s;
        }
        .node-card:hover {
            border-color: #f59e0b;
            box-shadow: 0 0 8px rgba(245,158,11,.3);
        }
        .node-card.selected {
            border-color: #f59e0b !important;
            box-shadow: 0 0 12px rgba(245,158,11,.4);
        }
        .node-card.locked { opacity: .42; }
        .node-card.locked:hover { border-color: #2d4060; box-shadow: none; cursor: default; }
        .node-card.maxed .nc-bar-text { color: #fbbf24; }

        .nc-icon {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            border-radius: 6px;
            border: 2px solid #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .nc-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .nc-name {
            font-size: 0.65rem;
            color: #e2e8f0;
            font-weight: 600;
            line-height: 1.2;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .nc-bar {
            position: relative;
            height: 16px;
            background: #0f1e30;
            border-radius: 3px;
            border: 1px solid #2d4060;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nc-fill {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            background: #3b82f6;
            border-radius: 2px;
            transition: width .3s;
        }
        .nc-fill.maxed {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }
        .nc-bar-text {
            position: relative;
            font-size: 0.6rem;
            color: #94a3b8;
            z-index: 1;
        }
        .nc-active-dot {
            position: absolute;
            top: 4px; right: 4px;
            width: 8px; height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse-dot 1s infinite;
        }

        /* ── Detail panel ── */
        .detail-panel {
            flex-shrink: 0;
            background: #1e293b;
            border-top: 2px solid #334155;
            padding: 12px 16px;
            display: flex;
            gap: 16px;
            align-items: center;
            min-height: 100px;
            max-height: 140px;
        }
        .dp-header {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 180px;
        }
        .dp-name  { font-size: 0.92rem; font-weight: 700; color: #fbbf24; }
        .dp-level { font-size: 0.75rem; color: #94a3b8; }
        .dp-close {
            background: none; border: none;
            color: #64748b; cursor: pointer;
            font-size: 1.1rem; align-self: flex-start;
            margin-top: 2px;
        }
        .dp-close:hover { color: #ef4444; }
        .dp-body {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            font-size: 0.75rem;
            color: #94a3b8;
            align-items: center;
        }
        .dp-effect { color: #22c55e; font-weight: 600; }
        .dp-lock   { color: #ef4444; }
        .dp-actions { display: flex; flex-direction: column; gap: 6px; }
        .dp-btn-start {
            padding: 8px 18px;
            background: #0ea5e9;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.82rem;
            white-space: nowrap;
        }
        .dp-btn-start:hover:not(:disabled) { background: #0284c7; }
        .dp-btn-start:disabled { background: #1e3a4f; color: #64748b; cursor: default; }
        .dp-btn-instant {
            padding: 8px 14px;
            background: #7c3aed;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.82rem;
            white-space: nowrap;
        }
        .dp-btn-instant:hover:not(:disabled) { background: #6d28d9; }
        .dp-btn-instant:disabled { background: #2d1b69; color: #64748b; cursor: default; }

        /* ── Buffs tab ── */
        .buffs-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
            scrollbar-width: thin;
            scrollbar-color: #334155 #0f172a;
        }
        .buffs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .buff-group-title {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #d4a017;
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
        .buff-label { color: #64748b; }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 1.5rem; right: 1.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 7px;
            font-size: 0.8rem; font-weight: 600;
            z-index: 9000; pointer-events: none;
            display: none;
        }
        .toast.ok  { background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.4); color:#22c55e; }
        .toast.err { background: rgba(239,68,68,.12);  border:1px solid rgba(239,68,68,.4);  color:#ef4444; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>

<div id="game">

    <!-- Top bar -->
    <div class="topbar">
        <a href="/city" class="topbar-back">&larr; Stadt</a>
        <span class="topbar-title">&#x1F52C; Akademie &mdash; Forschung</span>
        <div class="topbar-acad">Akademie Lv <strong><?= $academyLevel ?></strong></div>
    </div>

    <!-- Queue banner (hidden when no queue) -->
    <div class="queue-banner" id="queue-banner" style="<?= $queueRow ? '' : 'display:none' ?>">
        <div class="qb-dot"></div>
        <span style="color:#64748b;font-size:.65rem;text-transform:uppercase;font-weight:800">In Forschung</span>
        <span class="qb-name" id="qb-name"><?= $queueRow ? htmlspecialchars($allNodes[$queueRow['research_code']]['name'] ?? $queueRow['research_code']) . ' &rarr; Lv ' . (int)$queueRow['level_to'] : '' ?></span>
        <span class="qb-eta" id="qb-eta">&#x23F1; &hellip;</span>
        <button class="btn-instant-banner" onclick="instantFinishBanner()">&#x1F48E; Sofort</button>
    </div>

    <!-- Tab bar -->
    <div class="tab-bar">
        <button class="tab-btn active" data-tab="battle"     onclick="switchTab('battle')">&#x2694; Kampf</button>
        <button class="tab-btn"        data-tab="production" onclick="switchTab('production')">&#x1F33E; Produktion</button>
        <button class="tab-btn"        data-tab="advanced"   onclick="switchTab('advanced')">&#x1F52E; Erweitert</button>
        <button class="tab-btn"        data-tab="buffs"      onclick="switchTab('buffs')">&#x1F4CA; Buffs</button>
    </div>

    <!-- ── BATTLE TAB ── -->
    <div class="tree-wrap" id="tree-battle">
        <div class="tree-scroll">
            <div class="tree-canvas" id="canvas-battle"
                 style="width:5910px;height:290px">
                <svg class="tree-svg" id="svg-battle" width="5910" height="290"></svg>
                <?= renderNodes($battleGrid, $allNodes, $researchLevels, $queueRow, $academyLevel) ?>
            </div>
        </div>

        <!-- Detail panel for battle tab -->
        <div class="detail-panel" id="detail-panel-battle" style="display:none">
            <div class="dp-header">
                <span class="dp-name" id="dp-name-battle">—</span>
                <span class="dp-level" id="dp-level-battle">Lv. 0/0</span>
                <button class="dp-close" onclick="closeDetail()">&#x2715;</button>
            </div>
            <div class="dp-body">
                <div class="dp-effect" id="dp-effect-battle"></div>
                <div id="dp-costs-battle" style="font-size:.72rem;color:#94a3b8"></div>
                <div id="dp-time-battle" style="font-size:.72rem;color:#64748b"></div>
                <div class="dp-lock" id="dp-lock-battle"></div>
            </div>
            <div class="dp-actions">
                <button class="dp-btn-start" id="dp-btn-start-battle" disabled>Erforschen</button>
                <button class="dp-btn-instant" id="dp-btn-instant-battle" disabled>Sofort (? &#x1F48E;)</button>
            </div>
        </div>
    </div>

    <!-- ── PRODUCTION TAB ── -->
    <div class="tree-wrap" id="tree-production" style="display:none">
        <div class="tree-scroll">
            <div class="tree-canvas" id="canvas-production"
                 style="width:5130px;height:400px">
                <svg class="tree-svg" id="svg-production" width="5130" height="400"></svg>
                <?= renderNodes($productionGrid, $allNodes, $researchLevels, $queueRow, $academyLevel) ?>
            </div>
        </div>

        <div class="detail-panel" id="detail-panel-production" style="display:none">
            <div class="dp-header">
                <span class="dp-name" id="dp-name-production">—</span>
                <span class="dp-level" id="dp-level-production">Lv. 0/0</span>
                <button class="dp-close" onclick="closeDetail()">&#x2715;</button>
            </div>
            <div class="dp-body">
                <div class="dp-effect" id="dp-effect-production"></div>
                <div id="dp-costs-production" style="font-size:.72rem;color:#94a3b8"></div>
                <div id="dp-time-production" style="font-size:.72rem;color:#64748b"></div>
                <div class="dp-lock" id="dp-lock-production"></div>
            </div>
            <div class="dp-actions">
                <button class="dp-btn-start" id="dp-btn-start-production" disabled>Erforschen</button>
                <button class="dp-btn-instant" id="dp-btn-instant-production" disabled>Sofort (? &#x1F48E;)</button>
            </div>
        </div>
    </div>

    <!-- ── ADVANCED TAB ── -->
    <div class="tree-wrap" id="tree-advanced" style="display:none">
        <div class="tree-scroll">
            <div class="tree-canvas" id="canvas-advanced"
                 style="width:4090px;height:290px">
                <svg class="tree-svg" id="svg-advanced" width="4090" height="290"></svg>
                <?= renderNodes($advancedGrid, $allNodes, $researchLevels, $queueRow, $academyLevel) ?>
            </div>
        </div>

        <div class="detail-panel" id="detail-panel-advanced" style="display:none">
            <div class="dp-header">
                <span class="dp-name" id="dp-name-advanced">—</span>
                <span class="dp-level" id="dp-level-advanced">Lv. 0/0</span>
                <button class="dp-close" onclick="closeDetail()">&#x2715;</button>
            </div>
            <div class="dp-body">
                <div class="dp-effect" id="dp-effect-advanced"></div>
                <div id="dp-costs-advanced" style="font-size:.72rem;color:#94a3b8"></div>
                <div id="dp-time-advanced" style="font-size:.72rem;color:#64748b"></div>
                <div class="dp-lock" id="dp-lock-advanced"></div>
            </div>
            <div class="dp-actions">
                <button class="dp-btn-start" id="dp-btn-start-advanced" disabled>Erforschen</button>
                <button class="dp-btn-instant" id="dp-btn-instant-advanced" disabled>Sofort (? &#x1F48E;)</button>
            </div>
        </div>
    </div>

    <!-- ── BUFFS TAB ── -->
    <div class="tree-wrap" id="tree-buffs" style="display:none">
        <div class="buffs-scroll">
            <div class="buffs-grid">
                <?php
                $boostGroups = [
                    'Allgemein'   => ['Truppen HP' => 'troops_hp', 'Truppen ATK' => 'troops_atk', 'Truppen DEF' => 'troops_def', 'Truppen SPD' => 'troops_spd'],
                    'Infanterie'  => ['HP' => 'infantry_hp', 'ATK' => 'infantry_atk', 'DEF' => 'infantry_def', 'SPD' => 'infantry_spd'],
                    'Fernkämpfer' => ['HP' => 'ranged_hp', 'ATK' => 'ranged_atk', 'DEF' => 'ranged_def', 'SPD' => 'ranged_spd'],
                    'Kavallerie'  => ['HP' => 'cavalry_hp', 'ATK' => 'cavalry_atk', 'DEF' => 'cavalry_def', 'SPD' => 'cavalry_spd'],
                    'Sonstiges'   => [
                        'Marschgröße'    => 'march_size',
                        'Krankenhaus'    => 'hospital_capacity',
                        'Heilung'        => 'healing_time_reduced',
                        'Baugeschw.'     => 'construction_speed',
                        'Forschungsgeschw.' => 'research_speed',
                    ],
                ];
                foreach ($boostGroups as $gName => $items):
                ?>
                <div>
                    <div class="buff-group-title"><?= htmlspecialchars($gName) ?></div>
                    <?php foreach ($items as $label => $key):
                        $raw    = $buffs[$key] ?? 0;
                        $isFlat = in_array($key, ['march_size', 'hospital_capacity'], true);
                        $disp   = $isFlat ? '+' . (int)$raw : '+' . round((float)$raw * 100, 1) . '%';
                        $active = $raw > 0;
                    ?>
                    <div class="buff-row">
                        <span class="buff-label"><?= htmlspecialchars($label) ?></span>
                        <span class="buff-val" style="font-weight:700;color:<?= $active ? '#22c55e' : '#64748b' ?>"><?= $disp ?></span>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>

</div><!-- #game -->

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── Data injected from PHP ────────────────────────────────────────────────────
const RES_DATA = <?= json_encode([
    'research'     => $researchLevels,
    'queue'        => $queueRow ? [
        'code'        => $queueRow['research_code'],
        'name'        => $allNodes[$queueRow['research_code']]['name'] ?? $queueRow['research_code'],
        'level_to'    => (int)$queueRow['level_to'],
        'finishes_at' => $queueRow['finishes_at'],
    ] : null,
    'buffs'        => $buffs,
    'academyLevel' => $academyLevel,
    'city'         => $cityRow,
    'csrf'         => $session['csrf_token'],
    'nodes'        => array_map(fn($n) => [
        'code'      => $n['code'],
        'name'      => $n['name'],
        'max_level' => (int)$n['max_level'],
        'type'      => $n['type'],
        'stat'      => $n['stat'] ?? '',
        'category'  => $n['category'] ?? '',
        'levels'    => array_map(fn($l) => [
            'level'         => (int)$l['level'],
            'ability_value' => $l['ability_value'],
            'time'          => (int)$l['time'],
            'resources'     => $l['resources'],
            'requirements'  => $l['requirements'] ?? [],
        ], $n['levels']),
    ], $allNodes),
], JSON_THROW_ON_ERROR) ?>;

const BATTLE_CONNECTIONS     = <?= json_encode($battleConnections, JSON_THROW_ON_ERROR) ?>;
const PRODUCTION_CONNECTIONS = <?= json_encode($productionConnections, JSON_THROW_ON_ERROR) ?>;
const ADVANCED_CONNECTIONS   = <?= json_encode($advancedConnections, JSON_THROW_ON_ERROR) ?>;

// ── Live state (mutable) ─────────────────────────────────────────────────────
let research     = Object.assign({}, RES_DATA.research);
let queue        = RES_DATA.queue ? Object.assign({}, RES_DATA.queue) : null;
let academyLevel = RES_DATA.academyLevel;
let selectedCode = null;
let activeTab    = 'battle';

// ── SVG connection drawing ────────────────────────────────────────────────────
function drawConnections(canvasId, connections) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const svg = canvas.querySelector('.tree-svg');
    svg.innerHTML = '';

    connections.forEach(([from, to]) => {
        const fromEl = canvas.querySelector(`[data-code="${from}"]`);
        const toEl   = canvas.querySelector(`[data-code="${to}"]`);
        if (!fromEl || !toEl) return;

        const fx = fromEl.offsetLeft + fromEl.offsetWidth;
        const fy = fromEl.offsetTop  + fromEl.offsetHeight / 2;
        const tx = toEl.offsetLeft;
        const ty = toEl.offsetTop   + toEl.offsetHeight / 2;

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        let d;
        if (Math.abs(fy - ty) < 4) {
            // Same row — straight horizontal line
            d = `M${fx},${fy} H${tx}`;
        } else {
            // Different rows — L-shaped path via midpoint
            const mx = fx + (tx - fx) / 2;
            d = `M${fx},${fy} H${mx} V${ty} H${tx}`;
        }
        path.setAttribute('d', d);
        path.setAttribute('stroke', '#3a5580');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        svg.appendChild(path);
    });
}

function getConnectionsForTab(tab) {
    if (tab === 'battle')     return BATTLE_CONNECTIONS;
    if (tab === 'production') return PRODUCTION_CONNECTIONS;
    return ADVANCED_CONNECTIONS;
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.tab === tab)
    );
    document.querySelectorAll('.tree-wrap').forEach(w =>
        w.style.display = (w.id === 'tree-' + tab) ? '' : 'none'
    );
    closeDetail();
    if (tab !== 'buffs') {
        requestAnimationFrame(() => drawConnections('canvas-' + tab, getConnectionsForTab(tab)));
    }
}

// ── Node selection ────────────────────────────────────────────────────────────
function selectNode(code) {
    if (selectedCode === code) {
        closeDetail();
        return;
    }
    selectedCode = code;
    document.querySelectorAll('.node-card').forEach(el =>
        el.classList.toggle('selected', el.dataset.code === code)
    );
    renderDetailPanel(code);
}

function closeDetail() {
    selectedCode = null;
    document.querySelectorAll('.node-card').forEach(el => el.classList.remove('selected'));
    ['battle', 'production', 'advanced'].forEach(t => {
        const dp = document.getElementById('detail-panel-' + t);
        if (dp) dp.style.display = 'none';
    });
}

// ── Detail panel ──────────────────────────────────────────────────────────────
function renderDetailPanel(code) {
    const node = RES_DATA.nodes[code];
    if (!node) return;

    const curLv   = research[code] ?? 0;
    const maxLv   = node.max_level;
    const nextLv  = curLv + 1;
    const isMaxed = curLv >= maxLv;
    const entry   = node.levels.find(l => l.level === nextLv) ?? null;

    // Determine which tab panel is active
    const t = activeTab;
    if (t === 'buffs') return;

    const dpEl     = document.getElementById('detail-panel-' + t);
    const nameEl   = document.getElementById('dp-name-' + t);
    const levelEl  = document.getElementById('dp-level-' + t);
    const effectEl = document.getElementById('dp-effect-' + t);
    const costsEl  = document.getElementById('dp-costs-' + t);
    const timeEl   = document.getElementById('dp-time-' + t);
    const lockEl   = document.getElementById('dp-lock-' + t);
    const btnStart = document.getElementById('dp-btn-start-' + t);
    const btnInst  = document.getElementById('dp-btn-instant-' + t);

    nameEl.textContent  = node.name;
    levelEl.textContent = `Lv. ${curLv} / ${maxLv}`;

    if (isMaxed) {
        effectEl.textContent = '✓ Maximal erforscht';
        costsEl.textContent  = '';
        timeEl.textContent   = '';
        lockEl.textContent   = '';
        btnStart.disabled    = true;
        btnInst.disabled     = true;
    } else {
        // Effect text
        if (entry) {
            const v = parseFloat(entry.ability_value);
            const flatStats = ['march_size', 'hospital_capacity', 'troops_storage',
                               'infantry_storage', 'ranged_storage', 'cavalry_storage'];
            if (node.type === 'unlock') {
                effectEl.textContent = `Schaltet Einheit frei (Lv ${nextLv})`;
            } else if (flatStats.includes(node.stat) || flatStats.includes(code)) {
                effectEl.textContent = `+${Math.round(v).toLocaleString('de')} (Lv ${nextLv})`;
            } else {
                effectEl.textContent = `+${(v * 100).toFixed(1)}% (Lv ${nextLv})`;
            }
        } else {
            effectEl.textContent = '';
        }

        // Costs
        if (entry) {
            const r = entry.resources ?? {};
            const fmt = n => Number(n).toLocaleString('de');
            costsEl.innerHTML =
                `&#x1F33E;&thinsp;${fmt(r.food ?? 0)} &nbsp;` +
                `&#x1FAB5;&thinsp;${fmt(r.lumber ?? 0)} &nbsp;` +
                `&#x1FAA8;&thinsp;${fmt(r.stone ?? 0)} &nbsp;` +
                `&#x1FA99;&thinsp;${fmt(r.gold ?? 0)}`;
            timeEl.textContent = '⏱ ' + fmtSec(entry.time ?? 0);
        } else {
            costsEl.textContent = '';
            timeEl.textContent  = '';
        }

        // Lock reason
        const lockReason = getLockReason(code, nextLv, node);
        lockEl.textContent = lockReason;

        // Buttons
        const inQueue  = queue !== null;
        const canStart = !lockReason && !inQueue && entry !== null;
        btnStart.disabled = !canStart;
        btnStart.onclick  = canStart ? () => startResearch(code, nextLv) : null;

        if (inQueue && queue.code === code) {
            const secsLeft = Math.max(0, Math.floor((new Date(queue.finishes_at.replace(' ', 'T') + 'Z') - Date.now()) / 1000));
            const gemCost  = Math.max(1, Math.ceil(secsLeft / 60));
            btnInst.disabled    = false;
            btnInst.textContent = `Sofort (${gemCost} 💎)`;
            btnInst.onclick     = () => instantFinish();
        } else {
            btnInst.disabled    = true;
            btnInst.textContent = 'Sofort (? 💎)';
            btnInst.onclick     = null;
        }
    }

    dpEl.style.display = 'flex';
}

function getLockReason(code, nextLv, node) {
    const entry = node.levels.find(l => l.level === nextLv);
    if (!entry) return '';
    for (const req of entry.requirements ?? []) {
        if (req.type === 'academy' && academyLevel < req.level) {
            return `Akademie Lv ${req.level} benötigt (aktuell: ${academyLevel})`;
        }
        if (req.type === 'research') {
            const needed = req.level ?? 1;
            if ((research[req.code] ?? 0) < needed) {
                const preName = RES_DATA.nodes[req.code]?.name ?? req.code;
                return `Voraussetzung: ${preName} Lv ${needed}`;
            }
        }
    }
    return '';
}

// ── Queue ETA countdown ───────────────────────────────────────────────────────
function fmtSec(s) {
    s = parseInt(s);
    if (s < 60)   return s + 's';
    if (s < 3600) return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
    return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
}

function updateQueueEta() {
    if (!queue) return;
    const diff = Math.max(0, Math.floor(
        (new Date(queue.finishes_at.replace(' ', 'T') + 'Z') - Date.now()) / 1000
    ));
    const etaEl = document.getElementById('qb-eta');
    if (etaEl) etaEl.textContent = '⏱ ' + (diff === 0 ? 'Fertig!' : fmtSec(diff));
    if (diff === 0) {
        setTimeout(() => pollState(), 1500);
    }
}

setInterval(updateQueueEta, 1000);
setInterval(() => pollState(), 30000);

// ── API calls ─────────────────────────────────────────────────────────────────
async function startResearch(code, levelTo) {
    try {
        const r = await fetch('/api/research/start', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': RES_DATA.csrf },
            body:    JSON.stringify({ code, level_to: levelTo }),
        });
        const j = await r.json();
        if (j.ok) {
            queue = j.data.queue ?? null;
            showToast('Forschung gestartet!', 'ok');
            // Update banner
            updateBanner();
            // Re-render detail panel
            if (selectedCode) renderDetailPanel(selectedCode);
        } else {
            showToast(j.message ?? j.error ?? 'Fehler', 'err');
        }
    } catch (e) {
        showToast('Netzwerkfehler: ' + e.message, 'err');
    }
}

async function instantFinish() {
    try {
        const r = await fetch('/api/research/instant', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': RES_DATA.csrf },
        });
        const j = await r.json();
        if (j.ok) {
            window.location.reload();
        } else {
            showToast(j.message ?? j.error ?? 'Fehler', 'err');
        }
    } catch (e) {
        showToast('Netzwerkfehler: ' + e.message, 'err');
    }
}

function instantFinishBanner() {
    instantFinish();
}

async function pollState() {
    try {
        const r = await fetch('/api/research/state');
        const j = await r.json();
        if (!j.ok) return;
        research     = j.data.research ?? research;
        queue        = j.data.queue    ?? null;
        academyLevel = j.data.academy_level ?? academyLevel;
        updateBanner();
        // Update node card levels in DOM
        refreshNodeCards();
        if (selectedCode) renderDetailPanel(selectedCode);
    } catch {}
}

function updateBanner() {
    const banner = document.getElementById('queue-banner');
    const nameEl = document.getElementById('qb-name');
    if (!banner) return;
    if (queue) {
        banner.style.display = '';
        if (nameEl) {
            nameEl.textContent = (queue.name ?? queue.code) + ' → Lv ' + queue.level_to;
        }
    } else {
        banner.style.display = 'none';
    }
}

function refreshNodeCards() {
    document.querySelectorAll('.node-card[data-code]').forEach(card => {
        const code  = card.dataset.code;
        const node  = RES_DATA.nodes[code];
        if (!node) return;
        const curLv = research[code] ?? 0;
        const maxLv = node.max_level;
        const pct   = maxLv > 0 ? Math.round(curLv / maxLv * 100) : 0;

        const fill = card.querySelector('.nc-fill');
        const text = card.querySelector('.nc-bar-text');
        if (fill) {
            fill.style.width = pct + '%';
            fill.classList.toggle('maxed', curLv >= maxLv);
        }
        if (text) text.textContent = curLv + '/' + maxLv;

        card.classList.toggle('maxed', curLv >= maxLv);
    });
}

// ── Toast ─────────────────────────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg, type) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className   = 'toast ' + type;
    el.style.display = 'block';
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.style.display = 'none'; }, 3500);
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Draw initial SVG connections for battle tab
    drawConnections('canvas-battle', BATTLE_CONNECTIONS);
    // Start ETA display
    updateQueueEta();
});
</script>
</body>
</html>
