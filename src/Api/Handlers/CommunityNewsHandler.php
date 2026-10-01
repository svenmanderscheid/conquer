<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;
use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Community\CommunityNewsService;
use Conquer\Game\World\WorldContext;
final class CommunityNewsHandler
{
    public static function state(array $params=[]):void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED',\Conquer\Game\Locale::t('social.sign_in'));
        try{$world=WorldContext::current($_GET['world_id']??null);WorldContext::city((int)$session['player_id'],$world);header('Cache-Control: private, no-store');Response::ok(CommunityNewsService::state($world));}
        catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'COMMUNITY_RULE',$e->getMessage());}
    }
}
