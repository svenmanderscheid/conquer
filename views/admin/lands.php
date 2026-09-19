<?php
declare(strict_types=1);
use Conquer\Game\World\{LandProgressService,LandRules};
if(!LandProgressService::available()){echo '<section class="card"><p>Die Datenbankmigration für Landentwicklung ist noch nicht eingerichtet.</p></section>';return;}
$overview=LandProgressService::overview($selectedWorld,0);
$rules=LandRules::get($selectedWorld);
$zoneNames=['outer'=>'Außenbereich','middle'=>'Mittlerer Bereich','center'=>'Zentrum'];
$levels=array_fill(1,9,0);foreach($overview['lands'] as $land)$levels[$land['level']]++;
?>
<section class="card"><h2><?= count($overview['lands']) ?> Landteile · jeweils 8 × 8 Felder</h2><p>Jedes Land kann bis Stufe 9 wachsen. Die Entfernung bestimmt ausschließlich seine Startstufe. Neue Monster richten sich nach der Landentwicklung; vorhandene Gegner und laufende Armeen bleiben unverändert.</p>
<div class="fields"><?php foreach($levels as $level=>$count): ?><span class="pill">Stufe <?= $level ?>: <?= $count ?> Länder</span><?php endforeach ?></div>
<p class="subtle">Bestehende Welten behalten ihre geöffneten Gebiete. Neue Welten beginnen im Außenbereich. Änderungen an den Regeln setzen keinen Fortschritt zurück.</p></section>
<section class="card"><h2>Kartenfreigabe</h2><div class="table-wrap"><table><thead><tr><th>Bereich</th><th>Status</th><th>Entwickelte Länder</th><th>Öffnung · UTC</th></tr></thead><tbody>
<?php foreach($overview['zones'] as $zone): ?><tr><td><?= ah($zoneNames[$zone['key']]) ?></td><td><?= $zone['open']?'Geöffnet':'Gesperrt' ?></td><td><?= $zone['required_count']===null?'Ab Weltstart':((int)$zone['reached_count'].' / '.(int)$zone['required_count'].' auf Stufe '.(int)$zone['target_level']) ?></td><td><?= ah($zone['open']?($zone['opened_at']??'Ab Weltstart'):($zone['not_before']?'Frühestens '.$zone['not_before']:'Nach Öffnung des vorherigen Bereichs')) ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section class="card"><h2>Entwicklung und Freigaberegeln</h2>
<?php adminForm('land-rules-save',$selectedWorld); ?>
<input type="hidden" name="revision" value="<?= (int)$rules['revision'] ?>">
<h3>Punkte bis zum nächsten Landlevel</h3><div class="fields"><?php foreach(range(1,8) as $level)adminNumber('Stufe '.$level.' → '.($level+1),'rules[thresholds]['.$level.']',$rules['thresholds'][$level],1,1000000000); ?></div>
<h3>Beiträge</h3><div class="fields">
<?php adminNumber('Jagdpunkte je Monsterstufe','rules[monster_points_per_level]',$rules['monster_points_per_level'],1,100000000);adminNumber('Ressourcenwert je Entwicklungspunkt','rules[resource_units_per_point]',$rules['resource_units_per_point'],1,1000000000); ?>
<?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'] as $key=>$label)adminNumber('Wertfaktor '.$label,'rules[resource_values]['.$key.']',$rules['resource_values'][$key],0,1000000); ?>
<?php adminNumber('Tageslimit Spenden · Anteil 0–1','rules[donation_daily_ratio]',$rules['donation_daily_ratio'],.001,1,'.001');adminNumber('Sammeln volle Wirkung · Tagesanteil 0–1','rules[gather_full_daily_ratio]',$rules['gather_full_daily_ratio'],0,1,'.01');adminNumber('Sammelwirkung danach · Anteil 0–1','rules[gather_reduced_factor]',$rules['gather_reduced_factor'],0,1,'.01'); ?>
</div><p class="subtle">0,10 entspricht 10 %. Jagd und tatsächlicher Sammelertrag zählen automatisch. Die Bedeutung von Kristallen ist noch offen; dieser Spendenweg ist deshalb noch nicht aktiv.</p>
<?php foreach(['middle','center'] as $key): $gate=$rules['gates'][$key]; ?><h3><?= ah($zoneNames[$key]) ?> freigeben</h3><p>Gezählt werden entwickelte Länder im <?= $key==='middle'?'Außenbereich':'mittleren Bereich' ?>. Entwicklungsziel und Mindestlaufzeit müssen erfüllt sein.</p><div class="fields">
<?php adminNumber('Erforderliche Landstufe','rules[gates]['.$key.'][target_level]',$gate['target_level'],$key==='center'?7:2,9);adminNumber('Anteil entwickelter Länder · 0–1','rules[gates]['.$key.'][ratio]',$gate['ratio'],.001,1,'.001');adminNumber('Mindestanzahl Länder','rules[gates]['.$key.'][minimum_count]',$gate['minimum_count'],1,1000000);adminNumber($key==='middle'?'Mindestalter der Welt · Tage':'Wartezeit nach Öffnung der Mitte · Tage','rules[gates]['.$key.'][not_before_days]',$gate['not_before_days'],0,3650); ?>
<label>Optionale Zeitfreigabe · Tage<input type="number" name="rules[gates][<?= $key ?>][fallback_after_days]" min="1" max="3650" value="<?= ah($gate['fallback_after_days']??'') ?>" placeholder="Leer = nur Entwicklungsziel"></label></div><p class="subtle">Die nötige Anzahl ist der größere Wert aus Anteil und Mindestanzahl, begrenzt auf die tatsächlich entwickelbaren Länder. Eine optionale Zeitfreigabe ersetzt danach das Entwicklungsziel; die Mindestlaufzeit gilt weiterhin.</p><?php endforeach ?>
<?php adminSubmit('Landregeln speichern'); ?>
</section>
