<?php
declare(strict_types=1);

/**
 * Admin World — Stub (Sprint 5+)
 */

use Conquer\Db\Connection;

$worldInfo = null;
try {
    $db = Connection::getInstance();
    $worldInfo = $db->query(
        "SELECT id, name, max_players, speed, created_at FROM worlds LIMIT 1"
    )->fetch() ?: null;
} catch (\Throwable) {}
?>

<div class="admin-card">
  <div class="admin-card-header">Weltverwaltung</div>
  <div class="admin-card-body">
    <?php if ($worldInfo !== null): ?>
      <table class="admin-table" style="max-width:500px;">
        <tr>
          <td style="color:#64748b;padding:8px 12px;font-size:0.8rem;width:140px;">Welt-ID</td>
          <td style="padding:8px 12px;font-size:0.82rem;"><?= (int) $worldInfo['id'] ?></td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 12px;font-size:0.8rem;">Name</td>
          <td style="padding:8px 12px;font-size:0.82rem;"><?= htmlspecialchars($worldInfo['name'] ?? '') ?></td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 12px;font-size:0.8rem;">Max. Spieler</td>
          <td style="padding:8px 12px;font-size:0.82rem;"><?= number_format((int) $worldInfo['max_players']) ?></td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 12px;font-size:0.8rem;">Geschwindigkeit</td>
          <td style="padding:8px 12px;font-size:0.82rem;"><?= htmlspecialchars($worldInfo['speed'] ?? '1.0') ?>&times;</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 12px;font-size:0.8rem;">Erstellt am</td>
          <td style="padding:8px 12px;font-size:0.82rem;"><?= htmlspecialchars($worldInfo['created_at'] ?? '') ?></td>
        </tr>
      </table>
    <?php else: ?>
      <p style="color:#64748b;font-size:0.85rem;">Keine Welt gefunden.</p>
    <?php endif ?>
    <p style="color:#475569;font-size:0.78rem;margin-top:16px;">
      Erweiterte Weltverwaltung (Spawns, Events, Karten-Reset) wird in einem sp&auml;teren Sprint implementiert.
    </p>
  </div>
</div>
