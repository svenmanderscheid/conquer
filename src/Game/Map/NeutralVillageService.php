<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\March\PvpRules;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** Neutral settlements; public map data intentionally excludes stock and garrison. */
final class NeutralVillageService
{
    public const TARGET_TYPE=6;
    public const MARCH_ATTACK=11;
    public const MARCH_SCOUT=12;

    public static function mapRows(int $worldId,int $x,int $y,int $radius): array
    {
        return Connection::getInstance()->query('SELECT id,coord_x,coord_y,level,name FROM neutral_villages WHERE world_id=? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? ORDER BY POW(coord_x-?,2)+POW(coord_y-?,2) LIMIT 80',[$worldId,max(0,$x-$radius),$x+$radius,max(0,$y-$radius),$y+$radius,$x,$y])->fetchAll();
    }
    public static function findAt(Connection $db,int $worldId,int $x,int $y,bool $lock=false): array
    {
        $row=$db->query('SELECT * FROM neutral_villages WHERE world_id=? AND coord_x=? AND coord_y=?'.($lock?' FOR UPDATE':''),[$worldId,$x,$y])->fetch();
        if(!$row)throw new \RuntimeException('Das freie Dorf ist nicht mehr an diesem Ort.');
        return $row;
    }
    public static function garrison(int $level): array
    {
        $tier=max(1,min(TroopData::MAX_TIER,(int)ceil($level/2)));$count=(int)round(170*pow(1.72,$level-1));$rows=[];
        foreach(TroopData::all() as $code=>$def)if((int)$def['tier']===$tier)$rows[$code]=$count;
        return $rows;
    }
    public static function initialStock(int $level): array
    {
        $base=(int)round(14000*pow(1.62,$level-1));
        return ['food'=>$base,'lumber'=>(int)round($base*.9),'stone'=>(int)round($base*.65),'gold'=>(int)round($base*.42)];
    }
    public static function sync(Connection $db,array $village): array
    {
        $elapsed=max(0,time()-strtotime($village['last_production_at'].' UTC'));$level=(int)$village['level'];$cap=self::initialStock($level);$hourly=(int)round(1800*pow(1.48,$level-1));$weights=['food'=>1.0,'lumber'=>.9,'stone'=>.65,'gold'=>.42];$next=[];
        foreach($weights as $key=>$weight)$next[$key]=min($cap[$key],(int)$village[$key]+(int)floor($hourly*$weight*$elapsed/3600));
        $db->execute('UPDATE neutral_villages SET food=?,lumber=?,stone=?,gold=?,last_production_at=UTC_TIMESTAMP() WHERE id=?',[...array_values($next),(int)$village['id']]);
        return array_replace($village,$next);
    }
    public static function scout(Connection $db,array $march): void
    {
        $v=self::findAt($db,(int)$march['world_id'],(int)$march['target_x'],(int)$march['target_y'],true);
        if((int)$v['id']!==(int)$march['target_id'])throw new \RuntimeException('Das freie Dorf wurde ersetzt.');
        $data=['type'=>'neutral_village_scout','observed_at'=>gmdate('Y-m-d H:i:s'),'target_name'=>$v['name'],'village_level'=>(int)$v['level'],'troops'=>array_map('intval',json_decode($v['garrison_json'],true)?:[]),'resources_hidden'=>true];
        $db->execute("INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json,attacker_read,created_at) VALUES(?,?,?,?,6,?,?,?,'scouted',?,0,UTC_TIMESTAMP())",[(int)$march['world_id'],(int)$march['id'],(int)$march['player_id'],(int)$march['origin_city_id'],(int)$v['id'],(int)$v['coord_x'],(int)$v['coord_y'],json_encode($data,JSON_THROW_ON_ERROR)]);
        self::returnMarch($db,$march,[],[]);
    }
    public static function attack(Connection $db,array $march,array $troops): void
    {
        $v=self::sync($db,self::findAt($db,(int)$march['world_id'],(int)$march['target_x'],(int)$march['target_y'],true));
        if((int)$v['id']!==(int)$march['target_id'])throw new \RuntimeException('Das freie Dorf wurde ersetzt.');
        $garrison=array_map('intval',json_decode($v['garrison_json'],true)?:[]);$buffs=ResearchEffects::armyBuffs(BuffEngine::getBuffs((int)$march['player_id'],(int)$march['world_id']),$troops,false);
        $attack=0.0;foreach($troops as $code=>$count)$attack+=PvpRules::strength((int)$code,(int)$count,$buffs);
        $defense=0.0;foreach($garrison as $code=>$count)$defense+=PvpRules::strength((int)$code,(int)$count,[]);
        $wins=PvpRules::attackerWins($attack,$defense*1.1);$loss=PvpRules::losses($troops,$wins?.10:.30);HospitalService::addWounded((int)$march['origin_city_id'],$loss['wounded']);
        $capacity=0;foreach($loss['survivors'] as $code=>$count)$capacity+=(int)$count*ResearchEffects::carryPerTroop((int)$code,$buffs);
        $loot=['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];if($wins)foreach(array_keys($loot) as $key){$loot[$key]=min($capacity,(int)$v[$key]);$capacity-=$loot[$key];}
        $outcome=$wins?'attacker_wins':'defender_wins';$report=['battle_kind'=>'neutral_village','target_name'=>$v['name'],'village_level'=>(int)$v['level'],'outcome'=>$outcome,'loot'=>$loot,'dead'=>$loss['dead'],'wounded'=>$loss['wounded'],'troops'=>[],'resources_hidden_before_battle'=>true];
        foreach($troops as $code=>$count)$report['troops'][]=['code'=>(int)$code,'sent'=>(int)$count,'survived'=>$loss['survivors'][$code]??0,'injured'=>$loss['wounded'][$code]??0,'dead'=>$loss['dead'][$code]??0];
        $db->execute("INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json,attacker_read,created_at) VALUES(?,?,?,?,6,?,?,?,?,?,0,UTC_TIMESTAMP())",[(int)$march['world_id'],(int)$march['id'],(int)$march['player_id'],(int)$march['origin_city_id'],(int)$v['id'],(int)$v['coord_x'],(int)$v['coord_y'],$outcome,json_encode($report,JSON_THROW_ON_ERROR)]);
        if($wins)$db->execute('UPDATE neutral_villages SET food=food-?,lumber=lumber-?,stone=stone-?,gold=gold-?,last_attacked_at=UTC_TIMESTAMP() WHERE id=?',[$loot['food'],$loot['lumber'],$loot['stone'],$loot['gold'],(int)$v['id']]);
        self::returnMarch($db,$march,$loss['survivors'],$loot);
    }
    private static function returnMarch(Connection $db,array $march,array $survivors,array $loot): void
    {
        $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(2,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>$survivors,'loot'=>$loot],JSON_THROW_ON_ERROR),(int)$march['id']]);
    }
}
