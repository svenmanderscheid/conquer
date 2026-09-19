<?php
declare(strict_types=1);
$messages=$db->query('SELECT id,player_id,username,message,created_at FROM world_chat WHERE world_id=? ORDER BY id DESC LIMIT 100',[$selectedWorld])->fetchAll();
?>
<section class="card"><h2>Weltchat · letzte 100 Nachrichten</h2><p>Über das Spielerprofil kannst du bei Bedarf einen Zugang sperren.</p><div class="table-wrap"><table><thead><tr><th>Zeit (UTC)</th><th>Spieler</th><th>Nachricht</th></tr></thead><tbody><?php foreach($messages as $m): ?><tr><td><?= ah($m['created_at']) ?></td><td><a href="<?= APP_BASE ?>/admin/players/<?= (int)$m['player_id'] ?>?world_id=<?= $selectedWorld ?>"><?= ah($m['username']) ?></a></td><td><?= ah($m['message']) ?></td></tr><?php endforeach ?></tbody></table></div><?php if(!$messages): ?><p class="empty">Noch keine Nachrichten in dieser Welt.</p><?php endif ?></section>
