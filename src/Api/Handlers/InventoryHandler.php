<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Inventory\InventoryService;

/**
 * Handles /api/inventory/* endpoints.
 *
 * GET  /api/inventory      — list all items in the player's inventory
 * POST /api/inventory/use  — use one item from the inventory
 */
final class InventoryHandler
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/inventory
    // -------------------------------------------------------------------------

    /**
     * Returns the authenticated player's full item inventory.
     *
     * Also runs a hospital lazy-tick so healed troops are credited before any
     * UI reads that might display troop counts alongside the inventory.
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        // Resolve the player's city to run the hospital tick.
        // city_id is available directly in the session array once set by the router.
        $cityId = (int) ($session['city_id'] ?? 0);

        if ($cityId > 0) {
            HospitalService::processHealed($cityId);
        }

        $items = InventoryService::getInventory($playerId);

        Response::ok(['items' => $items]);
    }

    // -------------------------------------------------------------------------
    // POST /api/inventory/use
    // -------------------------------------------------------------------------

    /**
     * Uses one item from the authenticated player's inventory.
     *
     * Expected JSON body:
     * {
     *   "item_code":  <int>,           // required
     *   "quantity":   <int>,           // optional, default 1 (reserved for future batch use)
     *   "queue_type": <string>|null,   // required for speedup items: building|research|training|healing
     *   "queue_id":   <int>|null       // required for speedup items (except healing)
     * }
     *
     * Header: X-CSRF-Token
     */
    public static function use(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF validation
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals((string) $session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        // Parse JSON body
        $body     = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $itemCode = (int) ($body['item_code'] ?? 0);

        if ($itemCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'item_code is required.');
        }

        // quantity is reserved for future multi-use; enforce 1 for now
        $quantity = max(1, (int) ($body['quantity'] ?? 1));
        if ($quantity !== 1) {
            Response::error(400, 'INVALID_INPUT', 'Only quantity=1 is currently supported.');
        }

        $playerId = (int) $session['player_id'];
        $cityId   = (int) ($session['city_id'] ?? 0);

        if ($cityId <= 0) {
            Response::error(400, 'NO_CITY', 'No city associated with this session.');
        }

        // Optional context for speed-up routing
        $context = [];

        if (isset($body['queue_type'])) {
            $context['queue_type'] = trim((string) $body['queue_type']);
        }

        if (isset($body['queue_id'])) {
            $context['queue_id'] = (int) $body['queue_id'];
        }

        $result = InventoryService::useItem($playerId, $cityId, $itemCode, $context);

        if (!$result['ok']) {
            Response::error(400, 'USE_FAILED', $result['effect']);
        }

        Response::ok([
            'effect'    => $result['effect'],
            'item_code' => $itemCode,
            'drops'     => $result['drops'] ?? null,
        ]);
    }
}
