<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\World\WorldContext;

final class LandmarkHandler
{
    public static function scout(array $params=[]): void
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Please sign in.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($csrf)||$csrf===''||!hash_equals((string)$s['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Invalid session token.');
        $raw=(string)file_get_contents('php://input');if(strlen($raw)>4096)Response::error(413,'INVALID_INPUT','Request too large.');
        try{$b=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_INPUT','Invalid request.');}
        if(!is_array($b)||!is_string($b['target_kind']??null)||!is_string($b['target_id']??null)||strlen($b['target_id'])>100)Response::error(400,'INVALID_INPUT','Invalid scouting target.');
        try{$city=WorldContext::city((int)$s['player_id']);$result=\Conquer\Game\March\LandmarkScout::dispatch((int)$s['player_id'],(int)$city['id'],$b['target_kind'],$b['target_id']);}
        catch(\RuntimeException|\DomainException $e){Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'SCOUT_FAILED',$e->getMessage());}
        Response::ok($result);
    }
}
