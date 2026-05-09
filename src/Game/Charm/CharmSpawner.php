<?php
declare(strict_types=1);

namespace Conquer\Game\Charm;

use Conquer\Db\Connection;

/**
 * Spawns a charm on the map when a monster is killed.
 *
 * Drop rate: 100% for Orc / Skeleton / Golem.
 * Grade probabilities depend on monster level (from data/charms.json).
 */
final class CharmSpawner
{
    private function __construct() {}

    /** Stat categories (uniform random pick). */
    private const CATEGORIES = [
        'construction', 'research', 'troops_hp', 'troops_attack',
        'troops_defense', 'carry', 'march_speed', 'gathering',
    ];

    /**
     * Grade weights by monster level tier.
     * Each entry: [normal_weight, epic_weight, legendary_weight]
     */
    private const GRADE_WEIGHTS = [
        // Lv 1–3
        'tier1' => [82, 18,  0],
        // Lv 4–6
        'tier2' => [70, 30,  0],
        // Lv 7–8
        'tier3' => [ 0, 80, 20],
        // Lv 9–10
        'tier4' => [ 0, 50, 50],
    ];

    /** Charm codes per category+grade: code = 10700000 + (categoryIdx * 3) + gradeOffset */
    private const GRADE_OFFSET = ['normal' => 1, 'epic' => 2, 'legendary' => 3];

    /** Bonus percent per grade */
    private const BONUS_PCT = ['normal' => 3.0, 'epic' => 5.0, 'legendary' => 10.0];

    /** Duration in seconds per grade */
    private const DURATION_SECS = ['normal' => 1800, 'epic' => 7200, 'legendary' => 14400];

    /**
     * Spawn a charm at the given tile position. Called after a monster kill.
     * Drop rate is 100% for solo monster types (Orc/Skeleton/Golem).
     * Other monster types are silently ignored.
     *
     * @param int $monsterCode  field_monsters.monster_code value
     */
    public static function spawn(int $worldId, int $x, int $y, int $monsterCode): void
    {
        $level   = $monsterCode % 100;
        $typeIdx = (int) floor($monsterCode / 100) % 100;

        // Only Orc (1), Skeleton (2), Golem (3) drop charms
        if (!in_array($typeIdx, [1, 2, 3], true)) return;

        // Roll grade
        $grade = self::rollGrade($level);

        // Roll stat category (uniform)
        $categoryIdx = array_rand(self::CATEGORIES);
        $category    = self::CATEGORIES[$categoryIdx];

        // Charm code
        $charmCode = 10700000 + ($categoryIdx * 3) + self::GRADE_OFFSET[$grade];

        $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hour on map

        $db = Connection::getInstance();
        $db->execute(
            'INSERT INTO map_charms
                (world_id, coord_x, coord_y, stat_category, grade, charm_code, spawned_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)',
            [$worldId, $x, $y, $category, $grade, $charmCode, $expiresAt],
        );
    }

    /** Get bonus_pct for a grade */
    public static function bonusPct(string $grade): float
    {
        return self::BONUS_PCT[$grade] ?? 3.0;
    }

    /** Get buff duration in seconds for a grade */
    public static function durationSecs(string $grade): int
    {
        return self::DURATION_SECS[$grade] ?? 1800;
    }

    private static function rollGrade(int $level): string
    {
        $weights = match (true) {
            $level <= 3  => self::GRADE_WEIGHTS['tier1'],
            $level <= 6  => self::GRADE_WEIGHTS['tier2'],
            $level <= 8  => self::GRADE_WEIGHTS['tier3'],
            default      => self::GRADE_WEIGHTS['tier4'],
        };

        $total = array_sum($weights);
        $roll  = mt_rand(1, max(1, $total));
        $cum   = 0;
        foreach (['normal', 'epic', 'legendary'] as $i => $grade) {
            $cum += $weights[$i];
            if ($roll <= $cum) return $grade;
        }
        return 'normal';
    }
}
