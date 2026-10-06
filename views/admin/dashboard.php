<?php
declare(strict_types=1);
$state=\Conquer\Game\World\WorldSettings::get($selectedWorld);$cfg=$state['settings'];
$counts=['players'=>(int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=?',[$selectedWorld])->fetchColumn(),'resources'=>(int)$db->query('SELECT COUNT(*) FROM field_objects WHERE world_id=? AND resource_amount>0 AND expires_at>UTC_TIMESTAMP()',[$selectedWorld])->fetchColumn(),'monsters'=>(int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=? AND hp_current>0',[$selectedWorld])->fetchColumn(),'villages'=>(int)$db->query('SELECT COUNT(*) FROM neutral_villages WHERE world_id=?',[$selectedWorld])->fetchColumn(),'bugs'=>(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE world_id=? AND status IN ('new','in_progress')",[$selectedWorld])->fetchColumn()];
$runs=$db->query('SELECT * FROM world_spawn_runs WHERE world_id=? ORDER BY id DESC LIMIT 5',[$selectedWorld])->fetchAll();
$alphaWaiting=$canEdit?(int)$db->query('SELECT COUNT(*) FROM alpha_waitlist WHERE invited_at IS NULL')->fetchColumn():0;
?>
<?php
$urgent=(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE world_id=? AND status IN ('new','in_progress') AND priority IN ('high','urgent')",[$selectedWorld])->fetchColumn();
$openWorlds=(int)$db->query("SELECT COUNT(*) FROM worlds WHERE status IN ('open','running')")->fetchColumn();
$recent=$db->query('SELECT a.*,u.username FROM admin_audit_log a LEFT JOIN admin_users u ON u.id=a.admin_id ORDER BY a.id DESC LIMIT 5')->fetchAll();
$historyLabel=static function(string $action):string {
    $key=match(true){
        str_contains($action,'login')||str_contains($action,'logout')=>'session',
        str_contains($action,'password')=>'password',str_contains($action,'reward')=>'drops',
        str_contains($action,'world')||str_contains($action,'land')=>'worlds',
        str_contains($action,'alpha')=>'access',str_contains($action,'bug')||str_contains($action,'community')=>'reports',
        str_contains($action,'layout')=>'layout',str_contains($action,'player')||str_contains($action,'gift')=>'players',
        default=>'change'
    };return \Conquer\Game\Locale::html('admin.modern.history_'.$key);
};
?>
<div class="stats dashboard-metrics"><?php foreach(['world_players'=>$counts['players'],'open_bugs'=>$counts['bugs'],'open_worlds'=>$openWorlds] as $label=>$value): ?><div class="stat"><small><?= \Conquer\Game\Locale::html('admin.modern.'.$label) ?></small><strong><?= an($value) ?></strong><span <?= $label==='open_worlds'?'':'data-user-content' ?>><?= $label==='open_worlds'?\Conquer\Game\Locale::html('admin.modern.all_worlds'):ah($world['name']) ?></span></div><?php endforeach ?></div>
<div class="dashboard-work"><section class="card"><h2><?= \Conquer\Game\Locale::html('admin.modern.attention') ?></h2>
<div class="dashboard-task"><div><strong><?= \Conquer\Game\Locale::html('admin.modern.urgent_reports',['count'=>$urgent]) ?></strong><p class="subtle"><?= $urgent?'':\Conquer\Game\Locale::html('admin.modern.no_attention') ?></p></div><a class="button secondary" href="<?= APP_BASE ?>/admin/bug-reports?world_id=<?= $selectedWorld ?>"><?= \Conquer\Game\Locale::html('admin.modern.open_reports') ?></a></div>
<?php if($canEdit&&$alphaWaiting): ?><div class="dashboard-task"><strong><?= \Conquer\Game\Locale::html('admin.modern.waiting',['count'=>$alphaWaiting]) ?></strong><a class="button secondary" href="<?= APP_BASE ?>/admin/alpha-waitlist"><?= \Conquer\Game\Locale::html('admin.modern.open_waitlist') ?></a></div><?php endif ?>
<div class="dashboard-task"><strong><?= \Conquer\Game\Locale::html('admin.modern.edit_mines') ?></strong><a class="button secondary" href="<?= APP_BASE ?>/admin/rewards?type=farm&amp;world_id=<?= $selectedWorld ?>"><?= \Conquer\Game\Locale::html('admin.modern.nav_drops') ?></a></div></section>
<section class="card"><div class="split"><h2><?= \Conquer\Game\Locale::html('admin.modern.recent') ?></h2><a href="<?= APP_BASE ?>/admin/audit"><?= \Conquer\Game\Locale::html('admin.modern.view_history') ?> ↗</a></div>
<?php foreach($recent as $change): $details=json_decode($change['details']??'{}',true); ?><div class="dashboard-history-row"><div><strong data-user-content><?= ah($change['username']??'—') ?></strong><div><?= $historyLabel($change['action']) ?></div><small class="subtle" data-user-content><?= ah($details['reason']??'') ?></small></div><time datetime="<?= ah(str_replace(' ','T',$change['created_at']).'Z') ?>"><?= ah($change['created_at']) ?> UTC</time></div><?php endforeach ?>
<?php if(!$recent): ?><p class="subtle"><?= \Conquer\Game\Locale::html('admin.modern.no_history') ?></p><?php endif ?></section></div>
<details class="card dashboard-secondary"><summary><?= \Conquer\Game\Locale::html('admin.modern.workspaces') ?></summary>
<div class="quick-actions">
<?php foreach([
    ...($canEdit?[[ '/alpha-waitlist','hud/reports.svg','Alpha-E-Mails ansehen',\Conquer\Game\Locale::t('admin.dashboard.waiting_invitations',['count'=>$alphaWaiting]),'Warteliste öffnen' ]]:[]),
    ['/world-create','hud/city.svg','Welt erstellen','Name, Tempo, Minen, Monster und Spawnregeln in einem Schritt festlegen.','Neue Welt vorbereiten'],
    ['/rewards','items/chest-gold.svg','Beute festlegen','Monster, Dungeons, Truhen und Feldzüge. Bestimme Gegenstände, Mengen und Chancen.','Beuteverwaltung öffnen'],
    ['/items','hud/inventory.svg','Gegenstände entdecken','Finde Items über ihre Bilder, Seltenheit und Wirkung.','Bildkatalog öffnen'],
    ['/players?world_id='.$selectedWorld,'knight.png','Spielern helfen','Konten suchen, Fortschritt prüfen und Geschenke mit persönlicher Nachricht senden.','Spieler suchen'],
    ['/analytics?world_id='.$selectedWorld,'hud/reports.svg','Statistiken auswerten','Online-Aktivität, Farmen, Kämpfe, Monsterkills und Drops nach Zeitraum prüfen.','Statistiken öffnen'],
    ['/bug-reports?world_id='.$selectedWorld,'menu-icons-v2/bug-report.png','Bugmeldungen prüfen','Neue Meldungen aus dem Spiel priorisieren, untersuchen und abschließen.','Meldungen öffnen']
] as [$path,$art,$label,$description,$action]): ?><a class="quick-action" href="<?= APP_BASE ?>/admin<?= ah($path) ?>"><?= adminIcon($art) ?><h2><?= ah($label) ?></h2><p><?= ah($description) ?></p><span><?= ah($action) ?> →</span></a><?php endforeach ?>
</div>
</details>
<h2 class="dashboard-world-heading"><span data-user-content><?= ah($world['name']??'Your world') ?></span> at a glance</h2>
<div class="stats"><?php foreach(['players'=>['Spieler','Städte in dieser Welt','knight.png'],'resources'=>['Rohstoffvorkommen','Aktiv auf der Karte','ui-resources/gold.png'],'monsters'=>['Monster','Solo- und Rally-Ziele','hud/expeditions.svg'],'villages'=>['Freie Dörfer','Verborgene Vorräte','map/castle-default.png'],'bugs'=>['Offene Bugmeldungen','Neu oder in Bearbeitung','menu-icons-v2/bug-report.png']] as $key=>[$label,$sub,$art]): ?><div class="stat"><?= adminIcon($art) ?><small><?= ah($label) ?></small><strong><?= an($counts[$key]) ?></strong><span><?= ah($sub) ?></span></div><?php endforeach ?></div>
<div class="grid"><section class="card"><div class="split"><h2>Leben auf der Weltkarte</h2><span class="pill <?= ah($world['status']) ?>"><?= ah(['open'=>'Offen','running'=>'Aktiv','paused'=>'Pausiert','closed'=>'Geschlossen'][$world['status']]??$world['status']) ?></span></div><p>So viele Ziele sind vorhanden und geplant.</p>
<?php foreach(['resource'=>'Rohstoffvorkommen','monster'=>'Monster','village'=>'Freie Dörfer'] as $kind=>$label): $target=min($cfg[$kind.'_limit'],floor($world['map_size']**2*$cfg[$kind.'_density_pct']/100));$current=$counts[$kind==='resource'?'resources':($kind==='monster'?'monsters':'villages')]; ?><div class="split"><span><?= $label ?></span><strong><?= an($current) ?> / <?= an($target) ?></strong></div><div class="progress"><i style="width:<?= min(100,$target>0?$current/$target*100:0) ?>%"></i></div><?php endforeach ?>
<a class="button secondary" href="<?= APP_BASE ?>/admin/world?world_id=<?= $selectedWorld ?>">Welten & Spawns bearbeiten →</a></section>
<section class="card"><h2>Automatische Auffüllung</h2><p>Neue Rohstoffvorkommen, Monster und freie Dörfer erscheinen nach deinem Zeitplan.</p><div class="split"><span>Zustand</span><strong><?= $cfg['enabled']?'Eingeschaltet':'Pausiert' ?></strong></div><hr><div class="split"><span>Nächster Termin</span><strong><?= ah($state['next_run_at']??'Noch nicht geplant') ?></strong></div><div class="split"><span>Letzter Lauf</span><strong><?= ah($state['last_run_at']??'Noch kein Lauf') ?></strong></div><p class="subtle">Prüfung alle <?= (int)$cfg['interval_minutes'] ?> Minuten · Zeiten in UTC</p></section></div>
<details class="card dashboard-secondary"><summary>Letzte Spawnläufe ansehen</summary><?php require ROOT_DIR.'/views/admin/spawn_runs.php'; ?></details>
<a class="button secondary" href="<?= APP_BASE ?>/admin/audit"><?= adminIcon('hud/reports.svg') ?> Änderungen nachvollziehen</a>
