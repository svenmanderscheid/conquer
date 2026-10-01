<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;

use Conquer\Db\Connection;
use Conquer\Game\World\{WorldContext,WorldMapProfile,LuxembourgGeography};
use Conquer\Game\WorldRules;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSpeed,MarchSkinService};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Rally\{RallyService,MonsterRally};

/** Authenticated territory commands and replay receipts share the army transaction. */
final class TerritoryService
{
    /** Translate former generated titles without changing stable target IDs or ownership. */
    public static function displayName(string $name): string
    {
        if($name==='Krounbuerg')return 'Royal Castle';
        if(str_starts_with($name,'Vogtei '))return 'Commune '.substr($name,7);
        if(str_starts_with($name,'Markfeste '))return 'Shrine of '.substr($name,10);
        return $name;
    }
    private static function displayNames(array $rows,string $field='name'): array
    {
        foreach($rows as &$row)$row[$field]=self::displayName($row[$field]);unset($row);
        return $rows;
    }
    public static function available(): bool
    {
        try{return (bool)Connection::getInstance()->query("SHOW TABLES LIKE 'territory_profiles'")->fetchColumn();}
        catch(\PDOException){return false;}
    }
    public static function enabled(int $world): bool
    {
        return self::available()&&WorldMapProfile::isLuxembourg($world);
    }
    public static function ensureWorld(int $world): void
    {
        if(!self::enabled($world))return;
        $db=Connection::getInstance();
        if($db->query('SELECT world_id FROM territory_profiles WHERE world_id=?',[$world])->fetchColumn())return;
        $db->transaction(function(Connection $db)use($world):void{
            $map=WorldMapProfile::forWorld($world);$continent=(string)($map['continent_id']??'luxembourg');
            $db->execute('INSERT IGNORE INTO territory_profiles(world_id,continent_id,version,rules_json)VALUES(?,?,1,?)',[$world,$continent,json_encode(TerritoryRules::defaults(),JSON_THROW_ON_ERROR)]);
            foreach(LuxembourgGeography::landmarks() as $t){
                $db->execute('INSERT IGNORE INTO territory_targets(world_id,id,continent_id,geometry_version,kind,commune_id,canton_id,name,x,y,footprint,benefit_type)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$world,$t['id'],$continent,(string)$map['version'],$t['type'],$t['commune_id']??null,$t['canton_id']??null,$t['name'],$t['x'],$t['y'],$t['footprint'],TerritoryRules::benefit($t['id'],$t['type'])]);
            }
        });
    }
    public static function profile(int $world): array
    {
        self::ensureWorld($world);
        $json=Connection::getInstance()->query('SELECT rules_json FROM territory_profiles WHERE world_id=?',[$world])->fetchColumn();
        self::require((bool)$json,'Gebietseroberungen sind in dieser Welt nicht aktiv.');
        return json_decode((string)$json,true,32,JSON_THROW_ON_ERROR);
    }
    public static function compactState(int $player,int $world,?array $bounds=null): array
    {
        if(!self::enabled($world))return ['available'=>false,'map_targets'=>[]];
        return ['available'=>true,'alliance_id'=>self::member($player,$world)['alliance_id']??null,'map_targets'=>self::mapTargets($world,$bounds),
            'ownership'=>Connection::getInstance()->query('SELECT t.id,t.owner_alliance_id,a.name AS owner_name FROM territory_targets t LEFT JOIN alliances a ON a.id=t.owner_alliance_id WHERE t.world_id=? ORDER BY t.id',[$world])->fetchAll()];
    }
    public static function mapTargets(int $world,?array $bounds=null): array
    {
        if(!self::enabled($world))return [];
        self::ensureWorld($world);$args=[$world];$where='';
        if($bounds){$where=' AND t.x BETWEEN ? AND ? AND t.y BETWEEN ? AND ?';$args=array_merge($args,[(int)($bounds['x_min']??$bounds['min_x']??$bounds[0]??0),(int)($bounds['x_max']??$bounds['max_x']??$bounds[2]??767),(int)($bounds['y_min']??$bounds['min_y']??$bounds[1]??0),(int)($bounds['y_max']??$bounds['max_y']??$bounds[3]??1099)]);}
        return self::displayNames(Connection::getInstance()->query('SELECT t.id,t.kind,t.name,t.x,t.y,t.footprint,t.commune_id,t.canton_id,t.benefit_type,t.owner_alliance_id,a.name AS owner_name FROM territory_targets t LEFT JOIN alliances a ON a.id=t.owner_alliance_id WHERE t.world_id=?'.$where.' ORDER BY t.id',$args)->fetchAll());
    }
    public static function state(int $player,int $world): array
    {
        self::assertPlayer($player,$world);
        if(!self::enabled($world))return ['enabled'=>false,'available'=>false,'world_id'=>$world,'server_time'=>time(),'targets'=>[]];
        $rules=self::profile($world);$db=Connection::getInstance();$member=self::member($player,$world);$aid=(int)($member['alliance_id']??0);
        $targets=$db->query("SELECT t.*,a.name AS owner_name,COALESCE(g.garrison_count,0) AS garrison_count FROM territory_targets t LEFT JOIN alliances a ON a.id=t.owner_alliance_id LEFT JOIN (SELECT target_id,COUNT(*) AS garrison_count FROM territory_garrisons WHERE world_id=? AND status='active' GROUP BY target_id) g ON g.target_id=t.id WHERE t.world_id=? ORDER BY t.kind,t.name",[$world,$world])->fetchAll();
        $progress=self::progress($world,$aid);foreach($targets as &$t)$t=self::describe($t,$rules,$aid,$progress);unset($t);
        return ['enabled'=>true,'available'=>true,'world_id'=>$world,'server_time'=>time(),'profile'=>$rules,'alliance_id'=>$aid?:null,'role'=>$member['role']??null,
            'targets'=>$targets,'progress'=>$progress,'goal'=>$aid?($db->query('SELECT target_id,updated_at FROM territory_goals WHERE world_id=? AND alliance_id=?',[$world,$aid])->fetch()?:null):null,
            'history'=>self::displayNames($db->query('SELECT h.*,t.name AS target_name,a.name AS owner_name,previous.name AS previous_owner_name FROM territory_history h JOIN territory_targets t ON t.world_id=h.world_id AND t.id=h.target_id LEFT JOIN alliances a ON a.id=h.new_alliance_id LEFT JOIN alliances previous ON previous.id=h.old_alliance_id WHERE h.world_id=? ORDER BY h.occurred_at DESC,h.id DESC LIMIT 50',[$world])->fetchAll(),'target_name'),
            'rewards'=>self::rewards($player,$world),'crown'=>TerritoryCrown::state($world),
            'contributions'=>$aid?TerritoryContribution::summary($player,$world,$aid):null,
            'benefit_usage'=>$aid?$db->query('SELECT benefit,used,day_key FROM territory_benefit_usage WHERE world_id=? AND alliance_id=? AND day_key=UTC_DATE()',[$world,$aid])->fetchAll():[],
            'rallies'=>$aid?array_values(array_filter(RallyService::listForAlliance($aid),fn($r)=>($r['target_kind']??'')==='territory')):[]];
    }
    public static function detail(int $player,int $world,string $id): array
    {
        self::assertPlayer($player,$world);$rules=self::profile($world);$target=self::target($world,$id);$member=self::member($player,$world);$aid=(int)($member['alliance_id']??0);$db=Connection::getInstance();
        $target['owner_name']=$target['owner_alliance_id']?$db->query('SELECT name FROM alliances WHERE id=?',[$target['owner_alliance_id']])->fetchColumn():null;
        $t=self::describe($target,$rules,$aid,self::progress($world,$aid));
        $t['profile']=$rules;$t['npc_troops']=$t['owner_alliance_id']===null?(int)$rules['npc_troops'][$t['kind']]:0;$t['garrisons']=$db->query("SELECT g.id,g.player_id,p.username AS player_name,g.troops_json,g.status,g.arrival_at,g.return_at FROM territory_garrisons g JOIN players p ON p.id=g.player_id WHERE g.world_id=? AND g.target_id=? AND g.status<>'returned' AND (g.alliance_id=? OR g.player_id=?) ORDER BY g.id",[$world,$id,$aid,$player])->fetchAll();
        $t['canton_mission']=$t['kind']==='canton'?['required'=>(int)$rules['canton_mission_contributors'],'contributions'=>(int)$db->query("SELECT COUNT(*) FROM territory_support WHERE world_id=? AND target_id=? AND alliance_id=? AND kind='supply' AND day_key=UTC_DATE()",[$world,$id,$aid])->fetchColumn(),'reward_per_resource'=>(int)$rules['canton_mission_reward']]:null;
        foreach($t['garrisons'] as &$g){$g['troops']=json_decode($g['troops_json'],true);unset($g['troops_json']);}unset($g);
        $t['campaigns']=$db->query("SELECT c.id,c.rally_id,c.alliance_id,a.name AS alliance_name,c.status,c.objective,c.created_at,c.resolved_at,r.launch_at,r.arrival_time AS arrival_at,r.return_time AS return_at,r.leader_player_id FROM territory_campaigns c JOIN alliances a ON a.id=c.alliance_id LEFT JOIN rallies r ON r.id=c.rally_id WHERE c.world_id=? AND c.target_id=? ORDER BY c.id DESC LIMIT 20",[$world,$id])->fetchAll();
        $t['rewards']=array_values(array_filter(self::rewards($player,$world),fn($r)=>$r['target_id']===$id));
        $t['own_participation']=$db->query('SELECT kind,day_key,created_at FROM territory_support WHERE world_id=? AND target_id=? AND player_id=? ORDER BY id DESC LIMIT 10',[$world,$id,$player])->fetchAll();
        $t['crown']=$t['kind']==='crown'?TerritoryCrown::state($world):null;
        return $t;
    }
    public static function progress(int $world,int $aid): array
    {
        return self::displayNames(Connection::getInstance()->query("SELECT f.id AS target_id,f.canton_id,f.name,f.owner_alliance_id,COUNT(c.id) AS total,SUM(c.owner_alliance_id=?) AS held,FLOOR(COUNT(c.id)/2)+1 AS required FROM territory_targets f JOIN territory_targets c ON c.world_id=f.world_id AND c.canton_id=f.canton_id AND c.kind='commune' WHERE f.world_id=? AND f.kind='canton' GROUP BY f.id,f.canton_id,f.name,f.owner_alliance_id ORDER BY f.name",[$aid,$world])->fetchAll());
    }
    private static function describe(array $t,array $rules,int $aid,array $progress): array
    {
        $t['name']=self::displayName($t['name']);
        $t['x']=(int)$t['x'];$t['y']=(int)$t['y'];$t['owner_alliance_id']=$t['owner_alliance_id']===null?null:(int)$t['owner_alliance_id'];
        $t['active']=!$rules['active_cantons']||$t['kind']==='crown'||in_array($t['canton_id'],$rules['active_cantons'],true);
        $t['next_window']=TerritoryRules::window($rules,time(),$t['kind']==='crown');
        $reason=$aid===0?'Tritt zuerst einer Allianz bei.':(!$t['active']?'Dieser Kanton ist noch nicht für Eroberungen geöffnet.':($t['owner_alliance_id']===$aid&&$t['kind']!=='crown'?'Eure Allianz hält dieses Gebiet.':null));
        if(!$reason&&$t['kind']==='canton')foreach($progress as $p)if($p['canton_id']===$t['canton_id']&&(int)$p['held']<(int)$p['required'])$reason='Zuerst mehr als die Hälfte der Communes im Kanton kontrollieren.';
        if(!$reason&&$t['kind']==='crown'&&!array_filter($progress,fn($p)=>(int)$p['owner_alliance_id']===$aid))$reason='Zuerst einen Shrine erobern.';
        if(!$reason&&($t['owner_alliance_id']!==null||$t['kind']==='crown')&&!$t['next_window']['open'])$reason='Das nächste angekündigte Kampffenster abwarten.';
        $t['blocked_reason']=$reason;$t['can_attack']=$reason===null;
        $t['garrison_count']=isset($t['garrison_count'])?(int)$t['garrison_count']:(int)Connection::getInstance()->query("SELECT COUNT(*) FROM territory_garrisons WHERE world_id=? AND target_id=? AND status='active'",[$t['world_id'],$t['id']])->fetchColumn();
        return $t;
    }
    public static function action(int $player,array $body,int $world): array
    {
        self::assertPlayer($player,$world);
        self::require((int)($body['world_id']??0)===$world&&(int)($body['expected_world_id']??0)===$world,'Die Welt hat sich geändert. Lade die Ansicht neu.',409);
        $request=$body['request_id']??'';self::require(is_string($request)&&preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$request)===1,'Eine gültige Vorgangskennung ist erforderlich.');
        self::profile($world);self::tick($world);$payload=$body;unset($payload['request_id']);self::canonical($payload);$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        $db=Connection::getInstance();$playerLock='conquer-player-'.$player;
        self::require((int)$db->query('SELECT GET_LOCK(?,5)',[$playerLock])->fetchColumn()===1,'Deine Armee wird gerade aktualisiert. Wiederhole den Vorgang.',409);
        try{return WorldRules::combatLock(fn()=>$db->transaction(function(Connection $db)use($player,$body,$world,$request,$hash):array{
            $old=$db->query('SELECT * FROM territory_operations WHERE world_id=? AND player_id=? AND request_id=? FOR UPDATE',[$world,$player,$request])->fetch();
            if($old){self::require(hash_equals($old['payload_hash'],$hash),'Diese Vorgangskennung wurde bereits anders verwendet.',409);return json_decode($old['result_json'],true,32,JSON_THROW_ON_ERROR);}
            if(!in_array($body['action']??'',['recall','cancel','claim'],true))WorldContext::assertActionAvailable($world);
            self::assertCaughtUp($world);
            $result=match($body['action']??''){
                'start'=>self::start($player,$world,$body),
                'join'=>self::join($player,$world,$body),
                'cancel'=>self::cancel($player,$world,$body),
                'reinforce'=>TerritoryGarrison::reinforce($player,$world,$body),
                'recall'=>TerritoryGarrison::recall($player,$world,self::integer($body,'garrison_id')),
                'set_goal'=>self::goal($player,$world,(string)($body['target_id']??'')),
                'support'=>TerritoryEconomy::support($player,$world,$body),
                'claim'=>TerritoryEconomy::claim($player,$world,self::integer($body,'reward_id')),
                'rune_teleport'=>TerritoryEconomy::teleport($player,$world,$body),
                'appoint'=>TerritoryCrown::appoint($player,$world,$body),
                'office_use'=>TerritoryCrown::ability($player,$world,$body),
                default=>throw new \DomainException('Unbekannte Gebietsaktion.')};
            $result['request_id']=$request;
            $db->execute('INSERT INTO territory_operations(world_id,player_id,request_id,payload_hash,result_json)VALUES(?,?,?,?,?)',[$world,$player,$request,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        }));}finally{$db->query('SELECT RELEASE_LOCK(?)',[$playerLock]);}
    }
    private static function start(int $player,int $world,array $body): array
    {
        $member=self::mustMember($player,$world,true);$aid=(int)$member['alliance_id'];$target=self::target($world,(string)($body['target_id']??''),true);$rules=self::profile($world);$db=Connection::getInstance();
        $cityId=self::integer($body,'city_id');WorldRules::origin($player,$cityId,$world);$minutes=self::integer($body,'rally_minutes');self::require(in_array($minutes,[1,5,15,30],true),'Wähle 1, 5, 15 oder 30 Minuten Sammelzeit.');
        self::require(!$rules['active_cantons']||$target['kind']==='crown'||in_array($target['canton_id'],$rules['active_cantons'],true),'Dieser Kanton ist noch nicht geöffnet.');
        self::require((int)$target['owner_alliance_id']!==$aid||$target['kind']==='crown','Eure Allianz hält dieses Gebiet bereits.');
        $window=TerritoryRules::window($rules,time(),$target['kind']==='crown');
        self::require(($target['owner_alliance_id']===null&&$target['kind']!=='crown')||$window['open'],'Ein Angriff ist nur im angekündigten Kampffenster möglich.');
        $eligibility=['alliance_id'=>$aid,'at'=>gmdate('Y-m-d H:i:s'),'window'=>$window,'owner_at_start'=>$target['owner_alliance_id']];$reserved=0;$cycle=null;$objective=null;
        // Serializing the alliance row includes both owned cantons and in-flight reservations.
        $db->query('SELECT id FROM alliances WHERE id=? AND world_id=? FOR UPDATE',[$aid,$world])->fetch();
        if($target['kind']==='canton'){
            $p=array_values(array_filter(self::progress($world,$aid),fn($p)=>$p['canton_id']===$target['canton_id']))[0];
            self::require((int)$p['held']>=(int)$p['required'],'Die Mehrheit der Communes dieses Kantons fehlt.');
            $used=(int)$db->query("SELECT COUNT(*) FROM territory_targets WHERE world_id=? AND owner_alliance_id=? AND kind='canton'",[$world,$aid])->fetchColumn()+(int)$db->query('SELECT COUNT(*) FROM territory_campaigns WHERE world_id=? AND alliance_id=? AND slot_reserved=1',[$world,$aid])->fetchColumn();
            self::require($used<(int)$rules['canton_limit'],'Das Shrine-Limit einschließlich laufender Angriffe ist erreicht.');$reserved=1;$eligibility['majority']=$p;
        }
        if($target['kind']==='crown'){
            $cantons=(int)$db->query("SELECT COUNT(*) FROM territory_targets WHERE world_id=? AND kind='canton' AND owner_alliance_id=?",[$world,$aid])->fetchColumn();
            self::require($cantons>=1,'Mindestens ein Shrine ist für den Kampf um das Royal Castle erforderlich.');$eligibility['cantons']=$cantons;
            $objective=(string)($body['objective']??'gate');self::require(in_array($objective,$rules['crown_objectives'],true),'Ungültiges Belagerungsziel.');
            $cycle=TerritoryCrown::ensureCycle($world,$rules,$window);$rules=json_decode($cycle['rules_json'],true);$cycle=(int)$cycle['id'];
        }
        MarchDispatcher::assertSlotAvailable($player,$world);$troops=MarchArmy::clean($body['troops']??[],ResearchEffects::limits(BuffEngine::getBuffs($player,$world))['march_capacity']);$capacity=MonsterRally::capacity($player,$cityId);
        self::require(array_sum($troops)<=$capacity,'Die Rally-Kapazität ist erschöpft.');MarchArmy::reserve($db,$cityId,$troops);WorldRules::relinquishShield($player,$cityId);$skin=MarchSkinService::dispatchSnapshot($player);
        $db->execute('INSERT INTO territory_campaigns(world_id,continent_id,target_id,alliance_id,rules_json,eligibility_json,slot_reserved,objective,crown_cycle_id,created_at)VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$world,$target['continent_id'],$target['id'],$aid,json_encode($rules),json_encode($eligibility),$reserved,$objective,$cycle]);$campaign=$db->lastInsertId();
        $meta=['alliance_id'=>$aid,'capacity'=>$capacity,'target_name'=>$target['name'],'territory_campaign_id'=>$campaign,'territory_id'=>$target['id'],'objective'=>$objective];
        $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,march_skin,march_speed_bonus_pct,target_kind,target_territory_id,territory_campaign_id,target_x,target_y,rally_minutes,troops_json,message,result_json,status,launch_at)VALUES(?,?,?,?,?,'territory',?,?,?,?,?,?,?,?,'gathering',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[$world,$player,$cityId,$skin['march_skin'],$skin['bonus_pct'],$target['id'],$campaign,$target['x'],$target['y'],$minutes,json_encode($troops),mb_substr((string)($body['message']??''),0,512),json_encode($meta),$minutes]);$rally=$db->lastInsertId();
        $db->execute('UPDATE territory_campaigns SET rally_id=? WHERE id=?',[$rally,$campaign]);
        $db->execute('UPDATE rallies SET territory_army_snapshot=? WHERE id=?',[json_encode(TerritoryRally::snapshot($player,$cityId,$world,$troops,$skin),JSON_THROW_ON_ERROR),$rally]);
        return ['message'=>'Die Allianz sammelt Truppen für die Eroberung.','rally_id'=>$rally,'campaign_id'=>$campaign];
    }
    private static function join(int $player,int $world,array $body): array
    {
        $rally=self::rally($world,self::integer($body,'rally_id'));RallyService::join($player,self::integer($body,'city_id'),(int)$rally['id'],$body['troops']??[]);return ['message'=>'Deine Truppen reisen zur Rally.','rally_id'=>(int)$rally['id']];
    }
    private static function cancel(int $player,int $world,array $body): array
    {
        $rally=self::rally($world,self::integer($body,'rally_id'));RallyService::cancel((int)$rally['id'],$player);return ['message'=>'Die Rally wurde abgebrochen.'];
    }
    private static function goal(int $player,int $world,string $id): array
    {
        $m=self::mustMember($player,$world,true);self::target($world,$id);Connection::getInstance()->execute('INSERT INTO territory_goals(world_id,alliance_id,target_id,set_by,updated_at)VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),set_by=VALUES(set_by),updated_at=VALUES(updated_at)',[$world,$m['alliance_id'],$id,$player]);return ['message'=>'Das gemeinsame Allianz-Ziel wurde gesetzt.','target_id'=>$id];
    }
    public static function tick(?int $world=null,?int $now=null,int $limit=100): void
    {
        if(!self::available())return;$db=Connection::getInstance();$now??=time();
        $worlds=$world===null?$db->query('SELECT world_id FROM territory_profiles ORDER BY world_id')->fetchAll(\PDO::FETCH_COLUMN):[$world];
        foreach($worlds as $wid){$wid=(int)$wid;if(!self::enabled($wid))continue;self::ensureWorld($wid);
            TerritoryRally::tick($wid,$now,$limit);
            // Never accrue beyond an unprocessed scheduled battle in a bounded catch-up batch.
            $pending=$db->query("SELECT MIN(CASE WHEN status='gathering' THEN launch_at WHEN status='marching' THEN arrival_time ELSE return_time END) FROM rallies WHERE world_id=? AND target_kind='territory' AND status IN ('gathering','marching','returning')",[$wid])->fetchColumn();
            $frontier=$pending?min($now,TerritoryService::timestamp($pending)-1):$now;
            WorldContext::run($wid,fn()=>WorldRules::combatLock(fn()=>$db->transaction(function()use($wid,$frontier,$limit):void{
                TerritoryArmyReturn::tick($wid,$frontier,$limit);TerritoryGarrison::tick($wid,$frontier,$limit);TerritoryCrown::tick($wid,$frontier,$limit);TerritoryEconomy::tick($wid,$frontier,$limit);
            })));
        }
    }
    public static function target(int $world,string $id,bool $lock=false): array
    {
        $r=Connection::getInstance()->query('SELECT * FROM territory_targets WHERE world_id=? AND id=?'.($lock?' FOR UPDATE':''),[$world,$id])->fetch();self::require((bool)$r,'Gebiet nicht gefunden.',404);$r['name']=self::displayName($r['name']);return $r;
    }
    public static function member(int $player,int $world): ?array
    {
        $r=Connection::getInstance()->query('SELECT m.alliance_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=m.world_id WHERE m.world_id=? AND m.player_id=?',[$world,$player])->fetch();return $r?:null;
    }
    public static function mustMember(int $player,int $world,bool $officer=false): array
    {
        $r=self::member($player,$world);self::require($r!==null,'Tritt zuerst einer Allianz bei.',403);if($officer)self::require(in_array($r['role'],['officer','vice_leader','leader'],true),'Nur Führung und Offiziere können diese Aktion ausführen.',403);return $r;
    }
    public static function integer(array $body,string $key,int $min=1): int
    {
        $v=$body[$key]??null;self::require(is_int($v)&&$v>=$min&&$v<=2147483647,'Ungültiger Wert: '.$key);return $v;
    }
    public static function require(bool $condition,string $message,int $code=422): void { if(!$condition)throw new \DomainException($message,$code); }
    public static function hasDetachedArmy(int $player,int $world): bool
    {
        if(!self::available())return false;$db=Connection::getInstance();
        return (bool)$db->query("SELECT id FROM territory_garrisons WHERE world_id=? AND player_id=? AND status<>'returned' LIMIT 1",[$world,$player])->fetchColumn()
            ||(bool)$db->query("SELECT id FROM territory_army_returns WHERE world_id=? AND player_id=? AND status='returning' LIMIT 1",[$world,$player])->fetchColumn();
    }
    /** Invoke inside the membership transaction, with the combat lock acquired before its row locks. */
    public static function memberDeparting(int $player,int $world): void
    {
        if(!self::enabled($world))return;self::assertCaughtUp($world);$db=Connection::getInstance();
        foreach($db->query("SELECT * FROM territory_garrisons WHERE world_id=? AND player_id=? AND status IN ('active','inbound') FOR UPDATE",[$world,$player])->fetchAll() as $g)TerritoryGarrison::returnArmy($g,time());
        foreach($db->query("SELECT * FROM rallies WHERE world_id=? AND leader_player_id=? AND target_kind='territory' AND status='gathering' FOR UPDATE",[$world,$player])->fetchAll() as $r)RallyService::cancelGathering($r,'Der Anführer hat die Allianz verlassen.');
        $db->execute('DELETE FROM territory_crown_offices WHERE world_id=? AND player_id=?',[$world,$player]);
    }
    /** Admin handler authenticates the operator; revision validation prevents lost profile updates. */
    public static function saveProfile(int $world,array $changes,int $expectedVersion): array
    {
        self::profile($world);self::tick($world,time(),1000);$defaults=TerritoryRules::defaults();
        foreach($changes as $key=>$value)self::require(array_key_exists($key,$defaults)&&$key!=='version','Unbekannter oder unveränderlicher Regelwert: '.$key);
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($world,$changes,$expectedVersion):array{
            self::assertCaughtUp($world);$row=$db->query('SELECT * FROM territory_profiles WHERE world_id=? FOR UPDATE',[$world])->fetch();self::require((int)$row['version']===$expectedVersion,'Das Regelprofil wurde inzwischen geändert.',409);
            $rules=array_replace(json_decode($row['rules_json'],true),$changes);self::require(in_array($rules['canton_limit'],[1,2],true),'Das Shrine-Limit muss eins oder zwei sein.');
            foreach(['income_per_hour','conquest_reward_gold','support_cost','special_daily_limit','rune_daily_charges','rune_radius','office_daily_uses','office_resource_grant','office_acceleration_seconds','regional_supply_percent','regional_daily_cap','canton_mission_contributors','canton_mission_reward'] as $k)self::require(is_int($rules[$k])&&$rules[$k]>=0&&$rules[$k]<=1000000,'Ungültiger Regelwert: '.$k);
            self::require($rules['regional_supply_percent']<=10&&$rules['canton_mission_contributors']>=1&&$rules['canton_mission_contributors']<=20,'Regionale Versorgung bleibt klein und Aufträge benötigen 1 bis 20 Mitglieder.');
            self::require(is_int($rules['pvp_window_start_hour_utc'])&&$rules['pvp_window_start_hour_utc']>=0&&$rules['pvp_window_start_hour_utc']<=23,'Ungültige UTC-Startstunde.');
            self::require(is_numeric($rules['pvp_window_hours'])&&$rules['pvp_window_hours']>0&&$rules['pvp_window_hours']<=24,'Ungültige Fensterdauer.');
            self::require(is_int($rules['crown_period_days'])&&$rules['crown_period_days']>=1&&$rules['crown_period_days']<=365&&is_numeric($rules['crown_duration_hours'])&&$rules['crown_duration_hours']>0&&$rules['crown_duration_hours']<=24,'Ungültiger Kronenkalender.');
            self::require(is_string($rules['crown_anchor'])&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$rules['crown_anchor'])&&strtotime($rules['crown_anchor'].' UTC')!==false,'Ungültiger UTC-Kalenderanker.');
            self::require($rules['crown_objectives']===['gate','arsenal','throne']&&$rules['crown_required_objectives']===3,'Alle drei Belagerungsziele sind erforderlich.');
            $previous=json_decode($row['rules_json'],true);$calendarChanged=false;foreach(['crown_anchor','crown_period_days','crown_duration_hours'] as $key)if($previous[$key]!==$rules[$key])$calendarChanged=true;
            if($calendarChanged)self::require(!$db->query("SELECT id FROM territory_crown_cycles WHERE world_id=? AND status='open' LIMIT 1",[$world])->fetchColumn(),'Der laufende Kronenkrieg muss vor einer Kalenderänderung abgeschlossen sein.');
            foreach(['crown_tie_rule','crown_eligibility','canton_eligibility'] as $k)self::require($rules[$k]===TerritoryRules::defaults()[$k],'Die veröffentlichte Entscheidungsregel kann nicht geändert werden.');
            self::require(is_array($rules['active_cantons']),'Aktive Kantone müssen eine Liste sein.');$ids=$db->query("SELECT canton_id FROM territory_targets WHERE world_id=? AND kind='canton'",[$world])->fetchAll(\PDO::FETCH_COLUMN);
            foreach($rules['active_cantons'] as $id)self::require(is_string($id)&&in_array($id,$ids,true),'Unbekannter Kanton.');
            self::require(is_array($rules['npc_troops'])&&count($rules['npc_troops'])===3,'Ungültige NPC-Verteidigung.');foreach(['commune','canton','crown'] as $kind)self::require(is_int($rules['npc_troops'][$kind]??null)&&$rules['npc_troops'][$kind]>=1&&$rules['npc_troops'][$kind]<=500000,'Ungültige NPC-Verteidigung.');
            $above=$db->query("SELECT alliance_id,SUM(n) AS count FROM (SELECT owner_alliance_id AS alliance_id,COUNT(*) AS n FROM territory_targets WHERE world_id=? AND kind='canton' AND owner_alliance_id IS NOT NULL GROUP BY owner_alliance_id UNION ALL SELECT alliance_id,COUNT(*) AS n FROM territory_campaigns WHERE world_id=? AND slot_reserved=1 GROUP BY alliance_id) a GROUP BY alliance_id HAVING SUM(n)>?",[$world,$world,$rules['canton_limit']])->fetch();self::require(!$above,'Das neue Shrine-Limit unterschreitet vorhandenen oder reservierten Besitz.');
            $old=json_decode($row['rules_json'],true);foreach($db->query('SELECT * FROM territory_targets WHERE world_id=? AND owner_alliance_id IS NOT NULL FOR UPDATE',[$world])->fetchAll() as $t)TerritoryEconomy::settle($t,time(),$old);
            $rules['version']=$expectedVersion+1;$db->execute('UPDATE territory_profiles SET version=?,rules_json=? WHERE world_id=?',[$rules['version'],json_encode($rules),$world]);return $rules;
        }));
    }
    public static function date(int $time): string { return gmdate('Y-m-d H:i:s',$time); }
    public static function timestamp(?string $date): int { return $date?strtotime($date.' UTC'):0; }
    private static function assertPlayer(int $player,int $world): void
    {
        WorldContext::current($world);self::require((bool)Connection::getInstance()->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn(),'Du hast keine Stadt in dieser Welt.',403);
    }
    private static function canonical(array &$value): void { ksort($value);foreach($value as &$v)if(is_array($v))self::canonical($v); }
    private static function assertCaughtUp(int $world): void
    {
        $pending=Connection::getInstance()->query("SELECT id FROM rallies WHERE world_id=? AND target_kind='territory' AND status IN ('gathering','marching','returning') AND CASE WHEN status='gathering' THEN launch_at WHEN status='marching' THEN arrival_time ELSE return_time END<=UTC_TIMESTAMP() LIMIT 1",[$world])->fetchColumn();
        self::require(!$pending,'Die fälligen Feldzüge werden noch verarbeitet. Wiederhole diesen Vorgang gleich.',409);
    }
    private static function rewards(int $player,int $world): array
    {
        $rows=Connection::getInstance()->query('SELECT * FROM territory_rewards WHERE world_id=? AND player_id=? ORDER BY id DESC LIMIT 100',[$world,$player])->fetchAll();foreach($rows as &$r){$r['reward']=json_decode($r['reward_json'],true);unset($r['reward_json']);}return $rows;
    }
    private static function rally(int $world,int $id): array
    {
        $r=Connection::getInstance()->query("SELECT * FROM rallies WHERE id=? AND world_id=? AND target_kind='territory' FOR UPDATE",[$id,$world])->fetch();self::require((bool)$r,'Gebiets-Rally nicht gefunden.',404);return $r;
    }
}
