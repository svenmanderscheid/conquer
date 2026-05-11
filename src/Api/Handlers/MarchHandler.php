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
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
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
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
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
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
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
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
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
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
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
     * POST /api/march/reinforce
     *
     * Body: { "target_player_id": int, "troops": {"50100101": 500} }
     * Sends troops as reinforcements to an alliance member's city.
     */
    public static function dispatchReinforce(array $session): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body           = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetPlayerId = (int) ($body['target_player_id'] ?? 0);
        $troops         = (array) ($body['troops'] ?? []);

        if ($targetPlayerId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'target_player_id ist erforderlich.');
        }

        if (empty($troops)) {
            Response::error(400, 'INVALID_INPUT', 'Mindestens eine Truppenart muss ausgewählt werden.');
        }

        $playerId = (int) $session['player_id'];

        if ($targetPlayerId === $playerId) {
            Response::error(400, 'INVALID_INPUT', 'Du kannst keine Verstärkung zu dir selbst schicken.');
        }

        $db    = Connection::getInstance();
        $state = CityState::loadForPlayer($playerId);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        // Verify both players are in the same alliance
        $myAllianceRow = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($myAllianceRow === false) {
            Response::error(403, 'NOT_MEMBER', 'Du bist kein Mitglied einer Allianz.');
        }

        $targetAllianceRow = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$targetPlayerId],
        )->fetch();

        if ($targetAllianceRow === false || (int) $targetAllianceRow['alliance_id'] !== (int) $myAllianceRow['alliance_id']) {
            Response::error(403, 'NOT_SAME_ALLIANCE', 'Verstärkung nur für Allianz-Mitglieder möglich.');
        }

        // Load target city coordinates
        $targetCity = $db->query(
            'SELECT id, coord_x, coord_y FROM cities WHERE player_id = ? LIMIT 1',
            [$targetPlayerId],
        )->fetch();

        if ($targetCity === false) {
            Response::error(404, 'TARGET_NO_CITY', 'Ziel-Spieler hat keine Stadt.');
        }

        $targetCityId = (int) $targetCity['id'];

        // Check active reinforcement count at target (max 5 active reinforcement marches per city)
        $activeReinforceCount = (int) $db->query(
            "SELECT COUNT(*) FROM marches
             WHERE march_type = 10 AND target_id = ? AND state IN ('marching','arrived')",
            [$targetCityId],
        )->fetchColumn();

        if ($activeReinforceCount >= 5) {
            Response::error(409, 'TOO_MANY_REINFORCEMENTS', 'Ziel-Stadt hat bereits zu viele Verstärkungen.');
        }

        // Validate troops exist in city
        $cityTroops = $state['troops'] ?? [];
        $validTroops = [];
        foreach ($troops as $code => $count) {
            $count = (int) $count;
            if ($count <= 0) continue;
            $available = (int) ($cityTroops[(int) $code] ?? 0);
            if ($available < $count) {
                Response::error(400, 'NOT_ENOUGH_TROOPS', "Nicht genug Truppen vom Typ {$code}. Vorhanden: {$available}.");
            }
            $validTroops[(int) $code] = $count;
        }

        if (empty($validTroops)) {
            Response::error(400, 'INVALID_INPUT', 'Keine gültigen Truppen ausgewählt.');
        }

        try {
            $marchId = MarchDispatcher::dispatchReinforce(
                playerId:      $playerId,
                cityId:        $cityId,
                originX:       (int) $city['coord_x'],
                originY:       (int) $city['coord_y'],
                targetX:       (int) $targetCity['coord_x'],
                targetY:       (int) $targetCity['coord_y'],
                targetCityId:  $targetCityId,
                targetPlayerId: $targetPlayerId,
                selectedTroops: $validTroops,
            );
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId ?? 0]);
    }

    /**
     * POST /api/march/recall-reinforce
     *
     * Body: { "reinforcement_id": int }
     * Recalls active reinforcements — troops return to sender.
     */
    public static function recallReinforcement(array $session): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body             = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $reinforcementId  = (int) ($body['reinforcement_id'] ?? 0);

        if ($reinforcementId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'reinforcement_id ist erforderlich.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        // Load reinforcement and verify ownership
        $reinforcement = $db->query(
            "SELECT r.id, r.march_id, r.sender_id, r.troops_json, r.state,
                    m.origin_city_id, m.target_id AS target_city_id
             FROM reinforcements r
             JOIN marches m ON m.id = r.march_id
             WHERE r.id = ? AND r.sender_id = ? AND r.state = 'active'",
            [$reinforcementId, $playerId],
        )->fetch();

        if ($reinforcement === false) {
            Response::error(404, 'NOT_FOUND', 'Verstärkung nicht gefunden oder nicht deine.');
        }

        $db->transaction(function (Connection $db) use ($reinforcement, $reinforcementId): void {
            // Mark reinforcement as recalled
            $db->execute(
                "UPDATE reinforcements SET state = 'recalled', recalled_at = UTC_TIMESTAMP() WHERE id = ?",
                [$reinforcementId],
            );

            // Return troops directly (no march needed — instant return for simplicity)
            $troops = json_decode((string) ($reinforcement['troops_json'] ?? '{}'), true) ?? [];
            foreach ($troops as $code => $count) {
                $count = (int) $count;
                if ($count <= 0) continue;
                $db->execute(
                    'INSERT INTO city_troops (city_id, troop_code, count) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + ?',
                    [(int) $reinforcement['origin_city_id'], (int) $code, $count, $count],
                );
            }
        });

        Response::ok(['recalled' => true]);
    }

    /**
     * GET /api/march/reinforcements
     *
     * Returns active reinforcements sent by and received by the current player.
     */
    public static function listReinforcements(array $session): void
    {
        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $state = CityState::loadForPlayer($playerId);
        $cityId = $state !== null ? (int) $state['city']['id'] : 0;

        $sent = $db->query(
            "SELECT r.id, r.target_player_id, p.username AS target_username,
                    r.troops_json, r.state, r.created_at
             FROM reinforcements r
             JOIN players p ON p.id = r.target_player_id
             WHERE r.sender_id = ? AND r.state = 'active'
             ORDER BY r.created_at DESC",
            [$playerId],
        )->fetchAll();

        $received = [];
        if ($cityId > 0) {
            $received = $db->query(
                "SELECT r.id, r.sender_id, p.username AS sender_username,
                        r.troops_json, r.state, r.created_at
                 FROM reinforcements r
                 JOIN players p ON p.id = r.sender_id
                 WHERE r.target_city_id = ? AND r.state = 'active'
                 ORDER BY r.created_at DESC",
                [$cityId],
            )->fetchAll();
        }

        // Decode troops_json for display
        foreach ($sent as &$s) {
            $s['troops'] = json_decode($s['troops_json'] ?? '{}', true);
            unset($s['troops_json']);
        }
        unset($s);
        foreach ($received as &$r) {
            $r['troops'] = json_decode($r['troops_json'] ?? '{}', true);
            unset($r['troops_json']);
        }
        unset($r);

        Response::ok(['sent' => $sent, 'received' => $received]);
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
