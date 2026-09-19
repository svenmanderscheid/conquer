<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Game\City\TroopData;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** One source for mission-specific troop travel speeds and ETA read models. */
final class MarchSpeed
{
    public static function generic(int $code,array $buffs,float $skinMultiplier=1): float
    {
        return self::typedBase($code,$buffs)*self::bonus($buffs,'')*$skinMultiplier;
    }

    public static function monster(int $code,array $buffs,float $skinMultiplier=1): float
    {
        return self::typedBase($code,$buffs)*self::bonus($buffs,'talent_hunt_march')*$skinMultiplier;
    }

    public static function charm(int $code,float $skinMultiplier=1): float
    {
        return (float)(TroopData::get($code)['march_speed']??TroopData::get($code)['speed']??65)*$skinMultiplier;
    }

    public static function pvp(int $code,array $buffs,float $skinMultiplier=1): float
    {
        return self::typedBase($code,$buffs)*self::bonus($buffs,'talent_pvp_march')*$skinMultiplier;
    }

    public static function rally(int $code,array $buffs,bool $monster,float $skinMultiplier=1): float
    {
        $talent=$monster?'talent_hunt_march':'talent_pvp_march';
        $bonus=max(0.0,(float)($buffs['march_speed']??0)+(float)($buffs[$talent]??0)+(float)($buffs['troop_speed_when_participating_a_rally']??0));
        return self::typedBase($code,$buffs)*(1+$bonus)*$skinMultiplier;
    }

    /** @return array<string,float> Values already include the equipped skin once. */
    public static function readModel(int $code,array $buffs,float $skinMultiplier=1): array
    {
        $generic=self::generic($code,$buffs,$skinMultiplier);
        return [
            'march_speed'=>$generic,
            'monster_march_speed'=>self::monster($code,$buffs,$skinMultiplier),
            'monster_rally_speed'=>self::rally($code,$buffs,true,$skinMultiplier),
            'charm_march_speed'=>self::charm($code,$skinMultiplier),
            'pvp_march_speed'=>self::pvp($code,$buffs,$skinMultiplier),
            'pvp_rally_speed'=>self::rally($code,$buffs,false,$skinMultiplier),
            'reinforce_march_speed'=>$generic,
            'shrine_neutral_speed'=>$generic,
            'shrine_occupied_speed'=>self::pvp($code,$buffs,$skinMultiplier),
        ];
    }

    private static function typedBase(int $code,array $buffs): float
    {
        return (float)(TroopData::get($code)['march_speed']??TroopData::get($code)['speed']??65)
            *BuffEngine::effectiveMultiplier($buffs,ResearchEffects::troopType($code),'spd');
    }

    private static function bonus(array $buffs,string $talent): float
    {
        return 1+max(0.0,(float)($buffs['march_speed']??0)+($talent===''?0.0:(float)($buffs[$talent]??0)));
    }
}
