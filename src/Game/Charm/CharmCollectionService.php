<?php
declare(strict_types=1);

namespace Conquer\Game\Charm;

use Conquer\Db\Connection;

/** Resolves all due collectors for one charm by the global (arrival_time,id) order. */
final class CharmCollectionService
{
    private function __construct() {}

    /** @return array{winner_march_id:?int,winner_player_id:?int,settled_march_ids:list<int>} */
    public static function resolveDue(int $worldId, int $charmId): array
    {
        $db = Connection::getInstance();
        return $db->transaction(function(Connection $db) use ($worldId,$charmId): array {
            $charm = $db->query('SELECT * FROM map_charms WHERE id=? AND world_id=? FOR UPDATE',[$charmId,$worldId])->fetch();
            $marches = $db->query(
                "SELECT * FROM marches
                 WHERE world_id=? AND march_type=6 AND target_id=? AND state='marching'
                   AND arrival_time<=UTC_TIMESTAMP()
                 ORDER BY arrival_time,id FOR UPDATE",
                [$worldId,$charmId],
            )->fetchAll();
            if (!$marches) return ['winner_march_id'=>null,'winner_player_id'=>null,'settled_march_ids'=>[]];

            $winner = null;
            if ($charm && $charm['collected_by'] === null) {
                foreach ($marches as $candidate) {
                    if ((int)$candidate['target_x'] === (int)$charm['coord_x']
                        && (int)$candidate['target_y'] === (int)$charm['coord_y']
                        && strtotime((string)$candidate['arrival_time'].' UTC') < strtotime((string)$charm['expires_at'].' UTC')) {
                        $winner = $candidate;
                        break;
                    }
                }
            }

            if ($winner) {
                $winnerPlayer = (int)$winner['player_id'];
                $db->execute(
                    'UPDATE map_charms SET collected_by=?,collected_at=? WHERE id=? AND collected_by IS NULL',
                    [$winnerPlayer,$winner['arrival_time'],$charmId],
                );
                $db->execute(
                    'INSERT INTO player_charms_active
                        (player_id,world_id,stat_category,grade,charm_code,source_map_charm_id,
                         source_march_id,bonus_pct,activated_at,expires_at)
                     VALUES (?,?,?,?,?,?,?,?, ?,DATE_ADD(?,INTERVAL ? SECOND))
                     ON DUPLICATE KEY UPDATE
                         grade=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(grade),grade),
                         charm_code=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(charm_code),charm_code),
                         bonus_pct=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(bonus_pct),bonus_pct),
                         expires_at=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(expires_at),expires_at),
                         source_map_charm_id=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(source_map_charm_id),source_map_charm_id),
                         source_march_id=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(source_march_id),source_march_id),
                         activated_at=IF(VALUES(activated_at)>activated_at OR (VALUES(activated_at)=activated_at AND VALUES(source_march_id)>COALESCE(source_march_id,0)),VALUES(activated_at),activated_at)',
                    [$winnerPlayer,$worldId,$charm['stat_category'],$charm['grade'],$charm['charm_code'],$charmId,
                     $winner['id'],$charm['bonus_pct'],$winner['arrival_time'],$winner['arrival_time'],$charm['effect_duration_seconds']],
                );
            }

            $settled=[];
            foreach ($marches as $march) {
                $isWinner=$winner && (int)$march['id']===(int)$winner['id'];
                $reason=$isWinner?'charm_collected':($winner!==null||($charm && $charm['collected_by']!==null)?'charm_already_collected':'charm_unavailable');
                $db->execute(
                    "UPDATE marches SET state='returning',
                         return_time=DATE_ADD(arrival_time,INTERVAL GREATEST(5,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),
                         haul_json=? WHERE id=? AND state='marching'",
                    [json_encode(['survivors'=>json_decode((string)$march['troops_json'],true)?:[],'loot'=>[],'reason'=>$reason]),$march['id']],
                );
                $settled[]=(int)$march['id'];
            }
            return [
                'winner_march_id'=>$winner ? (int)$winner['id'] : null,
                'winner_player_id'=>$winner ? (int)$winner['player_id'] : null,
                'settled_march_ids'=>$settled,
            ];
        });
    }
}
