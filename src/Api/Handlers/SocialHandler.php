<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Community\SocialService;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;

final class SocialHandler
{
    public static function state(array $params):void
    {
        $session=self::session();
        try{
            $world=WorldContext::current($_GET['world_id']??null);$query=$_GET['query']??'';
            if(!is_string($query))throw new \DomainException(Locale::t('social.invalid'));
            $result=SocialService::state((int)$session['player_id'],$world,$query);
        }catch(\DomainException $e){self::failure($e);}
        Response::ok($result);
    }

    public static function history(array $params):void
    {
        $session=self::session();
        try{
            $world=WorldContext::current($_GET['world_id']??null);$channel=$_GET['channel']??'world';
            if(!is_string($channel))throw new \DomainException(Locale::t('social.invalid'));
            $result=SocialService::history((int)$session['player_id'],$world,$channel,self::optionalId('player_id'),self::optionalId('before_id'));
        }catch(\DomainException $e){self::failure($e);}
        Response::ok($result);
    }

    public static function action(array $params):void
    {
        $session=self::session();$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if(!is_string($csrf)||$csrf===''||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Sicherheitstoken ungültig. Lade das Spiel neu.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>16384)Response::error(413,'REQUEST_TOO_LARGE','Die Nachricht ist zu groß.');
        try{$body=json_decode($raw,true,24,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON',Locale::t('social.invalid'));}
        if(!is_array($body)||!is_string($body['action']??null))Response::error(400,'INVALID_ACTION',Locale::t('social.invalid'));
        try{$result=SocialService::action((int)$session['player_id'],$body,WorldContext::current($body['world_id']??null));}
        catch(\DomainException $e){self::failure($e);}
        Response::ok($result);
    }

    private static function session():array{$session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');return $session;}
    private static function failure(\DomainException $e):never{Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'SOCIAL_RULE',$e->getMessage());}
    private static function optionalId(string $key):?int
    {
        if(!array_key_exists($key,$_GET))return null;$value=filter_var($_GET[$key],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if($value===false)throw new \DomainException(Locale::t('social.invalid'));return (int)$value;
    }
}
