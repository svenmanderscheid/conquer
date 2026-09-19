<?php
declare(strict_types=1);

namespace Conquer\Game\Buff;

use Conquer\Db\Connection;

/** Read-only presentation of the timed effects used by the game. */
final class ActiveEffectService
{
    public static function forPlayer(int $playerId, int $worldId): array
    {
        $db = Connection::getInstance();
        // Map charms belong to their world; inventory boosts apply account-wide,
        // matching BuffEngine's scope. Never expose another player's effects.
        $charms = $db->query(
            'SELECT id,stat_category,grade,charm_code,source_map_charm_id,bonus_pct,activated_at,expires_at
             FROM player_charms_active
             WHERE player_id=? AND (source_map_charm_id IS NULL OR world_id=?) AND expires_at>UTC_TIMESTAMP()
             ORDER BY expires_at,id',
            [$playerId, $worldId],
        )->fetchAll();
        $effects = [];
        $mirrors = [];
        $types = ['production_boost'=>'resource_production', 'research_boost'=>'research_speed', 'training_boost'=>'training_speed'];
        foreach ($charms as $row) {
            $bonus = (float)$row['bonus_pct'];
            if ($bonus == 0.0) continue;
            $effects[] = [
                'id'=>'charm:'.$row['id'], 'kind'=>$bonus < 0 ? 'debuff' : 'bonus',
                'stat_category'=>$row['stat_category'], 'bonus_pct'=>$bonus,
                'grade'=>$row['grade'],
                'source'=>($row['source_map_charm_id'] !== null || str_starts_with((string)$row['charm_code'], '107')) ? 'charm' : 'boost',
                'activated_at'=>$row['activated_at'], 'expires_at'=>$row['expires_at'],
            ];
            if ($row['source_map_charm_id'] === null && in_array($row['stat_category'], $types, true)) {
                $mirrors[] = $row;
            }
        }
        $buffs = $db->query(
            'SELECT id,buff_type,multiplier,created_at,expires_at FROM active_buffs
             WHERE player_id=? AND expires_at>UTC_TIMESTAMP() ORDER BY expires_at,id', [$playerId],
        )->fetchAll();
        foreach ($buffs as $row) {
            $category = $types[$row['buff_type']] ?? null;
            $bonus = round(((float)$row['multiplier'] - 1) * 100, 4);
            if ($category === null || (float)$row['multiplier'] <= 0 || $bonus == 0.0) continue;
            // Inventory stores the same effect in both tables. Match one copy
            // by category, strength and deadline; independent stacked buffs stay visible.
            foreach ($mirrors as $key => $mirror) {
                if ($mirror['stat_category'] === $category && $mirror['expires_at'] === $row['expires_at']
                    && abs((float)$mirror['bonus_pct'] - $bonus) < 0.0001) {
                    unset($mirrors[$key]);
                    continue 2;
                }
            }
            $effects[] = [
                'id'=>'buff:'.$row['id'], 'kind'=>$bonus < 0 ? 'debuff' : 'bonus',
                'stat_category'=>$category, 'bonus_pct'=>$bonus, 'grade'=>null, 'source'=>'boost',
                'activated_at'=>$row['created_at'], 'expires_at'=>$row['expires_at'],
            ];
        }
        usort($effects, static fn(array $a, array $b): int => [$a['expires_at'], $a['id']] <=> [$b['expires_at'], $b['id']]);
        return $effects;
    }
}
