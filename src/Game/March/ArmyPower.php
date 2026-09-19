<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** One comparable power value for a formation, including its combat bonuses. */
final class ArmyPower
{
    private function __construct() {}

    /** Buffs must already contain the context and composition effects for this army. */
    public static function effective(array $troops,array $buffs): float
    {
        $power=0.0;
        foreach($troops as$code=>$count){
            $def=\Conquer\Game\City\TroopData::get((int)$code);
            if(!$def||(int)$count<=0)continue;
            $power+=(int)$count*self::unit($def,$buffs);
        }
        return $power;
    }

    /** Power weights attack, defence and HP bonuses equally; monster attack affects its attack third. */
    public static function unit(array $troop,array $buffs): float
    {
        $type=ResearchEffects::troopType((int)$troop['code']);
        $atk=BuffEngine::effectiveMultiplier($buffs,$type,'atk')*(1+max(0.0,(float)($buffs['vs_monster_attack']??0)));
        $def=BuffEngine::effectiveMultiplier($buffs,$type,'def');
        $hp=BuffEngine::effectiveMultiplier($buffs,$type,'hp');
        return max(0.0,(float)($troop['power']??0))*($atk+$def+$hp)/3;
    }
}
