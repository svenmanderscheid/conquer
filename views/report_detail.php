<?php
declare(strict_types=1);
/**
 * Battle Report Detail — /reports/:id
 * Variables: $session, $reportId (from index.php)
 */

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Research\BuffEngine;

$db       = Connection::getInstance();
$playerId = (int) $session['player_id'];

$row = $db->query(
    'SELECT * FROM battle_reports WHERE id = ? AND attacker_id = ?',
    [$reportId, $playerId],
)->fetch();

if ($row === false) {
    header('Location: /reports');
    exit;
}

// Mark as read
if (!(int) $row['attacker_read']) {
    $db->execute('UPDATE battle_reports SET attacker_read = 1 WHERE id = ?', [$reportId]);
}

$isEmbed = isset($_GET['embed']);
$data    = json_decode($row['data_json'], true) ?? [];
$outcome = $row['outcome'];
$troops  = $data['troops'] ?? [];

// ── Player / city stats ───────────────────────────────────────────────────────
$playerStats = $db->query(
    'SELECT p.username, p.vip_level, c.castle_level, c.power, c.name AS city_name
     FROM players p JOIN cities c ON c.player_id = p.id
     WHERE p.id = ? LIMIT 1',
    [$playerId],
)->fetch() ?: [];

// ── Attacker combat totals ────────────────────────────────────────────────────
$totalSent       = 0;
$totalAtk        = 0;
$totalHp         = 0;
$totalDef        = 0;
$totalAbsorption = 0;
$totalInjured    = 0;
$totalSurvived   = 0;

foreach ($troops as $t) {
    $sent    = (int) ($t['sent']     ?? 0);
    $injured = (int) ($t['injured']  ?? $t['lost'] ?? 0);
    $surv    = (int) ($t['survived'] ?? 0);
    $def     = TroopData::get((int) $t['code']);

    $totalSent     += $sent;
    $totalInjured  += $injured;
    $totalSurvived += $surv;

    if ($def !== null && $sent > 0) {
        $totalAtk        += $sent * (float) $def['attack'];
        $totalHp         += $sent * (float) $def['hp'];
        $totalDef        += $sent * (float) $def['defense'];
        $totalAbsorption += $sent * ((float) $def['hp'] + (float) $def['defense']);
    }
}

// ── Outcome helpers ───────────────────────────────────────────────────────────
$isWin  = $outcome === 'attacker_wins';
$isDraw = $outcome === 'draw';

$outcomeText  = match($outcome) {
    'attacker_wins' => 'Sieg',
    'defender_wins' => 'Niederlage',
    default         => 'Unentschieden',
};
$outcomeColor = match($outcome) {
    'attacker_wins' => '#22c55e',
    'defender_wins' => '#ef4444',
    default         => '#f59e0b',
};

// Monster side
$monsterName    = $data['monster_name']      ?? 'Monster';
$monsterHpBefore = (int) ($data['monster_hp_before'] ?? 0);
$monsterHpAfter  = (int) ($data['monster_hp_after']  ?? 0);
$monsterKilled   = (bool) ($data['monster_killed']   ?? false);
$monsterAtk      = (int) round((float) ($data['monster_atk_pool'] ?? 0));
$monsterLossPct  = round(($data['monster_loss_ratio'] ?? 0) * 100, 1);

$buffs = BuffEngine::getBuffs($playerId);

// Troop type index → BuffEngine type name
$troopTypeMap = [1 => 'infantry', 2 => 'ranged', 3 => 'cavalry'];

$fmt = fn(mixed $n): string => number_format((int) $n, 0, '.', ',');
$fmtF = fn(float $n): string => number_format($n, 0, '.', ',');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Kampfbericht #<?= $reportId ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      var(--c-bg, #f0e8d0);
            --surface: var(--c-panel, #f4e4c1);
            --surface2: var(--c-panel2, #ede0c4);
            --border:  var(--c-border, rgba(139,90,43,0.35));
            --border2: var(--c-gold, #c08858);
            --text:    var(--c-text, #4a3520);
            --muted:   var(--c-muted, #8b6f47);
            --gold:    var(--c-gold, #c08858);
            --gold2:   var(--c-wood-dark, #8b5a2b);
            --green:   var(--c-success, #7fb069);
            --red:     var(--c-danger, #c0604d);
            --red2:    var(--c-danger, #c0604d);
            --blue:    var(--c-panel2, #ede0c4);
            --blue2:   var(--c-info, #5f9ea0);
        }

        html, body {
            min-height: 100%;
            background: var(--c-bg, #f0e8d0);
            color: var(--c-text, #4a3520);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 960px;
            min-height: calc(100vh - 52px);
            margin-top: <?= $isEmbed ? '0' : '52px' ?>;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        /* ── Top back bar ── */
        .topbar {
            background: var(--c-panel2, #ede0c4);
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
            padding: 0.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px var(--c-shadow, rgba(139,90,43,0.18));
        }
        .topbar-back {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0.28rem 0.8rem;
            border-radius: 5px;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: linear-gradient(180deg, #c9925a 0%, #9a6535 100%);
            border: none;
            border-bottom: 2px solid #6b4120;
            color: #fff8ec;
            text-decoration: none;
            transition: filter 0.15s;
        }
        .topbar-back:hover { filter: brightness(1.12); color: #fff8ec; }
        .topbar-id { font-size: 0.82rem; color: var(--c-wood-dark, #8b5a2b); font-weight: 700; letter-spacing: 0.04em; }
        .topbar-date { font-size: 0.75rem; color: var(--c-muted, #8b6f47); margin-left: auto; }

        /* ── VS Header ── */
        .vs-header {
            background: var(--c-panel2, #ede0c4);
            border-bottom: 2px solid var(--c-border, rgba(139,90,43,0.35));
            padding: 1.25rem 1.5rem 1rem;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 1rem;
        }

        .vs-side { display: flex; flex-direction: column; gap: 0.25rem; }
        .vs-side.right { align-items: flex-end; }

        .vs-player-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--c-wood-dark, #8b5a2b);
            letter-spacing: 0.02em;
        }
        .vs-power {
            font-size: 0.82rem;
            color: var(--c-muted, #8b6f47);
        }
        .vs-power strong { color: var(--c-text, #4a3520); }

        .vs-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .vs-badge.win  { background: rgba(34,197,94,0.15); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .vs-badge.loss { background: rgba(220,38,38,0.15); color: var(--red2);  border: 1px solid rgba(220,38,38,0.3); }
        .vs-badge.draw { background: rgba(212,160,23,0.15); color: var(--gold); border: 1px solid rgba(212,160,23,0.3); }

        .vs-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
        }
        .vs-icon {
            font-size: 2rem;
            line-height: 1;
        }
        .vs-text {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--muted);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .vs-coords {
            font-size: 0.7rem;
            color: var(--c-muted, #8b6f47);
            background: var(--c-panel3, #e8d8b0);
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }

        .vs-monster-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--c-danger, #c0604d);
            letter-spacing: 0.02em;
        }

        /* ── Section header ── */
        .section-header {
            background: var(--c-panel3, #e8d8b0);
            padding: 0.35rem 1.25rem;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--c-wood-dark, #8b5a2b);
            border-left: 3px solid var(--c-gold, #c08858);
            border-top: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }

        /* ── Troops Lost comparison ── */
        .troops-lost {
            display: grid;
            grid-template-columns: 1fr 1px 1fr;
            background: var(--c-panel, #f4e4c1);
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .troops-lost-col {
            padding: 1rem 1.5rem;
        }
        .troops-lost-divider {
            background: var(--border);
        }
        .troops-lost-title {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--c-wood-dark, #8b5a2b);
            margin-bottom: 0.75rem;
            padding-bottom: 0.4rem;
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .tl-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.3rem 0;
            font-size: 0.82rem;
            border-bottom: 1px solid rgba(139,90,43,0.08);
        }
        .tl-row:last-child { border-bottom: none; }
        .tl-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--c-muted, #8b6f47);
        }
        .tl-val { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--c-text, #4a3520); }
        .tl-val.green { color: var(--c-success, #7fb069); }
        .tl-val.red   { color: var(--c-danger, #c0604d); }
        .tl-val.gold  { color: var(--c-wood-dark, #8b5a2b); }
        .tl-val.muted { color: var(--c-muted, #8b6f47); }

        /* ── Troops Info grid ── */
        .troops-info {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: var(--c-panel2, #ede0c4);
            align-items: flex-end;
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .troop-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }
        .troop-chip-icon {
            width: 52px;
            height: 52px;
            border-radius: 6px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel, #f4e4c1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            position: relative;
            box-shadow: 0 2px 8px var(--c-shadow, rgba(139,90,43,0.18));
        }
        .troop-chip-tier {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--c-panel3, #e8d8b0);
            color: var(--c-wood-dark, #8b5a2b);
            font-size: 0.55rem;
            font-weight: 800;
            padding: 0.05rem 0.25rem;
            border-radius: 3px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .troop-chip-count {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--c-text, #4a3520);
            font-variant-numeric: tabular-nums;
        }
        .troop-chip-injured {
            font-size: 0.65rem;
            color: var(--c-danger, #c0604d);
            font-weight: 700;
        }
        .troop-chip-name {
            font-size: 0.6rem;
            color: var(--c-muted, #8b6f47);
            text-align: center;
            max-width: 56px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .troops-info-total {
            margin-left: auto;
            text-align: right;
            align-self: center;
        }
        .troops-info-total-label {
            font-size: 0.6rem;
            color: var(--c-muted, #8b6f47);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .troops-info-total-val {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--c-wood-dark, #8b5a2b);
            font-variant-numeric: tabular-nums;
        }

        /* ── Monster stats ── */
        .monster-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            background: var(--c-panel, #f4e4c1);
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .mstat {
            flex: 1 1 150px;
            padding: 0.85rem 1.25rem;
            border-right: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .mstat:last-child { border-right: none; }
        .mstat-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-muted, #8b6f47);
            margin-bottom: 0.25rem;
        }
        .mstat-val {
            font-size: 1rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--c-text, #4a3520);
        }

        /* ── HP bar ── */
        .hp-bar-wrap { margin-top: 0.5rem; }
        .hp-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.65rem;
            color: var(--c-muted, #8b6f47);
            margin-bottom: 0.2rem;
        }
        .hp-bar-track {
            height: 6px;
            background: rgba(139,90,43,0.12);
            border-radius: 3px;
            overflow: hidden;
        }
        .hp-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: var(--c-danger, #c0604d);
        }

        /* ── Combat totals ── */
        .combat-totals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            background: var(--c-panel2, #ede0c4);
            border-top: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .ctotal {
            padding: 0.85rem 1.25rem;
            border-right: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .ctotal:last-child { border-right: none; }
        .ctotal-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-muted, #8b6f47);
            margin-bottom: 0.25rem;
        }
        .ctotal-val {
            font-size: 1rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--c-text, #4a3520);
        }

        /* ── Player profile ── */
        .player-profile {
            background: var(--c-panel, #f4e4c1);
            padding: 1rem 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
        }
        .pp-field { display: flex; flex-direction: column; gap: 0.15rem; }
        .pp-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-muted, #8b6f47);
        }
        .pp-val { font-size: 0.92rem; font-weight: 700; color: var(--c-text, #4a3520); }

        /* ── Troops detail table ── */
        .section-body { background: var(--c-panel, #f4e4c1); }
        .troop-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .troop-table th {
            text-align: left;
            padding: 0.5rem 1.25rem;
            color: var(--c-wood-dark, #8b5a2b);
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel3, #e8d8b0);
        }
        .troop-table td {
            padding: 0.55rem 1.25rem;
            border-bottom: 1px solid rgba(139,90,43,0.1);
            color: var(--c-text, #4a3520);
        }
        .troop-table tr:last-child td { border-bottom: none; }
        .troop-table tr:hover td { background: rgba(192,136,88,0.06); }
        .troop-table tfoot td {
            border-top: 1px solid var(--c-border, rgba(139,90,43,0.35));
            padding-top: 0.6rem;
            font-weight: 700;
        }
        .tier-badge {
            display: inline-block;
            padding: 0.1rem 0.35rem;
            border-radius: 3px;
            font-size: 0.62rem;
            font-weight: 800;
            background: var(--c-panel3, #e8d8b0);
            color: var(--c-wood-dark, #8b5a2b);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }
        .val-green { color: var(--c-success, #7fb069); font-weight: 700; }
        .val-red   { color: var(--c-danger, #c0604d);  font-weight: 700; }
        .val-muted { color: var(--c-muted, #8b6f47); }

        @media (max-width: 640px) {
            .vs-header { grid-template-columns: 1fr auto 1fr; gap: 0.5rem; }
            .vs-player-name, .vs-monster-name { font-size: 0.95rem; }
            .troops-lost { grid-template-columns: 1fr; }
            .troops-lost-divider { height: 1px; width: 100%; }
            .troops-info-total { margin-left: 0; text-align: left; }
        }
    </style>
</head>
<body>
<?php if (!$isEmbed): $hudCurrentView = 'reports'; require __DIR__ . '/partials/hud.php'; endif ?>
<div id="game">
<?php if (!$isEmbed): ?>
    <!-- Back bar -->
    <div class="topbar">
        <a href="/reports" class="topbar-back">← Alle Berichte</a>
        <span class="topbar-id">📜 Kampfbericht #<?= $reportId ?></span>
        <span class="topbar-date"><?= htmlspecialchars($row['created_at']) ?> UTC</span>
    </div>
<?php endif ?>

    <!-- VS Header -->
    <div class="vs-header">
        <div class="vs-side">
            <div class="vs-player-name"><?= htmlspecialchars($playerStats['username'] ?? 'Spieler') ?></div>
            <div class="vs-power">Macht: <strong><?= $fmt($playerStats['power'] ?? 0) ?></strong></div>
            <div class="vs-power">Schloss Lv <?= (int)($playerStats['castle_level'] ?? 1) ?> · <?= htmlspecialchars($playerStats['city_name'] ?? '') ?></div>
            <div style="margin-top:0.35rem">
                <span class="vs-badge <?= $isWin ? 'win' : ($isDraw ? 'draw' : 'loss') ?>">
                    <?= $isWin ? '⚔ Sieg' : ($isDraw ? '🤝 Unentschieden' : '💀 Niederlage') ?>
                </span>
            </div>
        </div>

        <div class="vs-center">
            <div class="vs-icon">⚔</div>
            <div class="vs-text">VS</div>
            <div class="vs-coords">📍 <?= (int)$row['target_x'] ?>, <?= (int)$row['target_y'] ?></div>
        </div>

        <div class="vs-side right">
            <div class="vs-monster-name"><?= htmlspecialchars($monsterName) ?></div>
            <div class="vs-power">HP: <strong><?= $fmt($monsterHpBefore) ?></strong></div>
            <div class="vs-power">Angriffskraft: <strong><?= $fmt($monsterAtk) ?></strong></div>
            <div style="margin-top:0.35rem; text-align: right">
                <span class="vs-badge <?= $monsterKilled ? 'win' : 'loss' ?>">
                    <?= $monsterKilled ? '💀 Besiegt' : '⚡ Überlebt' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ANGREIFER PROFIL — direkt unter VS-Header -->
    <?php if (!empty($playerStats)): ?>
    <div style="background:var(--c-panel2,#ede0c4);border-bottom:1px solid var(--c-border,rgba(139,90,43,0.35));padding:0.6rem 1.5rem;display:flex;flex-wrap:wrap;gap:1.5rem;align-items:center">
        <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--c-muted,#8b6f47);min-width:4rem">Angreifer</div>
        <?php
        $ppItems = [
            'Spieler'       => htmlspecialchars($playerStats['username']  ?? ''),
            'Stadt'         => htmlspecialchars($playerStats['city_name'] ?? ''),
            'Schloss'       => 'Lv ' . (int)($playerStats['castle_level'] ?? 1),
            'Macht'         => '<span style="color:var(--c-wood-dark,#8b5a2b)">' . $fmt($playerStats['power'] ?? 0) . '</span>',
            'VIP'           => 'Lv ' . (int)($playerStats['vip_level'] ?? 0),
        ];
        foreach ($ppItems as $label => $val): ?>
        <div style="display:flex;flex-direction:column;gap:0.1rem">
            <div style="font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--c-muted,#8b6f47)"><?= $label ?></div>
            <div style="font-size:0.88rem;font-weight:700;color:var(--c-text,#4a3520)"><?= $val ?></div>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <!-- TRUPPEN-VERLUSTE -->
    <div class="section-header">Truppen-Verluste</div>
    <div class="troops-lost">
        <!-- Attacker column -->
        <div class="troops-lost-col">
            <div class="troops-lost-title">Angreifer</div>
            <div class="tl-row">
                <span class="tl-label">Macht</span>
                <span class="tl-val gold"><?= $fmt($playerStats['power'] ?? 0) ?></span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Truppen</span>
                <span class="tl-val"><?= $fmt($totalSent) ?></span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Verletzt</span>
                <span class="tl-val <?= $totalInjured > 0 ? 'red' : 'green' ?>">
                    <?= $totalInjured > 0 ? $fmt($totalInjured) : '—' ?>
                </span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Tod</span>
                <span class="tl-val muted">0</span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Verbleibend</span>
                <span class="tl-val green"><?= $fmt($totalSurvived) ?></span>
            </div>
        </div>

        <div class="troops-lost-divider"></div>

        <!-- Monster column -->
        <div class="troops-lost-col">
            <div class="troops-lost-title">Monster</div>
            <div class="tl-row">
                <span class="tl-label">HP vor Kampf</span>
                <span class="tl-val gold"><?= $fmt($monsterHpBefore) ?></span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Schaden erhalten</span>
                <span class="tl-val red"><?= $fmt((int)($data['attacker_damage'] ?? 0)) ?></span>
            </div>
            <div class="tl-row">
                <span class="tl-label">HP zerstört</span>
                <span class="tl-val <?= $monsterLossPct >= 100 ? 'green' : 'red' ?>">
                    <?= $monsterLossPct ?>%
                </span>
            </div>
            <div class="tl-row">
                <span class="tl-label">HP verbleibend</span>
                <span class="tl-val <?= $monsterKilled ? 'muted' : 'red' ?>">
                    <?= $monsterKilled ? '0' : $fmt($monsterHpAfter) ?>
                </span>
            </div>
            <div class="tl-row">
                <span class="tl-label">Status</span>
                <span class="tl-val <?= $monsterKilled ? 'green' : 'red' ?>">
                    <?= $monsterKilled ? 'Besiegt' : 'Überlebt' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- HP-Bar Monster -->
    <?php if ($monsterHpBefore > 0): ?>
    <div style="background:var(--c-panel,#f4e4c1);padding:0 1.5rem 1rem;border-bottom:1px solid var(--c-border,rgba(139,90,43,0.35))">
        <div class="hp-bar-wrap">
            <div class="hp-bar-labels">
                <span><?= $fmt($monsterHpAfter) ?> HP verbleibend</span>
                <span><?= $fmt($monsterHpBefore) ?> HP gesamt</span>
            </div>
            <div class="hp-bar-track">
                <?php $pct = $monsterHpBefore > 0 ? max(0, round($monsterHpAfter / $monsterHpBefore * 100)) : 0; ?>
                <div class="hp-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- TRUPPEN INFO -->
    <?php if (!empty($troops)): ?>
    <div class="section-header">Truppen Info</div>
    <div class="troops-info">
        <?php
        $troopEmoji = fn(int $tier): string => match(true) {
            $tier >= 4 => '🐉',
            $tier >= 3 => '⚔',
            $tier >= 2 => '🛡',
            default    => '🗡',
        };
        ?>
        <?php foreach ($troops as $t): ?>
            <?php
                $injured = (int)($t['injured'] ?? $t['lost'] ?? 0);
                $tier    = (int)($t['tier'] ?? 1);
            ?>
            <div class="troop-chip">
                <div class="troop-chip-icon">
                    <?= $troopEmoji($tier) ?>
                    <span class="troop-chip-tier">T<?= $tier ?></span>
                </div>
                <div class="troop-chip-count"><?= $fmt($t['sent']) ?></div>
                <?php if ($injured > 0): ?>
                    <div class="troop-chip-injured">-<?= $fmt($injured) ?></div>
                <?php endif ?>
                <div class="troop-chip-name"><?= htmlspecialchars($t['name'] ?? '?') ?></div>
            </div>
        <?php endforeach ?>
        <div class="troops-info-total">
            <div class="troops-info-total-label">Gesamt</div>
            <div class="troops-info-total-val"><?= $fmt($totalSent) ?></div>
        </div>
    </div>
    <?php endif ?>

    <!-- KAMPFWERTE DER TRUPPEN -->
    <div class="section-header">Kampfwerte der gesendeten Truppen</div>
    <div class="combat-totals">
        <div class="ctotal">
            <div class="ctotal-label">Angriff gesamt</div>
            <div class="ctotal-val" style="color:var(--c-danger,#c0604d)"><?= $fmtF($totalAtk) ?></div>
        </div>
        <div class="ctotal">
            <div class="ctotal-label">HP gesamt</div>
            <div class="ctotal-val"><?= $fmtF($totalHp) ?></div>
        </div>
        <div class="ctotal">
            <div class="ctotal-label">Verteidigung</div>
            <div class="ctotal-val"><?= $fmtF($totalDef) ?></div>
        </div>
        <div class="ctotal">
            <div class="ctotal-label">Absorption (HP+Def)</div>
            <div class="ctotal-val" style="color:var(--c-wood-dark,#8b5a2b)"><?= $fmtF($totalAbsorption) ?></div>
        </div>
        <div class="ctotal">
            <div class="ctotal-label">Verwundungsrate</div>
            <?php $injPct = round(($data['attacker_injury_ratio'] ?? 0) * 100, 1); ?>
            <div class="ctotal-val" style="color:<?= $injPct > 0 ? 'var(--c-danger,#c0604d)' : 'var(--c-success,#7fb069)' ?>">
                <?= $injPct ?>%
            </div>
        </div>
    </div>

    <!-- TRUPPEN DETAIL TABELLE -->
    <?php if (!empty($troops)): ?>
    <div class="section-header">Truppen Detail</div>
    <div class="section-body">
        <table class="troop-table">
            <thead>
                <tr>
                    <th>Einheit</th>
                    <th>Tier</th>
                    <th style="text-align:right">ATK/Einheit</th>
                    <th style="text-align:right">HP/Einheit</th>
                    <th style="text-align:right">DEF/Einheit</th>
                    <th style="text-align:right">Gesendet</th>
                    <th style="text-align:right">Verletzt</th>
                    <th style="text-align:right">Zurückgekehrt</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($troops as $t): ?>
                <?php
                    $injured  = (int)($t['injured']  ?? $t['lost'] ?? 0);
                    $survived = (int)($t['survived'] ?? 0);
                    $sent     = (int)($t['sent']     ?? 0);
                    $tDef     = TroopData::get((int)$t['code']);
                    $tType    = $tDef ? ($troopTypeMap[$tDef['type'] ?? 1] ?? null) : null;
                    $effAtk   = $tDef ? (int) round($tDef['attack']  * BuffEngine::effectiveMultiplier($buffs, $tType, 'atk')) : 0;
                    $effHp    = $tDef ? (int) round($tDef['hp']       * BuffEngine::effectiveMultiplier($buffs, $tType, 'hp'))  : 0;
                    $effDef   = $tDef ? (int) round($tDef['defense']  * BuffEngine::effectiveMultiplier($buffs, $tType, 'def')) : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($t['name'] ?? '?') ?></td>
                    <td><span class="tier-badge">T<?= (int)($t['tier'] ?? 1) ?></span></td>
                    <td style="text-align:right;color:var(--c-danger,#c0604d)"><?= $fmt($effAtk) ?></td>
                    <td style="text-align:right"><?= $fmt($effHp) ?></td>
                    <td style="text-align:right"><?= $fmt($effDef) ?></td>
                    <td style="text-align:right"><?= $fmt($sent) ?></td>
                    <td style="text-align:right">
                        <?php if ($injured > 0): ?>
                            <span class="val-red">-<?= $fmt($injured) ?></span>
                        <?php else: ?>
                            <span class="val-muted">—</span>
                        <?php endif ?>
                    </td>
                    <td style="text-align:right">
                        <span class="val-green"><?= $fmt($survived) ?></span>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="color:var(--c-muted,#8b6f47);font-size:0.75rem">Gesamt</td>
                    <td style="text-align:right"><?= $fmt($totalSent) ?></td>
                    <td style="text-align:right">
                        <?php if ($totalInjured > 0): ?>
                            <span class="val-red">-<?= $fmt($totalInjured) ?></span>
                        <?php else: ?>
                            <span class="val-muted">—</span>
                        <?php endif ?>
                    </td>
                    <td style="text-align:right">
                        <span class="val-green"><?= $fmt($totalSurvived) ?></span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif ?>


    <!-- BOOST LIST -->
    <div class="section-header">Boost List <span style="font-weight:400;opacity:.6;font-size:0.58rem;letter-spacing:0.04em">— Forschungs-Buffs zum Zeitpunkt des Angriffs</span></div>
    <?php
    // Defender buffs: for PvP load via target_id; monsters have no research buffs
    $isMonster    = ((int)$row['target_type']) === 3;
    $defenderBuffs = (!$isMonster && $row['target_id'])
        ? (BuffEngine::getBuffs((int)$row['target_id']) ?: [])
        : [];

    $bPctFor = fn(array $b, string $key): string => '+' . round(($b[$key] ?? 0.0) * 100, 1) . '%';
    $bIntFor  = fn(array $b, string $key): string => '+' . (int)($b[$key] ?? 0);

    // Same stat list for both sides
    $boostStats = [
        'Truppen HP'                  => ['troops_hp',          'pct'],
        'Truppen Angriff'             => ['troops_atk',         'pct'],
        'Truppen Verteidigung'        => ['troops_def',         'pct'],
        'Truppen Geschwindigkeit'     => ['troops_spd',         'pct'],
        'Infanterie HP'               => ['infantry_hp',        'pct'],
        'Infanterie Angriff'          => ['infantry_atk',       'pct'],
        'Infanterie Verteidigung'     => ['infantry_def',       'pct'],
        'Infanterie Geschwindigkeit'  => ['infantry_spd',       'pct'],
        'Fernkämpfer HP'              => ['ranged_hp',          'pct'],
        'Fernkämpfer Angriff'         => ['ranged_atk',         'pct'],
        'Fernkämpfer Verteidigung'    => ['ranged_def',         'pct'],
        'Fernkämpfer Geschwindigkeit' => ['ranged_spd',         'pct'],
        'Kavallerie HP'               => ['cavalry_hp',         'pct'],
        'Kavallerie Angriff'          => ['cavalry_atk',        'pct'],
        'Kavallerie Verteidigung'     => ['cavalry_def',        'pct'],
        'Kavallerie Geschwindigkeit'  => ['cavalry_spd',        'pct'],
        'Marschgröße (Bonus)'         => ['march_size',         'int'],
        'Krankenhauskapazität'        => ['hospital_capacity',  'int'],
        'Heilungsgeschwindigkeit'     => ['healing_speed',      'pct'],
        'Baugeschwindigkeit'          => ['construction_speed', 'pct'],
    ];

    $resolveVal = function(array $b, string $key, string $type) use ($bPctFor, $bIntFor): string {
        if (empty($b)) return '—';
        return $type === 'int' ? $bIntFor($b, $key) : $bPctFor($b, $key);
    };

    $boostRow = fn(string $label, string $atkVal, string $defVal): string =>
        '<div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:0.22rem 0;border-bottom:1px solid rgba(139,90,43,0.08);font-size:0.77rem;gap:8px">'
        . '<span style="color:var(--c-muted,#8b6f47)">' . htmlspecialchars($label) . '</span>'
        . '<span style="font-weight:700;color:' . (($atkVal === '+0%' || $atkVal === '+0') ? 'var(--c-muted,#8b6f47)' : 'var(--c-success,#7fb069)') . ';white-space:nowrap">' . htmlspecialchars($atkVal) . '</span>'
        . '</div>';

    $boostRowDef = fn(string $label, string $defVal): string =>
        '<div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:0.22rem 0;border-bottom:1px solid rgba(139,90,43,0.08);font-size:0.77rem;gap:8px">'
        . '<span style="color:var(--c-muted,#8b6f47)">' . htmlspecialchars($label) . '</span>'
        . '<span style="font-weight:700;color:' . (($defVal === '—' || $defVal === '+0%' || $defVal === '+0') ? 'var(--c-muted,#8b6f47)' : 'var(--c-success,#7fb069)') . ';white-space:nowrap">' . htmlspecialchars($defVal) . '</span>'
        . '</div>';

    $atkName = htmlspecialchars($playerStats['username'] ?? 'Angreifer');
    $defName = $isMonster ? htmlspecialchars($monsterName) : htmlspecialchars($data['defender_name'] ?? 'Verteidiger');
    ?>
    <div style="background:var(--c-panel,#f4e4c1);display:grid;grid-template-columns:1fr 1px 1fr">
        <div style="padding:0.75rem 1.25rem 1rem">
            <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--c-muted,#8b6f47);margin-bottom:0.5rem"><?= $atkName ?></div>
            <?php foreach ($boostStats as $label => [$key, $type]):
                $val = $resolveVal($buffs, $key, $type); ?>
                <div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:0.22rem 0;border-bottom:1px solid rgba(139,90,43,0.08);font-size:0.77rem;gap:8px">
                    <span style="color:var(--c-muted,#8b6f47)"><?= htmlspecialchars($label) ?></span>
                    <span style="font-weight:700;color:<?= ($val === '+0%' || $val === '+0') ? 'var(--c-muted,#8b6f47)' : 'var(--c-success,#7fb069)' ?>;white-space:nowrap"><?= htmlspecialchars($val) ?></span>
                </div>
            <?php endforeach ?>
        </div>
        <div style="background:var(--c-border,rgba(139,90,43,0.35))"></div>
        <div style="padding:0.75rem 1.25rem 1rem">
            <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--c-muted,#8b6f47);margin-bottom:0.5rem"><?= $defName ?></div>
            <?php if ($isMonster): ?>
                <?php
                $monsterRows = [
                    'HP vor Kampf'      => $fmt($monsterHpBefore),
                    'HP verbleibend'    => $monsterKilled ? '0' : $fmt($monsterHpAfter),
                    'Angriffskraft'     => $fmt($monsterAtk),
                    'Schaden erhalten'  => $fmt((int)($data['attacker_damage'] ?? 0)),
                    'HP zerstört'       => $monsterLossPct . '%',
                    'Status'            => $monsterKilled ? 'Besiegt' : 'Überlebt',
                ];
                foreach ($monsterRows as $mLabel => $mVal):
                    $mColor = match($mLabel) {
                        'Status'          => $monsterKilled ? 'var(--c-success,#7fb069)' : 'var(--c-danger,#c0604d)',
                        'HP verbleibend'  => $monsterKilled ? 'var(--c-muted,#8b6f47)' : 'var(--c-danger,#c0604d)',
                        'Schaden erhalten'=> 'var(--c-danger,#c0604d)',
                        'HP zerstört'     => $monsterLossPct >= 100 ? 'var(--c-success,#7fb069)' : 'var(--c-danger,#c0604d)',
                        default           => 'var(--c-text,#4a3520)',
                    };
                ?>
                <div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:0.22rem 0;border-bottom:1px solid rgba(139,90,43,0.08);font-size:0.77rem;gap:8px">
                    <span style="color:var(--c-muted,#8b6f47)"><?= htmlspecialchars($mLabel) ?></span>
                    <span style="font-weight:700;color:<?= $mColor ?>;white-space:nowrap"><?= htmlspecialchars($mVal) ?></span>
                </div>
                <?php endforeach ?>
            <?php else: ?>
                <?php foreach ($boostStats as $label => [$key, $type]):
                    $val = $resolveVal($defenderBuffs, $key, $type); ?>
                    <div style="display:grid;grid-template-columns:1fr auto;align-items:center;padding:0.22rem 0;border-bottom:1px solid rgba(139,90,43,0.08);font-size:0.77rem;gap:8px">
                        <span style="color:var(--c-muted,#8b6f47)"><?= htmlspecialchars($label) ?></span>
                        <span style="font-weight:700;color:<?= ($val === '—' || $val === '+0%' || $val === '+0') ? 'var(--c-muted,#8b6f47)' : 'var(--c-success,#7fb069)' ?>;white-space:nowrap"><?= htmlspecialchars($val) ?></span>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>

    <div style="height:2rem"></div>
</div>
</body>
</html>
