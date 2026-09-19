<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\City\CityState;
use Conquer\Game\Hospital\HospitalService;

final class HospitalHandler
{
    public static function status(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $state=CityState::loadForPlayer((int)$session['player_id']);
        if(!$state)Response::error(404,'NO_CITY','Deine Stadt wurde nicht gefunden.');
        header('Cache-Control: no-store');Response::ok(HospitalService::getStatus((int)$state['city']['id']));
    }
    public static function heal(array $params): void {self::command('hospital.heal');}
    public static function instantHeal(array $params): void {self::command('hospital.instant');}
    public static function finish(array $params): void {self::command('hospital.finish');}
    public static function speedup(array $params): void {self::command('hospital.speedup');}
    private static function command(string $action): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if($csrf===''||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Ungültige Sitzung.');
        $raw=file_get_contents('php://input')?:'';
        if(strlen($raw)>8192)Response::error(413,'INVALID_JSON','Die Anfrage ist zu groß.');
        try{$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        $body['action']=$action;
        try{$result=HospitalService::execute((int)$session['player_id'],$body);}
        catch(\DomainException $e){Response::error($e->getCode()===409?409:422,'HOSPITAL_RULE',$e->getMessage());}
        Response::ok($result);
    }
}
