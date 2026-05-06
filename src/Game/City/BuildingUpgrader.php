<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Db\Connection;

/**
 * Handles starting a building upgrade.
 *
 * Validation order:
 *   1. Building code must be valid
 *   2. Building must not already be at max level (30)
 *   3. No other upgrade in progress (1 slot; 2 at VIP 4+)
 *   4. Non-castle buildings cannot exceed castle level
 *   5. Resources must be sufficient (after applying production tick)
 *
 * On success: resources are deducted and a building_queue row is inserted.
 */
final class BuildingUpgrader
{
    private const MAX_LEVEL = 30;

    private function __construct() {}

    /**
     * Start a building upgrade for the given city.
     *
     * @param  int                               $cityId
     * @param  string                            $code       Building code to upgrade
     * @param  array<string, mixed>              $city       City row from DB
     * @param  array<string, array{level: int}>  $buildings  Current buildings map
     * @param  int                               $vipLevel   Player VIP level
     * @return array<string, mixed>  The new building_queue row
     * @throws \RuntimeException  On any validation failure (message is user-safe)
     */
    public static function start(
        int    $cityId,
        string $code,
        array  $city,
        array  $buildings,
        int    $vipLevel = 1,
    ): array {
        // 1. Valid building code?
        if (!in_array($code, CityState::BUILDING_CODES, true)) {
            throw new \RuntimeException('Unknown building: ' . $code);
        }

        $currentLevel = (int) ($buildings[$code]['level'] ?? 1);
        $toLevel      = $currentLevel + 1;

        // 2. Already at max?
        if ($currentLevel >= self::MAX_LEVEL) {
            throw new \RuntimeException(
                CityState::BUILDING_NAMES[$code] . ' is already at maximum level.',
            );
        }

        $db = Connection::getInstance();

        // 3. Queue slot available?
        $maxSlots = $vipLevel >= 4 ? 2 : 1;
        $activeCount = (int) $db->query(
            'SELECT COUNT(*) FROM building_queue WHERE city_id = ? AND is_processed = 0',
            [$cityId],
        )->fetchColumn();

        if ($activeCount >= $maxSlots) {
            $msg = $maxSlots === 1
                ? 'A building is already being upgraded. Reach VIP 4 to unlock a second slot.'
                : 'Both building queue slots are occupied.';
            throw new \RuntimeException($msg);
        }

        // Also check the specific building isn't already queued.
        $alreadyQueued = $db->query(
            'SELECT 1 FROM building_queue
             WHERE city_id = ? AND building_code = ? AND is_processed = 0',
            [$cityId, $code],
        )->fetch();

        if ($alreadyQueued !== false) {
            throw new \RuntimeException(
                CityState::BUILDING_NAMES[$code] . ' is already in the upgrade queue.',
            );
        }

        // 4a. Castle requirements: Wall + secondary building must be at required level.
        if ($code === 'castle') {
            $reqs = BuildingData::getCastleRequirements($toLevel);
            foreach ($reqs as $reqCode => $reqLevel) {
                $actual = (int) ($buildings[$reqCode]['level'] ?? 1);
                if ($actual < $reqLevel) {
                    $name = CityState::BUILDING_NAMES[$reqCode] ?? $reqCode;
                    throw new \RuntimeException(
                        "Castle LV.{$toLevel} requires {$name} LV.{$reqLevel} "
                            . "(currently LV.{$actual}).",
                    );
                }
            }
        }

        // 4b. Non-castle buildings can't exceed castle level.
        if ($code !== 'castle') {
            $castleLevel = (int) ($buildings['castle']['level'] ?? 1);
            if ($toLevel > $castleLevel) {
                throw new \RuntimeException(
                    CityState::BUILDING_NAMES[$code]
                        . " can't exceed Castle level (LV.{$castleLevel}).",
                );
            }
        }

        // 5. Resources — persist accumulated production first, then check.
        ResourceTick::persist($city, $buildings);

        // Re-read fresh resource values from DB.
        $fresh = $db->query(
            'SELECT food, lumber, stone, gold FROM cities WHERE id = ?',
            [$cityId],
        )->fetch();

        $cost = BuildingData::getCost($code, $toLevel);

        foreach (['lumber', 'stone', 'gold', 'food'] as $res) {
            if ((int) $fresh[$res] < $cost[$res]) {
                throw new \RuntimeException(
                    'Not enough ' . $res . '. Need '
                        . number_format($cost[$res]) . ', have '
                        . number_format((int) $fresh[$res]) . '.',
                );
            }
        }

        // All checks passed — deduct resources and insert queue entry.
        $buildTime  = BuildingData::getBuildTime($code, $toLevel);
        $startedAt  = gmdate('Y-m-d H:i:s');
        $finishesAt = gmdate('Y-m-d H:i:s', time() + $buildTime);

        $db->transaction(static function (Connection $db) use (
            $cityId, $code, $toLevel, $cost, $startedAt, $finishesAt,
        ): void {
            // Deduct resources.
            $db->execute(
                'UPDATE cities
                 SET lumber = lumber - ?,
                     stone  = stone  - ?,
                     gold   = gold   - ?,
                     food   = food   - ?
                 WHERE id = ?',
                [$cost['lumber'], $cost['stone'], $cost['gold'], $cost['food'], $cityId],
            );

            // Enqueue upgrade.
            $db->execute(
                'INSERT INTO building_queue
                     (city_id, building_code, level_to, started_at, finishes_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$cityId, $code, $toLevel, $startedAt, $finishesAt],
            );
        });

        return [
            'building_code' => $code,
            'level_to'      => $toLevel,
            'started_at'    => $startedAt,
            'finishes_at'   => $finishesAt,
        ];
    }
}
