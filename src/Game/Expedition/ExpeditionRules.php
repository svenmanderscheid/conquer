<?php
declare(strict_types=1);

namespace Conquer\Game\Expedition;

use Conquer\Game\City\TroopData;
use Conquer\Game\Research\BuffEngine;

/** Fixed encounter rules; all contributions count only actual, needed progress. */
final class ExpeditionRules
{
    public const SUPPLIES = 1000;
    public const DEFENSES = 300;
    public const PASS = 300;
    public const BOSS_HP = 1800;
    public const MARCH_SECONDS = 20;
    public const RETURN_SECONDS = 20;
    public const MIN_CONTRIBUTION = 10;
    public const REWARD_COOLDOWN = 3600;
    public const REWARD = ['food' => 2000, 'lumber' => 2000, 'stone' => 1200, 'gold' => 600];

    public static function publicRules(array $buffs = [],float $finalSpeedMultiplier=1): array
    {
        return [
            'supply_target' => self::SUPPLIES, 'defenses_target' => self::DEFENSES,
            'pass_target' => self::PASS, 'boss_hp' => self::BOSS_HP,
            ...self::missionTiming($buffs,$finalSpeedMultiplier),
            'min_contribution' => self::MIN_CONTRIBUTION, 'max_active_missions' => 2,
            'max_troops_per_mission' => self::missionCapacity($buffs),
            'reward' => self::REWARD, 'reward_cooldown_seconds' => self::REWARD_COOLDOWN,
            'loss_rule' => 'Einführungsfeldzug: Keine Toten oder Verwundeten. Alle entsandten Truppen kehren nach ihrem Einsatz zurück. Verbrauchte Versorgung wird bei Abbruch nicht erstattet.',
            'strategy' => 'Die gastgebende Allianz zerstört die Schutzanlagen mit Angriffskraft, die Partnerallianz sichert den Pass mit Verteidigungskraft. Forschung und ausgerüstete Schätze zählen. 1.000 Nahrung versorgen beide. Danach greifen beide den Aschenfürsten an.',
        ];
    }

    /** @return array{troops:array<int,int>,damage:int} */
    public static function troops(mixed $input, int $capacity = 5000): array
    {
        if (!is_array($input) || count($input) > 15) {
            throw new ExpeditionException('INVALID_TROOPS', 'Bitte wähle vorhandene Truppen.');
        }
        $troops = [];
        $damage = 0;
        foreach ($input as $code => $count) {
            if (!preg_match('/^[1-9][0-9]*$/D', (string) $code)
                || !is_int($count) || $count < 0 || $count > $capacity
                || ($definition = TroopData::get((int) $code)) === null) {
                throw new ExpeditionException('INVALID_TROOPS', 'Truppentypen und ganze Truppenanzahlen sind erforderlich.');
            }
            if ($count === 0) { continue; }
            $troops[(int) $code] = $count;
            $damage += (int) $definition['attack'] * $count;
        }
        if (!$troops || array_sum($troops) > $capacity) {
            throw new ExpeditionException('INVALID_TROOPS', 'Entsende zwischen 1 und ' . number_format($capacity,0,',','.') . ' Truppen.');
        }
        ksort($troops);
        return ['troops' => $troops, 'damage' => $damage];
    }

    public static function active(string $phase): bool
    {
        return in_array($phase, ['planning', 'preparation', 'boss'], true);
    }

    public static function missionCapacity(array $buffs): int
    {
        return (int) floor(5000 * (1 + max(0.0,(float)($buffs['rally_attack_amount'] ?? 0)+(float)($buffs['talent_expedition_capacity']??0))) + 1.0e-8);
    }

    public static function missionTiming(array $buffs,float $finalSpeedMultiplier=1): array
    {
        $multiplier = 1 + max(0.0, (float)($buffs['troop_speed_when_participating_a_rally'] ?? 0)
            + (float)($buffs['troops_spd'] ?? 0) + (float)($buffs['march_speed'] ?? 0)+(float)($buffs['talent_hunt_march']??0));
        $multiplier*=max(.01,$finalSpeedMultiplier);
        return ['march_seconds'=>max(1,(int)ceil(self::MARCH_SECONDS/$multiplier)),
            'return_seconds'=>max(1,(int)ceil(self::RETURN_SECONDS/$multiplier))];
    }

    /** Combat strength is snapshotted when the army leaves the city. */
    public static function strength(array $troops, string $objective, array $buffs): int
    {
        $buffs = \Conquer\Game\Player\TalentEffects::combat($buffs,'monster',true);
        $buffs = \Conquer\Game\Research\ResearchEffects::armyBuffs($buffs,$troops,true);
        $stat = $objective === 'pass' ? 'defense' : 'attack';
        $strength = 0.0;
        foreach ($troops as $code => $count) {
            $definition = TroopData::get((int) $code);
            $type = [1 => 'infantry', 2 => 'ranged', 3 => 'cavalry'][(int) $definition['type']] ?? null;
            $strength += $count * (int) $definition[$stat] * BuffEngine::effectiveMultiplier($buffs, $type, $stat);
        }
        if ($stat === 'attack') { $strength *= 1 + max(0.0, (float) ($buffs['vs_monster_attack'] ?? 0.0)); }
        return max(1, (int) floor($strength));
    }
}
