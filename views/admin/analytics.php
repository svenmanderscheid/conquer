<?php declare(strict_types=1);
require_once __DIR__.'/operations_helpers.php';
$filters=\Conquer\Admin\OperationsDashboard::filters($_GET,$worlds);
$analysis=\Conquer\Admin\OperationsDashboard::economy($filters);
?>
<div class="ops-workspace">
<?php opsFilters($filters,$worlds); ?>
<div class="ops-metrics">
<?php foreach([['hunt_count',$analysis['kills']],['gather_count',$analysis['gathered']['total']],['pvp_count',$analysis['pvp']],['messages_count',$analysis['messages']]] as [$label,$count]): ?><div class="ops-metric"><span><?= opsText($label) ?></span><strong><?= opsNumber($count) ?></strong></div><?php endforeach ?>
</div>
<div class="ops-overview-grid"><section class="card"><h2><?= opsText('gathered_resources') ?></h2><p class="subtle"><?= opsText('economy_scope') ?></p><?php foreach(['food','lumber','stone','gold','gems'] as $resource): ?><div class="split"><span><?= opsText('resource_'.$resource) ?></span><strong><?= opsNumber($analysis['gathered'][$resource]) ?></strong></div><?php endforeach ?></section>
<section class="card"><h2><?= opsText('reward_analysis') ?></h2><p><?= opsText('rewards_explanation') ?></p><div class="ops-profile-links"><a class="button secondary" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'actual'])) ?>"><?= opsText('actual_rewards') ?></a><a class="button secondary" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'invalid'])) ?>"><?= opsText('invalid_rewards') ?></a><a class="button secondary" href="<?= ah(opsUrl('',$filters,['tab'=>'activity'])) ?>"><?= opsText('tab_activity') ?></a></div></section></div>
<?php if($filters['world_id']): $selectedWorld=$filters['world_id'];$days=$filters['days'];$alpha=\Conquer\Admin\AlphaPlaytestAnalytics::snapshot($selectedWorld,$days); ?><details class="card"><summary><?= opsText('alpha_details') ?></summary><?php require __DIR__.'/alpha_playtest.php'; ?></details><?php else: ?><p class="notice"><?= opsText('choose_world_analytics') ?></p><?php endif ?>
</div>
