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
    public static function combat(array $buffs,string $context,bool $rally=false): array
    {
        $extra=match($context){
            'pvp'=>['atk'=>($buffs['talent_pvp_attack']??0)+($rally?($buffs['talent_pvp_rally_attack']??0):0),'hp'=>$buffs['talent_pvp_attacker_hp']??0],
            'city_defense'=>['atk'=>$buffs['talent_city_defender_attack']??0,'def'=>$buffs['talent_city_defense']??0,'hp'=>$buffs['talent_city_defender_hp']??0],
            'monster'=>['hp'=>($buffs['talent_monster_hp']??0)+($rally?($buffs['talent_boss_hp']??0):0)],
            default=>[],
        };
        foreach($extra as $stat=>$value)$buffs['troops_'.$stat]=($buffs['troops_'.$stat]??0)+$value;
        if($context==='monster'&&$rally)$buffs['vs_monster_attack']=($buffs['vs_monster_attack']??0)+($buffs['talent_boss_attack']??0);
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
