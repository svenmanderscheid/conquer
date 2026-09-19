<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Game\World\WorldContext;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\BuildingData;
use Conquer\Game\City\BuildingUpgrader;
use Conquer\Game\City\CityState;

/**
 * Handles /api/city/* endpoints.
 */
final class CityHandler
{
    private function __construct() {}

    /**
     * GET /api/city/state
     *
     * Returns a full city snapshot: resources, buildings, and build queue.
     * The frontend polls this endpoint to keep the city view up to date.
     */
    public static function state(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);

        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city yet.');
        }

        Response::ok($state);
    }

    /**
     * POST /api/city/upgrade-building
     *
     * Body: {"building_code": "farm"}
     *
     * Validates resources and requirements, deducts resources, enqueues the upgrade.
     */
    public static function upgradeBuilding(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        try{WorldContext::assertActionAvailable();}catch(\DomainException $e){Response::error(409,'WORLD_UNAVAILABLE',$e->getMessage());}


        // Verify CSRF.
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        // Parse JSON body.
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $code = trim((string) ($body['building_code'] ?? ''));

        if ($code === '') {
            Response::error(400, 'MISSING_FIELD', 'building_code is required.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city.');
        }

        $city      = $state['city'];
        $buildings = $state['buildings'];
        $cityId    = (int) $city['id'];

        if (array_key_exists('expected_level',$body) && (!is_int($body['expected_level']) || $body['expected_level'] !== (int)($buildings[$code]['level'] ?? 0))) {
            Response::error(409, 'STALE_LEVEL', 'Die Gebäudestufe hat sich verändert. Bitte lade den aktuellen Stand.');
        }

        $vipBonuses = $state['vip']['bonuses'] ?? [];

        try {
            $entry = BuildingUpgrader::start(
                $cityId,
                $code,
                $city,
                $buildings,
                (int) $session['vip_level'],
                $vipBonuses,
            );
        } catch (\RuntimeException|\DomainException $e) {
            Response::error(422, 'UPGRADE_FAILED', $e->getMessage());
        }

        Response::ok(['queue_entry' => $entry]);
    }

    /**
     * POST /api/city/wall-repair
     *
     * Repairs wall HP at a cost of 100 stone + 50 wood per 1 000 HP repaired.
     * The requested HP amount is rounded to the nearest 1 000.
     * Repair is capped at wall_hp_max.
     *
     * Body: { "hp_amount": int }  — HP to repair (will be rounded to nearest 1 000)
     */
    public static function repairWall(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true)??[];
        try{$result=\Conquer\Game\Defense\DefenseService::action((int)$session['player_id'],['action'=>'wall.repair','hp_amount'=>$body['hp_amount']??null]);}
        catch(\RuntimeException|\DomainException $e){Response::error(422,'WALL_REPAIR_FAILED',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * POST /api/city/cancel-build/:queue_id
     *
     * Cancels a building queue entry and refunds the full resource cost.
     * Only allowed if the entry is not yet processed (i.e. still in progress).
     */
    public static function cancelBuild(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $queueId  = (int) ($params['queue_id'] ?? 0);
        $playerId = (int) $session['player_id'];

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        try { $result = BuildingUpgrader::cancel($playerId, $queueId); }
        catch (\RuntimeException|\DomainException $e) { Response::error(422, 'CANCEL_FAILED', $e->getMessage()); }
        Response::ok($result);
    }

    /**
     * POST /api/city/speedup-build/:queue_id
     *
     * Uses a speedup item from the player's inventory to reduce the building timer.
     * Accepts generic (subcategory=generic) and building-specific (subcategory=building) items.
     * If finishes_at moves into the past, the building is completed immediately.
     *
     * Body: {"item_code": 10103011}
     */
    public static function speedupBuild(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        try{WorldContext::assertActionAvailable();}catch(\DomainException $e){Response::error(409,'WORLD_UNAVAILABLE',$e->getMessage());}


        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $queueId  = (int) ($params['queue_id'] ?? 0);
        $playerId = (int) $session['player_id'];

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        $body     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $itemCode = (int) ($body['item_code'] ?? 0);

        if ($itemCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'item_code is required.');
        }

        // Load item definition from items.json.
        $itemsJson = file_get_contents(ROOT_DIR . '/data/items.json') ?: '{}';
        $itemsData = json_decode($itemsJson, true) ?? [];
        $itemDef   = null;
        foreach ($itemsData['items'] ?? [] as $item) {
            if ((int) $item['code'] === $itemCode) {
                $itemDef = $item;
                break;
            }
        }

        if ($itemDef === null) {
            Response::error(400, 'UNKNOWN_ITEM', 'Item code ' . $itemCode . ' not found.');
        }

        // Must be a speedup item (generic or building subcategory).
        $cat    = $itemDef['category']    ?? '';
        $subcat = $itemDef['subcategory'] ?? '';
        if ($cat !== 'speedup' || !in_array($subcat, ['generic', 'building'], true)) {
            Response::error(400, 'WRONG_ITEM_TYPE', 'This item cannot be used for building speedups.');
        }

        $durationSeconds = (int) ($itemDef['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            Response::error(400, 'ITEM_NO_DURATION', 'Item has no valid duration.');
        }

        $db = Connection::getInstance();

        // Load the queue entry — verify ownership.
        $entry = $db->query(
            'SELECT bq.id, bq.city_id, bq.building_code, bq.level_to,
                    bq.finishes_at, bq.is_processed,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), bq.finishes_at) AS secs_remaining
             FROM   building_queue bq
             JOIN   cities c ON c.id = bq.city_id
             WHERE  bq.id = ? AND c.player_id = ? AND c.world_id = ? AND bq.is_processed = 0',
            [$queueId, $playerId,WorldContext::id()],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Queue entry not found or already completed.');
        }

        // Verify player has the item in inventory.
        $invRow = $db->query(
            'SELECT id, quantity FROM player_inventory WHERE player_id = ? AND item_code = ? LIMIT 1',
            [$playerId, $itemCode],
        )->fetch();

        if ($invRow === false || (int) $invRow['quantity'] < 1) {
            Response::error(400, 'NOT_ENOUGH_ITEMS', 'You do not have this speedup item.');
        }

        $cityId    = (int) $entry['city_id'];
        $buildCode = $entry['building_code'];
        $levelTo   = (int) $entry['level_to'];
        $secsLeft  = max(0, (int) $entry['secs_remaining']);
        $newSecs   = max(0, $secsLeft - $durationSeconds);
        $isInstant = ($newSecs === 0);

        $db->transaction(function () use (
            $db, $queueId, $cityId, $buildCode, $levelTo,
            $playerId, $itemCode, $invRow, $durationSeconds, $newSecs, $isInstant
        ): void {
            // Deduct item from inventory.
            if ((int) $invRow['quantity'] === 1) {
                $db->execute('DELETE FROM player_inventory WHERE id = ?', [(int) $invRow['id']]);
            } else {
                $db->execute(
                    'UPDATE player_inventory SET quantity = quantity - 1 WHERE id = ?',
                    [(int) $invRow['id']],
                );
            }

            if ($isInstant) {
                // Apply the upgrade immediately.
                $db->execute(
                    'INSERT INTO city_buildings (city_id, building_code, level)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE level = ?',
                    [$cityId, $buildCode, $levelTo, $levelTo],
                );
                if ($buildCode === 'castle') {
                    $db->execute(
                        'UPDATE cities SET castle_level = ? WHERE id = ?',
                        [$levelTo, $cityId],
                    );
                }
                $db->execute(
                    'UPDATE building_queue SET is_processed = 1, finishes_at = UTC_TIMESTAMP() WHERE id = ?',
                    [$queueId],
                );
            } else {
                // Reduce the timer.
                $db->execute(
                    'UPDATE building_queue
                     SET finishes_at = DATE_SUB(finishes_at, INTERVAL ? SECOND)
                     WHERE id = ?',
                    [$durationSeconds, $queueId],
                );
            }
        });

        Response::ok([
            'speedup_applied'    => true,
            'item_code'          => $itemCode,
            'duration_seconds'   => $durationSeconds,
            'instantly_finished' => $isInstant,
            'secs_remaining'     => $isInstant ? 0 : $newSecs,
        ]);
    }

    /**
     * POST /api/city/instant-build/:queue_id
     *
     * Rejects the retired crystal completion action.
     */
    public static function instantBuild(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        try{WorldContext::assertActionAvailable();}catch(\DomainException $e){Response::error(409,'WORLD_UNAVAILABLE',$e->getMessage());}


        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        Response::error(422, 'CRYSTAL_PURCHASE_DISABLED', \Conquer\Game\CrystalEconomy::RESTRICTION);
    }
}
