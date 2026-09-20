<?php
declare(strict_types=1);

$defaults=\Conquer\Game\World\WorldSettings::defaults();
$draft=is_array($_SESSION['admin_world_create_draft']??null)?$_SESSION['admin_world_create_draft']:[];
unset($_SESSION['admin_world_create_draft']);
$draftSettings=is_array($draft['settings']??null)?$draft['settings']:[];
$cfg=array_replace_recursive($defaults,$draftSettings);
$cfg['enabled']=array_key_exists('enabled',$draftSettings)?in_array($draftSettings['enabled'],[true,1,'1','on'],true):$defaults['enabled'];
$value=static fn(string $key,mixed $fallback):mixed=>is_scalar($draft[$key]??null)?$draft[$key]:$fallback;
$resourceLabels=['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Edelsteine'];
$monsterLabels=['Orc'=>'Orks','Skeleton'=>'Skelette','Golem'=>'Golems','Treasure Goblin'=>'Schatzgoblins','Deathkar'=>'Rallybosse','dragon'=>'Drachen · noch nicht aktiv','Magdar'=>'Magdar · noch nicht aktiv'];
?>
<div class="world-create-intro card">
    <div>
        <span class="eyebrow">NEUE SPIELWELT</span>
        <h2>Alles vor dem Start festlegen</h2>
        <p>Die Welt wird mit den Einstellungen dieser Seite vollständig angelegt. Der Status „Pausiert“ lässt dir Zeit für eine letzte Kontrolle, bevor Spieler beitreten.</p>
    </div>
    <div class="world-create-facts" aria-label="Umfang der Einrichtung">
        <span><strong>1</strong><small>Vorgang</small></span>
        <span><strong>256²</strong><small>Kartenfelder</small></span>
        <span><strong>2</strong><small>Spawnarten</small></span>
    </div>
</div>

<?php adminForm('world-create',0); ?>
<div class="world-create-sections" data-world-create>
    <section class="card world-create-section">
        <header><span class="world-create-step">1</span><div><h2>Name & Startzustand</h2><p>Diese Angaben sehen Spieler in der Weltauswahl.</p></div></header>
        <div class="fields">
            <label>Weltname<input name="name" value="<?= ah($value('name','')) ?>" required minlength="2" maxlength="50" placeholder="z. B. Morgenrot" data-world-name></label>
            <label>Eindeutiges Kürzel<input name="slug" value="<?= ah($value('slug','')) ?>" required minlength="2" maxlength="20" pattern="[a-z0-9][a-z0-9-]{1,19}" placeholder="morgenrot" autocapitalize="none" spellcheck="false" data-world-slug><small>2–20 Kleinbuchstaben, Zahlen oder Bindestriche</small></label>
            <label>Weltstatus<select name="status"><?php foreach(['paused'=>'Pausiert · erst prüfen','open'=>'Offen · Beitritt möglich','running'=>'Laufend','closed'=>'Geschlossen'] as $key=>$label): ?><option value="<?= $key ?>" <?= $value('status','paused')===$key?'selected':'' ?>><?= ah($label) ?></option><?php endforeach ?></select></label>
            <label>Kartengröße<select name="map_size" aria-describedby="map-size-hint"><option value="256" selected>256 × 256 Felder · Standard</option></select><small id="map-size-hint">Die gezeichnete Weltkarte und ihre fünf Schreine verwenden derzeit diese feste Größe.</small></label>
        </div>
    </section>

    <section class="card world-create-section">
        <header><span class="world-create-step">2</span><div><h2>Spielgeschwindigkeit</h2><p>Jeder Faktor gilt ab dem ersten Spieler dieser Welt.</p></div></header>
        <div class="fields world-speed-fields">
            <?php adminNumber('Bauen, Forschen & Ausbildung','speed_factor',$value('speed_factor',1),.1,20,'.1'); ?>
            <?php adminNumber('Rohstoffe sammeln','gather_factor',$value('gather_factor',1),.1,20,'.1'); ?>
            <?php adminNumber('Transport & Handel','haul_factor',$value('haul_factor',1),.1,20,'.1'); ?>
        </div>
        <p class="subtle">1,0 entspricht dem normalen Tempo, 2,0 ist doppelt so schnell.</p>
    </section>

    <section class="card world-create-section world-create-population">
        <header><span class="world-create-step">3</span><div><h2>Minen & Monster</h2><p>Lege die maximale Anzahl und die Häufigkeit auf der Karte fest.</p></div></header>
        <div class="world-population-grid">
            <?php foreach(['resource'=>['Rohstoffvorkommen / Minen','⛏️'],'monster'=>['Monster','🐉']] as $kind=>[$label,$symbol]): ?>
            <div class="world-population-card">
                <h3><span aria-hidden="true"><?= $symbol ?></span> <?= ah($label) ?></h3>
                <div class="fields">
                    <?php adminNumber('Maximal gleichzeitig','settings['.$kind.'_limit]',$cfg[$kind.'_limit'],0,25000); ?>
                    <?php adminNumber('Zieldichte je 100 Felder (%)','settings['.$kind.'_density_pct]',$cfg[$kind.'_density_pct'],0,100,'.001'); ?>
                    <?php adminNumber('Spawnchance je Versuch (%)','settings['.$kind.'_chance_pct]',$cfg[$kind.'_chance_pct'],0,100,'.001'); ?>
                    <?php adminNumber('Lebensdauer (Stunden)','settings['.$kind.'_lifetime_hours]',$cfg[$kind.'_lifetime_hours'],1,720); ?>
                    <?php adminNumber('Minimale Stufe','settings['.$kind.'_level_min]',$cfg[$kind.'_level_min'],$kind==='resource'?1:0,$kind==='resource'?10:20); ?>
                    <?php adminNumber('Maximale Stufe','settings['.$kind.'_level_max]',$cfg[$kind.'_level_max'],$kind==='resource'?1:0,$kind==='resource'?10:20); ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <div class="hint"><strong>Maximal gleichzeitig</strong> ist die harte Obergrenze. Die Zieldichte kann bei einer kleinen Welt vorher greifen. Die Spawnchance bestimmt, wie oft ein freier Versuch erfolgreich ist.</div>
    </section>

    <section class="card world-create-section">
        <header><span class="world-create-step">4</span><div><h2>Spawnzeitplan</h2><p>Bestimme, wann und wie stark die Welt automatisch aufgefüllt wird.</p></div></header>
        <label class="check"><input type="checkbox" name="settings[enabled]" value="1" <?= $cfg['enabled']?'checked':'' ?>> Automatische Spawns nach dem Weltstart aktivieren</label>
        <div class="fields">
            <?php adminNumber('Prüfung alle (Minuten)','settings[interval_minutes]',$cfg['interval_minutes'],1,10080); ?>
            <?php adminNumber('Maximale Spawnversuche je Lauf','settings[batch_limit]',$cfg['batch_limit'],1,500); ?>
            <label>Zeitfenster ab (UTC)<input type="time" name="settings[window_start]" value="<?= ah($cfg['window_start']) ?>" required></label>
            <label>Zeitfenster bis (UTC)<input type="time" name="settings[window_end]" value="<?= ah($cfg['window_end']) ?>" required></label>
        </div>
        <p class="subtle">Gleiche Start- und Endzeit bedeutet ganztägig. Im pausierten oder geschlossenen Zustand erscheinen keine neuen Ziele.</p>
    </section>

    <section class="card world-create-section">
        <header><span class="world-create-step">5</span><div><h2>Verteilung der Spawns</h2><p>Jede Gruppe muss zusammen genau 100 % ergeben.</p></div></header>
        <div class="world-weight-grid">
            <?php foreach(['resource'=>['Minenverteilung',$resourceLabels],'monster'=>['Monsterverteilung',$monsterLabels]] as $kind=>[$title,$labels]): ?>
            <div class="world-weight-group" data-weight-group>
                <div class="split"><h3><?= ah($title) ?></h3><output data-weight-total aria-live="polite">100 %</output></div>
                <div class="fields">
                    <?php foreach($cfg[$kind.'_weights'] as $type=>$weight)adminNumber(($labels[$type]??$type).' (%)','settings['.$kind.'_weights]['.$type.']',$weight,0,100); ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <p class="subtle">„Rallybosse“ erzeugt Dämmerhorn, Grumwald, Frostgrimm, Sandmaul oder Glutramm. Drachen und Magdar bleiben bis zu ihrer Aktivierung auf 0 %.</p>
    </section>

    <section class="card world-create-section">
        <header><span class="world-create-step">6</span><div><h2>Allianzgebiete & Abschluss</h2><p>Die Radien gelten später für alle Allianzgebäude dieser Welt.</p></div></header>
        <div class="fields">
            <?php adminNumber('Radius Allianzzentrum (Felder)','settings[alliance_center_radius]',$cfg['alliance_center_radius'],4,40); ?>
            <?php adminNumber('Radius Außenposten (Felder)','settings[alliance_outpost_radius]',$cfg['alliance_outpost_radius'],2,24); ?>
        </div>
        <div class="world-create-review"><strong>Beim Erstellen werden angelegt:</strong><span>Weltkarte</span><span>Kongress & vier Schreine</span><span>Landentwicklung</span><span>Spawnplan</span></div>
        <label>Begründung für das Änderungsprotokoll<input name="reason" required minlength="3" maxlength="500" placeholder="z. B. Start der nächsten Spielrunde"></label>
        <button type="submit">Welt jetzt erstellen</button>
    </section>
</div>
</fieldset></form>
