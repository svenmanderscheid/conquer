<?php declare(strict_types=1);
require_once __DIR__.'/operations_helpers.php';
$filters=\Conquer\Admin\OperationsDashboard::filters($_GET,$worlds);
$overview=\Conquer\Admin\OperationsDashboard::snapshot($filters);
$tab=$filters['tab'];
?>
<div class="ops-workspace" data-operations-overview>
<?php opsFilters($filters,$worlds,['tab'=>$tab]); ?>
<?php opsFallbackNotice(); ?>
<div class="ops-metrics">
<?php foreach([
    ['online',$overview['online'],'last_five',opsUrl('/activity',$filters,['mode'=>'online'])],
    ['active_players',$overview['active'],'unique_in_period',opsUrl('/activity',$filters,['mode'=>'players'])],
    ['open_cases',$overview['reports'],$overview['reports_legacy']?'legacy_cases':'all_open_cases',opsUrl('/cases',$filters,['case_world'=>$filters['world_id']])],
    ['affected_players',$overview['affected'],'error_players',opsUrl('/technical',$filters,['category'=>'error'])]
] as [$label,$value,$hint,$url]): ?><a class="ops-metric" href="<?= ah($url) ?>"><span><?= opsText($label) ?></span><strong><?= opsNumber($value) ?></strong><small><?= opsText($value===null?'unavailable':$hint) ?></small></a><?php endforeach ?>
</div>
<div class="ops-overview-grid">
<section class="card ops-analysis"><div class="split"><h2><?= opsText('analysis') ?></h2><a href="<?= ah(opsUrl('/analytics',$filters)) ?>"><?= opsText('deep_analysis') ?> →</a></div>
<nav class="ops-tabs" aria-label="<?= opsText('analysis') ?>"><?php foreach(['activity','economy','stability'] as $key): ?><a href="<?= ah(opsUrl('',$filters,['tab'=>$key])) ?>" <?= $key===$tab?'aria-current="page"':'' ?>><?= opsText('tab_'.$key) ?></a><?php endforeach ?></nav>
<?php if($tab==='activity'): ?>
<div class="ops-chart-heading"><div><h3><?= opsText('active_players') ?></h3><p class="subtle"><?= opsText($filters['days']===1?'two_hour_buckets':'daily_buckets') ?></p></div><div><strong><?= opsNumber($overview['active']) ?></strong><small><?= opsText('previous_period',['count'=>$overview['previous_active']??'—']) ?></small></div></div>
<?php if(!$overview['activity_available']): ?><p class="notice"><?= opsText('activity_missing') ?></p><?php else: $max=max(1,...array_column($overview['activity'],'count')); ?>
<div class="ops-chart" role="img" aria-label="<?= opsText('activity_chart',['max'=>$max]) ?>"><?php foreach($overview['activity'] as $i=>$bucket): ?><div class="ops-chart-column"><span style="height:<?= round($bucket['count']/$max*100,3) ?>%" title="<?= ah($bucket['time'].' UTC · '.$bucket['count']) ?>"></span><small><?= $i%max(1,(int)ceil(count($overview['activity'])/6))===0?ah($bucket['label']):'' ?></small></div><?php endforeach ?></div>
<details class="ops-chart-values"><summary><?= opsText('show_values') ?></summary><div class="table-wrap"><table><thead><tr><th><?= opsText('time') ?> UTC</th><th><?= opsText('active_players') ?></th></tr></thead><tbody><?php foreach($overview['activity'] as $bucket): ?><tr><td><?= ah($bucket['time']) ?></td><td><?= opsNumber($bucket['count']) ?></td></tr><?php endforeach ?></tbody></table></div></details>
<?php endif ?>
<div class="ops-analysis-footer"><a href="<?= ah(opsUrl('/activity',$filters,['mode'=>'registrations'])) ?>"><?= opsText('new_accounts',['count'=>$overview['registrations']]) ?></a><a href="<?= ah(opsUrl('/activity',$filters)) ?>"><?= opsText('inspect_activity') ?> →</a></div>
<?php elseif($tab==='economy'): ?>
<h3><?= opsText('reward_analysis') ?></h3><p class="subtle"><?= opsText('rewards_explanation') ?></p>
<a class="ops-attention-row" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'actual','type'=>'monster'])) ?>"><span><strong><?= opsText('actual_rewards') ?></strong><small><?= opsText('actual_rewards_hint') ?></small></span><span>→</span></a>
<a class="ops-attention-row" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'invalid','type'=>'monster'])) ?>"><span><strong><?= opsText('invalid_rewards') ?></strong><small><?= opsText('invalid_rewards_hint') ?></small></span><strong><?= opsNumber($overview['invalid_rewards']) ?></strong></a>
<a class="ops-attention-row" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'rules','type'=>'farm'])) ?>"><span><strong><?= opsText('drop_rules') ?></strong><small><?= opsText('drop_rules_hint') ?></small></span><span>→</span></a>
<a class="ops-attention-row" href="<?= ah(opsUrl('/analytics',$filters)) ?>"><span><strong><?= opsText('resource_economy') ?></strong><small><?= opsText('economy_scope') ?></small></span><span>→</span></a>
<?php else: ?>
<h3><?= opsText('technical_state') ?></h3>
<?php foreach(['errors'=>'errors','connections'=>'connection_signals','recovered'=>'recoveries','invalid_rewards'=>'invalid_rewards'] as $key=>$label): ?><a class="ops-attention-row" href="<?= ah(opsUrl('/technical',$filters,['category'=>$key==='connections'||$key==='recovered'?'connection':($key==='invalid_rewards'?'reward':'error')])) ?>"><span><?= opsText($label) ?></span><strong><?= opsNumber($overview[$key]) ?></strong></a><?php endforeach ?>
<div class="ops-attention-row"><span><?= opsText('mutation_p95') ?><small><?= opsText('sample_size',['count'=>$overview['latency_samples']]) ?></small></span><strong><?= opsNumber($overview['p95']) ?> ms</strong></div>
<p class="subtle"><?= opsText('connection_scope') ?></p>
<?php endif ?>
</section>
<section class="card ops-attention"><h2><?= opsText('attention') ?></h2>
<a class="ops-attention-row" href="<?= ah(opsUrl('/cases',$filters,['case_world'=>$filters['world_id'],'priority'=>'attention'])) ?>"><span><strong><?= opsText('urgent_cases') ?></strong><small><?= opsText('review_team_cases') ?></small></span><strong><?= opsNumber($overview['urgent_reports']) ?></strong></a>
<a class="ops-attention-row" href="<?= ah(opsUrl('/rewards',$filters,['tab'=>'invalid','type'=>'monster'])) ?>"><span><strong><?= opsText('invalid_rewards') ?></strong><small><?= opsText('invalid_rewards_hint') ?></small></span><strong><?= opsNumber($overview['invalid_rewards']) ?></strong></a>
<?php foreach($overview['groups'] as $group): ?><a class="ops-attention-row" href="<?= ah(opsUrl('/technical',$filters,['group'=>$group['group_hash']])) ?>"><span><strong data-user-content><?= ah($group['code']) ?></strong><small><?= opsText('group_summary',['events'=>$group['total'],'players'=>$group['players']]) ?></small></span><span>→</span></a><?php endforeach ?>
<?php foreach($overview['overdue'] as $w): ?><a class="ops-attention-row" href="<?= ah(opsUrl('/world',['world_id'=>(int)$w['id'],'days'=>$filters['days']])) ?>#activity"><span><strong><?= opsText('spawn_overdue') ?></strong><small data-user-content><?= ah($w['name']) ?> · <?= ah($w['next_run_at']) ?> UTC</small></span><span>→</span></a><?php endforeach ?>
<?php if(!$overview['events_available']): ?><p class="notice"><?= opsText('events_missing') ?></p><?php elseif(!$overview['groups']&&!$overview['overdue']): ?><p class="subtle"><?= opsText('no_recorded_incidents') ?></p><?php endif ?>
</section>
</div>
<section class="card"><div class="split"><h2><?= opsText('world_overview') ?></h2><a href="<?= APP_BASE ?>/admin/world"><?= opsText('world_settings') ?> →</a></div><div class="table-wrap" tabindex="0" role="region" aria-label="<?= opsText('world_overview') ?>"><table class="ops-world-table"><thead><tr><th><?= opsText('world') ?></th><th><?= opsText('online') ?></th><th><?= opsText('world_players') ?></th><th><?= opsText('spawn_state') ?></th><th><?= opsText('last_run') ?> UTC</th></tr></thead><tbody><?php foreach($overview['worlds'] as $w): ?><tr><td><a href="<?= APP_BASE ?>/admin/world?world_id=<?= (int)$w['id'] ?>" data-user-content><?= ah($w['name']) ?></a><small data-user-content>#<?= (int)$w['id'] ?> · <?= ah($w['status']) ?></small></td><td><?= opsNumber($w['online']) ?></td><td><?= opsNumber($w['players']) ?></td><td><span class="pill <?= $w['spawn_state']==='overdue'?'closed':'' ?>"><?= opsText('spawn_'.$w['spawn_state']) ?></span></td><td><?= ah($w['last_run_at']??'—') ?></td></tr><?php endforeach ?></tbody></table></div></section>
<section class="card"><div class="split"><h2><?= opsText('recent_actions') ?></h2><a href="<?= ah(opsUrl('/activity',$filters)) ?>"><?= opsText('all_actions') ?> →</a></div><?php opsEvents($overview['recent'],$filters); ?></section>
<p class="ops-coverage subtle"><?= opsText('coverage') ?> <?= ah($overview['first_event']??'—') ?> UTC · <?= opsText('latest_record') ?> <?= ah($overview['last_event']??'—') ?> UTC. <?= opsText('coverage_hint') ?></p>
</div>
