<?php
declare(strict_types=1);

namespace Conquer\Game\Research;

use Conquer\Db\Connection;

/**
 * Calculates effective research buff values for a player.
 *
 * Each research node has a category + stat field that determines its buff key
 * (resolved by ResearchData::buffKey()). The ability_value per level entry is
 * the cumulative value at that level — not a per-level delta — so we read only
 * the entry for the player's current level.
 *
 * Buff values are additive across nodes that share the same key:
 *   infantry_hp at Lv 3 (0.03) + troops_hp at Lv 2 (0.02) → infantry_hp total = 0.03,
 *   effectiveMultiplier uses both.
 *
 * Integer flat bonuses (march_size, hospital_capacity, etc.) are stored as int
 * sums; all percentage buffs are stored as float.
 */
final class BuffEngine
{
    private function __construct() {}

    /**
     * Stat keys that accumulate as flat integers rather than percentages.
     * Anything not in this list is treated as a percentage (float).
     */
    private const FLAT_INT_STATS = [
        'march_size'        => true,
        'march_limit'       => true,
        'hospital_capacity' => true,
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Loads all unlocked research levels for the player and builds the buff map.
     *
     * For each research code the player has levelled:
     *  - Determine the buff key via ResearchData::buffKey()
     *  - Read the ability_value for the player's current level
     *  - Add it to the running total for that key
     *
     * Returns a flat array keyed by stat name.
     * Missing keys are not included — callers should use ($buffs[$key] ?? 0.0).
     *
     * @return array<string, int|float>
     */
    public static function getBuffs(int $playerId, int $worldId = 1): array
    {
        $result   = [];
        $research = self::loadPlayerResearch($playerId, $worldId);

        if (empty($research)) {
            return $result;
        }

        foreach ($research as $code => $playerLevel) {
            if ($playerLevel <= 0) {
                continue;
            }

            $node = ResearchData::get($code);
            if ($node === null) {
                continue;
            }

            $buffKey = ResearchData::buffKey($node);
            if ($buffKey === '') {
                // Unlock-type nodes produce no numeric buff.
                continue;
            }

            // Find the level entry for the player's current level.
            // ability_value is cumulative so we only need the matching entry.
            $abilityValue = null;
            foreach ($node['levels'] as $entry) {
                if ((int) $entry['level'] === $playerLevel) {
                    $abilityValue = $entry['ability_value'];
                    break;
                }
            }

            if ($abilityValue === null) {
                continue;
            }

            $isFlat = isset(self::FLAT_INT_STATS[$buffKey]);

            if (!isset($result[$buffKey])) {
                $result[$buffKey] = $isFlat ? 0 : 0.0;
            }

            if ($isFlat) {
                $result[$buffKey] += (int) $abilityValue;
            } else {
                $result[$buffKey] += (float) $abilityValue;
            }
        }

        return $result;
    }

    /**
     * Returns the effective combat multiplier for a given troop type and stat.
     *
     * The formula combines the specific-type buff with the general troops_* buff:
     *   Infantry Attack multiplier = 1.0 + infantry_atk + troops_atk
     *   Ranged HP multiplier       = 1.0 + ranged_hp    + troops_hp
     *   etc.
     *
     * When $type is null (or unrecognised), only the general troops_* buff applies.
     *
     * @param array<string, int|float> $buffs  Result of getBuffs()
     * @param string|null              $type   'infantry' | 'ranged' | 'cavalry' | null
     * @param string                   $stat   'atk' | 'hp' | 'def' | 'spd'
     *                                         OR legacy aliases: 'attack'|'defense'|'speed'
     */
    public static function effectiveMultiplier(
        array   $buffs,
        ?string $type,
        string  $stat,
    ): float {
        // Normalise legacy stat name aliases.
        $statKey = match ($stat) {
            'attack'  => 'atk',
            'defense' => 'def',
            'speed'   => 'spd',
            default   => $stat,
        };

        // General troops_* buff always applies.
        $general = (float) ($buffs['troops_' . $statKey] ?? 0.0);

        // Type-specific buff (null type = no specific buff).
        $specific = 0.0;
        if ($type !== null) {
            $specific = (float) ($buffs[$type . '_' . $statKey] ?? 0.0);
        }

        return 1.0 + $specific + $general;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Fetches [research_code => level] from player_research for a given player.
     *
     * @return array<string, int>
     */
    private static function loadPlayerResearch(int $playerId, int $worldId): array
    {
        $db = Connection::getInstance();

        try {
            $rows = $db->query(
                'SELECT research_code, level
                 FROM   player_research
                 WHERE  player_id = ? AND world_id = ?',
                [$playerId, $worldId],
            )->fetchAll();
        } catch (\PDOException) {
            // Table may not exist yet during early migrations.
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['research_code']] = (int) $row['level'];
        }

        return $result;
    }
}
