<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Game\World\WorldContext;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Rally\RallyService;
use Conquer\Game\WorldRules;

final class RallyHandler
{
    private static function session(bool $write=false): array
    {
        $s=Session::current();if(!$s)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        if($write){$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($s['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Ungültige Sitzung.');}return $s;
    }
    private static function body(): array
    {
        $raw=(string)file_get_contents('php://input');if(strlen($raw)>8192)Response::error(413,'INVALID_INPUT','Die Anfrage ist zu groß.');
        try{$b=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){Response::error(400,'INVALID_INPUT','Ungültige Anfrage.');}
        if(!is_array($b)||!str_starts_with(ltrim($raw),'{')||($b!==[]&&array_is_list($b)))Response::error(400,'INVALID_INPUT','Ein Aktionsobjekt ist erforderlich.');return $b;
    }
    private static function integer(array $b,string $name,int $min,int $max): int
    {
        $value=$b[$name]??null;if(!is_int($value)||$value<$min||$value>$max)Response::error(400,'INVALID_INPUT','Ungültiger Wert: '.$name);return $value;
    }
    public static function start(array $params): void
    {
        $s=self::session(true);$b=self::body();$pid=(int)$s['player_id'];
        $max=(int)Connection::getInstance()->query('SELECT map_size FROM worlds WHERE id=?',[WorldContext::id()])->fetchColumn()-1;$x=self::integer($b,'target_x',0,$max);$y=self::integer($b,'target_y',0,$max);$target=self::integer($b,'target_player_id',1,PHP_INT_MAX);$minutes=self::integer($b+['rally_minutes'=>5],'rally_minutes',1,60);
        if(!is_array($b['troops']??null)||!is_string($b['message']??''))Response::error(400,'INVALID_INPUT','Truppen und Rally-Nachricht sind ungültig.');
        $state=CityState::loadForPlayer($pid);if(!$state)Response::error(404,'NO_CITY','Keine Stadt gefunden.');
        try{$id=RallyService::start($pid,(int)$state['city']['id'],$target,$x,$y,$b['troops'],$minutes,$b['message']??'');}catch(\RuntimeException|\DomainException $e){Response::error(400,'RALLY_FAILED',$e->getMessage());}
        Response::ok(['rally_id'=>$id]);
    }
    public static function startMonster(array $params): void
    {
        $s=self::session(true);$b=self::body();$pid=(int)$s['player_id'];
        $max=(int)Connection::getInstance()->query('SELECT map_size FROM worlds WHERE id=?',[WorldContext::id()])->fetchColumn()-1;
        $x=self::integer($b,'target_x',0,$max);$y=self::integer($b,'target_y',0,$max);
        $minutes=self::integer($b+['rally_minutes'=>5],'rally_minutes',1,60);
        if(!is_array($b['troops']??null)||!is_string($b['message']??''))Response::error(400,'INVALID_INPUT','Truppen und Rally-Nachricht sind ungültig.');
        $city=WorldContext::city($pid);
        try{$id=\Conquer\Game\Rally\MonsterRally::start($pid,(int)$city['id'],$x,$y,$b['troops'],$minutes,$b['message']??'');}
        catch(\RuntimeException|\DomainException $e){Response::error(400,'RALLY_FAILED',$e->getMessage());}
        Response::ok(['rally_id'=>$id]);
    }
    public static function join(array $params): void
    {
        $s=self::session(true);$b=self::body();$id=self::integer($b,'rally_id',1,PHP_INT_MAX);if(!is_array($b['troops']??null))Response::error(400,'INVALID_INPUT','Wähle eine gültige Armee.');
        $state=CityState::loadForPlayer((int)$s['player_id']);if(!$state)Response::error(404,'NO_CITY','Keine Stadt gefunden.');
        try{RallyService::join((int)$s['player_id'],(int)$state['city']['id'],$id,$b['troops']);}catch(\RuntimeException|\DomainException $e){Response::error(400,'JOIN_FAILED',$e->getMessage());}
        Response::ok(['joined'=>true,'rally_id'=>$id]);
    }
    public static function launch(array $params): void
    {
        $s=self::session(true);self::body();$id=(int)($params['id']??0);if($id<=0)Response::error(400,'INVALID_INPUT','Ungültige Rally.');
        try{$result=RallyService::launch($id,(int)$s['player_id']);}catch(\RuntimeException|\DomainException $e){Response::error(400,'LAUNCH_FAILED',$e->getMessage());}Response::ok($result);
    }
    public static function cancel(array $params): void
    {
        $s=self::session(true);self::body();$id=(int)($params['id']??0);if($id<=0)Response::error(400,'INVALID_INPUT','Ungültige Rally.');
        try{RallyService::cancel($id,(int)$s['player_id']);}catch(\RuntimeException|\DomainException $e){Response::error(400,'CANCEL_FAILED',$e->getMessage());}Response::ok(['cancelled'=>true,'rally_id'=>$id]);
    }
    public static function list(array $params): void
    {
        $s=self::session();RallyService::tick();$alliance=WorldRules::alliance((int)$s['player_id']);Response::ok(['rallies'=>$alliance?RallyService::listForAlliance($alliance):[]]);
    }
    public static function detail(array $params): void
    {
        $s=self::session();RallyService::tick();$id=(int)($params['id']??0);$db=Connection::getInstance();
        $r=$db->query('SELECT r.*,COALESCE(k.display_name,p.username) AS leader_name,COALESCE(tk.display_name,tp.username) AS target_name FROM rallies r JOIN players p ON p.id=r.leader_player_id LEFT JOIN players tp ON tp.id=r.target_player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id LEFT JOIN kingdom_profiles tk ON tk.player_id=tp.id WHERE r.id=? AND r.world_id=?',[$id,WorldContext::id()])->fetch();
        if(!$r)Response::error(404,'NOT_FOUND','Rally nicht gefunden.');
        $participants=RallyService::getParticipants($id);$meta=json_decode($r['result_json']??'{}',true)?:[];$pid=(int)$s['player_id'];$alliance=WorldRules::alliance($pid);
        if($pid!==(int)$r['leader_player_id']&&$pid!==(int)$r['target_player_id']&&!in_array($pid,array_map(fn($p)=>(int)$p['player_id'],$participants),true)&&($alliance===null||$alliance!==($meta['alliance_id']??null)))Response::error(403,'FORBIDDEN','Diese Rally gehört nicht zu deiner Allianz.');
        $r=RallyService::describe($r);$r['troops']=json_decode($r['troops_json'],true)?:[];$r['result']=$meta;$r['participants']=$participants;$r['participant_count']=count($participants);unset($r['troops_json'],$r['result_json']);
        Response::ok(['rally'=>$r,'participants'=>$participants]);
    }
}
