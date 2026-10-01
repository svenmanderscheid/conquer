<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Territory\TerritoryService;

final class TerritoryHandler
{
    public static function state(array $params=[]): void { self::read(false); }
    public static function target(array $params=[]): void { self::read(true); }
    private static function read(bool $detail): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{$world=WorldContext::current($_GET['world_id']??null);TerritoryService::tick($world);$result=$detail?TerritoryService::detail((int)$session['player_id'],$world,(string)($_GET['id']??'')):TerritoryService::state((int)$session['player_id'],$world);}
        catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'TERRITORY_RULE',$e->getMessage());}
        Response::ok($result);
    }
    public static function action(array $params=[]): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($csrf)||$csrf===''||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Sicherheitstoken ungültig.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>16384)Response::error(413,'REQUEST_TOO_LARGE','Die Anfrage ist zu groß.');
        try{$body=json_decode($raw,true,24,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body))Response::error(400,'INVALID_ACTION','Die Aktion fehlt.');
        try{$result=TerritoryService::action((int)$session['player_id'],$body,WorldContext::current($body['world_id']??null));}
        catch(\DomainException|\RuntimeException $e){Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'TERRITORY_RULE',$e->getMessage());}
        if(isset($result['message'])&&is_string($result['message']))$result['message']=\Conquer\Game\Locale::text($result['message']);
        Response::ok($result);
    }
}
