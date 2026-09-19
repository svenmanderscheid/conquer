<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Game\World\WorldContext;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Defense\DefenseService;
final class DefenseHandler
{
    public static function state(array $params): void
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $city=$_GET['city_id']??null;
        if($city!==null&&filter_var($city,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)Response::error(400,'INVALID_CITY','Ungültige Stadt.');
        try{$state=DefenseService::state((int)$s['player_id'],$city===null?null:(int)$city);}catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'DEFENSE_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($state);
    }
    public static function action(array $params): void
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals((string)$s['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>8192)Response::error(413,'INVALID_JSON','Anfrage zu groß.');
        try{$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body)||!is_string($body['action']??null))Response::error(400,'INVALID_ACTION','Aktion fehlt.');
        try{$result=DefenseService::action((int)$s['player_id'],$body);}catch(\RuntimeException|\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'DEFENSE_RULE',$e->getMessage());}
        Response::ok($result);
    }
}
