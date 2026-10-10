<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;

/** Read-only projections. Missing instrumentation is never presented as a healthy zero. */
final class OperationsDashboard
{
    public static function filters(array $input, array $worlds): array
    {
        $world = self::integer($input['world_id'] ?? 0);
        if ($world !== 0 && !in_array($world, array_map(static fn(array $w): int => (int)$w['id'], $worlds), true)) $world = 0;
        $days = self::integer($input['days'] ?? 1);
        if (!in_array($days, [1, 7, 30, 90], true)) $days = 1;
        $now = time();
        return ['world_id'=>$world, 'days'=>$days, 'since'=>gmdate('Y-m-d H:i:s', $now-$days*86400),
            'until'=>gmdate('Y-m-d H:i:s', $now), 'previous'=>gmdate('Y-m-d H:i:s', $now-2*$days*86400),
            'tab'=>in_array($input['tab']??'', ['activity','economy','stability'], true)?$input['tab']:'activity'];
    }

    public static function integer(mixed $value): int
    {
        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT) !== false ? max(0, (int)$value) : 0;
    }

    public static function available(string $table): bool
    {
        return (bool)Connection::getInstance()->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table])->fetchColumn();
    }

    private static function scope(array $f, string $column='world_id'): array
    {
        return $f['world_id'] ? [' AND '.$column.'=?', [$f['world_id']]] : ['', []];
    }

    public static function snapshot(array $f): array
    {
        $db=Connection::getInstance();[$scope,$wp]=self::scope($f);[$sessionScope,$sp]=self::scope($f,'s.active_world_id');
        $out=['activity_available'=>self::available('player_activity_minutes'), 'events_available'=>self::available('operational_events'),
            'active'=>null, 'previous_active'=>null, 'activity'=>[], 'errors'=>null, 'affected'=>null, 'connections'=>null,
            'recovered'=>null, 'invalid_rewards'=>null, 'actions'=>null, 'rejected'=>null, 'first_event'=>null, 'last_event'=>null,
            'recent'=>[], 'groups'=>[], 'releases'=>[], 'p95'=>null, 'latency_samples'=>0];
        $out['online']=(int)$db->query('SELECT COUNT(DISTINCT s.player_id) FROM sessions s WHERE s.last_active>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) AND s.expires_at>UTC_TIMESTAMP()'.$sessionScope,$sp)->fetchColumn();
        $registrationScope=$f['world_id']?' AND EXISTS(SELECT 1 FROM cities c WHERE c.player_id=p.id AND c.world_id=?)':'';
        $out['registrations']=(int)$db->query('SELECT COUNT(*) FROM players p WHERE p.created_at>=? AND p.created_at<=?'.$registrationScope,[$f['since'],$f['until'],...$wp])->fetchColumn();
        if($out['activity_available']) {
            $out['active']=(int)$db->query('SELECT COUNT(DISTINCT player_id) FROM player_activity_minutes WHERE minute_slot>=? AND minute_slot<=?'.$scope,[$f['since'],$f['until'],...$wp])->fetchColumn();
            $out['previous_active']=(int)$db->query('SELECT COUNT(DISTINCT player_id) FROM player_activity_minutes WHERE minute_slot>=? AND minute_slot<?'.$scope,[$f['previous'],$f['since'],...$wp])->fetchColumn();
            $bucket=$f['days']===1?7200:86400;
            $rows=$db->query('SELECT FLOOR(UNIX_TIMESTAMP(minute_slot)/'.$bucket.') AS bucket,COUNT(DISTINCT player_id) AS total FROM player_activity_minutes WHERE minute_slot>=? AND minute_slot<=?'.$scope.' GROUP BY bucket ORDER BY bucket',[$f['since'],$f['until'],...$wp])->fetchAll();
            $byBucket=array_column($rows,'total','bucket');
            $first=(int)floor(strtotime($f['since'].' UTC')/$bucket);$last=(int)floor(strtotime($f['until'].' UTC')/$bucket);
            for($i=$first;$i<=$last;$i++) $out['activity'][]=['time'=>gmdate('Y-m-d H:i:s',$i*$bucket),'label'=>gmdate($bucket===7200?'H:i':'d M',$i*$bucket),'count'=>(int)($byBucket[$i]??0)];
        }
        $out['worlds']=$db->query('SELECT w.id,w.name,w.status,s.settings_json,s.next_run_at,s.last_run_at,
            (SELECT COUNT(DISTINCT c.player_id) FROM cities c WHERE c.world_id=w.id) AS players,
            (SELECT COUNT(DISTINCT ss.player_id) FROM sessions ss WHERE ss.active_world_id=w.id AND ss.last_active>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) AND ss.expires_at>UTC_TIMESTAMP()) AS online
            FROM worlds w LEFT JOIN world_spawn_settings s ON s.world_id=w.id'.($f['world_id']?' WHERE w.id=?':'').' ORDER BY w.id',$wp)->fetchAll();
        $out['overdue']=[];
        foreach($out['worlds'] as &$world) {
            $settings=json_decode((string)($world['settings_json']??''),true)?:[];
            $world['spawn_state']='not_configured';
            if($world['settings_json']!==null) {
                $enabled=!empty($settings['enabled']) && in_array($world['status'],['running','open'],true);
                $window=\Conquer\Game\World\WorldSettings::inWindow(array_replace(\Conquer\Game\World\WorldSettings::defaults(),$settings),time());
                $overdue=$enabled && $window && $world['next_run_at'] && strtotime($world['next_run_at'].' UTC')<time()-300;
                $world['spawn_state']=$overdue?'overdue':($enabled?'scheduled':'paused');
                if($overdue)$out['overdue'][]=$world;
            }
        }
        unset($world);
        $out['reports']=(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE status IN ('new','in_progress')".$scope,$wp)->fetchColumn();
        $out['urgent_reports']=(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE status IN ('new','in_progress') AND priority IN ('high','urgent')".$scope,$wp)->fetchColumn();
        $out['reports_legacy']=true;
        if(CaseService::available()) {
            $counts=CaseService::overviewCounts($_SESSION['admin']??[],(int)$f['world_id']);
            $out['reports']=$counts['open'];$out['urgent_reports']=$counts['urgent'];$out['reports_legacy']=false;
        }
        if(!$out['events_available'])return $out;
        $row=$db->query("SELECT SUM(category='error' AND severity IN ('warning','error','critical')) errors,
            COUNT(DISTINCT CASE WHEN category='error' AND severity IN ('warning','error','critical') THEN player_id END) affected,
            SUM(category='connection' AND code IN ('NETWORK_FAILURE','NETWORK_TIMEOUT')) connections,
            SUM(category='connection' AND code='CONNECTION_RESTORED') recovered,
            SUM(category='reward' AND outcome='rejected') invalid_rewards,
            SUM(category='action' AND outcome='success') actions,SUM(category='action' AND outcome='rejected') rejected
            FROM operational_events WHERE occurred_at>=? AND occurred_at<=?".$scope,[$f['since'],$f['until'],...$wp])->fetch();
        foreach(['errors','affected','connections','recovered','invalid_rewards','actions','rejected'] as $key)$out[$key]=(int)($row[$key]??0);
        $coverage=$db->query('SELECT MIN(received_at) first_event,MAX(received_at) last_event FROM operational_events WHERE 1=1'.$scope,$wp)->fetch();
        $out['first_event']=$coverage['first_event'];$out['last_event']=$coverage['last_event'];
        $durationWhere="category='action' AND origin='server' AND duration_ms IS NOT NULL AND occurred_at>=? AND occurred_at<=?".$scope;
        $params=[$f['since'],$f['until'],...$wp];
        $out['latency_samples']=(int)$db->query('SELECT COUNT(*) FROM operational_events WHERE '.$durationWhere,$params)->fetchColumn();
        if($out['latency_samples']) {
            $offset=max(0,(int)ceil($out['latency_samples']*.95)-1);
            $out['p95']=(int)$db->query('SELECT duration_ms FROM operational_events WHERE '.$durationWhere.' ORDER BY duration_ms LIMIT 1 OFFSET '.$offset,$params)->fetchColumn();
        }
        $out['recent']=self::events($f,['category'=>'action'])['rows'];$out['recent']=array_slice($out['recent'],0,6);
        $out['groups']=array_slice(self::groups($f),0,5);
        $out['releases']=$db->query("SELECT release_id,COUNT(*) total,COUNT(DISTINCT player_id) players FROM operational_events WHERE category='error' AND occurred_at>=? AND occurred_at<=?".$scope.' GROUP BY release_id ORDER BY total DESC LIMIT 8',$params)->fetchAll();
        return $out;
    }

    public static function events(array $f, array $input): array
    {
        if(!self::available('operational_events'))return ['available'=>false,'rows'=>[],'next'=>0];
        [$scope,$wp]=self::scope($f,'e.world_id');
        $where='e.occurred_at>=? AND e.occurred_at<=?'.$scope;$params=[$f['since'],$f['until'],...$wp];
        foreach(['category'=>['action','error','connection','reward','system'],'outcome'=>['success','replayed','rejected','failed','restored','observed']] as $field=>$values) {
            if(in_array($input[$field]??'', $values, true)){$where.=' AND e.'.$field.'=?';$params[]=$input[$field];}
        }
        $player=self::integer($input['player_id']??0);if($player){$where.=' AND e.player_id=?';$params[]=$player;}
        $before=self::integer($input['before']??0);if($before){$where.=' AND e.id<?';$params[]=$before;}
        $group=is_string($input['group']??null)?$input['group']:'';
        if(preg_match('/^[a-f0-9]{64}$/D',$group)){$where.=' AND e.group_hash=?';$params[]=$group;}
        $q=is_string($input['q']??null)?mb_substr(trim($input['q']),0,100):'';
        if($q!==''){$where.=" AND (e.code LIKE ? OR e.route LIKE ? OR e.request_id=? OR e.operation_id=? OR p.username LIKE ? OR CASE WHEN JSON_VALID(e.context_json) THEN JSON_UNQUOTE(JSON_EXTRACT(e.context_json,'$.action')) ELSE '' END LIKE ?)";array_push($params,'%'.$q.'%','%'.$q.'%',$q,$q,'%'.$q.'%','%'.$q.'%');}
        $rows=Connection::getInstance()->query('SELECT e.*,p.username,w.name world_name FROM operational_events e LEFT JOIN players p ON p.id=e.player_id LEFT JOIN worlds w ON w.id=e.world_id WHERE '.$where.' ORDER BY e.id DESC LIMIT 51',$params)->fetchAll();
        $hasMore=count($rows)>50;if($hasMore)array_pop($rows);
        return ['available'=>true,'rows'=>$rows,'next'=>$hasMore?(int)end($rows)['id']:0];
    }

    public static function event(int $id, array $f): ?array
    {
        if(!$id||!self::available('operational_events'))return null;
        [$scope,$params]=self::scope($f,'e.world_id');
        return Connection::getInstance()->query('SELECT e.*,p.username,w.name world_name FROM operational_events e LEFT JOIN players p ON p.id=e.player_id LEFT JOIN worlds w ON w.id=e.world_id WHERE e.id=?'.$scope,[$id,...$params])->fetch()?:null;
    }

    public static function players(array $f,string $mode,int $before=0): array
    {
        $db=Connection::getInstance();$params=[];$where='1=1';
        if($mode==='players') {
            if(!self::available('player_activity_minutes'))return ['available'=>false,'rows'=>[],'next'=>0];
            [$scope,$wp]=self::scope($f,'a.world_id');
            $where='EXISTS(SELECT 1 FROM player_activity_minutes a WHERE a.player_id=p.id AND a.minute_slot>=? AND a.minute_slot<=?'.$scope.')';$params=[$f['since'],$f['until'],...$wp];
        } elseif($mode==='online') {
            [$scope,$wp]=self::scope($f,'s.active_world_id');
            $where='EXISTS(SELECT 1 FROM sessions s WHERE s.player_id=p.id AND s.last_active>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) AND s.expires_at>UTC_TIMESTAMP()'.$scope.')';$params=$wp;
        } else {
            $where='p.created_at>=? AND p.created_at<=?';$params=[$f['since'],$f['until']];
            if($f['world_id']){$where.=' AND EXISTS(SELECT 1 FROM cities c WHERE c.player_id=p.id AND c.world_id=?)';$params[]=$f['world_id'];}
        }
        if($before){$where.=' AND p.id<?';$params[]=$before;}
        $rows=$db->query('SELECT p.id,p.username,p.created_at,p.last_active_at FROM players p WHERE '.$where.' ORDER BY p.id DESC LIMIT 51',$params)->fetchAll();
        $more=count($rows)>50;if($more)array_pop($rows);
        return ['available'=>true,'rows'=>$rows,'next'=>$more?(int)end($rows)['id']:0];
    }

    public static function groups(array $f): array
    {
        if(!self::available('operational_events'))return [];
        [$scope,$params]=self::scope($f);
        return Connection::getInstance()->query("SELECT group_hash,MIN(code) code,MIN(category) category,MIN(origin) origin,MIN(route) route,COUNT(*) total,COUNT(DISTINCT player_id) players,MIN(occurred_at) first_seen,MAX(occurred_at) last_seen
            FROM operational_events WHERE occurred_at>=? AND occurred_at<=? AND (category='error' OR (category='reward' AND outcome='rejected') OR (category='connection' AND code IN ('NETWORK_FAILURE','NETWORK_TIMEOUT')))".$scope.' GROUP BY group_hash ORDER BY last_seen DESC LIMIT 50',[$f['since'],$f['until'],...$params])->fetchAll();
    }

    public static function economy(array $f): array
    {
        $db=Connection::getInstance();[$scope,$wp]=self::scope($f);$params=[$f['since'],$f['until'],...$wp];
        $out=['kills'=>(int)$db->query('SELECT COUNT(*) FROM monster_kill_receipts WHERE created_at>=? AND created_at<=?'.$scope,$params)->fetchColumn(),
            'pvp'=>(int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE defender_id IS NOT NULL AND created_at>=? AND created_at<=?'.$scope,$params)->fetchColumn()];
        $out['messages']=(int)$db->query('SELECT COUNT(*) FROM world_chat WHERE created_at>=? AND created_at<=?'.$scope,$params)->fetchColumn()
            +(int)$db->query('SELECT COUNT(*) FROM private_chat_messages WHERE created_at>=? AND created_at<=?'.$scope,$params)->fetchColumn();
        $sums=[];
        foreach(['food','lumber','stone','gold','gems'] as $resource)$sums[]="COALESCE(SUM(CASE WHEN JSON_VALID(haul_json) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(haul_json,'$.loot.".$resource."')) AS DECIMAL(30,4)) ELSE 0 END),0) AS ".$resource;
        $out['gathered']=$db->query("SELECT COUNT(*) total,".implode(',',$sums)." FROM marches WHERE march_type=9 AND state='complete' AND player_id>0 AND return_time>=? AND return_time<=?".$scope,$params)->fetch();
        return $out;
    }
}
