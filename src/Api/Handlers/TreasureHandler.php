<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Treasure\ChestService;
use Conquer\Game\Treasure\TreasureData;
use Conquer\Game\Treasure\TreasureService;
use Conquer\Game\World\WorldContext;

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
        $treasureCode = self::integer($body, 'treasure_code');
        $slot         = self::integer($body, 'slot');

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
            Response::error(400, 'TREASURE_LOCKED', 'Dieser Schatz ist noch nicht freigeschaltet oder der Platz ist gesperrt.');
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
        $treasureCode = self::integer($body, 'treasure_code');

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

        $playerId=(int)$session['player_id'];
        $free=($body['free']??false)===true;
        $buy=($body['buy_with_gems']??false)===true;
          if($free&&$buy)Response::error(400,'INVALID_FIELD','Kostenlos öffnen und Kaufen sind getrennte Aktionen.');
          try{
            $open=static fn():array=>$free?ChestService::openFreeChest($playerId,$chestType):
                ($buy?ChestService::purchaseAndOpenChest($playerId,$chestType):ChestService::openChest($playerId,$chestType));
            $rewards=array_key_exists('operation_key',$body)
                ? \Conquer\Game\Operation::run($playerId,array_replace($body,['action'=>'treasure.open','expected_world_id'=>WorldContext::id()]),$open)
                : $open();
        }catch(\DomainException $e){Response::error($e->getCode()===503?503:400,'CHEST_UNAVAILABLE',$e->getMessage());}

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
        WorldContext::assertActionAvailable();
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

        $body = is_array($decoded) ? $decoded : [];
        WorldContext::current($body['expected_world_id'] ?? null);
        return $body;
    }

    /**
     * Reads the Treasure House level from the city_buildings table for the player.
     */
    private static function getTreasureHouseLevel(int $playerId): int
    {
        return TreasureService::houseLevel($playerId);
    }

    /**
     * Returns the minimum Treasure House level needed to unlock the given slot.
     */
    private static function integer(array $body, string $key): int
    {
        $value=$body[$key]??null;
        if ((!is_int($value)&&!is_string($value)) || filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false) {
            Response::error(400,'INVALID_FIELD','Ungültiger Wert: '.$key);
        }
        return (int)$value;
    }

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
