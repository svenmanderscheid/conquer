<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Community\CommunityService;

final class CommunityHandler
{
    public static function chat(array $params): void
    {
        $session = Session::current();
        if (!$session) Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
        try {
            $world = \Conquer\Game\World\WorldContext::current($_GET['world_id']??null);
            $target = null;
            if (array_key_exists('player_id', $_GET)) {
                $value = filter_var($_GET['player_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
                if ($value === false) throw new \DomainException('Ungültiger Chatpartner.');
                $target = (int)$value;
            }
            $state = CommunityService::chatState((int)$session['player_id'], $world, $target);
        } catch (\DomainException $e) { Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422, 'COMMUNITY_RULE', $e->getMessage()); }
        header('Cache-Control: no-store');
        Response::ok($state);
    }

    public static function state(array $params): void
    {
        $session = Session::current();
        if (!$session) Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
        try {
            $world = \Conquer\Game\World\WorldContext::current($_GET['world_id']??null);
            $state = CommunityService::state((int)$session['player_id'], $world);
        } catch (\DomainException $e) { Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422, 'COMMUNITY_RULE', $e->getMessage()); }
        header('Cache-Control: no-store');
        Response::ok($state);
    }

    public static function action(array $params): void
    {
        $session = Session::current();
        if (!$session) Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrf) || $csrf==='' || !hash_equals((string)$session['csrf_token'], $csrf)) Response::error(403, 'CSRF_INVALID', 'Sicherheitstoken ungültig. Lade das Spiel neu.');
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw)>16384) Response::error(413, 'REQUEST_TOO_LARGE', 'Die Nachricht ist zu groß.');
        try { $body=json_decode($raw,true,24,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Response::error(400,'INVALID_JSON','Ungültige Anfrage.'); }
        if (!is_array($body)||!is_string($body['action']??null)) Response::error(400,'INVALID_ACTION','Die Aktion fehlt.');
        try { $result=CommunityService::action((int)$session['player_id'],$body,\Conquer\Game\World\WorldContext::current($body['world_id']??null)); }
        catch (\DomainException $e) { Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'COMMUNITY_RULE',$e->getMessage()); }
        Response::ok($result);
    }

    public static function sharedReport(array $params): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{$world=\Conquer\Game\World\WorldContext::current($_GET['world_id']??null);$report=CommunityService::sharedReport((int)$session['player_id'],(int)($params['id']??0),$world);}
        catch(\DomainException $e){$status=in_array($e->getCode(),[403,404,409],true)?$e->getCode():422;Response::error($status,'COMMUNITY_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok(['report'=>$report]);
    }

    private static function world(mixed $value): int
    {
        if ((!is_string($value)&&!is_int($value))||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])===false) throw new \DomainException('Ungültige Welt.');
        return (int)$value;
    }
}
