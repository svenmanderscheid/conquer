<?php
declare(strict_types=1);
namespace Conquer\Game\Community;

use Conquer\Db\Connection;
use Conquer\Game\Alliance\AllianceRank;
use Conquer\Game\Locale;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\World\WorldContext;

/** Recruitment, member-only planning and persistent receipts share the membership lock. */
final class AllianceCommunityService
{
    private const STYLES=['casual','pve','competitive'];
    private const TIMES=['flexible','morning','afternoon','evening','night'];

    public static function state(int $player,int $world,array $filters=[]): array
    {
        WorldContext::current($world);self::city($player,$world);$db=Connection::getInstance();
        $member=self::member($player,$world);$aid=(int)($member['alliance_id']??0);
        $alliance=$aid?$db->query('SELECT * FROM alliances WHERE id=? AND world_id=?',[$aid,$world])->fetch():null;
        $power=self::power($player);$search=self::search($player,$world,$power,$filters);
        $state=['world_id'=>$world,'player_id'=>$player,'server_time'=>time(),'power'=>$power,'alliance'=>$alliance?:null,'role'=>$member['role']??null,'role_level'=>AllianceRank::level($member['role']??''),
            'search'=>$search,'can_manage'=>AllianceRank::level($member['role']??'')>=4,'can_plan'=>AllianceRank::level($member['role']??'')>=3,
            'applications'=>$db->query("SELECT ap.*,a.name,a.tag FROM alliance_applications ap JOIN alliances a ON a.id=ap.alliance_id AND a.world_id=ap.world_id WHERE ap.player_id=? AND ap.world_id=? ORDER BY (ap.status='pending') DESC,ap.updated_at DESC LIMIT 30",[$player,$world])->fetchAll(),
            'notices'=>[],'events'=>[],'polls'=>[],'recent_members'=>[],'open_help'=>[],'research'=>[],'territory_goal'=>null,'pending_applications'=>[]];
        if(!$aid)return $state;
        $state['alliance']['member_count']=(int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE alliance_id=? AND world_id=?',[$aid,$world])->fetchColumn();
        $state['notices']=$db->query('SELECT * FROM alliance_notices WHERE alliance_id=? AND world_id=? AND archived=0 ORDER BY pinned DESC,completed,id DESC LIMIT 30',[$aid,$world])->fetchAll();
        $state['recent_members']=$db->query('SELECT m.player_id,m.role,m.joined_at,COALESCE(k.display_name,p.username) AS username FROM alliance_members m JOIN players p ON p.id=m.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE m.alliance_id=? AND m.world_id=? ORDER BY m.joined_at DESC,m.id DESC LIMIT 8',[$aid,$world])->fetchAll();
        foreach($state['recent_members'] as &$entry)$entry['role_level']=AllianceRank::level($entry['role']);unset($entry);
        $state['open_help']=$db->query("SELECT h.id,h.queue_type,h.help_count,h.max_helps,h.player_id,COALESCE(k.display_name,p.username) AS username,q.finishes_at FROM community_help_requests h JOIN players p ON p.id=h.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id JOIN alliance_members m ON m.player_id=h.player_id AND m.alliance_id=h.alliance_id AND m.world_id=h.world_id JOIN (SELECT id,'building' AS queue_type,finishes_at,is_processed FROM building_queue UNION ALL SELECT id,'research',finishes_at,is_processed FROM research_queue) q ON q.id=h.queue_id AND q.queue_type=h.queue_type WHERE h.alliance_id=? AND h.world_id=? AND q.is_processed=0 AND q.finishes_at>UTC_TIMESTAMP() AND h.help_count<h.max_helps AND h.reduced_seconds<FLOOR(h.initial_seconds*0.3) ORDER BY h.id DESC LIMIT 12",[$aid,$world])->fetchAll();
        $state['research']=$db->query('SELECT research_code,level_to,finishes_at FROM alliance_research_queue WHERE alliance_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() LIMIT 1',[$aid])->fetchAll();
        $state['territory_goal']=$db->query('SELECT t.id,t.name,t.x,t.y,t.owner_alliance_id,g.updated_at FROM territory_goals g JOIN territory_targets t ON t.id=g.target_id AND t.world_id=g.world_id WHERE g.world_id=? AND g.alliance_id=?',[$world,$aid])->fetch()?:null;
        $events=$db->query('SELECT e.*,r.response AS my_response FROM alliance_calendar_events e LEFT JOIN alliance_event_rsvps r ON r.event_id=e.id AND r.player_id=? WHERE e.alliance_id=? AND e.world_id=? AND e.cancelled_at IS NULL AND DATE_ADD(e.starts_at,INTERVAL e.duration_minutes MINUTE)>UTC_TIMESTAMP() ORDER BY e.starts_at,e.id LIMIT 50',[$player,$aid,$world])->fetchAll();
        // Keep a small cancellation notice history without hiding later active events.
        $cancelled=$db->query('SELECT e.*,r.response AS my_response FROM alliance_calendar_events e LEFT JOIN alliance_event_rsvps r ON r.event_id=e.id AND r.player_id=? WHERE e.alliance_id=? AND e.world_id=? AND e.cancelled_at IS NOT NULL AND DATE_ADD(e.starts_at,INTERVAL e.duration_minutes MINUTE)>UTC_TIMESTAMP() ORDER BY e.cancelled_at DESC,e.id DESC LIMIT 5',[$player,$aid,$world])->fetchAll();
        $events=array_merge($events,$cancelled);usort($events,static fn(array $a,array $b):int=>strcmp($a['starts_at'],$b['starts_at'])?:((int)$a['id']<=>(int)$b['id']));
        foreach($events as &$event){
            $event['rsvps']=$db->query('SELECT r.player_id,r.response,COALESCE(k.display_name,p.username) AS username FROM alliance_event_rsvps r JOIN alliance_members m ON m.player_id=r.player_id AND m.alliance_id=? AND m.world_id=? JOIN players p ON p.id=r.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE r.event_id=? ORDER BY r.response,p.username LIMIT 100',[$aid,$world,$event['id']])->fetchAll();
            $event['reminder']=!$event['cancelled_at']&&in_array($event['my_response'],['yes','maybe'],true)&&strtotime($event['starts_at'].' UTC')>=time()&&strtotime($event['starts_at'].' UTC')<=time()+86400;
        }unset($event);$state['events']=$events;
        $before=self::integer($filters,'poll_before',0,PHP_INT_MAX,0);
        $polls=$db->query('SELECT p.*,v.choice AS my_choice FROM alliance_polls p LEFT JOIN alliance_poll_votes v ON v.poll_id=p.id AND v.player_id=? WHERE p.alliance_id=? AND p.world_id=?'.($before?' AND p.id<?':'').' ORDER BY p.id DESC LIMIT 21',array_merge([$player,$aid,$world],$before?[$before]:[]))->fetchAll();
        $state['poll_next']=count($polls)>20?(int)$polls[19]['id']:null;$polls=array_slice($polls,0,20);
        foreach($polls as &$poll){
            $poll['options']=json_decode($poll['options_json'],true,8,JSON_THROW_ON_ERROR);unset($poll['options_json']);
            $poll['counts']=array_fill(0,count($poll['options']),0);
            foreach($db->query('SELECT v.choice,COUNT(*) AS votes FROM alliance_poll_votes v JOIN alliance_members m ON m.player_id=v.player_id AND m.alliance_id=? AND m.world_id=? WHERE v.poll_id=? GROUP BY v.choice',[$aid,$world,$poll['id']])->fetchAll()as$vote)$poll['counts'][(int)$vote['choice']]=(int)$vote['votes'];
            $poll['open']=!$poll['closed_at']&&strtotime($poll['closes_at'].' UTC')>time();
        }unset($poll);$state['polls']=$polls;
        if($state['can_manage'])$state['pending_applications']=$db->query("SELECT ap.*,COALESCE(k.display_name,p.username) AS username FROM alliance_applications ap JOIN players p ON p.id=ap.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE ap.alliance_id=? AND ap.world_id=? AND ap.status='pending' ORDER BY ap.id LIMIT 100",[$aid,$world])->fetchAll();
        return $state;
    }

    private static function search(int $player,int $world,int $power,array $filters): array
    {
        $where=['a.world_id=?'];$args=[$world];$needle=self::text($filters,'search',0,50,'');
        if($needle!==''){$where[]='(a.name LIKE ? OR a.tag LIKE ?)';$like='%'.addcslashes($needle,'%_\\').'%';array_push($args,$like,$like);}
        foreach(['language'=>'recruitment_language','style'=>'play_style','activity'=>'activity_time','mode'=>'recruitment_mode']as$key=>$column){
            $v=self::text($filters,$key,0,16,'');if($v!==''){$where[]='a.'.$column.'=?';$args[]=$v;}
        }
        $cursor=self::integer($filters,'cursor',0,PHP_INT_MAX,0);if($cursor){$where[]='a.id>?';$args[]=$cursor;}
        $minimum=self::integer($filters,'max_minimum_power',0,1000000000000,1000000000000);$where[]='a.minimum_power<=?';$args[]=$minimum;
        if(self::flag($filters,'eligible',false)){$where[]='a.minimum_power<=?';$args[]=$power;}
        if(self::flag($filters,'free_slots',false))$where[]='(SELECT COUNT(*) FROM alliance_members am WHERE am.alliance_id=a.id)<a.max_members';
        $rows=Connection::getInstance()->query('SELECT a.id,a.name,a.tag,a.description,a.max_members,a.minimum_power,a.recruitment_mode,a.recruitment_language,a.play_style,a.activity_time,a.activity_timezone,(SELECT COUNT(*) FROM alliance_members m WHERE m.alliance_id=a.id) AS member_count FROM alliances a WHERE '.implode(' AND ',$where).' ORDER BY a.id LIMIT 21',$args)->fetchAll();
        return ['items'=>array_slice($rows,0,20),'next_cursor'=>count($rows)>20?(int)$rows[19]['id']:null];
    }

    public static function action(int $player,array $body,?int $world=null): array
    {
        $world??=WorldContext::id();WorldContext::current($world);WorldContext::current($body['world_id']??null);WorldContext::current($body['expected_world_id']??null);WorldContext::assertActionAvailable($world);self::city($player,$world);
        $request=self::text($body,'request_id',16,80);self::require((bool)preg_match('/^[a-zA-Z0-9_-]+$/D',$request),'request');
        $hashed=$body;unset($hashed['request_id']);ksort($hashed);$hash=hash('sha256',json_encode([$world,$hashed],JSON_THROW_ON_ERROR));
        $db=Connection::getInstance();$result=$db->transaction(function()use($db,$player,$world,$body,$request,$hash):array{
            self::require((bool)$db->query('SELECT id FROM players WHERE id=? AND is_banned=0 FOR UPDATE',[$player])->fetchColumn(),'player',403);
            $old=$db->query('SELECT payload_hash,result_json FROM alliance_community_operations WHERE player_id=? AND request_id=?',[$player,$request])->fetch();
            if($old){self::require(hash_equals($old['payload_hash'],$hash),'receipt');return json_decode($old['result_json'],true,16,JSON_THROW_ON_ERROR);}
            $action=$body['action']??'';
            $result=match($action){
                'recruitment.save'=>self::recruitment($player,$world,$body),
                'alliance.join','application.submit','application.withdraw','application.accept','application.decline'=>self::application($player,$world,$body),
                'notice.create','notice.archive','notice.complete','notice.pin'=>self::notice($player,$world,$body),
                'event.create','event.cancel','event.rsvp'=>self::event($player,$world,$body),
                'poll.create','poll.vote','poll.close'=>self::poll($player,$world,$body),
                default=>throw new \DomainException(self::t('error.action')),
            };
            $result['message']=self::t('saved');$db->execute('INSERT INTO alliance_community_operations(player_id,request_id,payload_hash,result_json)VALUES(?,?,?,?)',[$player,$request,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
        });return ['message'=>$result['message'],'result'=>$result];
    }

    private static function recruitment(int $player,int $world,array $body): array
    {
        $a=self::managed($player,$world,4);$language=self::text($body,'language',2,12);self::require((bool)preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/D',$language),'language');
        $mode=self::option($body,'mode',['open','application']);$style=self::option($body,'style',self::STYLES);$activity=self::option($body,'activity',self::TIMES);$zone=self::timezone($body);
        $minimum=self::integer($body,'minimum_power',0,1000000000000);
        Connection::getInstance()->execute('UPDATE alliances SET recruitment_mode=?,recruitment_language=?,play_style=?,activity_time=?,activity_timezone=?,minimum_power=? WHERE id=?',[$mode,$language,$style,$activity,$zone,$minimum,$a['id']]);return [];
    }

    private static function application(int $player,int $world,array $body): array
    {
        $db=Connection::getInstance();$action=$body['action'];
        if(in_array($action,['application.accept','application.decline'],true)){
            $a=self::managed($player,$world,4);$id=self::integer($body,'application_id');
            $ap=$db->query("SELECT * FROM alliance_applications WHERE id=? AND alliance_id=? AND world_id=? AND status='pending' FOR UPDATE",[$id,$a['id'],$world])->fetch();self::require((bool)$ap,'application',404);
            if($action==='application.accept'){self::assertCanJoin((int)$ap['player_id'],$a,true);self::insertMember((int)$ap['player_id'],$a,$world);}
            $db->execute('UPDATE alliance_applications SET status=?,reviewed_by=? WHERE id=?',[$action==='application.accept'?'accepted':'declined',$player,$id]);return ['application_id'=>$id];
        }
        $id=self::integer($body,'alliance_id');$a=$db->query('SELECT * FROM alliances WHERE id=? AND world_id=? FOR UPDATE',[$id,$world])->fetch();self::require((bool)$a,'alliance',404);
        if($action==='application.withdraw'){$db->execute("UPDATE alliance_applications SET status='withdrawn' WHERE alliance_id=? AND player_id=? AND world_id=? AND status='pending'",[$id,$player,$world]);return [];}
        self::assertCanJoin($player,$a,$action==='application.submit');
        if($action==='alliance.join'){self::insertMember($player,$a,$world);return ['alliance_id'=>$id];}
        self::require($a['recruitment_mode']==='application','open');$message=self::text($body,'message',0,500,'');
        $old=$db->query('SELECT * FROM alliance_applications WHERE alliance_id=? AND player_id=? FOR UPDATE',[$id,$player])->fetch();
        if($old&&$old['status']==='pending')return ['application_id'=>(int)$old['id']];
        self::require(!$old||strtotime($old['updated_at'].' UTC')<=time()-60,'cooldown');
        self::require((int)$db->query("SELECT COUNT(*) FROM alliance_applications WHERE player_id=? AND world_id=? AND status='pending'",[$player,$world])->fetchColumn()<5,'applications_limit');
        if($old){$db->execute("UPDATE alliance_applications SET message=?,status='pending',reviewed_by=NULL,created_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?",[$message,$old['id']]);$newId=(int)$old['id'];}
        else{$db->execute('INSERT INTO alliance_applications(alliance_id,world_id,player_id,message)VALUES(?,?,?,?)',[$id,$world,$player,$message]);$newId=$db->lastInsertId();}
        return ['application_id'=>$newId];
    }

    /** Called by the original KingdomService join route inside its alliance lock as well. */
    public static function assertCanJoin(int $player,array $alliance,bool $approvedApplication=false): void
    {
        $db=Connection::getInstance();$world=(int)$alliance['world_id'];self::city($player,$world);
        self::require($approvedApplication||($alliance['recruitment_mode']??'open')==='open','apply_first');
        self::require(!$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=? FOR UPDATE',[$player,$world])->fetchColumn(),'already_member');
        // A receipt lookup may already have established a repeatable-read snapshot.
        // Count current locked membership after waiting for the alliance row.
        self::require((int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE alliance_id=? FOR UPDATE',[$alliance['id']])->fetchColumn()<(int)$alliance['max_members'],'full');
        self::require(self::power($player)>=(int)($alliance['minimum_power']??0),'power');
    }

    private static function insertMember(int $player,array $a,int $world): void
    {
        $db=Connection::getInstance();$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role,role_level)VALUES(?,?,?,'member',?)",[$a['id'],$player,$world,AllianceRank::level('member')]);
        $db->execute('UPDATE alliances SET member_count=(SELECT COUNT(*) FROM alliance_members WHERE alliance_id=?) WHERE id=?',[$a['id'],$a['id']]);
        self::closeApplicationsAfterJoin($player,(int)$a['id'],$world);
    }

    public static function closeApplicationsAfterJoin(int $player,int $alliance,int $world): void
    {
        Connection::getInstance()->execute("UPDATE alliance_applications SET status=IF(alliance_id=?,'accepted','withdrawn') WHERE player_id=? AND world_id=? AND status='pending'",[$alliance,$player,$world]);
    }

    private static function notice(int $player,int $world,array $body): array
    {
        $a=self::managed($player,$world,3);$db=Connection::getInstance();$action=$body['action'];
        if($action==='notice.create'){
            self::require((int)$db->query('SELECT COUNT(*) FROM alliance_notices WHERE alliance_id=? AND archived=0 FOR UPDATE',[$a['id']])->fetchColumn()<30,'notices_limit');
            $title=self::text($body,'title',1,100);$text=self::text($body,'body',0,2000,'');$kind=self::option($body,'kind',['notice','goal']);
            $db->execute('INSERT INTO alliance_notices(alliance_id,world_id,creator_id,kind,title,body,pinned)VALUES(?,?,?,?,?,?,?)',[$a['id'],$world,$player,$kind,$title,$text,(int)self::flag($body,'pinned',true)]);return ['notice_id'=>$db->lastInsertId()];
        }
        $id=self::integer($body,'notice_id');$row=$db->query('SELECT * FROM alliance_notices WHERE id=? AND alliance_id=? AND world_id=? AND archived=0 FOR UPDATE',[$id,$a['id'],$world])->fetch();self::require((bool)$row,'notice',404);
        if($action==='notice.complete'){self::require($row['kind']==='goal','input');$db->execute('UPDATE alliance_notices SET completed=? WHERE id=?',[(int)self::flag($body,'completed',true),$id]);}
        elseif($action==='notice.pin')$db->execute('UPDATE alliance_notices SET pinned=? WHERE id=?',[(int)self::flag($body,'pinned',true),$id]);
        else $db->execute('UPDATE alliance_notices SET archived=1 WHERE id=?',[$id]);return [];
    }

    private static function event(int $player,int $world,array $body): array
    {
        $action=$body['action'];$a=self::managed($player,$world,$action==='event.rsvp'?1:3);$db=Connection::getInstance();
        if($action==='event.create'){
            self::require((int)$db->query('SELECT COUNT(*) FROM alliance_calendar_events WHERE alliance_id=? AND cancelled_at IS NULL AND DATE_ADD(starts_at,INTERVAL duration_minutes MINUTE)>UTC_TIMESTAMP() FOR UPDATE',[$a['id']])->fetchColumn()<50,'events_limit');
            $start=self::future($body,'starts_at',366*86400);$duration=self::integer($body,'duration_minutes',15,1440);$zone=self::timezone($body);
            $db->execute('INSERT INTO alliance_calendar_events(alliance_id,world_id,creator_id,title,description,starts_at,duration_minutes,timezone)VALUES(?,?,?,?,?,?,?,?)',[$a['id'],$world,$player,self::text($body,'title',1,100),self::text($body,'description',0,2000,''),gmdate('Y-m-d H:i:s',$start),$duration,$zone]);return ['event_id'=>$db->lastInsertId()];
        }
        $id=self::integer($body,'event_id');$event=$db->query('SELECT * FROM alliance_calendar_events WHERE id=? AND alliance_id=? AND world_id=? FOR UPDATE',[$id,$a['id'],$world])->fetch();self::require((bool)$event,'event',404);
        if($action==='event.cancel'){$db->execute('UPDATE alliance_calendar_events SET cancelled_at=COALESCE(cancelled_at,UTC_TIMESTAMP()) WHERE id=?',[$id]);return [];}
        self::require(!$event['cancelled_at']&&strtotime($event['starts_at'].' UTC')+(int)$event['duration_minutes']*60>time(),'event_ended');
        $response=self::option($body,'response',['yes','maybe','no']);$db->execute('INSERT INTO alliance_event_rsvps(event_id,player_id,response)VALUES(?,?,?) ON DUPLICATE KEY UPDATE response=VALUES(response)',[$id,$player,$response]);return [];
    }

    private static function poll(int $player,int $world,array $body): array
    {
        $action=$body['action'];$a=self::managed($player,$world,$action==='poll.vote'?1:3);$db=Connection::getInstance();
        if($action==='poll.create'){
            self::require((int)$db->query('SELECT COUNT(*) FROM alliance_polls WHERE alliance_id=? AND closed_at IS NULL AND closes_at>UTC_TIMESTAMP() FOR UPDATE',[$a['id']])->fetchColumn()<20,'polls_limit');
            $options=$body['options']??null;self::require(is_array($options)&&array_is_list($options)&&count($options)>=2&&count($options)<=8,'options');
            $kind=self::option($body+['kind'=>'choice'],'kind',['choice','time']);$clean=[];
            foreach($options as$option)$clean[]=$kind==='time'?self::future(['option'=>$option],'option',366*86400):self::text(['option'=>$option],'option',1,100);
            self::require(count(array_unique(array_map(static fn($v)=>mb_strtolower((string)$v),$clean)))===count($clean),'options');
            $close=self::future($body,'closes_at',30*86400);
            if($kind==='time')self::require($close<min($clean),'poll_deadline');
            $db->execute('INSERT INTO alliance_polls(alliance_id,world_id,creator_id,question,kind,options_json,closes_at)VALUES(?,?,?,?,?,?,?)',[$a['id'],$world,$player,self::text($body,'question',1,200),$kind,json_encode($clean,JSON_THROW_ON_ERROR),gmdate('Y-m-d H:i:s',$close)]);return ['poll_id'=>$db->lastInsertId()];
        }
        $id=self::integer($body,'poll_id');$poll=$db->query('SELECT * FROM alliance_polls WHERE id=? AND alliance_id=? AND world_id=? FOR UPDATE',[$id,$a['id'],$world])->fetch();self::require((bool)$poll,'poll',404);
        if($action==='poll.close'){$db->execute('UPDATE alliance_polls SET closed_at=COALESCE(closed_at,UTC_TIMESTAMP()) WHERE id=?',[$id]);return [];}
        self::require(!$poll['closed_at']&&strtotime($poll['closes_at'].' UTC')>time(),'poll_closed');
        $choice=self::integer($body,'choice',0,count(json_decode($poll['options_json'],true,8,JSON_THROW_ON_ERROR))-1);
        $db->execute('INSERT INTO alliance_poll_votes(poll_id,player_id,choice)VALUES(?,?,?) ON DUPLICATE KEY UPDATE choice=VALUES(choice)',[$id,$player,$choice]);return [];
    }

    private static function managed(int $player,int $world,int $rank): array
    {
        $db=Connection::getInstance();$member=self::member($player,$world);self::require((bool)$member,'member',403);
        $a=$db->query('SELECT * FROM alliances WHERE id=? AND world_id=? FOR UPDATE',[$member['alliance_id'],$world])->fetch();
        $member=$db->query('SELECT role FROM alliance_members WHERE player_id=? AND alliance_id=? AND world_id=? FOR UPDATE',[$player,$member['alliance_id'],$world])->fetch();
        self::require((bool)$a&&(bool)$member&&AllianceRank::level($member['role'])>=$rank,'role',403);return $a;
    }

    private static function city(int $player,int $world): void
    {
        self::require((bool)Connection::getInstance()->query('SELECT c.id FROM cities c JOIN players p ON p.id=c.player_id WHERE c.player_id=? AND c.world_id=? AND p.is_banned=0',[$player,$world])->fetchColumn(),'player',403);
    }
    private static function member(int $player,int $world): array|false
    {
        $member=Connection::getInstance()->query('SELECT m.alliance_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=m.world_id WHERE m.player_id=? AND m.world_id=?',[$player,$world])->fetch();
        if($member)$member['role_level']=AllianceRank::level($member['role']);
        return $member;
    }
    private static function power(int $player): int {return (int)(KingdomService::publicSummaries([$player])[$player]['power']??0);}
    private static function text(array $body,string $key,int $min,int $max,?string $default=null): string
    {
        $v=$body[$key]??$default;self::require(is_string($v)&&mb_check_encoding($v,'UTF-8'),'input');$v=trim($v);self::require(mb_strlen($v)>=$min&&mb_strlen($v)<=$max&&!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$v),'input');return $v;
    }
    private static function integer(array $body,string $key,int $min=1,int $max=2147483647,?int $default=null): int
    {
        $v=$body[$key]??$default;self::require((is_int($v)||is_string($v))&&filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]])!==false,'input');return (int)$v;
    }
    private static function flag(array $body,string $key,bool $default): bool {$v=$body[$key]??$default;self::require(in_array($v,[true,false,0,1,'0','1'],true),'input');return (bool)$v;}
    private static function option(array $body,string $key,array $values): string {$v=self::text($body,$key,1,30);self::require(in_array($v,$values,true),'input');return $v;}
    private static function timezone(array $body): string {$v=self::text($body,'timezone',1,64);self::require(in_array($v,\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),true),'timezone');return $v;}
    private static function future(array $body,string $key,int $max): int {$v=self::integer($body,$key,1,PHP_INT_MAX);self::require($v>time()&&$v<=time()+$max,'date');return $v;}
    private static function require(bool $ok,string $key,int $status=422): void {if(!$ok)throw new \DomainException(self::t('error.'.$key),$status);}
    private static function t(string $key): string {return Locale::t('alliance_community.'.$key);}
}
