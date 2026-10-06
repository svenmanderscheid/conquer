<?php
declare(strict_types=1);
namespace Conquer\Game\Rally;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\March\MarchArmy;
use Conquer\Game\March\MarchDispatcher;
use Conquer\Game\March\MarchSkinService;
use Conquer\Game\March\MarchSpeed;
use Conquer\Game\March\CityCombat;
use Conquer\Game\City\TroopData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchEffects;
use Conquer\Game\Kingdom\KingdomService;

/** Reserved alliance armies fight once and return once, including while browsers are closed. */
final class RallyService
{
    public static function start(int $leaderId,int $leaderCityId,int $targetPlayerId,int $targetX,int $targetY,array $troops,int $rallyMinutes,string $message): int
    {
        WorldContext::assertActionAvailable();
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($leaderId,$leaderCityId,$targetPlayerId,$targetX,$targetY,$troops,$rallyMinutes,$message):int{
            $origin=WorldRules::origin($leaderId,$leaderCityId);$target=WorldRules::assertCityAttackAllowed($leaderId,$targetX,$targetY,$targetPlayerId);
            $alliance=WorldRules::alliance($leaderId);if($alliance===null)throw new \RuntimeException('Für eine Rally musst du einer Allianz angehören.');
            if(!in_array($rallyMinutes,[1,5,15,30],true))throw new \RuntimeException('Wähle 1, 5, 15 oder 30 Minuten Sammelzeit.');
            $skinSnapshot=MarchSkinService::dispatchSnapshot($leaderId);
            MarchDispatcher::assertSlotAvailable($leaderId);$clean=self::clean($leaderId,$troops);
            $capacity=RallyCapacity::forCity($leaderId,$leaderCityId)['total'];
            if(array_sum($clean)>$capacity)throw new \RuntimeException('Die Allianzhalle bietet nicht genug Platz für diese Rally.');
            MarchArmy::reserve($db,$leaderCityId,$clean);WorldRules::relinquishShield($leaderId,$leaderCityId);
            $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,march_skin,march_speed_bonus_pct,target_player_id,target_city_id,target_x,target_y,rally_minutes,troops_json,message,result_json,status,launch_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'gathering',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[(int)$origin['world_id'],$leaderId,$leaderCityId,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$targetPlayerId,$target['id'],$targetX,$targetY,$rallyMinutes,json_encode($clean),mb_substr($message,0,512),json_encode(['alliance_id'=>$alliance,'capacity'=>$capacity]),$rallyMinutes]);
            return $db->lastInsertId();
        }));
    }

    public static function join(int $playerId,int $cityId,int $rallyId,array $troops): void
    {
        WorldContext::assertActionAvailable();
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($playerId,$cityId,$rallyId,$troops):void{
            $joinOrigin=WorldRules::origin($playerId,$cityId);$r=self::row($rallyId);WorldContext::current((int)$r['world_id']);self::gathering($r);
            if(strtotime($r['launch_at'].' UTC')<=time())throw new \RuntimeException('Die Sammelzeit ist bereits abgelaufen.');
            if((int)$r['leader_player_id']===$playerId)throw new \RuntimeException('Die führende Armee nimmt bereits teil.');
            $meta=json_decode($r['result_json']??'{}',true)?:[];$alliance=WorldRules::alliance($playerId);
            if($alliance===null||$alliance!==($meta['alliance_id']??null)||$alliance!==WorldRules::alliance((int)$r['leader_player_id']))throw new \RuntimeException('Nur Mitglieder der Rally-Allianz können teilnehmen.');
            if(($r['target_kind']??'city')==='territory')\Conquer\Game\Territory\TerritoryRally::validateJoin($r,$playerId);
            elseif(($r['target_kind']??'city')==='monster')MonsterRally::target((int)$r['world_id'],(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_monster_id']);
            else WorldRules::assertCityAttackAllowed($playerId,(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_player_id']);
            if($db->query('SELECT id FROM rally_participants WHERE rally_id=? AND player_id=?',[$rallyId,$playerId])->fetchColumn()!==false)throw new \RuntimeException('Du nimmst bereits an dieser Rally teil.');
            MarchDispatcher::assertSlotAvailable($playerId);$clean=self::clean($playerId,$troops);
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            // Existing city rallies without a recorded limit retain their original contract.
            if(isset($meta['capacity'])||in_array($r['target_kind']??'city',['monster','territory'],true)){
                $total=array_sum(array_map(static fn($a)=>!empty($a['is_ai'])?0:array_sum($a['troops']),self::armies($r)));
                if($total+array_sum($clean)>(int)$meta['capacity'])throw new \RuntimeException('Die Rally-Kapazität der Allianzhalle ist erschöpft.');
            }
            $host=WorldRules::origin((int)$r['leader_player_id'],(int)$r['leader_city_id'],(int)$r['world_id']);$buffs=BuffEngine::getBuffs($playerId,(int)$r['world_id']);$skinMultiplier=MarchSkinService::speedMultiplier(['bonus_pct'=>$skinSnapshot['bonus_pct']]);$speed=INF;
            foreach($clean as $code=>$count)if($count>0)$speed=min($speed,MarchSpeed::rally((int)$code,$buffs,($r['target_kind']??'city')==='monster',$skinMultiplier));
            $seconds=MarchSpeed::duration(hypot($host['coord_x']-$joinOrigin['coord_x'],$host['coord_y']-$joinOrigin['coord_y']),$speed,(int)$r['world_id']);
            // Use the database clock for both the deadline check and the saved march.
            // A stale browser countdown must never reserve an army that cannot join.
            $travel=$db->query('SELECT UTC_TIMESTAMP() AS joined_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) AS arrival_time,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND)<=? AS arrives_in_time',[$seconds,$seconds,$r['launch_at']])->fetch();
            if(!(bool)$travel['arrives_in_time'])throw new \RuntimeException(\Conquer\Game\Locale::t('rally.join.too_late'));
            $apCost=0;
            if(($r['target_kind']??'city')==='monster'){
                $definition=$meta['monster'];
                $apCost=\Conquer\Game\Player\ActionPoints::monsterCost((int)($definition['action_point_cost']??\Conquer\Game\Player\ActionPoints::costForMonster($definition['name'])),$buffs);
                \Conquer\Game\Player\ActionPoints::deduct($playerId,$apCost);
            }
            MarchArmy::reserve($db,$cityId,$clean);if(($r['target_kind']??'city')!=='monster')WorldRules::relinquishShield($playerId,$cityId);
            $db->execute("INSERT INTO rally_participants(rally_id,player_id,city_id,march_skin,march_speed_bonus_pct,troops_json,ap_cost_paid,status,joined_at,arrival_time) VALUES(?,?,?,?,?,?,?,'joining',?,?)",[$rallyId,$playerId,$cityId,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],json_encode($clean),$apCost,$travel['joined_at'],$travel['arrival_time']]);
            if(($r['target_kind']??'city')==='territory')$db->execute('UPDATE rally_participants SET territory_army_snapshot=? WHERE rally_id=? AND player_id=?',[json_encode(\Conquer\Game\Territory\TerritoryRally::snapshot($playerId,$cityId,(int)$r['world_id'],$clean,$skinSnapshot),JSON_THROW_ON_ERROR),$rallyId,$playerId]);
            RallySupport::refresh($r);
        }));
    }

    public static function launch(int $rallyId,int $captainId): array
    {
        WorldContext::assertActionAvailable();
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($rallyId,$captainId):void{$r=self::row($rallyId);WorldContext::current((int)$r['world_id']);self::captain($r,$captainId);self::gathering($r);self::launchRow($r);}));
        $status=(string)Connection::getInstance()->query('SELECT status FROM rallies WHERE id=?',[$rallyId])->fetchColumn();
        return ['launched'=>$status==='marching','status'=>$status,'rally_id'=>$rallyId];
    }

    public static function tryLaunch(int $rallyId): void
    {
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($rallyId):void{$r=self::row($rallyId);self::gathering($r);if(strtotime($r['launch_at'].' UTC')>time())throw new \RuntimeException('Die Rally sammelt noch Truppen.');WorldContext::run((int)$r['world_id'],fn()=>self::launchRow($r));}));
    }

    public static function cancel(int $rallyId,int $captainId): void
    {
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($rallyId,$captainId):void{$r=self::row($rallyId);WorldContext::current((int)$r['world_id']);self::captain($r,$captainId);self::gathering($r);self::cancelGathering($r,'Vom Anführer abgebrochen.');}));
    }

    public static function tick(): void
    {
        $db=Connection::getInstance();
        \Conquer\Game\Territory\TerritoryRally::tick();
        $db->execute("UPDATE rally_participants rp JOIN rallies r ON r.id=rp.rally_id SET rp.status='pending' WHERE r.status='gathering' AND rp.status='joining' AND rp.arrival_time<=UTC_TIMESTAMP()");
        RallySupport::tick();
        $ids=$db->query("SELECT id FROM rallies WHERE target_kind<>'territory' AND ((status='gathering' AND launch_at<=UTC_TIMESTAMP()) OR (status='marching' AND arrival_time<=UTC_TIMESTAMP()) OR (status='returning' AND return_time<=UTC_TIMESTAMP())) ORDER BY id LIMIT 50")->fetchAll(\PDO::FETCH_COLUMN);
        if(!$ids)return;
        WorldRules::combatLock(function()use($db,$ids):void{foreach($ids as $id){try{$db->transaction(function()use($db,$id):void{
            $r=self::row((int)$id);
            WorldContext::run((int)$r['world_id'],function()use($db,$id,$r):void{
            if($r['status']==='gathering'&&strtotime($r['launch_at'].' UTC')<=time()){self::launchRow($r);return;}
            if($r['status']==='marching'&&strtotime($r['arrival_time'].' UTC')<=time()){
                $result=($r['target_kind']??'city')==='monster'?MonsterRally::resolve($r,self::armies($r)):CityCombat::resolve(self::armies($r),(int)$r['target_city_id'],(int)$r['target_x'],(int)$r['target_y'],null,(int)$id);
                $meta=json_decode($r['result_json']??'{}',true)?:[];$result+=$meta;
                $db->execute("UPDATE rallies SET status='returning',result_json=? WHERE id=?",[json_encode($result),$id]);$r['status']='returning';$r['result_json']=json_encode($result);
            }
            if($r['status']==='returning'&&strtotime($r['return_time'].' UTC')<=time()){$result=json_decode($r['result_json'],true);self::refund($result['armies']??self::armies($r));$db->execute("UPDATE rallies SET status='complete' WHERE id=?",[$id]);$db->execute("UPDATE rally_participants SET status='returned' WHERE rally_id=?",[$id]);}
            });
        });}catch(\Throwable $e){\Conquer\Logger::getInstance()->error('[Rally] '.$id.': '.$e->getMessage());}}});
    }

    public static function listForAlliance(int $allianceId): array
    {
        $db=Connection::getInstance();$rows=$db->query("SELECT r.*,COALESCE(k.display_name,p.username) AS leader_name,COALESCE(tk.display_name,tp.username) AS target_name,(SELECT COUNT(*) FROM rally_participants rp WHERE rp.rally_id=r.id AND rp.status IN ('joining','pending','marching')) AS participant_count FROM rallies r JOIN players p ON p.id=r.leader_player_id LEFT JOIN players tp ON tp.id=r.target_player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN kingdom_profiles tk ON tk.player_id=tp.id WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND CAST(JSON_UNQUOTE(JSON_EXTRACT(r.result_json,'$.alliance_id')) AS UNSIGNED)=? ORDER BY r.id DESC LIMIT 30",[WorldContext::id(),$allianceId])->fetchAll();
        $ids=[];foreach($rows as &$r){self::settleJoiners($r);$r=self::describe($r);$r['troops']=json_decode($r['troops_json'],true)?:[];$r['result']=json_decode($r['result_json']??'{}',true);$r['participants']=array_values(array_filter(self::getParticipants((int)$r['id'],false),fn($p)=>in_array($p['status'],['joining','pending','marching'],true)));$r['participant_count']=count($r['participants']);$ids[]=(int)$r['leader_player_id'];if($r['target_player_id']!==null)$ids[]=(int)$r['target_player_id'];foreach($r['participants'] as $p)$ids[]=(int)$p['player_id'];unset($r['troops_json'],$r['result_json']);}unset($r);
        $summaries=KingdomService::publicSummaries(array_unique($ids));foreach($rows as &$r){$r['leader']=$summaries[(int)$r['leader_player_id']]??null;$r['target_player']=$r['target_player_id']!==null?($summaries[(int)$r['target_player_id']]??null):null;foreach($r['participants'] as &$p)$p['profile']=$summaries[(int)$p['player_id']]??null;unset($p);}unset($r);return $rows;
    }
    public static function getOpenRallies(int $allianceId): array{return self::listForAlliance($allianceId);}
    /** Map-safe alliance rally routes without troop counts or combat details. */
    public static function publicMapMarches(int $allianceId,int $viewerPlayerId): array
    {
        $db=Connection::getInstance();$rows=$db->query("SELECT r.*,c.coord_x AS leader_x,c.coord_y AS leader_y FROM rallies r JOIN cities c ON c.id=r.leader_city_id WHERE r.world_id=? AND r.status IN ('marching','returning') AND CAST(JSON_UNQUOTE(JSON_EXTRACT(r.result_json,'$.alliance_id')) AS UNSIGNED)=?",[WorldContext::id(),$allianceId])->fetchAll();$result=[];
        foreach($rows as $r){
            $base=['rally_id'=>(int)$r['id'],'march_type'=>'rally','target_type'=>match($r['target_kind']??'city'){'monster'=>3,'territory'=>5,default=>2},'target_x'=>(int)$r['target_x'],'target_y'=>(int)$r['target_y'],'departure_time'=>$r['launch_at'],'arrival_time'=>$r['arrival_time'],'return_time'=>$r['return_time'],'state'=>$r['status'],'is_allied'=>true];
            if($r['status']==='marching'){$leader=(int)$r['leader_player_id'];$result[]=$base+['id'=>'alliance-rally:'.$r['id'],'player_id'=>$leader,'is_own'=>$leader===$viewerPlayerId,'origin_x'=>(int)$r['leader_x'],'origin_y'=>(int)$r['leader_y']];continue;}
            $meta=json_decode($r['result_json']??'{}',true)?:[];$armies=$meta['armies']??self::armies($r);
            foreach($armies as $army){if(!empty($army['is_ai']))continue;$city=$db->query('SELECT coord_x,coord_y FROM cities WHERE id=? AND world_id=?',[(int)$army['city_id'],(int)$r['world_id']])->fetch();if(!$city)continue;$owner=(int)$army['player_id'];$result[]=$base+['id'=>'alliance-rally:'.$r['id'].':'.$owner,'player_id'=>$owner,'is_own'=>$owner===$viewerPlayerId,'origin_x'=>(int)$city['coord_x'],'origin_y'=>(int)$city['coord_y']];}
        }
        return $result;
    }
    /** Each real rally occupies one march slot for each contributing owner. */
    public static function activeMarchesForPlayer(int $playerId): array
    {
        $db=Connection::getInstance();$rows=$db->query("SELECT r.*,c.coord_x AS origin_x,c.coord_y AS origin_y FROM rallies r JOIN cities c ON c.id=r.leader_city_id WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND (r.leader_player_id=? OR EXISTS(SELECT 1 FROM rally_participants rp WHERE rp.rally_id=r.id AND rp.player_id=? AND rp.status IN ('joining','pending','marching'))) ORDER BY r.id",[WorldContext::id(),$playerId,$playerId])->fetchAll();$marches=[];
        foreach($rows as $r){
            if($r['status']==='gathering'&&(int)$r['leader_player_id']!==$playerId){
                self::settleJoiners($r);
                $joining=$db->query("SELECT rp.*,c.coord_x AS join_origin_x,c.coord_y AS join_origin_y FROM rally_participants rp JOIN cities c ON c.id=rp.city_id WHERE rp.rally_id=? AND rp.player_id=?",[$r['id'],$playerId])->fetch();
                if(($joining['status']??null)==='joining'){$marches[]=['id'=>'rally-join:'.$r['id'].':'.$playerId,'rally_id'=>(int)$r['id'],'march_type'=>'rally_join','march_skin'=>$joining['march_skin']??null,'march_speed_bonus_pct'=>(int)($joining['march_speed_bonus_pct']??0),'state'=>'marching','target_type'=>2,'target_x'=>(int)$r['origin_x'],'target_y'=>(int)$r['origin_y'],'origin_x'=>(int)$joining['join_origin_x'],'origin_y'=>(int)$joining['join_origin_y'],'departure_time'=>$joining['joined_at'],'arrival_time'=>$joining['arrival_time'],'return_time'=>null,'troops_json'=>$joining['troops_json']];}
                elseif(($joining['status']??null)==='pending'){$marches[]=['id'=>'rally:'.$r['id'],'rally_id'=>(int)$r['id'],'march_type'=>'rally','march_skin'=>$joining['march_skin']??null,'march_speed_bonus_pct'=>(int)($joining['march_speed_bonus_pct']??0),'state'=>'gathering','target_type'=>match($r['target_kind']??'city'){'monster'=>3,'territory'=>5,default=>2},'target_x'=>(int)$r['target_x'],'target_y'=>(int)$r['target_y'],'origin_x'=>(int)$r['origin_x'],'origin_y'=>(int)$r['origin_y'],'departure_time'=>$r['launch_at'],'arrival_time'=>$r['launch_at'],'return_time'=>null,'troops_json'=>$joining['troops_json']];}
                continue;
            }
            $combined=[];$result=json_decode($r['result_json']??'{}',true)?:[];$armies=$r['status']==='returning'?($result['armies']??self::armies($r)):self::armies($r);$originX=(int)$r['origin_x'];$originY=(int)$r['origin_y'];
            if($r['status']==='returning'){$own=array_values(array_filter($armies,fn($army)=>(int)$army['player_id']===$playerId))[0]??null;if(!$own)continue;$armies=[$own];$home=WorldRules::origin($playerId,(int)$own['city_id'],(int)$r['world_id']);$originX=(int)$home['coord_x'];$originY=(int)$home['coord_y'];}
            foreach($armies as $army)foreach(($r['status']==='returning'?($army['survivors']??$army['troops']):$army['troops']) as $code=>$count)$combined[$code]=($combined[$code]??0)+(int)$count;
            $marches[]=['id'=>'rally:'.$r['id'],'rally_id'=>(int)$r['id'],'march_type'=>'rally','march_skin'=>$r['march_skin']??null,'march_speed_bonus_pct'=>(int)($r['march_speed_bonus_pct']??0),'state'=>$r['status'],'target_x'=>(int)$r['target_x'],'target_y'=>(int)$r['target_y'],'origin_x'=>$originX,'origin_y'=>$originY,'departure_time'=>$r['launch_at'],'arrival_time'=>$r['arrival_time']??$r['launch_at'],'return_time'=>$r['return_time'],'troops_json'=>json_encode($combined)];}
        return $marches;
    }
    public static function getParticipants(int $rallyId,bool $withSummaries=true): array
    {
        $rows=Connection::getInstance()->query('SELECT rp.*,COALESCE(k.display_name,p.username) AS username,c.coord_x,c.coord_y FROM rally_participants rp JOIN players p ON p.id=rp.player_id JOIN cities c ON c.id=rp.city_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE rp.rally_id=? ORDER BY rp.id',[$rallyId])->fetchAll();$summaries=$withSummaries?KingdomService::publicSummaries(array_column($rows,'player_id')):[];foreach($rows as &$r){$r['troops']=json_decode($r['troops_json'],true)?:[];if($withSummaries)$r['profile']=$summaries[(int)$r['player_id']]??null;unset($r['troops_json']);}unset($r);$rally=Connection::getInstance()->query('SELECT * FROM rallies WHERE id=?',[$rallyId])->fetch();return array_merge($rows,$rally?RallySupport::participants($rally):[]);
    }
    private static function clean(int $playerId,array $troops): array{return MarchArmy::clean($troops,ResearchEffects::limits(BuffEngine::getBuffs($playerId))['march_capacity']);}
    private static function row(int $id): array{$r=Connection::getInstance()->query('SELECT * FROM rallies WHERE id=? FOR UPDATE',[$id])->fetch();if(!$r)throw new \RuntimeException('Rally nicht gefunden.');return $r;}
    private static function gathering(array $r): void{if($r['status']!=='gathering')throw new \RuntimeException('Diese Rally sammelt keine Truppen mehr.');}
    private static function captain(array $r,int $id): void{if((int)$r['leader_player_id']!==$id)throw new \RuntimeException('Nur der Rally-Anführer kann diese Aktion ausführen.');}
    public static function armies(array $r): array
    {
        $armies=[['player_id'=>(int)$r['leader_player_id'],'city_id'=>(int)$r['leader_city_id'],'troops'=>json_decode($r['troops_json'],true)?:[],'march_skin'=>$r['march_skin']??null,'march_speed_bonus_pct'=>(int)($r['march_speed_bonus_pct']??0),'army_snapshot'=>json_decode($r['territory_army_snapshot']??'null',true)]];
        foreach(self::getParticipants((int)$r['id'],false) as $p)if(in_array($p['status'],['joining','pending','marching'],true))$armies[]=['player_id'=>(int)$p['player_id'],'city_id'=>(int)$p['city_id'],'troops'=>$p['troops'],'march_skin'=>$p['march_skin']??null,'march_speed_bonus_pct'=>(int)($p['march_speed_bonus_pct']??0),'army_snapshot'=>json_decode($p['territory_army_snapshot']??'null',true),'is_ai'=>!empty($p['is_ai'])];return $armies;
    }
    private static function launchRow(array $r): void
    {
        if(($r['target_kind']??'city')==='territory'){\Conquer\Game\Territory\TerritoryRally::launch($r);return;}
        if(($r['target_kind']??'city')==='monster'){
            try{MonsterRally::target((int)$r['world_id'],(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_monster_id']);}
            catch(\PDOException $e){throw $e;}
        catch(\RuntimeException $e){self::cancelGathering($r,$e->getMessage());return;}
        }
        $db=Connection::getInstance();self::settleJoiners($r,true);$r=RallySupport::refresh($r);$armies=self::armies($r);$origin=WorldRules::origin((int)$r['leader_player_id'],(int)$r['leader_city_id'],(int)$r['world_id']);$speed=INF;$returnSpeed=INF;$monster=($r['target_kind']??'city')==='monster';
        foreach($armies as $army){
            if(!empty($army['is_ai']))continue;
            $raw=BuffEngine::getBuffs($army['player_id'],(int)$r['world_id']);$buffs=$monster?$raw:\Conquer\Game\Player\TalentEffects::cavalryMarch($raw,$army['troops']);$returnBuffs=$monster?$raw:\Conquer\Game\Player\TalentEffects::cavalryMarch($raw,$army['troops'],true);
            $skinMultiplier=MarchSkinService::speedMultiplier(['bonus_pct'=>$army['march_speed_bonus_pct']??0]);
            foreach($army['troops'] as $code=>$count){if($count<=0)continue;$speed=min($speed,MarchSpeed::rally((int)$code,$buffs,$monster,$skinMultiplier));$returnSpeed=min($returnSpeed,MarchSpeed::rally((int)$code,$returnBuffs,$monster,$skinMultiplier));}
        }
        $seconds=MarchSpeed::duration(hypot($r['target_x']-$origin['coord_x'],$r['target_y']-$origin['coord_y']),$speed,(int)$r['world_id']);
        $returnSeconds=MarchSpeed::duration(hypot($r['target_x']-$origin['coord_x'],$r['target_y']-$origin['coord_y']),$returnSpeed,(int)$r['world_id']);
        $db->execute("UPDATE rallies SET status='marching',launch_at=UTC_TIMESTAMP(),arrival_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?",[$seconds,$seconds+$returnSeconds,$r['id']]);$db->execute("UPDATE rally_participants SET status='marching' WHERE rally_id=? AND status='pending'",[$r['id']]);
    }
    public static function describe(array $row): array
    {
        $meta=json_decode($row['result_json']??'{}',true)?:[];
        if(($row['target_kind']??'city')==='monster'){
            $row['target_name']=($meta['monster']['name']??'Monster').' Lv. '.($meta['monster']['level']??1);
            $row['ap_cost_base']=max(0,(int)($meta['monster']['action_point_cost']??\Conquer\Game\Player\ActionPoints::costForMonster($meta['monster']['name']??'')));
        }
        if(($row['target_kind']??'city')==='territory')$row['target_name']=$meta['target_name']??'Eroberungsziel';
        $row['capacity']=$meta['capacity']??null;$row['human_capacity_remaining']=isset($meta['capacity'])?max(0,(int)$meta['capacity']-array_sum(array_map(static fn($a)=>!empty($a['is_ai'])?0:array_sum($a['troops']),self::armies($row)))):null;
        return $row;
    }

    /** Arrived armies become participants; armies still travelling when the rally departs are returned. */
    private static function settleJoiners(array $r,bool $launching=false): void
    {
        $db=Connection::getInstance();$db->execute("UPDATE rally_participants SET status='pending' WHERE rally_id=? AND status='joining' AND arrival_time<=UTC_TIMESTAMP()",[$r['id']]);
        if(!$launching)return;
        $late=$db->query("SELECT player_id,city_id,troops_json FROM rally_participants WHERE rally_id=? AND status='joining'",[$r['id']])->fetchAll();
        self::refundParticipantAp((int)$r['id'],'joining');
        foreach($late as $army)self::refund([['player_id'=>(int)$army['player_id'],'city_id'=>(int)$army['city_id'],'troops'=>json_decode($army['troops_json'],true)?:[]]]);
        $db->execute("UPDATE rally_participants SET status='cancelled' WHERE rally_id=? AND status='joining'",[$r['id']]);
    }

    public static function cancelGathering(array $row,string $reason): void
    {
        if(($row['target_kind']??'city')==='territory'){\Conquer\Game\Territory\TerritoryRally::cancelGathering($row,$reason);return;}
        $db=Connection::getInstance();self::refund(self::armies($row));
        $meta=json_decode($row['result_json']??'{}',true)?:[];$meta['reason']=$reason;
        if(($row['target_kind']??'city')==='monster'){
            \Conquer\Game\Player\ActionPoints::refund((int)$row['leader_player_id'],(int)($meta['ap_cost']??0));
            self::refundParticipantAp((int)$row['id']);
        }
        $db->execute("UPDATE rallies SET status='cancelled',result_json=? WHERE id=?",[json_encode($meta),$row['id']]);
        $db->execute("UPDATE rally_participants SET status='cancelled' WHERE rally_id=?",[$row['id']]);
    }

    public static function refund(array $armies): void
    {
        $db=Connection::getInstance();
        foreach($armies as $army){
            if(!empty($army['is_ai']))continue;
            foreach(($army['survivors']??$army['troops']) as $code=>$count)if($count>0)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$army['city_id'],\Conquer\Game\City\TroopData::activeCode((int)$code),$count]);
            $world=(int)$db->query('SELECT world_id FROM cities WHERE id=?',[$army['city_id']])->fetchColumn();$loot=$army['loot']??[];
            if(!empty($loot['gems']))$db->execute('UPDATE players SET gems=gems+? WHERE id=?',[(int)$loot['gems'],$army['player_id']]);
            foreach(($army['items']??[]) as $code=>$amount)\Conquer\Game\Inventory\InventoryService::addItems($army['player_id'],(int)$code,(int)$amount,$world);
            \Conquer\Game\Rewards\RewardCatalog::grantFragments($army['player_id'],$army['fragments']??[]);
            \Conquer\Game\Rewards\RewardCatalog::grantRelics($army['player_id'],$army['relics']??[]);
            $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$loot['food']??0,$loot['lumber']??0,$loot['stone']??0,$loot['gold']??0,$army['city_id']]);
        }
    }

    private static function refundParticipantAp(int $rallyId,?string $status=null): void
    {
        $db=Connection::getInstance();$sql='SELECT id,player_id,ap_cost_paid FROM rally_participants WHERE rally_id=? AND ap_cost_paid>0';$params=[$rallyId];
        if($status!==null){$sql.=' AND status=?';$params[]=$status;}
        foreach($db->query($sql.' FOR UPDATE',$params)->fetchAll() as $participant){
            \Conquer\Game\Player\ActionPoints::refund((int)$participant['player_id'],(int)$participant['ap_cost_paid']);
            $db->execute('UPDATE rally_participants SET ap_cost_paid=0 WHERE id=?',[$participant['id']]);
        }
    }
}
