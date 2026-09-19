<?php
declare(strict_types=1);
namespace Conquer\Game\Shrine;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSkinService,MarchSpeed};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\WorldRules;
use Conquer\Game\World\WorldContext;

/** Real shrine marches, persistent attrition and alliance occupation, sharing existing tables. */
final class CongressService
{
    public const ATTACK=13;
    public const GARRISON=14;
    public const TARGET_TYPE=4;

    public static function state(int $playerId): ?array
    {
        self::tick();
        $id=Connection::getInstance()->query("SELECT id FROM shrines WHERE world_id=? AND shrine_code='CONGRESS'",[WorldContext::id()])->fetchColumn();
        return $id===false?null:self::detail((int)$id,$playerId);
    }

    /** Exactly the four event landmarks, independent of the current map viewport. */
    public static function eventShrines(int $playerId): array
    {
        $rows=Connection::getInstance()->query("SELECT id FROM shrines WHERE world_id=? AND shrine_code IN ('SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA') ORDER BY FIELD(shrine_code,'SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA')",[WorldContext::id()])->fetchAll(\PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map(static fn($id)=>self::detail((int)$id,$playerId),$rows)));
    }

    public static function detail(int $id,int $playerId): ?array
    {
        $shrine=ShrineService::getShrine($id);if(!$shrine||(int)$shrine['world_id']!==WorldContext::id())return null;
        if(!\Conquer\Game\World\LandAccessPolicy::isOpen(WorldContext::id(),(int)$shrine['coord_x'],(int)$shrine['coord_y']))return null;
        $alliance=WorldRules::alliance($playerId);$own=$alliance!==null&&$alliance===$shrine['alliance_id'];
        $rows=Connection::getInstance()->query('SELECT player_id,troops_json FROM shrine_garrisons WHERE shrine_id=?',[$id])->fetchAll();
        $mine=[];$total=$shrine['garrison_troops'];
        foreach($rows as $row){$troops=json_decode($row['troops_json'],true)?:[];foreach($troops as $code=>$count)$total[$code]=($total[$code]??0)+(int)$count;if((int)$row['player_id']===$playerId)$mine=$troops;}
        $shrine['garrison_troops']=$total;$shrine['garrison_total']=array_sum($total);
        $shrine['my_garrison']=['troops'=>$mine,'total'=>array_sum($mine)];
        $shrine['own_alliance_id']=$alliance;$shrine['can_attack']=$alliance!==null&&!$own;
        $shrine['can_garrison']=$own;$shrine['can_recall']=array_sum($mine)>0;
        $shrine['hold_seconds']=ShrineService::CONTEST_DURATION_SECONDS;
        $shrine['description']='Besiege die Verteidiger mit Allianzangriffen und halte den Kongress eine Stunde. Verstärkungen schützen die Besatzung. Verluste sind dauerhaft; Verwundete werden im Lazarett versorgt.';
        if(ShrineEvent::shrine($shrine['shrine_code'])){
            $shrine['event']=ShrineEvent::state(self::now());
            $shrine['can_attack']=$shrine['can_attack']&&$shrine['event']['active'];
            $shrine['description']='Erobere den Schrein während „Krieg der vier Schreine“ und halte ihn eine Stunde. Angriffe müssen noch im selben Ereignis eintreffen. Besitz, Verstärkung und Rückruf bleiben außerhalb des Ereignisses erhalten.';
        }
        elseif($shrine['shrine_code']!=='CONGRESS'){
            try{\Conquer\Game\Conquest\EventService::assertShrineOpen($shrine);}catch(\DomainException $e){$shrine['can_attack']=false;$shrine['locked_reason']=$e->getMessage();}
            $shrine['description']='Conquest-Ziel: Besiege die Garnison, sichere den Schrein eine Stunde und sammle Haltepunkte für deine Allianz.';
        }
        return $shrine;
    }

    public static function dispatch(int $playerId,int $shrineId,mixed $input,bool $garrison=false): array
    {
        self::tick();
        return self::atomic(function(Connection $db)use($playerId,$shrineId,$input,$garrison): array{
            // The city row serializes stock/slot checks even for service callers without API advisory locks.
            WorldContext::assertActionAvailable();
            $city=$db->query('SELECT * FROM cities WHERE player_id=? AND world_id=? ORDER BY id LIMIT 1 FOR UPDATE',[$playerId,WorldContext::id()])->fetch();
            if(!$city)throw new \RuntimeException('Keine eigene Stadt gefunden.');
            $alliance=WorldRules::alliance($playerId);if($alliance===null)throw new \RuntimeException('Für eine Eroberung benötigst du eine Allianz.',403);
            $db->query('SELECT id FROM shrines WHERE id=? AND world_id=? FOR UPDATE',[$shrineId,WorldContext::id()])->fetch();
            $shrine=ShrineService::getShrine($shrineId);if(!$shrine||(int)$shrine['world_id']!==WorldContext::id())throw new \RuntimeException('Schrein nicht gefunden.',404);
            \Conquer\Game\World\LandAccessPolicy::assertTargetOpen(WorldContext::id(),(int)$shrine['coord_x'],(int)$shrine['coord_y']);
            $own=$shrine['alliance_id']===$alliance;
            if($garrison&&!$own)throw new \RuntimeException('Nur deine Allianz kann ihre eigene Besatzung verstärken.',403);
            if(!$garrison&&$own)throw new \RuntimeException('Deine Allianz hält diesen Schrein bereits. Sende Verstärkung.');
            $eventInstance=null;
            if(!$garrison&&ShrineEvent::shrine($shrine['shrine_code'])){
                $event=ShrineEvent::state(self::now());
                if(!$event['active'])throw new \RuntimeException('Krieg der vier Schreine ist geschlossen. Nächster Beginn: '.$event['next_starts_at'].' UTC.',403);
                $eventInstance=$event['instance_id'];
            }
            if(!$garrison&&$shrine['shrine_code']!=='CONGRESS'&&!ShrineEvent::shrine($shrine['shrine_code']))\Conquer\Game\Conquest\EventService::assertShrineOpen($shrine);
            $buffs=BuffEngine::getBuffs($playerId);
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            $troops=MarchArmy::clean($input,ResearchEffects::limits($buffs)['march_capacity']);
            MarchDispatcher::assertSlotAvailable($playerId);
            MarchArmy::reserve($db,(int)$city['id'],$troops);
            $seconds=self::travelSeconds($city,$shrine,$troops,$buffs,$skinSnapshot,!$garrison&&$shrine['alliance_id']!==null);
            $db->execute("INSERT INTO marches(player_id,world_id,march_type,march_skin,march_speed_bonus_pct,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'marching')",[$playerId,WorldContext::id(),$garrison?self::GARRISON:self::ATTACK,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$city['id'],$shrine['coord_x'],$shrine['coord_y'],self::TARGET_TYPE,$shrineId,json_encode($troops,JSON_THROW_ON_ERROR),$seconds]);
            $id=$db->lastInsertId();
            $db->execute('INSERT INTO shrine_march_orders(march_id,alliance_id,buffs_json,event_instance) VALUES(?,?,?,?)',[$id,$alliance,json_encode($buffs,JSON_THROW_ON_ERROR),$eventInstance]);
            return self::receipt($id,$garrison?'Verstärkung ist zum Schrein unterwegs.':'Der Angriff auf den Schrein ist unterwegs.');
        });
    }

    /** Own garrison can be recalled after leaving an alliance, with a real return journey. */
    public static function recall(int $playerId,int $shrineId): array
    {
        self::tick();
        return self::atomic(function(Connection $db)use($playerId,$shrineId):array{
            $db->query('SELECT id FROM shrines WHERE id=? FOR UPDATE',[$shrineId])->fetch();
            $row=$db->query('SELECT * FROM shrine_garrisons WHERE shrine_id=? AND player_id=? FOR UPDATE',[$shrineId,$playerId])->fetch();
            if(!$row)throw new \RuntimeException('Du hast hier keine Garnison.');
            $shrine=ShrineService::getShrine($shrineId);if(!$shrine||(int)$shrine['world_id']!==WorldContext::id())throw new \RuntimeException('Schrein nicht gefunden.',404);
            $id=self::returnGarrison($db,$row,$shrine,json_decode($row['troops_json'],true)?:[]);
            return self::receipt($id,'Deine Garnison kehrt in die Stadt zurück.');
        });
    }

    /** Arrival settlement runs even when the attacker is offline and another player views the Congress. */
    public static function tick(): void
    {
        $db=Connection::getInstance();
        $due=$db->query("SELECT id FROM marches WHERE march_type IN (13,14) AND ((state='marching' AND arrival_time<=UTC_TIMESTAMP()) OR (state='returning' AND return_time<=UTC_TIMESTAMP())) ORDER BY COALESCE(return_time,arrival_time),id LIMIT 100")->fetchAll(\PDO::FETCH_COLUMN);
        foreach($due as $id)self::resolveMarch((int)$id);
        ShrineService::checkSecured();
    }

    public static function resolveMarch(int $marchId): void
    {
        self::atomic(function(Connection $db)use($marchId):void{
            $march=$db->query('SELECT m.*,o.alliance_id AS order_alliance_id,o.buffs_json,o.event_instance FROM marches m LEFT JOIN shrine_march_orders o ON o.march_id=m.id WHERE m.id=? FOR UPDATE',[$marchId])->fetch();
            if(!$march||!in_array((int)$march['march_type'],[self::ATTACK,self::GARRISON],true))return;
            $troops=json_decode($march['troops_json'],true)?:[];
            if($march['state']==='returning'){
                if(strtotime($march['return_time'].' UTC')>self::now())return;
                $haul=json_decode($march['haul_json']??'{}',true)?:[];
                foreach($haul['survivors']??$troops as $code=>$count)if($count>0)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$march['origin_city_id'],(int)$code,(int)$count]);
                $db->execute("UPDATE marches SET state='complete' WHERE id=?",[$marchId]);return;
            }
            if($march['state']!=='marching'||strtotime($march['arrival_time'].' UTC')>self::now())return;
            $db->query('SELECT id FROM shrines WHERE id=? FOR UPDATE',[$march['target_id']])->fetch();
            $shrine=ShrineService::getShrine((int)$march['target_id']);
            $alliance=(int)$march['order_alliance_id'];
            // Membership is rechecked; switching cannot hand an earlier army to a new alliance.
            if(!$shrine||(int)$shrine['world_id']!==(int)$march['world_id']||$alliance<1||WorldRules::alliance((int)$march['player_id'],(int)$march['world_id'])!==$alliance){self::returnMarch($db,$march,$troops,'Allianzmitgliedschaft hat sich geändert.');return;}
            if((int)$march['march_type']===self::ATTACK&&ShrineEvent::shrine($shrine['shrine_code'])&&!ShrineEvent::arrivalAllowed($march['event_instance'],strtotime($march['arrival_time'].' UTC'))){
                self::returnMarch($db,$march,$troops,'Das Schrein-Ereignis ist bei Ankunft beendet oder gehört zu einer anderen Ereigniswoche. Die gesamte Armee kehrt zurück.');return;
            }
            $own=$shrine['alliance_id']===$alliance;
            if((int)$march['march_type']===self::GARRISON){
                if(!$own){self::returnMarch($db,$march,$troops,'Die Allianz hält den Schrein nicht mehr.');return;}
                $previous=$db->query('SELECT * FROM shrine_garrisons WHERE shrine_id=? AND player_id=? FOR UPDATE',[$shrine['id'],$march['player_id']])->fetch();
                $combined=$previous?(json_decode($previous['troops_json'],true)?:[]):[];
                foreach($troops as $code=>$count)$combined[$code]=($combined[$code]??0)+(int)$count;
                $db->execute('INSERT INTO shrine_garrisons(shrine_id,player_id,city_id,march_skin,march_speed_bonus_pct,troops_json,alliance_id,buffs_json,travel_seconds) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE troops_json=VALUES(troops_json),march_skin=VALUES(march_skin),march_speed_bonus_pct=VALUES(march_speed_bonus_pct),buffs_json=VALUES(buffs_json),travel_seconds=VALUES(travel_seconds)',[$shrine['id'],$march['player_id'],$march['origin_city_id'],$march['march_skin'],$march['march_speed_bonus_pct'],json_encode($combined),$alliance,$march['buffs_json'],self::duration($march)]);
                $db->execute("UPDATE marches SET state='complete',haul_json=? WHERE id=?",[json_encode(['garrisoned'=>$troops]),$marchId]);return;
            }
            if($own){self::returnMarch($db,$march,$troops,'Deine Allianz hat den Schrein inzwischen erobert.');return;}
            if($shrine['shrine_code']!=='CONGRESS'&&!ShrineEvent::shrine($shrine['shrine_code'])){
                try{\Conquer\Game\Conquest\EventService::assertShrineOpen($shrine);}catch(\DomainException $e){self::returnMarch($db,$march,$troops,$e->getMessage());return;}
            }
            self::fight($db,$march,$shrine,$troops,$alliance);
        });
    }

    private static function fight(Connection $db,array $march,array $shrine,array $troops,int $alliance): void
    {
        $guards=$db->query('SELECT * FROM shrine_garrisons WHERE shrine_id=? ORDER BY id FOR UPDATE',[$shrine['id']])->fetchAll();
        $attackBuffs=ResearchEffects::armyBuffs(json_decode($march['buffs_json']??'{}',true)?:[],$troops);
        if($shrine['alliance_id']!==null)$attackBuffs=\Conquer\Game\Player\TalentEffects::combat($attackBuffs,'pvp');
        $attack=self::power($troops,$attackBuffs,false);$absorption=self::power($troops,$attackBuffs,true);
        $defense=self::power($shrine['garrison_troops'],[],true);
        foreach($guards as $guard){$army=json_decode($guard['troops_json'],true)?:[];$defense+=self::power($army,ResearchEffects::armyBuffs(json_decode($guard['buffs_json'],true)?:[],$army),true);}
        $wins=$attack>=$defense;
        $attackRate=$wins?min(.5,$defense/max(1,$absorption)):min(.8,$defense/max(1,$absorption)*.6);
        $defenseRate=$wins?.5:min(.5,$attack/max(1,$defense));
        $loss=self::casualties($troops,$attackRate);
        HospitalService::addWounded((int)$march['origin_city_id'],$loss['wounded']);
        foreach($guards as $guard){
            $guardLoss=self::casualties(json_decode($guard['troops_json'],true)?:[],$defenseRate);
            HospitalService::addWounded((int)$guard['city_id'],$guardLoss['wounded']);
            if($wins)self::returnGarrison($db,$guard,$shrine,$guardLoss['survivors']);
            else $db->execute('UPDATE shrine_garrisons SET troops_json=? WHERE id=?',[json_encode($guardLoss['survivors']),$guard['id']]);
        }
        $npc=$wins?[]:self::casualties($shrine['garrison_troops'],$defenseRate)['survivors'];
        if($wins){
            \Conquer\Game\Conquest\EventService::beforeCapture($shrine);
            $db->execute('INSERT INTO shrine_captures(shrine_id,alliance_id,captured_at,contested_until,secured_at,garrison_troops_json) VALUES(?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),NULL,?) ON DUPLICATE KEY UPDATE alliance_id=VALUES(alliance_id),captured_at=VALUES(captured_at),contested_until=VALUES(contested_until),secured_at=NULL,garrison_troops_json=VALUES(garrison_troops_json)',[$shrine['id'],$alliance,ShrineService::CONTEST_DURATION_SECONDS,'{}']);
        }else{
            $db->execute('INSERT INTO shrine_captures(shrine_id,garrison_troops_json) VALUES(?,?) ON DUPLICATE KEY UPDATE garrison_troops_json=VALUES(garrison_troops_json)',[$shrine['id'],json_encode($npc)]);
        }
        $outcome=$wins?'attacker_wins':'defender_wins';$report=['battle_kind'=>$shrine['shrine_code']==='CONGRESS'?'congress':'shrine','target_name'=>$shrine['name'],'monster_name'=>$shrine['name'],'outcome'=>$outcome,'alliance_id'=>$alliance,'attacker_damage'=>(int)round($attack),'defender_strength'=>(int)round($defense),'loot'=>[],'dead'=>$loss['dead'],'troops'=>[],'hold_seconds'=>ShrineService::CONTEST_DURATION_SECONDS];
        foreach($troops as $code=>$count)$report['troops'][]=['code'=>(int)$code,'sent'=>$count,'survived'=>$loss['survivors'][$code]??0,'injured'=>$loss['wounded'][$code]??0,'dead'=>$loss['dead'][$code]??0];
        $report['message']=$wins?'Besatzung besiegt. Deine Allianz muss den Schrein eine Stunde halten.':'Angriff abgewehrt. Die verbleibende Besatzung bleibt für weitere Angriffe geschwächt.';
        $db->execute('INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$march['world_id'],$march['id'],$march['player_id'],$march['origin_city_id'],self::TARGET_TYPE,$shrine['id'],$shrine['coord_x'],$shrine['coord_y'],$outcome,json_encode($report)]);
        if($wins){
            // The surviving conquering army actually holds the landmark until recalled or defeated.
            $db->execute('INSERT INTO shrine_garrisons(shrine_id,player_id,city_id,march_skin,march_speed_bonus_pct,troops_json,alliance_id,buffs_json,travel_seconds) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE troops_json=VALUES(troops_json),alliance_id=VALUES(alliance_id),march_skin=VALUES(march_skin),march_speed_bonus_pct=VALUES(march_speed_bonus_pct),buffs_json=VALUES(buffs_json),travel_seconds=VALUES(travel_seconds)',[$shrine['id'],$march['player_id'],$march['origin_city_id'],$march['march_skin'],$march['march_speed_bonus_pct'],json_encode($loss['survivors']),$alliance,$march['buffs_json'],self::duration($march)]);
            $db->execute("UPDATE marches SET state='complete',haul_json=? WHERE id=?",[json_encode(['garrisoned'=>$loss['survivors'],'reason'=>'Sieg: Die Überlebenden halten den Schrein als Garnison.']),$march['id']]);
        }else{
            self::returnMarch($db,$march,$loss['survivors'],$report['message']);
        }
        ShrineService::checkSecured();
    }

    /** Original shrine attack-vs-HP/defense formula; real research modifies each troop type. */
    private static function power(array $troops,array $buffs,bool $defense): float
    {
        $score=0.0;foreach($troops as $code=>$count){$def=TroopData::get((int)$code);if(!$def)continue;$type=ResearchEffects::troopType((int)$code);$value=$defense?($def['hp']*BuffEngine::effectiveMultiplier($buffs,$type,'hp')+$def['defense']*BuffEngine::effectiveMultiplier($buffs,$type,'def')):$def['attack']*BuffEngine::effectiveMultiplier($buffs,$type,'atk');$score+=$count*$value;}return $score;
    }

    private static function casualties(array $troops,float $rate): array
    {
        $out=['survivors'=>[],'wounded'=>[],'dead'=>[]];foreach($troops as $code=>$count){$lost=min($count,(int)round($count*$rate));$wounded=(int)floor($lost*.3);$out['survivors'][$code]=$count-$lost;$out['wounded'][$code]=$wounded;$out['dead'][$code]=$lost-$wounded;}return $out;
    }

    private static function returnMarch(Connection $db,array $march,array $troops,string $reason): void
    {
        $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),haul_json=? WHERE id=?",[self::duration($march),json_encode(['survivors'=>$troops,'loot'=>[],'reason'=>$reason]),$march['id']]);
    }

    private static function returnGarrison(Connection $db,array $guard,array $shrine,array $troops): int
    {
        $db->execute("INSERT INTO marches(player_id,world_id,march_type,march_skin,march_speed_bonus_pct,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,return_time,state,haul_json) VALUES(?,?,14,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),'returning',?)",[$guard['player_id'],$shrine['world_id'],$guard['march_skin']??null,(int)($guard['march_speed_bonus_pct']??0),$guard['city_id'],$shrine['coord_x'],$shrine['coord_y'],self::TARGET_TYPE,$shrine['id'],json_encode($troops),max(5,(int)$guard['travel_seconds']),json_encode(['survivors'=>$troops,'loot'=>[]])]);
        $id=$db->lastInsertId();$db->execute('DELETE FROM shrine_garrisons WHERE id=?',[$guard['id']]);return $id;
    }

    private static function duration(array $march): int {return max(5,strtotime($march['arrival_time'].' UTC')-strtotime($march['departure_time'].' UTC'));}

    /** DB clock also drives arrival and return SQL, preventing mixed-clock boundary decisions. */
    private static function now(): int {return (int)Connection::getInstance()->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();}

    private static function travelSeconds(array $city,array $shrine,array $troops,array $buffs,array $skinSnapshot,bool $occupiedAttack): int
    {
        $skinMultiplier=MarchSkinService::speedMultiplier($skinSnapshot);$speed=PHP_INT_MAX;
        foreach($troops as $code=>$count)$speed=min($speed,$occupiedAttack?MarchSpeed::pvp((int)$code,$buffs,$skinMultiplier):MarchSpeed::generic((int)$code,$buffs,$skinMultiplier));
        return max(5,(int)floor(hypot($city['coord_x']-$shrine['coord_x'],$city['coord_y']-$shrine['coord_y'])*100/max(1,$speed)));
    }

    private static function receipt(int $id,string $message): array
    {
        $row=Connection::getInstance()->query('SELECT arrival_time,return_time,state FROM marches WHERE id=?',[$id])->fetch();
        return ['march_id'=>$id,'arrival_time'=>$row['return_time']??$row['arrival_time'],'state'=>$row['state'],'message'=>$message];
    }

    private static function atomic(callable $fn): mixed
    {
        return WorldRules::combatLock(function()use($fn):mixed{$db=Connection::getInstance();return $db->getPdo()->inTransaction()?$fn($db):$db->transaction($fn);});
    }
}
