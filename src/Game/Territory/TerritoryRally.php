<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Rally\RallyService;
use Conquer\Game\March\{MarchSpeed,MarchSkinService,PvpRules,CombatReport};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Hospital\HospitalService;

/** Ordinary reserved rally armies, with a chronological scheduler for territorial events. */
final class TerritoryRally
{
    /** Troop effects and march speed bind at dispatch; offline settlement must not read later buffs. */
    public static function snapshot(int $player,int $city,int $world,array $troops,array $skin,bool $rally=true): array
    {
        $raw=BuffEngine::getBuffs($player,$world);$buffs=ResearchEffects::armyBuffs($raw,$troops,$rally);$speed=INF;$returnSpeed=INF;
        $travel=$rally?\Conquer\Game\Player\TalentEffects::cavalryMarch($raw,$troops):$raw;$back=$rally?\Conquer\Game\Player\TalentEffects::cavalryMarch($raw,$troops,true):$raw;
        foreach($troops as $code=>$count)if($count>0){$speed=min($speed,MarchSpeed::rally((int)$code,$travel,false,MarchSkinService::speedMultiplier($skin)));$returnSpeed=min($returnSpeed,MarchSpeed::rally((int)$code,$back,false,MarchSkinService::speedMultiplier($skin)));}
        return ['version'=>1,'captured_at'=>gmdate('Y-m-d H:i:s'),'buffs'=>$buffs,'march_speed'=>$speed,'return_march_speed'=>$returnSpeed,'report'=>CombatReport::army(['player_id'=>$player,'city_id'=>$city,'troops'=>$troops],$buffs)];
    }
    public static function validateJoin(array $r,int $player): void
    {
        $c=Connection::getInstance()->query('SELECT * FROM territory_campaigns WHERE id=? AND world_id=?',[$r['territory_campaign_id'],$r['world_id']])->fetch();
        TerritoryService::require($c&&$c['status']==='gathering'&&WorldRules::alliance($player,(int)$r['world_id'])===(int)$c['alliance_id'],'Dieser Feldzug ist nicht mehr für dich verfügbar.');
    }
    public static function cancelled(array $r,string $reason,?int $at=null): void
    {
        Connection::getInstance()->execute("UPDATE territory_campaigns SET status='cancelled',slot_reserved=0,resolved_at=?,result_json=? WHERE id=? AND status IN ('gathering','marching')",[TerritoryService::date($at??time()),json_encode(['reason'=>$reason]),$r['territory_campaign_id']]);
    }
    public static function launch(array $r,?int $at=null): void
    {
        $at??=time();$db=Connection::getInstance();$c=$db->query('SELECT * FROM territory_campaigns WHERE id=? FOR UPDATE',[$r['territory_campaign_id']])->fetch();$world=(int)$r['world_id'];
        if(WorldRules::alliance((int)$r['leader_player_id'],$world)!==(int)$c['alliance_id']){self::cancelGathering($r,'Der Anführer hat die Allianz verlassen.',$at);return;}
        // Joiners must have arrived by the planned departure, even after a long server outage.
        $joiners=$db->query("SELECT * FROM rally_participants WHERE rally_id=? AND status IN ('joining','pending') FOR UPDATE",[$r['id']])->fetchAll();
        foreach($joiners as $p){
            if(TerritoryService::timestamp($p['arrival_time'])<=$at&&WorldRules::alliance((int)$p['player_id'],$world)===(int)$c['alliance_id'])$db->execute("UPDATE rally_participants SET status='pending' WHERE id=?",[$p['id']]);
            else TerritoryArmyReturn::dispatch($r,$p,$at);
        }
        $armies=RallyService::armies($r);$origin=WorldRules::origin((int)$r['leader_player_id'],(int)$r['leader_city_id'],$world);$speed=INF;$returnSpeed=INF;
        foreach($armies as $a){$snapshot=$a['army_snapshot']??self::snapshot((int)$a['player_id'],(int)$a['city_id'],$world,$a['troops'],['bonus_pct'=>$a['march_speed_bonus_pct']??0]);$speed=min($speed,(float)$snapshot['march_speed']);$returnSpeed=min($returnSpeed,(float)($snapshot['return_march_speed']??$snapshot['march_speed']));}
        $seconds=MarchSpeed::duration(hypot($r['target_x']-$origin['coord_x'],$r['target_y']-$origin['coord_y']),$speed,$world);
        $returnSeconds=MarchSpeed::duration(hypot($r['target_x']-$origin['coord_x'],$r['target_y']-$origin['coord_y']),$returnSpeed,$world);
        $rules=json_decode($c['rules_json'],true);$elig=json_decode($c['eligibility_json'],true);$target=TerritoryService::target($world,$c['target_id']);
        if(($target['owner_alliance_id']!==null||$target['kind']==='crown')&&($at+$seconds>=(int)$elig['window']['end']||$at+$seconds<(int)$elig['window']['start'])){self::cancelGathering($r,'Die Armee erreicht das Ziel außerhalb des angekündigten Kampffensters.',$at);return;}
        $db->execute("UPDATE rallies SET status='marching',launch_at=?,arrival_time=?,return_time=? WHERE id=?",[TerritoryService::date($at),TerritoryService::date($at+$seconds),TerritoryService::date($at+$seconds+$returnSeconds),$r['id']]);$db->execute("UPDATE rally_participants SET status='marching' WHERE rally_id=? AND status='pending'",[$r['id']]);$db->execute("UPDATE territory_campaigns SET status='marching' WHERE id=?",[$c['id']]);
    }
    public static function tick(?int $world=null,?int $now=null,int $limit=100): void
    {
        if(!TerritoryService::available())return;$db=Connection::getInstance();$now??=time();
        WorldRules::combatLock(function()use($world,$now,$limit,$db):void{
            for($i=0;$i<$limit;$i++){
                $params=[TerritoryService::date($now)];if($world!==null)$params[]=$world;
                $id=$db->query("SELECT id FROM rallies WHERE target_kind='territory' AND status IN ('gathering','marching','returning') AND CASE WHEN status='gathering' THEN launch_at WHEN status='marching' THEN arrival_time ELSE return_time END<=?".($world===null?'':' AND world_id=?')." ORDER BY CASE WHEN status='gathering' THEN launch_at WHEN status='marching' THEN arrival_time ELSE return_time END,id LIMIT 1",$params)->fetchColumn();
                if(!$id)break;
                $db->transaction(function()use($db,$id,$now):void{
                    $r=$db->query('SELECT * FROM rallies WHERE id=? FOR UPDATE',[$id])->fetch();
                    WorldContext::run((int)$r['world_id'],function()use($db,$r):void{
                        if($r['status']==='gathering'){self::launch($r,TerritoryService::timestamp($r['launch_at']));return;}
                        if($r['status']==='marching'){$result=self::resolve($r,RallyService::armies($r));$meta=json_decode($r['result_json'],true)?:[];$result+=$meta;$db->execute("UPDATE rallies SET status='returning',result_json=? WHERE id=?",[json_encode($result,JSON_THROW_ON_ERROR),$r['id']]);return;}
                        if($r['status']==='returning'){$result=json_decode($r['result_json'],true);RallyService::refund($result['armies']??RallyService::armies($r));$db->execute("UPDATE rallies SET status='complete' WHERE id=?",[$r['id']]);$db->execute("UPDATE rally_participants SET status='returned' WHERE rally_id=? AND status='marching'",[$r['id']]);}
                    });
                });
            }
        });
    }
    public static function resolve(array $r,array $armies): array
    {
        $db=Connection::getInstance();$world=(int)$r['world_id'];$at=TerritoryService::timestamp($r['arrival_time']);$c=$db->query('SELECT * FROM territory_campaigns WHERE id=? FOR UPDATE',[$r['territory_campaign_id']])->fetch();
        if(!in_array($c['status'],['gathering','marching'],true))return json_decode($c['result_json'],true)?:['outcome'=>'cancelled','armies'=>$armies];
        $t=TerritoryService::target($world,$c['target_id'],true);$rules=json_decode($c['rules_json'],true);$aid=(int)$c['alliance_id'];$eligible=[];$returned=[];
        foreach($armies as $a){if(WorldRules::alliance((int)$a['player_id'],$world)===$aid)$eligible[]=$a;else $returned[]=$a+['survivors'=>$a['troops'],'loot'=>[],'wounded'=>[],'dead'=>[]];}
        $reason=null;
        if(!$eligible||WorldRules::alliance((int)$r['leader_player_id'],$world)!==$aid)$reason='Die Rally-Allianz ist nicht mehr gültig.';
        elseif($t['kind']!=='crown'&&(int)$t['owner_alliance_id']===$aid)$reason='Eure Allianz kontrolliert das Gebiet bereits.';
        elseif(($t['owner_alliance_id']!==null||$t['kind']==='crown')&&!TerritoryRules::window($rules,$at,$t['kind']==='crown')['open'])$reason='Das Kampfzeitfenster ist geschlossen.';
        if($reason){$result=['outcome'=>'cancelled','cancelled'=>true,'reason'=>$reason,'armies'=>$armies];self::finish($c,$result,$at,'cancelled');return $result;}
        // Defense at this instant includes every prior arrival for this exact target.
        while(TerritoryGarrison::tick($world,$at,1000,$t['id'])===1000){}
        $defenders=[];$defAid=(int)$t['owner_alliance_id'];
        if($t['kind']==='crown'){$control=$db->query('SELECT alliance_id FROM territory_crown_control WHERE cycle_id=? AND objective=? FOR UPDATE',[$c['crown_cycle_id'],$c['objective']])->fetchColumn();$defAid=(int)$control;if($defAid===$aid){$result=['outcome'=>'cancelled','cancelled'=>true,'reason'=>'Dieses Belagerungsziel steht bereits unter eurer Kontrolle.','armies'=>$armies];self::finish($c,$result,$at,'cancelled');return $result;}}
        if($t['kind']!=='crown')foreach($db->query("SELECT * FROM territory_garrisons WHERE world_id=? AND target_id=? AND status='active' AND alliance_id=? ORDER BY id FOR UPDATE",[$world,$t['id'],$defAid])->fetchAll() as $g)$defenders[]=['player_id'=>(int)$g['player_id'],'city_id'=>(int)$g['city_id'],'troops'=>json_decode($g['troops_json'],true),'army_snapshot'=>json_decode($g['army_snapshot']??'null',true),'garrison_id'=>(int)$g['id']];
        $attack=0.;$defense=0.;$attSnapshots=[];$defSnapshots=[];$attackTroops=[];$defenseTroops=[];
        foreach($eligible as $a)foreach($a['troops'] as $code=>$count)$attackTroops[$code]=($attackTroops[$code]??0)+$count;
        foreach($defenders as $a)foreach($a['troops'] as $code=>$count)$defenseTroops[$code]=($defenseTroops[$code]??0)+$count;
        foreach($eligible as $a){$s=self::combatSnapshot($a,$world,true,$defenseTroops);$attack+=array_sum(array_column($s['troops'],'strength'));$attSnapshots[]=$s;}
        foreach($defenders as $a){$s=self::combatSnapshot($a,$world,false,$attackTroops);$defense+=array_sum(array_column($s['troops'],'strength'));$defSnapshots[]=$s;}
        // Neutral militia disappears on ordinary ownership; crown objectives retain their fixed guards.
        $npcCount=(!$defAid||$t['kind']==='crown')?(int)$rules['npc_troops'][$t['kind']]:0;$npcScore=PvpRules::strength(TerritoryRules::NPC_TROOP_CODE,$npcCount,[]);$defense=($defense+$npcScore)*(1.1+(int)$t['fortification']*.01);
        $support=(int)$db->query("SELECT COUNT(*) FROM territory_support WHERE world_id=? AND target_id=? AND alliance_id=? AND kind='supply' AND created_at<=? AND created_at>=DATE_SUB(?,INTERVAL 1 DAY)",[$world,$t['id'],$aid,TerritoryService::date($at),TerritoryService::date($at)])->fetchColumn();$attack*=1+min(.1,$support*.01);
        $won=PvpRules::attackerWins($attack,$defense);$result=['outcome'=>$won?'attacker_wins':'defender_wins','cancelled'=>false,'armies'=>$returned,'target_name'=>$t['name'],'territory_id'=>$t['id'],'campaign_id'=>(int)$c['id'],'objective'=>$c['objective'],'attacker_score'=>(int)$attack,'defender_score'=>(int)$defense,'npc_troops'=>$npcCount,'at'=>TerritoryService::date($at)];
        foreach($eligible as $i=>$a){$loss=PvpRules::losses($a['troops'],$defense<=0?0:($won?.10:.30),$a['army_snapshot']['buffs']??BuffEngine::getBuffs((int)$a['player_id'],$world),$defenseTroops);HospitalService::addWounded((int)$a['city_id'],$loss['wounded']);$result['armies'][]=$a+$loss+['loot'=>[]];$attSnapshots[$i]=CombatReport::settle($attSnapshots[$i],$loss);if($won)TerritoryEconomy::reward($t,(int)$a['player_id'],$aid,'campaign:'.$c['id'],['gold'=>$rules['conquest_reward_gold']],$at);}
        foreach($defenders as $i=>$a){$loss=PvpRules::losses($a['troops'],$won?.30:.10,$a['army_snapshot']['buffs']??BuffEngine::getBuffs((int)$a['player_id'],$world),$attackTroops);HospitalService::addWounded((int)$a['city_id'],$loss['wounded']);$db->execute('UPDATE territory_garrisons SET troops_json=? WHERE id=?',[json_encode($loss['survivors']),$a['garrison_id']]);$defSnapshots[$i]=CombatReport::settle($defSnapshots[$i],$loss);if(!$won)TerritoryEconomy::reward($t,(int)$a['player_id'],$defAid,'defense:'.$c['id'],['gold'=>$rules['conquest_reward_gold']],$at);}
        $result['combat']=['version'=>1,'attacker'=>CombatReport::side($attSnapshots,(int)$attack),'defender'=>CombatReport::side($defSnapshots,(int)$defense),'npc_troops'=>$npcCount,'fortification'=>(int)$t['fortification']];
        if($won){
            if($t['kind']==='crown')TerritoryCrown::control((int)$c['crown_cycle_id'],$c['objective'],$aid,$at);
            else TerritoryEconomy::transfer($t,$aid,$at,(int)$c['id'],$rules,'campaign:'.$c['id']);
        }
        foreach(array_merge($eligible,$defenders) as $a){$report=$result;unset($report['armies']);$report['battle_kind']='territory';$report['perspective']=isset($a['garrison_id'])?'defender':'attacker';$report['monster_name']=$t['name'];$report['loot']=[];$report['troops']=[];
            $db->execute('INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at)VALUES(?,?,?,5,?,?,?,?,?,?)',[$world,$a['player_id'],$a['city_id'],$c['id'],$t['x'],$t['y'],$result['outcome'],json_encode($report,JSON_THROW_ON_ERROR),TerritoryService::date($at)]);
        }
        self::finish($c,$result,$at,$won?'won':'lost');return $result;
    }
    private static function finish(array $c,array $result,int $at,string $status): void
    {
        Connection::getInstance()->execute('UPDATE territory_campaigns SET status=?,slot_reserved=0,result_json=?,resolved_at=? WHERE id=?',[$status,json_encode($result,JSON_THROW_ON_ERROR),TerritoryService::date($at),$c['id']]);
    }
    private static function combatSnapshot(array $a,int $world,bool $rally,array $enemyTroops=[]): array
    {
        $snap=$a['army_snapshot']??self::snapshot((int)$a['player_id'],(int)$a['city_id'],$world,$a['troops'],['bonus_pct'=>$a['march_speed_bonus_pct']??0],$rally);
        $buffs=\Conquer\Game\Player\TalentEffects::combat($snap['buffs'],$rally?'pvp':'field_defense',$rally,$enemyTroops);
        $report=$snap['report'];
        foreach($report['troops'] as &$troop)$troop['strength']=PvpRules::strength((int)$troop['code'],(int)$troop['sent'],$buffs);unset($troop);
        // A stationed army may already have taken losses in an earlier defense.
        foreach($report['troops'] as &$troop){$count=(int)($a['troops'][$troop['code']]??0);$troop['strength']=$troop['sent']>0?$troop['strength']*$count/$troop['sent']:0;$troop['sent']=$count;}unset($troop);
        return $report;
    }
    public static function cancelGathering(array $r,string $reason,?int $at=null): void
    {
        $at??=time();$db=Connection::getInstance();
        RallyService::refund([['player_id'=>(int)$r['leader_player_id'],'city_id'=>(int)$r['leader_city_id'],'troops'=>json_decode($r['troops_json'],true)]]);
        foreach($db->query("SELECT * FROM rally_participants WHERE rally_id=? AND status IN ('joining','pending') FOR UPDATE",[$r['id']])->fetchAll() as $p)TerritoryArmyReturn::dispatch($r,$p,$at);
        $meta=json_decode($r['result_json'],true)?:[];$meta['reason']=$reason;$db->execute("UPDATE rallies SET status='cancelled',result_json=? WHERE id=?",[json_encode($meta),$r['id']]);self::cancelled($r,$reason,$at);
    }
}
