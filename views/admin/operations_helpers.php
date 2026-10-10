<?php declare(strict_types=1);
if(!function_exists('opsText')) {
    function opsText(string $key,array $params=[]): string { return \Conquer\Game\Locale::html('admin.ops.'.$key,$params); }
    function opsUrl(string $path,array $filters,array $extra=[]): string { return APP_BASE.'/admin'.$path.'?'.http_build_query(array_merge(['world_id'=>$filters['world_id'],'days'=>$filters['days']],$extra)); }
    function opsNumber(mixed $value): string { return $value===null?'—':ah(number_format((float)$value,0,'.',',')); }
    function opsEventTitle(array $event): string {
        $context=json_decode((string)$event['context_json'],true)?:[];
        return $event['category']==='action'&&is_string($context['action']??null)&&$context['action']!==''?$context['action']:(string)$event['code'];
    }
    function opsFallbackNotice(): void {
        $fallback=\Conquer\Observability\EventLog::fallbackStatus();
        if($fallback['files']>0)echo '<p class="notice" role="status">'.opsText('fallback_pending',['count'=>$fallback['files'],'time'=>$fallback['latest']??'—']).'</p>';
    }
    function opsFilters(array $filters,array $worlds,array $extra=[]): void { ?>
        <form method="get" class="ops-filters">
        <?php foreach($extra as $name=>$value): ?><input type="hidden" name="<?= ah($name) ?>" value="<?= ah($value) ?>"><?php endforeach ?>
        <label><?= opsText('world') ?><select name="world_id"><option value="0"><?= opsText('all_worlds') ?></option><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$filters['world_id']?'selected':'' ?> data-user-content><?= ah($w['name']) ?></option><?php endforeach ?></select></label>
        <label><?= opsText('period') ?><select name="days"><?php foreach([1,7,30,90] as $days): ?><option value="<?= $days ?>" <?= $filters['days']===$days?'selected':'' ?>><?= opsText($days===1?'last_day':'last_days',['days'=>$days]) ?></option><?php endforeach ?></select></label>
        <button type="submit" class="secondary"><?= opsText('apply') ?></button><span class="subtle"><?= opsText('updated') ?> <time><?= ah($filters['until']) ?> UTC</time></span>
        </form>
    <?php }
    function opsEvents(array $rows,array $filters,string $path='/activity',array $returnFilters=[]): void { ?>
        <?php if(!$rows): ?><p class="empty"><?= opsText('no_events') ?></p><?php else: ?><div class="table-wrap" tabindex="0" role="region" aria-label="<?= opsText('recorded_events') ?>"><table class="ops-event-table"><thead><tr><th><?= opsText('time') ?></th><th><?= opsText('player_world') ?></th><th><?= opsText('event') ?></th><th><?= opsText('result') ?></th></tr></thead><tbody>
        <?php foreach($rows as $row): ?><tr><td><time><?= ah($row['occurred_at']) ?></time><small>UTC</small></td><td><?php if($row['player_id']): ?><a href="<?= ah(opsUrl('/activity',$filters,['player_id'=>(int)$row['player_id']])) ?>" data-user-content><?= ah($row['username']??'#'.$row['player_id']) ?></a><?php else: ?>—<?php endif ?><small data-user-content><?= ah($row['world_name']??'—') ?></small></td><td><a href="<?= ah(opsUrl($path,$filters,array_merge($returnFilters,['event'=>(int)$row['id']]))) ?>" data-user-content><?= ah(opsEventTitle($row)) ?></a><small data-user-content><?= ah($row['route']) ?></small></td><td><span class="pill <?= in_array($row['outcome'],['failed','rejected'],true)?'closed':'' ?>" data-user-content><?= ah($row['outcome']) ?></span><small><?= opsText($row['origin']==='client'?'client_observation':'server_record') ?></small></td></tr><?php endforeach ?>
        </tbody></table></div><?php endif ?>
    <?php }
}
