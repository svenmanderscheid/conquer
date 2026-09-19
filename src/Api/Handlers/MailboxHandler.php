<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Community\MailboxService;
use Conquer\Game\World\WorldContext;

final class MailboxHandler
{
    public static function state(array $params): void { self::read(false); }
    public static function message(array $params): void { self::read(true); }

    private static function read(bool $detail): void
    {
        $session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        try{
            $world=WorldContext::current($_GET['world_id']??null);$player=(int)$session['player_id'];
            if($detail&&(!is_string($_GET['id']??null)||filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false))throw new \DomainException('Ungültige Nachrichtenkennung.');
            $data=$detail?MailboxService::message($player,$world,(int)($_GET['id']??0)):MailboxService::state($player,$world,$_GET);
        }catch(\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'MAILBOX_RULE',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($data);
    }
}
