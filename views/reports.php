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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f172a;
            --surface: #1e293b;
            --border:  #334155;
            --text:    #e2e8f0;
            --muted:   #94a3b8;
            --gold:    #f59e0b;
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
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.06em;
        }
        td {
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid #1e293b;
        }
        tr:hover td { background: rgba(255,255,255,0.03); }
        tr.unread td { background: rgba(245,158,11,0.06); }

        .outcome-badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(0,0,0,0.3);
        }

        .btn-detail {
            padding: 0.2rem 0.65rem;
            border-radius: 5px;
            font-size: 0.75rem;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
        }
        .btn-detail:hover { border-color: var(--gold); color: var(--gold); }

        .pagination {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            margin-top: 1.25rem;
        }
        .page-btn {
            padding: 0.25rem 0.65rem;
            border-radius: 5px;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 0.78rem;
            text-decoration: none;
        }
        .page-btn.active { border-color: var(--gold); color: var(--gold); font-weight: 700; }
        .page-btn:hover  { border-color: var(--gold); }

        .empty { padding: 3rem; text-align: center; color: var(--muted); font-size: 0.9rem; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>
<div id="game">

    <header class="topbar">
        <span class="topbar-title">📜 Kampfberichte</span>
        <span style="color:var(--muted);font-size:0.8rem"><?= $total ?> Berichte gesamt</span>
    </header>

    <div class="content">
        <?php if (empty($reports)): ?>
            <div class="empty">Noch keine Kampfberichte vorhanden.</div>
        <?php else: ?>
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
                    <td style="color:var(--muted)"><?= htmlspecialchars($r['created_at']) ?> UTC</td>
                    <td>⚔ <?= htmlspecialchars($lbl) ?> (<?= (int)$r['target_x'] ?>,<?= (int)$r['target_y'] ?>)</td>
                    <td>
                        <span class="outcome-badge" style="color:<?= $oc['color'] ?>">
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
