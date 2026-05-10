<?php
declare(strict_types=1);

namespace Conquer\Game\Map;

use Conquer\Db\Connection;
use Conquer\Logger;

/**
 * Manages world field objects (resource nodes): farms, lumber camps, quarries,
 * gold mines and gem nodes. Handles spawning, locking, harvesting and cleanup.
 *
 * object_type constants:
 *   1 = OBJECT_FARM    → food
 *   2 = OBJECT_LUMBER  → wood
 *   3 = OBJECT_QUARRY  → stone
 *   4 = OBJECT_GOLD    → gold
 *   5 = OBJECT_GEM_NODE → gems
 */
final class FieldObjectService
{
    // ── Object type constants ─────────────────────────────────────────────────

    public const OBJECT_FARM     = 1;
    public const OBJECT_LUMBER   = 2;
    public const OBJECT_QUARRY   = 3;
    public const OBJECT_GOLD     = 4;
    public const OBJECT_GEM_NODE = 5;

    /** Maps object_type → resource column name in the cities table. */
    public const RESOURCE_BY_TYPE = [
        self::OBJECT_FARM     => 'food',
        self::OBJECT_LUMBER   => 'wood',
        self::OBJECT_QUARRY   => 'stone',
        self::OBJECT_GOLD     => 'gold',
        self::OBJECT_GEM_NODE => 'gems',
    ];

    /** resource_max values per level. */
    private const RESOURCE_MAX_BY_LEVEL = [
        1 => 10_000,
        2 => 50_000,
        3 => 200_000,
    ];

    /** Number of tiles per sector edge (world is 256×256, 8 sectors → 64 tiles each). */
    private const SECTOR_SIZE = 64;

    /** Sectors grid dimension (8 = 4×2 arrangement for a 256×256 world). */
    private const SECTORS_PER_AXIS = 4;

    /** How many of each type to spawn per sector. */
    private const SPAWN_COUNTS = [
        self::OBJECT_FARM     => 5,
        self::OBJECT_LUMBER   => 5,
        self::OBJECT_QUARRY   => 3,
        self::OBJECT_GOLD     => 2,
        self::OBJECT_GEM_NODE => 1,
    ];

    /** Field objects expire after 24 hours. */
    private const EXPIRE_HOURS = 24;

    /** Max placement attempts per node before giving up. */
    private const MAX_PLACEMENT_ATTEMPTS = 20;

    private function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Read helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the single non-expired field object at the given tile, or null.
     *
     * @return array<string,mixed>|null
     */
    public static function getAtTile(int $worldId, int $x, int $y): ?array
    {
        $db = Connection::getInstance();

        try {
            $row = $db->query(
                'SELECT * FROM field_objects
                 WHERE  world_id = ? AND coord_x = ? AND coord_y = ?
                   AND  expires_at > UTC_TIMESTAMP()
                 LIMIT  1',
                [$worldId, $x, $y],
            )->fetch();

            return ($row === false) ? null : $row;
        } catch (\PDOException $e) {
            self::log()->error('[FieldObjectService] getAtTile failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns all non-expired field objects within the given bounding box
     * (inclusive). Used by the map API to populate the visible viewport.
     *
     * @return list<array<string,mixed>>
     */
    public static function getVisibleObjects(
        int $worldId,
        int $x1,
        int $y1,
        int $x2,
        int $y2,
    ): array {
        $db = Connection::getInstance();

        try {
            return $db->query(
                'SELECT id, world_id, coord_x, coord_y, object_type, level,
                        resource_amount, resource_max, spawned_at, expires_at,
                        gatherer_march_id
                 FROM   field_objects
                 WHERE  world_id = ?
                   AND  coord_x BETWEEN ? AND ?
                   AND  coord_y BETWEEN ? AND ?
                   AND  expires_at > UTC_TIMESTAMP()
                 ORDER  BY coord_y ASC, coord_x ASC',
                [$worldId, $x1, $x2, $y1, $y2],
            )->fetchAll();
        } catch (\PDOException $e) {
            self::log()->error('[FieldObjectService] getVisibleObjects failed: ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Locking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attempts to lock a field object to the given march.
     * Succeeds if the object is currently unlocked OR already locked by this
     * exact march (idempotent). Returns false when locked by another march.
     */
    public static function lockForGathering(int $objectId, int $marchId): bool
    {
        $db = Connection::getInstance();

        try {
            $affected = $db->execute(
                'UPDATE field_objects
                 SET    gatherer_march_id = ?
                 WHERE  id = ?
                   AND  (gatherer_march_id IS NULL OR gatherer_march_id = ?)',
                [$marchId, $objectId, $marchId],
            );

            return $affected > 0;
        } catch (\PDOException $e) {
            self::log()->error('[FieldObjectService] lockForGathering failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Removes the gatherer lock from a field object so others can gather it.
     */
    public static function unlockObject(int $objectId): void
    {
        $db = Connection::getInstance();

        try {
            $db->execute(
                'UPDATE field_objects SET gatherer_march_id = NULL WHERE id = ?',
                [$objectId],
            );
        } catch (\PDOException $e) {
            self::log()->error('[FieldObjectService] unlockObject failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Harvesting
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Harvests resources from a field object up to the carry capacity.
     * Deducts the harvested amount from resource_amount (floor at 0).
     *
     * @return array{resource_type: string, amount_gathered: int}
     */
    public static function harvestObject(int $objectId, int $carryCapacity): array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT object_type, resource_amount FROM field_objects WHERE id = ?',
            [$objectId],
        )->fetch();

        if ($row === false) {
            return ['resource_type' => 'unknown', 'amount_gathered' => 0];
        }

        $objectType    = (int) $row['object_type'];
        $available     = (int) $row['resource_amount'];
        $amountGather  = min($available, max(0, $carryCapacity));
        $resourceType  = self::RESOURCE_BY_TYPE[$objectType] ?? 'unknown';

        if ($amountGather > 0) {
            $db->execute(
                'UPDATE field_objects
                 SET    resource_amount = GREATEST(0, resource_amount - ?)
                 WHERE  id = ?',
                [$amountGather, $objectId],
            );
        }

        return [
            'resource_type'  => $resourceType,
            'amount_gathered' => $amountGather,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Spawning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Seeds field objects across the world map.
     *
     * The 256×256 world is divided into 8 sectors (4 columns × 2 rows of 64 tiles
     * each). Per sector we spawn: 5 farms, 5 lumber, 3 quarries, 2 gold mines,
     * 1 gem node at random levels 1–3. Tiles already occupied by a city,
     * field monster, shrine or existing field object are skipped.
     */
    public static function spawnObjects(int $worldId): void
    {
        $db  = Connection::getInstance();
        $log = self::log();

        // Pre-load all occupied tiles for fast lookup.
        $occupied = self::buildOccupiedSet($db, $worldId);

        $totalSpawned = 0;

        // 4 columns × 2 rows = 8 sectors
        for ($sectorRow = 0; $sectorRow < 2; $sectorRow++) {
            for ($sectorCol = 0; $sectorCol < self::SECTORS_PER_AXIS; $sectorCol++) {
                $originX = $sectorCol * self::SECTOR_SIZE;
                $originY = $sectorRow * self::SECTOR_SIZE;

                foreach (self::SPAWN_COUNTS as $objectType => $count) {
                    for ($i = 0; $i < $count; $i++) {
                        $coords = self::findFreeTile($originX, $originY, $occupied);

                        if ($coords === null) {
                            $log->warn(sprintf(
                                '[FieldObjectService] No free tile found in sector (%d,%d) for type %d',
                                $sectorCol,
                                $sectorRow,
                                $objectType,
                            ));
                            continue;
                        }

                        [$cx, $cy] = $coords;
                        $level       = random_int(1, 3);
                        $resourceMax = self::RESOURCE_MAX_BY_LEVEL[$level];

                        try {
                            $db->execute(
                                'INSERT INTO field_objects
                                    (world_id, coord_x, coord_y, object_type, level,
                                     resource_amount, resource_max,
                                     spawned_at, expires_at)
                                 VALUES
                                    (?, ?, ?, ?, ?,
                                     ?, ?,
                                     UTC_TIMESTAMP(),
                                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR))',
                                [
                                    $worldId,
                                    $cx,
                                    $cy,
                                    $objectType,
                                    $level,
                                    $resourceMax,
                                    $resourceMax,
                                    self::EXPIRE_HOURS,
                                ],
                            );

                            $occupied[$cx . ',' . $cy] = true;
                            $totalSpawned++;
                        } catch (\PDOException $e) {
                            $log->error(sprintf(
                                '[FieldObjectService] spawn INSERT failed at (%d,%d): %s',
                                $cx,
                                $cy,
                                $e->getMessage(),
                            ));
                        }
                    }
                }
            }
        }

        $log->info('[FieldObjectService] spawnObjects complete — spawned ' . $totalSpawned . ' field objects');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Deletes expired field objects that are not currently locked by a march.
     * Safe to call from cron without disrupting active gatherers.
     */
    public static function cleanExpired(): void
    {
        $db = Connection::getInstance();

        try {
            $deleted = $db->execute(
                'DELETE FROM field_objects
                 WHERE  expires_at < UTC_TIMESTAMP()
                   AND  gatherer_march_id IS NULL',
            );

            if ($deleted > 0) {
                self::log()->info('[FieldObjectService] cleanExpired — removed ' . $deleted . ' expired objects');
            }
        } catch (\PDOException $e) {
            self::log()->error('[FieldObjectService] cleanExpired failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Builds a hash-set of "x,y" strings for tiles already occupied by any
     * city, field monster, shrine or existing field object in the given world.
     *
     * @return array<string,true>
     */
    private static function buildOccupiedSet(Connection $db, int $worldId): array
    {
        $occupied = [];

        // Cities
        try {
            $rows = $db->query(
                'SELECT coord_x, coord_y FROM cities WHERE world_id = ?',
                [$worldId],
            )->fetchAll();
            foreach ($rows as $r) {
                $occupied[$r['coord_x'] . ',' . $r['coord_y']] = true;
            }
        } catch (\PDOException $e) {
            self::log()->warn('[FieldObjectService] Could not load cities for occupied set: ' . $e->getMessage());
        }

        // Field monsters
        try {
            $rows = $db->query(
                'SELECT coord_x, coord_y FROM field_monsters WHERE world_id = ?',
                [$worldId],
            )->fetchAll();
            foreach ($rows as $r) {
                $occupied[$r['coord_x'] . ',' . $r['coord_y']] = true;
            }
        } catch (\PDOException $e) {
            self::log()->warn('[FieldObjectService] Could not load field_monsters for occupied set: ' . $e->getMessage());
        }

        // Existing field objects (non-expired)
        try {
            $rows = $db->query(
                'SELECT coord_x, coord_y FROM field_objects
                 WHERE  world_id = ? AND expires_at > UTC_TIMESTAMP()',
                [$worldId],
            )->fetchAll();
            foreach ($rows as $r) {
                $occupied[$r['coord_x'] . ',' . $r['coord_y']] = true;
            }
        } catch (\PDOException $e) {
            self::log()->warn('[FieldObjectService] Could not load field_objects for occupied set: ' . $e->getMessage());
        }

        // Shrines (field_shrines table — uses coord_x/coord_y)
        try {
            $rows = $db->query(
                'SELECT coord_x, coord_y FROM field_shrines WHERE world_id = ?',
                [$worldId],
            )->fetchAll();
            foreach ($rows as $r) {
                $occupied[$r['coord_x'] . ',' . $r['coord_y']] = true;
            }
        } catch (\PDOException) {
            // Shrine table may not exist yet — silently skip
        }

        return $occupied;
    }

    /**
     * Finds a random unoccupied tile within the sector starting at (originX, originY).
     * Returns [x, y] or null after MAX_PLACEMENT_ATTEMPTS failures.
     *
     * @param array<string,true> $occupied
     * @return array{int,int}|null
     */
    private static function findFreeTile(int $originX, int $originY, array &$occupied): ?array
    {
        $max = self::SECTOR_SIZE - 1;

        for ($attempt = 0; $attempt < self::MAX_PLACEMENT_ATTEMPTS; $attempt++) {
            $cx  = $originX + random_int(0, $max);
            $cy  = $originY + random_int(0, $max);
            $key = $cx . ',' . $cy;

            if (!isset($occupied[$key])) {
                return [$cx, $cy];
            }
        }

        return null;
    }

    /** Returns the Logger singleton (convenience shorthand). */
    private static function log(): Logger
    {
        return Logger::getInstance();
    }
}
