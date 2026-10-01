<?php
declare(strict_types=1);

namespace Conquer\Game\Tutorial;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

/** Read-only milestones from committed gameplay receipts, scoped to one city/world. */
final class BeginnerJourney
{
    public static function state(int $playerId, int $worldId): array
    {
        WorldContext::current($worldId);
        $city = WorldContext::city($playerId, $worldId);
        $db = Connection::getInstance();
        $exists = static fn(string $sql, array $args): bool => (bool)$db->query($sql, $args)->fetchColumn();
        $member = $db->query('SELECT m.alliance_id FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id AND a.world_id=m.world_id WHERE m.player_id=? AND m.world_id=?', [$playerId, $worldId])->fetchColumn();

        // A dispatched or recalled empty march is not a successful delivery.
        $gathered = $exists("SELECT 1 FROM marches WHERE player_id=? AND world_id=? AND origin_city_id=? AND march_type=9 AND state='complete' AND (
            COALESCE(JSON_EXTRACT(haul_json,'$.loot.food'),0)>0 OR
            COALESCE(JSON_EXTRACT(haul_json,'$.loot.lumber'),0)>0 OR
            COALESCE(JSON_EXTRACT(haul_json,'$.loot.stone'),0)>0 OR
            COALESCE(JSON_EXTRACT(haul_json,'$.loot.gold'),0)>0 OR
            COALESCE(JSON_EXTRACT(haul_json,'$.loot.gems'),0)>0) LIMIT 1", [$playerId, $worldId, $city['id']]);
        $monster = $exists('SELECT 1 FROM monster_kill_receipts WHERE winner_player_id=? AND world_id=? LIMIT 1', [$playerId, $worldId])
            || $exists("SELECT 1 FROM battle_reports WHERE attacker_id=? AND attacker_city_id=? AND world_id=? AND target_type=3 AND outcome='attacker_wins' LIMIT 1", [$playerId, $city['id'], $worldId]);
        // CharmCollectionService writes this reason only for the winning collector.
        // Keep the receipt after the buff expires; an active buff alone is not progress.
        $charm = $exists("SELECT 1 FROM marches WHERE player_id=? AND world_id=? AND origin_city_id=? AND march_type=6 AND state IN ('returning','complete') AND JSON_UNQUOTE(JSON_EXTRACT(haul_json,'$.reason'))='charm_collected' LIMIT 1", [$playerId, $worldId, $city['id']]);
        $helped = $exists('SELECT 1 FROM community_help_log l JOIN community_help_requests h ON h.id=l.request_id WHERE l.helper_id=? AND h.world_id=? AND h.player_id<>? AND l.seconds_removed>0 LIMIT 1', [$playerId, $worldId, $playerId]);

        $helpAvailable = false;
        if ($member && !$helped && (int)$db->query('SELECT COUNT(*) FROM community_help_log WHERE helper_id=? AND created_at>=UTC_DATE()', [$playerId])->fetchColumn() < 30) {
            $helpAvailable = $exists("SELECT 1 FROM community_help_requests h
                JOIN alliance_members m ON m.player_id=h.player_id AND m.alliance_id=h.alliance_id AND m.world_id=h.world_id
                JOIN cities c ON c.id=h.city_id AND c.player_id=h.player_id AND c.world_id=h.world_id
                LEFT JOIN building_queue b ON h.queue_type='building' AND b.id=h.queue_id AND b.city_id=h.city_id
                LEFT JOIN research_queue r ON h.queue_type='research' AND r.id=h.queue_id AND r.player_id=h.player_id AND r.world_id=h.world_id
                WHERE h.world_id=? AND h.alliance_id=? AND h.player_id<>?
                AND h.help_count<h.max_helps AND h.reduced_seconds<FLOOR(h.initial_seconds*0.3)
                AND ((h.queue_type='building' AND b.is_processed=0 AND b.finishes_at>DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 SECOND))
                    OR (h.queue_type='research' AND r.is_processed=0 AND r.finishes_at>DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 SECOND)))
                AND NOT EXISTS(SELECT 1 FROM community_help_log l WHERE l.request_id=h.id AND l.helper_id=?) LIMIT 1", [$worldId, $member, $playerId, $playerId]);
        }

        return [
            'player_id'=>$playerId, 'world_id'=>$worldId,
            'progress'=>['gather'=>$gathered, 'monster'=>$monster, 'charm'=>$charm, 'alliance_help'=>$helped],
            'alliance_member'=>(bool)$member, 'help_available'=>$helpAvailable,
        ];
    }
}
