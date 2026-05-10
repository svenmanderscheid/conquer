<?php
declare(strict_types=1);

/**
 * Admin Chat Moderation — Letzte 100 World Chat Messages.
 */

use Conquer\Db\Connection;
use Conquer\Auth\AdminAuth;

$messages = [];
$dbError  = null;
$csrf     = $_SESSION['admin_csrf'] ?? (function() {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['admin_csrf'];
})();

try {
    $db = Connection::getInstance();

    // Try world_chat or chat_messages table name
    try {
        $messages = $db->query(
            "SELECT wc.id, p.username, wc.message, wc.created_at
               FROM world_chat wc
               JOIN players p ON p.id = wc.player_id
              ORDER BY wc.created_at DESC
              LIMIT 100"
        )->fetchAll();
    } catch (\Throwable) {
        try {
            $messages = $db->query(
                "SELECT cm.id, p.username, cm.message, cm.created_at
                   FROM chat_messages cm
                   JOIN players p ON p.id = cm.player_id
                  ORDER BY cm.created_at DESC
                  LIMIT 100"
            )->fetchAll();
        } catch (\Throwable $inner) {
            $dbError = 'Chat-Tabelle noch nicht vorhanden: ' . $inner->getMessage();
        }
    }
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}
?>

<?php if ($dbError !== null): ?>
  <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:14px 16px;color:#f87171;font-size:0.82rem;margin-bottom:20px;">
    <?= htmlspecialchars($dbError) ?>
  </div>
<?php endif ?>

<div class="admin-card">
  <div class="admin-card-header">Letzte 100 Chat-Nachrichten</div>

  <?php if (empty($messages)): ?>
    <div class="admin-empty">Keine Chat-Nachrichten vorhanden.</div>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Spieler</th>
          <th>Nachricht</th>
          <th>Zeitpunkt</th>
          <?php if (($adminSession['role'] ?? '') === 'superadmin'): ?>
            <th>Aktion</th>
          <?php endif ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($messages as $msg): ?>
          <tr>
            <td style="color:#475569;"><?= (int) $msg['id'] ?></td>
            <td>
              <a href="/admin/players?q=<?= urlencode($msg['username']) ?>" style="color:#93c5fd;text-decoration:none;">
                <?= htmlspecialchars($msg['username']) ?>
              </a>
            </td>
            <td style="max-width:400px;word-break:break-word;"><?= htmlspecialchars($msg['message']) ?></td>
            <td style="font-size:0.75rem;color:#64748b;white-space:nowrap;">
              <?= htmlspecialchars(substr($msg['created_at'], 0, 16)) ?>
            </td>
            <?php if (($adminSession['role'] ?? '') === 'superadmin'): ?>
              <td>
                <form method="post" action="/admin/action/delete-chat" onsubmit="return confirm('Nachricht l\u00f6schen?')">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
                  <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">L&ouml;schen</button>
                </form>
              </td>
            <?php endif ?>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
