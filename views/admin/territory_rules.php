<?php
declare(strict_types=1);
$territoryRules=\Conquer\Game\Territory\TerritoryService::profile($selectedWorld);
$territoryCantons=$db->query("SELECT canton_id,name FROM territory_targets WHERE world_id=? AND kind='canton' ORDER BY name",[$selectedWorld])->fetchAll();
?>
<section class="card">
<h2>Communes, Shrines & Royal Castle</h2>
<p>Regelversion <?= (int)$territoryRules['version'] ?> · 100 Communes → zwölf Shrines → Royal Castle. Neue Einstellungen gelten für neue Feldzüge. Laufende Kämpfe behalten ihre gespeicherten Regeln.</p>
<?php adminForm('world-territory-rules',$selectedWorld); ?>
<input type="hidden" name="version" value="<?= (int)$territoryRules['version'] ?>">
<div class="fields">
<label>Eroberungsbereich<select name="territory_scope"><option value="full" <?= !$territoryRules['active_cantons']?'selected':'' ?>>Gesamter Kontinent</option><option value="alpha" <?= $territoryRules['active_cantons']?'selected':'' ?>>Alpha · zwei oder drei Kantone</option></select></label>
<?php adminNumber('Shrines je Allianz · Alpha immer 1','rules[canton_limit]',$territoryRules['canton_limit'],1,2); ?>
</div>
<fieldset><legend>Kantone für die Alpha</legend><div class="fields">
<?php foreach($territoryCantons as $canton): ?><label class="check"><input type="checkbox" name="rules[active_cantons][]" value="<?= ah($canton['canton_id']) ?>" <?= in_array($canton['canton_id'],$territoryRules['active_cantons'],true)?'checked':'' ?>> <span translate="no"><?= ah(\Conquer\Game\Territory\TerritoryService::displayName($canton['name'])) ?></span></label><?php endforeach ?>
</div></fieldset>
<p class="subtle">Die gesamte Karte bleibt besiedelbar. Für einen Shrine zählt immer die Mehrheit aller Communes seines Kantons.</p>
<h3>Kampfzeiten · UTC</h3><div class="fields">
<?php adminNumber('Täglicher PvP-Beginn · Stunde UTC','rules[pvp_window_start_hour_utc]',$territoryRules['pvp_window_start_hour_utc'],0,23);adminNumber('PvP-Fenster · Stunden','rules[pvp_window_hours]',$territoryRules['pvp_window_hours'],1,24); ?>
<label>Kronenkalender beginnt (UTC)<input type="datetime-local" name="rules[crown_anchor]" value="<?= ah(str_replace(' ','T',substr($territoryRules['crown_anchor'],0,16))) ?>" required></label>
<?php adminNumber('Kronenkrieg alle · Tage','rules[crown_period_days]',$territoryRules['crown_period_days'],1,365);adminNumber('Kronenkrieg dauert · Stunden','rules[crown_duration_hours]',$territoryRules['crown_duration_hours'],1,24); ?>
</div>
<p class="subtle">Neutrale Communes und Shrines sind jederzeit angreifbar. Bei besetzten Gebieten muss der Angriff im PvP-Fenster eintreffen. Für die Krone sind Tor, Arsenal und Thron erforderlich; gehaltene Kontrollzeit entscheidet, bei Gleichstand die frühere erste Kontrolle und anschließend die Allianzkennung.</p>
<h3>Verteidigung & Belohnungen</h3><div class="fields">
<?php foreach(['commune'=>'Commune','canton'=>'Shrine','crown'=>'Royal Castle']as$kind=>$label)adminNumber('NPC-Truppen · '.$label,'rules[npc_troops]['.$kind.']',$territoryRules['npc_troops'][$kind],1,500000); ?>
<?php foreach(['income_per_hour'=>'Rohstoffertrag je Commune und Stunde','conquest_reward_gold'=>'Gold je erfolgreicher Teilnahme','support_cost'=>'Nahrung je Unterstützungsauftrag','special_daily_limit'=>'Gemeinsame Abteiaufträge pro Tag','rune_daily_charges'=>'Gemeinsame Runenteleports pro Tag','rune_radius'=>'Runenwacht · Ankunftsradius']as$key=>$label)adminNumber($label,'rules['.$key.']',$territoryRules[$key],0,1000000); ?>
</div>
<h3>Regionale Versorgung & Shrine-Auftrag</h3><div class="fields">
<?php adminNumber('Anteil regionaler PvE-Rohstoffbeute (%)','rules[regional_supply_percent]',$territoryRules['regional_supply_percent'],0,10);adminNumber('Regionale Versorgung · Tagesgrenze','rules[regional_daily_cap]',$territoryRules['regional_daily_cap'],0,1000000);adminNumber('Verschiedene Mitwirkende je Shrine-Auftrag','rules[canton_mission_contributors]',$territoryRules['canton_mission_contributors'],1,20);adminNumber('Shrine-Auftrag · Menge je Grundrohstoff','rules[canton_mission_reward]',$territoryRules['canton_mission_reward'],0,1000000); ?>
</div><p class="subtle">Regionale Beute und der gemeinsame Shrine-Auftrag zahlen begrenzt in die Allianzkasse ein. Sie verstärken keine Kampfwerte.</p>
<h3>Hofämter</h3><div class="fields">
<?php foreach(['office_daily_uses'=>'Nutzungen je Hofamt und Tag','office_resource_grant'=>'Schatzmeister · Menge je Grundrohstoff','office_acceleration_seconds'=>'Amtsbeschleuniger · Sekunden']as$key=>$label)adminNumber($label,'rules['.$key.']',$territoryRules[$key],0,1000000); ?>
</div>
<?php adminSubmit('Eroberungsregeln speichern'); ?>
</section>
