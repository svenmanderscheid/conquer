<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

use Conquer\Db\Connection;
use Conquer\Game\World\{LandAccessPolicy,WorldContext};

/** Authenticated world-wide discovery. Combat and gathering remain separate actions. */
final class MapSearchService
{
    private const RESOURCES=['food'=>1,'lumber'=>2,'stone'=>3,'gold'=>4,'gems'=>5];

    private static function monsterCodes(): array
    {
        $root=dirname(__DIR__,3);
        $definitions=json_decode(file_get_contents($root.'/data/monsters.json'),true,512,JSON_THROW_ON_ERROR);
        $spawns=json_decode(file_get_contents($root.'/data/world_spawn.json'),true,512,JSON_THROW_ON_ERROR);
        return array_unique(array_merge(array_column($definitions['monsters'],'code'),array_column($spawns['monsters'],'code')));
    }

    public static function catalog(): array
    {
        $levels=['solo'=>[],'rally'=>[]];
        foreach(self::monsterCodes() as $code){
            if(!MonsterData::isActive((int)$code))continue;
            $def=MonsterData::definition((int)$code);
            if(isset($levels[$def['type']]))$levels[$def['type']][]=max(1,(int)$def['level']);
        }
        $spawn=json_decode(file_get_contents(dirname(__DIR__,3).'/data/world_spawn.json'),true,512,JSON_THROW_ON_ERROR);
        $types=['farm'=>'food','forest'=>'lumber','quarry'=>'stone','gold_mine'=>'gold','gem_node'=>'gems'];
        foreach(self::RESOURCES as $key=>$type)$levels[$key]=range(1,10);
        foreach($spawn['field_objects'] as $entry){$key=$types[$entry['type']]??null;if($key)$levels[$key][]=(int)$entry['level'];}
        foreach($levels as &$values){$values=array_values(array_unique($values));sort($values,SORT_NUMERIC);}unset($values);
        return $levels;
    }

    public static function search(int $playerId,array $query): array
    {
        $city=WorldContext::city($playerId);$worldId=(int)$city['world_id'];
        $catalog=self::catalog();
        if(!isset($query['category']))return ['categories'=>$catalog];
        $category=$query['category'];$rawLevel=$query['level']??null;
        if(!is_string($category)||!isset($catalog[$category])||!is_scalar($rawLevel))throw new \DomainException('Bitte wähle einen Objekttyp und eine gültige Stufe.');
        $level=filter_var($rawLevel,FILTER_VALIDATE_INT);
        if($level===false||!in_array($level,$catalog[$category],true))throw new \DomainException('Diese Stufe ist für den Objekttyp nicht verfügbar.');
        $scope=[$worldId,$category,$level,(int)$city['id'],(int)$city['coord_x'],(int)$city['coord_y']];
        $cursor=null;
        if(isset($query['cursor'])){
            $encoded=$query['cursor'];
            if(!is_string($encoded)||strlen($encoded)>1024)throw new \DomainException('Ungültige Suchfortsetzung.');
            $decoded=base64_decode($encoded,true);
            try{$value=$decoded===false?null:json_decode($decoded,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){$value=null;}
            if(!is_array($value)||!array_is_list($value)||count($value)!==8||!is_int($value[6])||$value[6]<0||$value[6]>2147483647||!is_int($value[7])||$value[7]<1)throw new \DomainException('Ungültige Suchfortsetzung.');
            // A changed world, city position, category or level starts a fresh search.
            if(array_slice($value,0,6)===$scope)$cursor=$value;
        }
        $db=Connection::getInstance();$monster=in_array($category,['solo','rally'],true);
        $params=[$worldId];
        if($monster){
            $codes=array_values(array_filter(self::monsterCodes(),static function($code)use($category,$level):bool{
                $def=MonsterData::definition((int)$code);
                return MonsterData::isActive((int)$code)&&$def['type']===$category&&max(1,(int)$def['level'])===$level;
            }));
            $sql='SELECT id,monster_code,coord_x,coord_y,hp_current FROM field_monsters WHERE world_id=? AND hp_current>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) AND monster_code IN ('.implode(',',array_fill(0,count($codes),'?')).')';
            $params=array_merge($params,$codes);
        }else{
            $sql='SELECT id,coord_x,coord_y,object_type,level,resource_amount,gatherer_march_id FROM field_objects WHERE world_id=? AND object_type=? AND level=? AND resource_amount>0 AND expires_at>UTC_TIMESTAMP() AND gatherer_march_id IS NULL';
            $params[]=self::RESOURCES[$category];$params[]=$level;
        }
        $sql='SELECT candidates.*, POW(coord_x-?,2)+POW(coord_y-?,2) AS search_distance FROM ('.$sql.') candidates';
        $params=array_merge([(int)$city['coord_x'],(int)$city['coord_y']],$params);
        if($cursor){$sql.=' HAVING search_distance>? OR (search_distance=? AND id>?)';$params=array_merge($params,[$cursor[6],$cursor[6],$cursor[7]]);}
        $sql.=' ORDER BY search_distance,id';
        // Page candidates so a locked zone cannot hide the nearest open target.
        for($offset=0;;$offset+=100){
            $rows=$db->query($sql.' LIMIT 100 OFFSET '.$offset,$params)->fetchAll();
            foreach($rows as $row){
                if(!LandAccessPolicy::isOpen($worldId,(int)$row['coord_x'],(int)$row['coord_y']))continue;
                if($monster)$row=MonsterData::mapData($row);
                else{
                    $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($playerId,$worldId);
                    $factor=(float)$db->query('SELECT gather_factor FROM worlds WHERE id=?',[$worldId])->fetchColumn();
                    $row['gather_rate']=\Conquer\Game\March\GatherService::rate(FieldObjectService::RESOURCE_BY_TYPE[(int)$row['object_type']],(int)$row['level'],$buffs,$factor);
                }
                $next=base64_encode(json_encode([...$scope,(int)$row['search_distance'],(int)$row['id']],JSON_THROW_ON_ERROR));unset($row['search_distance']);
                return ['target'=>['kind'=>$monster?'monsters':'nodes','data'=>$row],'world_id'=>$worldId,'cursor'=>$next,'wrapped'=>false];
            }
            if(count($rows)<100){
                if($cursor){$first=self::search($playerId,['category'=>$category,'level'=>$level]);$first['wrapped']=$first['target']!==null;return $first;}
                return ['target'=>null,'world_id'=>$worldId,'cursor'=>null,'wrapped'=>false];
            }
        }
    }
}
