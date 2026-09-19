<?php
declare(strict_types=1);

namespace Conquer\Game\City;

/**
 * Building cost, build time, and production data.
 *
 * Upgrade values come from the supplied balance tables. Production and storage
 * retain their existing curves: those values are absent from the source files.
 */
final class BuildingData
{
    private function __construct() {}

    public static function level(string $code, int $level): ?array
    {
        static $data = null;
        $data ??= json_decode((string)file_get_contents(ROOT_DIR.'/data/buildings.json'), true, 512, JSON_THROW_ON_ERROR);
        $code = $data['aliases'][$code] ?? $code;
        return $data['buildings'][$code][$level] ?? null;
    }

    /** Inventory materials are separate from the four spendable city resources. */
    public static function getItemCosts(string $code, int $toLevel): array
    {
        return self::level($code, $toLevel)['items'] ?? [];
    }

    public static function itemRequirements(string $code, int $toLevel, array $owned): array
    {
        $out = [];
        foreach (self::getItemCosts($code, $toLevel) as $itemCode => $quantity) {
            $def = \Conquer\Game\Inventory\InventoryService::getItemDef((int)$itemCode);
            $have = (int)($owned[$itemCode] ?? 0);
            $out[] = ['item_code'=>(int)$itemCode, 'name'=>$def['name_de'] ?? $def['name'], 'count'=>$quantity, 'owned'=>$have, 'met'=>$have >= $quantity];
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // Upgrade costs
    // -------------------------------------------------------------------------

    /**
     * Returns resource costs to upgrade a building to the given level.
     * Level 1 values are retained for reference; starter buildings are granted separately.
     *
     * @return array{lumber: int, stone: int, gold: int, food: int}
     */
    public static function getCost(string $code, int $toLevel): array
    {
        return self::level($code, $toLevel)['resources'] ?? ['food'=>0, 'lumber'=>0, 'stone'=>0, 'gold'=>0];
    }

    /** Only for refunds of jobs paid before cost snapshots were introduced. */
    public static function legacyCost(string $code, int $toLevel): array
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
        $row = self::level($code, $toLevel);
        if ($row === null) return 0;
        $base = (int)$row['time'];

        $speedBonus = (float) ($vipBonuses['construction_speed'] ?? 0);
        if ($speedBonus > 0) {
            $base = (int) round($base * (1 - $speedBonus / 100));
        }

        return max(1, (int)round($base / (1+max(0,(float)($vipBonuses['talent_construction_speed']??0)))));
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

        $resource = self::getProducedResource($code);
        return $rate * (1 + (float) ($vipBonuses[$resource . '_prod_pct'] ?? 0));
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
     * Difference between the cumulative source power at consecutive levels.
     */
    public static function getPowerAtLevel(string $code, int $level): int
    {
        return max(0, self::getTotalPower($code, $level) - self::getTotalPower($code, $level - 1));
    }

    /**
     * Cumulative power at this level, exactly as recorded in the source.
     */
    public static function getTotalPower(string $code, int $level): int
    {
        return (int)(self::level($code, $level)['power'] ?? 0);
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
    public static function getUpgradeRequirements(string $code, int $toLevel): array
    {
        return self::level($code, $toLevel)['requirements'] ?? [];
    }

    public static function getCastleRequirements(int $toLevel): array
    {
        return self::getUpgradeRequirements('castle', $toLevel);
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
            'barrack', 'archery_range', 'stable' => ['lumber' => 2500, 'stone' => 2000, 'gold' =>  500],
            'hospital'         => ['lumber' => 2000, 'stone' => 2500, 'gold' =>  500],
            'academy'          => ['lumber' => 2500, 'stone' => 2500, 'gold' => 1000],
            'trading_post'     => ['lumber' => 2000, 'stone' => 2000, 'gold' =>  500],
            'hall_of_alliance' => ['lumber' => 2500, 'stone' => 2500, 'gold' =>  500],
            default            => ['lumber' => 2000, 'stone' => 2000, 'gold' =>  500],
        };
    }
}
