<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Game\Community\SocialService;

/** A scoped team workflow over original reports; no separate copy of player evidence. */
final class CaseService
{
    public const STATUSES=['new','in_progress','waiting','resolved','closed'];
    public const PRIORITIES=['low','normal','high','urgent'];

    public static function t(string $key,array $params=[]):string { return Locale::t('admin.cases.'.$key,$params); }
    /** Missing migration is distinguishable from database/permission failures. */
    public static function available():bool
    {
        $db=Connection::getInstance();
        if((int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('support_cases','support_case_messages')")->fetchColumn()!==2)return false;
        return (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='admin_users' AND column_name='role' AND column_type LIKE '%support%') OR (table_name='bug_reports' AND column_name='report_type' AND column_type LIKE '%support%') OR (table_name IN ('bug_reports','community_reports') AND column_name='status' AND column_type LIKE '%waiting%'))")->fetchColumn()===4;
    }
    private static function fail(string $key):never { throw new \DomainException(self::t($key)); }
    private static function actor(int $id,bool $lock=false):array
    {
        $row=Connection::getInstance()->query('SELECT id,username,role FROM admin_users WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
        if(!$row||!in_array($row['role'],['superadmin','support','moderator'],true))self::fail('denied');
        return $row;
    }
    public static function allowed(string $role,string $source):bool
    { return $role==='superadmin'||($role==='support'&&$source==='bug')||($role==='moderator'&&$source==='content'); }
    private static function source(mixed $source):string
    { if(!is_string($source)||!in_array($source,['bug','content'],true))self::fail('invalid');return $source; }
    private static function integer(mixed $value,int $min=1):int
    { if((!is_int($value)&&!is_string($value))||!preg_match('/^\d{1,10}$/D',(string)$value)||(int)$value<$min||(int)$value>2147483647)self::fail('invalid');return (int)$value; }
    private static function text(mixed $value,int $min,int $max):string
    { if(!is_string($value)||mb_strlen(trim($value))<$min||mb_strlen(trim($value))>$max)self::fail('invalid');return trim($value); }
    private static function choice(mixed $value,array $allowed):string
    { if(!is_string($value)||!in_array($value,$allowed,true))self::fail('invalid');return $value; }

    private static function unionSql():string
    {
        return "SELECT 'bug' source_type,b.id source_id,b.player_id reporter_id,b.player_id, b.world_id,b.report_type kind,b.title,b.status,b.priority,b.created_at FROM bug_reports b
            UNION ALL SELECT 'content',r.id,r.reporter_id,r.player_id,r.world_id,'content',r.reason,
            CASE r.status WHEN 'reviewing' THEN 'in_progress' WHEN 'dismissed' THEN 'closed' ELSE r.status END,'normal',r.created_at FROM community_reports r";
    }
    /** SQL filtering and pagination cover the entire inbox rather than a truncated client list. */
    public static function inbox(array $admin,array $filters=[]):array
    {
        $actor=self::actor((int)$admin['id']);$conditions=[];$params=[];
        if($actor['role']!=='superadmin'){$conditions[]='q.source_type=?';$params[]=$actor['role']==='support'?'bug':'content';}
        $world=isset($filters['case_world'])?self::integer($filters['case_world'],0):0;
        if($world){$conditions[]='q.world_id=?';$params[]=$world;}
        $scope=is_string($filters['scope']??null)&&in_array($filters['scope'],['all','mine','unassigned'],true)?$filters['scope']:'all';
        if($scope==='mine'){$conditions[]='c.assigned_to=?';$params[]=(int)$actor['id'];}
        if($scope==='unassigned')$conditions[]='c.assigned_to IS NULL';
        $type=is_string($filters['kind']??null)&&in_array($filters['kind'],['bug','idea','support','content'],true)?$filters['kind']:'';
        if($type){$conditions[]='q.kind=?';$params[]=$type;}
        $status=is_string($filters['status']??null)&&in_array($filters['status'],array_merge(['open'],self::STATUSES),true)?$filters['status']:'open';
        if($status==='open')$conditions[]="q.status IN ('new','in_progress','waiting')";else {$conditions[]='q.status=?';$params[]=$status;}
        if(($filters['status']??null)==='all'){array_pop($conditions);if($status!=='open')array_pop($params);$status='all';}
        $priority=is_string($filters['priority']??null)&&in_array($filters['priority'],array_merge(['attention'],self::PRIORITIES),true)?$filters['priority']:'';
        if($priority==='attention')$conditions[]="IF(q.source_type='bug',q.priority,COALESCE(c.priority,'normal')) IN ('high','urgent')";
        elseif($priority){$conditions[]="IF(q.source_type='bug',q.priority,COALESCE(c.priority,'normal'))=?";$params[]=$priority;}
        $search=is_string($filters['q']??null)?mb_substr(trim($filters['q']),0,120):'';
        if($search!==''){$conditions[]='(q.title LIKE ? OR p.username LIKE ? OR CAST(q.source_id AS CHAR)=?)';$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$params[]=$params[count($params)-1];$params[]=$search; $conditions[count($conditions)-1]="(q.title LIKE ? ESCAPE '!' OR p.username LIKE ? ESCAPE '!' OR CAST(q.source_id AS CHAR)=?)";}
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $from=' FROM ('.self::unionSql().') q LEFT JOIN support_cases c ON c.source_type=q.source_type AND c.source_id=q.source_id JOIN players p ON p.id=q.reporter_id LEFT JOIN admin_users a ON a.id=c.assigned_to';
        $db=Connection::getInstance();$total=(int)$db->query('SELECT COUNT(*)'.$from.$where,$params)->fetchColumn();
        $page=min(max(1,(int)($filters['page']??1)),max(1,(int)ceil($total/40)));$offset=($page-1)*40;
        $rows=$db->query("SELECT q.*,p.username reporter_name,c.assigned_to,a.username assignee,IF(q.source_type='bug',q.priority,COALESCE(c.priority,'normal')) case_priority".$from.$where." ORDER BY FIELD(case_priority,'urgent','high','normal','low'),q.created_at DESC,q.source_id DESC LIMIT 40 OFFSET $offset",$params)->fetchAll();
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/40)),'filters'=>['case_world'=>$world,'scope'=>$scope,'kind'=>$type,'status'=>$status,'priority'=>$priority,'q'=>$search]];
    }
    public static function openCount(array $admin):int { return self::inbox($admin,['status'=>'open'])['total']; }
    public static function overviewCounts(array $admin,int $worldId=0):array
    {
        return ['open'=>self::inbox($admin,['status'=>'open','case_world'=>$worldId])['total'],
            'urgent'=>self::inbox($admin,['status'=>'open','priority'=>'attention','case_world'=>$worldId])['total']];
    }

    private static function raw(string $source,int $id,bool $lock=false):array
    {
        $db=Connection::getInstance();
        if($source==='bug')$sql='SELECT id,player_id,player_id reporter_id,world_id,report_type kind,title,description,reproduction_steps,expected_result,page_path,client_context,status,priority,admin_note,handled_by,handled_at,created_at,(screenshot IS NOT NULL) has_screenshot FROM bug_reports WHERE id=?';
        else $sql="SELECT id,player_id,reporter_id,world_id,'content' kind,reason title,details description,snapshot_json,status,admin_note,handled_by,handled_at,created_at FROM community_reports WHERE id=?";
        $row=$db->query($sql.($lock?' FOR UPDATE':''),[$id])->fetch();if(!$row)self::fail('missing');
        $meta=$db->query('SELECT assigned_to,priority,revision,updated_at FROM support_cases WHERE source_type=? AND source_id=?'.($lock?' FOR UPDATE':''),[$source,$id])->fetch()?:['assigned_to'=>null,'priority'=>'normal','revision'=>0,'updated_at'=>$row['created_at']];
        $row['source_status']=$row['status'];$row['status']=match($row['status']){'reviewing'=>'in_progress','dismissed'=>'closed',default=>$row['status']};
        $row['priority']=$source==='bug'?$row['priority']:$meta['priority'];$row['source_type']=$source;$row['source_id']=$id;
        $row['assigned_to']=$meta['assigned_to'];$row['revision']=(int)$meta['revision'];$row['updated_at']=$meta['updated_at'];
        // Includes legacy source edits so an old form cannot overwrite a concurrent old-UI save.
        $row['version']=hash('sha256',json_encode([$source,$id,$row['source_status'],$row['priority'],$row['admin_note'],$row['handled_by'],$row['handled_at'],$row['assigned_to'],$row['revision']],JSON_THROW_ON_ERROR));
        return $row;
    }
    public static function detail(array $admin,string $source,int $id):array
    {
        $source=self::source($source);$actor=self::actor((int)$admin['id']);if(!self::allowed($actor['role'],$source))self::fail('denied');
        $row=self::raw($source,$id);$db=Connection::getInstance();
        $row['reporter_name']=$db->query('SELECT username FROM players WHERE id=?',[$row['reporter_id']])->fetchColumn()?:'#'.$row['reporter_id'];
        $row['player_name']=$db->query('SELECT username FROM players WHERE id=?',[$row['player_id']])->fetchColumn()?:'#'.$row['player_id'];
        $row['world_name']=$db->query('SELECT name FROM worlds WHERE id=?',[$row['world_id']])->fetchColumn()?:'#'.$row['world_id'];
        $row['team']=$db->query("SELECT id,username,role FROM admin_users WHERE role IN ('superadmin',?) ORDER BY username",[$source==='bug'?'support':'moderator'])->fetchAll();
        $row['messages']=$db->query("SELECT m.*,CASE m.author_type WHEN 'admin' THEN a.username ELSE p.username END author_name FROM support_case_messages m LEFT JOIN admin_users a ON m.author_type='admin' AND a.id=m.author_id LEFT JOIN players p ON m.author_type='player' AND p.id=m.author_id WHERE m.source_type=? AND m.source_id=? ORDER BY m.id",[$source,$id])->fetchAll();
        $row['evidence']=self::evidence($row);
        return $row;
    }
    /** Called only after case authorization; never returns other players, full inventories or log context blobs. */
    private static function evidence(array $row):array
    {
        $db=Connection::getInstance();$params=[(int)$row['player_id'],(int)$row['world_id'],$row['created_at'],$row['created_at']];
        $events=OperationsDashboard::available('operational_events');$rewards=OperationsDashboard::available('reward_grant_ledger');
        return ['events_available'=>$events,'rewards_available'=>$rewards,
            'events'=>$events?$db->query("SELECT id,category,code,message,origin,severity,outcome,occurred_at,route,request_id,operation_id,release_id,duration_ms FROM operational_events WHERE player_id=? AND world_id=? AND occurred_at BETWEEN DATE_SUB(?,INTERVAL 10 MINUTE) AND DATE_ADD(?,INTERVAL 10 MINUTE) ORDER BY occurred_at DESC,id DESC LIMIT 20",$params)->fetchAll():[],
            'rewards'=>$rewards?$db->query('SELECT id,item_code,reward_kind,resource_code,quantity,source_type,source_key,source_reference,rule_revision,operation_id,created_at FROM reward_grant_ledger WHERE player_id=? AND world_id=? AND created_at BETWEEN DATE_SUB(?,INTERVAL 10 MINUTE) AND DATE_ADD(?,INTERVAL 10 MINUTE) ORDER BY created_at DESC,id DESC LIMIT 20',$params)->fetchAll():[]];
    }
    private static function ensureMetadata(Connection $db,array $row):void
    { $db->execute('INSERT IGNORE INTO support_cases(source_type,source_id,priority) VALUES(?,?,?)',[$row['source_type'],$row['source_id'],$row['priority']]); }
    private static function updateSource(Connection $db,array $row,string $status,string $priority,int $admin):void
    {
        if($row['source_type']==='bug')$db->execute('UPDATE bug_reports SET status=?,priority=?,handled_by=?,handled_at=? WHERE id=?',[$status,$priority,$admin,in_array($status,['resolved','closed'],true)?gmdate('Y-m-d H:i:s'):null,$row['source_id']]);
        else SocialService::moderateCase($admin,['action'=>'report.update','report_id'=>$row['source_id'],'status'=>match($status){'in_progress'=>'reviewing','closed'=>'dismissed',default=>$status},'admin_note'=>$row['admin_note']]);
    }
    /** Auth, case revision, receipt, public/private message and audit commit together. */
    public static function execute(int $adminId,array $input):array
    {
        $source=self::source($input['source']??null);$id=self::integer($input['case_id']??null);$op=self::text($input['operation_id']??null,32,32);
        if(!preg_match('/^[a-f0-9]{32}$/D',$op))self::fail('invalid');
        $action=self::choice($input['case_action']??null,['update','note','reply','chat-ban','chat-unban']);
        $reason=self::text($input['reason']??'',3,500);unset($input['csrf_token']);ksort($input);$hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
        $db=Connection::getInstance();$lock='conquer-admin-op-'.$op;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)self::fail('busy');
        try {return $db->transaction(static function(Connection $db)use($adminId,$source,$id,$op,$action,$reason,$hash,$input):array{
            $actor=self::actor($adminId,true);if(!self::allowed($actor['role'],$source))self::fail('denied');
            $receipt=$db->query('SELECT * FROM admin_operations WHERE operation_id=? FOR UPDATE',[$op])->fetch();
            if($receipt){if((int)$receipt['admin_id']!==$adminId||$receipt['action']!=='case-'.$action||!hash_equals($receipt['payload_hash'],$hash))self::fail('replay');$result=json_decode($receipt['result_json'],true,32,JSON_THROW_ON_ERROR);$result['duplicate']=true;return $result;}
            $row=self::raw($source,$id,true);
            if(!is_string($input['version']??null)||!hash_equals($row['version'],$input['version']))self::fail('stale');
            self::ensureMetadata($db,$row);$before=['status'=>$row['status'],'priority'=>$row['priority'],'assigned_to'=>$row['assigned_to']];$after=$before;$body='';$visibility='history';$event=['action'=>$action,'before'=>$before];
            if($action==='update'){
                $after['status']=self::choice($input['status']??null,self::STATUSES);$after['priority']=self::choice($input['priority']??null,self::PRIORITIES);$assigned=self::integer($input['assigned_to']??0,0);$after['assigned_to']=$assigned?:null;
                if($assigned){$assignee=self::actor($assigned,true);if(!self::allowed($assignee['role'],$source))self::fail('assignee_invalid');}
                self::updateSource($db,$row,$after['status'],$after['priority'],$adminId);
                $db->execute('UPDATE support_cases SET assigned_to=?,priority=? WHERE source_type=? AND source_id=?',[$after['assigned_to'],$after['priority'],$source,$id]);
            }elseif(in_array($action,['note','reply'],true)){$body=self::text($input['body']??null,1,4000);$visibility=$action==='note'?'internal':'public';}
            else {
                if($source!=='content')self::fail('denied');
                $moderation=SocialService::moderateCase($adminId,['action'=>$action==='chat-ban'?'chat.ban':'chat.unban','player_id'=>(int)$row['player_id'],'world_id'=>(int)$row['world_id'],'reason'=>$reason,'minutes'=>self::integer($input['minutes']??60)]);
                $event['moderation']=['before'=>$moderation['before'],'after'=>$moderation['after']];
            }
            $event['after']=$after;$event['reason']=$reason;
            $db->execute('UPDATE support_cases SET revision=revision+1 WHERE source_type=? AND source_id=?',[$source,$id]);
            $db->execute("INSERT INTO support_case_messages(source_type,source_id,visibility,author_type,author_id,body,event_json,operation_key) VALUES(?,?,?,'admin',?,?,?,?)",[$source,$id,$visibility,$adminId,$body,json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$op]);
            $db->execute('INSERT INTO admin_audit_log(admin_id,action,target_type,target_id,details,ip) VALUES(?,?,?,?,?,?)',[$adminId,'case.'.$action,$source==='bug'?'bug_report':'community_report',$id,json_encode($event,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??null]);
            $result=['source'=>$source,'case_id'=>$id,'world_id'=>(int)$row['world_id'],'duplicate'=>false,'message'=>self::t('saved')];
            $db->execute('INSERT INTO admin_operations(operation_id,admin_id,action,payload_hash,result_json) VALUES(?,?,?,?,?)',[$op,$adminId,'case-'.$action,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        });}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    /** Public DTOs are an explicit allowlist: never return assignments, notes or moderation evidence. */
    public static function playerInbox(int $player):array
    {
        return Connection::getInstance()->query('SELECT q.source_type,q.source_id,q.kind,q.title,q.status,q.created_at FROM ('.self::unionSql().') q WHERE q.reporter_id=? ORDER BY q.created_at DESC LIMIT 100',[$player])->fetchAll();
    }
    public static function playerDetail(int $player,string $source,int $id):array
    {
        $source=self::source($source);$row=self::raw($source,$id);if((int)$row['reporter_id']!==$player)self::fail('missing');
        $messages=Connection::getInstance()->query("SELECT id,author_type,body,created_at FROM support_case_messages WHERE source_type=? AND source_id=? AND visibility='public' ORDER BY id",[$source,$id])->fetchAll();
        return ['source_type'=>$source,'source_id'=>$id,'title'=>$row['title'],'kind'=>$row['kind'],'status'=>$row['status'],'description'=>$row['description'],'created_at'=>$row['created_at'],'messages'=>$messages];
    }
    public static function playerReply(int $player,string $source,int $id,array $input):array
    {
        $source=self::source($source);$body=self::text($input['body']??null,1,4000);$op=self::text($input['operation_key']??null,16,64);
        if(!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$op))self::fail('invalid');
        return Connection::getInstance()->transaction(static function(Connection $db)use($player,$source,$id,$body,$op):array{
            $row=self::raw($source,$id,true);if((int)$row['reporter_id']!==$player)self::fail('missing');
            $receipt=$db->query("SELECT source_type,source_id,body FROM support_case_messages WHERE author_type='player' AND author_id=? AND operation_key=?",[$player,$op])->fetch();
            if($receipt){if($receipt['source_type']!==$source||(int)$receipt['source_id']!==$id||$receipt['body']!==$body)self::fail('replay');return ['duplicate'=>true,'message'=>self::t('sent')];}
            $recent=(int)$db->query("SELECT COUNT(*) FROM support_case_messages WHERE author_type='player' AND author_id=? AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)",[$player])->fetchColumn();if($recent>=30)self::fail('rate_limit');
            self::ensureMetadata($db,$row);
            $db->execute("INSERT INTO support_case_messages(source_type,source_id,visibility,author_type,author_id,body,operation_key) VALUES(?,?,'public','player',?,?,?)",[$source,$id,$player,$body,$op]);
            $db->execute('UPDATE support_cases SET revision=revision+1 WHERE source_type=? AND source_id=?',[$source,$id]);
            if(in_array($row['status'],['resolved','closed','waiting'],true))$db->execute('UPDATE '.($source==='bug'?'bug_reports':'community_reports')." SET status='new',handled_at=NULL WHERE id=?",[$id]);
            return ['duplicate'=>false,'message'=>self::t('sent')];
        });
    }
}
