<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Treasure\ChestService;
use Conquer\Game\Treasure\TreasureData;
use Conquer\Game\Treasure\TreasureService;

/**
 * Handles /api/treasure/* endpoints.
 *
 * GET  /api/treasure/list          — Player's treasure collection
 * POST /api/treasure/equip         — Equip a treasure into a slot
 * POST /api/treasure/unequip       — Remove a treasure from its slot
 * GET  /api/treasure/chest-status  — Chest counts + free-open remaining
 * POST /api/treasure/open-chest    — Open one chest
 */
final class TreasureHandler
{
    // Static-only handler — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/treasure/list
    // -------------------------------------------------------------------------

    /**
     * Returns the authenticated player's full treasure collection.
     *
     * Response data:
     * {
     *   "treasures": [...],
     *   "equipped_stats": {"infantry_attack": 5.5, ...},
     *   "unlock_slots": 2
     * }
     */
    public static function list(array $params): void
    {
        $session = self::requireAuth();

        $playerId          = (int) $session['player_id'];
        $treasureHouseLevel = self::getTreasureHouseLevel($playerId);

        $treasures     = TreasureService::getPlayerTreasures($playerId);
        $equippedStats = TreasureService::getEquippedStats($playerId);
        $unlockSlots   = TreasureData::getUnlockSlots($treasureHouseLevel);

        Response::ok([
            'treasures'           => $treasures,
            'equipped_stats'      => $equippedStats,
            'unlock_slots'        => $unlockSlots,
            'treasure_house_level' => $treasureHouseLevel,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/treasure/equip
    // -------------------------------------------------------------------------

    /**
     * Equips a treasure into a slot.
     *
     * Expected JSON body: {"treasure_code": 60100001, "slot": 1}
     */
    public static function equip(array $params): void
    {
        $session = self::requireAuth();
        self::requireCsrf($session);

        $body         = self::jsonBody();
        $treasureCode = (int) ($body['treasure_code'] ?? 0);
        $slot         = (int) ($body['slot']          ?? 0);

        if ($treasureCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'treasure_code is required.');
        }
        if ($slot < 1 || $slot > 6) {
            Response::error(400, 'INVALID_FIELD', 'slot must be between 1 and 6.');
        }

        $playerId          = (int) $session['player_id'];
        $treasureHouseLevel = self::getTreasureHouseLevel($playerId);
        $maxSlots          = TreasureData::getUnlockSlots($treasureHouseLevel);

        if ($slot > $maxSlots) {
            Response::error(403, 'SLOT_LOCKED',
                'Slot ' . $slot . ' requires Treasure House level '
                . self::slotRequiredLevel($slot) . '.');
        }

        // Verify the treasure exists in the game data.
        if (TreasureData::get($treasureCode) === null) {
            Response::error(400, 'UNKNOWN_TREASURE', 'Treasure code ' . $treasureCode . ' does not exist.');
        }

        $ok = TreasureService::equipTreasure($playerId, $treasureCode, $slot, $treasureHouseLevel);

        if (!$ok) {
            // Determine the specific failure reason for a helpful message.
            $db  = Connection::getInstance();
            $row = $db->query(
                'SELECT fragments, equipped_slot FROM player_treasures
                 WHERE  player_id = ? AND treasure_code = ?',
                [$playerId, $treasureCode],
            )->fetch();

            if ($row === false) {
                Response::error(404, 'TREASURE_NOT_OWNED', 'You do not own this treasure.');
            }

            $level = (int) floor((int) $row['fragments'] / 10);
            if ($level < 1) {
                Response::error(400, 'TREASURE_LOCKED',
                    'Collect at least 10 fragments to unlock this treasure.');
            }

            // Check slot occupation.
            $occupant = $db->query(
                'SELECT treasure_code FROM player_treasures
                 WHERE  player_id = ? AND equipped_slot = ? AND treasure_code != ?',
                [$playerId, $slot, $treasureCode],
            )->fetchColumn();

            if ($occupant !== false) {
                Response::error(409, 'SLOT_OCCUPIED',
                    'Slot ' . $slot . ' is already occupied. Unequip the other treasure first.');
            }

            Response::error(500, 'EQUIP_FAILED', 'Failed to equip treasure.');
        }

        $equippedStats = TreasureService::getEquippedStats($playerId);

        Response::ok([
            'equipped'      => true,
            'slot'          => $slot,
            'treasure_code' => $treasureCode,
            'equipped_stats' => $equippedStats,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/treasure/unequip
    // -------------------------------------------------------------------------

    /**
     * Removes a treasure from its equipped slot.
     *
     * Expected JSON body: {"treasure_code": 60100001}
     */
    public static function unequip(array $params): void
    {
        $session = self::requireAuth();
        self::requireCsrf($session);

        $body         = self::jsonBody();
        $treasureCode = (int) ($body['treasure_code'] ?? 0);

        if ($treasureCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'treasure_code is required.');
        }

        $playerId = (int) $session['player_id'];
        $ok       = TreasureService::unequipTreasure($playerId, $treasureCode);

        if (!$ok) {
            Response::error(404, 'NOT_EQUIPPED', 'Treasure is not currently equipped.');
        }

        Response::ok([
            'unequipped'    => true,
            'treasure_code' => $treasureCode,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/treasure/chest-status
    // -------------------------------------------------------------------------

    /**
     * Returns chest counts and free-open availability.
     */
    public static function chestStatus(array $params): void
    {
        $session  = self::requireAuth();
        $playerId = (int) $session['player_id'];

        $status = ChestService::getChestStatus($playerId);

        Response::ok($status);
    }

    // -------------------------------------------------------------------------
    // POST /api/treasure/open-chest
    // -------------------------------------------------------------------------

    /**
     * Opens one chest of the specified type.
     *
     * Expected JSON body: {"chest_type": "silver"|"gold"|"platinum"}
     *
     * Gold and platinum chests can alternatively be purchased with GEMS
     * if the player has no inventory chests. Pass {"chest_type": "gold", "buy_with_gems": true}
     * to trigger a gem purchase + open in one step.
     */
    public static function openChest(array $params): void
    {
        $session = self::requireAuth();
        self::requireCsrf($session);

        $body      = self::jsonBody();
        $chestType = trim((string) ($body['chest_type'] ?? ''));

        if (!in_array($chestType, ['silver', 'gold', 'platinum'], true)) {
            Response::error(400, 'INVALID_FIELD', 'chest_type must be silver, gold, or platinum.');
        }

        $playerId    = (int) $session['player_id'];
        $buyWithGems = (bool) ($body['buy_with_gems'] ?? false);

        // For paid chests purchased with GEMS on-the-fly.
        if ($buyWithGems && $chestType !== 'silver') {
            $gemCost = $chestType === 'gold'
                ? ChestService::GOLD_CHEST_COST_GEMS
                : ChestService::PLATINUM_CHEST_COST_GEMS;

            $db         = Connection::getInstance();
            $playerGems = (int) ($session['gems'] ?? 0);

            // Re-read gems from DB to ensure freshness.
            $gemsRow = $db->query('SELECT gems FROM players WHERE id = ?', [$playerId])->fetchColumn();
            $playerGems = $gemsRow !== false ? (int) $gemsRow : 0;

            if ($playerGems < $gemCost) {
                Response::error(400, 'NOT_ENOUGH_GEMS',
                    'Need ' . $gemCost . ' GEMS to buy a ' . $chestType . ' chest, have ' . $playerGems . '.');
            }

            $db->transaction(function (Connection $db) use ($playerId, $gemCost, $chestType): void {
                $db->execute('UPDATE players SET gems = gems - ? WHERE id = ?', [$gemCost, $playerId]);
                ChestService::addChest($playerId, $chestType, 1);
            });
        }

        // Open the chest (deducts from inventory or free uses).
        try {
            $rewards = ChestService::openChest($playerId, $chestType);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            match ($msg) {
                'NO_SILVER_CHEST'   => Response::error(400, 'NO_CHEST', 'No silver chests available and all free opens used for today.'),
                'NO_GOLD_CHEST'     => Response::error(400, 'NO_CHEST', 'No gold chests available. Use buy_with_gems=true to purchase one.'),
                'NO_PLATINUM_CHEST' => Response::error(400, 'NO_CHEST', 'No platinum chests available. Use buy_with_gems=true to purchase one.'),
                default             => Response::error(500, 'OPEN_FAILED', 'Failed to open chest: ' . $msg),
            };
        }

        // Return updated chest status alongside rewards.
        $status = ChestService::getChestStatus($playerId);

        Response::ok([
            'chest_type'  => $chestType,
            'rewards'     => $rewards,
            'chest_status' => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the current session or exits with 401.
     *
     * @return array<string, mixed>
     */
    private static function requireAuth(): array
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        /** @var array<string, mixed> */
        return $session;
    }

    /**
     * Validates the CSRF token from the X-CSRF-Token header.
     *
     * @param array<string, mixed> $session
     */
    private static function requireCsrf(array $session): void
    {
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals((string) $session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }
    }

    /**
     * Decodes the request body as JSON.
     *
     * @return array<string, mixed>
     */
    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';

        /** @var array<string, mixed>|null $decoded */
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Reads the Treasure House level from the city_buildings table for the player.
     */
    private static function getTreasureHouseLevel(int $playerId): int
    {
        $db = Connection::getInstance();

        $level = $db->query(
            'SELECT cb.level
             FROM   city_buildings cb
             JOIN   cities c ON c.id = cb.city_id
             WHERE  c.player_id = ? AND cb.building_code = ?
             LIMIT  1',
            [$playerId, 'treasure_house'],
        )->fetchColumn();

        return $level !== false ? (int) $level : 1;
    }

    /**
     * Returns the minimum Treasure House level needed to unlock the given slot.
     */
    private static function slotRequiredLevel(int $slot): int
    {
        return match ($slot) {
            1, 2 => 1,
            3    => 5,
            4    => 10,
            5    => 20,
            6    => 25,
            default => 1,
        };
    }
}
