<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Expedition\ExpeditionException;
use Conquer\Game\Expedition\ExpeditionService;

final class ExpeditionHandler
{
    public static function state(array $params): void
    {
        $session = Session::current();
        if ($session === null) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        try { $state = ExpeditionService::state((int) $session['player_id']); }
        catch (ExpeditionException $e) { Response::error($e->httpStatus, $e->errorCode, $e->getMessage()); }
        header('Cache-Control: no-store');
        Response::ok($state);
    }

    public static function action(array $params): void
    {
        $session = Session::current();
        if ($session === null) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrf) || $csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'Sicherheitstoken ungültig. Lade die Seite erneut.');
        }
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > 8192) { Response::error(413, 'INVALID_INPUT', 'Die Anfrage ist zu groß.'); }
        try { $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Response::error(400, 'INVALID_INPUT', 'Die Anfrage enthält ungültige Daten.'); }
        if (!is_array($body) || array_is_list($body)) { Response::error(400, 'INVALID_INPUT', 'Ein Aktionsobjekt ist erforderlich.'); }
        try { $result = ExpeditionService::action((int) $session['player_id'], $body); }
        catch (ExpeditionException $e) { Response::error($e->httpStatus, $e->errorCode, $e->getMessage()); }
        Response::ok($result);
    }
}
