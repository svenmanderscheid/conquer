<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Rally\RallyService;

/**
 * Handles /api/rally/* endpoints.
 *
 * POST /api/rally/start    — start a rally as leader
 * POST /api/rally/join     — join an existing rally as participant
 * GET  /api/rally/list     — list active rallies for the player's alliance
 * GET  /api/rally/:id      — get details of a specific rally
 */
final class RallyHandler
{
    private function __construct() {}

    // ── POST /api/rally/start ─────────────────────────────────────────────────

    public static function start(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body           = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetX        = (int)   ($body['target_x']        ?? -1);
        $targetY        = (int)   ($body['target_y']        ?? -1);
        $targetPlayerId = (int)   ($body['target_player_id'] ?? 0);
        $troops         = (array) ($body['troops']           ?? []);
        $rallyMinutes   = (int)   ($body['rally_minutes']    ?? 5);
        $message        = (string)($body['message']          ?? '');

        if ($targetX < 0 || $targetY < 0 || $targetPlayerId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Zielkoordinaten und Spieler-ID sind erforderlich.');
        }

        $playerId = (int) $session['player_id'];
        $state    = CityState::loadForPlayer($playerId);
        if ($state === null) Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');

        $cityId = (int) $state['city']['id'];

        try {
            $rallyId = RallyService::start(
                leaderId:       $playerId,
                leaderCityId:  $cityId,
                targetPlayerId: $targetPlayerId,
                targetX:        $targetX,
                targetY:        $targetY,
                troops:         $troops,
                rallyMinutes:   $rallyMinutes,
                message:        $message,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'RALLY_FAILED', $e->getMessage());
        }

        Response::ok(['rally_id' => $rallyId]);
    }

    // ── POST /api/rally/join ──────────────────────────────────────────────────

    public static function join(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $rallyId = (int)   ($body['rally_id'] ?? 0);
        $troops  = (array) ($body['troops']   ?? []);

        if ($rallyId <= 0) Response::error(400, 'INVALID_INPUT', 'rally_id ist erforderlich.');

        $playerId = (int) $session['player_id'];
        $state    = CityState::loadForPlayer($playerId);
        if ($state === null) Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');

        $cityId = (int) $state['city']['id'];

        try {
            RallyService::join(
                playerId: $playerId,
                cityId:   $cityId,
                rallyId:  $rallyId,
                troops:   $troops,
            );
        } catch (\RuntimeException $e) {
            Response::error(400, 'JOIN_FAILED', $e->getMessage());
        }

        Response::ok(['joined' => true]);
    }

    // ── GET /api/rally/list ───────────────────────────────────────────────────

    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $allianceRow = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($allianceRow === false) {
            Response::ok(['rallies' => []]);
        }

        $rallies = RallyService::listForAlliance((int) $allianceRow['alliance_id']);

        Response::ok(['rallies' => $rallies]);
    }

    // ── GET /api/rally/:id ────────────────────────────────────────────────────

    public static function detail(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $rallyId = (int) ($params['id'] ?? 0);
        if ($rallyId <= 0) Response::error(400, 'INVALID_INPUT', 'Ungültige Rally-ID.');

        $db = Connection::getInstance();

        $rally = $db->query(
            'SELECT r.*, p.username AS leader_name, tp.username AS target_name
             FROM   rallies r
             JOIN   players p  ON p.id  = r.leader_player_id
             JOIN   players tp ON tp.id = r.target_player_id
             WHERE  r.id = ?',
            [$rallyId],
        )->fetch();

        if ($rally === false) Response::error(404, 'NOT_FOUND', 'Rally nicht gefunden.');

        $participants = $db->query(
            'SELECT rp.player_id, rp.troops_json, rp.status, p.username
             FROM   rally_participants rp
             JOIN   players p ON p.id = rp.player_id
             WHERE  rp.rally_id = ?',
            [$rallyId],
        )->fetchAll();

        foreach ($participants as &$rp) {
            $rp['troops'] = json_decode($rp['troops_json'], true) ?? [];
            unset($rp['troops_json']);
        }
        unset($rp);

        Response::ok([
            'rally'        => $rally,
            'participants' => $participants,
        ]);
    }
}
