<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopTrainer;
use Conquer\Game\Vip\VipService;
use Conquer\Game\Buff\ActiveBuffService;

/**
 * Loads a player's city snapshot from the database.
 *
 * Returns a structured array with city info, all 13 buildings, and
 * the active build queue. Returns null when the player has no city yet.
 */
final class CityState
{
    /**
     * The 13 canonical building codes (SPEC §4.1).
     * Order determines display order in the city view.
     */
    public const BUILDING_CODES = [
        'castle',
        'wall',
        'farm',
        'lumber_camp',
        'quarry',
        'gold_mine',
        'storage',
        'treasure_house',
        'barrack',
        'hospital',
        'academy',
        'trading_post',
        'hall_of_alliance',
    ];

    /** Display labels for each building code. */
    public const BUILDING_NAMES = [
        'castle'           => 'Castle',
        'wall'             => 'Wall',
        'farm'             => 'Farm',
        'lumber_camp'      => 'Lumber Camp',
        'quarry'           => 'Quarry',
        'gold_mine'        => 'Gold Mine',
        'storage'          => 'Storage',
        'treasure_house'   => 'Treasure House',
        'barrack'          => 'Barrack',
        'hospital'         => 'Hospital',
        'academy'          => 'Academy',
        'trading_post'     => 'Trading Post',
        'hall_of_alliance' => 'Hall of Alliance',
    ];

    // Static-only utility — no instantiation.
    private function __construct() {}

    /**
     * Load the player's city in the given world.
     *
     * @return array{
     *     city: array<string, mixed>,
     *     buildings: array<string, array{code: string, level: int}>,
     *     build_queue: list<array<string, mixed>>
     * }|null  Null when the player has no city in this world.
     */
    public static function loadForPlayer(int $playerId, int $worldId = 1): ?array
    {
        $db = Connection::getInstance();

        $city = $db->query(
            'SELECT id, name, food, lumber, stone, gold, last_resource_update,
                    wall_hp_current, wall_hp_max, wall_last_update,
                    castle_level, power,
                    action_points, coord_x, coord_y, is_shielded
             FROM   cities
             WHERE  player_id = ? AND world_id = ?',
            [$playerId, $worldId],
        )->fetch();

        if ($city === false) {
            return null;
        }

        $cityId    = (int) $city['id'];
        $buildings = self::loadBuildings($db, $cityId);

        // Process any finished upgrades before returning state.
        self::processFinishedUpgrades($db, $cityId, $buildings);
        // Reload buildings in case upgrades were applied.
        $buildings = self::loadBuildings($db, $cityId);

        // Credit any completed troop training.
        TroopTrainer::processQueue($db, $cityId);

        // Apply wall HP auto-regeneration (lazy, ~10%/hour).
        self::applyWallRegen($db, $city);

        // Load VIP status — used for production and build-time bonuses.
        $vip        = VipService::status($playerId);
        $vipBonuses = $vip['bonuses'];

        // Apply active production buffs multiplicatively.
        $productionMultiplier = ActiveBuffService::getMultiplier($playerId, 'production_boost');

        // Apply lazy resource production (no DB write on read).
        $city = ResourceTick::apply($city, $buildings, $productionMultiplier, $vipBonuses);

        // Recalculate power live so it's always correct on read.
        $city['power'] = BuildingData::calculateCityPower($buildings);

        $queue      = self::loadBuildQueue($db, $cityId);
        $troops     = self::loadTroops($db, $cityId);
        $troopQueue = self::loadTroopQueue($db, $cityId);

        return [
            'city'        => $city,
            'buildings'   => $buildings,
            'build_queue' => $queue,
            'troops'      => $troops,
            'troop_queue' => $troopQueue,
            'vip'         => $vip,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Returns a map of building_code → {code, level}.
     * All 13 codes are always present; missing DB rows default to level 1.
     *
     * @return array<string, array{code: string, level: int}>
     */
    private static function loadBuildings(Connection $db, int $cityId): array
    {
        $rows = $db->query(
            'SELECT building_code, level FROM city_buildings WHERE city_id = ?',
            [$cityId],
        )->fetchAll();

        // Index existing rows by code.
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['building_code']] = (int) $row['level'];
        }

        // Merge with canonical list — fill gaps with level 1.
        $buildings = [];
        foreach (self::BUILDING_CODES as $code) {
            $buildings[$code] = [
                'code'  => $code,
                'level' => $indexed[$code] ?? 1,
            ];
        }

        return $buildings;
    }

    /**
     * Checks for finished queue entries and applies them to city_buildings.
     * This is the lazy "tick" for the build queue — runs on every city load.
     *
     * @param array<string, array{level: int}> $buildings Passed by reference to update in place
     */
    private static function processFinishedUpgrades(
        Connection $db,
        int        $cityId,
        array      &$buildings,
    ): void {
        try {
            $finished = $db->query(
                'SELECT id, building_code, level_to
                 FROM   building_queue
                 WHERE  city_id = ? AND is_processed = 0 AND finishes_at <= UTC_TIMESTAMP()',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return; // building_queue table may not exist yet
        }

        foreach ($finished as $entry) {
            $code    = $entry['building_code'];
            $levelTo = (int) $entry['level_to'];

            // Update or insert building level.
            $db->execute(
                'INSERT INTO city_buildings (city_id, building_code, level)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE level = ?',
                [$cityId, $code, $levelTo, $levelTo],
            );

            // Mark queue entry as processed.
            $db->execute(
                'UPDATE building_queue SET is_processed = 1 WHERE id = ?',
                [(int) $entry['id']],
            );

            // Keep castle_level in sync on cities table.
            if ($code === 'castle') {
                $db->execute(
                    'UPDATE cities SET castle_level = ? WHERE id = ?',
                    [$levelTo, $cityId],
                );
            }

            $buildings[$code] = ['code' => $code, 'level' => $levelTo];
        }

        // Recalculate and persist city power after all upgrades are applied.
        if (!empty($finished)) {
            $power = BuildingData::calculateCityPower($buildings);
            $db->execute(
                'UPDATE cities SET power = ? WHERE id = ?',
                [$power, $cityId],
            );
        }
    }

    /**
     * Returns active (unprocessed) build queue entries for the city.
     *
     * @return list<array<string, mixed>>
     */
    private static function loadBuildQueue(Connection $db, int $cityId): array
    {
        try {
            return $db->query(
                'SELECT id, building_code, level_to, started_at, finishes_at
                 FROM   building_queue
                 WHERE  city_id = ? AND is_processed = 0
                 ORDER  BY finishes_at ASC',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Returns troop counts for the city: {troop_code => count}.
     *
     * @return array<int, int>
     */
    private static function loadTroops(Connection $db, int $cityId): array
    {
        try {
            $rows = $db->query(
                'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['troop_code']] = (int) $row['count'];
        }
        return $result;
    }

    /**
     * Returns active training queue entries for the city.
     *
     * @return list<array<string, mixed>>
     */
    private static function loadTroopQueue(Connection $db, int $cityId): array
    {
        try {
            return $db->query(
                'SELECT id, troop_code, count, barrack_slot, started_at, finishes_at
                 FROM   troop_queue
                 WHERE  city_id = ? AND is_processed = 0
                 ORDER  BY finishes_at ASC',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Applies lazy wall HP regeneration: ~10% of wall_hp_max per hour.
     *
     * Only runs when wall is not full. Mutates $city in-place with the updated
     * wall_hp_current value and persists to DB if any regen occurred.
     *
     * @param array<string, mixed> $city  Passed by reference — updated in place
     */
    private static function applyWallRegen(Connection $db, array &$city): void
    {
        $wallHpCurrent = (int) ($city['wall_hp_current'] ?? 0);
        $wallHpMax     = (int) ($city['wall_hp_max']     ?? 0);

        // Skip if wall is already full or max is 0
        if ($wallHpMax <= 0 || $wallHpCurrent >= $wallHpMax) {
            return;
        }

        $lastUpdate = (string) ($city['wall_last_update'] ?? '');
        if ($lastUpdate === '') {
            return;
        }

        $lastTs = strtotime($lastUpdate . ' UTC');
        if ($lastTs === false || $lastTs <= 0) {
            return;
        }

        $hoursElapsed = (time() - $lastTs) / 3600.0;

        if ($hoursElapsed < 0.001) {
            return;
        }

        // 10% of max HP per hour, floor
        $regen = (int) floor($hoursElapsed * $wallHpMax * 0.10);

        if ($regen <= 0) {
            return;
        }

        $newHp = min($wallHpMax, $wallHpCurrent + $regen);

        $cityId = (int) $city['id'];

        try {
            $db->execute(
                'UPDATE cities
                 SET    wall_hp_current  = ?,
                        wall_last_update = UTC_TIMESTAMP()
                 WHERE  id = ?',
                [$newHp, $cityId],
            );

            // Update the in-memory snapshot so callers see the fresh value
            $city['wall_hp_current']  = $newHp;
            $city['wall_last_update'] = gmdate('Y-m-d H:i:s');
        } catch (\PDOException) {
            // Non-critical — skip on DB error
        }
    }
}
