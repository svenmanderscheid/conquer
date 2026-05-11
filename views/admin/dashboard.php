<?php
declare(strict_types=1);

/**
 * Admin Dashboard — Live-Statistiken und letzte Aktivitaet.
 *
 * Rendered via AdminController::render(), so $adminSession is available.
 */

use Conquer\Db\Connection;

// ---- Load stats ----
$activeToday          = 0;
$registrationsToday   = 0;
$activeMarches        = 0;
$unreads              = 0;
$recentPlayers        = [];
$recentBattles        = [];
$totalPlayers         = 0;
$dbError              = null;

try {
    $db = Connection::getInstance();

    $activeToday = (int) $db->query(
        "SELECT COUNT(*) FROM players WHERE last_active_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)"
    )->fetchColumn();

    $registrationsToday = (int) $db->query(
        "SELECT COUNT(*) FROM players WHERE DATE(created_at) = UTC_DATE()"
    )->fetchColumn();

    $totalPlayers = (int) $db->query("SELECT COUNT(*) FROM players")->fetchColumn();

    try {
        $activeMarches = (int) $db->query(
            "SELECT COUNT(*) FROM marches WHERE state IN ('marching','returning')"
        )->fetchColumn();
    } catch (\Throwable) { $activeMarches = 0; }

    try {
        $unreads = (int) $db->query(
            "SELECT COUNT(*) FROM battle_reports WHERE attacker_read = 0"
        )->fetchColumn();
    } catch (\Throwable) { $unreads = 0; }

    $recentPlayers = $db->query(
        "SELECT p.id, p.username, p.created_at,
                COALESCE(cb.level, 1) AS castle_level
           FROM players p
           LEFT JOIN cities c ON c.player_id = p.id
           LEFT JOIN city_buildings cb ON cb.city_id = c.id AND cb.building_code = 'castle'
          ORDER BY p.created_at DESC
          LIMIT 10"
    )->fetchAll();

    try {
        $recentBattles = $db->query(
            "SELECT p1.username AS attacker,
                    COALESCE(p2.username, '(NPC)') AS defender,
                    br.outcome,
                    br.created_at
               FROM battle_reports br
               JOIN players p1 ON p1.id = br.attacker_id
               LEFT JOIN cities c ON c.id = br.target_id
               LEFT JOIN players p2 ON p2.id = c.player_id
              WHERE br.target_type = 2
              ORDER BY br.created_at DESC
              LIMIT 10"
        )->fetchAll();
    } catch (\Throwable) { $recentBattles = []; }

} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<?php if ($dbError !== null): ?>
  <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 16px;color:#f87171;font-size:0.82rem;margin-bottom:20px;">
    Datenbankfehler: <?= htmlspecialchars($dbError) ?>
  </div>
<?php endif ?>

<!-- Stats Grid -->
<div class="admin-stats">
  <div class="admin-stat">
    <div class="admin-stat-label">Aktive Spieler (24h)</div>
    <div class="admin-stat-value"><?= number_format($activeToday) ?></div>
    <div class="admin-stat-sub">von <?= number_format($totalPlayers) ?> gesamt</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-label">Registrierungen heute</div>
    <div class="admin-stat-value"><?= number_format($registrationsToday) ?></div>
    <div class="admin-stat-sub"><?= gmdate('Y-m-d') ?> UTC</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-label">Aktive Maersche</div>
    <div class="admin-stat-value"><?= number_format($activeMarches) ?></div>
    <div class="admin-stat-sub">marching + returning</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-label">Ungelesene Battle Reports</div>
    <div class="admin-stat-value"><?= number_format($unreads) ?></div>
    <div class="admin-stat-sub">attacker_read = 0</div>
  </div>
</div>

<!-- Recent Registrations -->
<div class="admin-card">
  <div class="admin-card-header">Letzte 10 Registrierungen</div>
  <?php if (empty($recentPlayers)): ?>
    <div class="admin-empty">Noch keine Spieler registriert.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Castle Level</th>
          <th>Registriert</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentPlayers as $p): ?>
          <tr>
            <td><?= (int) $p['id'] ?></td>
            <td><?= htmlspecialchars($p['username']) ?></td>
            <td><?= (int) $p['castle_level'] ?></td>
            <td><?= htmlspecialchars($p['created_at']) ?></td>
            <td>
              <a href="<?= APP_BASE ?>/admin/players/<?= (int) $p['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Details</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<!-- Recent Battles -->
<div class="admin-card">
  <div class="admin-card-header">Letzte 10 Kaempfe (PvP)</div>
  <?php if (empty($recentBattles)): ?>
    <div class="admin-empty">Noch keine PvP-Kaempfe.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Angreifer</th>
          <th>Verteidiger</th>
          <th>Ergebnis</th>
          <th>Zeitpunkt</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentBattles as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['attacker']) ?></td>
            <td><?= htmlspecialchars($b['defender']) ?></td>
            <td>
              <?php
                $outcome = $b['outcome'] ?? '';
                $tagClass = match ($outcome) {
                    'victory' => 'admin-tag-green',
                    'defeat'  => 'admin-tag-red',
                    default   => 'admin-tag-blue',
                };
              ?>
              <span class="admin-tag <?= $tagClass ?>"><?= htmlspecialchars(ucfirst($outcome)) ?></span>
            </td>
            <td><?= htmlspecialchars($b['created_at']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
