<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Trading\MarketService;

final class MarketHandler
{
    public static function state(array $params): void
    {
        $session=Session::current();
        if (!$session) { Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.'); }
        header('Cache-Control: no-store');
        Response::ok(MarketService::state((int)$session['player_id']));
    }

    public static function action(array $params): void
    {
        $session=Session::current();
        if (!$session) { Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.'); }
        if (!hash_equals($session['csrf_token'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) { Response::error(403,'CSRF_INVALID','Bitte lade deine Sitzung neu.'); }
        $body=json_decode(file_get_contents('php://input')?:'',true);
        if (!is_array($body) || !is_string($body['offer_id']??null) || strlen($body['offer_id'])>40) { Response::error(400,'INVALID_INPUT','Wähle ein gültiges Handelsangebot.'); }
        try { \Conquer\Game\World\WorldContext::current($body['expected_world_id']??null);MarketService::exchange((int)$session['player_id'],$body['offer_id']); }
        catch (\DomainException $e) { Response::error($e->getCode()===409?409:422,'WORLD_RULE',$e->getMessage()); }
        catch (\InvalidArgumentException $e) { Response::error(400,'INVALID_OFFER',$e->getMessage()); }
        catch (\RuntimeException $e) { Response::error(422,'TRADE_FAILED',$e->getMessage()); }
        Response::ok(['message'=>'Der Händler hat deine Waren getauscht.','state'=>MarketService::state((int)$session['player_id'])]);
    }
}
