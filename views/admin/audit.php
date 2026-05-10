<?php
declare(strict_types=1);

/**
 * Admin Audit Log — Letzte 100 Aktionen aller Admins.
 */

use Conquer\Db\Connection;

$entries = [];
$dbError = null;

try {
    $db = Connection::getInstance();

    $entries = $db->query(
        "SELECT al.id, au.username, al.action, al.target_type, al.target_id,
                al.details, al.ip, al.created_at
           FROM admin_audit_log al
           LEFT JOIN admin_users au ON au.id = al.admin_id
          ORDER BY al.created_at DESC
          LIMIT 100"
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
  <div class="admin-card-header">Letzte 100 Audit-Eintr&auml;ge</div>

  <?php if (empty($entries)): ?>
    <div class="admin-empty">Noch keine Audit-Eintr&auml;ge vorhanden.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Admin</th>
          <th>Aktion</th>
          <th>Ziel</th>
          <th>Details</th>
          <th>IP</th>
          <th>Zeitpunkt</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td style="color:#475569;"><?= (int) $e['id'] ?></td>
            <td><?= htmlspecialchars($e['username'] ?? '(deleted)') ?></td>
            <td>
              <code style="font-size:0.75rem;background:#0f172a;padding:2px 6px;border-radius:4px;color:#93c5fd;">
                <?= htmlspecialchars($e['action']) ?>
              </code>
            </td>
            <td style="font-size:0.78rem;color:#94a3b8;">
              <?php if ($e['target_type'] !== null): ?>
                <?= htmlspecialchars($e['target_type']) ?> #<?= (int) $e['target_id'] ?>
              <?php else: ?>
                &mdash;
              <?php endif ?>
            </td>
            <td style="font-size:0.75rem;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= htmlspecialchars($e['details'] ?? '') ?>">
              <?= htmlspecialchars($e['details'] ?? '') ?>
            </td>
            <td style="font-size:0.75rem;color:#475569;"><?= htmlspecialchars($e['ip'] ?? '') ?></td>
            <td style="font-size:0.75rem;color:#64748b;white-space:nowrap;">
              <?= htmlspecialchars(substr($e['created_at'], 0, 16)) ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
