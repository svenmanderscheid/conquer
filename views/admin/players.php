<?php
declare(strict_types=1);

/**
 * Admin Players — Spieler-Suche und paginierte Liste.
 */

use Conquer\Db\Connection;

$perPage    = 20;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$search     = trim($_GET['q'] ?? '');
$searchId   = isset($_GET['id']) && $_GET['id'] !== '' ? (int) $_GET['id'] : null;

$players    = [];
$totalCount = 0;
$dbError    = null;

try {
    $db = Connection::getInstance();

    if ($searchId !== null) {
        // Search by ID
        $players = $db->query(
            "SELECT p.id, p.username, p.lord_level, p.vip_level, p.gems,
                    p.created_at, p.last_active_at,
                    COALESCE(cb.level, 1) AS castle_level,
                    COALESCE(cb2.power, 0) AS power
               FROM players p
               LEFT JOIN cities c ON c.player_id = p.id
               LEFT JOIN city_buildings cb ON cb.city_id = c.id AND cb.building_code = 'castle'
               LEFT JOIN cities c2 ON c2.player_id = p.id
               LEFT JOIN (SELECT city_id, SUM(level * 100) AS power FROM city_buildings GROUP BY city_id) cb2
                      ON cb2.city_id = c.id
              WHERE p.id = ?
              LIMIT 1",
            [$searchId],
        )->fetchAll();
        $totalCount = count($players);

    } elseif ($search !== '') {
        // Search by username (LIKE)
        $like = '%' . $search . '%';
        $totalCount = (int) $db->query(
            "SELECT COUNT(*) FROM players WHERE username LIKE ?",
            [$like],
        )->fetchColumn();

        $players = $db->query(
            "SELECT p.id, p.username, p.lord_level, p.vip_level, p.gems,
                    p.created_at, p.last_active_at,
                    COALESCE(cb.level, 1) AS castle_level
               FROM players p
               LEFT JOIN cities c ON c.player_id = p.id
               LEFT JOIN city_buildings cb ON cb.city_id = c.id AND cb.building_code = 'castle'
              WHERE p.username LIKE ?
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?",
            [$like, $perPage, $offset],
        )->fetchAll();

    } else {
        // All players
        $totalCount = (int) $db->query("SELECT COUNT(*) FROM players")->fetchColumn();

        $players = $db->query(
            "SELECT p.id, p.username, p.lord_level, p.vip_level, p.gems,
                    p.created_at, p.last_active_at,
                    COALESCE(cb.level, 1) AS castle_level
               FROM players p
               LEFT JOIN cities c ON c.player_id = p.id
               LEFT JOIN city_buildings cb ON cb.city_id = c.id AND cb.building_code = 'castle'
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?",
            [$perPage, $offset],
        )->fetchAll();
    }

} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;

function buildPageUrl(int $p, string $q, ?int $id): string {
    $params = ['page' => $p];
    if ($q !== '')       $params['q']  = $q;
    if ($id !== null)    $params['id'] = $id;
    return '/admin/players?' . http_build_query($params);
}
?>

<!-- Search -->
<div class="admin-card" style="margin-bottom:20px;">
  <div class="admin-card-body">
    <form method="get" action="<?= APP_BASE ?>/admin/players" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <div class="admin-form-group" style="flex:1;min-width:180px;margin-bottom:0;">
        <label>Username suchen</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Spieler suchen...">
      </div>
      <div class="admin-form-group" style="width:120px;margin-bottom:0;">
        <label>Spieler-ID</label>
        <input type="number" name="id" value="<?= $searchId !== null ? $searchId : '' ?>" placeholder="ID...">
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="submit" class="admin-btn admin-btn-primary">Suchen</button>
        <?php if ($search !== '' || $searchId !== null): ?>
          <a href="<?= APP_BASE ?>/admin/players" class="admin-btn" style="background:#334155;color:#94a3b8;">Zur&uuml;cksetzen</a>
        <?php endif ?>
      </div>
    </form>
  </div>
</div>

<?php if ($dbError !== null): ?>
  <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 16px;color:#f87171;font-size:0.82rem;margin-bottom:20px;">
    Datenbankfehler: <?= htmlspecialchars($dbError) ?>
  </div>
<?php endif ?>

<!-- Player list -->
<div class="admin-card">
  <div class="admin-card-header">
    <?php if ($search !== ''): ?>
      Suchergebnisse f&uuml;r &laquo;<?= htmlspecialchars($search) ?>&raquo; &mdash;
    <?php elseif ($searchId !== null): ?>
      Spieler ID <?= $searchId ?> &mdash;
    <?php endif ?>
    <?= number_format($totalCount) ?> Spieler
  </div>

  <?php if (empty($players)): ?>
    <div class="admin-empty">Keine Spieler gefunden.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Castle</th>
          <th>Lord Lv.</th>
          <th>VIP</th>
          <th>Gems</th>
          <th>Registriert</th>
          <th>Zuletzt aktiv</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($players as $p): ?>
          <tr>
            <td style="color:#475569;"><?= (int) $p['id'] ?></td>
            <td><?= htmlspecialchars($p['username']) ?></td>
            <td>Lv. <?= (int) $p['castle_level'] ?></td>
            <td><?= (int) $p['lord_level'] ?></td>
            <td><?= (int) $p['vip_level'] ?></td>
            <td><?= number_format((int) $p['gems']) ?></td>
            <td style="font-size:0.75rem;color:#64748b;"><?= htmlspecialchars(substr($p['created_at'] ?? '', 0, 10)) ?></td>
            <td style="font-size:0.75rem;color:#64748b;">
              <?= $p['last_active_at'] ? htmlspecialchars(substr($p['last_active_at'], 0, 16)) : '&mdash;' ?>
            </td>
            <td>
              <a href="<?= APP_BASE ?>/admin/players/<?= (int) $p['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Details</a>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1 && $searchId === null): ?>
      <div style="padding:14px 18px;border-top:1px solid #334155;">
        <div class="admin-pagination">
          <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(buildPageUrl($page - 1, $search, $searchId)) ?>">&laquo; Zur&uuml;ck</a>
          <?php endif ?>

          <?php
            $rangeStart = max(1, $page - 2);
            $rangeEnd   = min($totalPages, $page + 2);
            for ($i = $rangeStart; $i <= $rangeEnd; $i++):
          ?>
            <?php if ($i === $page): ?>
              <span><?= $i ?></span>
            <?php else: ?>
              <a href="<?= htmlspecialchars(buildPageUrl($i, $search, $searchId)) ?>"><?= $i ?></a>
            <?php endif ?>
          <?php endfor ?>

          <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(buildPageUrl($page + 1, $search, $searchId)) ?>">Weiter &raquo;</a>
          <?php endif ?>

          <span style="margin-left:8px;font-size:0.72rem;color:#475569;">
            Seite <?= $page ?> von <?= $totalPages ?>
          </span>
        </div>
      </div>
    <?php endif ?>
  <?php endif ?>
</div>
