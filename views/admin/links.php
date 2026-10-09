<?php
declare(strict_types=1);
use Conquer\Admin\LinkTrackerAdmin;
use Conquer\Game\Locale;
$list = LinkTrackerAdmin::listing($db, $_GET);
$hasCampaign = (bool)$db->query("SELECT 1 FROM link_tracker_links WHERE kind='campaign' LIMIT 1")->fetchColumn();
$lt = static fn(string $key, array $params=[]): string => Locale::html('links.' . $key, $params);
$query = ['days'=>$list['days'],'kind'=>$list['kind']];
$url = static fn(array $extra=[]): string => APP_BASE . '/admin/links?' . http_build_query(array_replace($query,$extra));
$draft = $_SESSION['admin_link_draft'] ?? [];
unset($_SESSION['admin_link_draft']);
$campaignForm = static function(array $row=[], bool $creating=false) use ($canEdit,$csrf,$lt): void {
    ?>
    <form method="post" action="<?= APP_BASE ?>/admin/action/link-save" class="admin-form link-editor">
      <input type="hidden" name="csrf_token" value="<?= ah($csrf) ?>">
      <input type="hidden" name="operation_id" value="<?= bin2hex(random_bytes(16)) ?>">
      <input type="hidden" name="link_id" value="<?= (int)($row['id']??$row['link_id']??0) ?>">
      <input type="hidden" name="revision" value="<?= (int)($row['revision']??0) ?>">
      <fieldset <?= !$canEdit?'disabled':'' ?>>
        <div class="link-form-grid">
          <label><?= $lt('label') ?><input name="label" required maxlength="120" value="<?= ah($row['label']??'') ?>" data-user-content></label>
          <label><?= $lt('slug') ?><input name="slug" required minlength="3" maxlength="64" pattern="[a-z0-9][a-z0-9-]{2,63}" value="<?= ah($row['slug']??'') ?>" <?= !$creating?'readonly':'' ?> data-user-content><small><?= $lt('slug_hint') ?></small></label>
          <label class="link-target-field"><?= $lt('target') ?><input name="destination" required maxlength="2048" value="<?= ah($row['destination']??'/') ?>" data-user-content><small><?= $lt('target_hint') ?></small></label>
          <label><?= $lt('status') ?><select name="enabled"><option value="1" <?= (string)($row['enabled']??'1')==='1'?'selected':'' ?>><?= $lt('active') ?></option><option value="0" <?= (string)($row['enabled']??'1')==='0'?'selected':'' ?>><?= $lt('paused') ?></option></select></label>
          <label><?= $lt('reason') ?><input name="reason" required minlength="3" maxlength="500" value="<?= ah($row['reason']??'') ?>" data-user-content></label>
        </div>
        <button type="submit"><?= $lt($creating?'create':'save') ?></button>
      </fieldset>
    </form>
    <?php
};
?>
<div class="link-tracker">
<?php if(!\Conquer\Analytics\LinkTracker::enabled()): ?><p class="notice"><?= $lt('disabled') ?></p><?php endif ?>
<p class="subtle"><?= $lt('privacy_hint') ?></p>
<section class="card">
  <form method="get" class="toolbar" action="<?= APP_BASE ?>/admin/links">
    <label><?= $lt('period') ?><select name="days"><?php foreach([1,7,30,90] as $days): ?><option value="<?= $days ?>" <?= $days===$list['days']?'selected':'' ?>><?= $lt($days===1?'today':'days',['count'=>$days]) ?></option><?php endforeach ?></select></label>
    <label><?= $lt('type') ?><select name="kind"><?php foreach([''=>'all','campaign'=>'campaign','website'=>'website'] as $kind=>$key): ?><option value="<?= $kind ?>" <?= $list['kind']===$kind?'selected':'' ?>><?= $lt($key) ?></option><?php endforeach ?></select></label>
    <?php if($list['id']): ?><input type="hidden" name="link_id" value="<?= $list['id'] ?>"><?php endif ?>
    <button type="submit"><?= $lt('apply') ?></button>
    <a class="button secondary" href="<?= ah($url(['export'=>'csv','link_id'=>$list['id']])) ?>"><?= $lt('export') ?></a>
  </form>
  <p class="subtle"><?= $lt('since',['date'=>$list['since']]) ?></p>
  <?php if($list['selected']): ?><p><strong data-user-content><?= ah(LinkTrackerAdmin::label($list['selected'])) ?></strong> · <a href="<?= ah($url()) ?>"><?= $lt('clear') ?></a></p><?php endif ?>
</section>
<div class="stats link-stats">
  <?php foreach(['clicks','campaign','website'] as $metric): ?><div class="stat"><span><?= $lt($metric) ?></span><strong><?= an($list['summary'][$metric]) ?></strong></div><?php endforeach ?>
</div>
<div class="link-insights">
  <section class="card"><h2><?= $lt('daily') ?></h2>
    <?php if(!$list['daily']): ?><p class="empty"><?= $lt('empty') ?></p><?php else: $byDay=array_column($list['daily'],'clicks','day');$max=max($byDay); ?>
    <div class="link-chart" tabindex="0" role="region" aria-label="<?= $lt('daily') ?>">
      <?php for($i=$list['days']-1;$i>=0;$i--): $date=gmdate('Y-m-d',strtotime($list['since'].' UTC')+$i*86400);$count=(int)($byDay[$date]??0); ?><div><time datetime="<?= $date ?>"><?= $date ?></time><progress max="<?= $max ?>" value="<?= $count ?>" aria-label="<?= $lt('clicks') ?> · <?= $date ?>"><?= $count ?></progress><strong><?= an($count) ?></strong></div><?php endfor ?>
    </div><?php endif ?>
  </section>
  <section class="card"><h2><?= $lt('sources') ?></h2><?php foreach($list['sources'] as $row): ?><div class="split"><span><?= $lt('source.'.$row['source']) ?></span><strong><?= an($row['clicks']) ?></strong></div><?php endforeach ?><?php if(!$list['sources']): ?><p class="empty"><?= $lt('empty') ?></p><?php endif ?><p class="subtle"><?= $lt('source_hint') ?></p>
    <h2><?= $lt('devices') ?></h2><?php foreach($list['devices'] as $row): ?><div class="split"><span><?= $lt('device.'.$row['device']) ?></span><strong><?= an($row['clicks']) ?></strong></div><?php endforeach ?>
  </section>
</div>
<?php if($canEdit): ?><section class="card"><details <?= !$hasCampaign||($draft&&empty($draft['link_id']))?'open':'' ?>><summary><?= $lt('create_title') ?></summary><p><?= $lt('create_hint') ?></p><?php $campaignForm(empty($draft['link_id'])?$draft:[],true); ?></details></section><?php endif ?>
<?php if(!empty($draft['link_id'])&&$canEdit): ?><section class="card"><h2><?= $lt('draft') ?></h2><?php $campaignForm($draft); ?></section><?php endif ?>
<section class="card"><h2><?= $lt('links') ?> <small>(<?= an($list['total']) ?>)</small></h2>
  <?php if(!$list['rows']): ?><p class="empty"><?= $lt('empty_links') ?></p><?php endif ?>
  <div class="link-list">
  <?php foreach($list['rows'] as $row): $campaign=$row['kind']==='campaign'; ?>
    <article class="link-row" data-link-id="<?= (int)$row['id'] ?>">
      <div class="link-row-heading"><div><h3 <?= $campaign?'data-user-content':'data-i18n="links.site.'.ah($row['label']).'"' ?>><?= ah(LinkTrackerAdmin::label($row)) ?></h3><span class="pill"><?= $lt($row['kind']) ?></span> <span><?= $lt($row['enabled']?'active':'paused') ?></span></div><a class="button secondary" href="<?= ah($url(['link_id'=>$row['id']])) ?>"><?= $lt('details') ?></a></div>
      <dl class="link-row-stats"><div><dt><?= $lt('period_clicks') ?></dt><dd><?= an($row['clicks']) ?></dd></div><div><dt><?= $lt('lifetime') ?></dt><dd><?= an($row['lifetime']) ?></dd></div><div><dt><?= $lt('last') ?></dt><dd data-user-content><?= ah($row['last_click']??'—') ?></dd></div></dl>
      <?php if($campaign): ?><label><?= $lt('share_url') ?><span class="link-copy"><input readonly value="<?= ah(LinkTrackerAdmin::publicUrl($row['slug'])) ?>" aria-label="<?= $lt('share_url') ?>" data-user-content><button type="button" class="secondary" data-link-copy><?= $lt('copy') ?></button></span></label><p class="link-destination" data-user-content><?= ah($row['destination']) ?></p>
      <?php if($canEdit): ?><details><summary><?= $lt('edit') ?></summary><?php $campaignForm($row); ?></details><?php endif ?>
      <?php else: ?><p class="subtle"><?= $lt('automatic') ?></p><?php endif ?>
    </article>
  <?php endforeach ?>
  </div>
  <?php if($list['pages']>1): ?><nav class="toolbar" aria-label="<?= $lt('pagination') ?>"><?php if($list['page']>1): ?><a class="button secondary" href="<?= ah($url(['page'=>$list['page']-1])) ?>"><?= $lt('previous') ?></a><?php endif ?><span><?= $lt('page',['page'=>$list['page'],'pages'=>$list['pages']]) ?></span><?php if($list['page']<$list['pages']): ?><a class="button secondary" href="<?= ah($url(['page'=>$list['page']+1])) ?>"><?= $lt('next') ?></a><?php endif ?></nav><?php endif ?>
</section>
<p data-link-status role="status" aria-live="polite"></p>
</div>
