<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\City\CityState;
use Conquer\Game\City\TroopData;
use Conquer\Game\City\TroopTrainer;
use Conquer\Game\City\ResourceTick;

/**
 * Handles /api/troops/* endpoints.
 *
 * GET  /api/troops/list  — current troops + training queue + troop definitions
 * POST /api/troops/train — enqueue a training batch
 */
final class TroopHandler
{
    private function __construct() {}

    /**
     * GET /api/troops/list
     *
     * Returns:
     *   troops      — {troop_code: count} for all troops the city has
     *   queue       — active training queue entries
     *   definitions — all troop definitions (for the UI)
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        Response::ok([
            'troops'      => $state['troops'],
            'troop_queue' => $state['troop_queue'],
            'definitions' => array_values(TroopData::all()),
        ]);
    }

    /**
     * POST /api/troops/train
     *
     * Body (JSON): { troop_code: int, count: int, barrack_slot?: int }
     * Header: X-CSRF-Token
     */
    public static function train(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF check
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || $csrf !== ($session['csrf_token'] ?? '')) {
            Response::error(403, 'CSRF_INVALID', 'Invalid CSRF token.');
        }

        $body = (string) file_get_contents('php://input');
        $data = json_decode($body, true);

        $troopCode   = (int) ($data['troop_code']   ?? 0);
        $count       = (int) ($data['count']        ?? 0);
        $barrackSlot = (int) ($data['barrack_slot'] ?? 1);

        if ($troopCode === 0 || $count <= 0) {
            Response::error(400, 'INVALID_INPUT', 'troop_code and count are required.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        // Apply current resource tick so the check uses up-to-date resources.
        $city      = ResourceTick::apply($state['city'], $state['buildings']);
        $buildings = $state['buildings'];

        try {
            TroopTrainer::train($city, $buildings, $troopCode, $count, $barrackSlot);
        } catch (\RuntimeException $e) {
            Response::error(400, 'TRAIN_FAILED', $e->getMessage());
        }

        $troop = TroopData::get($troopCode);
        Response::ok([
            'message' => 'Training started: ' . $count . '× ' . ($troop['name'] ?? 'troops'),
        ]);
    }
}
