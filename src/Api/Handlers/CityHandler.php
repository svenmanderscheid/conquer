<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
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

        try {
            $entry = BuildingUpgrader::start(
                $cityId,
                $code,
                $city,
                $buildings,
                (int) $session['vip_level'],
            );
        } catch (\RuntimeException $e) {
            Response::error(422, 'UPGRADE_FAILED', $e->getMessage());
        }

        Response::ok(['queue_entry' => $entry]);
    }
}
