<?php
declare(strict_types=1);

namespace Conquer\Game\Player;

use Conquer\Db\Connection;

/**
 * Action Point system.
 *
 * - Max AP:          200
 * - Regen rate:      1 AP every 5 minutes (12 AP/hour)
 * - Monster cost:    10 AP  (normal monsters)
 * - Deathkar cost:   25 AP
 * - Dragon cost:     30 AP  (Dragon / Wyrm / Wyvern)
 *
 * AP regeneration is computed lazily when get() is called.
 * If the recalculated value differs from the stored value, the DB is updated.
 */
final class ActionPoints
{
    public const MAX_AP           = 200;
    public const REGEN_PER_HOUR   = 12;
    private const REGEN_INTERVAL_MINUTES = 5; // 1 AP per interval

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns current AP for a player, applying any pending regeneration first.
     * Persists updated values to DB if AP changed.
     *
     * @return array{current: int, max: int, regen_per_hour: int}
     * @throws \RuntimeException if the player is not found
     */
    public static function get(int $playerId): array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT action_points, last_ap_regen FROM players WHERE id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Player not found: ' . $playerId);
        }

        $stored      = (int) $row['action_points'];
        $lastRegen   = new \DateTimeImmutable($row['last_ap_regen'], new \DateTimeZone('UTC'));
        $now         = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $minutesElapsed = (int) floor(($now->getTimestamp() - $lastRegen->getTimestamp()) / 60);
        $intervals      = (int) floor($minutesElapsed / self::REGEN_INTERVAL_MINUTES);

        $current = $stored;

        if ($intervals > 0 && $stored < self::MAX_AP) {
            $current = min(self::MAX_AP, $stored + $intervals);

            // Advance last_ap_regen by exactly the consumed intervals
            $secondsConsumed = $intervals * self::REGEN_INTERVAL_MINUTES * 60;
            $newLastRegen    = $lastRegen->modify('+' . $secondsConsumed . ' seconds');

            $db->execute(
                'UPDATE players SET action_points = ?, last_ap_regen = ? WHERE id = ?',
                [$current, $newLastRegen->format('Y-m-d H:i:s'), $playerId],
            );
        } elseif ($current >= self::MAX_AP && $stored !== self::MAX_AP) {
            // Clamp to max if somehow above cap in DB
            $db->execute(
                'UPDATE players SET action_points = ? WHERE id = ?',
                [self::MAX_AP, $playerId],
            );
            $current = self::MAX_AP;
        }

        return [
            'current'        => $current,
            'max'            => self::MAX_AP,
            'regen_per_hour' => self::REGEN_PER_HOUR,
        ];
    }

    /**
     * Deducts AP from a player after validating sufficient balance.
     * Calls get() first to apply pending regeneration.
     *
     * @throws \RuntimeException if the player has insufficient AP
     */
    public static function deduct(int $playerId, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        $db = Connection::getInstance();

        // Apply regen first so we operate on the freshest value
        $state   = self::get($playerId);
        $current = $state['current'];

        if ($current < $cost) {
            throw new \RuntimeException(
                'Nicht genug Aktionspunkte. Vorhanden: ' . $current . ', benötigt: ' . $cost . '.'
            );
        }

        $db->execute(
            'UPDATE players SET action_points = action_points - ? WHERE id = ? AND action_points >= ?',
            [$cost, $playerId, $cost],
        );
    }

    /**
     * Returns the AP cost for attacking a named monster.
     *
     * Cost table:
     *   'Deathkar'                    → 25 AP
     *   'Dragon' / 'Wyrm' / 'Wyvern' → 30 AP
     *   everything else               → 10 AP
     */
    public static function costForMonster(string $monsterName): int
    {
        $name = strtolower(trim($monsterName));

        if ($name === 'deathkar') {
            return 25;
        }

        if (
            str_contains($name, 'dragon') ||
            str_contains($name, 'wyrm')   ||
            str_contains($name, 'wyvern')
        ) {
            return 30;
        }

        return 10;
    }
}
