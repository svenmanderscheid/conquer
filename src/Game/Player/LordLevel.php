<?php
declare(strict_types=1);

namespace Conquer\Game\Player;

use Conquer\Db\Connection;

/**
 * Lord Level system — 50 levels driven by XP.
 *
 * XP formula (SPEC §17 / lord-level design):
 *   - Normal levels within a decade (positions 1-8 inside that decade):
 *       XP(n) = 50 * n²   where n is the 1-based position in the decade.
 *   - Milestone levels (9, 19, 29, 39, 49):
 *       XP = sum of the 8 normal levels in that decade.
 *   - Level 50:
 *       XP = sum of levels 41-49 (last milestone).
 *
 * Decade boundaries:
 *   Decade 1 → levels  1- 9   (normals 1-8, milestone 9)
 *   Decade 2 → levels 10-19   (normals 10-18, milestone 19)
 *   Decade 3 → levels 20-29   (normals 20-28, milestone 29)
 *   Decade 4 → levels 30-39   (normals 30-38, milestone 39)
 *   Decade 5 → levels 40-49   (normals 40-48, milestone 49)
 *   Level 50 → special capstone
 */
final class LordLevel
{
    private const MAX_LEVEL = 50;

    private function __construct() {}

    // -------------------------------------------------------------------------
    // XP table (cached on first build)
    // -------------------------------------------------------------------------

    /** @var array<int,int>|null  level → XP required to reach that level */
    private static ?array $table = null;

    /**
     * Returns the full XP-per-level table (index 1..50).
     *
     * @return array<int,int>
     */
    private static function table(): array
    {
        if (self::$table !== null) {
            return self::$table;
        }

        $table = [];

        for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
            $table[$level] = self::computeXpForLevel($level);
        }

        self::$table = $table;
        return $table;
    }

    /**
     * Pure computation — maps a level number to XP required.
     * No caching, no side effects.
     */
    private static function computeXpForLevel(int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        // Level 50 capstone: sum of levels 41-49
        if ($level === self::MAX_LEVEL) {
            $sum = 0;
            for ($l = 41; $l <= 49; $l++) {
                $sum += self::computeXpForLevel($l);
            }
            return $sum;
        }

        // Determine which decade (0-indexed: 0=levels 1-9, 1=levels 10-19, ...)
        $decadeIndex = (int) (($level - 1) / 9);   // 0..4
        $posInDecade = $level - $decadeIndex * 9;   // 1..9

        // Position 9 in the decade = milestone
        if ($posInDecade === 9) {
            // Sum of the 8 normal levels in this decade
            $base = $decadeIndex * 9; // level number of the first in this decade
            $sum  = 0;
            for ($pos = 1; $pos <= 8; $pos++) {
                $sum += self::computeXpForLevel($base + $pos);
            }
            return $sum;
        }

        // Normal level: XP = 50 * posInDecade²
        return 50 * $posInDecade * $posInDecade;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the XP required to reach a given level (not cumulative — just that step).
     * Level 0 and below returns 0.
     */
    public static function xpForLevel(int $level): int
    {
        if ($level <= 0 || $level > self::MAX_LEVEL) {
            return 0;
        }
        return self::table()[$level];
    }

    /**
     * Returns the lord level a player holds given their cumulative XP total.
     * Returns 0 if below level 1 threshold, capped at MAX_LEVEL.
     */
    public static function levelFromTotalXp(int $totalXp): int
    {
        if ($totalXp <= 0) {
            return 0;
        }

        $cumulative = 0;
        $reached    = 0;

        foreach (self::table() as $level => $xpNeeded) {
            $cumulative += $xpNeeded;
            if ($totalXp >= $cumulative) {
                $reached = $level;
            } else {
                break;
            }
        }

        return min($reached, self::MAX_LEVEL);
    }

    /**
     * Returns XP earned within the current level (progress toward next level).
     * E.g. if current level needs 200 XP and you have 350 total-XP-into-level, returns 150.
     */
    public static function xpIntoCurrentLevel(int $totalXp): int
    {
        $currentLevel = self::levelFromTotalXp($totalXp);

        // Sum XP required to finish all levels up to (but not including) current
        $spent = 0;
        for ($l = 1; $l <= $currentLevel; $l++) {
            $spent += self::xpForLevel($l);
        }

        return max(0, $totalXp - $spent);
    }

    /**
     * Returns XP required for the next level.
     * Returns 0 when already at MAX_LEVEL.
     */
    public static function xpForNextLevel(int $currentLevel): int
    {
        $next = $currentLevel + 1;
        if ($next > self::MAX_LEVEL) {
            return 0;
        }
        return self::xpForLevel($next);
    }

    /**
     * Adds XP to a player, persisting both lord_xp and lord_level to the DB.
     * Uses a transaction to ensure consistency.
     *
     * @throws \RuntimeException if the player is not found
     */
    public static function addXp(int $playerId, int $xp): void
    {
        if ($xp <= 0) {
            return;
        }

        $db = Connection::getInstance();

        $db->transaction(function (Connection $db) use ($playerId, $xp): void {
            // Atomic increment
            $db->execute(
                'UPDATE players SET lord_xp = lord_xp + ? WHERE id = ?',
                [$xp, $playerId],
            );

            // Fetch new total
            $row = $db->query(
                'SELECT lord_xp FROM players WHERE id = ?',
                [$playerId],
            )->fetch();

            if ($row === false) {
                throw new \RuntimeException('Player not found: ' . $playerId);
            }

            $newTotalXp = (int) $row['lord_xp'];
            $newLevel   = self::levelFromTotalXp($newTotalXp);

            $db->execute(
                'UPDATE players SET lord_level = ? WHERE id = ?',
                [$newLevel, $playerId],
            );
        });
    }
}
