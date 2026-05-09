<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Db\Connection;

/**
 * Lazy resource production calculator (SPEC §3.4).
 *
 * Resources are NOT continuously updated in the DB.
 * They are stored as a snapshot + last_resource_update timestamp.
 * This class computes what the current values would be.
 *
 * On read:  call apply() to get computed values — no DB write.
 * On write: call persist() first to save the tick, then deduct resources.
 */
final class ResourceTick
{
    private function __construct() {}

    /**
     * Compute current resource values by applying production since last update.
     *
     * @param  array<string, mixed>              $city        Row from cities table
     * @param  array<string, array{level: int}>  $buildings   Current buildings
     * @param  float                             $speedFactor World speed multiplier
     * @param  array<string, int>                $vipBonuses  Optional VIP bonuses from VipService::bonuses()
     * @return array<string, mixed>              City array with updated resource values
     */
    public static function apply(
        array $city,
        array $buildings,
        float $speedFactor = 1.0,
        array $vipBonuses  = [],
    ): array {
        $elapsed = time() - strtotime($city['last_resource_update']);
        if ($elapsed <= 0) {
            return $city;
        }

        $caps = BuildingData::getStorageCaps($buildings);

        foreach (CityState::BUILDING_CODES as $code) {
            $resource = BuildingData::getProducedResource($code);
            if ($resource === null) {
                continue;
            }

            $level      = (int) ($buildings[$code]['level'] ?? 1);
            $hourlyRate = BuildingData::getHourlyRate($code, $level, $vipBonuses) * $speedFactor;
            $gained     = ($elapsed / 3600.0) * $hourlyRate;

            $city[$resource] = (int) min(
                (int) $city[$resource] + $gained,
                $caps[$resource],
            );
        }

        return $city;
    }

    /**
     * Persist the current resource values to the DB.
     *
     * Call this before any action that consumes resources (upgrades, training, etc.)
     * to ensure the accumulated production is not lost.
     *
     * @param array<string, mixed>             $city        City row (after apply())
     * @param array<string, array{level: int}> $buildings   Current buildings
     * @param float                            $speedFactor World speed multiplier
     * @param array<string, int>               $vipBonuses  Optional VIP bonuses from VipService::bonuses()
     */
    public static function persist(
        array $city,
        array $buildings,
        float $speedFactor = 1.0,
        array $vipBonuses  = [],
    ): void {
        $updated = self::apply($city, $buildings, $speedFactor, $vipBonuses);

        Connection::getInstance()->execute(
            'UPDATE cities
             SET    food = ?, lumber = ?, stone = ?, gold = ?,
                    last_resource_update = UTC_TIMESTAMP()
             WHERE  id = ?',
            [
                $updated['food'],
                $updated['lumber'],
                $updated['stone'],
                $updated['gold'],
                (int) $city['id'],
            ],
        );
    }
}
