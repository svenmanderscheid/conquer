<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Admin\CaseService;
use Conquer\Api\Response;
use Conquer\Auth\Session;

final class SupportCaseHandler
{
    public static function inbox(array $params=[]):void { self::handle('inbox',$params); }
    public static function detail(array $params=[]):void { self::handle('detail',$params); }
    public static function reply(array $params=[]):void { self::handle('reply',$params); }
    private static function handle(string $action,array $params):void
    {
        header('Cache-Control: private, no-store');
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED',CaseService::t('denied'));
        try {
            $player=(int)$session['player_id'];$source=(string)($params['source']??'');$id=(int)($params['id']??0);
            if($action==='inbox')Response::ok(['cases'=>CaseService::playerInbox($player)]);
            if($action==='detail')Response::ok(CaseService::playerDetail($player,$source,$id));
            $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??null;if(!is_string($csrf)||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID',CaseService::t('csrf'));
            $raw=file_get_contents('php://input')?:'';if(strlen($raw)>20000)Response::error(413,'TOO_LARGE',CaseService::t('invalid'));
            $body=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($body))Response::error(400,'INVALID_JSON',CaseService::t('invalid'));
            Response::ok(CaseService::playerReply($player,$source,$id,$body));
        }catch(\DomainException $e){Response::error(422,'CASE_INVALID',$e->getMessage());}
        catch(\JsonException){Response::error(400,'INVALID_JSON',CaseService::t('invalid'));}
        catch(\Throwable $e){\Conquer\Observability\EventLog::exception($e,'case.support','CASE_CONVERSATION_FAILED');Response::error(500,'CASE_FAILED',CaseService::t('failed'));}
    }
}
