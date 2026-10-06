<?php
declare(strict_types=1);
use Conquer\Admin\RewardEditor;
use Conquer\Admin\ItemPresentation;
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\Locale;
$globalRecords=RewardCatalog::records();$batchData=[];
foreach($sources as $entry){
    $entryKey=(string)$entry['key'];$rowRecord=$sourceRecords[$type.':'.$entryKey]??null;
    $batchData[]=['key'=>$entryKey,'name'=>Locale::text($entry['name']),'level'=>$sourceOverview[$entryKey]['level'],'revision'=>(int)($rowRecord['revision']??0),'parent_revision'=>(int)($globalRecords[$type.':'.$entryKey]['revision']??0),'config'=>RewardEditor::formConfig($type,$sourceOverview[$entryKey]['config'])];
}
$fragmentTargets=[];
foreach(['normal','rare','epic','legendary'] as $grade)$fragmentTargets[]=['value'=>'fragment:'.$grade,'name'=>Locale::t('admin.drops.fragment_random_'.$grade)];
foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def)if(empty($def['legacy_only']))$fragmentTargets[]=['value'=>'treasure:'.$code,'name'=>Locale::text($def['name_de']??$def['name'])];
$relicTargets=[];
foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def)if(empty($def['legacy_only']))$relicTargets[]=['value'=>'relic:'.$code,'name'=>Locale::text($def['name_de']??$def['name'])];
$itemTargets=array_map(static fn(array $i):array=>['value'=>(string)$i['code'],'name'=>Locale::text($i['name'])],ItemPresentation::catalog());
$targetNames=['fragment_rows'=>array_column($fragmentTargets,'name','value'),'relic_rows'=>array_column($relicTargets,'name','value'),'rows'=>array_column($itemTargets,'name','value')];
$failed=$_SESSION['admin_reward_batch_draft']??null;
$matches=is_array($failed)&&($failed['source_type']??null)===$type&&($failed['reward_scope']??'global')===$rewardScope&&(!$scopeWorld||(int)($failed['world_id']??0)===$scopeWorld);
$restored=[];
if($matches&&is_string($failed['updates_json']??null)){try{$restored=json_decode($failed['updates_json'],true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){$restored=[];}}
if(!is_array($restored)||!array_is_list($restored))$restored=[];
?>
<section data-batch-editor data-context="<?= ah($type.':'.$rewardScope.':'.$scopeWorld) ?>">
<div class="reward-table-heading"><div><h2><?= Locale::html('admin.modern.direct_title') ?></h2><p><?= Locale::html('admin.modern.direct_hint') ?></p></div><span class="reward-table-scope"><?= Locale::html($scopeWorld?'admin.modern.world_rule':'admin.modern.base_rules') ?><?php if($scopeWorld): ?> · <span data-user-content><?= ah($world['name']) ?></span><?php endif ?></span></div>
<?php if($matches): ?><div class="notice"><?= Locale::html('admin.modern.draft_retained') ?> <a href="<?= ah(APP_BASE.'/admin/rewards?type='.$type.$scopeQuery.'&discard=1') ?>"><?= ah(Locale::text('Gespeicherte Werte neu laden')) ?></a></div><?php endif ?>
<div class="batch-kind" role="group" aria-label="<?= Locale::html('admin.modern.reward') ?>"><button type="button" class="secondary" data-batch-kind="relic_rows" aria-pressed="false"><?= Locale::html('admin.modern.relics') ?></button><button type="button" class="secondary" data-batch-kind="fragment_rows" aria-pressed="true"><?= Locale::html('admin.modern.fragments') ?></button><button type="button" class="secondary" data-batch-kind="rows" aria-pressed="false"><?= Locale::html('admin.modern.items') ?></button></div>
<p class="batch-help" data-batch-relic-help hidden><?= Locale::html('admin.drops.relic_rule_hint',['count'=>\Conquer\Game\Treasure\TreasureService::UNLOCK_COST]) ?></p>
<div class="batch-toolbar"><label class="batch-search"><?= Locale::html('admin.drops.search_sources') ?><input type="search" data-batch-search placeholder="<?= Locale::html('admin.modern.source_search') ?>"></label><label><?= Locale::html('admin.drops.level_filter') ?><select data-batch-level><option value=""><?= Locale::html('admin.drops.all_levels') ?></option><?php foreach($levels as $level): ?><option value="<?= $level ?>"><?= $level ?></option><?php endforeach ?></select></label><label><?= Locale::html('admin.drops.rule_filter') ?><select data-batch-rule><option value=""><?= Locale::html('admin.drops.all_rules') ?></option><option value="custom"><?= Locale::html('admin.drops.custom') ?></option><option value="default"><?= Locale::html('admin.drops.inherited') ?></option></select></label></div>
<p class="batch-filter-count" data-batch-count aria-live="polite"></p>
<form method="post" action="<?= APP_BASE ?>/admin/action/reward-batch-save" class="admin-form reward-batch-form" data-batch-form>
<input type="hidden" name="csrf_token" value="<?= ah($csrf) ?>"><input type="hidden" name="operation_id" value="<?= bin2hex(random_bytes(16)) ?>"><input type="hidden" name="source_type" value="<?= $type ?>"><input type="hidden" name="reward_scope" value="<?= $rewardScope ?>"><input type="hidden" name="world_id" value="<?= $selectedWorld ?>"><input type="hidden" name="updates_json" value="[]">
<fieldset <?= $canEdit?'':'disabled' ?>><div class="batch-table-wrap" tabindex="0" role="region" aria-label="<?= Locale::html('admin.modern.direct_title') ?>"><table class="batch-table"><thead><tr><th scope="col"><?= Locale::html('admin.modern.source') ?></th><th scope="col"><?= Locale::html('admin.modern.reward') ?></th><th scope="col"><?= Locale::html('admin.modern.details') ?></th></tr></thead><tbody>
<?php foreach($batchData as $entry): $original=$sources[$entry['key']];$overview=$sourceOverview[$entry['key']]; ?>
<tr class="batch-source" data-batch-source="<?= ah($entry['key']) ?>" data-name="<?= ah(mb_strtolower($entry['name'].' '.$entry['key'])) ?>" data-level="<?= $entry['level'] ?>" data-custom="<?= $overview['custom']?'1':'0' ?>">
<th scope="row"><div class="batch-source-title"><?= adminIcon($original['image']) ?><span><strong><?= ah($entry['name']) ?></strong><small><?= Locale::html('admin.drops.level',['level'=>$entry['level']]) ?></small><small><?= Locale::html($overview['custom']?'admin.drops.custom':'admin.drops.inherited') ?></small><?php if($type==='monster'&&!$original['active']): ?><small><?= Locale::html('admin.drops.table_inactive') ?></small><?php endif ?></span></div></th>
<td><?php foreach(['relic_rows','fragment_rows','rows'] as $group): ?><div data-batch-group="<?= $group ?>" <?= $group!=='fragment_rows'?'hidden':'' ?>><div data-batch-entries>
<?php foreach($entry['config'][$group] as $drop): ?><div class="batch-entry"><label><?= Locale::html('admin.modern.reward') ?><select data-field="target" required><option value="<?= ah($drop['target']) ?>" selected><?= ah($targetNames[$group][$drop['target']]??$drop['target']) ?></option></select></label><label><?= Locale::html('admin.modern.quantity') ?><input data-field="quantity" type="number" min="1" max="100000" step="1" value="<?= $drop['quantity'] ?>" required></label><label><?= Locale::html('admin.modern.chance') ?><input data-field="chance" type="number" min="0" max="100" step=".0001" value="<?= $drop['chance'] ?>" required></label><label><?= Locale::html('admin.modern.average') ?><output data-batch-average></output></label><button type="button" class="secondary batch-remove" data-batch-remove aria-label="<?= Locale::html('admin.modern.remove') ?>">×</button></div><?php endforeach ?>
</div><p class="batch-empty" <?= $entry['config'][$group]?'hidden':'' ?>><?= Locale::html('admin.modern.no_drops') ?></p><button type="button" class="secondary batch-add" data-batch-add><?= Locale::html('admin.modern.add') ?> +</button></div><?php endforeach ?></td>
<td><a class="batch-details" href="<?= ah(APP_BASE.'/admin/rewards?type='.$type.'&source='.rawurlencode($entry['key']).$scopeQuery) ?>#reward-editor"><?= Locale::html('admin.modern.details') ?> ↗</a></td></tr>
<?php endforeach ?></tbody></table></div>
<p data-batch-empty class="empty" hidden><?= Locale::html('admin.modern.no_drops') ?></p>
<div class="batch-save"><label><?= Locale::html('admin.modern.reason') ?><input name="reason" required minlength="3" maxlength="500" placeholder="<?= Locale::html('admin.modern.reason_hint') ?>" value="<?= ah($matches?($failed['reason']??''):'') ?>"></label><div class="batch-save-actions"><span class="batch-status" data-batch-status role="status"><?= Locale::html('admin.modern.clean') ?></span><button type="submit" data-batch-save disabled><?= Locale::html('admin.modern.save_all') ?></button></div></div></fieldset></form>
<p class="batch-help"><?= Locale::html($type==='farm'?'admin.drops.relic_farm_hint':'admin.drops.relic_monster_hint') ?></p>
<script type="application/json" data-batch-data><?= json_encode(['limit'=>RewardEditor::MAX_BATCH_SOURCES,'sources'=>$batchData,'targets'=>['relic_rows'=>$relicTargets,'fragment_rows'=>$fragmentTargets,'rows'=>$itemTargets],'restored'=>is_array($restored)?$restored:[]],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
</section>
