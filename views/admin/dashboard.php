<?php
declare(strict_types=1);
$state=\Conquer\Game\World\WorldSettings::get($selectedWorld);$cfg=$state['settings'];
$counts=['players'=>(int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=?',[$selectedWorld])->fetchColumn(),'resources'=>(int)$db->query('SELECT COUNT(*) FROM field_objects WHERE world_id=? AND resource_amount>0 AND expires_at>UTC_TIMESTAMP()',[$selectedWorld])->fetchColumn(),'monsters'=>(int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=? AND hp_current>0',[$selectedWorld])->fetchColumn(),'bugs'=>(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE world_id=? AND status IN ('new','in_progress')",[$selectedWorld])->fetchColumn()];
$runs=$db->query('SELECT * FROM world_spawn_runs WHERE world_id=? ORDER BY id DESC LIMIT 5',[$selectedWorld])->fetchAll();
?>
<div class="quick-actions">
<?php foreach([
    ['/rewards','items/chest-gold.svg','Beute festlegen','Monster, Dungeons, Truhen und Feldzüge. Bestimme Gegenstände, Mengen und Chancen.','Beuteverwaltung öffnen'],
    ['/items','hud/inventory.svg','Gegenstände entdecken','Finde Items über ihre Bilder, Seltenheit und Wirkung.','Bildkatalog öffnen'],
    ['/players?world_id='.$selectedWorld,'knight.png','Spielern helfen','Konten suchen, Fortschritt prüfen und Geschenke mit persönlicher Nachricht senden.','Spieler suchen'],
    ['/bug-reports?world_id='.$selectedWorld,'hud/quest.svg','Bugmeldungen prüfen','Neue Meldungen aus dem Spiel priorisieren, untersuchen und abschließen.','Meldungen öffnen']
] as [$path,$art,$label,$description,$action]): ?><a class="quick-action" href="<?= APP_BASE ?>/admin<?= ah($path) ?>"><?= adminIcon($art) ?><h2><?= ah($label) ?></h2><p><?= ah($description) ?></p><span><?= ah($action) ?> →</span></a><?php endforeach ?>
</div>
<h2 class="dashboard-world-heading"><?= ah($world['name']??'Deine Welt') ?> im Überblick</h2>
<div class="stats"><?php foreach(['players'=>['Spieler','Städte in dieser Welt','knight.png'],'resources'=>['Rohstoffvorkommen','Aktiv auf der Karte','ui-resources/gold.png'],'monsters'=>['Monster','Solo- und Rally-Ziele','hud/expeditions.svg'],'bugs'=>['Offene Bugmeldungen','Neu oder in Bearbeitung','hud/quest.svg']] as $key=>[$label,$sub,$art]): ?><div class="stat"><?= adminIcon($art) ?><small><?= ah($label) ?></small><strong><?= an($counts[$key]) ?></strong><span><?= ah($sub) ?></span></div><?php endforeach ?></div>
<div class="grid"><section class="card"><div class="split"><h2>Leben auf der Weltkarte</h2><span class="pill <?= ah($world['status']) ?>"><?= ah(['open'=>'Offen','running'=>'Aktiv','paused'=>'Pausiert','closed'=>'Geschlossen'][$world['status']]??$world['status']) ?></span></div><p>So viele Ziele sind vorhanden und geplant.</p>
<?php foreach(['resource'=>'Rohstoffvorkommen','monster'=>'Monster'] as $kind=>$label): $target=min($cfg[$kind.'_limit'],floor($world['map_size']**2*$cfg[$kind.'_density_pct']/100));$current=$counts[$kind==='resource'?'resources':'monsters']; ?><div class="split"><span><?= $label ?></span><strong><?= an($current) ?> / <?= an($target) ?></strong></div><div class="progress"><i style="width:<?= min(100,$target>0?$current/$target*100:0) ?>%"></i></div><?php endforeach ?>
<a class="button secondary" href="<?= APP_BASE ?>/admin/world?world_id=<?= $selectedWorld ?>">Welten & Spawns bearbeiten →</a></section>
<section class="card"><h2>Automatische Auffüllung</h2><p>Neue Rohstoffvorkommen und Monster erscheinen nach deinem Zeitplan.</p><div class="split"><span>Zustand</span><strong><?= $cfg['enabled']?'Eingeschaltet':'Pausiert' ?></strong></div><hr><div class="split"><span>Nächster Termin</span><strong><?= ah($state['next_run_at']??'Noch nicht geplant') ?></strong></div><div class="split"><span>Letzter Lauf</span><strong><?= ah($state['last_run_at']??'Noch kein Lauf') ?></strong></div><p class="subtle">Prüfung alle <?= (int)$cfg['interval_minutes'] ?> Minuten · Zeiten in UTC</p></section></div>
<details class="card dashboard-secondary"><summary>Letzte Spawnläufe ansehen</summary><?php require ROOT_DIR.'/views/admin/spawn_runs.php'; ?></details>
<a class="button secondary" href="<?= APP_BASE ?>/admin/audit"><?= adminIcon('hud/reports.svg') ?> Änderungen nachvollziehen</a>
