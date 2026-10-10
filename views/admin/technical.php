<?php declare(strict_types=1);
require_once __DIR__.'/operations_helpers.php';
$filters=\Conquer\Admin\OperationsDashboard::filters($_GET,$worlds);
$group=is_string($_GET['group']??null)&&preg_match('/^[a-f0-9]{64}$/D',$_GET['group'])?$_GET['group']:'';
$category=in_array($_GET['category']??'', ['error','connection','reward','system'],true)?$_GET['category']:'';
?>
<div class="ops-workspace">
<?php opsFilters($filters,$worlds,['category'=>$category,'group'=>$group]); ?>
<?php opsFallbackNotice(); ?>
<?php if(\Conquer\Admin\OperationsDashboard::integer($_GET['event']??0)): $eventPath='/technical'; require __DIR__.'/operational_event.php';
elseif(!\Conquer\Admin\OperationsDashboard::available('operational_events')): ?><p class="notice"><?= opsText('events_missing') ?></p>
<?php else: ?>
<nav class="ops-tabs" aria-label="<?= opsText('technical_state') ?>"><a href="<?= ah(opsUrl('/technical',$filters)) ?>" <?= !$category&&!$group?'aria-current="page"':'' ?>><?= opsText('error_groups') ?></a><?php foreach(['error','connection','reward','system'] as $key): ?><a href="<?= ah(opsUrl('/technical',$filters,['category'=>$key])) ?>" <?= $category===$key?'aria-current="page"':'' ?>><?= opsText('category_'.$key) ?></a><?php endforeach ?></nav>
<?php if($group||$category):
$eventInput=['category'=>$category,'group'=>$group,'before'=>\Conquer\Admin\OperationsDashboard::integer($_GET['before']??0)];
$list=\Conquer\Admin\OperationsDashboard::events($filters,$eventInput); ?>
<section class="card"><h2><?= opsText($group?'group_events':'recorded_events') ?></h2><?php opsEvents($list['rows'],$filters,'/technical',$eventInput); ?><?php if($list['next']): ?><a href="<?= ah(opsUrl('/technical',$filters,array_replace($eventInput,['before'=>$list['next']]))) ?>"><?= opsText('older_events') ?> →</a><?php endif ?></section>
<?php else: $groups=\Conquer\Admin\OperationsDashboard::groups($filters); ?>
<section class="card ops-group-list"><h2><?= opsText('error_groups') ?></h2><p class="subtle"><?= opsText('groups_hint') ?></p><div class="table-wrap" tabindex="0" role="region" aria-label="<?= opsText('error_groups') ?>"><table><thead><tr><th><?= opsText('event') ?></th><th><?= opsText('occurrences') ?></th><th><?= opsText('affected_players') ?></th><th><?= opsText('first_seen') ?> UTC</th><th><?= opsText('last_seen') ?> UTC</th></tr></thead><tbody><?php foreach($groups as $row): ?><tr><td><a href="<?= ah(opsUrl('/technical',$filters,['group'=>$row['group_hash']])) ?>" data-user-content><?= ah($row['code']) ?></a><small data-user-content><?= ah($row['route']) ?></small><small><?= opsText($row['origin']==='client'?'client_observation':'server_record') ?></small></td><td><?= opsNumber($row['total']) ?></td><td><?= opsNumber($row['players']) ?></td><td><?= ah($row['first_seen']) ?></td><td><?= ah($row['last_seen']) ?></td></tr><?php endforeach ?></tbody></table></div><?php if(!$groups): ?><p class="empty"><?= opsText('no_recorded_incidents') ?></p><?php endif ?></section>
<?php endif ?>
<p class="ops-coverage subtle"><?= opsText('technical_coverage') ?></p>
<?php endif ?>
</div>
