<?php
declare(strict_types=1);

namespace Conquer\Game\Charm;

use Conquer\Db\Connection;

/** Stable world-map DTO for uncollected, unexpired charms. */
final class CharmQueryService
{
    private function __construct() {}

    /** @return list<array<string,mixed>> */
    public static function inBounds(int $worldId, int $playerId, int $x, int $y, int $radius): array
    {
        if ($worldId < 1 || $playerId < 1 || $radius < 0 || $radius > 100) {
            throw new \InvalidArgumentException('Invalid charm viewport.');
        }
        $db=Connection::getInstance();
        if(!$db->query('SELECT id FROM cities WHERE world_id=? AND player_id=? LIMIT 1',[$worldId,$playerId])->fetchColumn()) {
            throw new \DomainException('Du besitzt in dieser Welt keine Stadt.',403);
        }
        $rows = $db->query(
            'SELECT id,world_id,coord_x,coord_y,stat_category,grade,charm_code,
                    bonus_pct,effect_duration_seconds,spawned_at,expires_at
             FROM map_charms
             WHERE world_id=? AND collected_by IS NULL AND expires_at>UTC_TIMESTAMP()
               AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ?
             ORDER BY coord_y,coord_x,id',
            [$worldId,$x-$radius,$x+$radius,$y-$radius,$y+$radius],
        )->fetchAll();

        return array_map(static fn(array $row): array => [
            'id'=>(int)$row['id'],
            'world_id'=>(int)$row['world_id'],
            'x'=>(int)$row['coord_x'],
            'y'=>(int)$row['coord_y'],
            'stat_category'=>(string)$row['stat_category'],
            'grade'=>(string)$row['grade'],
            'charm_code'=>(int)$row['charm_code'],
            'bonus_pct'=>(float)$row['bonus_pct'],
            'effect_duration_seconds'=>(int)$row['effect_duration_seconds'],
            'spawned_at'=>(string)$row['spawned_at'],
            'expires_at'=>(string)$row['expires_at'],
            'ownership'=>['player_id'=>null,'exclusive_until'=>null],
            'collectible'=>true,
        ], $rows);
    }
}
