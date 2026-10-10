<?php declare(strict_types=1);
require_once __DIR__.'/operations_helpers.php';
$filters=\Conquer\Admin\OperationsDashboard::filters($_GET,$worlds);
$mode=in_array($_GET['mode']??'',['online','players','registrations'],true)?$_GET['mode']:'events';
$playerFilter=\Conquer\Admin\OperationsDashboard::integer($_GET['player_id']??0);
$eventInput=['player_id'=>$playerFilter,'category'=>is_string($_GET['category']??null)?$_GET['category']:'','outcome'=>is_string($_GET['outcome']??null)?$_GET['outcome']:'','q'=>is_string($_GET['q']??null)?mb_substr($_GET['q'],0,100):''];
?>
<div class="ops-workspace">
<?php opsFilters($filters,$worlds,array_merge($eventInput,['mode'=>$mode])); ?>
<nav class="ops-tabs" aria-label="<?= opsText('activity_modes') ?>"><?php foreach(['events'=>'all_actions','players'=>'active_players','online'=>'online','registrations'=>'registrations'] as $key=>$label): ?><a href="<?= ah(opsUrl('/activity',$filters,['mode'=>$key])) ?>" <?= $mode===$key?'aria-current="page"':'' ?>><?= opsText($label) ?></a><?php endforeach ?></nav>
<?php if(\Conquer\Admin\OperationsDashboard::integer($_GET['event']??0)): $eventPath='/activity'; require __DIR__.'/operational_event.php';
elseif($mode!=='events'): $list=\Conquer\Admin\OperationsDashboard::players($filters,$mode,\Conquer\Admin\OperationsDashboard::integer($_GET['before']??0)); ?>
<section class="card"><h2><?= opsText($mode==='players'?'active_players':($mode==='online'?'online':'registrations')) ?></h2><?php if(!$list['available']): ?><p class="notice"><?= opsText('activity_missing') ?></p><?php else: ?><div class="table-wrap" tabindex="0" role="region" aria-label="<?= opsText('player') ?>"><table class="ops-player-table"><thead><tr><th><?= opsText('player') ?></th><th><?= opsText('registered') ?> UTC</th><th><?= opsText('last_activity') ?> UTC</th><th><?= opsText('investigate') ?></th></tr></thead><tbody><?php foreach($list['rows'] as $row): ?><tr><td data-user-content><?= ah($row['username']) ?> <small>#<?= (int)$row['id'] ?></small></td><td><?= ah($row['created_at']) ?></td><td><?= ah($row['last_active_at']??'—') ?></td><td><a href="<?= ah(opsUrl('/activity',$filters,['player_id'=>$row['id']])) ?>"><?= opsText('player_timeline') ?> →</a></td></tr><?php endforeach ?></tbody></table></div><?php if(!$list['rows']): ?><p class="empty"><?= opsText('no_players') ?></p><?php endif ?><?php endif ?></section>
<?php if($list['next']): ?><a class="button secondary" href="<?= ah(opsUrl('/activity',$filters,['mode'=>$mode,'before'=>$list['next']])) ?>"><?= opsText('next_page') ?> →</a><?php endif ?>
<?php else:
$eventInput['before']=\Conquer\Admin\OperationsDashboard::integer($_GET['before']??0);
$list=\Conquer\Admin\OperationsDashboard::events($filters,$eventInput);
?>
<section class="card"><form method="get" class="ops-filters"><input type="hidden" name="world_id" value="<?= $filters['world_id'] ?>"><input type="hidden" name="days" value="<?= $filters['days'] ?>"><label><?= opsText('player_id') ?><input name="player_id" type="number" min="1" value="<?= $playerFilter?:'' ?>"></label><label><?= opsText('category') ?><select name="category"><option value=""><?= opsText('all') ?></option><?php foreach(['action','reward','error','connection','system'] as $category): ?><option value="<?= $category ?>" <?= $eventInput['category']===$category?'selected':'' ?>><?= opsText('category_'.$category) ?></option><?php endforeach ?></select></label><label><?= opsText('result') ?><select name="outcome"><option value=""><?= opsText('all') ?></option><?php foreach(['success','replayed','rejected','failed'] as $outcome): ?><option value="<?= $outcome ?>" <?= $eventInput['outcome']===$outcome?'selected':'' ?>><?= opsText('outcome_'.$outcome) ?></option><?php endforeach ?></select></label><label><?= opsText('search_events') ?><input name="q" maxlength="100" value="<?= ah($eventInput['q']) ?>"></label><button type="submit"><?= opsText('apply') ?></button></form>
<?php if(!$list['available']): ?><p class="notice"><?= opsText('events_missing') ?></p><?php else: ?><?php opsEvents($list['rows'],$filters,'/activity',$eventInput); ?><?php endif ?>
<?php if($list['next']): ?><a href="<?= ah(opsUrl('/activity',$filters,array_replace($eventInput,['before'=>$list['next']]))) ?>"><?= opsText('older_events') ?> →</a><?php endif ?></section>
<?php if($playerFilter): ?><div class="ops-profile-links"><a class="button secondary" href="<?= APP_BASE ?>/admin/players/<?= $playerFilter ?>?world_id=<?= $filters['world_id']?:$selectedWorld ?>"><?= opsText('player_profile') ?></a><a class="button secondary" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'actual','player_id'=>$playerFilter,'type'=>'monster'])) ?>"><?= opsText('actual_rewards') ?></a></div><?php endif ?>
<p class="subtle ops-coverage"><?= opsText('actions_coverage') ?></p>
<?php endif ?>
</div>
