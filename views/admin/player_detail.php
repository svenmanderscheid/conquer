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
$troops        = [];
$research      = [];
$resources     = [];
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

            // Troops
            try {
                $troopRows = $db->query(
                    "SELECT troop_code, count FROM city_troops WHERE city_id = ? AND count > 0",
                    [$city['id']],
                )->fetchAll();
                foreach ($troopRows as $r) {
                    $troops[(int) $r['troop_code']] = (int) $r['count'];
                }
            } catch (\Throwable) {}

            // Resources
            try {
                $resRow = $db->query(
                    "SELECT food, lumber, stone, gold FROM cities WHERE id = ? LIMIT 1",
                    [$city['id']],
                )->fetch();
                $resources = $resRow ?: [];
            } catch (\Throwable) {}
        }
    } catch (\Throwable) {}

    // Research
    try {
        $researchRows = $db->query(
            "SELECT research_code, level FROM player_research WHERE player_id = ? AND world_id = 1",
            [$playerId],
        )->fetchAll();
        foreach ($researchRows as $r) {
            $research[(string) $r['research_code']] = (int) $r['level'];
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
  <a href="<?= APP_BASE ?>/admin/players" style="color:#64748b;text-decoration:none;font-size:0.8rem;">&larr; Alle Spieler</a>
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
<div class="admin-card" x-data="{ tab: 'general' }">
  <div class="admin-card-header" style="display:flex;gap:0;padding:0;overflow:hidden;">
    <?php foreach ([
        'general'   => '&#9881; Allgemein',
        'resources' => '&#127807; Ressourcen',
        'troops'    => '&#9876; Truppen',
        'buildings' => '&#127963; Geb&auml;ude',
        'research'  => '&#128300; Forschung',
    ] as $key => $label): ?>
      <button
        type="button"
        @click="tab = '<?= $key ?>'"
        :class="tab === '<?= $key ?>' ? 'admin-tab-active' : ''"
        class="admin-tab-btn"
      ><?= $label ?></button>
    <?php endforeach ?>
  </div>

  <!-- TAB: Allgemein -->
  <div x-show="tab === 'general'" class="admin-card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">

    <form method="post" action="<?= APP_BASE ?>/admin/action/grant-gems" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input type="number" name="amount" value="1000" min="1" max="100000" class="admin-input" style="width:100px;">
      <button type="submit" class="admin-btn admin-btn-warning">&#128142; Gems gew&auml;hren</button>
    </form>

    <form method="post" action="<?= APP_BASE ?>/admin/action/grant-shield" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input type="number" name="hours" value="24" min="1" max="168" class="admin-input" style="width:80px;">
      <button type="submit" class="admin-btn" style="background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);">&#128737; Schutzschild</button>
    </form>

    <form method="post" action="<?= APP_BASE ?>/admin/action/ban" onsubmit="return confirm('Spieler wirklich sperren?')" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <input type="text" name="reason" value="Regelversto&szlig;" placeholder="Grund..." class="admin-input" style="width:160px;">
      <button type="submit" class="admin-btn admin-btn-danger">&#128683; Spieler sperren</button>
    </form>

  </div>

  <!-- TAB: Ressourcen -->
  <div x-show="tab === 'resources'" class="admin-card-body">
    <form method="post" action="<?= APP_BASE ?>/admin/action/set-resources">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px;">
        <?php foreach (['food' => '&#127807; Nahrung', 'lumber' => '&#127795; Holz', 'stone' => '&#9970; Stein', 'gold' => '&#128176; Gold'] as $res => $label): ?>
          <div>
            <label style="display:block;font-size:0.75rem;color:#94a3b8;margin-bottom:4px;"><?= $label ?></label>
            <input type="number" name="<?= $res ?>" value="<?= (int) ($resources[$res] ?? 0) ?>" min="0" max="999999999" class="admin-input">
          </div>
        <?php endforeach ?>
      </div>
      <button type="submit" class="admin-btn admin-btn-primary">&#128190; Ressourcen setzen</button>
    </form>
  </div>

  <!-- TAB: Truppen -->
  <div x-show="tab === 'troops'" class="admin-card-body">
    <?php
    $troopDefs = [
        ['code' => 50100101, 'name' => 'Fighter',       'type' => 'Inf', 'tier' => 1],
        ['code' => 50100201, 'name' => 'Warrior',       'type' => 'Inf', 'tier' => 2],
        ['code' => 50100301, 'name' => 'Knight',        'type' => 'Inf', 'tier' => 3],
        ['code' => 50100401, 'name' => 'Guardian',      'type' => 'Inf', 'tier' => 4],
        ['code' => 50100501, 'name' => 'Crusader',      'type' => 'Inf', 'tier' => 5],
        ['code' => 50200101, 'name' => 'Hunter',        'type' => 'Rng', 'tier' => 1],
        ['code' => 50200201, 'name' => 'Longbow Man',   'type' => 'Rng', 'tier' => 2],
        ['code' => 50200301, 'name' => 'Ranger',        'type' => 'Rng', 'tier' => 3],
        ['code' => 50200401, 'name' => 'Crossbow Man',  'type' => 'Rng', 'tier' => 4],
        ['code' => 50200501, 'name' => 'Sniper',        'type' => 'Rng', 'tier' => 5],
        ['code' => 50300101, 'name' => 'Stableman',     'type' => 'Cav', 'tier' => 1],
        ['code' => 50300201, 'name' => 'Horseman',      'type' => 'Cav', 'tier' => 2],
        ['code' => 50300301, 'name' => 'Heavy Cavalry', 'type' => 'Cav', 'tier' => 3],
        ['code' => 50300401, 'name' => 'Iron Cavalry',  'type' => 'Cav', 'tier' => 4],
        ['code' => 50300501, 'name' => 'Dragoon',       'type' => 'Cav', 'tier' => 5],
    ];
    $typeColors = ['Inf' => '#93c5fd', 'Rng' => '#86efac', 'Cav' => '#fcd34d'];
    $currentType = '';
    ?>
    <p style="font-size:0.75rem;color:#64748b;margin-bottom:12px;">Truppenanzahl direkt setzen. 0 = löschen.</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
      <?php
      $grouped = [];
      foreach ($troopDefs as $t) { $grouped[$t['type']][] = $t; }
      foreach ($grouped as $typeName => $list): ?>
        <div>
          <div style="font-size:0.75rem;font-weight:700;color:<?= $typeColors[$typeName] ?>;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;"><?= $typeName === 'Inf' ? '&#9876; Infanterie' : ($typeName === 'Rng' ? '&#127993; Fernkampf' : '&#128014; Kavallerie') ?></div>
          <?php foreach ($list as $t): ?>
            <form method="post" action="<?= APP_BASE ?>/admin/action/add-troops" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
              <input type="hidden" name="troop_code" value="<?= $t['code'] ?>">
              <span style="flex:1;font-size:0.78rem;color:#e2e8f0;">T<?= $t['tier'] ?> <?= htmlspecialchars($t['name']) ?></span>
              <input type="number" name="count" value="<?= (int) ($troops[$t['code']] ?? 0) ?>" min="0" max="1000000" class="admin-input" style="width:90px;">
              <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">&#10003;</button>
            </form>
          <?php endforeach ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- TAB: Gebäude -->
  <div x-show="tab === 'buildings'" class="admin-card-body">
    <?php
    $buildingList = [
        'castle'          => '&#127963; Castle',
        'wall'            => '&#129697; Wall',
        'barrack'         => '&#9876; Barrack',
        'academy'         => '&#128300; Academy',
        'hospital'        => '&#129657; Hospital',
        'farm'            => '&#127807; Farm',
        'lumber_camp'     => '&#127795; Lumber Camp',
        'quarry'          => '&#9970; Quarry',
        'gold_mine'       => '&#128176; Gold Mine',
        'storage'         => '&#128204; Storage',
        'trading_post'    => '&#128722; Trading Post',
        'treasure_house'  => '&#128142; Treasure House',
        'hall_of_alliance'=> '&#128110; Hall of Alliance',
    ];
    ?>
    <p style="font-size:0.75rem;color:#64748b;margin-bottom:12px;">Gebäude-Level direkt setzen (0–30).</p>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
      <?php foreach ($buildingList as $code => $label): ?>
        <form method="post" action="<?= APP_BASE ?>/admin/action/set-building" style="display:flex;gap:8px;align-items:center;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
          <input type="hidden" name="building_code" value="<?= htmlspecialchars($code) ?>">
          <span style="flex:1;font-size:0.8rem;color:#e2e8f0;"><?= $label ?></span>
          <span style="font-size:0.7rem;color:#64748b;min-width:28px;">Lv<?= (int) ($buildings[$code] ?? 0) ?></span>
          <input type="number" name="level" value="<?= (int) ($buildings[$code] ?? 0) ?>" min="0" max="30" class="admin-input" style="width:60px;">
          <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">&#10003;</button>
        </form>
      <?php endforeach ?>
    </div>
  </div>

  <!-- TAB: Forschung -->
  <div x-show="tab === 'research'" class="admin-card-body">
    <?php
    $researchGroups = [
        '&#9876; Kampf — Infanterie' => ['infantry_hp','infantry_def','infantry_atk','infantry_spd','warrior','knight','guardian','crusader','advanced_infantry_hp','advanced_infantry_def','advanced_infantry_atk','advanced_infantry_spd'],
        '&#127993; Kampf — Fernkampf' => ['ranged_hp','ranged_def','ranged_atk','ranged_spd','longbow_man','ranger','crossbow_man','sniper','advanced_ranged_hp','advanced_ranged_def','advanced_ranged_atk','advanced_ranged_spd'],
        '&#128014; Kampf — Kavallerie' => ['cavalry_hp','cavalry_def','cavalry_atk','cavalry_spd','horseman','heavy_cavalry','iron_cavalry','dragoon','advanced_cavalry_hp','advanced_cavalry_def','advanced_cavalry_atk','advanced_cavalry_spd'],
        '&#128101; Kampf — Allgemein' => ['troops_hp','troops_atk','troops_def','troops_spd','troops_storage','march_size','march_limit','hospital_capacity','healing_time_reduced','rally_attack_amount'],
        '&#127807; Produktion' => ['food_production','food_capacity','food_gathering_speed','wood_production','wood_capacity','wood_gathering_speed','stone_production','stone_capacity','stone_gathering_speed','gold_production','gold_capacity','gold_gathering_speed','resource_protect'],
        '&#128736; Erweitert' => ['research_speed','construction_speed','advanced_food_production','advanced_wood_production','advanced_stone_production','advanced_gold_production','resource_production','resource_capacity'],
    ];
    ?>
    <p style="font-size:0.75rem;color:#64748b;margin-bottom:12px;">Forschungs-Level setzen. Aktive Werte werden sofort überschrieben.</p>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
      <?php foreach ($researchGroups as $groupLabel => $codes): ?>
        <div>
          <div style="font-size:0.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;"><?= $groupLabel ?></div>
          <?php foreach ($codes as $code): ?>
            <form method="post" action="<?= APP_BASE ?>/admin/action/set-research" style="display:flex;gap:6px;align-items:center;margin-bottom:5px;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
              <input type="hidden" name="research_code" value="<?= htmlspecialchars($code) ?>">
              <span style="flex:1;font-size:0.75rem;color:#cbd5e1;"><?= htmlspecialchars(str_replace('_', ' ', $code)) ?></span>
              <span style="font-size:0.68rem;color:#64748b;min-width:24px;">Lv<?= (int) ($research[$code] ?? 0) ?></span>
              <input type="number" name="level" value="<?= (int) ($research[$code] ?? 0) ?>" min="0" max="30" class="admin-input" style="width:55px;">
              <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">&#10003;</button>
            </form>
          <?php endforeach ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>

</div>
<?php endif ?>
<?php endif ?>
