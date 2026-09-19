<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Map\FieldObjectService;
use Conquer\Game\Player\TalentEffects;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\World\WorldContext;
use Conquer\Game\WorldRules;
use Conquer\Logger;

/** Travel, timed gathering at the node, then return with an exactly-once haul. */
final class GatherService
{
    public const FIELD_ATTACK = 15;

    /** Bring visible fields up to date without relying on the occupying player being online. */
    public static function refreshNodes(int $worldId,array $ids): void
    {
        $ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids)return;
        $due=Connection::getInstance()->query("SELECT DISTINCT target_id FROM marches WHERE world_id=? AND target_id IN (".implode(',',$ids).") AND target_type=5 AND ((march_type IN (9,15) AND state='marching' AND arrival_time<=UTC_TIMESTAMP()) OR (march_type=9 AND state='arrived' AND gathering_finishes_at<=UTC_TIMESTAMP()))",[$worldId])->fetchAll(\PDO::FETCH_COLUMN);
        if($due)self::atomic(function()use($worldId,$due):void{foreach($due as $id)self::settleNode($worldId,(int)$id);});
    }

    /** Resources per second; deliberately separate from travel speed. */
    public static function rate(string $resource,int $level,array $buffs,float $worldFactor=1): float
    {
        $key=($resource==='gems'?'crystal':$resource).'_gathering_speed';
        $base=\Conquer\Game\Map\FieldObjectData::hourlyRate($resource, $level)/3600;
        return $base*max(.05,1+(float)($buffs['gathering_speed']??0)+(float)($buffs[$key]??0))*max(.01,$worldFactor);
    }

    public static function troopSpeed(int $code,array $buffs,float $worldFactor=1,bool $attack=false,float $skinMultiplier=1): float
    {
        return (float)(TroopData::get($code)['march_speed']??TroopData::get($code)['speed']??65)
            *max(.05,BuffEngine::effectiveMultiplier($buffs,ResearchEffects::troopType($code),'spd'))
            *max(.05,1+(float)($buffs['march_speed']??0)+(float)($buffs[$attack?'talent_pvp_march':'talent_gather_march']??0))*max(.01,$worldFactor)*max(.01,$skinMultiplier);
    }

    public static function dispatch(int $playerId,int $cityId,int $targetX,int $targetY,int $troopCount=0,?array $selectedTroops=null,bool $attack=false): array
    {
        $origin=WorldRules::origin($playerId,$cityId);$worldId=(int)$origin['world_id'];WorldContext::assertActionAvailable($worldId);
        \Conquer\Game\World\LandAccessPolicy::assertTargetOpen($worldId,$targetX,$targetY);
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($playerId,$cityId,$targetX,$targetY,$troopCount,$selectedTroops,$worldId,$attack):array{
            $city=WorldContext::city($playerId,$worldId,true);
            $buffs=BuffEngine::getBuffs($playerId,$worldId,$targetX,$targetY);$cap=ResearchEffects::limits($buffs)['march_capacity'];
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            MarchDispatcher::assertSlotAvailable($playerId,null,!$attack);
            $obj=$db->query('SELECT * FROM field_objects WHERE world_id=? AND coord_x=? AND coord_y=? AND (expires_at>UTC_TIMESTAMP() OR gatherer_march_id IS NOT NULL) FOR UPDATE',[$worldId,$targetX,$targetY])->fetch();
            if(!$obj)throw new \RuntimeException('Hier ist kein Ressourcenfeld mehr verfügbar.');
            self::settleNode($worldId,(int)$obj['id']);
            $obj=$db->query('SELECT * FROM field_objects WHERE id=? FOR UPDATE',[$obj['id']])->fetch();
            $occupant=self::occupant($obj);
            if((int)$obj['resource_amount']<=0)throw new \RuntimeException('Das Ressourcenfeld ist erschöpft.');
            if($attack){
                if(!$occupant||!self::canAttack($playerId,(int)$occupant['player_id'],$worldId))throw new \RuntimeException('Nur besetzte Felder von Spielern außerhalb deiner Allianz sind angreifbar.');
            }elseif($occupant)throw new \RuntimeException('Das Ressourcenfeld ist belegt. Wähle für feindliche Sammler die Aktion Angreifen.');
            $selected=$selectedTroops;
            if($selected===null){
                if($troopCount<1||$troopCount>$cap)throw new \RuntimeException('Ungültige Truppenanzahl.');
                $selected=[];$remaining=$troopCount;
                foreach($db->query('SELECT troop_code,count FROM city_troops WHERE city_id=? ORDER BY troop_code FOR UPDATE',[$cityId])->fetchAll() as $row){
                    $take=min($remaining,max(0,(int)$row['count']));if($take>0){$selected[(int)$row['troop_code']]=$take;$remaining-=$take;}
                }
                if($remaining>0)throw new \RuntimeException('Nicht genug Truppen in deiner Stadt.');
            }
            $selected=MarchArmy::clean($selected,$cap);MarchArmy::reserve($db,$cityId,$selected);
            $world=$db->query('SELECT speed_factor,gather_factor FROM worlds WHERE id=?',[$worldId])->fetch();
            $speed=min(array_map(static fn($code)=>self::troopSpeed((int)$code,$buffs,(float)$world['speed_factor'],$attack),array_keys($selected)))
                * MarchSkinService::speedMultiplier($skinSnapshot);
            $distance=hypot($targetX-(int)$city['coord_x'],$targetY-(int)$city['coord_y']);
            $seconds=max(5,(int)floor($distance*100/$speed));
            $resource=FieldObjectService::RESOURCE_BY_TYPE[(int)$obj['object_type']];
            $snapshot=['gather'=>['rate'=>self::rate($resource,(int)$obj['level'],$buffs,(float)$world['gather_factor']),'capacity'=>ResearchEffects::carryCapacity($selected,TalentEffects::gather($buffs))]];
            if($attack){$snapshot['field_attack_march_id']=(int)$occupant['id'];WorldRules::relinquishShield($playerId,$cityId);}
            $db->execute("INSERT INTO marches(player_id,world_id,march_type,march_skin,march_speed_bonus_pct,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,state) VALUES(?,?,?,?,?,?,?,?,5,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching')",[$playerId,$worldId,$attack?self::FIELD_ATTACK:9,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$cityId,$targetX,$targetY,$obj['id'],json_encode($selected),json_encode($snapshot),$seconds]);
            $id=(int)$db->lastInsertId();
            return ['march_id'=>$id];
        }));
    }

    /** Called for inbound arrivals. Existing marches without a snapshot are upgraded here. */
    public static function resolveGather(Connection $db,Logger $log,int $marchId,int $playerId,int $cityId,int $targetX,int $targetY,int $objectId): void
    {
        self::atomic(function()use($db,$marchId,$playerId,$cityId):void{
            $march=$db->query('SELECT * FROM marches WHERE id=? AND player_id=? AND origin_city_id=? AND march_type IN (9,15)',[$marchId,$playerId,$cityId])->fetch();
            if($march)self::settleNode((int)$march['world_id'],(int)$march['target_id']);
        });
    }

    /** Process the whole field in event order, even when the later player's lazy tick runs first. Caller holds the combat lock and transaction. */
    private static function settleNode(int $worldId,int $objectId): void
    {
        $db=Connection::getInstance();
        $obj=$db->query('SELECT * FROM field_objects WHERE id=? AND world_id=? FOR UPDATE',[$objectId,$worldId])->fetch();
        // Older versions reserved a field at departure. Only an arrived army owns it now.
        if($obj&&!self::occupant($obj))$db->execute('UPDATE field_objects SET gatherer_march_id=NULL WHERE id=?',[$objectId]);
        $arrivals=$db->query("SELECT * FROM marches WHERE world_id=? AND target_type=5 AND target_id=? AND march_type IN (9,15) AND state='marching' AND arrival_time<=UTC_TIMESTAMP() ORDER BY arrival_time,id FOR UPDATE",[$worldId,$objectId])->fetchAll();
        foreach($arrivals as $march){
            $at=strtotime($march['arrival_time'].' UTC');
            $obj=$db->query('SELECT * FROM field_objects WHERE id=? AND world_id=? FOR UPDATE',[$objectId,$worldId])->fetch();
            $occupant=$obj?self::occupant($obj):false;
            if($occupant&&self::finishOne($occupant,$at)){
                $obj=$db->query('SELECT * FROM field_objects WHERE id=? FOR UPDATE',[$objectId])->fetch();$occupant=false;
            }
            if(!$obj||(int)$obj['coord_x']!==(int)$march['target_x']||(int)$obj['coord_y']!==(int)$march['target_y']||(int)$obj['resource_amount']<=0||(!$occupant&&strtotime($obj['expires_at'].' UTC')<=$at)){
                self::returnHome($march,[],$at,'field_unavailable');continue;
            }
            $haul=json_decode($march['haul_json']??'{}',true)?:[];
            if((int)$march['march_type']===self::FIELD_ATTACK){
                // The attack targets this particular occupation; a replacement owner is not ambushed.
                if(!$occupant||(int)($haul['field_attack_march_id']??0)!==(int)$occupant['id']||!self::canAttack((int)$march['player_id'],(int)$occupant['player_id'],$worldId)){
                    self::returnHome($march,[],$at,'target_changed');continue;
                }
                $battle=FieldCombat::resolve($march,$occupant);
                if(!$battle['won']){
                    self::returnHome($march,[],$at,'battle_lost');
                    self::resizeGathering($occupant,$obj,$at);continue;
                }
                self::finishOne($occupant,$at,true);
                $obj=$db->query('SELECT * FROM field_objects WHERE id=? FOR UPDATE',[$objectId])->fetch();
                $haul=json_decode($march['haul_json'],true)?:[];
                $db->execute('UPDATE marches SET march_type=9 WHERE id=?',[$march['id']]);
            }elseif($occupant){self::returnHome($march,[],$at,'field_occupied');continue;}
            if(!isset($haul['gather'])){
                $buffs=BuffEngine::getBuffs((int)$march['player_id'],(int)$march['world_id']);
                $factor=(float)$db->query('SELECT gather_factor FROM worlds WHERE id=?',[$march['world_id']])->fetchColumn();
                $resource=FieldObjectService::RESOURCE_BY_TYPE[(int)$obj['object_type']];
                $haul['gather']=['rate'=>self::rate($resource,(int)$obj['level'],$buffs,$factor),'capacity'=>ResearchEffects::carryCapacity(json_decode($march['troops_json'],true)?:[],TalentEffects::gather($buffs))];
            }
            $amount=min((int)$obj['resource_amount'],(int)$haul['gather']['capacity']);
            $seconds=max(1,(int)ceil($amount/max(.001,(float)$haul['gather']['rate'])));
            if($amount<=0){self::returnHome($march,[],$at,'field_depleted');continue;}
            $db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$march['id'],$objectId]);
            $db->execute("UPDATE marches SET state='arrived',haul_json=?,gathering_finishes_at=DATE_ADD(arrival_time,INTERVAL ? SECOND) WHERE id=?",[json_encode($haul),$seconds,$march['id']]);
        }
        $obj=$db->query('SELECT * FROM field_objects WHERE id=? AND world_id=? FOR UPDATE',[$objectId,$worldId])->fetch();
        if($obj&&($occupant=self::occupant($obj)))self::finishOne($occupant,time());
    }

    /** May run repeatedly or after an offline interval. Recall keeps only work already done. */
    public static function finish(int $marchId,bool $recall=false): bool
    {
        return self::atomic(function()use($marchId,$recall):bool{
            $db=Connection::getInstance();
            $march=$db->query("SELECT * FROM marches WHERE id=? AND march_type=9 AND state='arrived'",[$marchId])->fetch();
            if(!$march)return false;
            self::settleNode((int)$march['world_id'],(int)$march['target_id']);
            $march=$db->query("SELECT * FROM marches WHERE id=? AND state='arrived' FOR UPDATE",[$marchId])->fetch();
            return !$march||self::finishOne($march,time(),$recall);
        });
    }

    private static function finishOne(array $march,int $at,bool $recall=false): bool
    {
        $db=Connection::getInstance();
        $finish=strtotime($march['gathering_finishes_at'].' UTC');
        if(!$recall&&$finish>$at)return false;
        $end=min($at,$finish);$obj=self::node($march);$loot=[];$items=[];
        if($obj){
            $gather=(json_decode($march['haul_json'],true)?:[])['gather']??[];
            $elapsed=max(0,$end-strtotime($march['arrival_time'].' UTC'));
            $amount=min((int)$obj['resource_amount'],(int)($gather['capacity']??0),(int)floor($elapsed*(float)($gather['rate']??0)+1e-8));
            if($amount>0){
                $loot[FieldObjectService::RESOURCE_BY_TYPE[(int)$obj['object_type']]]=$amount;
                $db->execute('UPDATE field_objects SET resource_amount=resource_amount-? WHERE id=?',[$amount,$obj['id']]);
                // One drop roll when the field is exhausted, never on partial recalls.
                // The node lock and returning-state transition make this exactly once.
                if ($amount === (int)$obj['resource_amount']) {
                    $definition=\Conquer\Game\Map\FieldObjectData::get((int)$obj['object_type'],(int)$obj['level']);
                    $items=\Conquer\Game\Rewards\RewardCatalog::rollItems($definition['drops']);
                }
                \Conquer\Game\World\LandProgressService::recordGather((int)$march['world_id'],(int)$march['id'],(int)$obj['coord_x'],(int)$obj['coord_y'],FieldObjectService::RESOURCE_BY_TYPE[(int)$obj['object_type']],$amount,(int)$march['player_id']);
            }
        }
        self::returnHome($march,$loot,$recall?$at:$end,null,$items);return true;
    }

    private static function occupant(array $obj): array|false
    {
        return Connection::getInstance()->query("SELECT * FROM marches WHERE id=? AND world_id=? AND target_id=? AND target_type=5 AND march_type=9 AND state='arrived' FOR UPDATE",[$obj['gatherer_march_id'],$obj['world_id'],$obj['id']])->fetch();
    }

    public static function canAttack(int $playerId,int $ownerId,int $worldId): bool
    {
        if($playerId===$ownerId)return false;
        $alliance=WorldRules::alliance($playerId,$worldId);
        return $alliance===null||$alliance!==WorldRules::alliance($ownerId,$worldId);
    }

    private static function resizeGathering(array $march,array $obj,int $at): void
    {
        $haul=json_decode($march['haul_json'],true)?:[];
        $seconds=max(1,(int)ceil(min((int)$obj['resource_amount'],$haul['gather']['capacity'])/max(.001,(float)$haul['gather']['rate'])));
        $march['haul_json']=json_encode($haul);$march['gathering_finishes_at']=gmdate('Y-m-d H:i:s',max($at,strtotime($march['arrival_time'].' UTC')+$seconds));
        Connection::getInstance()->execute('UPDATE marches SET haul_json=?,gathering_finishes_at=? WHERE id=?',[$march['haul_json'],$march['gathering_finishes_at'],$march['id']]);
        self::finishOne($march,$at);
    }

    private static function node(array $march): array|false
    {
        return Connection::getInstance()->query('SELECT * FROM field_objects WHERE id=? AND world_id=? AND coord_x=? AND coord_y=? AND gatherer_march_id=? FOR UPDATE',[$march['target_id'],$march['world_id'],$march['target_x'],$march['target_y'],$march['id']])->fetch();
    }

    private static function returnHome(array $march,array $loot,int $leaveAt,?string $reason=null,array $items=[]): void
    {
        $db=Connection::getInstance();$travel=max(5,strtotime($march['arrival_time'].' UTC')-strtotime($march['departure_time'].' UTC'));
        $db->execute('UPDATE field_objects SET gatherer_march_id=NULL WHERE id=? AND world_id=? AND gatherer_march_id=?',[$march['target_id'],$march['world_id'],$march['id']]);
        $db->execute("UPDATE marches SET state='returning',haul_json=?,return_time=?,gathering_finishes_at=NULL WHERE id=?",[json_encode(['loot'=>$loot,'items'=>$items,'survivors'=>json_decode($march['troops_json'],true)?:[],'reason'=>$reason]),gmdate('Y-m-d H:i:s',$leaveAt+$travel),$march['id']]);
    }

    private static function atomic(callable $work): mixed
    {
        $db=Connection::getInstance();return WorldRules::combatLock(fn()=>$db->getPdo()->inTransaction()?$work():$db->transaction($work));
    }
}
