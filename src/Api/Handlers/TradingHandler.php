<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Trading\CaravanService;

/**
 * Handles /api/trading/* endpoints.
 */
final class TradingHandler
{
    private function __construct() {}

    /**
     * GET /api/trading/caravan
     *
     * Returns the player's current caravan state (lazy-refreshes if stale).
     * Response includes slots and the next refresh timestamp.
     */
    public static function caravan(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        $state = CityState::loadForPlayer($playerId);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city yet.');
        }

        $tradingPostLevel = (int) ($state['buildings']['trading_post']['level'] ?? 1);

        $caravan = CaravanService::load($playerId, $tradingPostLevel);

        Response::ok($caravan);
    }

    /**
     * POST /api/trading/caravan/buy
     *
     * Body: {"slot_idx": 0}
     *
     * Purchases one caravan slot, deducting resources and crediting gives_* items.
     */
    public static function buy(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF validation.
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $playerId = (int) $session['player_id'];

        // Parse body.
        $body    = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $slotIdx = isset($body['slot_idx']) ? (int) $body['slot_idx'] : -1;

        if ($slotIdx < 0) {
            Response::error(400, 'MISSING_FIELD', 'slot_idx is required and must be >= 0.');
        }

        $state = CityState::loadForPlayer($playerId);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Player has no city yet.');
        }

        // Build a mutable resource array matching what CaravanService expects.
        $cityResources = [
            'food'   => (int) $state['city']['food'],
            'lumber' => (int) $state['city']['lumber'],
            'stone'  => (int) $state['city']['stone'],
            'gold'   => (int) $state['city']['gold'],
        ];

        try {
            $result = CaravanService::buy($playerId, $slotIdx, $cityResources);
        } catch (\RuntimeException $e) {
            Response::error(422, 'BUY_FAILED', $e->getMessage());
        }

        Response::ok($result);
    }
}
