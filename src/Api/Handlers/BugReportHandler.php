<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Support\BugReportService;
use Conquer\Game\World\WorldContext;

final class BugReportHandler
{
    public static function submit(array $params=[]): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??null;
        if(!is_string($csrf)||!hash_equals((string)$session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Ungültiges Sitzungstoken.');
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>12000)Response::error(413,'TOO_LARGE','Die Bugmeldung ist zu groß.');
        try{$body=json_decode($raw,true,24,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_JSON','Ungültige Anfrage.');}
        if(!is_array($body))Response::error(400,'INVALID_REPORT','Bugmeldung fehlt.');
        try{$result=BugReportService::submit((int)$session['player_id'],WorldContext::id(),$body,(string)($_SERVER['HTTP_USER_AGENT']??''),(string)($_SERVER['REMOTE_ADDR']??''));}
        catch(\DomainException $e){Response::error(422,'BUG_REPORT_INVALID',$e->getMessage());}
        header('Cache-Control: no-store');Response::ok($result);
    }
}
