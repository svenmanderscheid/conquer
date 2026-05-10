<?php
declare(strict_types=1);

/**
 * Admin Player Detail — Einzelner Spieler mit Actions.
 *
 * $playerId comes from AdminController::playerDetail()
 */

use Conquer\Db\Connection;
use Conquer\Auth\AdminAuth;

$player        = null;
$city          = null;
$buildings     = [];
$recentBattles = [];
$oauthEmail    = null;
$dbError       = null;

// CSRF token for action forms
$csrf = $_SESSION['admin_csrf'] ?? (function() {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['admin_csrf'];
})();

try {
    $db = Connection::getInstance();

    $player = $db->query(
        "SELECT id, username, lord_level, vip_level, gems,
                kill_count, lord_xp, created_at, last_active_at
           FROM players
          WHERE id = ?
          LIMIT 1",
        [$playerId],
    )->fetch();

    if ($player === false) {
        http_response_code(404);
        echo '<div class="admin-empty">Spieler nicht gefunden.</div>';
        return;
    }

    // OAuth email
    try {
        $oauthRow  = $db->query(
            "SELECT email FROM oauth_accounts WHERE player_id = ? LIMIT 1",
            [$playerId],
        )->fetch();
        $oauthEmail = $oauthRow['email'] ?? null;
    } catch (\Throwable) {}

    // City
    try {
        $city = $db->query(
            "SELECT c.id, c.x, c.y
               FROM cities c
              WHERE c.player_id = ?
              LIMIT 1",
            [$playerId],
        )->fetch() ?: null;

        if ($city !== null) {
            $buildings = $db->query(
                "SELECT building_code, level FROM city_buildings WHERE city_id = ?",
                [$city['id']],
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
    } catch (\Throwable) {}

    // Recent battles
    try {
        $recentBattles = $db->query(
            "SELECT br.id, br.outcome, br.created_at,
                    CASE WHEN br.attacker_id = ? THEN 'Angreifer' ELSE 'Verteidiger' END AS role
               FROM battle_reports br
              WHERE br.attacker_id = ?
              ORDER BY br.created_at DESC
              LIMIT 5",
            [$playerId, $playerId],
        )->fetchAll();
    } catch (\Throwable) {}

} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$isSuperAdmin = ($adminSession['role'] ?? '') === 'superadmin';

function statRow(string $label, mixed $value): void {
    echo '<tr><td style="color:#64748b;width:150px;padding:8px 12px;font-size:0.8rem;">'
        . htmlspecialchars($label)
        . '</td><td style="padding:8px 12px;font-size:0.82rem;color:#e2e8f0;">'
        . htmlspecialchars((string) $value)
        . '</td></tr>';
}
?>

<div style="margin-bottom:16px;">
  <a href="/admin/players" style="color:#64748b;text-decoration:none;font-size:0.8rem;">&larr; Alle Spieler</a>
</div>

<?php if ($dbError !== null): ?>
  <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 16px;color:#f87171;font-size:0.82rem;margin-bottom:20px;">
    Datenbankfehler: <?= htmlspecialchars($dbError) ?>
  </div>
<?php endif ?>

<?php if ($player !== null): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

  <!-- Player Info -->
  <div class="admin-card">
    <div class="admin-card-header">Spieler-Info</div>
    <table class="admin-table">
      <?php statRow('ID',           $player['id']) ?>
      <?php statRow('Username',     $player['username']) ?>
      <?php statRow('E-Mail',       $oauthEmail ?? '(keine OAuth-Mail)') ?>
      <?php statRow('Lord Level',   $player['lord_level']) ?>
      <?php statRow('VIP Level',    $player['vip_level']) ?>
      <?php statRow('Gems',         number_format((int) $player['gems'])) ?>
      <?php statRow('Kill Count',   number_format((int) $player['kill_count'])) ?>
      <?php statRow('Lord XP',      number_format((int) $player['lord_xp'])) ?>
      <?php statRow('Registriert',  $player['created_at']) ?>
      <?php statRow('Zuletzt aktiv',$player['last_active_at'] ?? '—') ?>
    </table>
  </div>

  <!-- City Info -->
  <div class="admin-card">
    <div class="admin-card-header">Stadt-Info</div>
    <?php if ($city === null): ?>
      <div class="admin-empty">Keine Stadt vorhanden.</div>
    <?php else: ?>
      <table class="admin-table">
        <?php statRow('Stadt-ID', $city['id']) ?>
        <?php statRow('Position', $city['x'] . ' / ' . $city['y']) ?>
        <?php foreach ($buildings as $code => $level): ?>
          <?php statRow(ucfirst(str_replace('_', ' ', $code)), 'Level ' . $level) ?>
        <?php endforeach ?>
      </table>
    <?php endif ?>
  </div>

</div>

<!-- Recent Battles -->
<div class="admin-card" style="margin-bottom:20px;">
  <div class="admin-card-header">Letzte 5 Kaempfe</div>
  <?php if (empty($recentBattles)): ?>
    <div class="admin-empty">Noch keine Kaempfe.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr><th>Report-ID</th><th>Rolle</th><th>Ergebnis</th><th>Zeitpunkt</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentBattles as $b): ?>
          <tr>
            <td><?= (int) $b['id'] ?></td>
            <td><?= htmlspecialchars($b['role']) ?></td>
            <td>
              <?php
                $tagClass = match ($b['outcome'] ?? '') {
                    'victory' => 'admin-tag-green',
                    'defeat'  => 'admin-tag-red',
                    default   => 'admin-tag-blue',
                };
              ?>
              <span class="admin-tag <?= $tagClass ?>"><?= htmlspecialchars(ucfirst($b['outcome'] ?? '')) ?></span>
            </td>
            <td style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars($b['created_at']) ?></td>
            <td>
              <a href="/reports/<?= (int) $b['id'] ?>" target="_blank" class="admin-btn admin-btn-primary admin-btn-sm">Ansehen</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<!-- Superadmin Actions -->
<?php if ($isSuperAdmin): ?>
<div class="admin-card">
  <div class="admin-card-header">Superadmin Aktionen</div>
  <div class="admin-card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">

    <!-- Grant Gems -->
    <form method="post" action="/admin/action/grant-gems" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input
        type="number"
        name="amount"
        value="1000"
        min="1"
        max="100000"
        style="width:100px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;padding:6px 8px;font-size:0.82rem;"
      >
      <button type="submit" class="admin-btn admin-btn-warning">
        &#128142; Gems gew&auml;hren
      </button>
    </form>

    <!-- Grant Shield -->
    <form method="post" action="/admin/action/grant-shield" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input
        type="number"
        name="hours"
        value="24"
        min="1"
        max="168"
        style="width:80px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;padding:6px 8px;font-size:0.82rem;"
      >
      <button type="submit" class="admin-btn" style="background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);">
        &#128737; Schutzschild
      </button>
    </form>

    <!-- Ban Player -->
    <form
      method="post"
      action="/admin/action/ban"
      onsubmit="return confirm('Spieler wirklich sperren?')"
      style="display:flex;gap:8px;align-items:center;"
    >
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input
        type="text"
        name="reason"
        value="Regelversto&szlig;"
        placeholder="Grund..."
        style="width:160px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;padding:6px 8px;font-size:0.82rem;"
      >
      <button type="submit" class="admin-btn admin-btn-danger">
        &#128683; Spieler sperren
      </button>
    </form>

  </div>
</div>
<?php endif ?>
<?php endif ?>
