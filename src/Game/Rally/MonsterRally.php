<?php
declare(strict_types=1);
namespace Conquer\Game\Rally;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;
use Conquer\Game\WorldRules;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\March\{MarchArmy,MarchDispatcher,MarchSkinService,BattleEngine};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Player\{ActionPoints,LordLevel};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Quest\DailyQuestService;

/** Monster targets use the ordinary rally lifecycle and its reserved armies. */
final class MonsterRally
{
    public static function target(int $world,int $x,int $y,?int $id=null): array
    {
        $db=Connection::getInstance();
        $row=$db->query('SELECT * FROM field_monsters WHERE world_id=? AND coord_x=? AND coord_y=? AND hp_current>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())'.($db->getPdo()->inTransaction()?' FOR UPDATE':''),[$world,$x,$y])->fetch();
        if(!$row||($id!==null&&(int)$row['id']!==$id))throw new \RuntimeException('Das Monster ist nicht mehr an diesem Ort verfügbar.');
        if(!MonsterData::isActive((int)$row['monster_code']))throw new \RuntimeException('Dieses Monster ist derzeit nicht aktiv.');
        $definition=MonsterData::get((int)$row['monster_code']);
        if(($definition['type']??'solo')!=='rally'&&($row['monster_type']??'solo')!=='rally')throw new \RuntimeException('Dieses Monster ist ein Ziel für einen Solo-Angriff.');
        return $row+['definition'=>$definition];
    }

    public static function capacity(int $playerId,int $cityId): int
    {
        $level=max(1,(int)Connection::getInstance()->query("SELECT level FROM city_buildings WHERE city_id=? AND building_code='hall_of_alliance'",[$cityId])->fetchColumn());
        $base=\Conquer\Game\Map\MonsterPower::rallyCapacity($level);
        $buffs=BuffEngine::getBuffs($playerId);
        return (int)floor($base*(1+max(0,(float)($buffs['rally_attack_amount']??0)))+1e-8);
    }

    public static function drops(array $definition): array
    {
        $key=(string)($definition['spawn_code']??$definition['code']??'');
        try{return \Conquer\Game\Rewards\RewardCatalog::effective('monster',$key)['drops'];}
        catch(\InvalidArgumentException){return \Conquer\Game\Rewards\RewardCatalog::effective('monster',(string)($definition['code']??$key))['drops'];}
    }

    public static function start(int $playerId,int $cityId,int $x,int $y,array $troops,int $minutes,string $message): int
    {
        WorldContext::assertActionAvailable();
        return WorldRules::combatLock(fn()=>Connection::getInstance()->transaction(function(Connection $db)use($playerId,$cityId,$x,$y,$troops,$minutes,$message):int{
            WorldRules::origin($playerId,$cityId);$world=WorldContext::id();
            if(!in_array($minutes,[1,5,15,30],true))throw new \RuntimeException('Wähle 1, 5, 15 oder 30 Minuten Sammelzeit.');
            $alliance=WorldRules::alliance($playerId);if($alliance===null)throw new \RuntimeException('Tritt zuerst einer Allianz bei.');
            if(class_exists(\Conquer\Game\World\LandAccessPolicy::class))\Conquer\Game\World\LandAccessPolicy::assertTargetOpen($world,$x,$y);
            $target=self::target($world,$x,$y);$definition=$target['definition'];
            $skinSnapshot=MarchSkinService::dispatchSnapshot($playerId);
            MarchDispatcher::assertSlotAvailable($playerId);
            $troops=MarchArmy::clean($troops,ResearchEffects::limits(BuffEngine::getBuffs($playerId))['march_capacity']);
            $capacity=self::capacity($playerId,$cityId);if(array_sum($troops)>$capacity)throw new \RuntimeException('Die Allianzhalle bietet nicht genug Platz für diese Rally.');
            $cost=max(0,(int)($definition['action_point_cost']??ActionPoints::costForMonster($definition['name'])));
            ActionPoints::deduct($playerId,$cost);MarchArmy::reserve($db,$cityId,$troops);
            $meta=['alliance_id'=>$alliance,'monster'=>$definition,'monster_code'=>(int)$target['monster_code'],'capacity'=>$capacity,'ap_cost'=>$cost,'drops'=>self::drops($definition)];
            $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,march_skin,march_speed_bonus_pct,target_kind,target_monster_id,target_x,target_y,rally_minutes,troops_json,message,result_json,status,launch_at) VALUES(?,?,?,?,?,'monster',?,?,?,?,?,?,?,'gathering',DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[$world,$playerId,$cityId,$skinSnapshot['march_skin'],$skinSnapshot['bonus_pct'],$target['id'],$x,$y,$minutes,json_encode($troops),mb_substr($message,0,512),json_encode($meta),$minutes]);
            return $db->lastInsertId();
        }));
    }

    /** Called inside the row-locked rally settlement transaction. */
    public static function resolve(array $r,array $armies): array
    {
        $db=Connection::getInstance();$world=(int)$r['world_id'];$meta=json_decode($r['result_json'],true);
        WorldPlacement::lockWorld($db,$world);
        try{$target=self::target($world,(int)$r['target_x'],(int)$r['target_y'],(int)$r['target_monster_id']);}
        catch(\PDOException $e){throw $e;}
        catch(\RuntimeException $e){return ['armies'=>$armies,'reason'=>$e->getMessage(),'cancelled'=>true,'outcome'=>'cancelled'];}
        if((int)$target['monster_code']!==$meta['monster_code'])return ['armies'=>$armies,'reason'=>'Das ursprüngliche Monster ist nicht mehr verfügbar.','cancelled'=>true,'outcome'=>'cancelled'];
        foreach($armies as &$army){
            $army['buffs']=BuffEngine::getBuffs($army['player_id'],$world);
            $army['source_snapshot']=\Conquer\Game\March\MonsterReport::capture($army['player_id'],$army['city_id'],$world);
        }unset($army);
        $result=BattleEngine::resolveMonsterArmies($armies,$target,$meta['monster']);
        $weights=array_map(static fn($a)=>array_sum($a['troops']),$armies);$loot=[];$items=[];$xp=[];$settlement=null;
        if($result['monster_killed']){
            $pool=$meta['monster']['resource_reward']??['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50];
            $gems=$meta['monster']['gems_drop']??[];
            if(self::roll((float)($gems['chance']??0)))$pool['gems']=(int)($gems['amount']??0);
            foreach($pool as $resource=>$amount)if(in_array($resource,['food','lumber','stone','gold','gems'],true))foreach(BattleEngine::splitAmount((int)$amount,$weights) as $i=>$share)$loot[$i][$resource]=$share;
            foreach($meta['drops'] as $drop)if(self::roll((float)$drop['probability']))foreach(BattleEngine::splitAmount((int)$drop['count'],$weights) as $i=>$share)if($share>0)$items[$i][(int)$drop['item_code']]=($items[$i][(int)$drop['item_code']]??0)+$share;
            $xp=BattleEngine::splitAmount($meta['monster']['xp']??(max(1,(int)$meta['monster']['level'])*($meta['monster']['xp_per_level']??(str_contains(strtolower($meta['monster']['name']),'deathkar')?20:10))),$weights);
            $settlement=\Conquer\Game\Charm\MonsterCharmLifecycle::settle(
                $world,$target,$meta['monster'],'rally',(int)$r['id'],(int)$r['leader_player_id'],
                isset($meta['alliance_id'])?(int)$meta['alliance_id']:null,
                ['resources'=>$pool,'items_by_army'=>$items,'xp_by_army'=>$xp],
            );
            if(!$settlement['created'])return ['armies'=>$armies,'reason'=>'Das Monster wurde bereits besiegt.','cancelled'=>true,'outcome'=>'cancelled'];
            $result['charm']=['id'=>$settlement['charm_id'],'world_id'=>$world,'x'=>(int)$target['coord_x'],'y'=>(int)$target['coord_y'],'guaranteed'=>true,'ownership'=>null,'exclusive_until'=>null];
            $db->execute('DELETE FROM field_monsters WHERE id=? AND world_id=?',[$target['id'],$world]);
        }else{$db->execute('UPDATE field_monsters SET hp_current=? WHERE id=? AND world_id=?',[$result['new_monster_hp'],$target['id'],$world]);}
        foreach($result['armies'] as $i=>&$army){
            $pid=$army['player_id'];HospitalService::addWounded($army['city_id'],$army['wounded']);
            $army['loot']=\Conquer\Game\Player\TalentEffects::monsterLoot($loot[$i]??[],$armies[$i]['buffs']);$army['items']=$items[$i]??[];
            $earned=$result['monster_killed']?LordLevel::addXp($pid,$xp[$i]??0,$world,'monster-rally:'.$r['id']):0;
            if($result['monster_killed']){DailyQuestService::trackProgress($pid,'attack_monster');$db->execute('UPDATE players SET kill_count=kill_count+1 WHERE id=?',[$pid]);}
            $ownTroops=[];
            foreach($army['troops'] as $code=>$count){$def=\Conquer\Game\City\TroopData::get((int)$code);$ownTroops[]=['code'=>(int)$code,'name'=>$def['name'],'tier'=>$def['tier'],'sent'=>$count,'injured'=>$army['wounded'][$code]??0,'survived'=>$army['survivors'][$code]??0];}
            $itemRewards=[];foreach($army['items'] as $code=>$count)$itemRewards[]=['code'=>(int)$code,'name'=>InventoryService::getItemDef((int)$code)['name'],'count'=>$count];
            $report=$result['report']+['type'=>'monster_rally','rally_id'=>(int)$r['id'],'loot'=>$army['loot'],'items'=>$army['items'],'lord_xp'=>$earned,'own_survivors'=>$army['survivors'],'own_wounded'=>$army['wounded']];
            if($settlement!==null)$report['charm']=$result['charm'];
            $report['troops']=$ownTroops;$report['item_rewards']=$itemRewards;
            $report['rally_combat_snapshot']=$result['report']['combat_snapshot'];
            $report['combat_snapshot']=$army['combat_snapshot'];
            $report['source_snapshot']=$armies[$i]['source_snapshot'];
            $db->execute('INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json) VALUES(?,?,?,3,?,?,?,?,?)',[$world,$pid,$army['city_id'],$target['id'],$r['target_x'],$r['target_y'],$result['outcome'],json_encode($report)]);
        }unset($army);
        return $result;
    }

    private static function roll(float $chance): bool
    {
        return $chance>=1||($chance>0&&random_int(1,1000000)<=(int)round($chance*1000000));
    }
}
