<?php
declare(strict_types=1);
namespace Conquer\Game\Map;
use Conquer\Db\Connection;

/** Small, approachable frontier encounters keep the first session playable. */
final class FrontierService
{
    public static function refresh(int $playerId, array $city): void
    {
        $db = Connection::getInstance();
        $worldId = (int) ($city['world_id'] ?? 1);
        // Configured worlds are populated by the scheduled worker, which owns
        // density, limits and time windows. Starter replenishment cannot bypass it.
        if (\Conquer\Game\World\WorldSettings::get($worldId)['configured']) {
            \Conquer\Game\World\WorldSpawnService::tick($worldId,false,'game');
            return;
        }
        $lock = 'conquer-frontier-' . $worldId;
        if ((int) $db->query('SELECT GET_LOCK(?, 1)', [$lock])->fetchColumn() !== 1) { return; }
        try {
            $db->transaction(static function (Connection $db) use ($city, $playerId, $worldId): void {
                $size = WorldPlacement::lockWorld($db, $worldId);
                self::repairCollisions($db, $worldId);
                $recent = $db->query('SELECT 1 FROM frontier_spawns WHERE player_id = ? AND refreshed_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)', [$playerId])->fetchColumn();
                if ($recent) { return; }
                $near = $db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=? AND monster_code = 20209901 AND hp_current>0 AND ABS(coord_x - ?) < 12 AND ABS(coord_y - ?) < 12', [$worldId, $city['coord_x'], $city['coord_y']])->fetchColumn();
                $nodeCount = $db->query('SELECT COUNT(*) FROM field_objects WHERE world_id=? AND object_type BETWEEN 1 AND 5 AND resource_amount > 0 AND expires_at > UTC_TIMESTAMP() AND ABS(coord_x - ?) < 12 AND ABS(coord_y - ?) < 12', [$worldId, $city['coord_x'], $city['coord_y']])->fetchColumn();
                $types = array_merge(array_fill(0, max(0, 2 - (int) $near), 0), (int) $nodeCount < 4 ? [1, 2, 3, 4] : []);
                foreach ($types as $type) {
                    $kind = $type === 0 ? 'monster' : 'resource';
                    for ($attempt = 0; $attempt < 60; $attempt++) {
                        $x = max(1, min($size - 2, (int) $city['coord_x'] + random_int(-9, 9)));
                        $y = max(1, min($size - 2, (int) $city['coord_y'] + random_int(-9, 9)));
                        if (!WorldPlacement::canPlace($db, $worldId, $kind, $x, $y)) { continue; }
                        if ($type === 0) {
                            $scout = MonsterData::get(20209901);
                            $hp = max(1, (int)round($scout['stats']['hp'] * $scout['amount']));
                            $db->execute('INSERT INTO field_monsters (world_id,monster_code,coord_x,coord_y,hp_current) VALUES (?,20209901,?,?,?)', [$worldId, $x, $y, $hp]);
                            \Conquer\Game\World\RegionalSpawns::stamp('field_monsters',$db->lastInsertId(),$worldId,$x,$y);
                        } else {
                            $db->execute('INSERT INTO field_objects (world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES (?,?,?,?,1,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))', [$worldId, $x, $y, $type, FieldObjectData::capacity($type,1), FieldObjectData::capacity($type,1)]);
                            \Conquer\Game\World\RegionalSpawns::stamp('field_objects',$db->lastInsertId(),$worldId,$x,$y);
                        }
                        break;
                    }
                }
                $db->execute('INSERT INTO frontier_spawns (player_id) VALUES (?) ON DUPLICATE KEY UPDATE refreshed_at=UTC_TIMESTAMP()', [$playerId]);
            });
        } finally { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); }
    }

    /**
     * Repair legacy positions without resetting stock, health, expiry or identity.
     * The caller must hold the world placement lock inside its transaction.
     * Busy targets are revisited on a later refresh, including returning armies.
     */
    public static function repairCollisions(Connection $db, int $worldId = 1, int $maxMoves = 64): int
    {
        $moved = 0;
        foreach (['field_objects' => 'resource', 'field_monsters' => 'monster'] as $table => $kind) {
            $filter = $kind === 'resource'
                ? 'f.object_type BETWEEN 1 AND 5 AND f.resource_amount>0 AND f.expires_at>UTC_TIMESTAMP()'
                : 'f.hp_current>0';
            // Scan all live targets so valid low IDs cannot starve later invalid positions.
            $targets = $db->query('SELECT f.id,f.coord_x,f.coord_y'.($kind==='monster'?',f.monster_code':'').' FROM ' . $table . ' f WHERE f.world_id=? AND ' . $filter . ' ORDER BY f.id', [$worldId])->fetchAll();
            foreach ($targets as $target) {
                if ($moved >= $maxMoves) { return $moved; }
                $id = (int) $target['id'];
                $placementKind=$kind==='monster'?WorldPlacement::monsterKind((int)$target['monster_code']):$kind;
                if (WorldPlacement::canPlace($db, $worldId, $placementKind, (int) $target['coord_x'], (int) $target['coord_y'], $id)) { continue; }
                // A dispatch also locks this target before inserting its march. Re-read
                // current data after waiting, then use current reads for active armies.
                $columns = $kind === 'resource' ? ',f.gatherer_march_id' : ',f.monster_code';
                $current = $db->query('SELECT f.id,f.coord_x,f.coord_y' . $columns . ' FROM ' . $table . ' f WHERE f.id=? AND f.world_id=? AND ' . $filter . ' FOR UPDATE', [$id, $worldId])->fetch();
                if (!$current || ($kind === 'resource' && $current['gatherer_march_id'] !== null)) { continue; }
                $x = (int) $current['coord_x'];
                $y = (int) $current['coord_y'];
                $targetType = $kind === 'resource' ? 5 : 3;
                $active = $db->query("SELECT id FROM marches WHERE world_id=? AND ((target_x=? AND target_y=?) OR (target_type=? AND target_id=?)) AND state IN ('marching','resolving','returning') LIMIT 1 FOR UPDATE", [$worldId, $x, $y, $targetType, $id])->fetchColumn();
                if ($active !== false) { continue; }
                $rally = $db->query("SELECT id FROM rallies WHERE world_id=? AND target_x=? AND target_y=? AND status IN ('gathering','marching','returning') LIMIT 1 FOR UPDATE", [$worldId, $x, $y])->fetchColumn();
                if ($rally !== false) { continue; }
                if (WorldPlacement::canPlace($db, $worldId, $placementKind, $x, $y, $id)) { continue; }
                $destination = WorldPlacement::findNear($db, $worldId, $placementKind, $x, $y, $id, 24, $kind==='monster'?(MonsterData::get((int)$current['monster_code'])['biome']??null):null);
                if ($destination !== null) {
                    $db->execute('UPDATE ' . $table . ' SET coord_x=?,coord_y=? WHERE id=? AND world_id=?', [$destination[0], $destination[1], $id, $worldId]);
                    $moved++;
                }
            }
        }
        return $moved;
    }
}
