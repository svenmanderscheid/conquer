<?php
declare(strict_types=1);

namespace Conquer\Game\Inventory;

use Conquer\Db\Connection;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Vip\VipService;
use Conquer\Game\Buff\ActiveBuffService;

/**
 * Player item inventory — add, remove, and use consumable items.
 *
 * Item definitions are loaded once per request from data/items.json and cached
 * in a static property (same pattern as TroopData, ResearchData etc.).
 *
 * Categories (from items.json):
 *   speedup       — reduces a queue timer
 *   boost         — adds a timed % bonus
 *   resource_pack — credits resources to the city / gems to the player
 *   ap_refill     — credits action points to the player
 *   chest         — delegates to ChestService::open()
 *   vip_point     — delegates to VipService::addPoints()
 */
final class InventoryService
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** Path to item definitions. Resolved from ROOT_DIR at runtime. */
    private const ITEMS_FILE = 'data/items.json';

    /** Valid resources stored in the cities table. */
    private const CITY_RESOURCES = ['food', 'lumber', 'stone', 'gold'];

    /** Valid cap columns matching each resource. */
    private const RESOURCE_CAPS = [
        'food'   => 'food_cap',
        'lumber' => 'lumber_cap',
        'stone'  => 'stone_cap',
        'gold'   => 'gold_cap',
    ];

    // -------------------------------------------------------------------------
    // Item definition cache
    // -------------------------------------------------------------------------

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $defs = null;

    /**
     * Returns a single item definition by code, or null if unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function getItemDef(int $code): ?array
    {
        self::loadDefs();
        return self::$defs[$code] ?? null;
    }

    /**
     * Returns all item definitions, keyed by code.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allDefs(): array
    {
        self::loadDefs();
        return self::$defs ?? [];
    }

    // -------------------------------------------------------------------------
    // Inventory mutations
    // -------------------------------------------------------------------------

    /**
     * Grants $quantity of $itemCode to a player.
     * Creates the row if it doesn't exist yet.
     */
    public static function addItems(int $playerId, int $itemCode, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        Connection::getInstance()->execute(
            'INSERT INTO player_inventory (player_id, item_code, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)',
            [$playerId, $itemCode, $quantity],
        );
    }

    /**
     * Removes $quantity of $itemCode from a player's inventory.
     *
     * Returns false if the player doesn't have enough quantity (no mutation happens).
     */
    public static function removeItems(int $playerId, int $itemCode, int $quantity): bool
    {
        if ($quantity <= 0) {
            return true;
        }

        $db = Connection::getInstance();

        // Atomic check-and-decrement: only succeeds when quantity >= $quantity
        $affected = $db->execute(
            'UPDATE player_inventory
             SET    quantity = quantity - ?
             WHERE  player_id = ?
               AND  item_code = ?
               AND  quantity  >= ?',
            [$quantity, $playerId, $itemCode, $quantity],
        );

        return $affected > 0;
    }

    /**
     * Returns the full inventory for a player, enriched with item definitions.
     *
     * @return list<array<string, mixed>>
     */
    public static function getInventory(int $playerId): array
    {
        self::loadDefs();

        $db = Connection::getInstance();

        try {
            $rows = $db->query(
                'SELECT item_code, quantity
                 FROM   player_inventory
                 WHERE  player_id = ? AND quantity > 0
                 ORDER  BY item_code ASC',
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $code = (int) $row['item_code'];
            $def  = self::$defs[$code] ?? null;

            $entry = [
                'item_code' => $code,
                'quantity'  => (int) $row['quantity'],
            ];

            if ($def !== null) {
                // Merge all definition fields (name, category, subcategory, description …)
                $entry = array_merge($def, $entry);
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Uses one item of $itemCode from the player's inventory.
     *
     * Dispatches to the appropriate private handler based on item category.
     * Deducts 1× item only after a successful application.
     *
     * $context may carry:
     *   queue_type — 'building' | 'research' | 'training' | 'healing'
     *   queue_id   — ID of the specific queue row (building_queue / research_queue etc.)
     *
     * Returns ['ok' => true, 'effect' => '...'] on success.
     *
     * @param array<string, mixed> $context
     * @return array{ok: bool, effect: string}
     */
    public static function useItem(int $playerId, int $cityId, int $itemCode, array $context = []): array
    {
        $def = self::getItemDef($itemCode);

        if ($def === null) {
            return ['ok' => false, 'effect' => 'Unknown item.'];
        }

        // Verify possession before attempting to apply
        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT quantity FROM player_inventory WHERE player_id = ? AND item_code = ?',
            [$playerId, $itemCode],
        )->fetch();

        if ($row === false || (int) $row['quantity'] < 1) {
            return ['ok' => false, 'effect' => 'Item not in inventory.'];
        }

        $category = (string) ($def['category'] ?? '');

        $result = match ($category) {
            'speedup'       => self::applySpeedup($playerId, $cityId, $def, $context),
            'resource_pack' => self::applyResourcePack($playerId, $cityId, $def),
            'boost'         => self::applyBoost($playerId, $def),
            'ap_refill'     => self::applyApRefill($playerId, $def),
            'chest'         => self::applyChest($playerId, $def),
            'vip_point'     => self::applyVipPoint($playerId, $def),
            default         => ['ok' => false, 'effect' => 'Unsupported item category: ' . $category],
        };

        // Only deduct the item if the application succeeded
        if ($result['ok']) {
            self::removeItems($playerId, $itemCode, 1);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Private category handlers
    // -------------------------------------------------------------------------

    /**
     * Applies a speed-up item to the matching active queue.
     *
     * Context keys:
     *   queue_type — 'building' | 'research' | 'training' | 'healing'
     *   queue_id   — numeric ID of the target row (not required for 'healing')
     *
     * @param array<string, mixed>        $def
     * @param array<string, mixed>        $context
     * @return array{ok: bool, effect: string}
     */
    private static function applySpeedup(int $playerId, int $cityId, array $def, array $context): array
    {
        $subcategory     = (string) ($def['subcategory']     ?? 'generic');
        $durationSeconds = (int)   ($def['duration_seconds'] ?? 0);

        if ($durationSeconds <= 0) {
            return ['ok' => false, 'effect' => 'Speed-up has no duration.'];
        }

        $queueType = (string) ($context['queue_type'] ?? '');
        $queueId   = (int)   ($context['queue_id']   ?? 0);

        // Healing speed-ups target the hospital, not a queue row
        if ($subcategory === 'healing') {
            return self::applyHealingSpeedup($cityId, $durationSeconds);
        }

        // For all other subcategories we need a concrete queue_type and queue_id
        if ($queueType === '' || $queueId <= 0) {
            return ['ok' => false, 'effect' => 'queue_type and queue_id are required for this speed-up.'];
        }

        // Validate subcategory vs. requested queue_type
        $allowed = match ($subcategory) {
            'generic'  => ['building', 'research', 'training'],
            'building' => ['building'],
            'research' => ['research'],
            'training' => ['training'],
            default    => [],
        };

        if (!in_array($queueType, $allowed, true)) {
            return ['ok' => false, 'effect' => 'This speed-up cannot be applied to a ' . $queueType . ' queue.'];
        }

        $db = Connection::getInstance();

        [$table, $timeCol, $ownerCheck] = match ($queueType) {
            'building' => [
                'building_queue',
                'finishes_at',
                // Ensure the queue entry belongs to the player's city
                'AND city_id = (SELECT id FROM cities WHERE player_id = :pid LIMIT 1) AND is_processed = 0',
            ],
            'research' => [
                'research_queue',
                'research_ends_at',
                'AND city_id = (SELECT id FROM cities WHERE player_id = :pid LIMIT 1) AND is_processed = 0',
            ],
            'training' => [
                'troop_queue',
                'finishes_at',
                'AND city_id = (SELECT id FROM cities WHERE player_id = :pid LIMIT 1) AND is_processed = 0',
            ],
            default    => ['', '', ''],
        };

        if ($table === '') {
            return ['ok' => false, 'effect' => 'Unknown queue type.'];
        }

        // Verify the queue entry exists and belongs to the correct city/player
        $entry = $db->query(
            "SELECT id, $timeCol AS ends_at
             FROM   $table
             WHERE  id = :qid $ownerCheck",
            [':qid' => $queueId, ':pid' => $playerId],
        )->fetch();

        if ($entry === false) {
            return ['ok' => false, 'effect' => 'Queue entry not found or already completed.'];
        }

        $affected = $db->execute(
            "UPDATE $table
             SET    $timeCol = GREATEST(UTC_TIMESTAMP(), DATE_SUB($timeCol, INTERVAL :secs SECOND))
             WHERE  id = :qid",
            [':secs' => $durationSeconds, ':qid' => $queueId],
        );

        if ($affected === 0) {
            return ['ok' => false, 'effect' => 'Could not apply speed-up.'];
        }

        // Read back the new end time for the UI
        $updated = $db->query(
            "SELECT $timeCol AS ends_at FROM $table WHERE id = ?",
            [$queueId],
        )->fetch();

        $newEndsAt = $updated !== false ? (string) $updated['ends_at'] : '';

        return [
            'ok'     => true,
            'effect' => 'Speed-up applied. New completion time: ' . $newEndsAt,
        ];
    }

    /**
     * Applies a healing speed-up to all hospital_wounded rows for the city.
     *
     * @return array{ok: bool, effect: string}
     */
    private static function applyHealingSpeedup(int $cityId, int $durationSeconds): array
    {
        $db = Connection::getInstance();

        try {
            $affected = $db->execute(
                'UPDATE hospital_wounded
                 SET    healing_ends_at = GREATEST(UTC_TIMESTAMP(), DATE_SUB(healing_ends_at, INTERVAL ? SECOND))
                 WHERE  city_id = ?',
                [$durationSeconds, $cityId],
            );
        } catch (\PDOException) {
            return ['ok' => false, 'effect' => 'Hospital table not found.'];
        }

        if ($affected === 0) {
            return ['ok' => false, 'effect' => 'No wounded troops to speed up.'];
        }

        // Flush any rows that just became ready
        HospitalService::processHealed($cityId);

        return [
            'ok'     => true,
            'effect' => 'Healing speed-up applied to ' . $affected . ' troop group(s).',
        ];
    }

    /**
     * Credits resources from a resource_pack item to the city (capped).
     * GEMS packs are credited to the player row, not the city.
     *
     * @param array<string, mixed> $def
     * @return array{ok: bool, effect: string}
     */
    private static function applyResourcePack(int $playerId, int $cityId, array $def): array
    {
        $resource = (string) ($def['resource'] ?? '');
        $amount   = (int)   ($def['amount']   ?? 0);

        if ($amount <= 0) {
            return ['ok' => false, 'effect' => 'Resource pack has no amount.'];
        }

        $db = Connection::getInstance();

        if ($resource === 'gems') {
            // GEMS are player-level, not city-level
            $db->execute(
                'UPDATE players SET gems = gems + ? WHERE id = ?',
                [$amount, $playerId],
            );

            return [
                'ok'     => true,
                'effect' => 'Added ' . $amount . ' GEMS to your account.',
            ];
        }

        if (!in_array($resource, self::CITY_RESOURCES, true)) {
            return ['ok' => false, 'effect' => 'Unknown resource type: ' . $resource];
        }

        $capCol = self::RESOURCE_CAPS[$resource];

        // Add resource, capped at its cap column
        $db->execute(
            "UPDATE cities
             SET    {$resource} = LEAST({$resource} + ?, {$capCol})
             WHERE  id = ?",
            [$amount, $cityId],
        );

        return [
            'ok'     => true,
            'effect' => 'Added up to ' . $amount . ' ' . $resource . ' to your city.',
        ];
    }

    /**
     * Activates a timed boost by inserting into player_charms_active.
     * Additionally records production/research/training boosts in active_buffs
     * so ActiveBuffService can apply multiplicative stacking.
     *
     * @param array<string, mixed> $def
     * @return array{ok: bool, effect: string}
     */
    private static function applyBoost(int $playerId, array $def): array
    {
        $boostType       = (string) ($def['boost_type']       ?? '');
        $bonusPct        = (float)  ($def['bonus_pct']        ?? 0.0);
        $durationSeconds = (int)    ($def['duration_seconds'] ?? 0);

        if ($boostType === '' || $durationSeconds <= 0) {
            return ['ok' => false, 'effect' => 'Invalid boost definition.'];
        }

        // Validate boost_type against known stat categories
        $validTypes = [
            'resource_production',
            'gathering_speed',
            'construction_speed',
            'research_speed',
            'training_speed',
            'anti_spy',
        ];

        if (!in_array($boostType, $validTypes, true)) {
            return ['ok' => false, 'effect' => 'Unknown boost type: ' . $boostType];
        }

        $db = Connection::getInstance();

        // If an active boost of this type already exists, extend its duration
        $db->execute(
            'INSERT INTO player_charms_active
                (player_id, stat_category, bonus_pct, expires_at)
             VALUES
                (:pid, :cat, :pct,
                 DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND))
             ON DUPLICATE KEY UPDATE
                bonus_pct  = VALUES(bonus_pct),
                expires_at = GREATEST(
                    expires_at,
                    DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur2 SECOND)
                )',
            [
                ':pid'  => $playerId,
                ':cat'  => $boostType,
                ':pct'  => $bonusPct,
                ':dur'  => $durationSeconds,
                ':dur2' => $durationSeconds,
            ],
        );

        // Mirror production/research/training boosts into active_buffs for
        // multiplicative stacking via ActiveBuffService::getMultiplier().
        $activeBuffType = match ($boostType) {
            'resource_production' => 'production_boost',
            'research_speed'      => 'research_boost',
            'training_speed'      => 'training_boost',
            default               => null,
        };

        if ($activeBuffType !== null && $bonusPct > 0.0) {
            $multiplier = 1.0 + ($bonusPct / 100.0);
            $hours      = (int) ceil($durationSeconds / 3600.0);
            try {
                ActiveBuffService::apply($playerId, $activeBuffType, $multiplier, $hours);
            } catch (\Throwable) {
                // non-critical
            }
        }

        $hours   = number_format($durationSeconds / 3600.0, 1);
        $pctText = $bonusPct > 0 ? '+' . $bonusPct . '%' : '';

        return [
            'ok'     => true,
            'effect' => 'Boost activated: ' . $pctText . ' ' . $boostType . ' for ' . $hours . 'h.',
        ];
    }

    /**
     * Refills a player's action points.
     *
     * @param array<string, mixed> $def
     * @return array{ok: bool, effect: string}
     */
    private static function applyApRefill(int $playerId, array $def): array
    {
        $apAmount = (int) ($def['ap_amount'] ?? 0);

        if ($apAmount <= 0) {
            return ['ok' => false, 'effect' => 'AP refill has no amount.'];
        }

        Connection::getInstance()->execute(
            'UPDATE players
             SET    action_points = LEAST(action_points + ?, 200)
             WHERE  id = ?',
            [$apAmount, $playerId],
        );

        return [
            'ok'     => true,
            'effect' => 'Restored up to ' . $apAmount . ' action points.',
        ];
    }

    /**
     * Opens a chest by delegating to ChestService.
     *
     * ChestService does not exist yet — returns a stub result so inventory
     * can be wired up while ChestService is being built.
     *
     * @param array<string, mixed> $def
     * @return array{ok: bool, effect: string}
     */
    private static function applyChest(int $playerId, array $def): array
    {
        $chestType = (string) ($def['chest_type'] ?? '');

        if ($chestType === '') {
            return ['ok' => false, 'effect' => 'Invalid chest definition.'];
        }

        // Delegate to ChestService when available
        if (class_exists('\Conquer\Game\Chest\ChestService')) {
            $drops = \Conquer\Game\Chest\ChestService::open($playerId, $chestType);
            return [
                'ok'     => true,
                'effect' => 'Chest opened.',
                'drops'  => $drops,
            ];
        }

        // ChestService not yet implemented — stub response
        return [
            'ok'     => true,
            'effect' => 'Chest queued for opening (ChestService pending implementation).',
            'chest_type' => $chestType,
        ];
    }

    /**
     * Grants VIP points from a vip_point item.
     *
     * @param array<string, mixed> $def
     * @return array{ok: bool, effect: string}
     */
    private static function applyVipPoint(int $playerId, array $def): array
    {
        $points = (int) ($def['vip_points'] ?? 0);

        if ($points <= 0) {
            return ['ok' => false, 'effect' => 'VIP points item has no points value.'];
        }

        VipService::addPoints($playerId, $points);

        return [
            'ok'     => true,
            'effect' => 'Added ' . $points . ' VIP points.',
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Loads and caches item definitions from data/items.json.
     * Uses ROOT_DIR constant set by Bootstrap; falls back to relative path for
     * contexts where ROOT_DIR may not be defined.
     */
    private static function loadDefs(): void
    {
        if (self::$defs !== null) {
            return;
        }

        $path = defined('ROOT_DIR')
            ? ROOT_DIR . '/' . self::ITEMS_FILE
            : __DIR__ . '/../../../' . self::ITEMS_FILE;

        if (!is_file($path)) {
            self::$defs = [];
            return;
        }

        $json = file_get_contents($path);

        if ($json === false) {
            self::$defs = [];
            return;
        }

        /** @var array<string, mixed> $parsed */
        $parsed = json_decode($json, true) ?? [];

        /** @var list<array<string, mixed>> $items */
        $items = $parsed['items'] ?? [];

        self::$defs = [];

        foreach ($items as $item) {
            $code = (int) ($item['code'] ?? 0);
            if ($code > 0) {
                self::$defs[$code] = $item;
            }
        }
    }
}
