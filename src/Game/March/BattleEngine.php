<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Game\City\TroopData;

/**
 * Deterministic single-pass battle resolution (SPEC §9).
 *
 * MVP simplifications:
 *  - No research/VIP/charm buffs (base stats only)
 *  - No counter-cycle modifier vs monsters (monsters are neutral)
 *  - mortality_rate = 0 → all losses are permanent for MVP (hospital phase 2)
 */
final class BattleEngine
{
    private function __construct() {}

    /**
     * Resolve a monster attack.
     *
     * @param array<int, int>      $attackerTroops  troop_code → count
     * @param array<string, mixed> $monster         field_monsters row (hp_current, monster_code, ...)
     * @param array<string, mixed> $monsterDef      monsters.json entry for this monster
     *
     * @return array{
     *   outcome: string,
     *   attacker_losses: array<int,int>,
     *   attacker_survivors: array<int,int>,
     *   new_monster_hp: int,
     *   monster_killed: bool,
     *   report: array<string,mixed>
     * }
     */
    public static function resolveMonster(
        array $attackerTroops,
        array $monster,
        array $monsterDef,
    ): array {
        // ── Monster stats ─────────────────────────────────────────────────────
        $hpCurrent      = (int) $monster['hp_current'];
        $hpPerUnit      = (float) ($monsterDef['stats']['hp']      ?? 100);
        $atkPerUnit     = (float) ($monsterDef['stats']['attack']   ?? 50);
        $defPerUnit     = (float) ($monsterDef['stats']['defense']  ?? 30);
        $aliveUnits     = max(1, (int) round($hpCurrent / max(1, $hpPerUnit)));

        $monsterAtkPool = $atkPerUnit * $aliveUnits;
        $monsterDefPool = $defPerUnit * $aliveUnits;
        // Total absorption = remaining HP + defense pool (SPEC §9.5)
        $monsterAbsorption = max(1, $hpCurrent + $monsterDefPool);

        // ── Attacker stats ────────────────────────────────────────────────────
        $attackerDamage    = 0.0;
        $attackerAbsorption = 0.0;
        $troopDetails      = [];

        foreach ($attackerTroops as $code => $count) {
            if ($count <= 0) continue;
            $def = TroopData::get($code);
            if ($def === null) continue;

            $attackerDamage     += $count * (float) $def['attack'];
            $attackerAbsorption += $count * ((float) $def['hp'] + (float) $def['defense']);
            $troopDetails[$code] = ['count' => $count, 'def' => $def];
        }

        $attackerAbsorption = max(1, $attackerAbsorption);

        // ── Monster damage + outcome ──────────────────────────────────────────
        $monsterLossRatio = min(1.0, $attackerDamage / $monsterAbsorption);
        $hpDamage         = (int) round($hpCurrent * $monsterLossRatio);
        $newMonsterHp     = max(0, $hpCurrent - $hpDamage);
        $monsterKilled    = $newMonsterHp === 0;

        // Win  → attacker dealt enough damage to kill the monster → no troop losses.
        // Loss → monster survives → troops retreat injured (proportional to how
        //         outmatched the attacker was, capped at 80% injuries).
        if ($monsterKilled) {
            $attackerLossRatio = 0.0;
            $outcome           = 'attacker_wins';
        } else {
            $attackerLossRatio = min(0.8, $monsterAtkPool / $attackerAbsorption);
            $outcome           = 'defender_wins';
        }

        // ── Attacker losses (per troop type, proportional) ───────────────────
        $attackerLosses    = [];
        $attackerSurvivors = [];

        foreach ($troopDetails as $code => $info) {
            $lost = (int) round($info['count'] * $attackerLossRatio);
            $attackerLosses[$code]    = $lost;
            $attackerSurvivors[$code] = $info['count'] - $lost;
        }

        // ── Build report data ─────────────────────────────────────────────────
        $reportTroops = [];
        foreach ($troopDetails as $code => $info) {
            $reportTroops[] = [
                'code'     => $code,
                'name'     => $info['def']['name'],
                'tier'     => $info['def']['tier'],
                'sent'     => $info['count'],
                'injured'  => $attackerLosses[$code],
                'survived' => $attackerSurvivors[$code],
            ];
        }

        $report = [
            'monster_name'        => ($monsterDef['name'] ?? 'Monster') . ' Lv ' . ($monsterDef['level'] ?? '?'),
            'monster_hp_before'   => $hpCurrent,
            'monster_hp_after'    => $newMonsterHp,
            'monster_killed'      => $monsterKilled,
            'monster_atk_pool'    => round($monsterAtkPool),
            'attacker_damage'     => round($attackerDamage),
            'attacker_injury_ratio' => round($attackerLossRatio, 4),
            'monster_loss_ratio'    => round($monsterLossRatio, 4),
            'troops'              => $reportTroops,
            'outcome'             => $outcome,
        ];

        return [
            'outcome'            => $outcome,
            'attacker_losses'    => $attackerLosses,
            'attacker_survivors' => $attackerSurvivors,
            'new_monster_hp'     => $newMonsterHp,
            'monster_killed'     => $monsterKilled,
            'report'             => $report,
        ];
    }
}
