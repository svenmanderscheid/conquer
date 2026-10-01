<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\March\BattlePreview;

final class BattlePreviewHandler
{
    public static function calculate(array $params): void
    {
        $session=Session::current();
        if (!$session) Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if ($csrf===''||!hash_equals($session['csrf_token'],$csrf)) Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $raw=(string)file_get_contents('php://input');
        if(strlen($raw)>16384) Response::error(413,'INVALID_INPUT','Die Anfrage ist zu groß.');
        try{$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if (!is_array($input)||array_is_list($input)) Response::error(400,'INVALID_JSON','Ein Anfrageobjekt ist erforderlich.');
        try { $result=BattlePreview::calculate((int)$session['player_id'],$input); }
        catch (\PDOException $e) { throw $e; } // Central handler hides infrastructure details.
        catch (\DomainException|\RuntimeException $e) { Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'PREVIEW_INVALID',$e->getMessage()); }
        Response::ok($result);
    }
}
