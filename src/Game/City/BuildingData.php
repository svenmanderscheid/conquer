<?php
declare(strict_types=1);

namespace Conquer\Game\City;

/**
 * Building cost, build time, and production data.
 *
 * Sprint 1.5: formula-based placeholders.
 * These will be replaced by data/buildings/<code>.json files (SPEC §4.2)
 * once real balance data is available. The public interface stays the same.
 */
final class BuildingData
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Upgrade costs
    // -------------------------------------------------------------------------

    /**
     * Returns resource costs to upgrade a building to the given level.
     * Level 1 costs nothing (buildings start at L1 by default).
     *
     * @return array{lumber: int, stone: int, gold: int, food: int}
     */
    public static function getCost(string $code, int $toLevel): array
    {
        if ($toLevel <= 1) {
            return ['lumber' => 0, 'stone' => 0, 'gold' => 0, 'food' => 0];
        }

        ['lumber' => $bl, 'stone' => $bs, 'gold' => $bg] = self::baseCosts($code);
        $mult = pow(1.4, $toLevel - 1);

        return [
            'lumber' => (int) round($bl * $mult),
            'stone'  => (int) round($bs * $mult),
            'gold'   => (int) round($bg * $mult),
            'food'   => 0,
        ];
    }

    /**
     * Returns build time in seconds for upgrading to the given level.
     *
     * @param array<string, int> $vipBonuses Optional VIP bonus array from VipService::bonuses().
     *                                        If provided, the construction_speed bonus (%) reduces build time.
     */
    public static function getBuildTime(string $code, int $toLevel, array $vipBonuses = []): int
    {
        if ($toLevel <= 1) {
            return 0;
        }

        // Exponential: L2=42s, L10≈9min, L20≈3.2h, L30≈2.8days
        $base = max(10, (int) round(30 * pow(1.4, $toLevel - 1)));

        $speedBonus = (int) ($vipBonuses['construction_speed'] ?? 0);
        if ($speedBonus > 0) {
            $base = (int) round($base * (1 - $speedBonus / 100));
        }

        return max(1, $base);
    }

    // -------------------------------------------------------------------------
    // Resource production (SPEC §3.2)
    // -------------------------------------------------------------------------

    /**
     * Returns the hourly production rate of a resource building at a given level.
     * Returns 0 for buildings that don't produce resources.
     *
     * @param array<string, int> $vipBonuses Optional VIP bonus array from VipService::bonuses().
     *                                        If provided, the resource_production bonus (%) increases output.
     */
    public static function getHourlyRate(string $code, int $level, array $vipBonuses = []): float
    {
        $base = match ($code) {
            'farm'        => 300.0,   // food/hour at L1
            'lumber_camp' => 300.0,   // lumber/hour
            'quarry'      => 240.0,   // stone/hour
            'gold_mine'   => 150.0,   // gold/hour
            default       => 0.0,
        };

        if ($base === 0.0 || $level <= 0) {
            return 0.0;
        }

        // Scales: L1×1.0, L10×3.5, L20×16, L30×66
        $rate = $base * pow(1.15, $level - 1);

        $productionBonus = (int) ($vipBonuses['resource_production'] ?? 0);
        if ($productionBonus > 0) {
            $rate = $rate * (1 + $productionBonus / 100);
        }

        return $rate;
    }

    /**
     * Returns the resource produced by this building (or null if non-producing).
     */
    public static function getProducedResource(string $code): ?string
    {
        return match ($code) {
            'farm'        => 'food',
            'lumber_camp' => 'lumber',
            'quarry'      => 'stone',
            'gold_mine'   => 'gold',
            default       => null,
        };
    }

    // -------------------------------------------------------------------------
    // Storage caps (SPEC §3.3)
    // -------------------------------------------------------------------------

    /**
     * Returns the base storage cap for each resource given the current building levels.
     *
     * @param array<string, array{level: int}> $buildings
     * @return array{food: int, lumber: int, stone: int, gold: int}
     */
    public static function getStorageCaps(array $buildings): array
    {
        $storageLevel       = (int) ($buildings['storage']['level']       ?? 1);
        $treasureHouseLevel = (int) ($buildings['treasure_house']['level'] ?? 1);

        // Base 100k at L1, scales ×1.2 per level
        $fls = (int) round(100_000 * pow(1.2, $storageLevel - 1));
        $g   = (int) round(60_000  * pow(1.2, $treasureHouseLevel - 1));

        return [
            'food'   => $fls,
            'lumber' => $fls,
            'stone'  => $fls,
            'gold'   => $g,
        ];
    }

    // -------------------------------------------------------------------------
    // Power (SPEC §4.2)
    // -------------------------------------------------------------------------

    /**
     * Power contribution of a single building level.
     * Scales as base_power × 1.5^(level−1), matching Wall data from SPEC §4.7.1.
     */
    public static function getPowerAtLevel(string $code, int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        $base = match ($code) {
            'castle'           => 1000,
            'wall'             => 600,
            'barrack'          => 500,
            'academy'          => 450,
            'storage'          => 400,
            'treasure_house'   => 400,
            'hospital'         => 400,
            'hall_of_alliance' => 400,
            'trading_post'     => 350,
            'farm'             => 300,
            'lumber_camp'      => 300,
            'quarry'           => 300,
            'gold_mine'        => 300,
            default            => 300,
        };

        return (int) round($base * pow(1.5, $level - 1));
    }

    /**
     * Total power for a building at the given level (sum of L1…level).
     */
    public static function getTotalPower(string $code, int $level): int
    {
        $total = 0;
        for ($l = 1; $l <= $level; $l++) {
            $total += self::getPowerAtLevel($code, $l);
        }
        return $total;
    }

    /**
     * Recalculates the full city power from all current building levels.
     *
     * @param array<string, array{level: int}> $buildings
     */
    public static function calculateCityPower(array $buildings): int
    {
        $total = 0;
        foreach ($buildings as $code => $building) {
            $total += self::getTotalPower($code, (int) $building['level']);
        }
        return $total;
    }

    // -------------------------------------------------------------------------
    // Castle upgrade requirements (SPEC §4.4)
    // -------------------------------------------------------------------------

    /**
     * Returns the building levels required before Castle can be upgraded to $toLevel.
     * Keys are building codes, values are the minimum level required.
     *
     * @return array<string, int>  e.g. ['wall' => 4, 'trading_post' => 4]
     */
    public static function getCastleRequirements(int $toLevel): array
    {
        if ($toLevel <= 1) {
            return [];
        }

        // Wall is always a prerequisite at (toLevel − 1).
        $reqs = ['wall' => $toLevel - 1];

        // Secondary prerequisite varies by level range (SPEC §4.4).
        $secondary = match (true) {
            $toLevel >= 5  && $toLevel <= 14 => 'trading_post',
            $toLevel >= 15 && $toLevel <= 19 => 'academy',
            $toLevel >= 20 && $toLevel <= 24 => 'hospital',
            $toLevel >= 25 && $toLevel <= 29 => 'storage',
            $toLevel === 30                  => 'treasure_house',
            default                          => null,
        };

        if ($secondary !== null) {
            $reqs[$secondary] = $toLevel - 1;
        }

        return $reqs;
    }

    // -------------------------------------------------------------------------

    /**
     * Base lumber/stone/gold costs at level 2 (×1.0 multiplier).
     *
     * @return array{lumber: int, stone: int, gold: int}
     */
    private static function baseCosts(string $code): array
    {
        return match ($code) {
            'castle'           => ['lumber' => 4000, 'stone' => 4000, 'gold' => 2000],
            'wall'             => ['lumber' => 2500, 'stone' => 3500, 'gold' =>    0],
            'farm'             => ['lumber' => 2000, 'stone' => 1500, 'gold' =>    0],
            'lumber_camp'      => ['lumber' => 1500, 'stone' => 2000, 'gold' =>    0],
            'quarry'           => ['lumber' => 2000, 'stone' => 1500, 'gold' =>    0],
            'gold_mine'        => ['lumber' => 1500, 'stone' => 1500, 'gold' =>  500],
            'storage'          => ['lumber' => 2500, 'stone' => 2500, 'gold' =>    0],
            'treasure_house'   => ['lumber' => 2000, 'stone' => 2000, 'gold' => 1000],
            'barrack'          => ['lumber' => 2500, 'stone' => 2000, 'gold' =>  500],
            'hospital'         => ['lumber' => 2000, 'stone' => 2500, 'gold' =>  500],
            'academy'          => ['lumber' => 2500, 'stone' => 2500, 'gold' => 1000],
            'trading_post'     => ['lumber' => 2000, 'stone' => 2000, 'gold' =>  500],
            'hall_of_alliance' => ['lumber' => 2500, 'stone' => 2500, 'gold' =>  500],
            default            => ['lumber' => 2000, 'stone' => 2000, 'gold' =>  500],
        };
    }
}
