<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Premium\{PaymentGatewayFactory,ThemeBundleService};

final class ThemeBundleHandler
{
    public static function state(array $params):void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        header('Cache-Control: no-store');Response::ok(ThemeBundleService::state((int)$session['player_id']));
    }
    public static function notification(array $params):void
    {
        $provider=(string)($params['provider']??'');$raw=(string)file_get_contents('php://input');
        if(strlen($raw)>16384)Response::error(413,'PAYMENT_MESSAGE_TOO_LARGE','Die Zahlungsnachricht ist zu groß.');
        $headers=[];foreach($_SERVER as$key=>$value)if(str_starts_with($key,'HTTP_'))$headers[strtolower(str_replace('_','-',substr($key,5)))]=(string)$value;
        try{$result=ThemeBundleService::handleNotification(PaymentGatewayFactory::notification($provider),$raw,$headers);}
        catch(\DomainException $e){Response::error($e->getCode()===403?403:422,'PAYMENT_REJECTED',$e->getMessage());}
        Response::ok($result);
    }
}
