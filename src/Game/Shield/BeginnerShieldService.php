<?php
declare(strict_types=1);

namespace Conquer\Game\Shield;

use Conquer\Db\Connection;

/**
 * Beginner Shield — protects new players from PvP attacks for up to 7 days.
 *
 * The shield expiry timestamp is stored in players.beginner_shield_until (DATETIME, UTC).
 * A NULL value means the shield has been removed or was never granted.
 *
 * The shield is deactivated automatically the first time the player:
 *   - Attacks another player's city
 *   - Rallies another player
 *
 * Administrators can also call deactivate() directly.
 */
final class BeginnerShieldService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Returns true when the player's beginner shield is currently active.
     *
     * Uses the DB server's UTC clock (UTC_TIMESTAMP()) to avoid any drift
     * between application and database time zones.
     */
    public static function isActive(int $playerId): bool
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT beginner_shield_until > UTC_TIMESTAMP() AS active
             FROM   players
             WHERE  id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            return false;
        }

        return (bool) $row['active'];
    }

    /**
     * Returns the shield expiry datetime string (Y-m-d H:i:s, UTC),
     * or null if no shield is set or it has already expired.
     */
    public static function getExpiresAt(int $playerId): ?string
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT beginner_shield_until
             FROM   players
             WHERE  id = ?',
            [$playerId],
        )->fetch();

        if ($row === false || $row['beginner_shield_until'] === null) {
            return null;
        }

        return (string) $row['beginner_shield_until'];
    }

    /**
     * Immediately removes the beginner shield from a player.
     *
     * Idempotent — safe to call even if the shield is already gone.
     */
    public static function deactivate(int $playerId): void
    {
        $db = Connection::getInstance();

        $db->execute(
            'UPDATE players SET beginner_shield_until = NULL WHERE id = ?',
            [$playerId],
        );
    }

    /**
     * Extends (or restores) the shield by the given number of hours.
     *
     * The extension is added on top of whichever is later:
     *   - the current shield expiry (if the shield is still active), or
     *   - the current UTC time (if the shield has lapsed or is NULL).
     *
     * This means calling extendShield(24) always grants at least 24 more hours
     * from now, regardless of the current shield state.
     *
     * Uses a single SQL expression to avoid race conditions.
     */
    public static function extendShield(int $playerId, int $hours): void
    {
        if ($hours <= 0) {
            return;
        }

        $db = Connection::getInstance();

        $db->execute(
            'UPDATE players
             SET    beginner_shield_until = DATE_ADD(
                        GREATEST(
                            COALESCE(beginner_shield_until, UTC_TIMESTAMP()),
                            UTC_TIMESTAMP()
                        ),
                        INTERVAL ? HOUR
                    )
             WHERE  id = ?',
            [$hours, $playerId],
        );
    }

    /**
     * Returns true when the target player can be attacked (i.e., their shield
     * is not currently active).
     *
     * Call this from MarchDispatcher / BattleEngine before allowing a PvP march.
     */
    public static function canBeAttacked(int $targetPlayerId): bool
    {
        return !self::isActive($targetPlayerId);
    }
}
