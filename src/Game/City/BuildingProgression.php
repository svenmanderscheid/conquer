<?php
declare(strict_types=1);
namespace Conquer\Game\City;

use Conquer\Db\Connection;

/** Initial Conquer balancing; the guide supplies mechanics, not these curves. */
final class BuildingProgression
{
    public static function atLevels(array $levels): array
    {
        $castle=max(1,min(30,(int)($levels['castle']??1)));
        $barrack=max(1,min(30,(int)($levels['barrack']??1)));
        $storage=max(0,min(30,(int)($levels['storage']??0)));
        return [
            'base_march_capacity'=>5000+(int)floor(45000*($castle-1)/29),
            'base_training_amount'=>500+(int)floor(4500*($barrack-1)/29),
            'base_ranged_training_amount'=>self::trainingAmount((int)($levels['archery_range']??1)),
            'base_cavalry_training_amount'=>self::trainingAmount((int)($levels['stable']??1)),
            'storage_protection_flat'=>$storage>0?(int)round(10000*pow(1.2,$storage-1)):0,
        ];
    }

    public static function trainingAmount(int $level): int
    {
        return 500+(int)floor(4500*(max(1,min(30,$level))-1)/29);
    }

    public static function forPlayer(int $playerId,int $worldId): array
    {
        $levels=Connection::getInstance()->query('SELECT b.building_code,b.level FROM city_buildings b JOIN cities c ON c.id=b.city_id WHERE c.player_id=? AND c.world_id=?',[$playerId,$worldId])->fetchAll(\PDO::FETCH_KEY_PAIR);
        return self::atLevels($levels);
    }
}
