<?php
declare(strict_types=1);

namespace Conquer\Game\Treasure;

use Conquer\Db\Connection;

/**
 * Handles chest inventory and chest opening logic.
 *
 * Chest types: silver | gold | platinum
 *
 * DB table used: player_chests
 *   (player_id, silver_count, gold_count, platinum_count,
 *    free_silver_used_today, last_free_silver_reset)
 *
 * Item drops are stored in player_items (item_code, quantity).
 * Fragment drops are forwarded to TreasureService::addFragments().
 */
final class ChestService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public const FREE_SILVER_PER_DAY      = 5;
    public const GOLD_CHEST_COST_GEMS     = 50;
    public const PLATINUM_CHEST_COST_GEMS = 200;

    /** Valid chest type identifiers. */
    private const VALID_TYPES = ['silver', 'gold', 'platinum'];

    /** @var array<string, mixed>|null */
    private static ?array $dropTableCache = null;

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Returns the player's current chest inventory and free-silver status.
     *
     * @return array{
     *     silver_count: int,
     *     gold_count: int,
     *     platinum_count: int,
     *     free_silver_remaining: int,
     *     free_silver_resets_at: string
     * }
     */
    public static function getChestStatus(int $playerId): array
    {
        $db  = Connection::getInstance();
        $row = self::loadOrCreateRow($db, $playerId);

        [$usedToday, $resetDate] = self::resolveFreeSilver($db, $playerId, $row, commit: false);

        $remaining = max(0, self::FREE_SILVER_PER_DAY - $usedToday);

        // Next reset is start of tomorrow in UTC.
        $tomorrow = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+1 day')
            ->setTime(0, 0, 0)
            ->format('Y-m-d H:i:s');

        return [
            'silver_count'          => (int) $row['silver_count'],
            'gold_count'            => (int) $row['gold_count'],
            'platinum_count'        => (int) $row['platinum_count'],
            'free_silver_remaining' => $remaining,
            'free_silver_resets_at' => $tomorrow,
        ];
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    /**
     * Opens one chest of the given type for the player.
     *
     * For silver chests: uses free opens first, then requires silver_count > 0.
     * For gold / platinum: requires the corresponding count > 0.
     *
     * Returns an array of rewards:
     *   [
     *     ['type' => 'item',     'item_code' => 10103002, 'quantity' => 1],
     *     ['type' => 'fragment', 'treasure_code' => 60100001, 'grade' => 'normal',
     *      'quantity' => 5, 'name' => 'Manure', 'new_total' => 10],
     *   ]
     *
     * @return list<array<string, mixed>>
     */
    public static function openChest(int $playerId, string $chestType): array
    {
        if (!in_array($chestType, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid chest type: ' . $chestType);
        }

        $db  = Connection::getInstance();
        $row = self::loadOrCreateRow($db, $playerId);

        return $db->transaction(function (Connection $db) use ($playerId, $chestType, $row): array {
            // Re-read inside transaction for row-level lock.
            $db->execute(
                'SELECT 1 FROM player_chests WHERE player_id = ? FOR UPDATE',
                [$playerId],
            );
            $row = self::loadOrCreateRow($db, $playerId);

            // Deduct from inventory (or free uses).
            if ($chestType === 'silver') {
                [$usedToday, $resetDate] = self::resolveFreeSilver($db, $playerId, $row, commit: true);

                if ($usedToday < self::FREE_SILVER_PER_DAY) {
                    // Use a free open.
                    $db->execute(
                        'UPDATE player_chests
                         SET free_silver_used_today = free_silver_used_today + 1
                         WHERE player_id = ?',
                        [$playerId],
                    );
                } elseif ((int) $row['silver_count'] > 0) {
                    $db->execute(
                        'UPDATE player_chests SET silver_count = silver_count - 1 WHERE player_id = ?',
                        [$playerId],
                    );
                } else {
                    throw new \RuntimeException('NO_SILVER_CHEST');
                }
            } elseif ($chestType === 'gold') {
                if ((int) $row['gold_count'] < 1) {
                    throw new \RuntimeException('NO_GOLD_CHEST');
                }
                $db->execute(
                    'UPDATE player_chests SET gold_count = gold_count - 1 WHERE player_id = ?',
                    [$playerId],
                );
            } else { // platinum
                if ((int) $row['platinum_count'] < 1) {
                    throw new \RuntimeException('NO_PLATINUM_CHEST');
                }
                $db->execute(
                    'UPDATE player_chests SET platinum_count = platinum_count - 1 WHERE player_id = ?',
                    [$playerId],
                );
            }

            // Roll the drop table.
            $drops = self::rollDropTable($chestType);

            // Distribute rewards.
            $rewards = [];
            foreach ($drops as $drop) {
                if (isset($drop['fragment_grade'])) {
                    $grade    = (string) $drop['fragment_grade'];
                    $quantity = (int)   $drop['quantity'];
                    $result   = TreasureService::addRandomFragment($playerId, $grade, $quantity);

                    $rewards[] = [
                        'type'          => 'fragment',
                        'grade'         => $grade,
                        'treasure_code' => $result['treasure_code'],
                        'name'          => $result['name'],
                        'quantity'      => $quantity,
                        'new_total'     => $result['new_total'],
                    ];
                } else {
                    $itemCode = (int) $drop['item_code'];
                    $quantity = (int) $drop['quantity'];

                    self::addItemToInventory($db, $playerId, $itemCode, $quantity);

                    $rewards[] = [
                        'type'      => 'item',
                        'item_code' => $itemCode,
                        'quantity'  => $quantity,
                    ];
                }
            }

            return $rewards;
        });
    }

    /**
     * Adds chests of the given type to a player's inventory.
     */
    public static function addChest(int $playerId, string $chestType, int $count = 1): void
    {
        if (!in_array($chestType, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid chest type: ' . $chestType);
        }
        if ($count < 1) {
            throw new \InvalidArgumentException('count must be >= 1');
        }

        $column = $chestType . '_count'; // silver_count | gold_count | platinum_count
        $db     = Connection::getInstance();

        self::ensureRowExists($db, $playerId);

        $db->execute(
            "UPDATE player_chests SET {$column} = {$column} + ? WHERE player_id = ?",
            [$count, $playerId],
        );
    }

    // -------------------------------------------------------------------------
    // Drop table
    // -------------------------------------------------------------------------

    /**
     * Rolls the drop table for the given chest type.
     * Each roll produces exactly one entry from the table.
     *
     * @return list<array<string, mixed>>  One entry per roll.
     */
    public static function rollDropTable(string $chestType): array
    {
        $table  = self::loadDropTable();
        $config = $table['chests'][$chestType] ?? null;

        if ($config === null) {
            throw new \RuntimeException('No drop table configured for chest type: ' . $chestType);
        }

        $rolls   = (int) ($config['rolls'] ?? 1);
        $entries = (array) $config['drop_table'];
        $results = [];

        for ($i = 0; $i < $rolls; $i++) {
            $results[] = self::weightedRandom($entries);
        }

        return $results;
    }

    /**
     * Selects one entry from a weighted table using cumulative weight.
     *
     * @param list<array<string, mixed>> $table
     * @return array<string, mixed>
     */
    private static function weightedRandom(array $table): array
    {
        $totalWeight = 0;
        foreach ($table as $entry) {
            $totalWeight += (int) ($entry['weight'] ?? 0);
        }

        if ($totalWeight <= 0) {
            throw new \RuntimeException('Drop table has zero total weight.');
        }

        $roll      = random_int(1, $totalWeight);
        $cumulated = 0;

        foreach ($table as $entry) {
            $cumulated += (int) ($entry['weight'] ?? 0);
            if ($roll <= $cumulated) {
                return $entry;
            }
        }

        // Fallback (should never be reached with a valid table).
        return $table[array_key_last($table)];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Loads the chest drop table JSON and caches it.
     *
     * @return array<string, mixed>
     */
    private static function loadDropTable(): array
    {
        if (self::$dropTableCache !== null) {
            return self::$dropTableCache;
        }

        $file = ROOT_DIR . '/data/chest_drops.json';

        if (!is_file($file)) {
            throw new \RuntimeException('chest_drops.json not found at: ' . $file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read chest_drops.json');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('chest_drops.json has unexpected structure.');
        }

        self::$dropTableCache = $decoded;
        return $decoded;
    }

    /**
     * Loads the player_chests row, creating it if absent.
     *
     * @return array<string, mixed>
     */
    private static function loadOrCreateRow(Connection $db, int $playerId): array
    {
        self::ensureRowExists($db, $playerId);

        $row = $db->query(
            'SELECT silver_count, gold_count, platinum_count,
                    free_silver_used_today, last_free_silver_reset
             FROM   player_chests
             WHERE  player_id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Failed to load player_chests row for player ' . $playerId);
        }

        /** @var array<string, mixed> */
        return $row;
    }

    /**
     * Inserts a default player_chests row if one doesn't exist yet.
     */
    private static function ensureRowExists(Connection $db, int $playerId): void
    {
        $db->execute(
            'INSERT IGNORE INTO player_chests
                (player_id, silver_count, gold_count, platinum_count,
                 free_silver_used_today, last_free_silver_reset)
             VALUES (?, 0, 0, 0, 0, NULL)',
            [$playerId],
        );
    }

    /**
     * Resolves the free-silver counter, resetting it when the UTC date has changed.
     *
     * Returns [used_today, reset_date_string].
     * When $commit is true the DB row is updated in-place if a reset occurred.
     *
     * @param array<string, mixed> $row
     * @return array{int, string}
     */
    private static function resolveFreeSilver(
        Connection $db,
        int $playerId,
        array $row,
        bool $commit,
    ): array {
        $todayUtc  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $lastReset = isset($row['last_free_silver_reset'])
            ? (string) $row['last_free_silver_reset']
            : '';

        // Extract date part (the column may be a full DATETIME string).
        $lastResetDate = strlen($lastReset) >= 10 ? substr($lastReset, 0, 10) : '';

        if ($lastResetDate !== $todayUtc) {
            // New day — reset the counter.
            if ($commit) {
                $db->execute(
                    'UPDATE player_chests
                     SET free_silver_used_today = 0, last_free_silver_reset = ?
                     WHERE player_id = ?',
                    [$todayUtc, $playerId],
                );
            }

            return [0, $todayUtc];
        }

        return [(int) $row['free_silver_used_today'], $lastResetDate];
    }

    /**
     * Adds an item to player_items (INSERT … ON DUPLICATE KEY UPDATE).
     * Creates the table row when absent.
     */
    private static function addItemToInventory(
        Connection $db,
        int $playerId,
        int $itemCode,
        int $quantity,
    ): void {
        $db->execute(
            'INSERT INTO player_items (player_id, item_code, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + ?',
            [$playerId, $itemCode, $quantity, $quantity],
        );
    }
}
