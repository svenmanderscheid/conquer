<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Game\City\TroopData;

/**
 * Deterministic single-pass battle resolution (SPEC §9).
 *
 * Monsters are neutral. Applied research, equipment, charm and talent bonuses
 * are recorded with the result. Injured troops are settled through the hospital.
 */
final class BattleEngine
{
    private function __construct() {}

    /** Each owner's research and talents contribute only to that owner's troops. */
    public static function resolveMonsterArmies(array $armies, array $monster, array $definition): array
    {
        $combined=[];$base=[];$effective=[];$armyPower=0.0;
        foreach($armies as $army){
            $buffs=\Conquer\Game\Player\TalentEffects::combat($army['buffs']??[],'monster',true);
            $buffs=\Conquer\Game\Research\ResearchEffects::armyBuffs($buffs,$army['troops'],true);
            $armyPower+=ArmyPower::effective($army['troops'],$buffs);
            foreach($army['troops'] as $code=>$count){
                $def=TroopData::get((int)$code);if(!$def||$count<=0)continue;
                $combined[$code]=($combined[$code]??0)+$count;
                $type=\Conquer\Game\Research\ResearchEffects::troopType((int)$code);
                foreach(['atk'=>'attack','def'=>'defense','hp'=>'hp'] as $stat=>$field){
                    $raw=$count*$def[$field];$key=$type.'_'.$stat;
                    $base[$key]=($base[$key]??0)+$raw;
                    $value=$raw*\Conquer\Game\Research\BuffEngine::effectiveMultiplier($buffs,$type,$stat);
                    if($stat==='atk')$value*=1+max(0,(float)($buffs['vs_monster_attack']??0));
                    $effective[$key]=($effective[$key]??0)+$value;
                }
            }
        }
        // Fold already-applied bonuses into equivalent type multipliers. The
        // ordinary resolver then supplies the same battle and casualty rules.
        $folded=[];foreach($base as $key=>$value)$folded[$key]=$value>0?$effective[$key]/$value-1:0;
        $result=self::resolveMonster($combined,$monster,$definition,$folded,$armyPower);
        $result['report']['combat_snapshot']['power']=$armyPower;
        foreach($armies as &$army){
            $ownBuffs=\Conquer\Game\Player\TalentEffects::combat($army['buffs']??[],'monster',true);
            $ownBuffs=\Conquer\Game\Research\ResearchEffects::armyBuffs($ownBuffs,$army['troops'],true);
            $army['combat_snapshot']=MonsterReport::army($army['troops'],$ownBuffs);
            $army['survivors']=$army['troops'];$army['wounded']=[];unset($army['buffs']);
        }unset($army);
        foreach($result['attacker_losses'] as $code=>$lost){
            $weights=[];foreach($armies as $i=>$army)$weights[$i]=(int)($army['troops'][$code]??0);
            foreach(self::splitAmount($lost,$weights) as $i=>$count){
                if(!isset($armies[$i]['troops'][$code]))continue;
                $armies[$i]['wounded'][$code]=$count;
                $armies[$i]['survivors'][$code]-=$count;
            }
        }
        $result['armies']=$armies;
        return $result;
    }

    /** Largest-remainder distribution preserves the exact total, including tiny drops. */
    public static function splitAmount(int $amount,array $weights): array
    {
        $sum=array_sum($weights);$shares=array_fill_keys(array_keys($weights),0);
        if($sum<=0||$amount<=0)return $shares;
        $remainders=[];$used=0;
        foreach($weights as $key=>$weight){$exact=$amount*$weight/$sum;$shares[$key]=(int)floor($exact);$used+=$shares[$key];$remainders[$key]=$exact-$shares[$key];}
        arsort($remainders,SORT_NUMERIC);
        foreach($remainders as $key=>$unused){if($used>=$amount)break;if($weights[$key]>0){$shares[$key]++;$used++;}}
        return $shares;
    }

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
        array $buffs = [],
        ?float $armyPowerOverride = null,
    ): array {
        $buffs = \Conquer\Game\Player\TalentEffects::combat($buffs,'monster');
        $buffs = \Conquer\Game\Research\ResearchEffects::armyBuffs($buffs,$attackerTroops);
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

            $type = [1 => 'infantry', 2 => 'ranged', 3 => 'cavalry'][$def['type']] ?? null;
            $attackerDamage += $count * (float) $def['attack'] * \Conquer\Game\Research\BuffEngine::effectiveMultiplier($buffs, $type, 'atk');
            $attackerAbsorption += $count * ((float) $def['hp'] * \Conquer\Game\Research\BuffEngine::effectiveMultiplier($buffs, $type, 'hp') + (float) $def['defense'] * \Conquer\Game\Research\BuffEngine::effectiveMultiplier($buffs, $type, 'def'));
            $troopDetails[$code] = ['count' => $count, 'def' => $def];
        }

        $attackerDamage *= 1.0 + max(0.0, (float) ($buffs['vs_monster_attack'] ?? 0));
        $attackerAbsorption = max(1, $attackerAbsorption);

        // ── Power threshold, monster damage and outcome ───────────────────────
        $requiredPower=\Conquer\Game\Map\MonsterPower::currentRequired($monsterDef,$hpCurrent);
        $armyPower=max(0.0,$armyPowerOverride??ArmyPower::effective($attackerTroops,$buffs));
        $powerRatio=$requiredPower>0?$armyPower/$requiredPower:1.0;
        $monsterKilled=$powerRatio>=1.0;
        $monsterLossRatio=min(1.0,$powerRatio);
        $hpDamage=$monsterKilled?$hpCurrent:min($hpCurrent,max($armyPower>0?1:0,(int)round($hpCurrent*$monsterLossRatio)));
        $newMonsterHp=max(0,$hpCurrent-$hpDamage);

        // Early defeats are forgiving. Injury severity rises gradually with
        // underpower and monster level, but never returns to the former 80% cap.
        $level=max(1,min(10,(int)($monster['effective_monster_level']??$monsterDef['level']??1)));
        $levelFactor=.5+.5*($level-1)/9;
        if($monsterKilled){
            $attackerLossRatio=$powerRatio>=1.2?0.0:.03*(1.2-$powerRatio)/.2*$levelFactor;
            $outcome='attacker_wins';
        }else{
            $attackerLossRatio=min(.35,(.05+.30*(1-min(1.0,$powerRatio)))*$levelFactor);
            $outcome='defender_wins';
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
                'type'     => \Conquer\Game\Research\ResearchEffects::troopType((int)$code),
                'sent'     => $info['count'],
                'injured'  => $attackerLosses[$code],
                'survived' => $attackerSurvivors[$code],
            ];
        }

        $report = [
            'report_version'      => 3,
            'combat_snapshot'     => MonsterReport::army($attackerTroops, $buffs),
            'monster_snapshot'    => [
                'code'=>(int)($monster['monster_code'] ?? $monsterDef['code'] ?? 0),
                'name'=>$monsterDef['name'] ?? 'Monster',
                'level'=>(int)($monster['effective_monster_level'] ?? $monsterDef['level'] ?? 1),
                'art'=>$monsterDef['art'] ?? '',
                'count'=>$aliveUnits, 'attack'=>$monsterAtkPool, 'defense'=>$monsterDefPool,
                'hp'=>$hpCurrent, 'hp_after'=>$newMonsterHp,
                'required_power'=>$requiredPower,
            ],
            'monster_name'        => ($monsterDef['name'] ?? 'Monster') . ' Lv ' . ($monster['effective_monster_level'] ?? $monsterDef['level'] ?? '?'),
            'monster_hp_before'   => $hpCurrent,
            'monster_hp_after'    => $newMonsterHp,
            'monster_killed'      => $monsterKilled,
            'monster_atk_pool'    => round($monsterAtkPool),
            'attacker_damage'     => round($attackerDamage),
            'army_power'          => round($armyPower),
            'required_power'      => $requiredPower,
            'power_ratio'         => round($powerRatio,4),
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
