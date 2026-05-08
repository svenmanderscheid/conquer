<?php
declare(strict_types=1);
/**
 * Battle Report Detail — /reports/:id
 * Variables: $session, $reportId (from index.php)
 */

use Conquer\Db\Connection;

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

$fmt = fn(mixed $n): string => number_format((int)$n, 0, '.', ',');
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
            --bg:      #0f172a;
            --surface: #1e293b;
            --border:  #334155;
            --text:    #e2e8f0;
            --muted:   #94a3b8;
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
            min-height: calc(100vh - 72px);
            margin-top: 72px;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        /* ── Top bar ── */
        .topbar {
            flex: 0 0 48px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .topbar-title { font-size: 0.95rem; font-weight: 700; color: var(--gold); }
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

        /* ── Content ── */
        .content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* ── Outcome banner ── */
        .outcome-banner {
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid;
        }
        .outcome-banner.win  { background: rgba(34,197,94,0.08);  border-color: rgba(34,197,94,0.3);  }
        .outcome-banner.loss { background: rgba(239,68,68,0.08);  border-color: rgba(239,68,68,0.3);  }
        .outcome-banner.draw { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.3); }

        .outcome-label {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .outcome-meta {
            text-align: right;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ── Cards ── */
        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 640px) { .cards { grid-template-columns: 1fr; } }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
        }
        .card-title {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── Monster card ── */
        .monster-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 0.6rem;
        }
        .monster-stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .monster-stat-row:last-child { border-bottom: none; }
        .stat-label { color: var(--muted); }
        .stat-val   { font-weight: 600; font-variant-numeric: tabular-nums; }

        /* HP bar */
        .hp-bar-wrap {
            margin-top: 0.75rem;
        }
        .hp-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--muted);
            margin-bottom: 0.3rem;
        }
        .hp-bar-track {
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        .hp-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        /* ── Troops table ── */
        .troop-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .troop-table th {
            text-align: left;
            padding: 0.4rem 0.5rem;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
        }
        .troop-table td {
            padding: 0.5rem 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .troop-table tr:last-child td { border-bottom: none; }
        .troop-survived { color: var(--green); font-weight: 600; }
        .troop-injured  { color: var(--red);   font-weight: 600; }
        .tier-badge {
            display: inline-block;
            padding: 0.1rem 0.35rem;
            border-radius: 3px;
            font-size: 0.65rem;
            font-weight: 700;
            background: rgba(245,158,11,0.15);
            color: var(--gold);
            border: 1px solid rgba(245,158,11,0.3);
        }

        /* ── Combat stats card ── */
        .combat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.35rem 0;
            font-size: 0.8rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .combat-row:last-child { border-bottom: none; }
        .combat-label { color: var(--muted); }
        .combat-val   { font-weight: 600; font-variant-numeric: tabular-nums; }

        /* ── Coord info ── */
        .coord-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.75rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 0.78rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>
<div id="game">

    <header class="topbar">
        <a href="/reports" class="topbar-back">← Zurück</a>
        <span class="topbar-title">📜 Kampfbericht #<?= $reportId ?></span>
        <span class="coord-chip">
            📍 <?= (int)$row['target_x'] ?>, <?= (int)$row['target_y'] ?>
        </span>
        <span style="color:var(--muted);font-size:0.78rem;margin-left:auto">
            <?= htmlspecialchars($row['created_at']) ?> UTC
        </span>
    </header>

    <div class="content">

        <!-- Outcome banner -->
        <div class="outcome-banner <?= $isWin ? 'win' : ($isDraw ? 'draw' : 'loss') ?>">
            <div>
                <div class="outcome-label" style="color:<?= $outcomeColor ?>">
                    <?= $isWin ? '⚔ ' : ($isDraw ? '🤝 ' : '💀 ') ?><?= $outcomeText ?>
                </div>
                <div style="font-size:0.82rem;color:var(--muted);margin-top:0.2rem">
                    <?= htmlspecialchars($data['monster_name'] ?? 'Unbekanntes Monster') ?>
                    <?php if ($data['monster_killed'] ?? false): ?>
                        — <span style="color:var(--green)">Monster besiegt</span>
                    <?php elseif (!$isWin): ?>
                        — <span style="color:var(--red)">Monster überlebt</span>
                    <?php endif ?>
                </div>
            </div>
            <div class="outcome-meta">
                Schaden: <?= $fmt($data['attacker_damage'] ?? 0) ?><br>
                Monster-HP: <?= $fmt($data['monster_hp_before'] ?? 0) ?>
                → <?= $fmt($data['monster_hp_after'] ?? 0) ?>
            </div>
        </div>

        <div class="cards">

            <!-- Monster card -->
            <div class="card">
                <div class="card-title">Monster</div>
                <div class="monster-name">
                    <?= htmlspecialchars($data['monster_name'] ?? '?') ?>
                </div>

                <div class="hp-bar-wrap">
                    <div class="hp-bar-label">
                        <span>HP vor dem Kampf</span>
                        <span><?= $fmt($data['monster_hp_before'] ?? 0) ?></span>
                    </div>
                    <div class="hp-bar-track">
                        <?php
                            $hpBefore = (int)($data['monster_hp_before'] ?? 1);
                            $hpAfter  = (int)($data['monster_hp_after']  ?? 0);
                            $pct      = $hpBefore > 0 ? round($hpAfter / $hpBefore * 100) : 0;
                        ?>
                        <div class="hp-bar-fill" style="width:100%;background:#ef4444"></div>
                    </div>
                    <div class="hp-bar-label" style="margin-top:0.4rem">
                        <span>HP nach dem Kampf</span>
                        <span><?= $fmt($hpAfter) ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="hp-bar-track">
                        <div class="hp-bar-fill"
                             style="width:<?= $pct ?>%;background:<?= $pct === 0 ? '#334155' : '#ef4444' ?>"></div>
                    </div>
                </div>

                <div style="margin-top:0.75rem">
                    <div class="monster-stat-row">
                        <span class="stat-label">Angriffskraft</span>
                        <span class="stat-val"><?= $fmt($data['monster_atk_pool'] ?? 0) ?></span>
                    </div>
                    <div class="monster-stat-row">
                        <span class="stat-label">Status</span>
                        <span class="stat-val" style="color:<?= ($data['monster_killed'] ?? false) ? 'var(--green)' : 'var(--red)' ?>">
                            <?= ($data['monster_killed'] ?? false) ? 'Besiegt' : 'Überlebt' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Combat stats card -->
            <div class="card">
                <div class="card-title">Kampfstatistik</div>
                <div class="combat-row">
                    <span class="combat-label">Gesamtschaden (Angreifer)</span>
                    <span class="combat-val" style="color:var(--gold)"><?= $fmt($data['attacker_damage'] ?? 0) ?></span>
                </div>
                <div class="combat-row">
                    <span class="combat-label">Monster Angriff</span>
                    <span class="combat-val"><?= $fmt($data['monster_atk_pool'] ?? 0) ?></span>
                </div>
                <div class="combat-row">
                    <span class="combat-label">Verwundungsrate</span>
                    <?php $injRate = round(($data['attacker_injury_ratio'] ?? 0) * 100, 1); ?>
                    <span class="combat-val" style="color:<?= $injRate > 0 ? 'var(--red)' : 'var(--green)' ?>">
                        <?= $injRate ?>%
                    </span>
                </div>
                <div class="combat-row">
                    <span class="combat-label">Monster HP zerstört</span>
                    <span class="combat-val"><?= round(($data['monster_loss_ratio'] ?? 0) * 100, 1) ?>%</span>
                </div>
                <div class="combat-row">
                    <span class="combat-label">Koordinaten</span>
                    <span class="combat-val"><?= (int)$row['target_x'] ?>, <?= (int)$row['target_y'] ?></span>
                </div>
                <div class="combat-row">
                    <span class="combat-label">Ergebnis</span>
                    <span class="combat-val" style="color:<?= $outcomeColor ?>"><?= $outcomeText ?></span>
                </div>
            </div>

        </div>

        <!-- Troops detail -->
        <?php if (!empty($troops)): ?>
        <div class="card">
            <div class="card-title">Truppen</div>
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
                                <span class="troop-injured">-<?= $fmt($injured) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif ?>
                        </td>
                        <td style="text-align:right">
                            <span class="troop-survived"><?= $fmt($survived) ?></span>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 1px solid var(--border)">
                        <td colspan="2" style="padding-top:0.5rem;color:var(--muted);font-size:0.75rem">Gesamt</td>
                        <td style="text-align:right;padding-top:0.5rem;font-weight:700">
                            <?= $fmt(array_sum(array_column($troops, 'sent'))) ?>
                        </td>
                        <td style="text-align:right;padding-top:0.5rem">
                            <?php $totalInjured = array_sum(array_map(fn($t) => $t['injured'] ?? $t['lost'] ?? 0, $troops)); ?>
                            <?php if ($totalInjured > 0): ?>
                                <span class="troop-injured">-<?= $fmt($totalInjured) ?></span>
                            <?php else: ?>
                                <span style="color:var(--green)">—</span>
                            <?php endif ?>
                        </td>
                        <td style="text-align:right;padding-top:0.5rem">
                            <span class="troop-survived font-weight:700">
                                <?= $fmt(array_sum(array_column($troops, 'survived'))) ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif ?>

    </div>
</div>
</body>
</html>
