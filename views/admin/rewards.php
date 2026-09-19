<?php
declare(strict_types=1);
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\Rewards\RewardPreview;
use Conquer\Admin\ItemPresentation;
$types=['monster'=>['Monster','hud/expeditions.svg'],'dungeon'=>['Dungeons','hud/city.svg'],'chest'=>['Truhen','items/chest-gold.svg'],'expedition'=>['Feldzüge','hud/alliance.svg']];
$type=is_string($_GET['type']??null)&&isset($types[$_GET['type']])?$_GET['type']:'monster';
$rewardScope=($_GET['scope']??'global')==='world'?'world':'global';
$scopeWorld=$rewardScope==='world'?$selectedWorld:0;
$scopeQuery='&world_id='.$selectedWorld.'&scope='.$rewardScope;
$sources=RewardCatalog::sources($type);
$requested=is_string($_GET['source']??null)?$_GET['source']:'';
$key=isset($sources[$requested])?$requested:(string)array_key_first($sources);
$source=$sources[$key];$cfg=RewardCatalog::effective($type,$key,$scopeWorld);$record=($scopeWorld?RewardCatalog::worldRecords($scopeWorld):RewardCatalog::records())[$type.':'.$key]??null;
$revision=(int)($record['revision']??0);$custom=$record && $record['config']!==null;
$preview=$type==='monster'?RewardPreview::monster($key,$scopeWorld):null;
$history=RewardPreview::history($type,$key,$scopeWorld);
$previewNumber=static function(int|float $number):string{$rounded=round((float)$number,4);return abs($rounded-round($rounded))<.00005?number_format($rounded,0,',','.'):(rtrim(rtrim(number_format($rounded,4,',','.'),'0'),','));};
$rows=[];
foreach($cfg[$type==='chest'?'drop_table':($type==='dungeon'?'items':'drops')] as $row)$rows[]=['target'=>isset($row['fragment_grade'])?'fragment:'.$row['fragment_grade']:(string)$row['item_code'],'quantity'=>$row['count']??$row['quantity']??1,'chance'=>round(($row['probability']??0)*100,4),'weight'=>$row['weight']??1];
$form=$cfg;$form['rows']=$rows;
if($type==='monster'){$form['resources']=$cfg['resource_reward'];$form['gems_chance']=round($cfg['gems_drop']['chance']*100,4);$form['gems_amount']=$cfg['gems_drop']['amount'];$form['charms']['chance']=round($cfg['charms']['chance']*100,4);}
if($type==='dungeon')$form['item_chance']=round($cfg['item_chance']*100,4);
if(isset($_GET['discard']))unset($_SESSION['admin_reward_draft']);
$draft=$_SESSION['admin_reward_draft']??null;
$hasDraft=is_array($draft)&&($draft['source_type']??null)===$type&&($draft['source_key']??null)===$key&&($draft['reward_scope']??'global')===$rewardScope&&(!$scopeWorld||(int)($draft['world_id']??0)===$scopeWorld)&&is_array($draft['config']??null);
if($hasDraft){$form=array_replace($form,$draft['config']);$revision=is_scalar($draft['revision']??null)?(int)$draft['revision']:$revision;}
$editUrl=APP_BASE.'/admin/rewards?type='.rawurlencode($type).'&source='.rawurlencode($key).$scopeQuery;
$retired=$type==='monster'&&$source['definition']['type']==='solo'?array_values(array_filter($source['definition']['drops']??[],static fn($d)=>\Conquer\Game\Inventory\InventoryService::getItemDef((int)$d['item_code'])===null)):[];
?>
<section class="card"><form method="get" class="fields"><input type="hidden" name="type" value="<?= ah($type) ?>"><input type="hidden" name="source" value="<?= ah($key) ?>"><label>Geltungsbereich<select name="scope"><option value="global" <?= $rewardScope==='global'?'selected':'' ?>>Grundbeute · alle Welten</option><option value="world" <?= $rewardScope==='world'?'selected':'' ?>>Abweichung für eine Welt</option></select></label><label>Welt<select name="world_id"><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$selectedWorld?'selected':'' ?>><?= ah($w['name']) ?></option><?php endforeach ?></select></label><button class="secondary" type="submit">Anzeigen</button></form></section>
<nav class="reward-tabs" aria-label="Beuteart">
<?php foreach($types as $id=>[$label,$icon]): ?><a href="<?= APP_BASE ?>/admin/rewards?type=<?= $id ?><?= ah($scopeQuery) ?>" class="<?= $id===$type?'active':'' ?>" <?= $id===$type?'aria-current="page"':'' ?>><?= adminIcon($icon) ?><span><?= $label ?></span><small><?= count(RewardCatalog::sources($id)) ?></small></a><?php endforeach ?>
</nav>
<div class="reward-workspace">
<section class="card source-browser" aria-label="Beutequelle auswählen"><h2>1. Quelle auswählen</h2><label>Suche<input type="search" data-source-search placeholder="Name oder Stufe …"></label><p class="subtle" data-source-count aria-live="polite"><?= count($sources) ?> Quellen</p>
<div class="source-list">
<?php foreach($sources as $entry): $active=(string)$entry['key']===$key; ?><a class="source-choice <?= $active?'is-selected':'' ?>" data-source-name="<?= ah(mb_strtolower($entry['name'].' '.$entry['subtitle'].' '.$entry['key'])) ?>" href="<?= APP_BASE ?>/admin/rewards?type=<?= $type ?>&amp;source=<?= ah($entry['key'].$scopeQuery) ?>" <?= $active?'aria-current="true"':'' ?>><?= adminIcon($entry['image'],'source-icon') ?><span><strong><?= ah($entry['name']) ?></strong><small><?= ah($entry['subtitle']) ?></small></span><span class="source-arrow" aria-hidden="true"><?= $active?'✓':'›' ?></span></a><?php endforeach ?>
<p data-source-empty class="empty" hidden>Keine passende Quelle gefunden.</p></div>
</section>
<div class="reward-detail">
<section class="card reward-editor">
<div class="reward-hero"><?= adminIcon($source['image'],'reward-portrait') ?><div><span class="eyebrow">2. Belohnungen festlegen</span><h2><?= ah($source['name']) ?></h2><p><?= ah($source['subtitle']) ?></p><span class="pill <?= $custom?'open':'' ?>"><?= $custom?'Eigene Beute':'Standardbeute' ?></span></div></div>
<p class="reward-scope">🌐 <?= $scopeWorld?'Diese Abweichung gilt nur für <strong>'.ah($world['name']??'Welt '.$scopeWorld).'</strong>.':'Diese Grundbeute gilt für <strong>alle Welten ohne eigene Abweichung</strong>.' ?> <?= ['monster'=>'Neue Angriffe und Rallys übernehmen diese Beute beim Start. Laufende Aufträge behalten ihre gespeicherten Regeln.','dungeon'=>'Neue Gruppen übernehmen diese Regeln. Bereits angelegte Gruppen behalten ihre Beute.','chest'=>'Die Änderung gilt ab der nächsten Truhenöffnung.','expedition'=>'Die Änderung gilt für neu angelegte Feldzüge dieses Schwierigkeitsgrads.'][$type] ?></p>
<?php if($type==='monster'&&!$source['active']): ?><div class="notice">Noch nicht aktiver Inhalt. Du kannst die Beute vorbereiten; das Speichern aktiviert dieses Monster nicht und erzeugt keine Spawns.</div><?php endif ?>
<?php if($type==='monster'&&$source['catalog_level']!==(int)$source['definition']['level']): ?><p class="subtle">Historischer Code <?= ah($key) ?>: Die angezeigte Stufe <?= (int)$source['definition']['level'] ?> ist die tatsächlich im Kampf verwendete Stufe.</p><?php endif ?>
<?php if($hasDraft): ?><div class="notice">Deine Eingaben wurden noch nicht gespeichert und bleiben hier erhalten. <a href="<?= ah($editUrl.'&discard=1') ?>">Gespeicherte Werte neu laden</a></div><?php endif ?>
<?php adminForm('reward-save',$selectedWorld); ?>
<input type="hidden" name="source_type" value="<?= $type ?>"><input type="hidden" name="source_key" value="<?= ah($key) ?>"><input type="hidden" name="revision" value="<?= $revision ?>">
<input type="hidden" name="reward_scope" value="<?= $rewardScope ?>">
<div class="reward-fields" data-reward-editor="<?= $type ?>">
<?php if($retired&&!$custom): ?><details class="hint"><summary><?= count($retired) ?> alte Gegenstandsreferenzen sind nicht mehr verfügbar</summary><p>Diese historischen Einträge werden nicht ausgezahlt. Wähle über „Gegenstand hinzufügen“ passende Items aus dem aktuellen Katalog.</p><ul><?php foreach($retired as $drop): ?><li><?= ah($drop['label']??'Gegenstand') ?> · alte Nr. <?= (int)$drop['item_code'] ?></li><?php endforeach ?></ul></details><?php endif ?>
<?php if(in_array($type,['monster','expedition'],true)): ?>
<h3>Rohstoffe <?= $type==='monster'?'bei einem Sieg':'pro Teilnehmer' ?></h3><p class="subtle"><?= $type==='monster'?'Bei Rallys wird der Gesamtvorrat unter den Teilnehmern aufgeteilt.':'Diese Werte gelten bereits für den gewählten Schwierigkeitsgrad.' ?> Talente können die Rohstoffbeute erhöhen.</p>
<div class="resource-fields"><?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'] as $r=>$label): ?><div><?= adminIcon('ui-resources/'.$r.'.png') ?><?php adminNumber($label,'config[resources]['.$r.']',$form['resources'][$r]??0,0,1000000000); ?></div><?php endforeach ?></div>
<div class="fields"><?php if($type==='monster'){adminNumber('Edelsteine pro Drop','config[gems_amount]',$form['gems_amount']??0,0,1000000);adminNumber('Chance auf Edelsteine (%)','config[gems_chance]',$form['gems_chance']??0,0,100,'.01');}else adminNumber('Garantierte Edelsteine','config[gems]',$form['gems']??0,0,1000000); ?></div>
<?php elseif($type==='dungeon'): ?>
<h3>Garantierte Reliktfragmente bei Erfolg</h3><div class="fields"><label>Relikt<select name="config[treasure_code]" data-treasure-select><?php foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def): ?><option value="<?= (int)$code ?>" data-image="<?= ah(ItemPresentation::image('items/'.($def['icon']??'fragment.svg'))) ?>" <?= (string)$code===(string)($form['treasure_code']??'')?'selected':'' ?>><?= ah($def['name_de']??$def['name']) ?> · <?= ah(ItemPresentation::GRADES[$def['grade']]??$def['grade']) ?></option><?php endforeach ?></select></label><?php adminNumber('Fragmente pro Spieler · Basis','config[fragments]',$form['fragments']??3,0,10000); ?></div><div class="treasure-preview"><?= adminIcon('items/fragment.svg') ?><span data-treasure-name></span></div>
<div class="fields"><?php adminNumber('Chance auf einen Gegenstand · Basis (%)','config[item_chance]',$form['item_chance']??18,0,100,'.01');adminNumber('Anzahl des gezogenen Gegenstands','config[item_quantity]',$form['item_quantity']??1,1,100000); ?></div>
<div class="hint">Schwer: × 1,6 · Seitenkammer: × 1,25 · Sammlerbonus: bis × 1,5. Diese Faktoren erhöhen Fragmente und Itemchance (höchstens 100 %). Bei einem Fehlschlag gibt es keine Beute.</div>
<?php else: ?><div class="fields"><?php adminNumber('Ziehungen pro Truhe','config[rolls]',$form['rolls']??1,1,20); ?></div><?php endif ?>
<div class="split drop-heading"><h3><?= $type==='dungeon'?'Mögliche Gegenstände':'Gegenstände & Drops' ?></h3><span class="pill" data-drop-count></span></div>
<p class="subtle"><?= $type==='chest'?'Pro Ziehung wird genau ein Eintrag gewählt. Höhere Gewichtung bedeutet höhere Wahrscheinlichkeit; derselbe Gegenstand kann mehrfach gezogen werden.':($type==='dungeon'?'Wenn die Itemchance erfolgreich ist, wird genau ein Gegenstand aus diesem Pool gewählt. Die Gewichtung bestimmt seinen Anteil.':'Jede Zeile wird unabhängig gewürfelt: 100 % ist garantiert, 0 % deaktiviert den Drop. Die Chancen müssen zusammen nicht 100 % ergeben.') ?></p>
<label class="drop-search">Beuteliste durchsuchen<input type="search" data-drop-search placeholder="Gegenstand in dieser Beuteliste suchen …"></label>
<div class="drop-rows" data-drop-rows><?php foreach(is_array($form['rows']??null)?$form['rows']:[] as $index=>$row)if(is_array($row))adminDropRow($type,$index,$row); ?></div>
<p class="subtle" data-drop-no-match hidden>Kein passender Eintrag. Leere die Suche, um alle Drops zu sehen.</p>
<p class="empty" data-drop-empty>Keine Item-Drops eingetragen. Über „Gegenstand hinzufügen“ legst du den ersten Drop an.</p>
<button class="secondary" type="button" data-add-drop>＋ Gegenstand hinzufügen</button>
<template id="drop-row-template"><?php adminDropRow($type,'__ROW__',[]); ?></template>
<?php if($type==='monster'): ?><details class="advanced-settings" open><summary>Garantierter Charm auf der Weltkarte</summary><p>Jedes besiegte Monster hinterlässt genau einen Charm an seinem Spawnort, auch ein Rally-Boss. Truppen sammeln ihn ein. Hier legst du die Seltenheit fest; die drei Anteile ergeben zusammen 100 %.</p><input type="hidden" name="config[charms][chance]" value="100"><p class="pill open">1 Charm · 100 % garantiert</p><div class="fields"><?php foreach(['normal'=>'Normal','epic'=>'Episch','legendary'=>'Legendär'] as $grade=>$label)adminNumber($label.' (%)','config[charms]['.$grade.']',$form['charms'][$grade]??0,0,100); ?></div></details><?php endif ?>
<div class="save-area"><label>Notiz zur Änderung<input name="reason" required minlength="3" maxlength="500" value="<?= ah($hasDraft?($draft['reason']??''):'') ?>" placeholder="z. B. Beute für das Herbst-Event angepasst"></label><div class="split"><span class="save-status" data-save-status>Keine ungespeicherten Änderungen</span><button type="submit">✓ Beute speichern</button></div></div>
</div></fieldset></form>
 <?php if($record): ?><p class="subtle">Zuletzt gespeichert: <?= ah($record['updated_at']) ?> UTC · Version <?= (int)$record['revision'] ?></p><?php endif ?>
 </section>
 <?php if($preview!==null): ?>
 <section class="card" aria-labelledby="reward-preview-title">
 <span class="eyebrow">Gespeicherte Regel</span><h2 id="reward-preview-title">Erwartete Beute</h2>
 <p>Rechnerischer Durchschnitt für 100 Siege mit der aktuell wirksamen <?= $scopeWorld?'Weltregel':'Grundregel' ?>. Ungespeicherte Formularwerte erscheinen erst nach dem Speichern.</p>
 <div class="stats">
 <div class="stat"><?= adminIcon('hud/expeditions.svg') ?><small>Charms je Sieg</small><strong><?= $previewNumber($preview['charm']['guaranteed_per_victory']) ?></strong><span>garantiert · <?= $previewNumber($preview['charm']['expected_per_100']) ?> je 100 Siege</span></div>
 <div class="stat"><?= adminIcon('items/gems.svg') ?><small>Edelsteine je 100 Siege</small><strong><?= $previewNumber($preview['gems']['expected_per_100']) ?></strong><span><?= $previewNumber($preview['gems']['quantity_on_drop']) ?> bei Treffer · <?= $previewNumber($preview['gems']['chance']*100) ?> % Chance</span></div>
 <?php foreach(['food'=>['Nahrung','ui-resources/food.png'],'lumber'=>['Holz','ui-resources/lumber.png'],'stone'=>['Stein','ui-resources/stone.png'],'gold'=>['Gold','ui-resources/gold.png']] as $resource=>[$label,$icon]): ?><div class="stat"><?= adminIcon($icon) ?><small><?= $label ?> je Sieg</small><strong><?= $previewNumber($preview['resources_per_victory'][$resource]) ?></strong><span>Basis vor Talentboni</span></div><?php endforeach ?>
 </div>
 <h3>Gegenstände je 100 Siege</h3>
 <?php if($preview['items']): ?><div class="table-wrap"><table><thead><tr><th>Gegenstand</th><th>Menge bei Treffer</th><th>Chance je Sieg</th><th>Erwartungswert je 100</th></tr></thead><tbody><?php foreach($preview['items'] as $item): ?><tr><td><strong><?= ah($item['name']) ?></strong><br><small>Nr. <?= (int)$item['item_code'] ?></small></td><td><?= $previewNumber($item['quantity_on_drop']) ?></td><td><?= $previewNumber($item['chance']*100) ?> %</td><td><strong><?= $previewNumber($item['expected_per_100']) ?></strong></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p class="empty">Keine Gegenstände in der aktuell wirksamen Beuteliste.</p><?php endif ?>
 <div class="notice">Die Werte sind langfristige Durchschnittswerte. Ein Erwartungswert von 25 bedeutet nicht, dass in den nächsten 100 Siegen genau 25 Gegenstände fallen.</div>
 </section>
 <?php endif ?>
 <section class="card" aria-labelledby="reward-history-title"><h2 id="reward-history-title">Regelverlauf</h2><p>Die letzten 20 gespeicherten Änderungen für diese Quelle und genau diesen Geltungsbereich.</p>
 <?php if($history): ?><div class="table-wrap"><table><thead><tr><th>Revision</th><th>Datum</th><th>Änderung</th></tr></thead><tbody><?php foreach($history as $entry): ?><tr><td><?= (int)$entry['revision'] ?></td><td><time datetime="<?= ah(str_replace(' ','T',$entry['created_at']).'Z') ?>"><?= ah($entry['created_at']) ?> UTC</time></td><td><span class="pill <?= $entry['kind']==='adjustment'?'open':'' ?>"><?= $entry['kind']==='reset'?'Zurückgesetzt':'Anpassung' ?></span></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p class="empty">Für diese Quelle und diesen Geltungsbereich gibt es noch keinen gespeicherten Verlauf.</p><?php endif ?>
 </section>
 <?php if($custom): ?><details class="card reset-settings"><summary>Übergeordnete Beute wiederherstellen</summary><p>Entfernt deine Anpassung für <?= ah($source['name']) ?>. Neue Belohnungen verwenden danach <?= $scopeWorld?'die globale Grundbeute':'die mitgelieferten Werte' ?>.</p><?php adminForm('reward-reset',$selectedWorld); ?><input type="hidden" name="source_type" value="<?= $type ?>"><input type="hidden" name="source_key" value="<?= ah($key) ?>"><input type="hidden" name="reward_scope" value="<?= $rewardScope ?>"><input type="hidden" name="revision" value="<?= (int)$record['revision'] ?>"><?php adminSubmit('Standardbeute wiederherstellen'); ?></details><?php endif ?>
</div></div>
