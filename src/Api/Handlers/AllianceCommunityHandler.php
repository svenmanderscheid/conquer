<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Locale;
use Conquer\Game\Community\AllianceCommunityService;
use Conquer\Game\World\WorldContext;

final class AllianceCommunityHandler
{
    public static function state(array $params=[]): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED',Locale::t('alliance_community.error.login'));
        try{
            $world=WorldContext::current($_GET['world_id']??null);
            $state=AllianceCommunityService::state((int)$session['player_id'],$world,$_GET);
        }catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'ALLIANCE_COMMUNITY_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($state);
    }

    public static function action(array $params=[]): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED',Locale::t('alliance_community.error.login'));
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if(!is_string($csrf)||$csrf===''||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID',Locale::t('alliance_community.error.csrf'));
        $raw=file_get_contents('php://input')?:'';
        if(strlen($raw)>16384)Response::error(413,'REQUEST_TOO_LARGE',Locale::t('alliance_community.error.large'));
        try{$body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON',Locale::t('alliance_community.error.input'));}
        if(!is_array($body)||!is_string($body['action']??null))Response::error(400,'INVALID_ACTION',Locale::t('alliance_community.error.input'));
        try{$result=AllianceCommunityService::action((int)$session['player_id'],$body,WorldContext::current($body['world_id']??null));}
        catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'ALLIANCE_COMMUNITY_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($result);
    }
}
