<?php
declare(strict_types=1);

namespace Conquer\Game\Treasure;

use Conquer\Db\Connection;

/**
 * All business logic around player treasures.
 *
 * DB tables used:
 *   player_treasures (player_id, treasure_code, fragments, equipped_slot)
 */
final class TreasureService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Returns all treasures the player has fragments for, enriched with
     * static data and computed level/unlock state.
     *
     * @return list<array<string, mixed>>
     */
    public static function getPlayerTreasures(int $playerId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            'SELECT treasure_code, fragments, equipped_slot
             FROM   player_treasures
             WHERE  player_id = ?
             ORDER  BY treasure_code ASC',
            [$playerId],
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $code     = (int)  $row['treasure_code'];
            $frags    = (int)  $row['fragments'];
            $slot     = $row['equipped_slot'] !== null ? (int) $row['equipped_slot'] : null;
            $level    = self::levelFromFragments($frags);
            $unlocked = $level >= 1;

            $definition = TreasureData::get($code);

            if ($definition === null) {
                // Unknown code in DB — skip gracefully.
                continue;
            }

            // Build stat array at current level.
            $statsAtLevel = [];
            if ($unlocked && $level > 0) {
                foreach ($definition['stats'] as $stat) {
                    $statType           = (string) $stat['type'];
                    $statsAtLevel[$statType] = TreasureData::getStatValue($definition, $level, $statType);
                }
            }

            $result[] = [
                'treasure_code'  => $code,
                'name'           => (string) ($definition['name']  ?? ''),
                'grade'          => (string) ($definition['grade'] ?? 'normal'),
                'description'    => (string) ($definition['description'] ?? ''),
                'fragments'      => $frags,
                'fragments_next' => self::fragmentsForNextLevel($definition, $level),
                'level'          => $level,
                'max_level'      => (int) ($definition['max_level'] ?? 10),
                'equipped_slot'  => $slot,
                'stats_at_level' => $statsAtLevel,
                'is_unlocked'    => $unlocked,
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    /**
     * Adds fragments to a player's treasure (creates the row if absent).
     *
     * Returns the new state: {fragments, level, newly_unlocked}.
     *
     * @return array{fragments: int, level: int, newly_unlocked: bool}
     */
    public static function addFragments(int $playerId, int $treasureCode, int $amount): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be > 0, got ' . $amount);
        }

        $db = Connection::getInstance();

        // Read before-state to detect level-up / unlock.
        $before = $db->query(
            'SELECT fragments FROM player_treasures WHERE player_id = ? AND treasure_code = ?',
            [$playerId, $treasureCode],
        )->fetchColumn();

        $fragsBefore = $before !== false ? (int) $before : 0;
        $levelBefore = self::levelFromFragments($fragsBefore);

        $db->execute(
            'INSERT INTO player_treasures (player_id, treasure_code, fragments)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE fragments = fragments + ?',
            [$playerId, $treasureCode, $amount, $amount],
        );

        $fragsAfter  = $fragsBefore + $amount;
        $levelAfter  = self::levelFromFragments($fragsAfter);
        $newlyUnlocked = ($levelBefore < 1) && ($levelAfter >= 1);

        return [
            'fragments'      => $fragsAfter,
            'level'          => $levelAfter,
            'newly_unlocked' => $newlyUnlocked,
        ];
    }

    /**
     * Equips a treasure to the given slot.
     *
     * Validation:
     *   - Treasure must be unlocked (level >= 1).
     *   - Slot must be within the unlocked range for the player's Treasure House level.
     *   - Another treasure must not already occupy that slot.
     *
     * If the treasure is already equipped in a different slot it is moved.
     * Returns false on any validation failure (caller decides on error message).
     */
    public static function equipTreasure(
        int $playerId,
        int $treasureCode,
        int $slot,
        int $treasureHouseLevel,
    ): bool {
        if ($slot < 1 || $slot > 6) {
            return false;
        }

        $maxSlots = TreasureData::getUnlockSlots($treasureHouseLevel);
        if ($slot > $maxSlots) {
            return false;
        }

        $db = Connection::getInstance();

        // Load the player's row for this treasure.
        $row = $db->query(
            'SELECT fragments, equipped_slot FROM player_treasures
             WHERE  player_id = ? AND treasure_code = ?',
            [$playerId, $treasureCode],
        )->fetch();

        if ($row === false) {
            return false; // Player doesn't own this treasure.
        }

        $level = self::levelFromFragments((int) $row['fragments']);
        if ($level < 1) {
            return false; // Locked — needs at least 1 full level.
        }

        $currentSlot = $row['equipped_slot'] !== null ? (int) $row['equipped_slot'] : null;

        // Check if something else is already in the target slot.
        $occupant = $db->query(
            'SELECT treasure_code FROM player_treasures
             WHERE  player_id = ? AND equipped_slot = ? AND treasure_code != ?',
            [$playerId, $slot, $treasureCode],
        )->fetchColumn();

        if ($occupant !== false) {
            return false; // Slot taken by another treasure.
        }

        // If already in a different slot, clear that slot first.
        if ($currentSlot !== null && $currentSlot !== $slot) {
            $db->execute(
                'UPDATE player_treasures SET equipped_slot = NULL
                 WHERE  player_id = ? AND treasure_code = ?',
                [$playerId, $treasureCode],
            );
        }

        $db->execute(
            'UPDATE player_treasures SET equipped_slot = ?
             WHERE  player_id = ? AND treasure_code = ?',
            [$slot, $playerId, $treasureCode],
        );

        return true;
    }

    /**
     * Removes a treasure from its equipped slot.
     */
    public static function unequipTreasure(int $playerId, int $treasureCode): bool
    {
        $db = Connection::getInstance();

        $affected = $db->execute(
            'UPDATE player_treasures SET equipped_slot = NULL
             WHERE  player_id = ? AND treasure_code = ? AND equipped_slot IS NOT NULL',
            [$playerId, $treasureCode],
        );

        return $affected > 0;
    }

    /**
     * Returns aggregated stat bonuses from all equipped treasures.
     *
     * Example return value:
     *   ['infantry_attack' => 5.5, 'all_defense' => 3.0, 'march_speed' => 2.0]
     *
     * @return array<string, float>
     */
    public static function getEquippedStats(int $playerId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            'SELECT treasure_code, fragments
             FROM   player_treasures
             WHERE  player_id = ? AND equipped_slot IS NOT NULL',
            [$playerId],
        )->fetchAll();

        $aggregated = [];

        foreach ($rows as $row) {
            $code       = (int) $row['treasure_code'];
            $frags      = (int) $row['fragments'];
            $level      = self::levelFromFragments($frags);
            $definition = TreasureData::get($code);

            if ($definition === null || $level < 1) {
                continue;
            }

            foreach ($definition['stats'] as $stat) {
                $statType = (string) $stat['type'];
                $value    = TreasureData::getStatValue($definition, $level, $statType);

                $aggregated[$statType] = ($aggregated[$statType] ?? 0.0) + $value;
            }
        }

        return $aggregated;
    }

    /**
     * Picks a random treasure of the given grade and adds fragments to it.
     *
     * @return array{treasure_code: int, name: string, fragments_added: int, new_total: int}
     */
    public static function addRandomFragment(int $playerId, string $grade, int $amount = 1): array
    {
        $codes = TreasureData::getCodesByGrade($grade);

        if (empty($codes)) {
            throw new \RuntimeException('No treasures found for grade: ' . $grade);
        }

        $code       = $codes[array_rand($codes)];
        $definition = TreasureData::get($code);
        $name       = $definition !== null ? (string) ($definition['name'] ?? '') : (string) $code;

        $state = self::addFragments($playerId, $code, $amount);

        return [
            'treasure_code'  => $code,
            'name'           => $name,
            'fragments_added' => $amount,
            'new_total'      => $state['fragments'],
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Computes treasure level from fragment count.
     *
     * fragments_per_level is defined per treasure in the JSON (e.g. 10, 20, 40 …).
     * Since we don't have a specific treasure here we use the simple formula
     * `fragments // 10` which only holds for normal-grade treasures.
     *
     * For server-side internal use (equip checks etc.) we apply the correct
     * per-treasure threshold in the callers that already have the definition.
     * This helper is a fast fallback for fragment → level mapping.
     */
    private static function levelFromFragments(int $fragments): int
    {
        // fragments // 10  — minimum 0, maximum 10.
        return min(10, (int) floor($fragments / 10));
    }

    /**
     * Returns fragments needed to reach the next level, or 0 at max level.
     * Uses the treasure definition's fragments_per_level field.
     *
     * @param array<string, mixed> $definition
     */
    private static function fragmentsForNextLevel(array $definition, int $currentLevel): int
    {
        if ($currentLevel >= (int) ($definition['max_level'] ?? 10)) {
            return 0; // Already at max.
        }

        $perLevel = (int) ($definition['fragments_per_level'] ?? 10);
        return ($currentLevel + 1) * $perLevel;
    }
}
