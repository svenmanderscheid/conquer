<?php
declare(strict_types=1);

namespace Conquer\Game\Hospital;

use Conquer\Db\Connection;

/**
 * Hospital system — manages wounded troops, healing timers, and instant heal.
 *
 * Wounded troops arrive here after battle (addWounded).
 * Healing is lazy-evaluated on city load (processHealed).
 * Capacity is 1000 troops by default (will be building-level-driven later).
 */
final class HospitalService
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** Base healing rate: troops healed per minute. */
    private const BASE_TROOPS_PER_MINUTE = 100;

    /** Base hospital capacity (troops). Will be derived from building level later. */
    private const BASE_CAPACITY = 1000;

    /** Gold cost per wounded troop for paid instant-gold heal. */
    private const GOLD_COST_PER_TROOP = 2;

    /** GEMS cost for instant-heal-all via premium currency. */
    public const INSTANT_HEAL_GEM_COST = 50;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Adds wounded troops to the hospital after a battle.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE so that multiple wounds to the
     * same troop type accumulate, and healing_ends_at is always pushed to
     * max(current, now + additional_seconds).
     *
     * @param array<int, int> $woundedByCode  troop_code => count
     */
    public static function addWounded(int $cityId, array $woundedByCode): void
    {
        if (empty($woundedByCode)) {
            return;
        }

        $db              = Connection::getInstance();
        $secsPerTroop    = 60.0 / self::BASE_TROOPS_PER_MINUTE; // 0.6 s/troop

        foreach ($woundedByCode as $troopCode => $count) {
            $troopCode = (int) $troopCode;
            $count     = (int) $count;

            if ($count <= 0) {
                continue;
            }

            $totalSecs = (int) ceil($count * $secsPerTroop);

            // If a row already exists, accumulate the count and extend the
            // healing timer by the additional healing time needed for the new batch.
            // healing_ends_at = GREATEST(current_healing_ends_at, UTC_TIMESTAMP() + totalSecs)
            // For new rows, healing_ends_at = UTC_TIMESTAMP() + totalSecs.
            $db->execute(
                'INSERT INTO hospital_wounded
                    (city_id, troop_code, count, healing_ends_at)
                 VALUES
                    (:city_id, :code, :count,
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :secs SECOND))
                 ON DUPLICATE KEY UPDATE
                    count           = count + VALUES(count),
                    healing_ends_at = GREATEST(
                        healing_ends_at,
                        DATE_ADD(UTC_TIMESTAMP(), INTERVAL :secs2 SECOND)
                    )',
                [
                    ':city_id' => $cityId,
                    ':code'    => $troopCode,
                    ':count'   => $count,
                    ':secs'    => $totalSecs,
                    ':secs2'   => $totalSecs,
                ],
            );
        }
    }

    /**
     * Lazy tick: credits all fully-healed batches back to city_troops and
     * removes them from the hospital.
     *
     * Call this before any read of the hospital or city troops to ensure the
     * data is current (same lazy-tick pattern as building_queue and troop_queue).
     */
    public static function processHealed(int $cityId): void
    {
        $db = Connection::getInstance();

        try {
            $healed = $db->query(
                'SELECT id, troop_code, count
                 FROM   hospital_wounded
                 WHERE  city_id = ?
                   AND  healing_ends_at <= UTC_TIMESTAMP()',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return; // table may not exist in older migrations
        }

        if (empty($healed)) {
            return;
        }

        $db->transaction(function (Connection $db) use ($cityId, $healed): void {
            foreach ($healed as $row) {
                $code  = (int) $row['troop_code'];
                $count = (int) $row['count'];
                $id    = (int) $row['id'];

                // Credit back to city_troops
                $db->execute(
                    'INSERT INTO city_troops (city_id, troop_code, count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + VALUES(count)',
                    [$cityId, $code, $count],
                );

                // Remove healed row
                $db->execute(
                    'DELETE FROM hospital_wounded WHERE id = ?',
                    [$id],
                );
            }
        });
    }

    /**
     * Returns the current hospital status for a city.
     *
     * Callers should invoke processHealed() first to flush completed rows.
     *
     * @return array{
     *   wounded:  list<array{troop_code: int, count: int, healing_ends_at: string}>,
     *   capacity: int,
     *   used:     int
     * }
     */
    public static function getStatus(int $cityId): array
    {
        $db = Connection::getInstance();

        try {
            $rows = $db->query(
                'SELECT troop_code, count, healing_ends_at
                 FROM   hospital_wounded
                 WHERE  city_id = ?
                 ORDER  BY healing_ends_at ASC',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            $rows = [];
        }

        $wounded = [];
        $used    = 0;

        foreach ($rows as $row) {
            $count     = (int) $row['count'];
            $used     += $count;
            $wounded[] = [
                'troop_code'      => (int) $row['troop_code'],
                'count'           => $count,
                'healing_ends_at' => $row['healing_ends_at'],
            ];
        }

        return [
            'wounded'  => $wounded,
            'capacity' => self::BASE_CAPACITY,
            'used'     => $used,
        ];
    }

    /**
     * Returns the Gold cost to heal $count troops of a given type.
     *
     * Currently flat 2 Gold per troop. Will become tier-dependent later.
     */
    public static function getHealingCost(int $troopCode, int $count): int
    {
        return (int) ceil($count * self::GOLD_COST_PER_TROOP);
    }

    /**
     * Instantly heals all wounded troops for a city.
     *
     * Sets all healing_ends_at to UTC_TIMESTAMP(), then calls processHealed()
     * so every row is credited back to city_troops immediately.
     *
     * Returns false if there is nothing to heal.
     */
    public static function instantHeal(int $cityId): bool
    {
        $db = Connection::getInstance();

        // Check whether there is anything to heal
        try {
            $count = (int) $db->query(
                'SELECT COUNT(*) FROM hospital_wounded WHERE city_id = ?',
                [$cityId],
            )->fetchColumn();
        } catch (\PDOException) {
            return false;
        }

        if ($count === 0) {
            return false;
        }

        // Collapse all timers to now so processHealed picks them up
        $db->execute(
            'UPDATE hospital_wounded
             SET    healing_ends_at = UTC_TIMESTAMP()
             WHERE  city_id = ?',
            [$cityId],
        );

        self::processHealed($cityId);

        return true;
    }
}
