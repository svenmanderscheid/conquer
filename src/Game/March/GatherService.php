<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\Map\FieldObjectService;
use Conquer\Logger;

/**
 * Handles gather marches (march_type = 9).
 *
 * Flow:
 *   dispatch()       — validates target, inserts march, locks field object
 *   resolveGather()  — called by MarchTick when troops arrive; harvests + sets returning
 *
 * On return, MarchTick's generic returning handler credits the haul to the
 * origin city (loot key). The haul_json structure is:
 *   { "loot": { "food": 500 }, "survivors": {} }
 */
final class GatherService
{
    private const MARCH_GATHER    = 9;
    private const BASE_SPEED_TPS  = 1;    // 1 tile per second base march speed
    private const MIN_MARCH_SECS  = 5;    // minimum march duration in seconds
    private const BASE_CARRY_CAP  = 5_000; // base carry capacity per gather march

    private function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Dispatch
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatches a gather march to the given coordinates.
     *
     * Validation order:
     *   1. Field object exists and is not expired.
     *   2. Object is not locked by another march.
     *   3. Player has at least one free march slot.
     *   4. troopCount > 0.
     *
     * @return array{march_id: int}
     * @throws \RuntimeException on any validation failure
     */
    public static function dispatch(
        int $playerId,
        int $cityId,
        int $targetX,
        int $targetY,
        int $troopCount,
    ): array {
        if ($troopCount <= 0) {
            throw new \RuntimeException('Mindestens 1 Truppe muss zum Sammeln mitgeschickt werden.');
        }

        $db = Connection::getInstance();

        // ── Verify field object ──────────────────────────────────────────────
        $obj = FieldObjectService::getAtTile(1, $targetX, $targetY);

        if ($obj === null) {
            throw new \RuntimeException(
                'Kein Ressourcenfeld auf diesem Tile (' . $targetX . ',' . $targetY . ').'
            );
        }

        $objectId = (int) $obj['id'];

        if ($obj['gatherer_march_id'] !== null) {
            throw new \RuntimeException('Dieses Ressourcenfeld wird bereits von einem anderen Marsch gesammelt.');
        }

        if ((int) $obj['resource_amount'] <= 0) {
            throw new \RuntimeException('Das Ressourcenfeld ist bereits erschöpft.');
        }

        // ── Check march slots ────────────────────────────────────────────────
        $active = (int) $db->query(
            "SELECT COUNT(*) FROM marches
             WHERE  player_id = ? AND state IN ('marching','resolving','returning')",
            [$playerId],
        )->fetchColumn();

        // Gather marches share the 2-slot cap with other march types
        $maxSlots = 2;
        if ($active >= $maxSlots) {
            throw new \RuntimeException(
                'Alle ' . $maxSlots . ' Marsch-Slots belegt. Warte bis ein Marsch zurückkehrt.'
            );
        }

        // ── Get origin city coords for distance calculation ───────────────────
        $originCity = $db->query(
            'SELECT coord_x, coord_y FROM cities WHERE id = ? AND player_id = ?',
            [$cityId, $playerId],
        )->fetch();

        if ($originCity === false) {
            throw new \RuntimeException('Ungültige Stadt.');
        }

        $originX = (int) $originCity['coord_x'];
        $originY = (int) $originCity['coord_y'];

        // ── Calculate march duration ─────────────────────────────────────────
        $distance  = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $marchSecs = max(self::MIN_MARCH_SECS, (int) ceil($distance / self::BASE_SPEED_TPS));

        // ── Transaction: insert march + lock field object ────────────────────
        $marchId = 0;

        $db->transaction(function () use (
            $db,
            $playerId,
            $cityId,
            $targetX,
            $targetY,
            $objectId,
            $marchSecs,
            $troopCount,
            &$marchId,
        ): void {
            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, 1, :type, :city,
                     :tx, :ty, 5, :oid,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':type'   => self::MARCH_GATHER,
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':oid'    => $objectId,
                    ':troops' => json_encode(['gather_troops' => $troopCount]),
                    ':dur'    => $marchSecs,
                ],
            );

            $marchId = $db->lastInsertId();

            // Lock the field object to this march
            $locked = FieldObjectService::lockForGathering($objectId, $marchId);

            if (!$locked) {
                // Another march slipped in concurrently — abort the transaction
                throw new \RuntimeException('Dieses Ressourcenfeld wurde gerade von jemand anderem gesperrt. Bitte erneut versuchen.');
            }
        });

        Logger::getInstance()->info(sprintf(
            '[GatherService] March %d dispatched — player %d gathering at (%d,%d), object %d',
            $marchId,
            $playerId,
            $targetX,
            $targetY,
            $objectId,
        ));

        return ['march_id' => $marchId];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolution (called from MarchTick)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolves a gather march that has arrived at its target.
     *
     * Steps:
     *   1. Optimistic lock — set state to 'resolving'.
     *   2. Load and validate field object.
     *   3. Harvest up to BASE_CARRY_CAP resources.
     *   4. Unlock the field object.
     *   5. Set march state to 'returning' with haul_json populated.
     */
    public static function resolveGather(
        Connection $db,
        Logger     $log,
        int        $marchId,
        int        $playerId,
        int        $cityId,
        int        $targetX,
        int        $targetY,
        int        $objectId,
    ): void {
        // ── Step 1: Optimistic lock ───────────────────────────────────────────
        $locked = $db->execute(
            "UPDATE marches SET state = 'resolving' WHERE id = ? AND state = 'marching'",
            [$marchId],
        );

        if ($locked === 0) {
            // Already being resolved by another tick (race condition guard)
            $log->warn('[GatherService] March ' . $marchId . ' already resolving — skipped');
            return;
        }

        // ── Step 2: Load field object ─────────────────────────────────────────
        try {
            $obj = $db->query(
                'SELECT id, object_type, resource_amount, resource_max FROM field_objects WHERE id = ?',
                [$objectId],
            )->fetch();
        } catch (\PDOException $e) {
            $log->error('[GatherService] Failed to load field object ' . $objectId . ': ' . $e->getMessage());
            self::abortToReturning($db, $marchId);
            return;
        }

        if ($obj === false) {
            // Object has been deleted (e.g. expired + cleaned) — return empty
            $log->warn('[GatherService] March ' . $marchId . ' — field object ' . $objectId . ' no longer exists');
            self::abortToReturning($db, $marchId);
            return;
        }

        // ── Step 3: Harvest ───────────────────────────────────────────────────
        $carryCapacity = self::BASE_CARRY_CAP;

        try {
            $harvest = FieldObjectService::harvestObject($objectId, $carryCapacity);
        } catch (\PDOException $e) {
            $log->error('[GatherService] harvestObject failed for object ' . $objectId . ': ' . $e->getMessage());
            FieldObjectService::unlockObject($objectId);
            self::abortToReturning($db, $marchId);
            return;
        }

        $resourceType   = $harvest['resource_type'];
        $amountGathered = $harvest['amount_gathered'];

        // ── Step 4: Unlock field object ───────────────────────────────────────
        FieldObjectService::unlockObject($objectId);

        // ── Step 5: Set march returning ───────────────────────────────────────
        $loot = ($amountGathered > 0)
            ? [$resourceType => $amountGathered]
            : [];

        $haulJson = json_encode([
            'loot'      => $loot,
            'survivors' => [],
        ]);

        try {
            $db->execute(
                "UPDATE marches
                 SET state       = 'returning',
                     haul_json   = :haul,
                     return_time = DATE_ADD(UTC_TIMESTAMP(),
                                   INTERVAL TIMESTAMPDIFF(SECOND, departure_time, arrival_time) SECOND)
                 WHERE id = :id",
                [
                    ':haul' => $haulJson,
                    ':id'   => $marchId,
                ],
            );
        } catch (\PDOException $e) {
            $log->error('[GatherService] Failed to set march ' . $marchId . ' returning: ' . $e->getMessage());
            return;
        }

        $log->info(sprintf(
            '[GatherService] March %d resolved — gathered %d %s from object %d at (%d,%d)',
            $marchId,
            $amountGathered,
            $resourceType,
            $objectId,
            $targetX,
            $targetY,
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sets a march back to 'returning' with an empty haul on unexpected errors.
     * Ensures the march is never left stuck in 'resolving'.
     */
    private static function abortToReturning(Connection $db, int $marchId): void
    {
        try {
            $db->execute(
                "UPDATE marches
                 SET state       = 'returning',
                     haul_json   = '{\"loot\":{},\"survivors\":{}}',
                     return_time = UTC_TIMESTAMP()
                 WHERE id = ?",
                [$marchId],
            );
        } catch (\PDOException) {
            // Best-effort — MarchTick's 'resolving' recovery will handle it after 2 min
        }
    }
}
