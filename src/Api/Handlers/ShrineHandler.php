<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\Shrine\{ShrineService,CongressService};

/** Authenticated real march endpoints for alliance-controlled shrines. */
final class ShrineHandler
{
    public static function list(array $session): void
    {
        self::player($session);CongressService::tick();Response::ok(['shrines'=>ShrineService::getAllShrines(\Conquer\Game\World\WorldContext::id())]);
    }

    public static function detail(array $session,int $id): void
    {
        $pid=self::player($session);CongressService::tick();$shrine=CongressService::detail($id,$pid);
        if(!$shrine)Response::error(404,'NOT_FOUND','Schrein nicht gefunden.');
        Response::ok(['shrine'=>$shrine]);
    }

    public static function attack(array $session,int $id): void {self::dispatch($session,$id,false);}
    public static function garrison(array $session,int $id): void {self::dispatch($session,$id,true);}

    private static function dispatch(array $session,int $id,bool $garrison): void
    {
        $pid=self::player($session,true);$body=self::body();
        try{$result=CongressService::dispatch($pid,$id,$body['troops']??null,$garrison);}
        catch(\RuntimeException $e){self::failure($e);}
        Response::ok($result);
    }

    public static function recall(array $session,int $id): void
    {
        $pid=self::player($session,true);self::body();
        try{$result=CongressService::recall($pid,$id);}
        catch(\RuntimeException $e){self::failure($e);}
        Response::ok($result);
    }

    private static function body(): array
    {
        try{$object=json_decode((string)file_get_contents('php://input'),false,32,JSON_THROW_ON_ERROR);}
        catch(\JsonException){Response::error(400,'INVALID_JSON','Ein gültiges JSON-Objekt ist erforderlich.');}
        if(!$object instanceof \stdClass)Response::error(400,'INVALID_JSON','Ein JSON-Objekt ist erforderlich.');
        $body=get_object_vars($object);
        if(isset($body['troops'])&&$body['troops'] instanceof \stdClass)$body['troops']=get_object_vars($body['troops']);
        return $body;
    }

    private static function player(array $session,bool $mutation=false): int
    {
        if(empty($session['player_id']))Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        if($mutation){$current=Session::current();$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!$current||$csrf===''||!hash_equals((string)$current['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Ungültiges Sitzungstoken.');}
        return (int)$session['player_id'];
    }

    private static function failure(\RuntimeException $e): never
    {
        $status=in_array($e->getCode(),[403,404],true)?$e->getCode():400;
        Response::error($status,'SHRINE_ACTION_FAILED',$e->getMessage());
    }
}
