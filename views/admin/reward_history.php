<?php
declare(strict_types=1);
use Conquer\Admin\RewardLedger;
use Conquer\Game\Locale;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Treasure\TreasureData;
$invalid=$rewardTab==='invalid';
$filterNumber=static fn(string $key,int $default=0):int=>is_scalar($_GET[$key]??null)?max(0,(int)$_GET[$key]):$default;
$historyFilters=['world_id'=>$filterNumber('world_id',$selectedWorld),'player_id'=>$filterNumber('player_id'),'item_code'=>$filterNumber('item_code'),'before'=>$filterNumber('before'),'days'=>$filterNumber('days',7),'source_type'=>is_string($_GET['source_type']??null)?$_GET['source_type']:''];
$ledger=RewardLedger::history($historyFilters,$invalid);
$ledgerText=static fn(string $key):string=>Locale::html('admin.reward_ledger.'.$key);
$sources=['monster','farm','chest','dungeon','expedition','admin_gift','alliance_gift','daily_quest','welcome_event','item_use','item_shop','crystal_shop','trading_shop','territory','building_refund','pvp','neutral_village','rally','dungeon_quest','welcome','tutorial','inventory'];
?>
<section class="card reward-ledger-intro">
 <span class="eyebrow"><?= $ledgerText($invalid?'tab_invalid':'tab_actual') ?></span>
 <h2><?= $ledgerText($invalid?'invalid_title':'actual_title') ?></h2>
 <p><?= $ledgerText($invalid?'invalid_hint':'actual_hint') ?></p>
 <?php if($invalid): ?><p class="subtle"><?= $ledgerText('neutral_hint') ?></p><?php endif ?>
 <form method="get" class="reward-ledger-filters">
  <input type="hidden" name="tab" value="<?= ah($rewardTab) ?>">
  <label><?= $ledgerText('world') ?><select name="world_id"><option value="0"><?= $ledgerText('all_worlds') ?></option><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$historyFilters['world_id']?'selected':'' ?>><?= ah($w['name']) ?></option><?php endforeach ?></select></label>
  <label><?= $ledgerText('period') ?><select name="days"><?php foreach([1,7,30,90] as $days): ?><option value="<?= $days ?>" <?= $days===$historyFilters['days']?'selected':'' ?>><?= Locale::html('admin.reward_ledger.days',['days'=>$days]) ?></option><?php endforeach ?></select></label>
  <label><?= $ledgerText('source') ?><select name="source_type"><option value=""><?= $ledgerText('all_sources') ?></option><?php foreach($sources as $source): ?><option value="<?= ah($source) ?>" <?= $source===$historyFilters['source_type']?'selected':'' ?>><?= Locale::html('admin.reward_ledger.source_'.$source) ?></option><?php endforeach ?></select></label>
  <label><?= $ledgerText('player_id') ?><input name="player_id" type="number" min="1" value="<?= $historyFilters['player_id']?:'' ?>"></label>
  <label><?= $ledgerText('item_id') ?><input name="item_code" type="number" min="1" value="<?= $historyFilters['item_code']?:'' ?>"></label>
  <button type="submit"><?= $ledgerText('filter') ?></button>
 </form>
</section>
<?php if(!$ledger['available']): ?>
<section class="card"><p role="status"><?= $ledgerText('unavailable') ?></p></section>
<?php elseif(!$ledger['rows']): ?>
<section class="card"><p role="status"><?= $ledgerText('empty') ?></p></section>
<?php else: ?>
<section class="card reward-ledger-list" aria-label="<?= $ledgerText('results') ?>">
<?php foreach($ledger['rows'] as $entry):
    $code=(int)$entry['item_code'];$kind=$entry['reward_kind']??'item';
    $definition=in_array($kind,['fragment','relic'],true)?TreasureData::get($code):InventoryService::getItemDef($code);
    $name=$kind==='resource'?Locale::t('admin.reward_ledger.resource_'.$entry['resource_code']):($definition?Locale::text($definition['name_de']??$definition['name']):Locale::t('admin.reward_ledger.unknown_item'));
    $sourceKey='admin.reward_ledger.source_'.$entry['source_type'];
    $sourceLabel=in_array($entry['source_type'],$sources,true)?Locale::t($sourceKey):$entry['source_type'];
    $result=json_decode((string)($entry['result_json']??''),true)?:[];
?>
<details class="reward-ledger-row" data-reward-receipt="<?= (int)$entry['id'] ?>">
 <summary><span class="reward-ledger-time"><strong>#<?= (int)$entry['id'] ?></strong><time><?= ah($entry['created_at']) ?> UTC</time></span><span class="reward-ledger-reward"><strong><?= ah($name) ?></strong><small><?= $ledgerText('kind_'.$kind) ?><?= $code?' · #'.$code:'' ?></small></span><span class="reward-ledger-player"><?= ah($entry['username']??('#'.($entry['player_id']??'—'))) ?><small><?= ah($entry['world_name']??($entry['world_id']?'#'.$entry['world_id']:Locale::t('admin.reward_ledger.unspecified'))) ?></small></span><span><strong><?= an($entry['quantity']) ?> ×</strong><small><?= ah($sourceLabel) ?></small></span><span class="pill <?= $invalid?'blocked':'done' ?>"><?= $ledgerText($invalid?'rejected':'credited') ?></span><span aria-hidden="true">⌄</span></summary>
 <div class="reward-ledger-detail">
  <dl>
   <div><dt><?= $ledgerText('requested') ?></dt><dd><?= an($entry['quantity']) ?></dd></div>
   <div><dt><?= $ledgerText('credited') ?></dt><dd><?= $invalid?'0':an($entry['quantity']) ?></dd></div>
   <div><dt><?= $ledgerText('source') ?></dt><dd><?= ah($sourceLabel) ?> <?= ah($entry['source_key']) ?></dd></div>
   <div><dt><?= $ledgerText('reference') ?></dt><dd><?= ah($entry['source_reference']?:Locale::t('admin.reward_ledger.unspecified')) ?></dd></div>
   <div><dt><?= $ledgerText('revision') ?></dt><dd><code><?= ah($entry['rule_revision']?:Locale::t('admin.reward_ledger.unspecified')) ?></code></dd></div>
   <div><dt><?= $ledgerText('operation') ?></dt><dd><code><?= ah($entry['operation_id']?:Locale::t('admin.reward_ledger.unspecified')) ?></code></dd></div>
   <?php if($invalid): ?><div><dt><?= $ledgerText('reason') ?></dt><dd><?= Locale::html('admin.reward_ledger.reason_'.(in_array($entry['reason'],['unknown_item','unregistered_item','invalid_quantity','source_not_allowed','inventory_limit','unknown_relic','unknown_fragment'],true)?$entry['reason']:'rejected')) ?></dd></div><?php endif ?>
   <?php if(isset($result['fragments_added'])): ?><div><dt><?= $ledgerText('converted_fragments') ?></dt><dd><?= an($result['fragments_added']) ?></dd></div><?php endif ?>
   <?php if(isset($result['fragments_remaining'])): ?><div><dt><?= $ledgerText('remaining_fragments') ?></dt><dd><?= an($result['fragments_remaining']) ?></dd></div><?php endif ?>
   <?php if(isset($result['newly_unlocked'])): ?><div><dt><?= $ledgerText('unlocked') ?></dt><dd><?= $ledgerText($result['newly_unlocked']?'yes':'no') ?></dd></div><?php endif ?>
  </dl>
  <div class="reward-ledger-links">
   <?php if($entry['player_id']): ?><a class="button secondary" href="<?= ah(APP_BASE.'/admin/players/'.(int)$entry['player_id'].'?world_id='.(int)$entry['world_id']) ?>"><?= $ledgerText('open_player') ?></a><?php endif ?>
   <?php if(in_array($entry['source_type'],['monster','farm','chest','dungeon','expedition'],true)&&$entry['source_key']!==''): ?><a class="button secondary" href="<?= ah(APP_BASE.'/admin/rewards?'.http_build_query(['tab'=>'rules','type'=>$entry['source_type'],'source'=>$entry['source_key'],'scope'=>'world','world_id'=>$entry['world_id']])) ?>"><?= $ledgerText('open_rule') ?></a><?php endif ?>
  </div>
 </div>
</details>
<?php endforeach ?>
</section>
<?php if($ledger['next']): ?><a class="button secondary" href="<?= ah(APP_BASE.'/admin/rewards?'.http_build_query(array_replace($historyFilters,['tab'=>$rewardTab,'before'=>$ledger['next']]))) ?>"><?= $ledgerText('older') ?></a><?php endif ?>
<?php endif ?>
