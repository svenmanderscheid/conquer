<?php declare(strict_types=1);
$eventId=\Conquer\Admin\OperationsDashboard::integer($_GET['event']??0);
$event=\Conquer\Admin\OperationsDashboard::event($eventId,$filters);
if(!$event){http_response_code(404);echo '<p class="notice">'.opsText('event_not_found').'</p>';return;}
$context=json_decode((string)$event['context_json'],true)?:[];
$returnFilters=[];foreach(['category','outcome','player_id','q','group','before'] as $key)if(is_scalar($_GET[$key]??null))$returnFilters[$key]=mb_substr((string)$_GET[$key],0,100);
?>
<section class="card"><div class="split"><h2 data-user-content><?= ah(opsEventTitle($event)) ?></h2><a class="button secondary" href="<?= ah(opsUrl($eventPath,$filters,$returnFilters)) ?>"><?= opsText('back_to_events') ?></a></div>
<p class="notice"><?= opsText($event['origin']==='client'?'client_evidence':'server_evidence') ?></p>
<dl class="ops-event-detail">
<?php foreach(['time'=>$event['occurred_at'].' UTC','received'=>$event['received_at'].' UTC','category'=>$event['category'],'result'=>$event['outcome'],'player'=>$event['username']??$event['player_id']??'—','world'=>$event['world_name']??$event['world_id']??'—','route'=>$event['route'],'release'=>$event['release_id'],'request_id'=>$event['request_id'],'operation_id'=>$event['operation_id'],'duration'=>$event['duration_ms']===null?'—':$event['duration_ms'].' ms','message'=>$event['message']] as $label=>$value): ?><div><dt><?= opsText($label) ?></dt><dd data-user-content><?= ah($value===''?'—':$value) ?></dd></div><?php endforeach ?>
</dl>
<?php if($context): ?><h3><?= opsText('safe_context') ?></h3><pre class="ops-context" data-user-content><?= ah(json_encode($context,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre><?php endif ?>
<div class="ops-profile-links"><a class="button secondary" href="<?= ah(opsUrl('/technical',$filters,['group'=>$event['group_hash']])) ?>"><?= opsText('same_group') ?></a><?php if($event['player_id']): ?><a class="button secondary" href="<?= ah(opsUrl('/activity',$filters,['player_id'=>$event['player_id']])) ?>"><?= opsText('player_timeline') ?></a><a class="button secondary" href="<?= APP_BASE ?>/admin/players/<?= (int)$event['player_id'] ?>?world_id=<?= (int)($event['world_id']?:$selectedWorld) ?>"><?= opsText('player_profile') ?></a><?php endif ?></div>
</section>
