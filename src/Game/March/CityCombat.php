<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchEffects;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\Map\WorldPlacement;

/** Atomic city combat shared by solo marches and cooperative rally armies. */
final class CityCombat
{
    /** Caller holds the combat advisory lock and an open transaction. */
    public static function resolve(array $armies,int $targetCityId,int $x,int $y,?int $marchId=null,?int $rallyId=null): array
    {
        $db=Connection::getInstance();
        $world=(int)$db->query('SELECT world_id FROM cities WHERE id=?',[$targetCityId])->fetchColumn();
        if($world>0)WorldPlacement::lockWorld($db,$world);
        $city=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[$targetCityId])->fetch();
        $result=['outcome'=>'draw','cancelled'=>false,'armies'=>[]];
        try{
            foreach($armies as $army){$origin=WorldRules::origin((int)$army['player_id'],(int)$army['city_id'],$world);$target=WorldRules::assertCityAttackAllowed((int)$army['player_id'],$x,$y,null,(int)$origin['world_id']);if((int)$target['id']!==$targetCityId)throw new \RuntimeException('Die Zielstadt wurde versetzt.');}
        }catch(\RuntimeException $e){
            $result['cancelled']=true;$result['reason']=$e->getMessage();
            foreach($armies as $army)$result['armies'][]=$army+['survivors'=>$army['troops'],'loot'=>[],'wounded'=>[],'dead'=>[]];
            return $result;
        }
        $city=DefenseService::syncWall($targetCityId);
        $buildings=[];foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$targetCityId])->fetchAll() as $b)$buildings[$b['building_code']]=['level'=>(int)$b['level']];
        ResourceTick::persist($city,$buildings);$city=$db->query('SELECT * FROM cities WHERE id=?',[$targetCityId])->fetch();
        $wallBefore=DefenseService::wallStats($city,$buildings);
        $defender=(int)$city['player_id'];
        $garrison=array_map('intval',$db->query('SELECT troop_code,count FROM city_troops WHERE city_id=? AND count>0 ORDER BY troop_code FOR UPDATE',[$targetCityId])->fetchAll(\PDO::FETCH_KEY_PAIR));
        $defenders=[['player_id'=>$defender,'city_id'=>$targetCityId,'troops'=>$garrison]];
        foreach($db->query("SELECT * FROM reinforcements WHERE target_city_id=? AND state='active' ORDER BY id FOR UPDATE",[$targetCityId])->fetchAll() as $row){$defenders[]=['player_id'=>(int)$row['sender_id'],'city_id'=>(int)$row['sender_city_id'],'troops'=>json_decode($row['troops_json'],true)?:[],'reinforcement_id'=>(int)$row['id']];}
        $attackScore=0.0;$defenseScore=0.0;
        $attackSnapshots=[];$defenseSnapshots=[];
        foreach($armies as $army){
            $buffs=self::combatBuffs($army,$rallyId!==null,false,$x,$y);
            $snapshot=CombatReport::army($army,$buffs);
            $attackScore+=array_sum(array_column($snapshot['troops'],'strength'));
            $attackSnapshots[]=$snapshot;
        }
        foreach($defenders as $army){
            $buffs=self::combatBuffs($army,false,true,$x,$y);
            $factor=1/max(.05,1+(float)($buffs['talent_city_damage_taken']??0));
            $snapshot=CombatReport::army($army,$buffs,$factor);
            $defenseScore+=array_sum(array_column($snapshot['troops'],'strength'));
            foreach($snapshot['troops'] as &$troop)$troop['strength']*=1.1*(1+$wallBefore['defense_buff']/100);
            unset($troop);$defenseSnapshots[]=$snapshot;
        }
        $defenseScore*=1+$wallBefore['defense_buff']/100;
        $attackScoreBeforeLuck=$attackScore;
        $luckPercent=BattleLuck::roll();
        $luckFactor=BattleLuck::factor($luckPercent);
        $attackScore*=$luckFactor;
        foreach($attackSnapshots as &$snapshot)foreach($snapshot['troops'] as &$troop)$troop['strength']*=$luckFactor;
        unset($troop,$snapshot);
        $wins=PvpRules::attackerWins($attackScore,$defenseScore*1.1);$result['outcome']=$wins?'attacker_wins':'defender_wins';
        $result['attacker_score']=(int)round($attackScore);$result['defender_score']=(int)round($defenseScore*1.1);
        $wallBonus=0.0;$troopTotal=max(1,array_sum(array_map(static fn($a)=>array_sum($a['troops']),$armies)));
        foreach($armies as $army)$wallBonus+=(BuffEngine::getBuffs((int)$army['player_id'],$world)['talent_wall_damage']??0)*array_sum($army['troops'])/$troopTotal;
        if($wins)MarchTick::checkWallDestroyed($db,$targetCityId,(int)$city['player_id'],$wallBonus);
        $wallAfter=$db->query('SELECT wall_hp_current,wall_hp_max,coord_x,coord_y FROM cities WHERE id=?',[$targetCityId])->fetch();
        $result['wall']=['before'=>$wallBefore['durability'],'after'=>(int)$wallAfter['wall_hp_current'],'max'=>(int)$wallAfter['wall_hp_max'],'relocated'=>(int)$wallAfter['coord_x']!==$x||(int)$wallAfter['coord_y']!==$y];
        $defenderDead=[];$defenderWounded=[];
        foreach($defenders as $index=>$army){
            $reduction=max(.05,1+(float)(BuffEngine::getBuffs((int)$army['player_id'],$world)['talent_city_damage_taken']??0));
            $loss=self::losses($army['troops'],($wins?.30:.10)*$reduction);HospitalService::addWounded((int)$army['city_id'],$loss['wounded']);
            $defenseSnapshots[$index]=CombatReport::settle($defenseSnapshots[$index],$loss);
            if(isset($army['reinforcement_id']))$db->execute('UPDATE reinforcements SET troops_json=? WHERE id=?',[json_encode($loss['survivors']),$army['reinforcement_id']]);
            else foreach($army['troops'] as $code=>$count){$db->execute('UPDATE city_troops SET count=count-? WHERE city_id=? AND troop_code=?',[$count-($loss['survivors'][$code]??0),$targetCityId,$code]);$defenderDead[$code]=$loss['dead'][$code]??0;$defenderWounded[$code]=$loss['wounded'][$code]??0;}
        }
        $protected=DefenseService::protectedResources($city,BuffEngine::getBuffs($defender,$world));
        $lootPool=[];foreach(['food','lumber','stone','gold'] as $resource)$lootPool[$resource]=$wins?(int)floor(max(0,(float)$city[$resource]-$protected[$resource])*.20):0;
        $totalTroops=array_sum(array_map(fn($army)=>array_sum($army['troops']),$armies));$looted=array_fill_keys(array_keys($lootPool),0);
        foreach($armies as $index=>$army){
            $loss=self::losses($army['troops'],$wins?.10:.30);$buffs=BuffEngine::getBuffs((int)$army['player_id'],$world);$capacity=0;
            foreach($loss['survivors'] as $code=>$count)$capacity+=$count*ResearchEffects::carryPerTroop((int)$code,$buffs);
            $share=array_sum($army['troops'])/max(1,$totalTroops);$loot=[];
            foreach($lootPool as $resource=>$amount){$loot[$resource]=(int)min($capacity,floor($amount*$share));$capacity-=$loot[$resource];$looted[$resource]+=$loot[$resource];}
            HospitalService::addWounded((int)$army['city_id'],$loss['wounded']);
            $settled=$army+$loss+['loot'=>$loot];$result['armies'][]=$settled;
            $attackSnapshots[$index]=CombatReport::settle($attackSnapshots[$index],$loss);
        }
        $combat=['version'=>2,'outcome'=>$result['outcome'],'luck_percent'=>$luckPercent,
            'attacker_score_before_luck'=>(int)round($attackScoreBeforeLuck),
            'attacker'=>CombatReport::side($attackSnapshots,$result['attacker_score']),
            'defender'=>CombatReport::side($defenseSnapshots,$result['defender_score']),
            'wall_defense_pct'=>$wallBefore['defense_buff'],'defender_advantage_pct'=>10];
        foreach($result['armies'] as $army){
            $loss=$army;$loot=$army['loot'];
            $report=['battle_kind'=>$rallyId?'rally':'city','rally_id'=>$rallyId,'target_name'=>$target['display_name'],'monster_name'=>$target['display_name'],'target_player_id'=>$defender,'outcome'=>$result['outcome'],'attacker_damage'=>$result['attacker_score'],'defender_strength'=>$result['defender_score'],'monster_hp_after'=>0,'loot'=>$loot,'dead'=>$loss['dead'],'defender_dead'=>$defenderDead,'defender_wounded'=>$defenderWounded,'troops'=>[]];
            foreach($army['troops'] as $code=>$count)$report['troops'][]=['code'=>(int)$code,'sent'=>$count,'survived'=>$loss['survivors'][$code]??0,'injured'=>$loss['wounded'][$code]??0,'dead'=>$loss['dead'][$code]??0];
            $report['wall']=$result['wall'];$report['protected_resources']=$protected;
            $report['combat']=$combat;$report['perspective']='attacker';
            self::report((int)$army['player_id'],(int)$army['city_id'],$defender,$targetCityId,$x,$y,$marchId,$result['outcome'],$report);
        }
        $db->execute('UPDATE cities SET food=food-?,lumber=lumber-?,stone=stone-?,gold=gold-? WHERE id=?',[$looted['food'],$looted['lumber'],$looted['stone'],$looted['gold'],$targetCityId]);
        $result['loot']=$looted;
        $defReport=['battle_kind'=>$rallyId?'rally':'city','rally_id'=>$rallyId,'perspective'=>'defender','target_name'=>'Angreifende Armee','monster_name'=>'Angreifende Armee','attacker_damage'=>$result['defender_score'],'monster_hp_after'=>0,'outcome'=>$wins?'defender_wins':'attacker_wins','loot'=>[],'resources_lost'=>$looted,'troops'=>[]];
        foreach($garrison as $code=>$count)$defReport['troops'][]=['code'=>(int)$code,'sent'=>$count,'survived'=>$count-($defenderDead[$code]??0)-($defenderWounded[$code]??0),'injured'=>$defenderWounded[$code]??0,'dead'=>$defenderDead[$code]??0];
        $defReport['wall']=$result['wall'];$defReport['protected_resources']=$protected;
        $defReport['combat']=$combat;$defReport['target_name']=$defReport['monster_name']=$attackSnapshots[0]['name'];
        self::report($defender,$targetCityId,(int)$armies[0]['player_id'],(int)$armies[0]['city_id'],$x,$y,$marchId,$defReport['outcome'],$defReport);
        return $result;
    }

    private static function combatBuffs(array $army,bool $rally,bool $defending=false,?int $x=null,?int $y=null): array
    {
        $world=(int)Connection::getInstance()->query('SELECT world_id FROM cities WHERE id=?',[$army['city_id']])->fetchColumn();
        $buffs=ResearchEffects::armyBuffs(BuffEngine::getBuffs((int)$army['player_id'],$world,$x,$y),$army['troops'],$rally);$buffs=\Conquer\Game\Player\TalentEffects::combat($buffs,$defending?'city_defense':'pvp',$rally);
        if($defending)foreach(['infantry'=>'infantrys','ranged'=>'archers','cavalry'=>'cavalrys'] as $type=>$key)foreach(['hp','def','atk'] as $stat)$buffs[$type.'_'.$stat]=($buffs[$type.'_'.$stat]??0)+(float)($buffs['castle_defending_'.$key.'_'.$stat]??0);
        return $buffs;
    }

    private static function losses(array $troops,float $rate): array
    {
        return PvpRules::losses($troops,$rate);
    }

    private static function report(int $playerId,int $cityId,int $defenderId,int $targetId,int $x,int $y,?int $marchId,string $outcome,array $data): void
    {
        $db=Connection::getInstance();$world=(int)$db->query('SELECT world_id FROM cities WHERE id=?',[$cityId])->fetchColumn();
        $db->execute('INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,defender_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at) VALUES(?,?,?,?,?,2,?,?,?,?,?,UTC_TIMESTAMP())',[$world,$marchId,$playerId,$cityId,$defenderId,$targetId,$x,$y,$outcome,json_encode($data,JSON_THROW_ON_ERROR)]);
    }
}
