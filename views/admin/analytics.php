<?php
declare(strict_types=1);
$days=in_array((int)($_GET['days']??7),[1,7,30,90],true)?(int)($_GET['days']??7):7;
$since=gmdate('Y-m-d H:i:s',time()-$days*86400);
$onlineNow=(int)$db->query('SELECT COUNT(DISTINCT s.player_id) FROM sessions s JOIN cities c ON c.player_id=s.player_id AND c.world_id=? WHERE s.last_active>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) AND s.expires_at>UTC_TIMESTAMP()',[$selectedWorld])->fetchColumn();
$activePlayers=(int)$db->query('SELECT COUNT(DISTINCT player_id) FROM player_activity_minutes WHERE world_id=? AND minute_slot>=?',[$selectedWorld,$since])->fetchColumn();
$onlineMinutes=(int)$db->query('SELECT COUNT(*) FROM player_activity_minutes WHERE world_id=? AND minute_slot>=?',[$selectedWorld,$since])->fetchColumn();
$kills=(int)$db->query('SELECT COUNT(*) FROM monster_kill_receipts WHERE world_id=? AND created_at>=?',[$selectedWorld,$since])->fetchColumn();
$killRows=$db->query('SELECT reward_snapshot_json FROM monster_kill_receipts WHERE world_id=? AND created_at>=?',[$selectedWorld,$since])->fetchAll(PDO::FETCH_COLUMN);
$dropUnits=0;$dropKinds=0;foreach($killRows as $json){$reward=json_decode((string)$json,true)?:[];foreach($reward as $value){if(is_numeric($value)&&$value>0){$dropUnits+=(float)$value;$dropKinds++;}elseif(is_array($value))foreach($value as $amount)if(is_numeric($amount)&&$amount>0){$dropUnits+=(float)$amount;$dropKinds++;}}}
$gathers=$db->query("SELECT haul_json FROM marches WHERE world_id=? AND player_id>0 AND march_type=9 AND state='complete' AND return_time>=?",[$selectedWorld,$since])->fetchAll(PDO::FETCH_COLUMN);
$farmed=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0,'gems'=>0];foreach($gathers as $json){$haul=json_decode((string)$json,true)?:[];foreach(($haul['loot']??[]) as $key=>$amount)if(isset($farmed[$key])&&is_numeric($amount))$farmed[$key]+=(int)$amount;}
$attacks=(int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE world_id=? AND created_at>=? AND defender_id IS NOT NULL',[$selectedWorld,$since])->fetchColumn();
$messages=(int)$db->query('SELECT (SELECT COUNT(*) FROM world_chat WHERE world_id=? AND created_at>=?)+(SELECT COUNT(*) FROM private_chat_messages WHERE world_id=? AND created_at>=?)',[$selectedWorld,$since,$selectedWorld,$since])->fetchColumn();
$top=$db->query('SELECT p.id,p.username,COUNT(*) kills FROM monster_kill_receipts m JOIN players p ON p.id=m.winner_player_id WHERE m.world_id=? AND m.created_at>=? GROUP BY p.id,p.username ORDER BY kills DESC,p.username LIMIT 10',[$selectedWorld,$since])->fetchAll();
?>
<form method="get" class="analytics-filter card"><input type="hidden" name="world_id" value="<?= $selectedWorld ?>"><label>Zeitraum<select name="days"><?php foreach([1=>'24 Stunden',7=>'7 Tage',30=>'30 Tage',90=>'90 Tage'] as $value=>$label): ?><option value="<?= $value ?>" <?= $days===$value?'selected':'' ?>><?= ah($label) ?></option><?php endforeach ?></select></label><button type="submit">Auswerten</button><span class="subtle">UTC · ab <?= ah($since) ?></span></form>
<div class="stats analytics-stats">
<?php foreach([
 ['Jetzt online',$onlineNow,'in den letzten 5 Minuten'],['Aktive Spieler',$activePlayers,$days.' Tage'],['Spielzeit',round($onlineMinutes/60,1).' h','protokollierte aktive Minuten'],['Monster getötet',$kills,'bestätigte Abschlüsse'],['Farmmärsche',count($gathers),'abgeschlossene Rückkehr'],['PvP-Angriffe',$attacks,'mit Spieler als Verteidiger'],['Nachrichten',$messages,'Welt- und Privatnachrichten'],['Ø Dropmenge',$kills?number_format($dropUnits/$kills,2,',','.'):'—','Einheiten je Monster']
] as [$label,$value,$sub]): ?><div class="stat"><small><?= ah($label) ?></small><strong><?= ah($value) ?></strong><span><?= ah($sub) ?></span></div><?php endforeach ?>
</div>
<div class="grid"><section class="card"><h2>Gefarmte Ressourcen</h2><p>Aus der tatsächlichen Beute abgeschlossener Sammelmärsche.</p><?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Kristalle'] as $key=>$label): ?><div class="split"><span><?= ah($label) ?></span><strong><?= an($farmed[$key]) ?></strong></div><?php endforeach ?></section>
<section class="card"><h2>Aktivste Monsterjäger</h2><?php if(!$top): ?><p class="empty">Keine bestätigten Monsterkills im Zeitraum.</p><?php else: ?><?php foreach($top as $row): ?><div class="split"><a href="<?= APP_BASE ?>/admin/players/<?= (int)$row['id'] ?>?world_id=<?= $selectedWorld ?>"><?= ah($row['username']) ?></a><strong><?= an($row['kills']) ?></strong></div><?php endforeach ?><?php endif ?><p class="subtle">Ø Dropmenge basiert auf dem beim Kill gespeicherten Belohnungs-Snapshot. Chancen ohne tatsächlichen Drop werden nicht als Drop gezählt.</p></section></div>
