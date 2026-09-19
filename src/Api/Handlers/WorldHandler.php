<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\World\WorldService;
final class WorldHandler
{
    public static function state(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');header('Cache-Control: no-store');Response::ok(WorldService::state((int)$session['player_id']));
    }
    public static function action(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($csrf)||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade deine Sitzung neu.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>4096)Response::error(413,'REQUEST_TOO_LARGE','Diese Anfrage ist zu groß.');
        try{$body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($body))throw new \DomainException('Ungültige Anfrage.');$result=WorldService::action($session,$body);}
        catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());}
        Response::ok($result);
    }
}
