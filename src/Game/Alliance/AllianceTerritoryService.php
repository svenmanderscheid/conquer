<?php
declare(strict_types=1);
namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\World\WorldSettings;

/** Placement and non-stacking area effects for alliance buildings. */
final class AllianceTerritoryService
{
    private const ROLES=['member'=>1,'veteran'=>2,'officer'=>3,'vice_leader'=>4,'leader'=>5];

    public static function worldStructures(int $worldId): array
    {
        $settings=WorldSettings::get($worldId)['settings'];
        try{$rows=Connection::getInstance()->query('SELECT s.*,a.name AS alliance_name,a.tag AS alliance_tag FROM alliance_structures s JOIN alliances a ON a.id=s.alliance_id WHERE s.world_id=? ORDER BY s.id',[$worldId])->fetchAll();}catch(\PDOException){return [];}
        foreach($rows as&$row){$row['radius']=(int)$settings[$row['structure_type']==='center'?'alliance_center_radius':'alliance_outpost_radius'];$row['name']=$row['structure_type']==='center'?'Allianzzentrum':'Außenposten';}unset($row);
        return $rows;
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
        foreach(self::worldStructures($worldId) as$s)if((int)$s['alliance_id']===(int)$member&&(($x-(int)$s['coord_x'])**2+($y-(int)$s['coord_y'])**2)<=((int)$s['radius'])**2)$inside[$s['structure_type']]=true;
        $result=[];
        if($inside['center'])$result=['troops_atk'=>.05,'troops_def'=>.05,'food_production'=>.10,'lumber_production'=>.10,'stone_production'=>.10,'gold_production'=>.10,'gathering_speed'=>.10];
        if($inside['outpost'])foreach(['troops_atk','troops_def','troops_hp']as$key)$result[$key]=($result[$key]??0)+.05;
        return $result;
    }
}
