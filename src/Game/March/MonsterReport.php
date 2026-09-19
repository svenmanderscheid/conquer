<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchEffects;
use Conquer\Game\Player\{LordLevel, MasteryService};
use Conquer\Game\Treasure\TreasureService;

/** Historical values only: viewing a report must never recalculate its combat bonuses. */
final class MonsterReport
{
    /** Buffs have already received the battle's talent and composition modifiers. */
    public static function army(array $troops, array $buffs): array
    {
        $army = ['count'=>0, 'power'=>0.0, 'attack'=>0.0, 'defense'=>0.0, 'hp'=>0.0, 'types'=>[], 'bonuses'=>[]];
        foreach (['infantry','cavalry','ranged'] as $type) {
            $army['types'][$type] = ['count'=>0, 'attack'=>0.0, 'defense'=>0.0, 'hp'=>0.0];
            foreach (['atk'=>'attack','def'=>'defense','hp'=>'hp'] as $stat=>$field) {
                $multiplier = BuffEngine::effectiveMultiplier($buffs, $type, $stat);
                if ($stat === 'atk') $multiplier *= 1 + max(0, (float)($buffs['vs_monster_attack'] ?? 0));
                $army['bonuses'][$type][$stat] = round(($multiplier - 1) * 100, 4);
            }
        }
        foreach ($troops as $code=>$count) {
            $def = TroopData::get((int)$code);
            if (!$def || $count <= 0) continue;
            $type = ResearchEffects::troopType((int)$code);
            $army['count'] += $count;
            $army['types'][$type]['count'] += $count;
            foreach (['atk'=>'attack','def'=>'defense','hp'=>'hp'] as $stat=>$field) {
                $multiplier = BuffEngine::effectiveMultiplier($buffs, $type, $stat);
                if ($stat === 'atk') $multiplier *= 1 + max(0, (float)($buffs['vs_monster_attack'] ?? 0));
                $value = $count * $def[$field] * $multiplier;
                $army[$field] += $value;
                $army['types'][$type][$field] += $value;
            }
        }
        $army['power']=ArmyPower::effective($troops,$buffs);
        return $army;
    }

    public static function capture(int $playerId, int $cityId, int $worldId): array
    {
        $db = Connection::getInstance();
        $identity = $db->query('SELECT COALESCE(k.display_name,p.username) AS name, COALESCE(k.avatar,\'knight\') AS avatar,
            c.coord_x AS x,c.coord_y AS y,a.tag AS alliance_tag
            FROM cities c JOIN players p ON p.id=c.player_id
            LEFT JOIN kingdom_profiles k ON k.player_id=p.id
            LEFT JOIN alliance_members m ON m.player_id=p.id AND m.world_id=c.world_id
            LEFT JOIN alliances a ON a.id=m.alliance_id
            WHERE c.id=? AND c.player_id=? AND c.world_id=?', [$cityId,$playerId,$worldId])->fetch() ?: [];
        $equipped = [];
        foreach (TreasureService::getPlayerTreasures($playerId,$worldId) as $item) {
            if ($item['equipped_slot'] === null) continue;
            $equipped[] = array_intersect_key($item, array_flip(['treasure_code','name','name_de','icon','grade','level','equipped_slot','stats_at_level']));
        }
        $talents = [];
        $nodes = MasteryService::nodes();
        foreach ($db->query('SELECT talent_code,rank FROM player_lord_talents WHERE player_id=? AND world_id=? AND rank>0', [$playerId,$worldId])->fetchAll() as $rank) {
            $node = $nodes[$rank['talent_code']] ?? null;
            if ($node) $talents[] = ['code'=>$rank['talent_code'],'name'=>$node['name'],'rank'=>(int)$rank['rank'],'branch'=>$node['branch']];
        }
        return ['identity'=>$identity, 'equipment'=>$equipped, 'hunter'=>[
            'level'=>LordLevel::snapshot($playerId,$worldId)['level'], 'talents'=>$talents,
        ]];
    }
}
