<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Game\City\TroopData;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};

/** Pure rules shared by actual city combat and the hypothetical battle calculator. */
final class PvpRules
{
    public static function strength(int $code, int $count, array $buffs): float
    {
        $def = TroopData::get($code);
        if (!$def || $count <= 0) return 0;
        $type = ResearchEffects::troopType($code);
        return $count * ($def['attack'] * BuffEngine::effectiveMultiplier($buffs, $type, 'atk')
            + .6 * $def['defense'] * BuffEngine::effectiveMultiplier($buffs, $type, 'def')
            + .2 * $def['hp'] * BuffEngine::effectiveMultiplier($buffs, $type, 'hp'));
    }

    public static function attackerWins(float $attack, float $defense): bool
    {
        return $attack > $defense;
    }

    public static function losses(array $troops, float $rate): array
    {
        $result = ['survivors'=>[], 'wounded'=>[], 'dead'=>[]];
        foreach ($troops as $code=>$count) {
            $lost = min((int)$count, (int)ceil($count * max(0, $rate)));
            $wounded = (int)floor($lost * .3);
            $result['survivors'][$code] = $count - $lost;
            $result['wounded'][$code] = $wounded;
            $result['dead'][$code] = $lost - $wounded;
        }
        return $result;
    }
}
