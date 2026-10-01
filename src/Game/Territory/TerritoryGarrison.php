<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;
use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSpeed};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

final class TerritoryGarrison
{
    public static function reinforce(int $player,int $world,array $body): array
    {
        $m=TerritoryService::mustMember($player,$world);$t=TerritoryService::target($world,(string)($body['target_id']??''),true);
        TerritoryService::require((int)$t['owner_alliance_id']===(int)$m['alliance_id'],'Nur eigene Allianzgebiete können verstärkt werden.');
        TerritoryService::require($t['kind']!=='crown','Kronenziele werden durch Belagerungsrallys kontrolliert.');
        $city=WorldRules::origin($player,TerritoryService::integer($body,'city_id'),$world);MarchDispatcher::assertSlotAvailable($player,$world);
        $buffs=BuffEngine::getBuffs($player,$world);$troops=MarchArmy::clean($body['troops']??[],ResearchEffects::limits($buffs)['march_capacity']);
        $speed=INF;foreach($troops as $code=>$count)$speed=min($speed,MarchSpeed::rally((int)$code,$buffs,false,1));
        $seconds=MarchSpeed::duration(hypot($city['coord_x']-$t['x'],$city['coord_y']-$t['y']),$speed,$world);$db=Connection::getInstance();MarchArmy::reserve($db,(int)$city['id'],$troops);
        $db->execute("INSERT INTO territory_garrisons(world_id,continent_id,target_id,alliance_id,player_id,city_id,troops_json,status,departure_at,arrival_at,travel_seconds)VALUES(?,?,?,?,?,?,?,'inbound',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),?)",[$world,$t['continent_id'],$t['id'],$m['alliance_id'],$player,$city['id'],json_encode($troops),$seconds,$seconds]);
        $id=$db->lastInsertId();$snapshot=TerritoryRally::snapshot($player,(int)$city['id'],$world,$troops,['bonus_pct'=>0],false);$db->execute('UPDATE territory_garrisons SET army_snapshot=? WHERE id=?',[json_encode($snapshot,JSON_THROW_ON_ERROR),$id]);
        return ['message'=>'Die Garnison reist zum Gebiet.','garrison_id'=>$id];
    }
    public static function recall(int $player,int $world,int $id): array
    {
        $g=Connection::getInstance()->query('SELECT * FROM territory_garrisons WHERE id=? AND world_id=? AND player_id=? FOR UPDATE',[$id,$world,$player])->fetch();
        TerritoryService::require((bool)$g,'Diese Garnison gehört dir nicht.',403);TerritoryService::require(in_array($g['status'],['active','inbound'],true),'Diese Garnison ist bereits auf dem Rückweg.');
        self::returnArmy($g,time());return ['message'=>'Deine Garnison kehrt heim.'];
    }
    public static function returnArmy(array $g,int $at): void
    {
        if(!in_array($g['status'],['active','inbound'],true))return;
        $seconds=$g['status']==='inbound'?min((int)$g['travel_seconds'],max(1,$at-TerritoryService::timestamp($g['departure_at']))):(int)$g['travel_seconds'];
        Connection::getInstance()->execute("UPDATE territory_garrisons SET status='returning',return_at=? WHERE id=?",[TerritoryService::date($at+$seconds),$g['id']]);
    }
    /** Execute incoming/return events up to the battle timestamp before reading defense. */
    public static function tick(int $world,int $now,int $limit=100,?string $targetId=null): int
    {
        $db=Connection::getInstance();$params=[$world,TerritoryService::date($now),TerritoryService::date($now)];if($targetId!==null)$params[]=$targetId;
        $rows=$db->query("SELECT g.*,t.owner_alliance_id FROM territory_garrisons g JOIN territory_targets t ON t.world_id=g.world_id AND t.id=g.target_id LEFT JOIN alliance_members m ON m.world_id=g.world_id AND m.player_id=g.player_id WHERE g.world_id=? AND ((g.status='inbound' AND g.arrival_at<=?) OR (g.status='returning' AND g.return_at<=?) OR (g.status='active' AND (NOT(t.owner_alliance_id<=>g.alliance_id) OR NOT(m.alliance_id<=>g.alliance_id))))".($targetId===null?'':' AND g.target_id=?')." ORDER BY CASE WHEN g.status='inbound' THEN g.arrival_at WHEN g.status='returning' THEN g.return_at ELSE g.arrival_at END,g.id LIMIT ".max(1,min(1000,$limit)),$params)->fetchAll();
        foreach($rows as $g){
            $valid=(int)$g['owner_alliance_id']===(int)$g['alliance_id']&&WorldRules::alliance((int)$g['player_id'],$world)===(int)$g['alliance_id'];
            if($g['status']==='active'&&!$valid){self::returnArmy($g,$now);continue;}
            if($g['status']==='inbound'&&TerritoryService::timestamp($g['arrival_at'])<=$now){
                if(!$valid){self::returnArmy($g,TerritoryService::timestamp($g['arrival_at']));$g['status']='returning';$g['return_at']=TerritoryService::date(TerritoryService::timestamp($g['arrival_at'])+(int)$g['travel_seconds']);}
                else{$db->execute("UPDATE territory_garrisons SET status='active' WHERE id=?",[$g['id']]);continue;}
            }
            if($g['status']==='returning'&&TerritoryService::timestamp($g['return_at'])<=$now){
                foreach(json_decode($g['troops_json'],true) as $code=>$count)if($count>0)$db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$g['city_id'],\Conquer\Game\City\TroopData::activeCode((int)$code),$count]);
                $db->execute("UPDATE territory_garrisons SET status='returned',troops_json='{}' WHERE id=?",[$g['id']]);
            }
        }
        return count($rows);
    }
    public static function displace(int $world,string $target,int $at): void
    {
        foreach(Connection::getInstance()->query("SELECT * FROM territory_garrisons WHERE world_id=? AND target_id=? AND status IN ('active','inbound') FOR UPDATE",[$world,$target])->fetchAll() as $g)self::returnArmy($g,$at);
    }
    public static function activeMarches(int $player,int $world): array
    {
        if(!TerritoryService::enabled($world))return [];
        $rows=Connection::getInstance()->query("SELECT g.*,c.coord_x AS origin_x,c.coord_y AS origin_y,t.x AS target_x,t.y AS target_y,t.name AS target_name FROM territory_garrisons g JOIN cities c ON c.id=g.city_id JOIN territory_targets t ON t.world_id=g.world_id AND t.id=g.target_id WHERE g.world_id=? AND g.player_id=? AND g.status IN ('inbound','active','returning')",[$world,$player])->fetchAll();
        return array_merge(array_map(fn($g)=>['id'=>'territory-garrison:'.$g['id'],'garrison_id'=>(int)$g['id'],'march_type'=>'territory_garrison','state'=>$g['status']==='inbound'?'marching':($g['status']==='active'?'arrived':'returning'),'target_type'=>5,'origin_x'=>(int)$g['origin_x'],'origin_y'=>(int)$g['origin_y'],'target_x'=>(int)$g['target_x'],'target_y'=>(int)$g['target_y'],'target_name'=>TerritoryService::displayName($g['target_name']),'departure_time'=>$g['departure_at'],'arrival_time'=>$g['arrival_at'],'return_time'=>$g['return_at'],'troops_json'=>$g['troops_json']],$rows),TerritoryArmyReturn::activeMarches($player,$world));
    }
}
