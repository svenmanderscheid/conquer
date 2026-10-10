<?php
declare(strict_types=1);
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\Rewards\RewardPreview;
use Conquer\Admin\ItemPresentation;
use Conquer\Game\Locale;
$rewardTab=is_string($_GET['tab']??null)&&in_array($_GET['tab'],['actual','invalid'],true)?$_GET['tab']:'rules';
require __DIR__.'/reward_navigation.php';
if($rewardTab!=='rules'){require __DIR__.'/reward_history.php';return;}
$types=['monster'=>['Monster','hud/expeditions.svg'],'farm'=>[Locale::t('admin.modern.mine_tab'),'ui-resources/food.png'],'dungeon'=>['Dungeons','hud/city.svg'],'chest'=>['Truhen','items/chest-gold.svg'],'expedition'=>['Feldzüge','hud/alliance.svg']];
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
foreach($cfg[$type==='chest'?'drop_table':($type==='dungeon'?'items':'drops')] as $row)$rows[]=['target'=>isset($row['relic_code'])?'relic:'.$row['relic_code']:(isset($row['treasure_code'])?'treasure:'.$row['treasure_code']:(isset($row['fragment_grade'])?'fragment:'.$row['fragment_grade']:(string)$row['item_code'])),'quantity'=>$row['count']??$row['quantity']??1,'chance'=>round(($row['probability']??0)*100,4),'weight'=>$row['weight']??1];
$form=$cfg;$form['rows']=$rows;
$form['fragment_rows']=array_map(static fn(array $row):array=>['target'=>isset($row['treasure_code'])?'treasure:'.$row['treasure_code']:'fragment:'.$row['fragment_grade'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)],$cfg['fragment_drops']??[]);
$form['relic_rows']=array_map(static fn(array $row):array=>['target'=>'relic:'.$row['treasure_code'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)],$cfg['relic_drops']??[]);
if(in_array($type,['monster','farm'],true))$form=array_replace($form,\Conquer\Admin\RewardEditor::formConfig($type,$cfg));
else foreach($cfg['drop_table']??[] as $index=>$row)if(isset($row['quantity_min']))$form['rows'][$index]['quantity_min']=$row['quantity_min'];
$form['bonus_rows']=array_map(static fn(array $row):array=>['target'=>(string)$row['item_code'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)]+(isset($row['count_min'])?['quantity_min'=>$row['count_min']]:[]),$cfg['bonus_drops']??[]);
if($type==='monster'){$form['resources']=$cfg['resource_reward'];$form['gems_chance']=round($cfg['gems_drop']['chance']*100,4);$form['gems_amount']=$cfg['gems_drop']['amount'];$form['charms']['chance']=round($cfg['charms']['chance']*100,4);}
if($type==='dungeon')$form['item_chance']=round($cfg['item_chance']*100,4);
if(isset($_GET['discard']))unset($_SESSION['admin_reward_draft'],$_SESSION['admin_reward_batch_draft']);
$draft=$_SESSION['admin_reward_draft']??null;
$hasDraft=is_array($draft)&&($draft['source_type']??null)===$type&&($draft['source_key']??null)===$key&&($draft['reward_scope']??'global')===$rewardScope&&(!$scopeWorld||(int)($draft['world_id']??0)===$scopeWorld)&&is_array($draft['config']??null);
if($hasDraft){$form=array_replace($form,$draft['config']);$revision=is_scalar($draft['revision']??null)?(int)$draft['revision']:$revision;}
$editUrl=APP_BASE.'/admin/rewards?type='.rawurlencode($type).'&source='.rawurlencode($key).$scopeQuery;
$retired=$type==='monster'&&$source['definition']['type']==='solo'?array_values(array_filter($source['definition']['drops']??[],static fn($d)=>\Conquer\Game\Inventory\InventoryService::getItemDef((int)$d['item_code'])===null)):[];
$scopeLabel=$scopeWorld?($world['name']??'Welt '.$scopeWorld):'Alle Welten';
$saveLabel=$source['name'].($type==='monster'?' · Stufe '.$source['definition']['level']:'').' · '.$scopeLabel;
$ruleLabel=$custom?($scopeWorld?'Eigene Weltregel':'Angepasste Grundbeute'):($scopeWorld?'Übernimmt die Grundbeute':'Mitgelieferte Grundbeute');
$sourceRecords=$scopeWorld?RewardCatalog::worldRecords($scopeWorld):RewardCatalog::records();
$sourceOverview=[];$levels=[];$sourceTypes=[];
foreach($sources as $entryKey=>$entry){
    $effective=RewardCatalog::effective($type,(string)$entryKey,$scopeWorld);
    $pool=$effective[$type==='chest'?'drop_table':($type==='dungeon'?'items':'drops')];
    $enabled=count(array_filter($pool,static fn($r)=>($r['weight']??$r['probability']??0)>0));
    $enabled+=count(array_filter($effective['fragment_drops']??[],static fn($r)=>($r['probability']??0)>0));
    $enabled+=count(array_filter($effective['relic_drops']??[],static fn($r)=>($r['probability']??0)>0));
    if($type==='dungeon'&&$effective['item_chance']<=0)$enabled=0;
    $level=(int)($entry['definition']['level']??0);if($level)$levels[$level]=$level;
    $sourceType=$type==='monster'?($entry['definition']['type']==='rally'?'rally':(str_contains(strtolower($entry['definition']['name']),'goblin')?'goblin':'solo')):($type==='farm'?(string)($entry['definition']['code']-20100100):'');
    if($sourceType!=='')$sourceTypes[$sourceType]=Locale::t($type==='monster'?'admin.drops.type_'.$sourceType:'admin.drops.field.'.$sourceType);
    $sourceOverview[$entryKey]=['enabled'=>$enabled,'level'=>$level,'type'=>$sourceType,'custom'=>isset($sourceRecords[$type.':'.$entryKey]['config']),'config'=>$effective];
}
sort($levels);
if($sourceTypes){
    $typeOrder=$type==='monster'?['solo','goblin','rally']:['1','2','3','4','5'];
    uasort($sources,static function(array $a,array $b)use($sourceOverview,$typeOrder):int{
        $left=$sourceOverview[$a['key']];$right=$sourceOverview[$b['key']];
        return [array_search($left['type'],$typeOrder,true),$left['level'],Locale::text($a['name']),(string)$a['key']]<=>[array_search($right['type'],$typeOrder,true),$right['level'],Locale::text($b['name']),(string)$b['key']];
    });
}
?>
<nav class="reward-tabs reward-category-tabs" aria-label="Beuteart">
<?php foreach($types as $id=>[$label,$icon]): ?><a href="<?= APP_BASE ?>/admin/rewards?type=<?= $id ?><?= ah($scopeQuery) ?>" class="<?= $id===$type?'active':'' ?>" <?= $id===$type?'aria-current="page"':'' ?>><?= adminIcon($icon) ?><span><?= $label ?></span><small><?= count(RewardCatalog::sources($id)) ?></small></a><?php endforeach ?>
</nav>
<?php if(in_array($type,['monster','farm'],true)&&$requested===''&&!$hasDraft){require __DIR__.'/reward_table.php';return;} ?>
<div class="reward-workspace">
<details class="card source-browser" data-source-browser data-source-context="<?= ah($type.':'.$rewardScope.':'.$scopeWorld) ?>"  open>
<summary><span><?= $type==='monster'?Locale::html('admin.drops.table_title'):'1. Quelle auswählen' ?></span><small><?= $type==='monster'?Locale::html('admin.drops.table_hint'):ah($source['name'].' · '.$source['subtitle']) ?></small></summary>
<div class="source-browser-content">
<div class="source-filters">
<?php if($sourceTypes): ?><label><?= Locale::html('admin.drops.type_filter') ?><select data-source-type><option value=""><?= Locale::html('admin.drops.all_types') ?></option><?php foreach($sourceTypes as $sourceType=>$label): ?><option value="<?= ah($sourceType) ?>"><?= ah($label) ?></option><?php endforeach ?></select></label><?php endif ?>
<?php if($levels): ?><label><?= Locale::html('admin.drops.level_filter') ?><select data-source-level><option value=""><?= Locale::html('admin.drops.all_levels') ?></option><?php foreach($levels as $level): ?><option value="<?= $level ?>"><?= $level ?></option><?php endforeach ?></select></label><?php endif ?>
<label><?= Locale::html('admin.drops.rule_filter') ?><select data-source-rule><option value=""><?= Locale::html('admin.drops.all_rules') ?></option><option value="custom"><?= Locale::html('admin.drops.custom') ?></option><option value="default"><?= Locale::html('admin.drops.inherited') ?></option><option value="empty"><?= Locale::html('admin.drops.no_items') ?></option></select></label>
</div>
<label><?= Locale::html($type==='monster'?'admin.drops.search_monsters':'admin.drops.search_sources') ?><input type="search" data-source-search placeholder="<?= Locale::html($type==='monster'?'admin.drops.search_monsters_hint':'admin.drops.search_sources_hint') ?>"></label>
<div class="source-filter-status"><p class="subtle" data-source-count aria-live="polite"><?= Locale::html('admin.drops.found',['count'=>count($sources)]) ?></p><button type="button" class="secondary" data-source-reset hidden><?= Locale::html('admin.drops.reset_filters') ?></button></div>
<div class="source-list">

<?php foreach($sources as $entry): $active=(string)$entry['key']===$key;$overview=$sourceOverview[$entry['key']]; ?><a class="source-choice <?= $active?'is-selected':'' ?>" data-source-type-value="<?= ah($overview['type']) ?>" data-source-level-value="<?= $overview['level'] ?>" data-source-custom="<?= $overview['custom']?'1':'0' ?>" data-source-items="<?= $overview['enabled'] ?>" data-source-name="<?= ah(mb_strtolower(Locale::text($entry['name']).' '.Locale::text($entry['subtitle']).' '.$entry['key'])) ?>" href="<?= APP_BASE ?>/admin/rewards?type=<?= $type ?>&amp;source=<?= ah($entry['key'].$scopeQuery) ?>" <?= $active?'aria-current="true"':'' ?>><?= adminIcon($entry['image'],'source-icon') ?><span><strong><?= ah($entry['name']) ?></strong><small><?= ah($entry['subtitle']) ?></small><small class="source-drop-meta"><?= Locale::html('admin.drops.source_summary',['count'=>$overview['enabled']]) ?> · <?= Locale::html($overview['custom']?'admin.drops.custom':'admin.drops.inherited') ?></small></span><span class="source-arrow" aria-hidden="true"><?= $active?'✓':'›' ?></span></a><?php endforeach ?>

<p data-source-empty class="empty" hidden>Keine passende Quelle gefunden.</p></div>
</div></details>
<div class="reward-detail" id="reward-editor" <?= $type==='monster'&&$requested===''&&!$hasDraft?'hidden':'' ?>>
<a class="button secondary reward-back-table" href="<?= ah(APP_BASE.'/admin/rewards?type='.$type.$scopeQuery) ?>"><?= Locale::html('admin.modern.back_table') ?></a>
<section class="card reward-editor">
<div class="reward-hero"><?= adminIcon($source['image'],'reward-portrait') ?><div><span class="eyebrow">2. Belohnungen bearbeiten</span><h2><?= ah($source['name']) ?></h2><p><?= ah($source['subtitle']) ?></p></div></div>
<div class="reward-context">
<div class="reward-context-current"><span>Gilt für <strong><?= ah($scopeLabel) ?></strong></span><span class="pill <?= $custom?'open':'' ?>"><?= ah($ruleLabel) ?></span></div>
<details class="reward-scope-settings"><summary>Geltungsbereich ändern</summary>
<form method="get" class="reward-scope-form" data-reward-scope-form><input type="hidden" name="type" value="<?= ah($type) ?>"><input type="hidden" name="source" value="<?= ah($key) ?>"><label>Geltungsbereich<select name="scope" data-reward-scope><option value="global" <?= $rewardScope==='global'?'selected':'' ?>>Grundbeute · alle Welten</option><option value="world" <?= $rewardScope==='world'?'selected':'' ?>>Abweichung für eine Welt</option></select></label><label data-scope-world>Welt<select name="world_id"><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$selectedWorld?'selected':'' ?>><?= ah($w['name']) ?></option><?php endforeach ?></select></label><button class="secondary" type="submit">Bereich anzeigen</button></form>
</details>
<p class="subtle"><?= $scopeWorld?'Änderungen gelten nur in dieser Welt.':'Die Grundbeute gilt in allen Welten ohne eigene Abweichung.' ?> <?= ['monster'=>'Neue Angriffe und Rallys übernehmen sie beim Start.','farm'=>Locale::html('admin.drops.farm_timing'),'dungeon'=>'Neue Gruppen übernehmen diese Regeln.','chest'=>'Änderungen gelten ab der nächsten Truhenöffnung.','expedition'=>'Neue Feldzüge dieses Schwierigkeitsgrads übernehmen diese Regeln.'][$type] ?></p>
</div>
<?php if($type==='monster'&&!$source['active']): ?><div class="notice">Noch nicht aktiver Inhalt. Du kannst die Beute vorbereiten; das Speichern aktiviert dieses Monster nicht und erzeugt keine Spawns.</div><?php endif ?>
<?php if($hasDraft): ?><div class="notice">Deine Eingaben wurden noch nicht gespeichert und bleiben hier erhalten. <a href="<?= ah($editUrl.'&discard=1') ?>">Gespeicherte Werte neu laden</a></div><?php endif ?>
<?php adminForm('reward-save',$selectedWorld); ?>
<input type="hidden" name="source_type" value="<?= $type ?>"><input type="hidden" name="source_key" value="<?= ah($key) ?>"><input type="hidden" name="revision" value="<?= $revision ?>">
<input type="hidden" name="reward_scope" value="<?= $rewardScope ?>">
<div class="reward-fields" data-reward-editor="<?= $type ?>" data-has-draft="<?= $hasDraft?'1':'0' ?>">
<section class="drop-overview" aria-label="<?= Locale::html('admin.drops.overview') ?>">
<div><small><?= Locale::html('admin.drops.active') ?></small><strong data-drop-active>—</strong></div>
<div><small><?= Locale::html('admin.drops.guaranteed') ?></small><strong data-drop-guaranteed>—</strong></div>
<div><small><?= Locale::html('admin.drops.expected') ?></small><strong data-drop-expected>—</strong></div>
<p><?= Locale::html($type==='farm'?'admin.drops.farm_basis':($type==='dungeon'?'admin.drops.dungeon_basis':($type==='chest'?'admin.drops.chest_basis':'admin.drops.basis'))) ?> <?= Locale::html('admin.drops.draft_preview') ?></p>
</section>
<?php if(in_array($type,['monster','farm'],true)): ?>
<section class="reward-section reward-relics" aria-labelledby="reward-relics-title">
<div class="split drop-heading"><h3 id="reward-relics-title"><?= Locale::html('admin.drops.relic_title') ?></h3><button class="secondary" type="button" data-add-relic>＋ <?= Locale::html('admin.drops.relic_add') ?></button></div>
<p class="subtle"><?= Locale::html('admin.drops.relic_rule_hint',['count'=>\Conquer\Game\Treasure\TreasureService::UNLOCK_COST]) ?></p>
<p class="subtle"><?= Locale::html($type==='farm'?'admin.drops.relic_farm_hint':'admin.drops.relic_monster_hint') ?></p>
<div class="relic-rows" data-relic-rows><?php foreach(is_array($form['relic_rows']??null)?$form['relic_rows']:[] as $index=>$row)if(is_array($row))adminRelicRow($index,$row); ?></div>
<p class="empty" data-relic-empty><?= Locale::html('admin.drops.relic_empty') ?></p>
<p class="subtle" data-relic-summary aria-live="polite"></p>
<template id="relic-row-template"><?php adminRelicRow('__ROW__',[]); ?></template>
</section>
<section class="reward-section reward-fragments" aria-labelledby="reward-fragments-title">
<div class="split drop-heading"><h3 id="reward-fragments-title"><?= Locale::html('admin.drops.fragment_title') ?></h3><button class="secondary" type="button" data-add-fragment>＋ <?= Locale::html('admin.drops.fragment_add') ?></button></div>
<p class="subtle"><?= Locale::html($type==='farm'?'admin.drops.fragment_farm_hint':'admin.drops.fragment_monster_hint') ?></p>
<p class="subtle"><?= Locale::html('admin.drops.fragment_rule_hint') ?></p>
<div class="fragment-rows" data-fragment-rows><?php foreach(is_array($form['fragment_rows']??null)?$form['fragment_rows']:[] as $index=>$row)if(is_array($row))adminFragmentRow($index,$row); ?></div>
<p class="empty" data-fragment-empty><?= Locale::html('admin.drops.fragment_empty') ?></p>
<p class="subtle" data-fragment-summary aria-live="polite"></p>
<template id="fragment-row-template"><?php adminFragmentRow('__ROW__',[]); ?></template>
</section>
<?php endif ?>
<?php if($retired&&!$custom): ?><details class="hint"><summary><?= count($retired) ?> alte Gegenstandsreferenzen sind nicht mehr verfügbar</summary><p>Diese historischen Einträge werden nicht ausgezahlt. Wähle über „Gegenstand hinzufügen“ passende Items aus dem aktuellen Katalog.</p><ul><?php foreach($retired as $drop): ?><li><?= ah($drop['label']??'Gegenstand') ?> · alte Nr. <?= (int)$drop['item_code'] ?></li><?php endforeach ?></ul></details><?php endif ?>
<?php if($type==='dungeon'): ?>
<h3>Garantierte Reliktfragmente bei Erfolg</h3><div class="fields"><label>Relikt<select name="config[treasure_code]" data-treasure-select><?php foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def): ?><option value="<?= (int)$code ?>" data-image="<?= ah(ItemPresentation::image('items/'.($def['icon']??'fragment.svg'))) ?>" <?= (string)$code===(string)($form['treasure_code']??'')?'selected':'' ?>><?= ah($def['name_de']??$def['name']) ?> · <?= ah(ItemPresentation::GRADES[$def['grade']]??$def['grade']) ?></option><?php endforeach ?></select></label><?php adminNumber('Fragmente pro Spieler · Basis','config[fragments]',$form['fragments']??3,0,10000); ?></div><div class="treasure-preview"><?= adminIcon('items/fragment.svg') ?><span data-treasure-name></span></div>
<div class="fields"><?php adminNumber('Chance auf einen Gegenstand · Basis (%)','config[item_chance]',$form['item_chance']??18,0,100,'.01');adminNumber('Anzahl des gezogenen Gegenstands','config[item_quantity]',$form['item_quantity']??1,1,100000); ?></div>
<div class="hint">Schwer: × 1,6 · Seitenkammer: × 1,25 · Sammlerbonus: bis × 1,5. Diese Faktoren erhöhen Fragmente und Itemchance (höchstens 100 %). Bei einem Fehlschlag gibt es keine Beute.</div>
<?php elseif($type==='chest'): ?><div class="fields"><?php adminNumber('Ziehungen pro Truhe','config[rolls]',$form['rolls']??1,1,20); ?></div><p class="hint"><?= Locale::html('admin.drops.chest_relic_hint') ?> <?= Locale::html('admin.drops.relic_rule_hint',['count'=>\Conquer\Game\Treasure\TreasureService::UNLOCK_COST]) ?></p><?php endif ?>
<section class="reward-section reward-items" aria-labelledby="reward-items-title">
<div class="split drop-heading"><h3 id="reward-items-title"><?= $type==='chest'?Locale::html('admin.drops.chest_title'):($type==='dungeon'?'Mögliche Gegenstände':'Gegenstände & Pakete') ?></h3><span class="pill" data-drop-count></span></div>
<p class="subtle"><?= $type==='chest'?'Pro Ziehung wird genau ein Eintrag gewählt. Höhere Gewichtung bedeutet höhere Wahrscheinlichkeit; derselbe Gegenstand kann mehrfach gezogen werden.':($type==='dungeon'?'Wenn die Itemchance erfolgreich ist, wird genau ein Gegenstand aus diesem Pool gewählt. Die Gewichtung bestimmt seinen Anteil.':'Jede Zeile wird unabhängig gewürfelt: 100 % ist garantiert, 0 % deaktiviert den Drop. Die Chancen müssen zusammen nicht 100 % ergeben.') ?></p>
<div class="reward-items-toolbar"><label class="drop-search">Beuteliste durchsuchen<input type="search" data-drop-search placeholder="Name oder Gegenstandsnummer …"></label><button class="secondary" type="button" data-add-drop>＋ <?= $type==='chest'?Locale::html('admin.drops.chest_add'):'Gegenstand hinzufügen' ?></button></div>
<p class="drop-focus-note" data-drop-focus-note role="status" hidden></p>
<div class="drop-rows" data-drop-rows><?php foreach(is_array($form['rows']??null)?$form['rows']:[] as $index=>$row)if(is_array($row))adminDropRow($type,$index,$row); ?></div>
<p class="subtle" data-drop-no-match hidden>Kein passender Eintrag. Leere die Suche, um alle Drops zu sehen.</p>
<p class="empty" data-drop-empty>Keine Item-Drops eingetragen. Über „Gegenstand hinzufügen“ legst du den ersten Drop an.</p>
<template id="drop-row-template"><?php adminDropRow($type,'__ROW__',[]); ?></template>
</section>
<?php if($type==='chest'): ?>
<section class="reward-section">
<div class="split drop-heading"><h3><?= Locale::html('admin.drops.bonus_title') ?></h3><button type="button" class="secondary" data-add-bonus>＋ <?= Locale::html('admin.drops.bonus_add') ?></button></div>
<p class="subtle"><?= Locale::html('admin.drops.bonus_hint') ?></p>
<div class="drop-rows" data-bonus-rows><?php foreach($form['bonus_rows'] as $index=>$row)adminDropRow('bonus',$index,$row,'bonus_rows'); ?></div>
<template id="bonus-row-template"><?php adminDropRow('bonus','__ROW__',[],'bonus_rows'); ?></template>
</section>
<?php endif ?>
<?php if(in_array($type,['monster','expedition'],true)): ?>
<section class="reward-section" aria-labelledby="reward-resources-title">
<h3 id="reward-resources-title">Direkte Rohstoffe & Edelsteine</h3><p class="subtle">Diese Mengen erhöhen den Rohstoff- bzw. Edelsteinbestand. Pakete aus der Gegenstandsliste kommen zusätzlich ins Inventar. Eine 0 hier deaktiviert keine Pakete.</p>
<div class="resource-fields"><?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'] as $r=>$label): ?><div><?= adminIcon('ui-resources/'.$r.'.png') ?><?php adminNumber($label,'config[resources]['.$r.']',$form['resources'][$r]??0,0,1000000000); ?></div><?php endforeach ?></div>
<div class="fields"><?php if($type==='monster'){adminNumber('Edelsteine pro Treffer','config[gems_amount]',$form['gems_amount']??0,0,1000000);adminNumber('Chance auf Edelsteine (%)','config[gems_chance]',$form['gems_chance']??0,0,100,'.01');}else adminNumber('Garantierte Edelsteine','config[gems]',$form['gems']??0,0,1000000); ?></div>
<p class="subtle"><?= $type==='monster'?'Rohstoffe gelten je Sieg; bei Rallys wird der Gesamtvorrat aufgeteilt.':'Rohstoffe gelten pro Teilnehmer für diesen Schwierigkeitsgrad.' ?> Talente können die Rohstoffbeute erhöhen.</p>
</section>
<?php endif ?>
<?php if($type==='monster'): ?><details class="advanced-settings"><summary>Charm auf der Weltkarte <small>1 Charm je Sieg · garantiert</small></summary><p>Jedes besiegte Monster hinterlässt einen Charm am Spawnort. Truppen sammeln ihn ein. Die drei Seltenheiten müssen zusammen 100 % ergeben.</p><input type="hidden" name="config[charms][chance]" value="100"><div class="fields"><?php foreach(['normal'=>'Normal','epic'=>'Episch','legendary'=>'Legendär'] as $grade=>$label)adminNumber($label.' (%)','config[charms]['.$grade.']',$form['charms'][$grade]??0,0,100); ?></div></details><?php endif ?>
<div class="reward-change-note"><label>Notiz zur Änderung<input name="reason" required minlength="3" maxlength="500" value="<?= ah($hasDraft?($draft['reason']??''):'') ?>" placeholder="z. B. Beute für das Herbst-Event angepasst"></label></div>
<div class="save-area"><div><strong><?= ah($saveLabel) ?></strong><span class="save-status" data-save-status role="status">Keine ungespeicherten Änderungen</span></div><button type="submit">✓ Beute speichern</button></div>
</div></fieldset></form>
 <?php if($record): ?><p class="subtle">Zuletzt gespeichert: <?= ah($record['updated_at']) ?> UTC · Version <?= (int)$record['revision'] ?></p><?php endif ?>
 </section>
 <?php if($preview!==null): ?>
 <details class="card reward-reference" aria-labelledby="reward-preview-title">
 <summary id="reward-preview-title">Erwartete Beute <small>Durchschnitt aus der gespeicherten Regel</small></summary>
 <p>Rechnerischer Durchschnitt für 100 Siege mit der aktuell wirksamen <?= $scopeWorld?'Weltregel':'Grundregel' ?>. Ungespeicherte Formularwerte erscheinen erst nach dem Speichern.</p>
 <div class="stats">
 <div class="stat"><?= adminIcon('hud/expeditions.svg') ?><small>Charms je Sieg</small><strong><?= $previewNumber($preview['charm']['guaranteed_per_victory']) ?></strong><span>garantiert · <?= $previewNumber($preview['charm']['expected_per_100']) ?> je 100 Siege</span></div>
 <div class="stat"><?= adminIcon('items/gems.svg') ?><small>Edelsteine je 100 Siege</small><strong><?= $previewNumber($preview['gems']['expected_per_100']) ?></strong><span><?= $previewNumber($preview['gems']['quantity_on_drop']) ?> bei Treffer · <?= $previewNumber($preview['gems']['chance']*100) ?> % Chance</span></div>
 <?php foreach(['food'=>['Nahrung','ui-resources/food.png'],'lumber'=>['Holz','ui-resources/lumber.png'],'stone'=>['Stein','ui-resources/stone.png'],'gold'=>['Gold','ui-resources/gold.png']] as $resource=>[$label,$icon]): ?><div class="stat"><?= adminIcon($icon) ?><small><?= $label ?> je Sieg</small><strong><?= $previewNumber($preview['resources_per_victory'][$resource]) ?></strong><span>Basis vor Talentboni</span></div><?php endforeach ?>
 </div>
 <?php if($preview['fragments']): ?><h3><?= Locale::html('admin.drops.fragment_preview') ?></h3><div class="table-wrap"><table><thead><tr><th><?= Locale::html('admin.drops.fragment_relic') ?></th><th><?= Locale::html('admin.drops.fragment_quantity') ?></th><th><?= Locale::html('admin.drops.fragment_chance') ?></th><th><?= Locale::html('admin.drops.expected') ?></th></tr></thead><tbody><?php foreach($preview['fragments'] as $fragment): ?><tr><td <?= $fragment['treasure_code']?'data-fragment-relic-code="'.(int)$fragment['treasure_code'].'"':'' ?>><?= ah($fragment['name']) ?></td><td><?= ($fragment['quantity_min']??$fragment['quantity_on_drop'])!==$fragment['quantity_on_drop']?$previewNumber($fragment['quantity_min']).'–':'' ?><?= $previewNumber($fragment['quantity_on_drop']) ?></td><td><?= $previewNumber($fragment['chance']*100) ?> %</td><td><?= $previewNumber($fragment['expected_per_100']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
 <?php if(!empty($preview['relics'])): ?><h3><?= Locale::html('admin.drops.relic_preview') ?></h3><div class="table-wrap"><table><thead><tr><th><?= Locale::html('admin.drops.fragment_relic') ?></th><th><?= Locale::html('admin.drops.relic_quantity') ?></th><th><?= Locale::html('admin.drops.relic_chance') ?></th><th><?= Locale::html('admin.drops.expected') ?></th></tr></thead><tbody><?php foreach($preview['relics'] as $relic): ?><tr><td data-fragment-relic-code="<?= (int)$relic['treasure_code'] ?>"><?= ah($relic['name']) ?></td><td><?= $previewNumber($relic['quantity_on_drop']) ?></td><td><?= $previewNumber($relic['chance']*100) ?> %</td><td><?= $previewNumber($relic['expected_per_100']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
 <h3>Gegenstände & Pakete je 100 Siege</h3><p class="subtle">Die Rohstoff- und Edelsteinwerte oben enthalten nur direkte Beute. Pakete stehen separat in dieser Liste.</p>
 <?php if($preview['items']): ?><div class="table-wrap"><table><thead><tr><th>Gegenstand</th><th>Menge bei Treffer</th><th>Chance je Sieg</th><th>Erwartungswert je 100</th></tr></thead><tbody><?php foreach($preview['items'] as $item): ?><tr><td><strong><?= ah($item['name']) ?></strong><br><small>Nr. <?= (int)$item['item_code'] ?></small></td><td><?= ($item['quantity_min']??$item['quantity_on_drop'])!==$item['quantity_on_drop']?$previewNumber($item['quantity_min']).'–':'' ?><?= $previewNumber($item['quantity_on_drop']) ?></td><td><?= $previewNumber($item['chance']*100) ?> %</td><td><strong><?= $previewNumber($item['expected_per_100']) ?></strong></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p class="empty">Keine Gegenstände in der aktuell wirksamen Beuteliste.</p><?php endif ?>
 <div class="notice">Die Werte sind langfristige Durchschnittswerte. Ein Erwartungswert von 25 bedeutet nicht, dass in den nächsten 100 Siegen genau 25 Gegenstände fallen.</div>
 </details>
 <?php endif ?>
 <details class="card reward-reference" aria-labelledby="reward-history-title"><summary id="reward-history-title">Änderungsverlauf <small><?= count($history) ?> gespeicherte Änderungen</small></summary><p>Die letzten 20 gespeicherten Änderungen für diese Quelle und genau diesen Geltungsbereich.</p>
 <?php if($history): ?><div class="table-wrap"><table><thead><tr><th>Revision</th><th>Datum</th><th>Änderung</th></tr></thead><tbody><?php foreach($history as $entry): ?><tr><td><?= (int)$entry['revision'] ?></td><td><time datetime="<?= ah(str_replace(' ','T',$entry['created_at']).'Z') ?>"><?= ah($entry['created_at']) ?> UTC</time></td><td><span class="pill <?= $entry['kind']==='adjustment'?'open':'' ?>"><?= $entry['kind']==='reset'?'Zurückgesetzt':'Anpassung' ?></span></td></tr><?php endforeach ?></tbody></table></div><?php else: ?><p class="empty">Für diese Quelle und diesen Geltungsbereich gibt es noch keinen gespeicherten Verlauf.</p><?php endif ?>
 </details>
 <?php if($custom): ?><details class="card reset-settings"><summary>Übergeordnete Beute wiederherstellen</summary><p>Entfernt deine Anpassung für <?= ah($source['name']) ?>. Neue Belohnungen verwenden danach <?= $scopeWorld?'die globale Grundbeute':'die mitgelieferten Werte' ?>.</p><?php adminForm('reward-reset',$selectedWorld); ?><input type="hidden" name="source_type" value="<?= $type ?>"><input type="hidden" name="source_key" value="<?= ah($key) ?>"><input type="hidden" name="reward_scope" value="<?= $rewardScope ?>"><input type="hidden" name="revision" value="<?= (int)$record['revision'] ?>"><?php adminSubmit('Standardbeute wiederherstellen'); ?></details><?php endif ?>
</div></div>
