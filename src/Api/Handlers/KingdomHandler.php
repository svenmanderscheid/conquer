<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Kingdom\KingdomService;

/** Authenticated supporting menus for the cooperative game client. */
final class KingdomHandler
{
    public static function state(array $params): void
    {
        $session = Session::current();
        if (!$session) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        $target = $_GET['player_id'] ?? $session['player_id'];
        if (filter_var($target, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
            Response::error(400, 'INVALID_PLAYER', 'Ungültiges Spielerprofil.');
        }
        try { $state = KingdomService::state((int) $session['player_id'], (int) $target); }
        catch (\DomainException $e) { Response::error(422, 'KINGDOM_RULE', $e->getMessage()); }
        header('Cache-Control: no-store');
        Response::ok($state);
    }

    public static function action(array $params): void
    {
        $session = Session::current();
        if (!$session) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals((string) $session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'Sicherheitstoken ungültig. Bitte lade das Spiel neu.');
        }
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw)>8192) { Response::error(413, 'INVALID_JSON', 'Diese Anfrage ist zu groß.'); }
        try { $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Response::error(400, 'INVALID_JSON', 'Ungültige Anfrage.'); }
        if (!is_array($body) || !isset($body['action']) || !is_string($body['action'])) {
            Response::error(400, 'INVALID_ACTION', 'Die gewünschte Aktion fehlt.');
        }
        try {
            $result = KingdomService::action((int) $session['player_id'], $body);
        } catch (\DomainException $e) { Response::error(in_array($e->getCode(),[403,503],true) ? $e->getCode() : 422, 'KINGDOM_RULE', $e->getMessage()); }
        catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                Response::error(422, 'NAME_UNAVAILABLE', 'Dieser Name oder dieses Kürzel ist bereits vergeben.');
            }
            throw $e;
        }
        Response::ok($result);
    }
}
