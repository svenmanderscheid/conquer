<?php
declare(strict_types=1);
namespace Conquer\Game\Player;

/** Context-specific bonuses never leak into unrelated army types. */
final class TalentEffects
{
    public static function atomic(callable $fn): mixed
    {
        $db=\Conquer\Db\Connection::getInstance();
        return $db->getPdo()->inTransaction()?$fn($db):$db->transaction($fn);
    }
    public static function combat(array $buffs,string $context,bool $rally=false,array $enemyTroops=[]): array
    {
        $extra=match($context){
            'pvp'=>['atk'=>($buffs['talent_pvp_attack']??0)+($rally?($buffs['talent_pvp_rally_attack']??0):0),'hp'=>$buffs['talent_pvp_attacker_hp']??0],
            'city_defense'=>['atk'=>$buffs['talent_city_defender_attack']??0,'def'=>$buffs['talent_city_defense']??0,'hp'=>$buffs['talent_city_defender_hp']??0],
            'monster'=>['hp'=>($buffs['talent_monster_hp']??0)+($rally?($buffs['talent_boss_hp']??0):0)],
            default=>[],
        };
        foreach($extra as $stat=>$value)$buffs['troops_'.$stat]=($buffs['troops_'.$stat]??0)+$value;
        if($context==='monster'&&$rally)$buffs['vs_monster_attack']=($buffs['vs_monster_attack']??0)+($buffs['talent_boss_attack']??0);
        if($context==='city_defense')$buffs['infantry_atk']=($buffs['infantry_atk']??0)+($buffs['talent_infantry_city_atk']??0);
        if(in_array($context,['pvp','city_defense','field_defense'],true)){
            $shares=self::powerShares($enemyTroops);
            foreach(['infantry','ranged','cavalry'] as $type){
                foreach(['atk','hp'] as $stat)foreach($shares as $enemy=>$share)
                    $buffs[$type.'_'.$stat]=($buffs[$type.'_'.$stat]??0)+($buffs['talent_'.$type.'_'.$stat.'_vs_'.$enemy]??0)*$share;
                $factor=self::lossFactor($buffs,$type,$enemyTroops);
                // The PvP resolver uses weighted troop strength; resistance also
                // strengthens the defence component against that enemy composition.
                $buffs[$type.'_def']=(1+(float)($buffs[$type.'_def']??0))/$factor-1;
            }
        }
        return $buffs;
    }

    public static function powerShares(array $troops): array
    {
        $power=array_fill_keys(['infantry','ranged','cavalry'],0.0);
        foreach($troops as $code=>$count){
            $def=\Conquer\Game\City\TroopData::get((int)$code);
            if($def&&$count>0)$power[\Conquer\Game\Research\ResearchEffects::troopType((int)$code)]+=$count*(float)$def['power'];
        }
        $total=array_sum($power);
        return array_map(static fn($p)=>$total>0?$p/$total:0.0,$power);
    }

    public static function formation(array $buffs,array $troops,bool $rally=false): array
    {
        $shares=self::powerShares($troops);
        if(($shares['infantry']??0)>=.7)$buffs['infantry_def']=($buffs['infantry_def']??0)+($buffs['talent_infantry_formation_def']??0);
        if(($shares['ranged']??0)>=.7)$buffs['ranged_atk']=($buffs['ranged_atk']??0)+($buffs['talent_ranged_formation_atk']??0);
        if(min($shares)>=.2)foreach(['atk','def'] as $stat)$buffs['troops_'.$stat]=($buffs['troops_'.$stat]??0)+($buffs['talent_mixed_combat']??0);
        if($rally){
            $buffs['troops_atk']=($buffs['troops_atk']??0)+($buffs['talent_rally_attack']??0);
            $buffs['ranged_atk']=($buffs['ranged_atk']??0)+($buffs['talent_ranged_rally_atk']??0);
        }
        return $buffs;
    }

    public static function lossFactor(array $buffs,string $type,array $enemyTroops): float
    {
        $factor=1.0;
        foreach(self::powerShares($enemyTroops) as $enemy=>$share)$factor+=(float)($buffs['talent_'.$type.'_damage_from_'.$enemy]??0)*$share;
        return max(.05,min(1.0,$factor));
    }

    public static function cavalryMarch(array $buffs,array $troops,bool $return=false): array
    {
        if(self::powerShares($troops)['cavalry']>=.7){
            $buffs['talent_pvp_march']=($buffs['talent_pvp_march']??0)+($buffs['talent_cavalry_pvp_roundtrip']??0)+($return?0:($buffs['talent_cavalry_pvp_march']??0));
        }
        return $buffs;
    }

    public static function monsterLoot(array $loot,array $buffs): array
    {
        foreach(['food','wood','lumber','stone','gold']as$key)if(isset($loot[$key]))$loot[$key]=(int)floor($loot[$key]*(1+max(0,$buffs['talent_monster_loot']??0))+1e-8);
        return $loot;
    }

    public static function gather(array $buffs): array
    {
        $buffs['troops_storage']=($buffs['troops_storage']??0)+($buffs['talent_carry']??0);
        return $buffs;
    }
}
