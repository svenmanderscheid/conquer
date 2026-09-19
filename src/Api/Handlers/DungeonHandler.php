<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Dungeon\DungeonException;
use Conquer\Game\Dungeon\DungeonService;

final class DungeonHandler
{
    public static function state(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{Response::ok(DungeonService::state((int)$session['player_id']));}catch(DungeonException $e){Response::error($e->status,$e->errorCode,$e->getMessage());}
    }

    public static function action(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals((string)$session['csrf_token'],(string)$csrf))Response::error(403,'CSRF_INVALID','Ungültige Sitzung.');
        $raw=(string)file_get_contents('php://input');if(strlen($raw)>16384)Response::error(413,'INVALID_INPUT','Die Anfrage ist zu groß.');
        try{$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_INPUT','Ungültige Anfrage.');}
        if(!is_array($body)||array_is_list($body))Response::error(400,'INVALID_INPUT','Ein Aktionsobjekt ist erforderlich.');
        try{Response::ok(DungeonService::action((int)$session['player_id'],$body));}
        catch(DungeonException $e){Response::error($e->status,$e->errorCode,$e->getMessage());}
        catch(\InvalidArgumentException|\DomainException $e){Response::error(400,'DUNGEON_FAILED',$e->getMessage());}
    }
}
