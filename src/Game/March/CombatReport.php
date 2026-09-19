<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Player\LordLevel;
use Conquer\Game\Player\MasteryService;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchEffects;
use Conquer\Game\Treasure\TreasureService;

/** Immutable, compact battle-time data. Never rebuild historical bonuses on read. */
final class CombatReport
{
    public static function army(array $army, array $buffs, float $scoreFactor = 1): array
    {
        $db = Connection::getInstance();
        $owner = $db->query("SELECT COALESCE(k.display_name,p.username) AS username,COALESCE(k.avatar,'knight') AS avatar,c.name,c.coord_x,c.coord_y,c.world_id FROM cities c JOIN players p ON p.id=c.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE c.id=?", [$army['city_id']])->fetch();
        $world = (int)$owner['world_id'];
        $pid = (int)$army['player_id'];
        $troops = [];
        foreach ($army['troops'] as $code => $count) {
            $def = TroopData::get((int)$code);
            if (!$def || $count <= 0) continue;
            $type = ResearchEffects::troopType((int)$code);
            $strength = $count * ($def['attack'] * BuffEngine::effectiveMultiplier($buffs, $type, 'atk')
                + .6 * $def['defense'] * BuffEngine::effectiveMultiplier($buffs, $type, 'def')
                + .2 * $def['hp'] * BuffEngine::effectiveMultiplier($buffs, $type, 'hp')) * $scoreFactor;
            $troops[] = ['code'=>(int)$code, 'name'=>$def['name'], 'type'=>$type, 'tier'=>(int)$def['tier'],
                'sent'=>(int)$count, 'unit_power'=>(int)$def['power'], 'strength'=>$strength];
        }
        $bonuses = [];
        foreach (['infantry','cavalry','ranged'] as $type) {
            foreach (['atk','def','hp'] as $stat) $bonuses[$type][$stat] = round((BuffEngine::effectiveMultiplier($buffs, $type, $stat) - 1) * 100, 4);
        }
        $equipment = [];
        foreach (TreasureService::getPlayerTreasures($pid, $world) as $item) {
            if ($item['equipped_slot'] === null) continue;
            $equipment[] = ['code'=>$item['treasure_code'], 'name'=>$item['name_de'], 'slot'=>$item['equipped_slot'],
                'level'=>$item['level'], 'grade'=>$item['grade'], 'icon'=>$item['icon']];
        }
        $talents = [];
        $nodes = MasteryService::nodes();
        foreach ($db->query('SELECT talent_code,rank FROM player_lord_talents WHERE player_id=? AND world_id=? ORDER BY talent_code', [$pid,$world])->fetchAll() as $rank) {
            $node = $nodes[$rank['talent_code']] ?? null;
            if ($node) $talents[] = ['code'=>$rank['talent_code'], 'name'=>$node['name'], 'rank'=>(int)$rank['rank'], 'branch'=>$node['branch']];
        }
        $xp = (int)$db->query('SELECT xp FROM player_lord_progress WHERE player_id=? AND world_id=?', [$pid,$world])->fetchColumn();
        return ['player_id'=>$pid, 'name'=>$owner['username'], 'avatar'=>$owner['avatar'], 'city_name'=>$owner['name'],
            'x'=>(int)$owner['coord_x'], 'y'=>(int)$owner['coord_y'], 'troops'=>$troops, 'bonuses'=>$bonuses,
            'equipment'=>$equipment, 'talents'=>$talents, 'hunter_level'=>LordLevel::levelFromTotalXp($xp)];
    }

    public static function settle(array $snapshot, array $loss): array
    {
        $snapshot['totals'] = ['sent'=>0, 'dead'=>0, 'injured'=>0, 'survived'=>0, 'power_lost'=>0];
        foreach ($snapshot['troops'] as &$troop) {
            $code = $troop['code'];
            $troop['dead'] = (int)($loss['dead'][$code] ?? 0);
            $troop['injured'] = (int)($loss['wounded'][$code] ?? 0);
            $troop['survived'] = (int)($loss['survivors'][$code] ?? 0);
            $troop['power_lost'] = ($troop['dead'] + $troop['injured']) * $troop['unit_power'];
            foreach ($snapshot['totals'] as $key => $value) $snapshot['totals'][$key] += $troop[$key];
        }
        unset($troop);
        return $snapshot;
    }

    public static function side(array $armies, int $score): array
    {
        $totals = ['sent'=>0, 'dead'=>0, 'injured'=>0, 'survived'=>0, 'power_lost'=>0];
        foreach ($armies as $army) foreach ($totals as $key=>$value) $totals[$key] += $army['totals'][$key];
        $types = [];
        foreach (['infantry','cavalry','ranged'] as $type) {
            $count = 0; $strength = 0; $bonuses = ['atk'=>0, 'def'=>0, 'hp'=>0];
            foreach ($armies as $army) {
                $troops = array_filter($army['troops'], static fn($t)=>$t['type'] === $type);
                $weight = array_sum(array_column($troops, 'sent'));
                $count += $weight; $strength += array_sum(array_column($troops, 'strength'));
                foreach ($bonuses as $stat=>$value) $bonuses[$stat] += $army['bonuses'][$type][$stat] * $weight;
            }
            foreach ($bonuses as $stat=>$value) $bonuses[$stat] = $count ? round($value / $count, 2) : null;
            $types[$type] = ['count'=>$count, 'strength'=>(int)round($strength), 'bonuses'=>$bonuses];
        }
        return ['armies'=>$armies, 'totals'=>$totals, 'types'=>$types, 'score'=>$score];
    }
}
