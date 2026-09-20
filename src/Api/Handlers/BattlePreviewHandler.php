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
        Response::error(503,'PREVIEW_DISABLED','Der Kampfrechner ist noch nicht verfügbar.');
        $session=Session::current();
        if (!$session) Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if ($csrf===''||!hash_equals($session['csrf_token'],$csrf)) Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $input=json_decode(file_get_contents('php://input')?:'',true);
        if (!is_array($input)) Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        try { $result=BattlePreview::calculate((int)$session['player_id'],$input); }
        catch (\PDOException $e) { throw $e; } // Central handler hides infrastructure details.
        catch (\DomainException|\RuntimeException $e) { Response::error(in_array($e->getCode(),[403,404,409],true)?$e->getCode():422,'PREVIEW_INVALID',$e->getMessage()); }
        Response::ok($result);
    }
}
