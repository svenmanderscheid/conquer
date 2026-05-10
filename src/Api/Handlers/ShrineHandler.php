<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\City\CityState;
use Conquer\Game\Shrine\ShrineService;

/**
 * Handles /api/shrines/* endpoints.
 *
 * GET  /api/shrines            — list all shrines with capture state
 * GET  /api/shrines/:id        — detail view of a single shrine
 * POST /api/shrines/:id/garrison — send troops to garrison a secured shrine
 * POST /api/shrines/:id/recall   — recall garrison troops back to city
 */
final class ShrineHandler
{
    private function __construct() {}

    // ── GET /api/shrines ──────────────────────────────────────────────────────

    /**
     * Returns all shrines in world 1 with their current ownership state.
     *
     * @param array<string, mixed> $session
     */
    public static function list(array $session): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $shrines = ShrineService::getAllShrines(1);

        Response::ok(['shrines' => $shrines]);
    }

    // ── GET /api/shrines/:id ──────────────────────────────────────────────────

    /**
     * Returns full detail for one shrine including bonuses and garrison.
     *
     * @param array<string, mixed> $session
     */
    public static function detail(array $session, int $shrineId): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        if ($shrineId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Ungültige Shrine-ID.');
        }

        $shrine = ShrineService::getShrine($shrineId);

        if ($shrine === null) {
            Response::error(404, 'NOT_FOUND', 'Shrine nicht gefunden.');
        }

        Response::ok(['shrine' => $shrine]);
    }

    // ── POST /api/shrines/:id/garrison ────────────────────────────────────────

    /**
     * Sends troops from the player's city to garrison the specified shrine.
     *
     * Expected JSON body:
     *   { "troops": { "<troop_code>": <count>, ... } }
     *
     * @param array<string, mixed> $session
     */
    public static function garrison(array $session, int $shrineId): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF validation
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionObj = Session::current();
        if (
            $sessionObj === null ||
            $csrf === '' ||
            !hash_equals((string) $sessionObj['csrf_token'], $csrf)
        ) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        if ($shrineId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Ungültige Shrine-ID.');
        }

        $body   = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $troops = (array) ($body['troops'] ?? []);

        if (empty($troops)) {
            Response::error(400, 'INVALID_INPUT', 'Keine Truppen angegeben.');
        }

        $playerId = (int) $session['player_id'];

        $state = CityState::loadForPlayer($playerId);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $cityId = (int) $state['city']['id'];

        try {
            ShrineService::sendGarrison($playerId, $cityId, $shrineId, $troops);
        } catch (\RuntimeException $e) {
            Response::error(400, 'GARRISON_FAILED', $e->getMessage());
        }

        Response::ok(['garrisoned' => true]);
    }

    // ── POST /api/shrines/:id/recall ──────────────────────────────────────────

    /**
     * Recalls the player's garrison from the specified shrine.
     * Troops are returned to the player's primary city.
     *
     * @param array<string, mixed> $session
     */
    public static function recall(array $session, int $shrineId): void
    {
        if (empty($session)) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        // CSRF validation
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionObj = Session::current();
        if (
            $sessionObj === null ||
            $csrf === '' ||
            !hash_equals((string) $sessionObj['csrf_token'], $csrf)
        ) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        if ($shrineId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Ungültige Shrine-ID.');
        }

        $playerId = (int) $session['player_id'];

        try {
            ShrineService::recallGarrison($playerId, $shrineId);
        } catch (\RuntimeException $e) {
            Response::error(400, 'RECALL_FAILED', $e->getMessage());
        }

        Response::ok(['recalled' => true]);
    }
}
