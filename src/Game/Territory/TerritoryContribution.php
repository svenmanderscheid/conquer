<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;

use Conquer\Db\Connection;

/** A read-only account of actual contributions, scoped to the current alliance. */
final class TerritoryContribution
{
    public static function summary(int $player, int $world, int $alliance, ?int $now = null): array
    {
        $db = Connection::getInstance();
        $member = $db->query('SELECT alliance_id FROM alliance_members WHERE world_id=? AND player_id=?', [$world, $player])->fetchColumn();
        if ($alliance <= 0 || (int)$member !== $alliance) return [];
        $until = gmdate('Y-m-d H:i:s', $now ?? time());
        $since = gmdate('Y-m-d H:i:s', ($now ?? time()) - 7 * 86400);
        $support = $db->query('SELECT kind,COUNT(*) AS total FROM territory_support WHERE world_id=? AND alliance_id=? AND player_id=? AND created_at>=? AND created_at<=? GROUP BY kind', [$world,$alliance,$player,$since,$until])->fetchAll(\PDO::FETCH_KEY_PAIR);
        $victories = (int)$db->query("SELECT COUNT(*) FROM territory_rewards WHERE world_id=? AND alliance_id=? AND player_id=? AND created_at>=? AND created_at<=? AND (event_key LIKE 'campaign:%' OR event_key LIKE 'defense:%')", [$world,$alliance,$player,$since,$until])->fetchColumn();
        $garrisons = (int)$db->query("SELECT COUNT(*) FROM territory_garrisons WHERE world_id=? AND alliance_id=? AND player_id=? AND status='active'", [$world,$alliance,$player])->fetchColumn();
        // Union identities first: helping twice never means two participating people.
        $involved = (int)$db->query("SELECT COUNT(DISTINCT m.player_id) FROM alliance_members m JOIN (
            SELECT player_id FROM territory_support WHERE world_id=? AND alliance_id=? AND created_at BETWEEN ? AND ?
            UNION SELECT player_id FROM territory_rewards WHERE world_id=? AND alliance_id=? AND created_at BETWEEN ? AND ?
            UNION SELECT player_id FROM territory_garrisons WHERE world_id=? AND alliance_id=? AND status='active'
        ) contributions ON contributions.player_id=m.player_id WHERE m.world_id=? AND m.alliance_id=?", [$world,$alliance,$since,$until,$world,$alliance,$since,$until,$world,$alliance,$world,$alliance])->fetchColumn();
        return ['since'=>$since,'until'=>$until,'days'=>7,'support'=>array_map('intval',$support),'victories'=>$victories,'active_garrisons'=>$garrisons,
            'participating_members'=>$involved,'members'=>(int)$db->query('SELECT COUNT(*) FROM alliance_members WHERE world_id=? AND alliance_id=?',[$world,$alliance])->fetchColumn()];
    }
}
