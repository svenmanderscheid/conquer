<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Auth\AccountService;
use Conquer\Game\Player\MasteryService;
use Conquer\Game\Conquest\EventService;
use Conquer\Game\Operation;
use Conquer\Game\City\{CityState,ResourceTick};

final class ProgressionHandler
{
    public static function state(array $params=[]): void
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $pid=(int)$s['player_id'];header('Cache-Control: no-store');
        Response::ok(['events'=>EventService::state($pid),'mastery'=>MasteryService::snapshot($pid),'account'=>AccountService::state($pid)]);
    }

    public static function action(array $params=[]): void
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        if(!is_string($_SERVER['HTTP_X_CSRF_TOKEN']??null)||!hash_equals($s['csrf_token'],$_SERVER['HTTP_X_CSRF_TOKEN']))Response::error(403,'CSRF_INVALID','Ungültiges Sitzungstoken.');
        $raw=file_get_contents('php://input');if(strlen($raw)>16000)Response::error(413,'TOO_LARGE','Anfrage zu groß.');
        try{$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body)||!is_string($body['action']??null))Response::error(400,'INVALID_ACTION','Aktion fehlt.');
        try{
            $pid=(int)$s['player_id'];$action=$body['action'];
            if(in_array($action,['email.change','password.change','recovery.generate','sessions.revoke'],true)){$result=AccountService::action($pid,$body);}
            else{
                \Conquer\Game\World\WorldContext::current($body['expected_world_id']??null);\Conquer\Game\World\WorldContext::assertActionAvailable();EventService::tick(\Conquer\Game\World\WorldContext::id());$city=CityState::loadForPlayer($pid);if(!$city)throw new \DomainException('Deine Stadt wurde nicht gefunden.');ResourceTick::persist($city['city'],$city['buildings']);
                $result=Operation::run($pid,$body,static fn()=>match(true){
                    $action==='mastery.apply'=>MasteryService::change($pid,$body),
                    in_array($action,['invasion.dispatch','invasion.supply','invasion.claim','event.claim'],true)=>EventService::action($pid,$body),
                    default=>throw new \DomainException('Unbekannte Aktion.'),
                });
            }
        }catch(\DomainException $e){Response::error(422,'PROGRESSION_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($result);
    }
}
