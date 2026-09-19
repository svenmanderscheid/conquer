<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Map\MapSearchService;

final class MapSearchHandler
{
    public static function search(array $params): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        header('Cache-Control: no-store');
        try{$result=MapSearchService::search((int)$session['player_id'],$_GET);}
        catch(\DomainException $e){Response::error($e->getCode()===403?403:422,'MAP_SEARCH_INVALID',$e->getMessage());}
        Response::ok($result);
    }
}
