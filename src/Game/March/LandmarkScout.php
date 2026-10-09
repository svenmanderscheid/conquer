<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\World\{WorldContext,LandAccessPolicy};
use Conquer\Game\Territory\TerritoryService;
use Conquer\Game\Shrine\CongressService;

/** Real reconnaissance: no troops or rewards, intelligence recorded only at arrival. */
final class LandmarkScout
{
    private static function target(int $player,int $world,string $kind,string $id): array
    {
        if($kind==='territory'){
            $t=TerritoryService::detail($player,$world,$id);
            if(!$t['active'])throw new \DomainException('This territory is not open yet.',403);
            return $t;
        }
        if($kind!=='shrine'||!ctype_digit($id)||(int)$id<1)throw new \DomainException('Invalid scouting target.',400);
        $t=WorldContext::run($world,fn()=>CongressService::detail((int)$id,$player));
        if(!$t)throw new \DomainException('Scouting target not found.',404);
        return $t+['x'=>$t['coord_x'],'y'=>$t['coord_y']];
    }
    public static function dispatch(int $player,int $cityId,string $kind,string $id): array
    {
        WorldContext::assertActionAvailable();
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($player,$cityId,$kind,$id):array{
            $city=WorldRules::origin($player,$cityId);$world=(int)$city['world_id'];
            $db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetch();
            $t=self::target($player,$world,$kind,$id);LandAccessPolicy::assertTargetOpen($world,(int)$t['x'],(int)$t['y']);
            MarchDispatcher::assertSlotAvailable($player);
            $seconds=MarchSpeed::duration(hypot($t['x']-$city['coord_x'],$t['y']-$city['coord_y']),100,$world,2);
            $meta=['landmark_kind'=>$kind,'landmark_id'=>$id];
            $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state,haul_json) VALUES(?,?,8,?,?,?,?,?,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching',?)",[$player,$world,$cityId,$t['x'],$t['y'],$kind==='territory'?5:4,$kind==='territory'?0:(int)$id,$seconds,json_encode($meta,JSON_THROW_ON_ERROR)]);
            return ['march_id'=>$db->lastInsertId()];
        }));
    }
    public static function resolve(array $march): void
    {
        WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($march):void{
            $m=$db->query("SELECT * FROM marches WHERE id=? AND state='marching' AND arrival_time<=UTC_TIMESTAMP() FOR UPDATE",[$march['id']])->fetch();
            if(!$m)return;
            $meta=json_decode($m['haul_json']??'{}',true)?:[];$kind=(string)($meta['landmark_kind']??'');$id=(string)($meta['landmark_id']??'');$reason=null;
            try{
                $t=self::target((int)$m['player_id'],(int)$m['world_id'],$kind,$id);
                if((int)$t['x']!==(int)$m['target_x']||(int)$t['y']!==(int)$m['target_y'])throw new \DomainException('The scouting target has moved.');
                LandAccessPolicy::assertTargetOpen((int)$m['world_id'],(int)$t['x'],(int)$t['y']);
                $troops=[];$npc=0;
                if($kind==='territory'){
                    $npc=(!$t['owner_alliance_id']||$t['kind']==='crown')?(int)$t['profile']['npc_troops'][$t['kind']]:0;
                    foreach($db->query("SELECT troops_json FROM territory_garrisons WHERE world_id=? AND target_id=? AND status='active'",[$m['world_id'],$id])->fetchAll() as $g)foreach(json_decode($g['troops_json'],true)?:[] as $code=>$count)$troops[$code]=($troops[$code]??0)+(int)$count;
                }else $troops=$t['garrison_troops'];
                $data=['type'=>'landmark_scout','observed_at'=>gmdate('Y-m-d H:i:s'),'target_name'=>$t['name'],'landmark_kind'=>$kind,'landmark_id'=>$id,'target'=>$kind==='territory'?array_intersect_key($t,array_flip(['kind','benefit_type','id','name'])):['kind'=>'shrine','element'=>$t['element']??null,'shrine_code'=>$t['shrine_code']],
                    'troops'=>$troops,'npc_troops'=>$npc,'fortification'=>(int)($t['fortification']??0),'owner_name'=>$t['owner_name']??$t['alliance_name']??null];
                $db->execute("INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at) VALUES(?,?,?,?,?,?,?,?,'scouted',?,UTC_TIMESTAMP())",[$m['world_id'],$m['id'],$m['player_id'],$m['origin_city_id'],$m['target_type'],$m['target_id'],$m['target_x'],$m['target_y'],json_encode($data,JSON_THROW_ON_ERROR)]);
            }catch(\PDOException $e){throw $e;}
            catch(\DomainException|\RuntimeException $e){$reason=$e->getMessage();}
            $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(2,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>[],'loot'=>[],'reason'=>$reason]),$m['id']]);
        }));
    }
}
