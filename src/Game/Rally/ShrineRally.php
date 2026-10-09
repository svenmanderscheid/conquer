<?php
declare(strict_types=1);
namespace Conquer\Game\Rally;
use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\World\WorldContext;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSkinService};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Shrine\{CongressService,ShrineEvent};

/** Shrine and Congress rallies share reservation, joining, launch and return receipts. */
final class ShrineRally
{
    public static function target(int $player,int $id,?string $eventInstance=null,?int $arrival=null): array
    {
        $t=CongressService::detail($id,$player);
        if(!$t)throw new \RuntimeException('Rally target not found.',404);
        $scheduledEvent=$arrival!==null&&isset($t['event'])&&!empty($t['own_alliance_id'])&&$t['alliance_id']!==$t['own_alliance_id'];
        if(!$t['can_attack']&&!$scheduledEvent)throw new \RuntimeException($t['locked_reason']??'This target cannot be attacked now.',403);
        if(isset($t['event'])&&($eventInstance!==null||$arrival!==null)){
            if(!ShrineEvent::arrivalAllowed($eventInstance??$t['event']['instance_id'],$arrival??time()))throw new \RuntimeException('The rally must arrive during the current shrine event.',403);
        }
        return $t;
    }
    public static function validate(array $r,int $player,?int $arrival=null): array
    {
        $meta=json_decode($r['result_json']??'{}',true)?:[];
        return WorldContext::run((int)$r['world_id'],fn()=>self::target($player,(int)$meta['shrine_id'],$meta['event_instance']??null,$arrival));
    }
    public static function start(int $player,int $cityId,int $id,array $troops,int $minutes): int
    {
        WorldContext::assertActionAvailable();
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($player,$cityId,$id,$troops,$minutes):int{
            $city=WorldRules::origin($player,$cityId);$db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetch();
            if(!in_array($minutes,[1,5,15,30],true))throw new \RuntimeException('Choose 1, 5, 15 or 30 minutes.');
            $t=self::target($player,$id);MarchDispatcher::assertSlotAvailable($player);
            $troops=MarchArmy::clean($troops,ResearchEffects::limits(BuffEngine::getBuffs($player))['march_capacity']);
            $capacity=RallyCapacity::forCity($player,$cityId)['total'];if(array_sum($troops)>$capacity)throw new \RuntimeException('Rally capacity exceeded.');
            $skin=MarchSkinService::dispatchSnapshot($player);
            MarchArmy::reserve($db,$cityId,$troops);WorldRules::relinquishShield($player,$cityId);
            $meta=['alliance_id'=>WorldRules::alliance($player),'capacity'=>$capacity,'shrine_id'=>$id,'target_name'=>$t['name'],'element'=>$t['element']??null,'shrine_code'=>$t['shrine_code'],'event_instance'=>$t['event']['instance_id']??null];
            $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,march_skin,march_speed_bonus_pct,target_kind,target_x,target_y,rally_minutes,troops_json,message,result_json,status,launch_at) VALUES(?,?,?,?,?,'shrine',?,?,?,?,'',?,'gathering',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[$city['world_id'],$player,$cityId,$skin['march_skin'],$skin['bonus_pct'],$t['coord_x'],$t['coord_y'],$minutes,json_encode($troops),json_encode($meta),$minutes]);
            return $db->lastInsertId();
        }));
    }
    public static function resolve(array $r,array $armies): array
    {
        return WorldContext::run((int)$r['world_id'],fn()=>self::resolveInWorld($r,$armies));
    }
    private static function resolveInWorld(array $r,array $armies): array
    {
        $meta=json_decode($r['result_json']??'{}',true)?:[];$alliance=(int)$meta['alliance_id'];
        try{
            foreach($armies as $army)if(WorldRules::alliance((int)$army['player_id'])!==$alliance)throw new \RuntimeException('Alliance membership changed. The armies return.');
            $t=self::validate($r,(int)$r['leader_player_id'],strtotime($r['arrival_time'].' UTC'));
        }catch(\PDOException $e){throw $e;}
        catch(\RuntimeException|\DomainException $e){return ['cancelled'=>true,'reason'=>$e->getMessage(),'armies'=>$armies];}
        return CongressService::fightRally($r,$t,$armies,$alliance);
    }
}
