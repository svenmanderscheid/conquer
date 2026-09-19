<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Game\Map\{MonsterData,WorldTerrain};
use Conquer\Game\Rewards\RewardCatalog;

/** Land development selects existing monsters; it never rewrites living targets. */
final class RegionalSpawns
{
    public static function land(int $worldId,int $x,int $y): ?array
    {
        return class_exists(LandProgressService::class) && LandProgressService::available()
            ? LandProgressService::at($worldId,$x,$y) : null;
    }

    public static function candidates(int $worldId,string $family,int $x,int $y,int $legacyMin=0,int $legacyMax=10,bool $keepEasy=false): array
    {
        if(in_array($family,['dragon','Magdar','Green Dragon','Gold Dragon','Red Dragon'],true))return [];
        $biome=WorldTerrain::biomeAt($x,$y);
        if($family==='Deathkar'||$family==='regional'){
            // The historical Deathkar weight now represents the complete rally-boss
            // pool. Dämmerhorn appears across the world; the other four remain native
            // to their biome. Coordinate selection keeps placement deterministic.
            $family=(($x*31+$y*17)%5===0)?'Dämmerhorn':match($biome){'forest'=>'Grumwald','ice'=>'Frostgrimm','sand'=>'Sandmaul','lava'=>'Glutramm'};
        }
        $land=self::land($worldId,$x,$y);
        if($land&&!$land['open'])return [];
        $level=(int)($land['level']??1);
        $min=$land?($keepEasy?1:max(1,$level-2)):$legacyMin;
        $max=$land?min(10,$level+1):$legacyMax;
        $available=[];
        foreach(RewardCatalog::json('monsters')['monsters'] as $raw){
            $code=(int)$raw['code'];
            if(!MonsterData::isActive($code))continue;
            $def=MonsterData::definition($code);
            if($def['name']!==$family||(isset($def['biome'])&&$def['biome']!==$biome))continue;
            $monsterLevel=(int)$def['level'];
            if($monsterLevel>$max)continue;
            $available[$monsterLevel]??=['code'=>$code,'level'=>$monsterLevel,'monster'=>$def['name']];
        }
        $candidates=array_values(array_filter($available,static fn($entry)=>$entry['level']>=$min));
        // Short catalogs, notably goblins, retain their strongest existing tier.
        if(!$candidates&&$land&&$available){ksort($available);$candidates=[end($available)];}
        return $candidates;
    }

    public static function availableMonsters(int $worldId,int $x,int $y): array
    {
        $families=['Orc','Skeleton','Golem','Treasure Goblin','regional'];$out=[];
        $weights=WorldSettings::get($worldId)['settings']['monster_weights'];
        foreach($families as $family){
          if((int)($weights[$family==='regional'?'Deathkar':$family]??0)===0)continue;
          // The world worker retains occasional easy targets for new arrivals.
          foreach(self::candidates($worldId,$family,$x,$y,keepEasy:true) as $candidate){
            $def=WorldContext::run($worldId,static fn()=>MonsterData::get($candidate['code']));
            $drops=array_map(static function(array $drop):array{
                $item=\Conquer\Game\Inventory\InventoryService::getItemDef((int)$drop['item_code']);
                return $drop+['label'=>$item['name']??('Gegenstand '.$drop['item_code'])];
            },$def['drops']??[]);
            $out[]=['code'=>$candidate['code'],'name'=>$def['title']??$def['name'],'level'=>$candidate['level'],
                'type'=>$def['type']??'solo','biome'=>$def['biome']??null,'art'=>$def['art']??null,
                'drops'=>$drops,'resource_reward'=>$def['resource_reward']??[],
                'gems_drop'=>$def['gems_drop']??['chance'=>0,'amount'=>0],'guaranteed_charms'=>1];
        }
        }
        return $out;
    }

    public static function stamp(string $table,int $id,int $worldId,int $x,int $y): void
    {
        if(!in_array($table,['field_monsters','field_objects'],true))throw new \LogicException('Unknown spawn table.');
        $land=self::land($worldId,$x,$y);if(!$land)return;
        \Conquer\Db\Connection::getInstance()->execute('UPDATE '.$table.' SET land_part_id=?,regional_level_at_spawn=?,spawn_rule_revision=? WHERE id=? AND world_id=?',
            [$land['id'],$land['level'],$land['rule_revision']??1,$id,$worldId]);
        if($table==='field_monsters'){
            $db=\Conquer\Db\Connection::getInstance();$code=(int)$db->query('SELECT monster_code FROM field_monsters WHERE id=? AND world_id=?',[$id,$worldId])->fetchColumn();
            $level=(int)MonsterData::definition($code)['level'];
            $points=max(1,$level)*(int)LandRules::get($worldId)['monster_points_per_level'];
            $db->execute('UPDATE field_monsters SET effective_monster_level=?,regional_point_value=? WHERE id=? AND world_id=?',[$level,$points,$id,$worldId]);
        }
    }
}
