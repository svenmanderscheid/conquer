<?php
declare(strict_types=1);
namespace Conquer\Game\Territory;
use Conquer\Db\Connection;
use Conquer\Game\WorldRules;

/** Each objective accrues elapsed seconds to its previous controller before changing hands. */
final class TerritoryCrown
{
    public static function ensureCycle(int $world,array $rules,array $window): array
    {
        $db=Connection::getInstance();$t=TerritoryService::target($world,'crown:krounbuerg');
        $db->execute('INSERT IGNORE INTO territory_crown_cycles(world_id,continent_id,starts_at,ends_at,rules_json)VALUES(?,?,?,?,?)',[$world,$t['continent_id'],TerritoryService::date($window['start']),TerritoryService::date($window['end']),json_encode($rules)]);
        $cycle=$db->query('SELECT * FROM territory_crown_cycles WHERE world_id=? AND starts_at=? FOR UPDATE',[$world,TerritoryService::date($window['start'])])->fetch();
        foreach(json_decode($cycle['rules_json'],true)['crown_objectives'] as $objective)$db->execute('INSERT IGNORE INTO territory_crown_control(cycle_id,objective,controlled_since)VALUES(?,?,?)',[$cycle['id'],$objective,$cycle['starts_at']]);
        return $cycle;
    }
    public static function control(int $cycleId,string $objective,int $alliance,int $at): void
    {
        $db=Connection::getInstance();$cycle=$db->query('SELECT * FROM territory_crown_cycles WHERE id=? FOR UPDATE',[$cycleId])->fetch();
        TerritoryService::require($cycle&&$cycle['status']==='open'&&$at>=TerritoryService::timestamp($cycle['starts_at'])&&$at<TerritoryService::timestamp($cycle['ends_at']),'Dieser Kronenkrieg ist beendet.');
        $row=$db->query('SELECT * FROM territory_crown_control WHERE cycle_id=? AND objective=? FOR UPDATE',[$cycleId,$objective])->fetch();TerritoryService::require((bool)$row,'Unbekanntes Kronenziel.');self::accrue($row,$at);
        $score=$db->query('SELECT * FROM territory_crown_scores WHERE cycle_id=? AND alliance_id=? FOR UPDATE',[$cycleId,$alliance])->fetch();$objectives=$score?json_decode($score['objectives_json'],true):[];
        if(!in_array($objective,$objectives,true))$objectives[]=$objective;
        $db->execute('INSERT INTO territory_crown_scores(cycle_id,alliance_id,objectives_json,first_control_at)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE objectives_json=VALUES(objectives_json)',[$cycleId,$alliance,json_encode($objectives),TerritoryService::date($at)]);
        $db->execute('UPDATE territory_crown_control SET alliance_id=?,controlled_since=? WHERE cycle_id=? AND objective=?',[$alliance,TerritoryService::date($at),$cycleId,$objective]);
    }
    private static function accrue(array $control,int $at): void
    {
        $seconds=max(0,$at-TerritoryService::timestamp($control['controlled_since']));
        if($control['alliance_id']&&$seconds)Connection::getInstance()->execute('UPDATE territory_crown_scores SET control_seconds=control_seconds+? WHERE cycle_id=? AND alliance_id=?',[$seconds,$control['cycle_id'],$control['alliance_id']]);
    }
    public static function tick(int $world,int $now,int $limit): void
    {
        $db=Connection::getInstance();
        foreach($db->query("SELECT * FROM territory_crown_cycles WHERE world_id=? AND status='open' AND ends_at<=? ORDER BY ends_at,id LIMIT ".max(1,min(100,$limit)).' FOR UPDATE',[$world,TerritoryService::date($now)])->fetchAll() as $cycle){
            // Offline arrival events must be settled before final scoring, even if the worker is late.
            $pending=$db->query("SELECT r.id FROM rallies r JOIN territory_campaigns c ON c.id=r.territory_campaign_id WHERE c.crown_cycle_id=? AND r.status IN ('gathering','marching') AND (r.status='gathering' OR r.arrival_time<?) LIMIT 1",[$cycle['id'],$cycle['ends_at']])->fetchColumn();if($pending)continue;
            $end=TerritoryService::timestamp($cycle['ends_at']);$rules=json_decode($cycle['rules_json'],true);
            foreach($db->query('SELECT * FROM territory_crown_control WHERE cycle_id=? FOR UPDATE',[$cycle['id']])->fetchAll() as $control){self::accrue($control,$end);$db->execute('UPDATE territory_crown_control SET controlled_since=? WHERE cycle_id=? AND objective=?',[$cycle['ends_at'],$cycle['id'],$control['objective']]);}
            $scores=$db->query('SELECT * FROM territory_crown_scores WHERE cycle_id=? ORDER BY control_seconds DESC,first_control_at ASC,alliance_id ASC',[$cycle['id']])->fetchAll();$winner=null;
            foreach($scores as $score)if(count(json_decode($score['objectives_json'],true))>=(int)$rules['crown_required_objectives']&&(int)$score['control_seconds']>0){$winner=(int)$score['alliance_id'];break;}
            if($winner){$t=TerritoryService::target($world,'crown:krounbuerg',true);TerritoryEconomy::transfer($t,$winner,$end,0,$rules,'crown-cycle:'.$cycle['id']);$db->execute('DELETE FROM territory_crown_offices WHERE world_id=?',[$world]);
                $participants=$db->query("SELECT DISTINCT rw.player_id FROM territory_campaigns c JOIN territory_rewards rw ON rw.world_id=c.world_id AND rw.event_key=CONCAT('campaign:',c.id) WHERE c.crown_cycle_id=? AND c.alliance_id=? AND c.status='won'",[$cycle['id'],$winner])->fetchAll(\PDO::FETCH_COLUMN);
                foreach($participants as $pid)TerritoryEconomy::reward($t,(int)$pid,$winner,'crown-cycle:'.$cycle['id'],['gold'=>$rules['conquest_reward_gold']*4],$end);
            }
            $db->execute("UPDATE territory_crown_cycles SET status='complete',winner_alliance_id=?,result_json=? WHERE id=?",[$winner,json_encode(['scores'=>$scores,'tie_rule'=>$rules['crown_tie_rule'],'required_objectives'=>$rules['crown_required_objectives']]),$cycle['id']]);
        }
    }
    public static function state(int $world): array
    {
        $db=Connection::getInstance();$cycle=$db->query('SELECT * FROM territory_crown_cycles WHERE world_id=? ORDER BY starts_at DESC LIMIT 1',[$world])->fetch()?:null;
        if($cycle){$cycle['rules']=json_decode($cycle['rules_json'],true);$cycle['result']=json_decode($cycle['result_json']??'null',true);unset($cycle['rules_json'],$cycle['result_json']);$cycle['objectives']=$db->query('SELECT c.*,a.name AS alliance_name FROM territory_crown_control c LEFT JOIN alliances a ON a.id=c.alliance_id WHERE c.cycle_id=?',[$cycle['id']])->fetchAll();$cycle['scores']=$db->query('SELECT s.*,a.name AS alliance_name FROM territory_crown_scores s JOIN alliances a ON a.id=s.alliance_id WHERE cycle_id=? ORDER BY control_seconds DESC,first_control_at ASC,alliance_id ASC',[$cycle['id']])->fetchAll();
            foreach($cycle['scores'] as &$score){$score['objectives']=json_decode($score['objectives_json'],true);unset($score['objectives_json']);$score['control_seconds']=(int)$score['control_seconds'];if($cycle['status']==='open')foreach($cycle['objectives'] as $o)if((int)$o['alliance_id']===(int)$score['alliance_id'])$score['control_seconds']+=max(0,min(time(),TerritoryService::timestamp($cycle['ends_at']))-TerritoryService::timestamp($o['controlled_since']));}unset($score);
        }
        $t=TerritoryService::target($world,'crown:krounbuerg');$profile=TerritoryService::profile($world);
        return ['cycle'=>$cycle,'owner_alliance_id'=>$t['owner_alliance_id'],'offices'=>$db->query('SELECT o.*,p.username AS player_name FROM territory_crown_offices o JOIN players p ON p.id=o.player_id WHERE o.world_id=? ORDER BY o.office',[$world])->fetchAll(),
            'members'=>$t['owner_alliance_id']?$db->query('SELECT m.player_id,p.username AS player_name,m.role FROM alliance_members m JOIN players p ON p.id=m.player_id WHERE m.world_id=? AND m.alliance_id=? ORDER BY p.username',[$world,$t['owner_alliance_id']])->fetchAll():[],
            'abilities'=>['marshal'=>['label'=>'Lazaretthilfe','effect'=>'heal_seconds','amount'=>$profile['office_acceleration_seconds']],'treasurer'=>['label'=>'Kronenzuschuss','effect'=>'alliance_resources','amount'=>$profile['office_resource_grant']],'builder'=>['label'=>'Bauleitung','effect'=>'building_seconds','amount'=>$profile['office_acceleration_seconds']],'mage'=>['label'=>'Forschungssegen','effect'=>'research_seconds','amount'=>$profile['office_acceleration_seconds']]]];
    }
    public static function appoint(int $player,int $world,array $body): array
    {
        $m=TerritoryService::mustMember($player,$world,true);TerritoryService::require(in_array($m['role'],['leader','vice_leader'],true),'Nur die Allianzführung vergibt Hofämter.',403);$t=TerritoryService::target($world,'crown:krounbuerg',true);
        TerritoryService::require((int)$t['owner_alliance_id']===(int)$m['alliance_id'],'Eure Allianz hält die Krone nicht.');$pid=TerritoryService::integer($body,'player_id');$target=TerritoryService::mustMember($pid,$world);TerritoryService::require((int)$target['alliance_id']===(int)$m['alliance_id'],'Das Amt muss an ein Mitglied eurer Allianz gehen.');
        $office=(string)($body['office']??'ruler');TerritoryService::require(in_array($office,['ruler','marshal','treasurer','builder','mage'],true),'Unbekanntes Hofamt.');$title=$office==='ruler'?(string)($body['title']??'grand_duc'):$office;TerritoryService::require($office!=='ruler'||in_array($title,['grand_duc','grande_duchesse'],true),'Ungültiger Herrschertitel.');
        $db=Connection::getInstance();$cycle=(int)$db->query("SELECT id FROM territory_crown_cycles WHERE world_id=? AND winner_alliance_id=? AND status='complete' ORDER BY ends_at DESC LIMIT 1",[$world,$m['alliance_id']])->fetchColumn();
        $db->execute('INSERT INTO territory_crown_offices(world_id,office,cycle_id,alliance_id,player_id,title,appointed_at)VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE cycle_id=VALUES(cycle_id),alliance_id=VALUES(alliance_id),player_id=VALUES(player_id),title=VALUES(title),appointed_at=VALUES(appointed_at)',[$world,$office,$cycle,$m['alliance_id'],$pid,$title]);
        return ['message'=>'Das Hofamt wurde vergeben.','office'=>$office,'player_id'=>$pid];
    }
    public static function ability(int $player,int $world,array $body): array
    {
        $m=TerritoryService::mustMember($player,$world);$office=(string)($body['office']??'');$db=Connection::getInstance();$t=TerritoryService::target($world,'crown:krounbuerg',true);$rules=TerritoryService::profile($world);
        $row=$db->query('SELECT * FROM territory_crown_offices WHERE world_id=? AND office=? AND player_id=? FOR UPDATE',[$world,$office,$player])->fetch();TerritoryService::require($row&&(int)$row['alliance_id']===(int)$m['alliance_id']&&(int)$t['owner_alliance_id']===(int)$m['alliance_id'],'Du bekleidest dieses Hofamt nicht.',403);
        TerritoryService::require(in_array($office,['marshal','treasurer','builder','mage'],true),'Dieses Amt hat keine aktive Fähigkeit.');
        TerritoryEconomy::consume($world,(int)$m['alliance_id'],'office:'.$office,(int)$rules['office_daily_uses']);$seconds=(int)$rules['office_acceleration_seconds'];
        if($office==='treasurer')TerritoryEconomy::treasury((int)$m['alliance_id'],array_fill_keys(['food','lumber','stone','gold'],(int)$rules['office_resource_grant']));
        else{
            $city=WorldRules::origin($player,TerritoryService::integer($body,'city_id'),$world);
            if($office==='marshal')$count=$db->execute('UPDATE hospital_wounded SET healing_ends_at=GREATEST(UTC_TIMESTAMP(),DATE_SUB(healing_ends_at,INTERVAL ? SECOND)) WHERE city_id=? AND healing_count>0 AND healing_ends_at>UTC_TIMESTAMP()',[$seconds,$city['id']]);
            elseif($office==='builder')$count=$db->execute('UPDATE building_queue SET finishes_at=GREATEST(UTC_TIMESTAMP(),DATE_SUB(finishes_at,INTERVAL ? SECOND)) WHERE city_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() ORDER BY id LIMIT 1',[$seconds,$city['id']]);
            else $count=$db->execute('UPDATE research_queue SET finishes_at=GREATEST(UTC_TIMESTAMP(),DATE_SUB(finishes_at,INTERVAL ? SECOND)) WHERE player_id=? AND world_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() ORDER BY id LIMIT 1',[$seconds,$player,$world]);
            TerritoryService::require($count>0,'Für diese Fähigkeit läuft kein passender Auftrag.');
        }
        return ['message'=>'Die begrenzte Amtsfähigkeit wurde angewendet.','office'=>$office];
    }
}
