<?php
declare(strict_types=1);

namespace Conquer\Game\Vip;

use Conquer\Db\Connection;

/**
 * VIP system — level thresholds, passive bonuses, daily login reward.
 *
 * VIP is a 20-level account-wide system (SPEC §17).
 * Players earn VIP points through daily logins and item use.
 * Higher levels grant passive percentage bonuses to construction,
 * research, resource production, and troop training speed.
 */
final class VipService
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Level thresholds (cumulative points required)
    // Index = VIP level, value = points needed to reach that level.
    // -------------------------------------------------------------------------

    /**
     * Cumulative VIP points required to reach each level.
     * Index = VIP level (0-20), value = points needed.
     * VIP 20 requires 20,000,000 points (cap).
     *
     * @var list<int>
     */
    private const THRESHOLDS = [
        0,          // VIP 0  — no VIP (starting state)
        200,        // VIP 1
        500,        // VIP 2
        1_000,      // VIP 3
        5_000,      // VIP 4
        10_000,     // VIP 5
        20_000,     // VIP 6
        50_000,     // VIP 7
        100_000,    // VIP 8
        150_000,    // VIP 9
        200_000,    // VIP 10
        250_000,    // VIP 11
        500_000,    // VIP 12
        1_000_000,  // VIP 13
        1_500_000,  // VIP 14
        2_000_000,  // VIP 15
        3_000_000,  // VIP 16
        4_000_000,  // VIP 17
        8_000_000,  // VIP 18
        12_000_000, // VIP 19
        20_000_000, // VIP 20
    ];

    /**
     * Bonuses per VIP level (additive percentage).
     * Keys: construction_speed, research_speed, resource_production, troop_training_speed
     *
     * @var array<int, array{int, int, int, int}>
     */
    private const BONUSES = [
        0  => [0,   0,   0,   0  ],
        1  => [1,   0,   3,   0  ],
        2  => [2,   0,   6,   0  ],
        3  => [4,   3,   10,  0  ],
        4  => [6,   5,   15,  0  ],
        5  => [8,   8,   20,  0  ],
        6  => [10,  10,  25,  0  ],
        7  => [13,  13,  30,  0  ],
        8  => [16,  16,  40,  0  ],
        9  => [20,  20,  50,  5  ],
        10 => [25,  25,  60,  10 ],
        11 => [30,  30,  70,  15 ],
        12 => [36,  36,  80,  18 ],
        13 => [42,  42,  85,  20 ],
        14 => [50,  50,  90,  22 ],
        15 => [58,  58,  95,  24 ],
        16 => [66,  66,  97,  25 ],
        17 => [75,  75,  99,  25 ],
        18 => [85,  85,  100, 25 ],
        19 => [92,  92,  100, 25 ],
        20 => [100, 100, 100, 25 ],
    ];

    private const MAX_LEVEL = 20;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Calculates VIP level for a given cumulative point total.
     * Returns 0–20.
     */
    public static function levelForPoints(int $points): int
    {
        $level = 0;

        foreach (self::THRESHOLDS as $index => $threshold) {
            if ($points >= $threshold) {
                $level = $index;
            } else {
                break;
            }
        }

        // Check if player has exceeded the last threshold (VIP 20)
        return min($level, self::MAX_LEVEL);
    }

    /**
     * Returns the bonus array for a given VIP level.
     *
     * @return array{construction_speed: int, research_speed: int, resource_production: int, troop_training_speed: int}
     */
    public static function bonuses(int $level): array
    {
        $level  = max(0, min(self::MAX_LEVEL, $level));
        $raw    = self::BONUSES[$level];

        return [
            'construction_speed'   => $raw[0],
            'research_speed'       => $raw[1],
            'resource_production'  => $raw[2],
            'troop_training_speed' => $raw[3],
        ];
    }

    /**
     * Returns the full VIP status for a player, reading from DB.
     *
     * @return array{level: int, points: int, next_level_points: int, bonuses: array<string, int>}
     */
    public static function status(int $playerId): array
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT vip_points, vip_level FROM players WHERE id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            // Player not found — return zero state
            return [
                'level'             => 0,
                'points'            => 0,
                'next_level_points' => self::THRESHOLDS[1] ?? 0,
                'bonuses'           => self::bonuses(0),
            ];
        }

        $points = (int) $row['vip_points'];
        $level  = (int) $row['vip_level'];

        // Points to next level: threshold for level+1, or 0 if already at max
        $nextLevelPoints = ($level < self::MAX_LEVEL)
            ? (self::THRESHOLDS[$level + 1] ?? 0)
            : 0;

        return [
            'level'             => $level,
            'points'            => $points,
            'next_level_points' => $nextLevelPoints,
            'bonuses'           => self::bonuses($level),
        ];
    }

    /**
     * Awards +10 VIP points for daily login. Maximum once per calendar day (UTC).
     *
     * Returns true if points were awarded, false if already claimed today.
     */
    public static function dailyLogin(int $playerId): bool
    {
        $db = Connection::getInstance();

        // Fetch current points first so we can compute the new level
        $row = $db->query(
            'SELECT vip_points FROM players WHERE id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            return false;
        }

        $newPoints = (int) $row['vip_points'] + 10;
        $newLevel  = self::levelForPoints($newPoints);

        $affected = $db->execute(
            'UPDATE players
             SET    vip_points    = vip_points + 10,
                    vip_level     = ?,
                    last_vip_login = CURDATE()
             WHERE  id = ?
               AND  (last_vip_login IS NULL OR last_vip_login < CURDATE())',
            [$newLevel, $playerId],
        );

        return $affected > 0;
    }

    /**
     * Adds arbitrary VIP points to a player (e.g. from item use).
     * Recomputes and persists the level.
     */
    public static function addPoints(int $playerId, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $db = Connection::getInstance();

        // Fetch current total first so we can recalculate level
        $row = $db->query(
            'SELECT vip_points FROM players WHERE id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            return;
        }

        $newPoints = (int) $row['vip_points'] + $points;
        $newLevel  = self::levelForPoints($newPoints);

        $db->execute(
            'UPDATE players
             SET vip_points = vip_points + ?,
                 vip_level  = ?
             WHERE id = ?',
            [$points, $newLevel, $playerId],
        );
    }
}
