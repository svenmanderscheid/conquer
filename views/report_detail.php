<?php
declare(strict_types=1);
/**
 * Battle Report Detail — /reports/:id
 * Variables: $session, $reportId (from index.php)
 */

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

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

$fmt = fn(mixed $n): string => number_format((int) $n, 0, '.', ',');
$fmtF = fn(float $n): string => number_format($n, 0, '.', ',');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Kampfbericht #<?= $reportId ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0a0e1a;
            --surface: #111827;
            --surface2: #1a2235;
            --border:  #1e3a5f;
            --border2: #2a4a7f;
            --text:    #d1dce8;
            --muted:   #6b82a0;
            --gold:    #d4a017;
            --gold2:   #f0c040;
            --green:   #22c55e;
            --red:     #dc2626;
            --red2:    #ef4444;
            --blue:    #1e4080;
            --blue2:   #2563a8;
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 960px;
            min-height: calc(100vh - 72px);
            margin-top: 72px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        /* ── Top back bar ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .topbar-back {
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
            font-size: 0.78rem;
        }
        .topbar-back:hover { border-color: var(--gold); color: var(--gold); }
        .topbar-id { font-size: 0.82rem; color: var(--gold); font-weight: 700; }
        .topbar-date { font-size: 0.75rem; color: var(--muted); margin-left: auto; }

        /* ── VS Header ── */
        .vs-header {
            background: linear-gradient(180deg, #0d1e3a 0%, #0a1628 100%);
            border-bottom: 2px solid var(--border2);
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
            color: var(--gold2);
            letter-spacing: 0.02em;
        }
        .vs-power {
            font-size: 0.82rem;
            color: var(--muted);
        }
        .vs-power strong { color: var(--text); }

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
            color: var(--muted);
            background: var(--surface);
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            border: 1px solid var(--border);
        }

        .vs-monster-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--red2);
            letter-spacing: 0.02em;
        }

        /* ── Section header ── */
        .section-header {
            background: linear-gradient(90deg, var(--blue) 0%, transparent 100%);
            padding: 0.35rem 1.25rem;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #7ab4e0;
            border-top: 1px solid var(--border2);
            border-bottom: 1px solid var(--border);
        }

        /* ── Troops Lost comparison ── */
        .troops-lost {
            display: grid;
            grid-template-columns: 1fr 1px 1fr;
            background: var(--surface);
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
            color: var(--muted);
            margin-bottom: 0.75rem;
            padding-bottom: 0.4rem;
            border-bottom: 1px solid var(--border);
        }
        .tl-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.3rem 0;
            font-size: 0.82rem;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .tl-row:last-child { border-bottom: none; }
        .tl-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }
        .tl-val { font-weight: 700; font-variant-numeric: tabular-nums; }
        .tl-val.green { color: var(--green); }
        .tl-val.red   { color: var(--red2); }
        .tl-val.gold  { color: var(--gold2); }
        .tl-val.muted { color: var(--muted); }

        /* ── Troops Info grid ── */
        .troops-info {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: var(--surface2);
            align-items: flex-end;
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
            border: 2px solid var(--border2);
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            position: relative;
        }
        .troop-chip-tier {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: var(--gold2);
            font-size: 0.55rem;
            font-weight: 800;
            padding: 0.05rem 0.25rem;
            border-radius: 3px;
            border: 1px solid var(--border2);
        }
        .troop-chip-count {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text);
            font-variant-numeric: tabular-nums;
        }
        .troop-chip-injured {
            font-size: 0.65rem;
            color: var(--red2);
            font-weight: 700;
        }
        .troop-chip-name {
            font-size: 0.6rem;
            color: var(--muted);
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
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .troops-info-total-val {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--gold2);
            font-variant-numeric: tabular-nums;
        }

        /* ── Monster stats ── */
        .monster-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            background: var(--surface);
        }
        .mstat {
            flex: 1 1 150px;
            padding: 0.85rem 1.25rem;
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .mstat:last-child { border-right: none; }
        .mstat-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }
        .mstat-val {
            font-size: 1rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        /* ── HP bar ── */
        .hp-bar-wrap { margin-top: 0.5rem; }
        .hp-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.65rem;
            color: var(--muted);
            margin-bottom: 0.2rem;
        }
        .hp-bar-track {
            height: 6px;
            background: rgba(255,255,255,0.08);
            border-radius: 3px;
            overflow: hidden;
        }
        .hp-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: var(--red2);
        }

        /* ── Combat totals ── */
        .combat-totals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            background: var(--surface2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .ctotal {
            padding: 0.85rem 1.25rem;
            border-right: 1px solid var(--border);
        }
        .ctotal:last-child { border-right: none; }
        .ctotal-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }
        .ctotal-val {
            font-size: 1rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        /* ── Player profile ── */
        .player-profile {
            background: var(--surface);
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
            color: var(--muted);
        }
        .pp-val { font-size: 0.92rem; font-weight: 700; }

        /* ── Troops detail table ── */
        .section-body { background: var(--surface); }
        .troop-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .troop-table th {
            text-align: left;
            padding: 0.5rem 1.25rem;
            color: var(--muted);
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
        }
        .troop-table td {
            padding: 0.55rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .troop-table tr:last-child td { border-bottom: none; }
        .troop-table tfoot td {
            border-top: 1px solid var(--border);
            padding-top: 0.6rem;
            font-weight: 700;
        }
        .tier-badge {
            display: inline-block;
            padding: 0.1rem 0.35rem;
            border-radius: 3px;
            font-size: 0.62rem;
            font-weight: 800;
            background: rgba(37,99,168,0.3);
            color: var(--gold2);
            border: 1px solid rgba(37,99,168,0.5);
        }
        .val-green { color: var(--green); font-weight: 700; }
        .val-red   { color: var(--red2);  font-weight: 700; }
        .val-muted { color: var(--muted); }

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
<?php require __DIR__ . '/partials/nav.php'; ?>
<div id="game">

    <!-- Back bar -->
    <div class="topbar">
        <a href="/reports" class="topbar-back">← Alle Berichte</a>
        <span class="topbar-id">📜 Kampfbericht #<?= $reportId ?></span>
        <span class="topbar-date"><?= htmlspecialchars($row['created_at']) ?> UTC</span>
    </div>

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
    <div style="background:var(--surface2);border-bottom:1px solid var(--border);padding:0.6rem 1.5rem;display:flex;flex-wrap:wrap;gap:1.5rem;align-items:center">
        <div style="font-size:0.6rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);min-width:4rem">Angreifer</div>
        <?php
        $ppItems = [
            'Spieler'       => htmlspecialchars($playerStats['username']  ?? ''),
            'Stadt'         => htmlspecialchars($playerStats['city_name'] ?? ''),
            'Schloss'       => 'Lv ' . (int)($playerStats['castle_level'] ?? 1),
            'Macht'         => '<span style="color:var(--gold2)">' . $fmt($playerStats['power'] ?? 0) . '</span>',
            'VIP'           => 'Lv ' . (int)($playerStats['vip_level'] ?? 0),
        ];
        foreach ($ppItems as $label => $val): ?>
        <div style="display:flex;flex-direction:column;gap:0.1rem">
            <div style="font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted)"><?= $label ?></div>
            <div style="font-size:0.88rem;font-weight:700"><?= $val ?></div>
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
    <div style="background:var(--surface);padding:0 1.5rem 1rem;border-bottom:1px solid var(--border)">
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
            <div class="ctotal-val" style="color:var(--red2)"><?= $fmtF($totalAtk) ?></div>
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
            <div class="ctotal-val" style="color:var(--gold2)"><?= $fmtF($totalAbsorption) ?></div>
        </div>
        <div class="ctotal">
            <div class="ctotal-label">Verwundungsrate</div>
            <?php $injPct = round(($data['attacker_injury_ratio'] ?? 0) * 100, 1); ?>
            <div class="ctotal-val" style="color:<?= $injPct > 0 ? 'var(--red2)' : 'var(--green)' ?>">
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
                ?>
                <tr>
                    <td><?= htmlspecialchars($t['name'] ?? '?') ?></td>
                    <td><span class="tier-badge">T<?= (int)($t['tier'] ?? 1) ?></span></td>
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
                    <td colspan="2" style="color:var(--muted);font-size:0.75rem">Gesamt</td>
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


    <div style="height:2rem"></div>
</div>
</body>
</html>
