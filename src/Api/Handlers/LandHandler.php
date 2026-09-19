<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\World\{LandProgressService,WorldContext};

/** Mobile-safe JSON endpoints for the separate regional overview. */
final class LandHandler
{
    public static function state(array $params=[]): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{$world=WorldContext::current($_GET['world_id']??null);$result=LandProgressService::overview($world,(int)$session['player_id']);}
        catch(\DomainException $e){self::ruleError($e);}
        header('Cache-Control: no-store');Response::ok($result);
    }

    public static function detail(array $params=[]): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $land=self::positiveInt($params['id']??null,'Landteil');
        try{$world=WorldContext::current($_GET['world_id']??null);$result=LandProgressService::detail($world,(int)$session['player_id'],$land);}
        catch(\DomainException $e){self::ruleError($e);}
        header('Cache-Control: no-store');Response::ok($result);
    }

    public static function donate(array $params=[]): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!is_string($csrf)||$csrf===''||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Sicherheitstoken ungültig. Lade das Spiel neu.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>8192)Response::error(413,'REQUEST_TOO_LARGE','Diese Anfrage ist zu groß.');
        try{$body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        $land=self::positiveInt($params['id']??null,'Landteil');$request=$body['request_id']??$body['operation_key']??'';
        $revision=self::positiveInt($body['revision']??null,'Version',true);$resources=$body['resources']??null;
        if(!is_string($request)||!is_array($resources))Response::error(422,'INVALID_DONATION','Vorgangskennung und Ressourcen werden benötigt.');
        try{
            if(!array_key_exists('expected_world_id',$body))throw new \DomainException('Die erwartete Welt fehlt.',409);
            $world=WorldContext::current($body['expected_world_id']);WorldContext::assertActionAvailable($world);
            $result=LandProgressService::donate($world,(int)$session['player_id'],$land,$request,$revision,$resources);
        }catch(\DomainException $e){self::ruleError($e);}
        header('Cache-Control: no-store');Response::ok($result);
    }

    private static function positiveInt(mixed $value,string $label,bool $allowZero=false): int
    {
        $min=$allowZero?0:1;
        if((!is_int($value)&&!is_string($value))||filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>PHP_INT_MAX]])===false)Response::error(422,'INVALID_INPUT',"$label ist ungültig.");
        return (int)$value;
    }

    private static function ruleError(\DomainException $e): never
    {
        $message=$e->getMessage();$status=in_array($e->getCode(),[403,404,409],true)?$e->getCode():422;
        $code=match(true){$status===404=>'LAND_NOT_FOUND',$status===403=>'FORBIDDEN',str_contains($message,'aktiven Welt')||str_contains($message,'erwartete Welt')=>'WORLD_MISMATCH',str_contains($message,'Vorgangskennung')=>'REQUEST_REUSED',str_contains($message,'zwischenzeitlich')=>'STALE_LAND',str_contains($message,'geschlossen')=>'LAND_LOCKED',str_contains($message,'Stufe 9')=>'LAND_MAX_LEVEL',str_contains($message,'Spende')||str_contains($message,'spenden')=>'INVALID_DONATION',default=>'LAND_RULE'};
        Response::error($status,$code,$message);
    }
}
