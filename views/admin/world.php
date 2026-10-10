<?php
declare(strict_types=1);
$state=\Conquer\Game\World\WorldSettings::get($selectedWorld);$cfg=$state['settings'];
$runs=$db->query('SELECT * FROM world_spawn_runs WHERE world_id=? ORDER BY id DESC LIMIT 20',[$selectedWorld])->fetchAll();
$recipientCount=(int)$db->query('SELECT COUNT(*) FROM cities c JOIN players p ON p.id=c.player_id WHERE c.world_id=? AND p.is_banned=0',[$selectedWorld])->fetchColumn();
$mapProfile=\Conquer\Game\World\WorldMapProfile::forWorld($selectedWorld);

$worldSections=['overview'=>'overview','settings'=>'system','spawns'=>'drops','territories'=>'worlds','events'=>'access','gifts'=>'catalog','activity'=>'overview','management'=>'system'];
$worldText=static fn(string $key):string=>'<span data-i18n="admin.world_workspace.'.$key.'">'.\Conquer\Game\Locale::html('admin.world_workspace.'.$key).'</span>';
?>
<div class="world-workspace" data-world-workspace data-world-id="<?= $selectedWorld ?>">
    <section class="card world-summary" aria-labelledby="world-summary-title">
        <div><p class="world-eyebrow"><?= $worldText('editing') ?> · #<?= $selectedWorld ?></p><h2 id="world-summary-title" data-user-content><?= ah($world['name']) ?></h2></div>
        <span class="pill <?= ah($world['status']) ?>"><?= ah($world['status']) ?></span>
    </section>
    <div class="world-workspace-layout">
        <nav class="world-section-nav" aria-label="<?= \Conquer\Game\Locale::html('admin.world_workspace.nav') ?>" data-i18n-attrs="aria-label:admin.world_workspace.nav">
            <?php foreach($worldSections as $section=>$icon): ?>
            <a href="#world-<?= $section ?>" data-world-link="<?= $section ?>"><?= adminUiIcon($icon) ?><?= $worldText($section) ?></a>
            <?php endforeach ?>
        </nav>
        <div class="world-workspace-content">
            <section id="world-overview" data-world-panel="overview" aria-labelledby="world-overview-title">
                <div class="world-panel-heading"><h2 id="world-overview-title" tabindex="-1"><?= $worldText('overview') ?></h2><p><?= $worldText('overview_hint') ?></p></div>
                <dl class="world-metrics">
                    <div><dt><?= $worldText('players') ?></dt><dd><?= an($recipientCount) ?></dd></div>
                    <div><dt><?= $worldText('map_size') ?></dt><dd><?= (int)$mapProfile['width'] ?> × <?= (int)$mapProfile['height'] ?></dd></div>
                    <div><dt><?= $worldText('automatic_spawns') ?></dt><dd><?= $worldText($cfg['enabled']?'enabled':'disabled') ?></dd></div>
                    <div><dt><?= $worldText('next_run') ?></dt><dd class="world-metric-time"><?= $state['next_run_at']?ah($state['next_run_at']).' UTC':$worldText('pending') ?></dd></div>
                </dl>
                <div class="world-shortcuts">
                    <?php foreach(['settings','spawns','territories','events','gifts','activity'] as $section): ?>
                    <a class="card world-shortcut" href="#world-<?= $section ?>"><?= adminUiIcon($worldSections[$section]) ?><div><h3><?= $worldText($section) ?></h3><p><?= $worldText($section.'_hint') ?></p></div><span aria-hidden="true">→</span></a>
                    <?php endforeach ?>
                </div>
            </section>
            <?php adminForm('world-save',$selectedWorld); ?>
                <section class="card" id="world-settings" data-world-panel="settings" aria-labelledby="world-settings-title">
                    <h2 id="world-settings-title" tabindex="-1"><?= $worldText('settings') ?></h2><p><?= $worldText('settings_hint') ?></p>
                    <div class="fields"><label>Weltname<input name="name" value="<?= ah($world['name']) ?>" required minlength="2" maxlength="50"></label><label>Weltstatus<select name="status"><?php foreach(['open'=>'Offen','running'=>'Laufend','paused'=>'Pausiert','closed'=>'Geschlossen'] as $key=>$label): ?><option value="<?= $key ?>" <?= $world['status']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label><?php foreach(['speed_factor'=>'Geschwindigkeitsfaktor','gather_factor'=>'Sammelfaktor','haul_factor'=>'Transportfaktor'] as $key=>$label)adminNumber($label,$key,$world[$key],.1,20,'.1'); ?></div>

                </section>
                <section class="card" id="world-spawns" data-world-panel="spawns" aria-labelledby="world-spawns-title">
                    <h2 id="world-spawns-title" tabindex="-1"><?= $worldText('spawns') ?></h2><p><?= $worldText('spawns_hint') ?></p>
                    <h3><?= $worldText('schedule') ?></h3>
                    <label class="check"><input type="checkbox" name="settings[enabled]" value="1" <?= $cfg['enabled']?'checked':'' ?>> <?= $worldText('enable_auto') ?></label><div class="fields"><?php adminNumber('Intervall in Minuten','settings[interval_minutes]',$cfg['interval_minutes'],1,10080);adminNumber('Maximale Spawnversuche pro Lauf','settings[batch_limit]',$cfg['batch_limit'],1,500); ?><label><?= $worldText('window_start') ?><input type="time" name="settings[window_start]" value="<?= ah($cfg['window_start']) ?>" required></label><label><?= $worldText('window_end') ?><input type="time" name="settings[window_end]" value="<?= ah($cfg['window_end']) ?>" required></label></div><div class="hint">Gleiche Start- und Endzeit bedeutet ganztägig. Fenster über Mitternacht sind möglich. Pausierte oder geschlossene Welten erzeugen keine neuen Objekte. Der nächste Worker-Lauf nach dem Speichern übernimmt die Regeln.</div>

                    <h3 class="section-title"><?= $worldText('population_help') ?></h3><p class="subtle"><?= $worldText('spawn_help') ?></p>
                    <div class="world-populations"><?php foreach(['resource'=>'Rohstoffvorkommen / Minen','monster'=>'Monster'] as $kind=>$label): ?><details class="world-group"><summary><?= $worldText($kind==='resource'?'resources':'monsters') ?></summary><div class="world-group-body"><div class="fields"><?php adminNumber('Zieldichte (%)','settings['.$kind.'_density_pct]',$cfg[$kind.'_density_pct'],0,100,'.001');adminNumber('Spawnchance (%)','settings['.$kind.'_chance_pct]',$cfg[$kind.'_chance_pct'],0,100,'.001');adminNumber('Maximal gleichzeitig','settings['.$kind.'_limit]',$cfg[$kind.'_limit'],0,25000);adminNumber('Lebensdauer neuer Objekte (Stunden)','settings['.$kind.'_lifetime_hours]',$cfg[$kind.'_lifetime_hours'],1,720);adminNumber('Minimale Stufe','settings['.$kind.'_level_min]',$cfg[$kind.'_level_min'],$kind==='resource'?1:0,$kind==='resource'?10:20);adminNumber('Maximale Stufe','settings['.$kind.'_level_max]',$cfg[$kind.'_level_max'],$kind==='resource'?1:0,$kind==='resource'?10:20); ?></div><h3 class="section-title">TYPVERTEILUNG · SUMME 100 %</h3><div class="fields"><?php foreach($cfg[$kind.'_weights'] as $type=>$weight)adminNumber((['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Edelsteine','dragon'=>'Drachen · noch nicht aktiv','Magdar'=>'Magdar · noch nicht aktiv','Deathkar'=>'Rallybosse'][$type]??$type).' (%)','settings['.$kind.'_weights]['.$type.']',$weight,0,100); ?></div></div></details><?php endforeach ?></div>

                    <details class="world-group"><summary><?= $worldText('villages') ?></summary><div class="world-group-body">
                    <p>Neutrale Dörfer produzieren verborgene Vorräte. Spähberichte zeigen ausschließlich ihre Garnison; Kämpfe verwenden PvP-Verluste.</p><div class="fields"><?php adminNumber('Zieldichte (%)','settings[village_density_pct]',$cfg['village_density_pct'],0,100,'.001');adminNumber('Spawnchance (%)','settings[village_chance_pct]',$cfg['village_chance_pct'],0,100,'.001');adminNumber('Maximal gleichzeitig','settings[village_limit]',$cfg['village_limit'],0,1000); ?></div><div class="hint">Ressourcenstand, Produktionsfortschritt und letzter Überfall bleiben für Spieler unsichtbar.</div>

                    </div></details>
                    <details class="world-group world-group-help"><summary><?= $worldText('population_help') ?></summary><div class="world-group-body">
                    <div class="hint"><strong>Zieldichte</strong> = Anzahl gewünschter Objekte je 100 Kartenfelder (keine belegte Fläche). <strong>Spawnchance</strong> = Erfolgswahrscheinlichkeit je freiem Spawnplatz pro Lauf. <strong>Verteilung</strong> = Anteile der Typen unter neu erzeugten Objekten; jede Gruppe muss 100 % ergeben. Dichte und Chance sind unabhängig. Obergrenzen, Platzierungsregeln und das gemeinsame Laufbudget begrenzen das Ergebnis.</div>
<div class="hint">Die Monsterstufe folgt der <a href="<?= APP_BASE ?>/admin/lands?world_id=<?= $selectedWorld ?>">Landentwicklung</a>. Die unten gespeicherte minimale und maximale Monsterstufe gilt nur ohne Landentwicklung. Der Verteilungseintrag „Rallybosse“ erzeugt Dämmerhorn oder Runenhorn. Drachen und die alte Magdar-Vorlage sind nicht separat aktiv; ihre alten Verteilungsanteile werden beim Spawnen ausgelassen.</div>
<p class="subtle">Bereits vorhandene Objekte behalten ihren Ablaufzeitpunkt. Niedrigere Zielwerte löschen keine lebenden Objekte. Ziele mit laufenden Märschen oder Koalitionsangriffen bleiben bis zur Rückkehr erhalten. Gibt es einen Monstertyp nicht im Stufenbereich, wird dieser Versuch übersprungen. Konfigurierte Welten verwenden ausschließlich diesen Spawnplan.</p>

                    </div></details>
                </section>
                <section class="card" id="world-territories" data-world-panel="territories" aria-labelledby="world-territories-title">
                    <h2 id="world-territories-title" tabindex="-1"><?= $worldText('territories') ?></h2><p><?= $worldText('territories_hint') ?></p>
                    <h3><?= $worldText('alliance') ?></h3><div class="fields"><?php adminNumber('Radius Allianzzentrum (Felder)','settings[alliance_center_radius]',$cfg['alliance_center_radius'],4,40);adminNumber('Radius Außenposten (Felder)','settings[alliance_outpost_radius]',$cfg['alliance_outpost_radius'],2,24); ?></div><div class="hint">Der Radius wird vom Mittelpunkt des Gebäudes gemessen und gilt sofort für alle bestehenden Allianzgebäude dieser Welt.</div>

                </section>
                <div class="card world-settings-save" data-world-settings-save>
                    <p class="subtle"><?= $worldText('save_hint') ?></p>
                    <label>Begründung für das Änderungsprotokoll<input name="reason" required minlength="3" maxlength="500" placeholder="z. B. Supportkorrektur oder Eventbelohnung"></label>
                    <div class="world-save-actions"><p data-world-dirty-status role="status"><?= $worldText('no_changes') ?></p><button type="submit">Welteinstellungen speichern</button></div>
                </div>
            </fieldset></form>
            <?php if($mapProfile['key']==='luxembourg'): ?><div data-world-panel="territories"><?php require ROOT_DIR.'/views/admin/territory_rules.php'; ?></div><?php endif ?>
            <section id="world-events" data-world-panel="events" aria-labelledby="world-events-title">
                <div class="world-panel-heading"><h2 id="world-events-title" tabindex="-1"><?= $worldText('events') ?></h2><p><?= $worldText('events_hint') ?></p></div>
                <?php require __DIR__.'/extra_event.php'; ?>
                <?php if($mapProfile['key']!=='luxembourg'&&class_exists(\Conquer\Game\Conquest\EventService::class)): $events=\Conquer\Game\Conquest\EventService::settings($selectedWorld); ?><section class="card"><h2>Schrein-Events & Weltinvasionen</h2><p>Freischaltungen und wiederkehrende Eventtermine für diese Welt.</p><?php adminForm('world-events',$selectedWorld); ?><div class="fields"><label class="check"><input type="checkbox" name="enabled" value="1" <?= !empty($events['enabled'])?'checked':'' ?>> Conquest-Events aktivieren</label><label class="check"><input type="checkbox" name="invasion_enabled" value="1" <?= !empty($events['invasion_enabled'])?'checked':'' ?>> Weltinvasionen aktivieren</label><label>Nächstes Conquest-Event (UTC)<input type="datetime-local" name="next_start" value="<?= ah(str_replace(' ','T',substr($events['next_start']??gmdate('Y-m-d H:i:s',time()+86400),0,16))) ?>" required></label><label>Nächste Invasion (UTC)<input type="datetime-local" name="invasion_next_start" value="<?= ah(str_replace(' ','T',substr($events['invasion_next_start']??gmdate('Y-m-d H:i:s',time()+86400),0,16))) ?>" required></label><?php adminNumber('Conquest-Intervall (Stunden)','interval_hours',$events['interval_hours']??336,1,8760);adminNumber('Conquest-Dauer (Stunden)','duration_hours',$events['duration_hours']??168,1,8760);adminNumber('Invasionsintervall (Stunden)','invasion_interval_hours',$events['invasion_interval_hours']??72,1,8760); ?></div><?php adminSubmit('Eventzeitplan speichern'); ?></section><?php endif ?>

            </section>
            <section id="world-gifts" data-world-panel="gifts" aria-label="<?= \Conquer\Game\Locale::html('admin.world_workspace.gifts') ?>" data-i18n-attrs="aria-label:admin.world_workspace.gifts">
                <section class="card"><h2>Geschenk an die gesamte Welt</h2><p><?= an($recipientCount) ?> nicht gesperrte Spieler erhalten die gleichen Belohnungen direkt auf ihr Konto.</p><?php $giftPlayerId=0;require ROOT_DIR.'/views/admin/gift_form.php'; ?></section>
            </section>
            <section class="card" id="world-activity" data-world-panel="activity" aria-labelledby="world-activity-title">
                <h2 id="world-activity-title" tabindex="-1"><?= $worldText('activity') ?></h2><p>Nächster Termin: <?= ah($state['next_run_at']??'Erster Worker-Lauf') ?> UTC · Letzter Lauf: <?= ah($state['last_run_at']??'Noch keiner') ?></p>
                <?php require ROOT_DIR.'/views/admin/spawn_runs.php'; ?>
            </section>
            <section id="world-management" data-world-panel="management" aria-labelledby="world-management-title">
                <div class="world-panel-heading"><h2 id="world-management-title" tabindex="-1"><?= $worldText('management') ?></h2><p><?= $worldText('management_hint') ?></p></div>
                <section class="card world-create-link"><h2>Weitere Welt vorbereiten</h2><p>Name, Spieltempo, Minen, Monster und sämtliche Spawnregeln legst du auf einer eigenen Seite gemeinsam fest.</p><a class="button" href="<?= APP_BASE ?>/admin/world-create">Welt erstellen →</a></section>
                <?php require __DIR__.'/world_delete.php'; ?>
            </section>
        </div>
    </div>
</div>
