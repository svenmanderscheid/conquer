<?php
declare(strict_types=1);
namespace Conquer\Game\Conquest;

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\Expedition\ExpeditionRules;
use Conquer\Game\Research\BuffEngine;

/** One scheduler for announced conquest cycles and cooperative world invasions. */
final class EventService
{
    public static function settings(int $worldId): array
    {
        $row=Connection::getInstance()->query('SELECT * FROM world_event_settings WHERE world_id=?',[$worldId])->fetch();
        return $row?:['world_id'=>$worldId,'enabled'=>0,'next_start'=>gmdate('Y-m-d 18:00:00',time()+86400),'interval_hours'=>336,'duration_hours'=>168,'invasion_enabled'=>0,'invasion_interval_hours'=>72,'invasion_next_start'=>gmdate('Y-m-d 18:00:00',time()+86400)];
    }

    public static function saveSettings(int $worldId,array $body): array
    {
        $db=Connection::getInstance();if(!$db->query('SELECT id FROM worlds WHERE id=?',[$worldId])->fetchColumn())throw new \DomainException('Welt nicht gefunden.');
        $values=[];foreach(['enabled','invasion_enabled']as$key){$v=$body[$key]??0;if(!in_array($v,[0,1,'0','1',false,true],true))throw new \DomainException('Ungültiger Ereignisschalter.');$values[$key]=(int)$v;}
        foreach(['interval_hours','duration_hours','invasion_interval_hours']as$key){$v=filter_var($body[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>8760]]);if($v===false)throw new \DomainException('Ereigniszeiten benötigen 1 bis 8.760 Stunden.');$values[$key]=$v;}
        if($values['duration_hours']>$values['interval_hours'])throw new \DomainException('Die Ereignisdauer darf das Intervall nicht überschreiten.');
        foreach(['next_start','invasion_next_start']as$key){$raw=$body[$key]??'';if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/D',$raw))throw new \DomainException('Startzeit als gültiges Datum in UTC angeben.');$dt=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',str_replace('T',' ',$raw).(strlen($raw)===16?':00':''),new \DateTimeZone('UTC'));if(!$dt||\DateTimeImmutable::getLastErrors()!==false)throw new \DomainException('Ungültiger Starttermin.');$values[$key]=$dt->format('Y-m-d H:i:s');}
        $db->execute('INSERT INTO world_event_settings(world_id,enabled,next_start,interval_hours,duration_hours,invasion_enabled,invasion_interval_hours,invasion_next_start) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),next_start=VALUES(next_start),interval_hours=VALUES(interval_hours),duration_hours=VALUES(duration_hours),invasion_enabled=VALUES(invasion_enabled),invasion_interval_hours=VALUES(invasion_interval_hours),invasion_next_start=VALUES(invasion_next_start)',[$worldId,$values['enabled'],$values['next_start'],$values['interval_hours'],$values['duration_hours'],$values['invasion_enabled'],$values['invasion_interval_hours'],$values['invasion_next_start']]);
        return self::settings($worldId);
    }

    public static function tick(?int $worldId=null): void
    {
        \Conquer\Game\WorldRules::combatLock(static fn()=>self::advance($worldId));
    }

    private static function advance(?int $worldId): void
    {
        $db=Connection::getInstance();$key='conquer-world-events';if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)return;
        try{
            $worlds=$db->query("SELECT id FROM worlds WHERE 1=1".($worldId===null?'':' AND id=?'),$worldId===null?[]:[$worldId])->fetchAll(\PDO::FETCH_COLUMN);
            foreach($worlds as$id){$id=(int)$id;$db->transaction(function()use($db,$id):void{
                $settings=$db->query('SELECT * FROM world_event_settings WHERE world_id=? FOR UPDATE',[$id])->fetch();if(!$settings)return;if(!in_array($db->query('SELECT status FROM worlds WHERE id=?',[$id])->fetchColumn(),['open','running'],true))return;
                if($settings['enabled'])self::schedule($id,$settings);
                if($settings['invasion_enabled'])self::scheduleInvasion($id,$settings);
            });
            foreach($db->query("SELECT * FROM conquest_events WHERE world_id=? AND state IN ('upcoming','active') ORDER BY starts_at",[$id])->fetchAll()as$event){$db->transaction(function()use($db,$event):void{
                $event=$db->query('SELECT * FROM conquest_events WHERE id=? FOR UPDATE',[$event['id']])->fetch();
                if(strtotime($event['starts_at'].' UTC')>time())return;
                $phase=min(4,1+(int)floor(max(0,time()-strtotime($event['starts_at'].' UTC'))/max(1,(strtotime($event['ends_at'].' UTC')-strtotime($event['starts_at'].' UTC'))/4)));
                $db->execute("UPDATE conquest_events SET state='active',phase=? WHERE id=?",[$phase,$event['id']]);$event['phase']=$phase;
                self::score($event);
                if(strtotime($event['ends_at'].' UTC')<=time()){$report=['alliances'=>self::leaderboard((int)$event['id']),'players'=>$db->query('SELECT c.player_id,p.username,c.points FROM conquest_contributions c JOIN players p ON p.id=c.player_id WHERE event_id=? ORDER BY points DESC,player_id LIMIT 100',[$event['id']])->fetchAll(),'ends_at'=>$event['ends_at']];$db->execute('INSERT IGNORE INTO conquest_results(event_id,result_json) VALUES(?,?)',[$event['id'],json_encode($report,JSON_THROW_ON_ERROR)]);$db->execute("UPDATE conquest_events SET state='ended' WHERE id=?",[$event['id']]);}
            });}
            $db->execute("UPDATE world_invasions SET state='active' WHERE world_id=? AND state='upcoming' AND starts_at<=UTC_TIMESTAMP()",[$id]);
            self::settleMissions($id);
            $db->execute("UPDATE world_invasions SET state='expired' WHERE world_id=? AND state='active' AND ends_at<=UTC_TIMESTAMP()",[$id]);
            }
        }finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }

    private static function schedule(int $worldId,array $s): void
    {
        self::ensureObjectives($worldId);
        $db=Connection::getInstance();if($db->query("SELECT id FROM conquest_events WHERE world_id=? AND state IN ('active','upcoming')",[$worldId])->fetchColumn())return;
        $start=strtotime($s['next_start'].' UTC');$duration=(int)$s['duration_hours']*3600;$interval=(int)$s['interval_hours']*3600;
        // An offline worker skips wholly missed cycles; no fabricated past participation.
        if($start+$duration<time())$start+=((int)floor((time()-$start-$duration)/$interval)+1)*$interval;
        $db->execute("INSERT INTO conquest_events(world_id,phase,starts_at,ends_at,state) VALUES(?,1,?,?,'upcoming')",[$worldId,gmdate('Y-m-d H:i:s',$start),gmdate('Y-m-d H:i:s',$start+$duration)]);
        $db->execute('UPDATE world_event_settings SET next_start=? WHERE world_id=?',[gmdate('Y-m-d H:i:s',$start+$interval),$worldId]);
    }

    private static function scheduleInvasion(int $worldId,array $s): void
    {
        $db=Connection::getInstance();if($db->query("SELECT id FROM world_invasions WHERE world_id=? AND state IN ('upcoming','active')",[$worldId])->fetchColumn())return;
        $start=strtotime($s['invasion_next_start'].' UTC');$interval=(int)$s['invasion_interval_hours']*3600;$duration=min(86400,$interval);
        if($start+$duration<time())$start+=((int)floor((time()-$start-$duration)/$interval)+1)*$interval;
        $chapter=max(1,(int)$db->query('SELECT chapter FROM world_chapters WHERE world_id=?',[$worldId])->fetchColumn());
        $db->execute('INSERT INTO world_invasions(world_id,chapter,target,starts_at,ends_at) VALUES(?,?,?,?,?)',[$worldId,$chapter,min(200000000,5000*$chapter),gmdate('Y-m-d H:i:s',$start),gmdate('Y-m-d H:i:s',$start+$duration)]);
        $db->execute('UPDATE world_event_settings SET invasion_next_start=? WHERE world_id=?',[gmdate('Y-m-d H:i:s',$start+$interval),$worldId]);
    }

    /** Independent competition objectives, placed only when this world enables Conquest. */
    private static function ensureObjectives(int $worldId): void
    {
        $db=Connection::getInstance();\Conquer\Game\Map\WorldPlacement::lockWorld($db,$worldId);
        foreach(['C'=>[48,48],'B'=>[204,48],'A'=>[48,204],'S'=>[204,204]]as$tier=>[$x,$y]){
            if($db->query("SELECT id FROM shrines WHERE world_id=? AND tier=? AND shrine_code NOT IN ('CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA') LIMIT 1",[$worldId,$tier])->fetchColumn())continue;
            $point=\Conquer\Game\Map\WorldPlacement::findNear($db,$worldId,'shrine',$x,$y,null,20);
            if(!$point)throw new \DomainException('Kein freier Platz für das Conquest-Ziel '.$tier.'.');
            $db->execute('INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y) VALUES(?,?,?,?,?)',[$worldId,'EVENT_'.$tier,$tier,$point[0],$point[1]]);
        }
    }

    public static function objectives(int $playerId,int $worldId): array
    {
        $ids=Connection::getInstance()->query("SELECT id FROM shrines WHERE world_id=? AND shrine_code NOT IN ('CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA') ORDER BY FIELD(tier,'C','B','A','S'),id",[$worldId])->fetchAll(\PDO::FETCH_COLUMN);
        return \Conquer\Game\World\WorldContext::run($worldId,static fn()=>array_map(static fn($id)=>\Conquer\Game\Shrine\CongressService::detail((int)$id,$playerId),$ids));
    }

    private static function score(array $event): void
    {
        $db=Connection::getInstance();$end=min(time(),strtotime($event['ends_at'].' UTC'));
        foreach($db->query("SELECT s.id,s.tier,c.alliance_id,c.secured_at FROM shrines s JOIN shrine_captures c ON c.shrine_id=s.id WHERE s.world_id=? AND s.shrine_code NOT IN ('CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA') AND c.secured_at IS NOT NULL AND c.alliance_id IS NOT NULL",[$event['world_id']])->fetchAll()as$s){
            $tier=['C'=>1,'B'=>2,'A'=>3,'S'=>4][$s['tier']]??4;if($tier>(int)$event['phase'])continue;
            $last=$db->query('SELECT MAX(minute_at) FROM conquest_score_ticks WHERE event_id=? AND shrine_id=?',[$event['id'],$s['id']])->fetchColumn();
            $unlock=strtotime($event['starts_at'].' UTC')+($tier-1)*(strtotime($event['ends_at'].' UTC')-strtotime($event['starts_at'].' UTC'))/4;
            $begin=max((int)$unlock,strtotime($s['secured_at'].' UTC'),$last?strtotime($last.' UTC'):0);$minutes=(int)floor(($end-$begin)/60);if($minutes<1)continue;$cutoff=gmdate('Y-m-d H:i:s',$begin+$minutes*60);$points=$minutes*$tier;
            $db->execute('INSERT INTO conquest_score_ticks(event_id,shrine_id,minute_at,alliance_id,points) VALUES(?,?,?,?,?)',[$event['id'],$s['id'],$cutoff,$s['alliance_id'],$points]);
            $garrisons=$db->query('SELECT player_id FROM shrine_garrisons WHERE shrine_id=? AND alliance_id=?',[$s['id'],$s['alliance_id']])->fetchAll(\PDO::FETCH_COLUMN);
            foreach($garrisons as$pid)ConquestService::addContribution((int)$event['id'],(int)$pid,(int)$s['alliance_id'],$points);
        }
    }

    /** Credit the outgoing owner before a battle changes the capture record. */
    public static function beforeCapture(array $shrine): void
    {
        if(in_array($shrine['shrine_code'],['CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA'],true))return;
        $event=Connection::getInstance()->query("SELECT * FROM conquest_events WHERE world_id=? AND state='active' FOR UPDATE",[$shrine['world_id']])->fetch();
        if($event)self::score($event);
    }

    public static function assertShrineOpen(array $shrine): void
    {
        if(in_array($shrine['shrine_code']??'', ['CONGRESS','SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA'],true))return;
        $event=ConquestService::getActiveEvent((int)$shrine['world_id']);$settings=self::settings((int)$shrine['world_id']);
        if(!$settings['enabled'])return;
        if(!$event)throw new \DomainException('Der nächste Schreinwettbewerb ist angekündigt. Angriffe sind während des Ereignisses möglich.');
        $tier=['C'=>1,'B'=>2,'A'=>3,'S'=>4][$shrine['shrine_tier']]??4;
        if($tier>(int)$event['phase'])throw new \DomainException('Diese Schreinstufe wird in einer späteren Ereignisphase freigeschaltet.');
    }

    public static function leaderboard(int $eventId): array
    {
        return Connection::getInstance()->query('SELECT a.id AS alliance_id,a.name,a.tag,SUM(s.points) AS points FROM conquest_score_ticks s JOIN alliances a ON a.id=s.alliance_id WHERE s.event_id=? GROUP BY a.id,a.name,a.tag ORDER BY points DESC,a.id LIMIT 100',[$eventId])->fetchAll();
    }

    public static function state(int $playerId,?int $worldId=null): array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        self::tick($worldId);$db=Connection::getInstance();$events=$db->query('SELECT * FROM conquest_events WHERE world_id=? ORDER BY starts_at DESC LIMIT 12',[$worldId])->fetchAll();
        foreach($events as&$e){$e['leaderboard']=self::leaderboard((int)$e['id']);$r=$db->query('SELECT result_json FROM conquest_results WHERE event_id=?',[$e['id']])->fetchColumn();$e['report']=$r?json_decode($r,true):null;$e['my_points']=(int)$db->query('SELECT points FROM conquest_contributions WHERE event_id=? AND player_id=?',[$e['id'],$playerId])->fetchColumn();$e['claimed']=(bool)$db->query('SELECT player_id FROM conquest_reward_claims WHERE event_id=? AND player_id=?',[$e['id'],$playerId])->fetchColumn();}unset($e);
        $invasions=$db->query('SELECT i.*,COALESCE(c.contribution,0) AS mine,COALESCE(c.claimed,0) AS claimed FROM world_invasions i LEFT JOIN invasion_contributions c ON c.invasion_id=i.id AND c.player_id=? WHERE i.world_id=? ORDER BY i.starts_at DESC LIMIT 10',[$playerId,$worldId])->fetchAll();
        $missions=$db->query("SELECT m.* FROM invasion_missions m JOIN cities c ON c.id=m.city_id WHERE m.player_id=? AND c.world_id=? AND m.state<>'returned' ORDER BY m.id DESC",[$playerId,$worldId])->fetchAll();
        return ['objectives'=>self::objectives($playerId,$worldId),'events'=>$events,'invasions'=>$invasions,'missions'=>$missions,'chapter'=>max(1,(int)$db->query('SELECT chapter FROM world_chapters WHERE world_id=?',[$worldId])->fetchColumn()),'settings'=>self::settings($worldId)];
    }

    public static function action(int $playerId,array $body,?int $worldId=null): array
    {
        $worldId??=\Conquer\Game\World\WorldContext::id();
        $id=filter_var($body['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new \DomainException('Ungültiges Ereignis.');
        $db=Connection::getInstance();$city=$db->query('SELECT * FROM cities WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetch();if(!$city)throw new \DomainException('Keine eigene Stadt in dieser Welt.');
        if($body['action']==='event.claim'){
            $e=$db->query("SELECT * FROM conquest_events WHERE id=? AND world_id=? AND state='ended'",[$id,$worldId])->fetch();$points=(int)$db->query('SELECT points FROM conquest_contributions WHERE event_id=? AND player_id=?',[$id,$playerId])->fetchColumn();
            if(!$e||$points<1||$db->query('SELECT player_id FROM conquest_reward_claims WHERE event_id=? AND player_id=?',[$id,$playerId])->fetchColumn())throw new \DomainException('Keine abholbare Ereignisbelohnung.');
            $amount=min(50000,1000+$points*10);$db->execute('INSERT INTO conquest_reward_claims(event_id,player_id,reward_json) VALUES(?,?,?)',[$id,$playerId,json_encode(['gold'=>$amount])]);$db->execute('UPDATE cities SET gold=gold+? WHERE id=?',[$amount,$city['id']]);return ['message'=>$amount.' Gold als Ereignisbelohnung erhalten.'];
        }
        $event=$db->query('SELECT * FROM world_invasions WHERE id=? AND world_id=? FOR UPDATE',[$id,$worldId])->fetch();if(!$event)throw new \DomainException('Invasion nicht gefunden.');
        if($body['action']==='invasion.claim'){
            $mine=$db->query('SELECT * FROM invasion_contributions WHERE invasion_id=? AND player_id=? FOR UPDATE',[$id,$playerId])->fetch();if($event['state']!=='victory'||!$mine||(int)$mine['contribution']<10||$mine['claimed'])throw new \DomainException('Keine abholbare Invasionsbeute.');
            $reward=min(50000,2000*(int)$event['chapter']);$db->execute('UPDATE invasion_contributions SET claimed=1 WHERE invasion_id=? AND player_id=?',[$id,$playerId]);$db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$reward,$reward,$reward,$reward,$mine['city_id']]);return ['message'=>'Invasionsbeute erhalten: je '.$reward.' Nahrung, Holz, Stein und Gold.'];
        }
        if($event['state']!=='active'||strtotime($event['ends_at'].' UTC')<=time())throw new \DomainException('Diese Invasion ist nicht aktiv.');
        if($body['action']==='invasion.supply'){$amount=filter_var($body['amount']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>10,'max_range'=>10000]]);if(!$amount||$amount%10)throw new \DomainException('10 bis 10.000 Nahrung in Zehnerschritten liefern.');$amount=min($amount,((int)$event['target']-(int)$event['contribution'])*10);if($db->execute('UPDATE cities SET food=food-? WHERE id=? AND food>=?',[$amount,$city['id'],$amount])!==1)throw new \DomainException('Nicht genügend Nahrung.');self::contribute($event,$playerId,(int)$city['id'],intdiv($amount,10));return ['message'=>$amount.' Nahrung für die Weltverteidigung geliefert.'];}
        if($body['action']==='invasion.dispatch'){
            if($db->query("SELECT id FROM invasion_missions WHERE player_id=? AND city_id=? AND state<>'returned'",[$playerId,$city['id']])->fetchColumn())throw new \DomainException('Deine Invasionsarmee ist bereits unterwegs.');
            if(strtotime($event['ends_at'].' UTC')<time()+20)throw new \DomainException('Das Zeitfenster reicht nicht für die Ankunft.');$buffs=BuffEngine::getBuffs($playerId,$worldId);$army=ExpeditionRules::troops($body['troops']??null,ExpeditionRules::missionCapacity($buffs));
            foreach($army['troops']as$code=>$count)if($db->execute('UPDATE city_troops SET count=count-? WHERE city_id=? AND troop_code=? AND count>=?',[$count,$city['id'],$code,$count])!==1)throw new \DomainException('Nicht genügend verfügbare Truppen.');
            $db->execute('INSERT INTO invasion_missions(invasion_id,player_id,city_id,troops_json,damage,arrival_at,return_at) VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 SECOND),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 40 SECOND))',[$id,$playerId,$city['id'],json_encode($army['troops']),ExpeditionRules::strength($army['troops'],'boss',$buffs)]);return ['message'=>'Armee entsandt. 20 Sekunden Hinweg und 20 Sekunden Rückweg; keine dauerhaften Verluste.'];
        }
        throw new \DomainException('Unbekannte Ereignisaktion.');
    }

    private static function contribute(array $event,int $playerId,int $cityId,int $amount): void
    {
        $db=Connection::getInstance();$amount=max(0,min($amount,(int)$event['target']-(int)$event['contribution']));if(!$amount)return;
        $db->execute('INSERT INTO invasion_contributions(invasion_id,player_id,city_id,contribution) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE contribution=contribution+VALUES(contribution)',[$event['id'],$playerId,$cityId,$amount]);
        $db->execute('UPDATE world_invasions SET contribution=contribution+? WHERE id=?',[$amount,$event['id']]);
        if((int)$event['contribution']+$amount>=(int)$event['target']){$db->execute("UPDATE world_invasions SET state='victory' WHERE id=?",[$event['id']]);$db->execute('INSERT INTO world_chapters(world_id,chapter) VALUES(?,?) ON DUPLICATE KEY UPDATE chapter=GREATEST(chapter,VALUES(chapter))',[$event['world_id'],(int)$event['chapter']+1]);}
    }

    private static function settleMissions(int $worldId): void
    {
        $db=Connection::getInstance();$rows=$db->query("SELECT m.id FROM invasion_missions m JOIN world_invasions i ON i.id=m.invasion_id WHERE i.world_id=? AND ((m.state='marching' AND m.arrival_at<=UTC_TIMESTAMP()) OR (m.state='returning' AND m.return_at<=UTC_TIMESTAMP())) ORDER BY m.arrival_at,m.id LIMIT 200",[$worldId])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($rows as$id)$db->transaction(function()use($db,$id):void{$m=$db->query('SELECT * FROM invasion_missions WHERE id=? FOR UPDATE',[$id])->fetch();$e=$db->query('SELECT * FROM world_invasions WHERE id=? FOR UPDATE',[$m['invasion_id']])->fetch();
            if($m['state']==='marching'){if($e['state']==='active'&&$m['arrival_at']<=$e['ends_at'])self::contribute($e,(int)$m['player_id'],(int)$m['city_id'],(int)$m['damage']);$db->execute("UPDATE invasion_missions SET state='returning' WHERE id=?",[$id]);$m['state']='returning';}
            if($m['state']==='returning'&&strtotime($m['return_at'].' UTC')<=time()){foreach(json_decode($m['troops_json'],true)as$code=>$count)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$m['city_id'],$code,$count]);$db->execute("UPDATE invasion_missions SET state='returned' WHERE id=?",[$id]);}
        });
    }
}
