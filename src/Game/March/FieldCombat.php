<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Player\TalentEffects;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** Field battles involve only the two deployed armies, with the usual PvP casualty split. */
final class FieldCombat
{
    public static function resolve(array &$attacker,array &$defender): array
    {
        $db=Connection::getInstance();
        $attack=self::strength($attacker);$defense=self::strength($defender,true)*1.1;
        $won=$attack>$defense;$armies=[$attacker,$defender];$losses=[];
        foreach($armies as $i=>$army){
            $troops=json_decode($army['troops_json'],true)?:[];
            $rate=($i===0)===$won?.1:.3;$loss=['survivors'=>[],'wounded'=>[],'dead'=>[]];
            foreach($troops as $code=>$count){
                $lost=min((int)$count,(int)ceil($count*$rate));$wounded=(int)floor($lost*.3);
                $loss['survivors'][$code]=$count-$lost;$loss['wounded'][$code]=$wounded;$loss['dead'][$code]=$lost-$wounded;
            }
            HospitalService::addWounded((int)$army['origin_city_id'],$loss['wounded']);
            $haul=json_decode($army['haul_json'],true)?:[];
            if(isset($haul['gather']))$haul['gather']['capacity']=min((int)$haul['gather']['capacity'],ResearchEffects::carryCapacity($loss['survivors'],TalentEffects::gather(BuffEngine::getBuffs((int)$army['player_id'],(int)$army['world_id']))));
            $db->execute('UPDATE marches SET troops_json=?,haul_json=? WHERE id=?',[json_encode($loss['survivors']),json_encode($haul),$army['id']]);
            if($i===0)$attacker['haul_json']=json_encode($haul);else $defender['haul_json']=json_encode($haul);
            $losses[]=$loss;
        }
        foreach($armies as $i=>$army){
            $other=$armies[1-$i];$loss=$losses[$i];$outcome=(($i===0)===$won)?'attacker_wins':'defender_wins';
            $name=$db->query('SELECT COALESCE(k.display_name,p.username) FROM players p LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?',[$other['player_id']])->fetchColumn();
            $report=['battle_kind'=>'field','perspective'=>$i===0?'attacker':'defender','target_name'=>$name,'monster_name'=>$name,'outcome'=>$outcome,'attacker_damage'=>(int)round($i===0?$attack:$defense),'defender_strength'=>(int)round($i===0?$defense:$attack),'monster_hp_after'=>0,'loot'=>[],'dead'=>$loss['dead'],'troops'=>[]];
            foreach(json_decode($army['troops_json'],true)?:[] as $code=>$count)$report['troops'][]=['code'=>(int)$code,'sent'=>$count,'survived'=>$loss['survivors'][$code],'injured'=>$loss['wounded'][$code],'dead'=>$loss['dead'][$code]];
            $db->execute('INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,defender_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at) VALUES(?,?,?,?,?,5,?,?,?,?,?,UTC_TIMESTAMP())',[$army['world_id'],$attacker['id'],$army['player_id'],$army['origin_city_id'],$other['player_id'],$attacker['target_id'],$attacker['target_x'],$attacker['target_y'],$outcome,json_encode($report,JSON_THROW_ON_ERROR)]);
        }
        $attacker['troops_json']=json_encode($losses[0]['survivors']);$defender['troops_json']=json_encode($losses[1]['survivors']);
        return ['won'=>$won];
    }

    private static function strength(array $march,bool $defending=false): float
    {
        $troops=json_decode($march['troops_json'],true)?:[];
        $buffs=TalentEffects::combat(ResearchEffects::armyBuffs(BuffEngine::getBuffs((int)$march['player_id'],(int)$march['world_id'],(int)$march['target_x'],(int)$march['target_y']),$troops),$defending?'field_defense':'pvp');
        $score=0.0;
        foreach($troops as $code=>$count){
            $def=TroopData::get((int)$code);if(!$def)continue;$type=ResearchEffects::troopType((int)$code);
            $score+=$count*($def['attack']*BuffEngine::effectiveMultiplier($buffs,$type,'atk')+.6*$def['defense']*BuffEngine::effectiveMultiplier($buffs,$type,'def')+.2*$def['hp']*BuffEngine::effectiveMultiplier($buffs,$type,'hp'));
        }
        return $score;
    }
}
