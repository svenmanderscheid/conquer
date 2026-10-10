<?php
use Conquer\Admin\CaseService;
use Conquer\Game\Locale;
$ct=static fn(string $key,array $params=[]):string=>'<span data-i18n="admin.cases.'.ah($key).'">'.ah(CaseService::t($key,$params)).'</span>';
if(!CaseService::available()){echo '<section class="card"><h2>'.$ct('title').'</h2><p class="notice">'.$ct('migration_required').'</p></section>';return;}
$inbox=CaseService::inbox($adminSession,$_GET);$f=$inbox['filters'];
$source=is_string($_GET['source']??null)&&in_array($_GET['source'],['bug','content'],true)?$_GET['source']:($inbox['rows'][0]['source_type']??'');
$caseId=max(0,(int)($_GET['case_id']??($inbox['rows'][0]['source_id']??0)));$selected=null;$detailError='';
if($caseId&&$source)try{$selected=CaseService::detail($adminSession,$source,$caseId);}catch(DomainException $e){$detailError=$e->getMessage();}
$link=static fn(array $extra):string=>APP_BASE.'/admin/cases?'.http_build_query(array_merge($f,$extra));
$draft=$_SESSION['admin_case_draft']??[];if(($draft['source']??'')!==$source||(int)($draft['case_id']??0)!==$caseId)$draft=[];
$caseForm=static function(string $action)use($selected,$draft,$csrf):void{
    $same=($draft['case_action']??'')===$action;
    echo '<form method="post" action="'.ah(APP_BASE.'/admin/cases/action').'" class="admin-form case-form" data-case-form><input type="hidden" name="csrf_token" value="'.ah($csrf).'"><input type="hidden" name="source" value="'.ah($selected['source_type']).'"><input type="hidden" name="case_id" value="'.(int)$selected['source_id'].'"><input type="hidden" name="case_action" value="'.ah($action).'"><input type="hidden" name="version" value="'.ah($selected['version']).'"><input type="hidden" name="operation_id" value="'.ah($same&&preg_match('/^[a-f0-9]{32}$/D',$draft['operation_id']??'')?$draft['operation_id']:bin2hex(random_bytes(16))).'">';
};
$reason=static function(string $action)use($ct,$draft):void{echo '<label>'.$ct('reason').'<input name="reason" required minlength="3" maxlength="500" value="'.ah(($draft['case_action']??'')===$action?($draft['reason']??''):'').'"></label>';};
?>
<div class="case-workspace">
<section class="card case-toolbar">
 <div class="case-heading"><div><h2><?= $ct('title') ?></h2><p><?= $ct('intro') ?></p></div><strong><?= (int)$inbox['total'] ?> <?= $ct('cases') ?></strong></div>
 <nav class="case-scopes" aria-label="<?= ah(CaseService::t('inbox')) ?>"><?php foreach(['all','mine','unassigned'] as $scope): ?><a class="<?= $f['scope']===$scope?'active':'' ?>" <?= $f['scope']===$scope?'aria-current="page"':'' ?> href="<?= ah($link(['scope'=>$scope,'page'=>1])) ?>"><?= $ct('scope_'.$scope) ?></a><?php endforeach ?></nav>
 <form method="get" action="<?= ah(APP_BASE.'/admin/cases') ?>" class="case-filters">
  <input type="hidden" name="scope" value="<?= ah($f['scope']) ?>">
  <label><?= $ct('world') ?><select name="case_world"><option value="0"><?= ah(CaseService::t('all_worlds')) ?></option><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $f['case_world']===(int)$w['id']?'selected':'' ?> data-user-content><?= ah($w['name']) ?></option><?php endforeach ?></select></label>
  <label><?= $ct('kind') ?><select name="kind"><option value=""><?= ah(CaseService::t('all_types')) ?></option><?php foreach(['bug','support','idea','content'] as $kind):if(!CaseService::allowed($adminSession['role'],$kind==='content'?'content':'bug'))continue; ?><option value="<?= $kind ?>" <?= $f['kind']===$kind?'selected':'' ?>><?= ah(CaseService::t('kind_'.$kind)) ?></option><?php endforeach ?></select></label>
  <label><?= $ct('status') ?><select name="status"><?php foreach(array_merge(['open','all'],CaseService::STATUSES) as $status): ?><option value="<?= $status ?>" <?= $f['status']===$status?'selected':'' ?>><?= ah(CaseService::t('status_'.$status)) ?></option><?php endforeach ?></select></label>
  <label><?= $ct('priority') ?><select name="priority"><option value=""><?= ah(CaseService::t('all_priorities')) ?></option><?php foreach(array_merge(['attention'],CaseService::PRIORITIES) as $priority): ?><option value="<?= $priority ?>" <?= $f['priority']===$priority?'selected':'' ?>><?= ah(CaseService::t('priority_'.$priority)) ?></option><?php endforeach ?></select></label>
  <label><?= $ct('search') ?><input name="q" maxlength="120" value="<?= ah($f['q']) ?>"></label><button type="submit"><?= $ct('filter') ?></button>
 </form>
</section>
<div class="case-columns <?= $selected?'has-case':'' ?>">
 <aside class="card case-inbox"><h2><?= $ct('inbox') ?></h2>
 <?php if(!$inbox['rows']): ?><p class="empty"><?= $ct('empty') ?></p><?php endif ?>
 <?php foreach($inbox['rows'] as $row):$active=$source===$row['source_type']&&$caseId===(int)$row['source_id']; ?>
  <a class="case-list-item <?= $active?'active':'' ?>" href="<?= ah($link(['source'=>$row['source_type'],'case_id'=>$row['source_id'],'page'=>$inbox['page']])) ?>#case-detail" <?= $active?'aria-current="page"':'' ?>>
   <span class="case-line"><small><?= $ct('kind_'.$row['kind']) ?> #<?= (int)$row['source_id'] ?></small><span class="case-priority <?= ah($row['case_priority']) ?>"><?= $ct('priority_'.$row['case_priority']) ?></span></span>
   <strong data-user-content><?= $row['kind']==='content'?Locale::html('social.reason_'.$row['title']):ah($row['title']) ?></strong>
   <span class="case-line"><span data-user-content><?= ah($row['reporter_name']) ?></span><small><?= $ct('status_'.$row['status']) ?></small></span>
   <small><span data-user-content><?= ah($row['assignee']??CaseService::t('unassigned')) ?></span> · <?= ah($row['created_at']) ?> UTC</small>
  </a>
 <?php endforeach ?>
 <nav class="case-pagination" aria-label="<?= ah(CaseService::t('pages')) ?>"><?php if($inbox['page']>1): ?><a class="button secondary" href="<?= ah($link(['page'=>$inbox['page']-1])) ?>"><?= $ct('previous') ?></a><?php endif ?><span><?= (int)$inbox['page'] ?> / <?= (int)$inbox['pages'] ?></span><?php if($inbox['page']<$inbox['pages']): ?><a class="button secondary" href="<?= ah($link(['page'=>$inbox['page']+1])) ?>"><?= $ct('next') ?></a><?php endif ?></nav>
 </aside>
 <?php if($detailError): ?><section class="card"><p class="notice error"><?= ah($detailError) ?></p></section><?php endif ?>
 <?php if($selected): ?>
 <section class="card case-detail" id="case-detail" tabindex="-1">
  <div class="case-heading"><div><small><?= $ct('kind_'.$selected['kind']) ?> #<?= $caseId ?></small><h2 data-user-content><?= $selected['kind']==='content'?Locale::html('social.reason_'.$selected['title']):ah($selected['title']) ?></h2></div><span class="case-priority <?= ah($selected['priority']) ?>"><?= $ct('priority_'.$selected['priority']) ?></span></div>
  <?php if($draft): ?><p class="notice"><?= $ct('draft_retained') ?></p><?php endif ?>
  <article class="case-message original"><div class="case-line"><strong data-user-content><?= ah($selected['reporter_name']) ?></strong><small><?= ah($selected['created_at']) ?> UTC</small></div><p data-user-content><?= nl2br(ah($selected['description'])) ?></p>
   <?php foreach(['reproduction_steps'=>'steps','expected_result'=>'expected'] as $field=>$label):if(!empty($selected[$field])): ?><h3><?= $ct($label) ?></h3><p data-user-content><?= nl2br(ah($selected[$field])) ?></p><?php endif;endforeach ?>
   <?php if(!empty($selected['snapshot_json'])):$snapshot=json_decode($selected['snapshot_json'],true)?:[]; ?><h3><?= $ct('reported_content') ?></h3><blockquote data-user-content><?= nl2br(ah($snapshot['message']??'')) ?></blockquote><?php endif ?>
   <?php if(!empty($selected['has_screenshot'])): ?><a href="<?= ah(APP_BASE.'/admin/bug-reports/'.$caseId.'/screenshot') ?>" target="_blank" rel="noopener"><img class="case-screenshot" src="<?= ah(APP_BASE.'/admin/bug-reports/'.$caseId.'/screenshot') ?>" alt="<?= ah(CaseService::t('screenshot')) ?>" loading="lazy"></a><?php endif ?>
  </article>
  <h3><?= $ct('conversation') ?></h3><div class="case-timeline">
  <?php if($selected['admin_note']!==''): ?><article class="case-message internal"><strong><?= $ct('legacy_note') ?></strong><p data-user-content><?= nl2br(ah($selected['admin_note'])) ?></p></article><?php endif ?>
  <?php foreach($selected['messages'] as $message):$event=json_decode($message['event_json']??'null',true)?:[]; ?>
   <article class="case-message <?= ah($message['visibility']) ?>"><div class="case-line"><strong data-user-content><?= ah($message['author_name']??'#'.$message['author_id']) ?></strong><small><?= ah($message['created_at']) ?> UTC</small></div><small class="case-visibility"><?= $ct('visibility_'.$message['visibility']) ?></small>
   <?php if($message['body']!==''): ?><p data-user-content><?= nl2br(ah($message['body'])) ?></p><?php endif ?>
   <?php if($event): ?><p><?= $ct('event_'.($event['action']??'update')) ?></p><?php if(($event['action']??'')==='update'): ?><p><?= $ct('status') ?>: <?= $ct('status_'.$event['before']['status']) ?> → <?= $ct('status_'.$event['after']['status']) ?> · <?= $ct('priority') ?>: <?= $ct('priority_'.$event['before']['priority']) ?> → <?= $ct('priority_'.$event['after']['priority']) ?></p><p><?= $ct('assigned') ?>: <?php $teamNames=array_column($selected['team'],'username','id'); ?><span data-user-content><?= ah($teamNames[$event['before']['assigned_to']??0]??($event['before']['assigned_to']?'#'.$event['before']['assigned_to']:CaseService::t('unassigned'))) ?></span> → <span data-user-content><?= ah($teamNames[$event['after']['assigned_to']??0]??($event['after']['assigned_to']?'#'.$event['after']['assigned_to']:CaseService::t('unassigned'))) ?></span></p><?php endif ?><?php if(!empty($event['reason'])): ?><small data-user-content><?= ah($event['reason']) ?></small><?php endif ?><?php endif ?>
   </article>
  <?php endforeach ?>
  </div>
  <div class="case-composers">
   <details open><summary><?= $ct('reply') ?></summary><p><?= $ct('public_hint') ?></p><?php $caseForm('reply'); ?><label><?= $ct('message') ?><textarea name="body" required maxlength="4000" rows="4"><?= ah(($draft['case_action']??'')==='reply'?($draft['body']??''):'') ?></textarea></label><?php $reason('reply'); ?><button type="submit"><?= $ct('send_reply') ?></button></form></details>
   <details><summary><?= $ct('note') ?></summary><p><?= $ct('private_hint') ?></p><?php $caseForm('note'); ?><label><?= $ct('message') ?><textarea name="body" required maxlength="4000" rows="4"><?= ah(($draft['case_action']??'')==='note'?($draft['body']??''):'') ?></textarea></label><?php $reason('note'); ?><button type="submit" class="secondary"><?= $ct('save_note') ?></button></form></details>
  </div>
 </section>
 <aside class="card case-context"><h2><?= $ct('handling') ?></h2>
  <?php $caseForm('update'); ?><label><?= $ct('assigned') ?><select name="assigned_to"><option value="0"><?= ah(CaseService::t('unassigned')) ?></option><?php foreach($selected['team'] as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$selected['assigned_to']===(int)$member['id']?'selected':'' ?> data-user-content><?= ah($member['username']) ?></option><?php endforeach ?></select></label>
  <button type="button" class="secondary" data-case-claim="<?= (int)$adminSession['id'] ?>"><?= $ct('claim') ?></button>
  <label><?= $ct('status') ?><select name="status"><?php foreach(CaseService::STATUSES as $status): ?><option value="<?= $status ?>" <?= $selected['status']===$status?'selected':'' ?>><?= ah(CaseService::t('status_'.$status)) ?></option><?php endforeach ?></select></label>
  <label><?= $ct('priority') ?><select name="priority"><?php foreach(CaseService::PRIORITIES as $priority): ?><option value="<?= $priority ?>" <?= $selected['priority']===$priority?'selected':'' ?>><?= ah(CaseService::t('priority_'.$priority)) ?></option><?php endforeach ?></select></label>
  <?php $reason('update'); ?><button type="submit"><?= $ct('save') ?></button></form>
  <hr><h3><?= $ct('context') ?></h3><dl class="case-facts"><dt><?= $ct('world') ?></dt><dd data-user-content><?= ah($selected['world_name']) ?> (#<?= (int)$selected['world_id'] ?>)</dd><dt><?= $ct('reporter') ?></dt><dd data-user-content><?= ah($selected['reporter_name']) ?> (#<?= (int)$selected['reporter_id'] ?>)</dd>
  <?php if($selected['kind']==='content'): ?><dt><?= $ct('reported_player') ?></dt><dd data-user-content><?= ah($selected['player_name']) ?> (#<?= (int)$selected['player_id'] ?>)</dd><?php endif ?>
  <?php if(!empty($selected['page_path'])): ?><dt><?= $ct('screen') ?></dt><dd data-user-content><?= ah($selected['page_path']) ?></dd><?php endif ?></dl>
  <?php if(!empty($selected['client_context'])): ?><details><summary><?= $ct('diagnostics') ?></summary><dl class="case-facts"><?php foreach(json_decode($selected['client_context'],true)?:[] as $key=>$value): ?><dt data-user-content><?= ah($key) ?></dt><dd data-user-content><?= ah($value) ?></dd><?php endforeach ?></dl></details><?php endif ?>
  <?php if($adminSession['role']!=='support'): ?><a class="button secondary" href="<?= ah(APP_BASE.'/admin/players/'.(int)$selected['player_id'].'?world_id='.(int)$selected['world_id']) ?>"><?= $ct('player_history') ?></a><?php endif ?>
  <p class="case-muted"><?= $ct('permissions_hint') ?></p>
  <details class="case-evidence"><summary><?= $ct('nearby_events') ?></summary><p class="case-muted"><?= $ct('evidence_hint') ?></p>
   <?php if(!$selected['evidence']['events_available']): ?><p><?= $ct('evidence_unavailable') ?></p><?php elseif(!$selected['evidence']['events']): ?><p><?= $ct('no_nearby_events') ?></p><?php endif ?>
   <?php foreach($selected['evidence']['events'] as $event): ?><article class="case-message"><strong data-user-content><?= ah($event['code']) ?></strong><small><?= ah($event['occurred_at']) ?> UTC</small><p data-user-content><?= ah($event['message']) ?></p><dl class="case-facts"><dt><?= $ct('result') ?></dt><dd data-user-content><?= ah($event['outcome']) ?> · <?= ah($event['origin']) ?></dd><dt><?= $ct('screen') ?></dt><dd data-user-content><?= ah($event['route']) ?></dd><?php if($event['request_id']): ?><dt><?= $ct('request_id') ?></dt><dd data-user-content><?= ah($event['request_id']) ?></dd><?php endif ?><?php if($event['operation_id']): ?><dt><?= $ct('operation_id') ?></dt><dd data-user-content><?= ah($event['operation_id']) ?></dd><?php endif ?></dl></article><?php endforeach ?>
  </details>
  <details class="case-evidence"><summary><?= $ct('nearby_rewards') ?></summary><p class="case-muted"><?= $ct('evidence_hint') ?></p>
   <?php if(!$selected['evidence']['rewards_available']): ?><p><?= $ct('evidence_unavailable') ?></p><?php elseif(!$selected['evidence']['rewards']): ?><p><?= $ct('no_nearby_rewards') ?></p><?php endif ?>
   <?php foreach($selected['evidence']['rewards'] as $grant): ?><article class="case-message"><strong data-user-content><?= (int)$grant['quantity'] ?> × <?= ah($grant['resource_code']?:$grant['item_code']) ?></strong><small><?= ah($grant['created_at']) ?> UTC</small><p><?= $ct('confirmed_grant') ?></p><dl class="case-facts"><dt><?= $ct('source') ?></dt><dd data-user-content><?= ah($grant['reward_kind']) ?> · <?= ah($grant['source_type']) ?> · <?= ah($grant['source_key']) ?></dd><dt><?= $ct('source_reference') ?></dt><dd data-user-content><?= ah($grant['source_reference']) ?></dd><dt><?= $ct('rule_revision') ?></dt><dd data-user-content><?= ah($grant['rule_revision']) ?></dd><?php if($grant['operation_id']): ?><dt><?= $ct('operation_id') ?></dt><dd data-user-content><?= ah($grant['operation_id']) ?></dd><?php endif ?></dl></article><?php endforeach ?>
  </details>
  <?php if($source==='content'): ?><details><summary><?= $ct('moderation') ?></summary><p><?= $ct('moderation_hint') ?></p><?php $caseForm('chat-ban'); ?><label><?= $ct('minutes') ?><input type="number" name="minutes" value="60" min="1" max="43200" required></label><?php $reason('chat-ban'); ?><button type="submit"><?= $ct('restrict_chat') ?></button></form><?php $caseForm('chat-unban');$reason('chat-unban'); ?><button type="submit" class="secondary"><?= $ct('restore_chat') ?></button></form></details><?php endif ?>
 </aside>
 <?php endif ?>
</div></div>
