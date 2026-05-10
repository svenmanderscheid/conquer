<?php
declare(strict_types=1);
/**
 * Battle Reports — /reports
 * Variables: $session (from index.php)
 */

use Conquer\Db\Connection;

$playerId = (int) $session['player_id'];
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$db = Connection::getInstance();

try {
    $reports = $db->query(
        'SELECT id, target_type, target_x, target_y, outcome,
                attacker_read, created_at,
                JSON_UNQUOTE(JSON_EXTRACT(data_json, "$.monster_name")) AS monster_name
         FROM   battle_reports
         WHERE  attacker_id = ?
         ORDER  BY created_at DESC
         LIMIT  ? OFFSET ?',
        [$playerId, $perPage, $offset],
    )->fetchAll();

    $total = (int) $db->query(
        'SELECT COUNT(*) FROM battle_reports WHERE attacker_id = ?',
        [$playerId],
    )->fetchColumn();

    // Mark all on this page as read
    $ids = array_column($reports, 'id');
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->execute(
            "UPDATE battle_reports SET attacker_read = 1 WHERE id IN ($placeholders)",
            $ids,
        );
    }
} catch (\Throwable) {
    $reports = [];
    $total   = 0;
}

$pages = max(1, (int) ceil($total / $perPage));

$outcomeLabel = [
    'attacker_wins'  => ['label' => 'Sieg',       'color' => '#22c55e'],
    'defender_wins'  => ['label' => 'Niederlage', 'color' => '#ef4444'],
    'draw'           => ['label' => 'Unentschieden', 'color' => '#f59e0b'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Kampfberichte</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #080c18;
            --surface: #0f1729;
            --surface2: #1a2744;
            --border:  rgba(184,134,11,0.3);
            --border-h: rgba(212,160,23,0.7);
            --text:    #e2e8f0;
            --muted:   #64748b;
            --muted2:  #94a3b8;
            --gold:    #d4a017;
            --gold-l:  #f0d080;
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
            max-width: 1280px;
            min-height: calc(100vh - 52px);
            margin-top: 52px;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        .content { padding: 1.5rem; flex: 1; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        th {
            text-align: left;
            padding: 0.5rem 0.75rem;
            border-bottom: 2px solid var(--border);
            color: var(--muted2);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            background: linear-gradient(180deg, #1a2744 0%, #0f1729 100%);
        }
        td {
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid rgba(184,134,11,0.12);
        }
        tr:hover td { background: rgba(212,160,23,0.04); }
        tr.unread td { background: rgba(212,160,23,0.07); }

        .outcome-badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.06);
        }

        .btn-detail {
            padding: 0.2rem 0.65rem;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: linear-gradient(180deg, #c8870a 0%, #9a6508 100%);
            border: none;
            border-bottom: 2px solid #6b4306;
            color: #fff8e0;
            text-decoration: none;
            cursor: pointer;
            transition: filter 0.15s;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4);
        }
        .btn-detail:hover { filter: brightness(1.15); color: #fff8e0; }

        .pagination {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            margin-top: 1.25rem;
        }
        .page-btn {
            padding: 0.25rem 0.65rem;
            border-radius: 5px;
            background: linear-gradient(180deg, #1a2744 0%, #0f1729 100%);
            border: 1px solid var(--border);
            color: var(--muted2);
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: border-color 0.15s, color 0.15s, background 0.15s;
            display: inline-block;
        }
        .page-btn.active {
            background: linear-gradient(180deg, #c8870a 0%, #9a6508 100%);
            border-color: #6b4306;
            color: #fff8e0;
            font-weight: 700;
            border-bottom-width: 2px;
        }
        .page-btn:hover:not(.active) { border-color: var(--border-h); color: var(--gold-l); }

        .empty { padding: 3rem; text-align: center; color: var(--muted); font-size: 0.9rem; }
    </style>
</head>
<body>
<?php $hudCurrentView = 'reports'; require __DIR__ . '/partials/hud.php'; ?>
<div id="game">

    <header style="flex:0 0 44px;background:linear-gradient(180deg,#1a2744 0%,#0f1729 100%);border-bottom:2px solid rgba(212,160,23,0.4);padding:0 1.25rem;display:flex;align-items:center;gap:0.75rem;box-shadow:0 2px 12px rgba(0,0,0,0.5)">
        <span style="font-size:0.95rem;font-weight:800;color:var(--gold-l);text-transform:uppercase;letter-spacing:0.05em">&#x1F4DC; Kampfberichte</span>
        <span style="color:var(--muted);font-size:0.78rem;font-weight:600"><?= $total ?> Berichte</span>
    </header>

    <div class="content">
        <?php if (empty($reports)): ?>
            <div class="empty" style="background:var(--surface);border:1px solid var(--border);border-radius:10px;color:var(--muted);padding:3rem;text-align:center">
                Noch keine Kampfberichte vorhanden.
            </div>
        <?php else: ?>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.5)">
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Ziel</th>
                    <th>Ergebnis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $r): ?>
                <?php
                    $oc  = $outcomeLabel[$r['outcome']] ?? ['label' => $r['outcome'], 'color' => '#94a3b8'];
                    $lbl = $r['monster_name'] ?? ('Tile ' . $r['target_x'] . ',' . $r['target_y']);
                ?>
                <tr class="<?= $r['attacker_read'] ? '' : 'unread' ?>">
                    <td style="color:var(--muted);font-family:monospace;font-size:0.78rem"><?= htmlspecialchars($r['created_at']) ?> UTC</td>
                    <td style="font-weight:600">⚔ <?= htmlspecialchars($lbl) ?> <span style="color:var(--muted);font-size:0.78rem">(<?= (int)$r['target_x'] ?>,<?= (int)$r['target_y'] ?>)</span></td>
                    <td>
                        <span class="outcome-badge" style="color:<?= $oc['color'] ?>;border-color:<?= $oc['color'] ?>33">
                            <?= $oc['label'] ?>
                        </span>
                    </td>
                    <td>
                        <a href="/reports/<?= (int)$r['id'] ?>" class="btn-detail">Detail</a>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="/reports?page=<?= $p ?>"
                   class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor ?>
        </div>
        <?php endif ?>
        <?php endif ?>
    </div>

</div>
</body>
</html>
