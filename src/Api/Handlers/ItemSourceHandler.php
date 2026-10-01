<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Rewards\ItemSourceService;

final class ItemSourceHandler
{
    public static function search(array $params): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{$result=ItemSourceService::search((int)$session['player_id'],$_GET);}
        catch(\DomainException $e){$status=in_array($e->getCode(),[403,409],true)?$e->getCode():422;Response::error($status,'ITEM_SOURCE_INVALID',$e->getMessage());}
        Response::ok($result);
    }
}
