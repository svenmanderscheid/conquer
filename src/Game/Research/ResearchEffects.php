<?php
declare(strict_types=1);
namespace Conquer\Game\Research;

use Conquer\Game\City\TroopData;

/** Applies the units and categories in the complete research catalogue. */
final class ResearchEffects
{
    public static function normalize(array $buffs): array
    {
        $result = [];
        foreach ($buffs as $key => $value) {
            // Advanced economic technologies improve the same resource or timer.
            $key = preg_replace('/^advanced_(?=(?:food|wood|lumber|stone|gold|crystal|research|construction)_)/', '', $key);
            $key = preg_replace('/^wood_/', 'lumber_', $key);
            $result[$key] = ($result[$key] ?? 0) + $value;
        }
        foreach (['food','lumber','stone','gold'] as $resource) {
            $key = $resource . '_production';
            $result[$key] = ($result[$key] ?? 0.0) + ($result['resource_production'] ?? 0.0);
        }
        return $result;
    }

    public static function troopType(int $code): string
    {
        return [1=>'infantry',2=>'ranged',3=>'cavalry'][(int)(TroopData::get($code)['type'] ?? 1)];
    }

    public static function limits(array $buffs): array
    {
        return [
            'march_capacity' => self::wholeCount((float)($buffs['base_march_capacity']??5000) * (1 + max(0.0, (float)($buffs['march_size'] ?? 0))) + max(0,(int)($buffs['march_capacity_flat']??0))),
            'march_slots' => 3 + max(0, (int)($buffs['march_limit'] ?? 0)),
            'gather_march_slots' => max(0,(int)($buffs['gather_march_slots']??0)),
        ];
    }

    public static function training(int $code, array $buffs, float $boost = 1): array
    {
        $type = self::troopType($code);
        $factor = max(.05, 1 + (float)($buffs[$type.'_training_cost'] ?? 0) + (float)($buffs['training_cost'] ?? 0));
        $cost = TroopData::trainingCost($code,1);
        return [
            'cost' => array_map(static fn($amount)=>$amount*$factor,$cost),
            'speed_multiplier' => max(.05, (1 + (float)($buffs['training_speed'] ?? 0) + (float)($buffs[$type.'_training_speed'] ?? 0)) * $boost),
            'max_count' => self::wholeCount((float)($buffs['base_'.$type.'_training_amount']??$buffs['base_training_amount']??500) * (1 + max(0.0, (float)($buffs[$type.'_training_amount'] ?? 0)))),
        ];
    }

    public static function carryPerTroop(int $code, array $buffs): float
    {
        return (float)(TroopData::get($code)['carry']??0) * max(1, 1 + (float)($buffs['troops_storage'] ?? 0) + (float)($buffs[self::troopType($code).'_storage'] ?? 0));
    }

    public static function carryCapacity(array $troops, array $buffs): int
    {
        $total = array_sum($troops);
        $carry = 0.0;
        foreach ($troops as $code=>$count) { $carry += $count*self::carryPerTroop((int)$code,$buffs); }
        return $total > 0 ? self::wholeCount($carry) : 0;
    }

    private static function wholeCount(float $value): int
    {
        // Decimal percentages such as 0.15 must not turn 57,500 places into
        // 57,499 through binary floating point representation alone.
        return (int) floor($value + 1.0e-8);
    }

    public static function armyBuffs(array $buffs, array $troops, bool $rally = false): array
    {
        $types = [];
        foreach ($troops as $code=>$count) { if ($count>0) $types[self::troopType((int)$code)] = true; }
        foreach (['infantry'=>'infantrys','ranged'=>'archers','cavalry'=>'cavalrys'] as $type=>$source) {
            foreach (['hp','def','atk'] as $stat) {
                $extra = $rally ? (float)($buffs[$source.'_'.$stat.'_when_participating_a_rally'] ?? 0) : 0;
                if (count($types)===1 && isset($types[$type])) {
                    $composition = $type === 'ranged' ? 'archer' : $type;
                    $extra += (float)($buffs[$source.'_'.$stat.'_when_composed_of_'.$composition.'_only'] ?? 0);
                }
                $buffs[$type.'_'.$stat] = ($buffs[$type.'_'.$stat] ?? 0) + $extra;
            }
        }
        return $buffs;
    }
}
