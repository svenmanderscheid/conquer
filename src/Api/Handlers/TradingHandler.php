<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Trading\TradingShopService;
use Conquer\Game\World\WorldContext;

/** HTTP adapter for the same authoritative shop used by Kingdom actions. */
final class TradingHandler
{
    public static function caravan(array $params): void
    {
        $session=Session::current();
        if($session===null)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        header('Cache-Control: no-store');
        try{Response::ok(TradingShopService::state((int)$session['player_id']));}
        catch(\DomainException $e){Response::error(422,'TRADE_FAILED',$e->getMessage());}
    }

    public static function buy(array $params): void
    {
        $session=Session::current();
        if($session===null)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        if(!hash_equals($session['csrf_token'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))Response::error(403,'CSRF_INVALID','Bitte lade deine Sitzung neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true);
        if(!is_array($body)||!is_string($body['offer_id']??null)||!is_string($body['rotation']??null)||!is_int($body['quantity']??null)||!is_string($body['mode']??'caravan'))Response::error(400,'INVALID_INPUT','Bitte lade den Handel neu und wähle ein aktuelles Angebot.');
        try{
            WorldContext::current($body['expected_world_id']??null);
            $id=(int)$session['player_id'];
            $result=TradingShopService::buy($id,$body['mode']??'caravan',$body['offer_id'],$body['quantity'],$body['rotation']);
            Response::ok(['result'=>$result,'state'=>TradingShopService::state($id)]);
        }catch(\DomainException $e){Response::error($e->getCode()===409?409:422,'TRADE_FAILED',$e->getMessage());}
    }
}
