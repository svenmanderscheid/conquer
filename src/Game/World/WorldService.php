<?php
declare(strict_types=1);
namespace Conquer\Game\World;
use Conquer\Db\Connection;
use Conquer\Auth\{OAuth,Session};

final class WorldService
{
    public static function state(int $playerId): array
    {
        $worlds=Connection::getInstance()->query('SELECT w.id,w.name,w.slug,w.status,w.map_size,w.speed_factor,w.gather_factor,w.haul_factor,c.id AS city_id,c.name AS city_name,c.castle_level FROM worlds w LEFT JOIN cities c ON c.world_id=w.id AND c.player_id=? ORDER BY w.id',[$playerId])->fetchAll();
        foreach($worlds as &$world){$world['id']=(int)$world['id'];$world['owned']=$world['city_id']!==null;$world['selected']=$world['id']===WorldContext::id();$world['can_join']=!$world['owned']&&in_array($world['status'],['open','running'],true);$world['can_select']=$world['owned'];}unset($world);
        return ['active_world_id'=>WorldContext::id(),'worlds'=>$worlds,'server_time'=>time()];
    }
    public static function action(array $session,array $body): array
    {
        $player=(int)$session['player_id'];$sessionId=(int)$session['id'];$world=WorldContext::integer($body['world_id']??null);$expected=WorldContext::integer($body['expected_world_id']??null);
        $action=$body['action']??'';$request=$body['request_id']??'';
        if(!in_array($action,['join','select'],true)||!is_string($request)||!preg_match('/^[A-Za-z0-9_-]{16,80}$/D',$request))throw new \DomainException('Ungültiger Weltvorgang.');
        $hash=hash('sha256',json_encode([$sessionId,$action,$world,$expected],JSON_THROW_ON_ERROR));$db=Connection::getInstance();$key='conquer-player-'.$player;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new \DomainException('Dein Königreich wird gerade aktualisiert. Bitte erneut versuchen.');
        try{
            $result=$db->transaction(static function(Connection $db)use($player,$sessionId,$world,$expected,$action,$request,$hash):array{
                $current=$db->query('SELECT s.active_world_id,p.username,p.is_banned FROM sessions s JOIN players p ON p.id=s.player_id WHERE s.id=? AND s.player_id=? AND s.expires_at>UTC_TIMESTAMP() FOR UPDATE',[$sessionId,$player])->fetch();
                if(!$current||$current['is_banned'])throw new \DomainException('Deine Sitzung ist abgelaufen.',403);
                $receipt=$db->query('SELECT payload_hash,result_json FROM world_operations WHERE player_id=? AND request_id=?',[$player,$request])->fetch();
                if($receipt){if(!hash_equals($receipt['payload_hash'],$hash))throw new \DomainException('Diese Vorgangskennung wurde bereits für eine andere Weltaktion verwendet.');$result=json_decode($receipt['result_json'],true,512,JSON_THROW_ON_ERROR);$result['duplicate']=true;return $result;}
                if((int)$current['active_world_id']!==$expected)throw new \DomainException('Die aktive Welt hat sich bereits geändert. Lade die Weltauswahl neu.',409);
                $target=$db->query('SELECT id,status FROM worlds WHERE id=? FOR UPDATE',[$world])->fetch();if(!$target)throw new \DomainException('Diese Welt existiert nicht.');
                $city=$db->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn();
                if(!$city){if($action!=='join')throw new \DomainException('Tritt dieser Welt zuerst bei.',403);WorldContext::assertActionAvailable($world);self::initializeWorld($world);OAuth::createDefaultCity($db,$player,$current['username'],$world);$city=$db->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$player,$world])->fetchColumn();}
                $db->execute('UPDATE sessions SET active_world_id=? WHERE id=? AND player_id=?',[$world,$sessionId,$player]);
                $result=['active_world_id'=>$world,'city_id'=>(int)$city,'duplicate'=>false,'message'=>$action==='join'?'Dein Königreich in dieser Welt ist bereit.':'Welt gewechselt.'];
                $db->execute('INSERT INTO world_operations(player_id,session_id,request_id,payload_hash,action,world_id,result_json) VALUES(?,?,?,?,?,?,?)',[$player,$sessionId,$request,$hash,$action,$world,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
            });
            // A replay must not switch back after a more recent selection.
            $active=(int)$db->query('SELECT active_world_id FROM sessions WHERE id=? AND player_id=?',[$sessionId,$player])->fetchColumn();
            Session::setActiveWorld($active);$result['state']=self::state($player);return $result;
        }finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }

    /** New worlds receive the same five landmarks before the first city reserves space. */
    public static function initializeWorld(int $worldId): void
    {
        $db=Connection::getInstance();
        $work=static function()use($db,$worldId):void{
            $size=\Conquer\Game\Map\WorldPlacement::lockWorld($db,$worldId);
            if($size!==256)throw new \DomainException('Spielbare Welten benötigen derzeit genau 256 × 256 Felder.');
            $center=intdiv($size,2);
            $existing=$db->query('SELECT shrine_code FROM shrines WHERE world_id=?',[$worldId])->fetchAll(\PDO::FETCH_COLUMN);
            if(!in_array('CONGRESS',$existing,true)){
                // The Congress intentionally occupies the central lake; all other
                // placement still obeys dry terrain and the established footprints.
                foreach(['cities'=>'city','field_objects'=>'resource','field_monsters'=>'monster','shrines'=>'shrine']as$table=>$kind)foreach($db->query('SELECT coord_x,coord_y'.($kind==='monster'?',monster_code':'').' FROM '.$table.' WHERE world_id=? AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? FOR UPDATE',[$worldId,$center-6,$center+6,$center-6,$center+6])->fetchAll()as$row){if(\Conquer\Game\Map\WorldPlacement::conflicts('congress',$center,$center,$kind==='monster'?\Conquer\Game\Map\WorldPlacement::monsterKind((int)$row['monster_code']):$kind,(int)$row['coord_x'],(int)$row['coord_y']))throw new \DomainException('Der Platz für den zentralen Kongress ist bereits belegt.');}
                $db->execute("INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y)VALUES(?,'CONGRESS','S',?,?)",[$worldId,$center,$center]);
            }
            $config=json_decode((string)file_get_contents(ROOT_DIR.'/data/shrine_event.json'),true,32,JSON_THROW_ON_ERROR);
            foreach($config['shrines']as$shrine){if(in_array($shrine['code'],$existing,true))continue;$x=$center+(int)$shrine['coord_x']-128;$y=$center+(int)$shrine['coord_y']-128;
                $position=\Conquer\Game\Map\WorldPlacement::findNear($db,$worldId,'shrine',$x,$y,null,24);
                if(!$position)throw new \DomainException('Für einen Weltschrein ist kein freier trockener Platz verfügbar.');
                $db->execute("INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y)VALUES(?,?,'C',?,?)",[$worldId,$shrine['code'],$position[0],$position[1]]);
            }
            LandProgressService::ensureWorld($worldId);
        };
        if($db->getPdo()->inTransaction())$work();else $db->transaction($work);
    }
}
