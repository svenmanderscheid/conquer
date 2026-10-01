<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Game\City\TroopData;

/** Versioned encounter rules travel with the server's saved monster definition. */
final class BossMechanics
{
    private const GRUMWALD_FIRST = 20202401;
    private const GRUMWALD_LAST = 20202410;
    private const FAMILIES = [
        'frostgrimm_ice_armor' => ['first'=>20202101, 'last'=>20202110, 'rule'=>[
            'required_power_percent'=>12, 'counter_type'=>'infantry', 'counter_power_percent'=>50,
        ]],
        'sandmaul_sandstorm' => ['first'=>20202201, 'last'=>20202210, 'rule'=>[
            'army_power_reduction_percent'=>10, 'counter_type'=>'cavalry', 'counter_power_percent'=>50,
        ]],
        'glutramm_ember_backlash' => ['first'=>20202301, 'last'=>20202310, 'rule'=>[
            'injury_increase_percent'=>20, 'counter_type'=>'ranged', 'counter_power_percent'=>50,
        ]],
        'daemmerhorn_runic_barrier' => ['first'=>20200501, 'last'=>20200510, 'rule'=>[
            'required_power_percent'=>15, 'active_above_hp_percent'=>50,
            'counter_type'=>'balanced', 'counter_power_percent'=>30,
        ]],
    ];

    /** Only the current catalog gets new rules; never call this on a saved order. */
    public static function currentDefinition(array $definition): array
    {
        $code = (int)($definition['code'] ?? 0);
        if ($code >= self::GRUMWALD_FIRST && $code <= self::GRUMWALD_LAST) {
            $definition['boss_mechanic'] = [
                'id' => 'grumwald_regeneration',
                'version' => 1,
                'heal_percent' => 12,
                'counter_type' => 'ranged',
                'counter_power_percent' => 50,
            ];
        }
        foreach (self::FAMILIES as $id => $family) {
            if ($code >= $family['first'] && $code <= $family['last'] && ($definition['type'] ?? null) === 'rally') {
                $definition['boss_mechanic'] = ['id'=>$id, 'version'=>1] + $family['rule'];
                break;
            }
        }
        return $definition;
    }

    /** Adjust the actual power comparison, before server-side luck is applied. */
    public static function beforeBattle(array $definition, array $troops, int $hpCurrent, int $requiredPower, float $armyPower): ?array
    {
        $rule = self::savedRule($definition);
        if ($rule === null || $rule['id'] === 'glutramm_ember_backlash') return null;
        $effect = self::formation($rule, $troops);
        $active = !$effect['countered'];
        if ($rule['id'] === 'daemmerhorn_runic_barrier') {
            $maximum = max(1, (int)round((float)($definition['stats']['hp'] ?? 1) * max(1, (int)($definition['amount'] ?? 1))));
            $effect['phase_active'] = $hpCurrent * 100 > $maximum * $rule['active_above_hp_percent'];
            $active = $active && $effect['phase_active'];
        }
        if ($rule['id'] === 'sandmaul_sandstorm') {
            $effect['army_power_before'] = max(0.0, $armyPower);
            $effect['army_power_after'] = $active ? $effect['army_power_before'] * (100 - $rule['army_power_reduction_percent']) / 100 : $effect['army_power_before'];
            $effect['active'] = $effect['army_power_after'] < $effect['army_power_before'];
        } else {
            $effect['required_power_before'] = max(1, $requiredPower);
            $effect['required_power_after'] = $active ? (int)ceil($effect['required_power_before'] * (100 + $rule['required_power_percent']) / 100) : $effect['required_power_before'];
            $effect['active'] = $effect['required_power_after'] > $effect['required_power_before'];
        }
        return $effect;
    }

    /** A surviving Glutramm increases wounds relatively; the existing 35% cap remains. */
    public static function afterInjuries(array $definition, array $troops, float $injuryRatio, bool $monsterKilled): ?array
    {
        $rule = self::savedRule($definition);
        if ($rule === null || $rule['id'] !== 'glutramm_ember_backlash') return null;
        $effect = self::formation($rule, $troops);
        $effect['injury_ratio_before'] = max(0.0, min(.35, $injuryRatio));
        $effect['injury_ratio_after'] = !$monsterKilled && !$effect['countered']
            ? min(.35, $effect['injury_ratio_before'] * (100 + $rule['injury_increase_percent']) / 100)
            : $effect['injury_ratio_before'];
        $effect['active'] = $effect['injury_ratio_after'] > $effect['injury_ratio_before'];
        return $effect;
    }

    /** Accept only an explicitly saved, supported rule for this exact boss family. */
    private static function savedRule(array $definition): ?array
    {
        $rule = $definition['boss_mechanic'] ?? null;
        if (!is_array($rule) || !is_string($rule['id'] ?? null) || ($rule['version'] ?? null) !== 1
            || ($definition['type'] ?? null) !== 'rally') return null;
        $family = self::FAMILIES[$rule['id']] ?? null;
        $code = (int)($definition['code'] ?? 0);
        if ($family === null || $code < $family['first'] || $code > $family['last']
            || ($rule['counter_type'] ?? null) !== $family['rule']['counter_type']
            || array_diff(array_keys($rule), ['id','version',...array_keys($family['rule'])])) return null;
        foreach ($family['rule'] as $parameter => $default) {
            if ($parameter === 'counter_type') continue;
            $value = $rule[$parameter] ?? null;
            $maximum = in_array($parameter, ['army_power_reduction_percent','active_above_hp_percent'], true) ? 99 : 100;
            if ($parameter === 'counter_power_percent' && $rule['counter_type'] === 'balanced') $maximum = 33;
            if (!is_int($value) || $value < 1 || $value > $maximum) return null;
        }
        return $rule;
    }

    private static function formation(array $rule, array $troops): array
    {
        $power = ['infantry'=>0.0, 'ranged'=>0.0, 'cavalry'=>0.0];
        foreach ($troops as $code => $count) {
            $unit = TroopData::get((int)$code);
            if (!$unit || (int)$count <= 0) continue;
            $type = [1=>'infantry', 2=>'ranged', 3=>'cavalry'][(int)$unit['type']] ?? null;
            if ($type !== null) $power[$type] += (int)$count * max(0.0, (float)($unit['power'] ?? 0));
        }
        $total = array_sum($power);
        $counter = $rule['counter_type'] === 'balanced' ? min($power) : $power[$rule['counter_type']];
        return $rule + [
            'countered'=>$total > 0 && $counter * 100 >= $total * $rule['counter_power_percent'],
            'counter_power'=>$counter, 'total_base_power'=>$total,
            'counter_power_share_percent'=>$total > 0 ? 100 * $counter / $total : 0.0,
            'type_power_share_percent'=>array_map(static fn(float $value): float => $total > 0 ? 100 * $value / $total : 0.0, $power),
        ];
    }

    /**
     * Restore part of a nonlethal hit. Base troop power, not headcount or buffs,
     * determines the counter across the whole army which reached this battle.
     * A legacy definition without a rule deliberately returns null.
     */
    public static function afterDamage(array $definition, array $troops, int $hpBefore, int $hpAfter): ?array
    {
        $rule = $definition['boss_mechanic'] ?? null;
        $code = (int)($definition['code'] ?? 0);
        if (!is_array($rule) || $code < self::GRUMWALD_FIRST || $code > self::GRUMWALD_LAST
            || ($rule['id'] ?? null) !== 'grumwald_regeneration' || ($rule['version'] ?? null) !== 1
            || ($rule['counter_type'] ?? null) !== 'ranged') {
            return null;
        }
        $healPercent = $rule['heal_percent'] ?? null;
        $counterPercent = $rule['counter_power_percent'] ?? null;
        if (!is_int($healPercent) || $healPercent < 0 || $healPercent >= 100
            || !is_int($counterPercent) || $counterPercent <= 0 || $counterPercent > 100) {
            return null;
        }

        $totalPower = 0.0;
        $rangedPower = 0.0;
        foreach ($troops as $code => $count) {
            $unit = TroopData::get((int)$code);
            if (!$unit || $count <= 0) continue;
            $power = (int)$count * max(0.0, (float)($unit['power'] ?? 0));
            $totalPower += $power;
            if ((int)$unit['type'] === 2) $rangedPower += $power;
        }
        // Never round the formation before deciding whether it meets the threshold.
        $countered = $totalPower > 0 && $rangedPower * 100 >= $totalPower * $counterPercent;
        $hpAfter = max(0, min($hpBefore, $hpAfter));
        $damage = max(0, $hpBefore - $hpAfter);
        $restored = $hpAfter > 0 && !$countered ? (int)floor($damage * $healPercent / 100) : 0;
        return $rule + [
            'countered' => $countered,
            'counter_power' => $rangedPower,
            'total_base_power' => $totalPower,
            'counter_power_share_percent' => $totalPower > 0 ? 100 * $rangedPower / $totalPower : 0.0,
            'hp_before_regeneration' => $hpAfter,
            'hp_restored' => $restored,
            'hp_after_regeneration' => $hpAfter + $restored,
            'damage_before_regeneration' => $damage,
            'damage_after_regeneration' => $damage - $restored,
        ];
    }
}
