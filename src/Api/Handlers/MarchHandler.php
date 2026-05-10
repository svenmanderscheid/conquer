<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\March\MarchDispatcher;
use Conquer\Game\March\MarchTick;

/**
 * Handles /api/march/* endpoints.
 *
 * POST /api/march/dispatch  — dispatch a monster attack
 * GET  /api/march/list      — all active marches for the player
 */
final class MarchHandler
{
    private function __construct() {}

    /**
     * POST /api/march/dispatch
     *
     * Body: { target_x: int, target_y: int, troops: {code: count, ...} }
     */
    public static function dispatch(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body = json_decode((string) file_get_contents('php://input'), true) ?? [];

        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);
        $troops  = (array) ($body['troops']  ?? []);

        if ($targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchMonster(
                playerId:      (int) $session['player_id'],
                cityId:        $cityId,
                originX:       (int) $city['coord_x'],
                originY:       (int) $city['coord_y'],
                targetX:       $targetX,
                targetY:       $targetY,
                selectedTroops: $troops,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-charm
     *
     * Body: { charm_id: int, target_x: int, target_y: int, troops: {code: count} }
     */
    public static function dispatchCharm(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $charmId = (int) ($body['charm_id'] ?? 0);
        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);
        $troops  = (array) ($body['troops']  ?? []);

        if ($charmId <= 0 || $targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'charm_id, target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = \Conquer\Game\March\MarchDispatcher::dispatchCharm(
                playerId:       (int) $session['player_id'],
                cityId:         $cityId,
                originX:        (int) $city['coord_x'],
                originY:        (int) $city['coord_y'],
                targetX:        $targetX,
                targetY:        $targetY,
                charmId:        $charmId,
                selectedTroops: $troops,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-player
     *
     * Body: { target_x: int, target_y: int, troops: {code: count} }
     */
    public static function dispatchPlayer(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);
        $troops  = (array) ($body['troops']  ?? []);

        if ($targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchPlayerAttack(
                playerId:       (int) $session['player_id'],
                cityId:         $cityId,
                originX:        (int) $city['coord_x'],
                originY:        (int) $city['coord_y'],
                targetX:        $targetX,
                targetY:        $targetY,
                selectedTroops: $troops,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-scout
     *
     * Body: { target_x: int, target_y: int }
     */
    public static function dispatchScout(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);

        if ($targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchScout(
                playerId: (int) $session['player_id'],
                cityId:   $cityId,
                originX:  (int) $city['coord_x'],
                originY:  (int) $city['coord_y'],
                targetX:  $targetX,
                targetY:  $targetY,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-gather
     *
     * Body: { target_x: int, target_y: int, troop_count: int }
     */
    public static function dispatchGather(array $session): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body       = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetX    = (int) ($body['target_x']    ?? -1);
        $targetY    = (int) ($body['target_y']    ?? -1);
        $troopCount = (int) ($body['troop_count'] ?? 1000);

        if ($targetX < 0 || $targetX > 255 || $targetY < 0 || $targetY > 255) {
            Response::error(400, 'INVALID_INPUT', 'target_x und target_y müssen zwischen 0 und 255 liegen.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $cityId = (int) $state['city']['id'];

        try {
            $marchId = MarchDispatcher::dispatchGather(
                playerId:   (int) $session['player_id'],
                cityId:     $cityId,
                targetX:    $targetX,
                targetY:    $targetY,
                troopCount: $troopCount,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/recall
     *
     * Body: { march_id: int }
     * Recalls a marching march — state must be 'marching', not yet arrived.
     */
    public static function recall(array $session): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $marchId = (int) ($body['march_id'] ?? 0);

        if ($marchId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'march_id ist erforderlich.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        // Load march and verify ownership
        $march = $db->query(
            "SELECT id, player_id, state, march_type, target_id
             FROM marches
             WHERE id = ?",
            [$marchId],
        )->fetch();

        if ($march === false) {
            Response::error(404, 'NOT_FOUND', 'March nicht gefunden.');
        }

        if ((int) $march['player_id'] !== $playerId) {
            Response::error(403, 'FORBIDDEN', 'Das ist nicht dein March.');
        }

        if ($march['state'] !== 'marching') {
            Response::error(409, 'WRONG_STATE', 'Nur marschierende Truppen können zurückgerufen werden.');
        }

        // Update march to returning
        $db->execute(
            "UPDATE marches
             SET state = 'returning', return_time = UTC_TIMESTAMP(), haul_json = '{\"survivors\":{},\"loot\":{}}'
             WHERE id = ? AND state = 'marching' AND player_id = ?",
            [$marchId, $playerId],
        );

        // If this was a gather march, unlock the field object
        if ((int) $march['march_type'] === MarchDispatcher::MARCH_GATHER && $march['target_id'] !== null) {
            try {
                \Conquer\Game\Map\FieldObjectService::unlockObject((int) $march['target_id']);
            } catch (\Throwable) {
                // Non-fatal — object may already be unlocked or expired
            }
        }

        Response::ok(['recalled' => true]);
    }

    /**
     * GET /api/map/marches
     *
     * Returns all publicly visible active marches (all players, types 5+7).
     * Used for the map overlay — includes origin_x/origin_y per march.
     */
    public static function listAll(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $marches = MarchDispatcher::listAllActive();
        Response::ok(['marches' => $marches]);
    }

    /**
     * GET /api/march/list
     *
     * Returns all active marches (marching + returning) for the player.
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        // Lazy tick — resolve arrived/returning marches without needing a cron job.
        MarchTick::runForPlayer($playerId);

        $marches = MarchDispatcher::listActive($playerId);

        // Decode troops_json for each march.
        foreach ($marches as &$m) {
            $m['troops'] = json_decode($m['troops_json'] ?? '{}', true);
            unset($m['troops_json']);
        }
        unset($m);

        Response::ok(['marches' => $marches]);
    }
}
