<?php
declare(strict_types=1);
namespace Conquer\Game\Dungeon;

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\World\LuxembourgGeography;
use Conquer\Game\World\WorldMapProfile;

/** Stable per-world locations, allocated under the same lock as cities and nodes. */
final class DungeonEntrance
{
    public static function registered(int $worldId, bool $lock = false): array
    {
        try {
            $db=Connection::getInstance();
            return $db->query('SELECT * FROM world_dungeon_entrances WHERE world_id=?'.($lock?' FOR UPDATE':''),[$worldId])->fetchAll();
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1]??0)!==1146) throw $e;
            return [];
        }
    }

    public static function forWorld(int $worldId): ?array
    {
        if (!WorldMapProfile::isLuxembourg($worldId)) return null;
        foreach (self::registered($worldId) as $row) {
            if ($row['dungeon_code']==='melusina_well') return self::describe($row);
        }
        $db=Connection::getInstance();
        // Never acquire the world lock after a caller has locked a city/quest/run.
        // The top-level state/action entry points allocate before their transaction.
        if ($db->getPdo()->inTransaction()) return null;
        try {
            $work=static function()use($db,$worldId):?array {
                WorldPlacement::lockWorld($db,$worldId);
                // Another request may have registered it while this one waited.
                foreach (self::registered($worldId, true) as $row) {
                    if ($row['dungeon_code']==='melusina_well') return self::describe($row);
                }
                for ($radius=0;$radius<=32;$radius++) for ($dy=-$radius;$dy<=$radius;$dy++) {
                    foreach (abs($dy)===$radius?range(-$radius,$radius):[-$radius,$radius] as $dx) {
                        $x=400+$dx;$y=850+$dy;
                        if (!LuxembourgGeography::isDryRectangle($x-1.5,$y-1.5,$x+1.5,$y+1.5,'03')) continue;
                        if (!WorldPlacement::canPlace($db,$worldId,'outpost',$x,$y)) continue;
                        $db->execute("INSERT INTO world_dungeon_entrances(world_id,dungeon_code,coord_x,coord_y,footprint) VALUES(?,'melusina_well',?,?,3)",[$worldId,$x,$y]);
                        return self::describe(['world_id'=>$worldId,'dungeon_code'=>'melusina_well','coord_x'=>$x,'coord_y'=>$y,'footprint'=>3]);
                    }
                }
                return null;
            };
            return $db->getPdo()->inTransaction()?$work():$db->transaction($work);
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1]??0)!==1146) throw $e;
            return null;
        }
    }

    private static function describe(array $row): array
    {
        return ['id'=>'melusina_well','dungeon_code'=>'melusina_well','world_id'=>(int)$row['world_id'],
            'x'=>(int)$row['coord_x'],'y'=>(int)$row['coord_y'],'coord_x'=>(int)$row['coord_x'],'coord_y'=>(int)$row['coord_y'],
            'footprint'=>(int)$row['footprint'],'canton_id'=>'03','name_key'=>'melusina.entrance_name',
            'art'=>'assets/world-lux-preview/art/dungeon.webp'];
    }
}
