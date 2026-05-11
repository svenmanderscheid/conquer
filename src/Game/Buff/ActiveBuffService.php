<?php
declare(strict_types=1);

namespace Conquer\Game\Buff;

use Conquer\Db\Connection;

/**
 * Active Buffs — timed production/research/training multipliers stored in active_buffs.
 *
 * Buff types:
 *   production_boost  — multiplies resource production rate
 *   research_boost    — multiplies research speed
 *   training_boost    — multiplies troop training speed
 *
 * Multiple buffs of the same type stack multiplicatively.
 */
final class ActiveBuffService
{
    private function __construct() {}

    /**
     * Grants a new timed buff to a player.
     *
     * @param float $multiplier  e.g. 1.25 for +25%
     * @param int   $hours       How many hours the buff lasts
     */
    public static function apply(int $playerId, string $type, float $multiplier, int $hours): void
    {
        if ($multiplier <= 0.0 || $hours <= 0) {
            return;
        }

        Connection::getInstance()->execute(
            'INSERT INTO active_buffs (player_id, buff_type, multiplier, expires_at)
             VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR))',
            [$playerId, $type, $multiplier, $hours],
        );
    }

    /**
     * Returns all active (non-expired) buffs for a player.
     *
     * @return list<array<string, mixed>>
     */
    public static function getActive(int $playerId): array
    {
        try {
            return Connection::getInstance()->query(
                'SELECT id, buff_type, multiplier, expires_at
                 FROM   active_buffs
                 WHERE  player_id = ? AND expires_at > UTC_TIMESTAMP()
                 ORDER  BY buff_type, expires_at ASC',
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Returns the combined multiplier for a specific buff type.
     *
     * Active multipliers of the same type stack multiplicatively:
     *   1.25 × 1.25 = 1.5625 (two +25% buffs)
     *
     * Returns 1.0 (no boost) when no active buffs exist.
     */
    public static function getMultiplier(int $playerId, string $type): float
    {
        try {
            $rows = Connection::getInstance()->query(
                'SELECT multiplier
                 FROM   active_buffs
                 WHERE  player_id = ? AND buff_type = ? AND expires_at > UTC_TIMESTAMP()',
                [$playerId, $type],
            )->fetchAll();
        } catch (\PDOException) {
            return 1.0;
        }

        if (empty($rows)) {
            return 1.0;
        }

        $combined = 1.0;
        foreach ($rows as $row) {
            $m = (float) $row['multiplier'];
            if ($m > 0.0) {
                $combined *= $m;
            }
        }

        return $combined;
    }

    /**
     * Cleans up expired buffs for a player (best-effort, call lazily).
     */
    public static function pruneExpired(int $playerId): void
    {
        try {
            Connection::getInstance()->execute(
                'DELETE FROM active_buffs WHERE player_id = ? AND expires_at <= UTC_TIMESTAMP()',
                [$playerId],
            );
        } catch (\PDOException) {
            // non-critical
        }
    }
}
