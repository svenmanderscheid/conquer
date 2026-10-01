<?php
declare(strict_types=1);
namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\World\WorldSettings;
use Conquer\Game\WorldRules;
use Conquer\Game\World\WorldContext;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSkinService,MarchSpeed};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** Placement and non-stacking area effects for alliance buildings. */
final class AllianceTerritoryService
{
    private const ROLES=['member'=>1,'veteran'=>2,'officer'=>3,'vice_leader'=>4,'leader'=>5];

    public static function worldStructures(int $worldId,?int $playerId=null): array
    {
        $settings=WorldSettings::get($worldId)['settings'];
        $db=Connection::getInstance();
        try{$rows=$db->query('SELECT s.*,a.name AS alliance_name,a.tag AS alliance_tag FROM alliance_structures s JOIN alliances a ON a.id=s.alliance_id WHERE s.world_id=? ORDER BY s.id',[$worldId])->fetchAll();}catch(\PDOException){return [];}
        $garrisons=[];try{foreach($db->query('SELECT g.structure_id,g.troops_json FROM alliance_structure_garrisons g JOIN alliance_structures s ON s.id=g.structure_id WHERE s.world_id=?',[$worldId])->fetchAll()as$g){$troops=json_decode($g['troops_json'],true)?:[];$garrisons[(int)$g['structure_id']]=($garrisons[(int)$g['structure_id']]??0)+array_sum(array_map('intval',$troops));}}catch(\PDOException){}
        $viewer=$playerId?WorldRules::alliance($playerId):null;
        foreach($rows as&$row){$row['radius']=(int)$settings[$row['structure_type']==='center'?'alliance_center_radius':'alliance_outpost_radius'];$row['name']=$row['structure_type']==='center'?'Allianzzentrum':'Außenposten';$row['garrison_total']=$garrisons[(int)$row['id']]??0;$row['can_garrison']=$row['structure_type']==='center'&&$viewer!==null&&(int)$row['alliance_id']===$viewer;}unset($row);
        return $rows;
    }

    public static function dispatchGarrison(int $playerId,int $worldId,int $structureId,mixed $input): array
    {
        try{return WorldRules::combatLock(function()use($playerId,$worldId,$structureId,$input):array{return Connection::getInstance()->transaction(function(Connection $db)use($playerId,$worldId,$structureId,$input):array{
            WorldContext::assertActionAvailable();
            $city=$db->query('SELECT * FROM cities WHERE player_id=? AND world_id=? ORDER BY id LIMIT 1 FOR UPDATE',[$playerId,$worldId])->fetch();if(!$city)throw new \DomainException('Keine eigene Stadt gefunden.');
            $structure=$db->query("SELECT * FROM alliance_structures WHERE id=? AND world_id=? AND structure_type='center' FOR UPDATE",[$structureId,$worldId])->fetch();if(!$structure)throw new \DomainException('Allianzzentrum nicht gefunden.',404);
            $alliance=WorldRules::alliance($playerId);if($alliance===null||(int)$structure['alliance_id']!==$alliance)throw new \DomainException('Nur Mitglieder dieser Allianz dürfen das Zentrum verteidigen.',403);
            $buffs=BuffEngine::getBuffs($playerId,$worldId);$troops=MarchArmy::clean($input,ResearchEffects::limits($buffs)['march_capacity']);MarchDispatcher::assertSlotAvailable($playerId,$worldId);MarchArmy::reserve($db,(int)$city['id'],$troops);
            $skin=MarchSkinService::dispatchSnapshot($playerId);$speed=PHP_INT_MAX;foreach($troops as$code=>$count)$speed=min($speed,MarchSpeed::generic((int)$code,$buffs,MarchSkinService::speedMultiplier($skin)));
            $seconds=max(5,(int)floor(hypot((int)$city['coord_x']-(int)$structure['coord_x'],(int)$city['coord_y']-(int)$structure['coord_y'])*100/max(1,$speed)));
            $db->execute("INSERT INTO marches(player_id,world_id,march_type,march_skin,march_speed_bonus_pct,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching')",[$playerId,$worldId,16,$skin['march_skin'],$skin['bonus_pct'],$city['id'],$structure['coord_x'],$structure['coord_y'],6,$structureId,json_encode($troops,JSON_THROW_ON_ERROR),$seconds]);
            return ['message'=>'Die Verteidigung ist zum Allianzzentrum unterwegs.','march_id'=>$db->lastInsertId()];
        });});}catch(\RuntimeException $e){throw new \DomainException($e->getMessage(),$e->getCode(),$e);}
    }

    public static function resolveGarrison(int $marchId): void
    {
        $db=Connection::getInstance();$db->transaction(function(Connection $db)use($marchId):void{$march=$db->query("SELECT * FROM marches WHERE id=? AND march_type=16 FOR UPDATE",[$marchId])->fetch();if(!$march||$march['state']!=='marching')return;$structure=$db->query("SELECT * FROM alliance_structures WHERE id=? AND world_id=? AND structure_type='center' FOR UPDATE",[$march['target_id'],$march['world_id']])->fetch();$alliance=WorldRules::alliance((int)$march['player_id']);$troops=json_decode($march['troops_json'],true)?:[];
            if(!$structure||$alliance===null||(int)$structure['alliance_id']!==$alliance){foreach($troops as$code=>$count)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$march['origin_city_id'],\Conquer\Game\City\TroopData::activeCode((int)$code),(int)$count]);$db->execute("UPDATE marches SET state='complete' WHERE id=?",[$marchId]);return;}
            $old=$db->query('SELECT troops_json FROM alliance_structure_garrisons WHERE structure_id=? AND player_id=? FOR UPDATE',[$structure['id'],$march['player_id']])->fetchColumn();$combined=is_string($old)?json_decode($old,true):[];foreach($troops as$code=>$count)$combined[(int)$code]=(int)($combined[(int)$code]??0)+(int)$count;ksort($combined);
            $db->execute('INSERT INTO alliance_structure_garrisons(structure_id,alliance_id,player_id,city_id,troops_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE alliance_id=VALUES(alliance_id),city_id=VALUES(city_id),troops_json=VALUES(troops_json),sent_at=UTC_TIMESTAMP()',[$structure['id'],$alliance,$march['player_id'],$march['origin_city_id'],json_encode($combined,JSON_THROW_ON_ERROR)]);$db->execute("UPDATE marches SET state='complete',haul_json=? WHERE id=?",[json_encode(['garrisoned'=>$troops],JSON_THROW_ON_ERROR),$marchId]);
        });
    }

    public static function allianceState(int $allianceId,int $worldId): array
    {
        $settings=WorldSettings::get($worldId)['settings'];$structures=[];
        try{$structures=Connection::getInstance()->query('SELECT id,structure_type,coord_x,coord_y,created_at FROM alliance_structures WHERE alliance_id=? AND world_id=? ORDER BY structure_type,id',[$allianceId,$worldId])->fetchAll();}catch(\PDOException){}
        $level=(int)(Connection::getInstance()->query("SELECT level FROM alliance_research WHERE alliance_id=? AND research_code='ally_outpost_capacity'",[$allianceId])->fetchColumn()?:0);
        return ['structures'=>$structures,'center_radius'=>(int)$settings['alliance_center_radius'],'outpost_radius'=>(int)$settings['alliance_outpost_radius'],'outpost_limit'=>1+intdiv(min(AllianceResearchService::MAX_LEVEL,$level),5),'outpost_count'=>count(array_filter($structures,static fn($s)=>$s['structure_type']==='outpost'))];
    }

    public static function place(int $playerId,int $worldId,string $type,int $x,int $y): array
    {
        if(!in_array($type,['center','outpost'],true))throw new \DomainException('Unbekannter Allianzgebäudetyp.');
        $db=Connection::getInstance();$member=$db->query('SELECT m.alliance_id,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=? WHERE m.player_id=?',[$worldId,$playerId])->fetch();
        if(!$member)throw new \DomainException('Du gehörst in dieser Welt keiner Allianz an.');
        if((self::ROLES[$member['role']]??0)<3)throw new \DomainException('Nur Offiziere und die Allianzführung dürfen Allianzgebäude setzen.');
        $aid=(int)$member['alliance_id'];
        WorldPlacement::lockWorld($db,$worldId);
        $existing=$db->query('SELECT structure_type,COUNT(*) AS amount FROM alliance_structures WHERE alliance_id=? AND world_id=? GROUP BY structure_type FOR UPDATE',[$aid,$worldId])->fetchAll(\PDO::FETCH_KEY_PAIR);
        if($type==='center'&&($existing['center']??0)>0)throw new \DomainException('Diese Allianz besitzt bereits ein Allianzzentrum.');
        if($type==='outpost'){
            if(($existing['center']??0)<1)throw new \DomainException('Errichtet zuerst ein Allianzzentrum.');
            $state=self::allianceState($aid,$worldId);if(($existing['outpost']??0)>=$state['outpost_limit'])throw new \DomainException('Das Außenposten-Limit ist erreicht. Erforsche Grenzverwaltung.');
        }
        $kind=$type==='center'?'alliance_center':'outpost';
        if(!WorldPlacement::canPlace($db,$worldId,$kind,$x,$y))throw new \DomainException('An dieser Position ist nicht genügend freier, zugänglicher Boden.');
        $db->execute('INSERT INTO alliance_structures(alliance_id,world_id,structure_type,coord_x,coord_y,placed_by)VALUES(?,?,?,?,?,?)',[$aid,$worldId,$type,$x,$y,$playerId]);
        return ['message'=>($type==='center'?'Allianzzentrum':'Außenposten').' errichtet.','id'=>$db->lastInsertId()];
    }

    /** Area effects use the strongest matching building of each type, never multiple outposts. */
    public static function bonusesAt(int $playerId,int $worldId,?int $x=null,?int $y=null): array
    {
        $db=Connection::getInstance();$member=$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetchColumn();if($member===false)return [];
        if($x===null||$y===null){$city=$db->query('SELECT coord_x,coord_y FROM cities WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetch();if(!$city)return [];$x=(int)$city['coord_x'];$y=(int)$city['coord_y'];}
        $inside=['center'=>false,'outpost'=>false];
        foreach(self::worldStructures($worldId) as$s)if((int)$s['alliance_id']===(int)$member&&max(abs($x-(int)$s['coord_x']),abs($y-(int)$s['coord_y']))<=(int)$s['radius'])$inside[$s['structure_type']]=true;
        $result=[];
        if($inside['center'])$result=['troops_atk'=>.05,'troops_def'=>.05,'food_production'=>.10,'lumber_production'=>.10,'stone_production'=>.10,'gold_production'=>.10,'gathering_speed'=>.10];
        if($inside['outpost'])foreach(['troops_atk','troops_def','troops_hp']as$key)$result[$key]=($result[$key]??0)+.05;
        return $result;
    }
}
