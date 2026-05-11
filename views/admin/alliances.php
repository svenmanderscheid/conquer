<?php
declare(strict_types=1);

/**
 * Admin Alliances — Uebersicht aller Allianzen.
 */

use Conquer\Db\Connection;

$alliances = [];
$dbError   = null;

try {
    $db = Connection::getInstance();
    $alliances = $db->query(
        "SELECT a.id, a.tag, a.name, a.leader_id, p.username AS leader_name,
                COUNT(am.player_id) AS member_count, a.created_at
           FROM alliances a
           LEFT JOIN players p ON p.id = a.leader_id
           LEFT JOIN alliance_members am ON am.alliance_id = a.id
          GROUP BY a.id
          ORDER BY member_count DESC
          LIMIT 50"
    )->fetchAll();
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<?php if ($dbError !== null): ?>
  <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 16px;color:#f87171;font-size:0.82rem;margin-bottom:20px;">
    Datenbankfehler: <?= htmlspecialchars($dbError) ?>
  </div>
<?php endif ?>

<div class="admin-card">
  <div class="admin-card-header">Allianzen (Top 50 nach Mitgliederzahl)</div>

  <?php if (empty($alliances)): ?>
    <div class="admin-empty">Noch keine Allianzen vorhanden.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tag</th>
          <th>Name</th>
          <th>Leader</th>
          <th>Mitglieder</th>
          <th>Gegr&uuml;ndet</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($alliances as $a): ?>
          <tr>
            <td style="color:#475569;"><?= (int) $a['id'] ?></td>
            <td>
              <span class="admin-tag admin-tag-blue">[<?= htmlspecialchars($a['tag']) ?>]</span>
            </td>
            <td><?= htmlspecialchars($a['name']) ?></td>
            <td>
              <a href="<?= APP_BASE ?>/admin/players?q=<?= urlencode($a['leader_name'] ?? '') ?>" style="color:#93c5fd;text-decoration:none;">
                <?= htmlspecialchars($a['leader_name'] ?? '—') ?>
              </a>
            </td>
            <td><?= (int) $a['member_count'] ?></td>
            <td style="font-size:0.75rem;color:#64748b;">
              <?= htmlspecialchars(substr($a['created_at'], 0, 10)) ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
