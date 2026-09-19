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
            MarchDispatcher::assertSlotAvailable($leaderId);$clean=self::clean($leaderId,$troops);MarchArmy::reserve($db,$leaderCityId,$clean);WorldRules::relinquishShield($leaderId,$leaderCityId);
            $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,march_skin,march_speed_bonus_pct,target_player_id,target_city_id,target_x,target_y,rally_minutes,troops_json,message,result_json,status,launch_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'gathering',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[(int)$origin['world_id'],$leaderId,$leaderCityId,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$targetPlayerId,$target['id'],$targetX,$targetY,$rallyMinutes,json_encode($clean),mb_substr($message,0,512),json_encode(['alliance_id'=>$alliance]),$rallyMinutes]);
            return $db->lastInsertId();
        }));
    }

    public static function join(int $playerId,int $cityId,int $rallyId,array $troops): void
    {
        WorldContext::assertActionAvailable();
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($playerId,$cityId,$rallyId,$troops):void{
            WorldRules::origin($playerId,$cityId);$r=self::row($rallyId);WorldContext::current((int)$r['world_id']);self::gathering($r);
            if(strtotime($r['launch_at'].' UTC')<=time())throw new \RuntimeException('Die Sammelzeit ist bereits abgelaufen.');
            if((int)$r['leader_player_id']===$playerId)throw new \RuntimeException('Die führende Armee nimmt bereits teil.');
            $meta=json_decode($r['result_json']??'{}',true)?:[];$alliance=WorldRules::alliance($playerId);
            if($alliance===null||$alliance!==($meta['alliance_id']??null)||$alliance!==WorldRules::alliance((int)$r['leader_player_id']))throw new \RuntimeException('Nur Mitglieder der Rally-Allianz können teilnehmen.');
            if(($r['target_kind']??'city')==='monster')MonsterRally::target((int)$r['world_id'],(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_monster_id']);
            else WorldRules::assertCityAttackAllowed($playerId,(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_player_id']);
            if($db->query('SELECT id FROM rally_participants WHERE rally_id=? AND player_id=?',[$rallyId,$playerId])->fetchColumn()!==false)throw new \RuntimeException('Du nimmst bereits an dieser Rally teil.');
            MarchDispatcher::assertSlotAvailable($playerId);$clean=self::clean($playerId,$troops);
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            if(($r['target_kind']??'city')==='monster'){
                $total=array_sum(array_map(static fn($a)=>array_sum($a['troops']),self::armies($r)));
                if($total+array_sum($clean)>(int)$meta['capacity'])throw new \RuntimeException('Die Rally-Kapazität der Allianzhalle ist erschöpft.');
            }
            MarchArmy::reserve($db,$cityId,$clean);if(($r['target_kind']??'city')!=='monster')WorldRules::relinquishShield($playerId,$cityId);
            $db->execute("INSERT INTO rally_participants(rally_id,player_id,city_id,march_skin,march_speed_bonus_pct,troops_json,status) VALUES(?,?,?,?,?,?,'pending')",[$rallyId,$playerId,$cityId,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],json_encode($clean)]);
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
        $ids=$db->query("SELECT id FROM rallies WHERE (status='gathering' AND launch_at<=UTC_TIMESTAMP()) OR (status='marching' AND arrival_time<=UTC_TIMESTAMP()) OR (status='returning' AND return_time<=UTC_TIMESTAMP()) ORDER BY id LIMIT 50")->fetchAll(\PDO::FETCH_COLUMN);
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
        $db=Connection::getInstance();$rows=$db->query("SELECT r.*,COALESCE(k.display_name,p.username) AS leader_name,COALESCE(tk.display_name,tp.username) AS target_name,(SELECT COUNT(*) FROM rally_participants rp WHERE rp.rally_id=r.id) AS participant_count FROM rallies r JOIN players p ON p.id=r.leader_player_id LEFT JOIN players tp ON tp.id=r.target_player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN kingdom_profiles tk ON tk.player_id=tp.id WHERE r.world_id=? AND CAST(JSON_UNQUOTE(JSON_EXTRACT(r.result_json,'$.alliance_id')) AS UNSIGNED)=? ORDER BY r.id DESC LIMIT 30",[WorldContext::id(),$allianceId])->fetchAll();
        foreach($rows as &$r){$r=self::describe($r);$r['troops']=json_decode($r['troops_json'],true)?:[];$r['result']=json_decode($r['result_json']??'{}',true);$r['participants']=self::getParticipants((int)$r['id']);unset($r['troops_json'],$r['result_json']);}unset($r);return $rows;
    }
    public static function getOpenRallies(int $allianceId): array{return self::listForAlliance($allianceId);}
    /** Each real rally occupies one march slot for each contributing owner. */
    public static function activeMarchesForPlayer(int $playerId): array
    {
        $db=Connection::getInstance();$rows=$db->query("SELECT r.*,c.coord_x AS origin_x,c.coord_y AS origin_y FROM rallies r JOIN cities c ON c.id=r.leader_city_id WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND (r.leader_player_id=? OR EXISTS(SELECT 1 FROM rally_participants rp WHERE rp.rally_id=r.id AND rp.player_id=? AND rp.status IN ('pending','marching'))) ORDER BY r.id",[WorldContext::id(),$playerId,$playerId])->fetchAll();$marches=[];
        foreach($rows as $r){$combined=[];$result=json_decode($r['result_json']??'{}',true)?:[];$armies=$r['status']==='returning'?($result['armies']??self::armies($r)):self::armies($r);foreach($armies as $army)foreach(($r['status']==='returning'?($army['survivors']??$army['troops']):$army['troops']) as $code=>$count)$combined[$code]=($combined[$code]??0)+(int)$count;
            $marches[]=['id'=>'rally:'.$r['id'],'rally_id'=>(int)$r['id'],'march_type'=>'rally','march_skin'=>$r['march_skin']??null,'march_speed_bonus_pct'=>(int)($r['march_speed_bonus_pct']??0),'state'=>$r['status'],'target_x'=>(int)$r['target_x'],'target_y'=>(int)$r['target_y'],'origin_x'=>(int)$r['origin_x'],'origin_y'=>(int)$r['origin_y'],'departure_time'=>$r['launch_at'],'arrival_time'=>$r['arrival_time']??$r['launch_at'],'return_time'=>$r['return_time'],'troops_json'=>json_encode($combined)];}
        return $marches;
    }
    public static function getParticipants(int $rallyId): array
    {
        $rows=Connection::getInstance()->query('SELECT rp.*,COALESCE(k.display_name,p.username) AS username FROM rally_participants rp JOIN players p ON p.id=rp.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE rp.rally_id=? ORDER BY rp.id',[$rallyId])->fetchAll();foreach($rows as &$r){$r['troops']=json_decode($r['troops_json'],true)?:[];unset($r['troops_json']);}unset($r);return $rows;
    }
    private static function clean(int $playerId,array $troops): array{return MarchArmy::clean($troops,ResearchEffects::limits(BuffEngine::getBuffs($playerId))['march_capacity']);}
    private static function row(int $id): array{$r=Connection::getInstance()->query('SELECT * FROM rallies WHERE id=? FOR UPDATE',[$id])->fetch();if(!$r)throw new \RuntimeException('Rally nicht gefunden.');return $r;}
    private static function gathering(array $r): void{if($r['status']!=='gathering')throw new \RuntimeException('Diese Rally sammelt keine Truppen mehr.');}
    private static function captain(array $r,int $id): void{if((int)$r['leader_player_id']!==$id)throw new \RuntimeException('Nur der Rally-Anführer kann diese Aktion ausführen.');}
    private static function armies(array $r): array
    {
        $armies=[['player_id'=>(int)$r['leader_player_id'],'city_id'=>(int)$r['leader_city_id'],'troops'=>json_decode($r['troops_json'],true)?:[],'march_skin'=>$r['march_skin']??null,'march_speed_bonus_pct'=>(int)($r['march_speed_bonus_pct']??0)]];
        foreach(self::getParticipants((int)$r['id']) as $p)if(in_array($p['status'],['pending','marching'],true))$armies[]=['player_id'=>(int)$p['player_id'],'city_id'=>(int)$p['city_id'],'troops'=>$p['troops'],'march_skin'=>$p['march_skin']??null,'march_speed_bonus_pct'=>(int)($p['march_speed_bonus_pct']??0)];return $armies;
    }
    private static function launchRow(array $r): void
    {
        if(($r['target_kind']??'city')==='monster'){
            try{MonsterRally::target((int)$r['world_id'],(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_monster_id']);}
            catch(\PDOException $e){throw $e;}
        catch(\RuntimeException $e){self::cancelGathering($r,$e->getMessage());return;}
        }
        $db=Connection::getInstance();$armies=self::armies($r);$origin=WorldRules::origin((int)$r['leader_player_id'],(int)$r['leader_city_id'],(int)$r['world_id']);$speed=INF;
        foreach($armies as $army){$buffs=BuffEngine::getBuffs($army['player_id'],(int)$r['world_id']);$skinMultiplier=MarchSkinService::speedMultiplier(['bonus_pct'=>$army['march_speed_bonus_pct']??0]);foreach($army['troops'] as $code=>$count){if($count<=0)continue;$speed=min($speed,MarchSpeed::rally((int)$code,$buffs,($r['target_kind']??'city')==='monster',$skinMultiplier));}}
        $seconds=max(5,(int)floor(hypot($r['target_x']-$origin['coord_x'],$r['target_y']-$origin['coord_y'])*100/max(1,$speed)));
        $db->execute("UPDATE rallies SET status='marching',launch_at=UTC_TIMESTAMP(),arrival_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?",[$seconds,$seconds*2,$r['id']]);$db->execute("UPDATE rally_participants SET status='marching' WHERE rally_id=? AND status='pending'",[$r['id']]);
    }
    public static function describe(array $row): array
    {
        $meta=json_decode($row['result_json']??'{}',true)?:[];
        if(($row['target_kind']??'city')==='monster')$row['target_name']=($meta['monster']['name']??'Monster').' Lv. '.($meta['monster']['level']??1);
        $row['capacity']=$meta['capacity']??null;
        return $row;
    }

    private static function cancelGathering(array $row,string $reason): void
    {
        $db=Connection::getInstance();self::refund(self::armies($row));
        $meta=json_decode($row['result_json']??'{}',true)?:[];$meta['reason']=$reason;
        if(($row['target_kind']??'city')==='monster')$db->execute('UPDATE players SET action_points=LEAST(?,action_points+?) WHERE id=?',[\Conquer\Game\Player\ActionPoints::MAX_AP,(int)($meta['ap_cost']??0),$row['leader_player_id']]);
        $db->execute("UPDATE rallies SET status='cancelled',result_json=? WHERE id=?",[json_encode($meta),$row['id']]);
        $db->execute("UPDATE rally_participants SET status='cancelled' WHERE rally_id=?",[$row['id']]);
    }

    private static function refund(array $armies): void
    {
        $db=Connection::getInstance();foreach($armies as $army){foreach(($army['survivors']??$army['troops']) as $code=>$count)if($count>0)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$army['city_id'],$code,$count]);$loot=$army['loot']??[];if(!empty($loot['gems']))$db->execute('UPDATE players SET gems=gems+? WHERE id=?',[(int)$loot['gems'],$army['player_id']]);foreach(($army['items']??[]) as $code=>$amount)\Conquer\Game\Inventory\InventoryService::addItems($army['player_id'],(int)$code,(int)$amount);$db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$loot['food']??0,$loot['lumber']??0,$loot['stone']??0,$loot['gold']??0,$army['city_id']]);}
    }
}
