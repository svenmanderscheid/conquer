<?php
declare(strict_types=1);
namespace Conquer\Game\Player;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\Defense\DefenseService;

final class MasteryService
{
    public static function catalog(): array
    {
        static $catalog;
        return $catalog??=json_decode(file_get_contents(ROOT_DIR.'/data/lord_talents.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public static function nodes(): array
    {
        $nodes=[];
        foreach(self::catalog()['branches'] as $branch) foreach($branch['nodes'] as $node) $nodes[$node['code']]=$node+['branch'=>$branch['code']];
        return $nodes;
    }

    private static function ranks(int $playerId,int $worldId): array
    {
        return array_map('intval',Connection::getInstance()->query('SELECT talent_code,rank FROM player_lord_talents WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    /** Validate the entire plan, including dependencies after removing a rank. */
    public static function validate(array $ranks,int $level): array
    {
        $nodes=self::nodes(); $clean=[];
        foreach($ranks as $code=>$rank) {
            if(!isset($nodes[$code])||!is_int($rank)||$rank<0||$rank>5) throw new \DomainException('Ungültiger Talentrang.');
            if($rank) $clean[$code]=$rank;
        }
        if(array_sum($clean)>min(60,$level)) throw new \DomainException('Nicht genügend Talentpunkte.');
        foreach($clean as $code=>$rank) {
            $n=$nodes[$code]; $earlier=0;
            foreach($clean as $other=>$points) if($nodes[$other]['branch']===$n['branch']&&$nodes[$other]['tier']<$n['tier']) $earlier+=$points;
            $parentOk=!$n['parents'];
            foreach($n['parents'] as $parent) if(($clean[$parent]??0)>=3) $parentOk=true;
            if($level<$n['required_level']||$earlier<$n['required_points']||!$parentOk) throw new \DomainException('Voraussetzungen für „'.$n['name'].'“ fehlen.');
        }
        ksort($clean);
        return $clean;
    }

    public static function blockedReason(int $playerId,int $worldId): ?string
    {
        $db=Connection::getInstance();
        if($db->query('SELECT m.run_id FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND r.world_id=? AND m.returned_at IS NULL LIMIT 1',[$playerId,$worldId])->fetchColumn())
            return 'Deine Dungeontruppen müssen vor dem Übernehmen der Talente zurückkehren.';
        if($db->query("SELECT id FROM marches WHERE player_id=? AND world_id=? AND state IN ('marching','resolving','returning','arrived') LIMIT 1",[$playerId,$worldId])->fetchColumn()
            ||$db->query("SELECT r.id FROM rallies r WHERE r.world_id=? AND r.status IN ('gathering','marching','returning') AND (r.leader_player_id=? OR EXISTS(SELECT 1 FROM rally_participants p WHERE p.rally_id=r.id AND p.player_id=? AND p.status IN ('pending','marching'))) LIMIT 1",[$worldId,$playerId,$playerId])->fetchColumn()
            ||$db->query("SELECT m.id FROM expedition_missions m JOIN cities c ON c.id=m.city_id WHERE m.player_id=? AND c.world_id=? AND m.status IN ('marching','returning') LIMIT 1",[$playerId,$worldId])->fetchColumn()
            ||$db->query("SELECT g.id FROM shrine_garrisons g JOIN cities c ON c.id=g.city_id WHERE g.player_id=? AND c.world_id=? AND g.troops_json NOT IN ('{}','[]') LIMIT 1",[$playerId,$worldId])->fetchColumn()
            ||$db->query("SELECT r.id FROM reinforcements r JOIN cities c ON c.id=r.sender_city_id WHERE r.sender_id=? AND c.world_id=? AND r.state='active' LIMIT 1",[$playerId,$worldId])->fetchColumn())
            return 'Deine Armeen müssen vor dem Übernehmen der Talente zurückkehren.';
        if($db->query("SELECT m.id FROM marches m JOIN cities c ON c.id=m.target_id WHERE c.player_id=? AND c.world_id=? AND m.world_id=? AND m.march_type=7 AND m.state IN ('marching','resolving') AND m.arrival_time<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) LIMIT 1",[$playerId,$worldId,$worldId])->fetchColumn()
            ||$db->query("SELECT id FROM rallies WHERE target_player_id=? AND world_id=? AND status IN ('gathering','marching') LIMIT 1",[$playerId,$worldId])->fetchColumn())
            return 'Während eines unmittelbar bevorstehenden Angriffs kannst du die Talente nicht wechseln.';
        return null;
    }

    public static function snapshot(int $playerId,?int $worldId=null): array
    {
        $worldId??=WorldContext::id(); WorldContext::city($playerId,$worldId);
        $lord=LordLevel::snapshot($playerId,$worldId); $ranks=self::ranks($playerId,$worldId);
        $row=Connection::getInstance()->query('SELECT revision,last_respec_at FROM player_lord_progress WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetch()?:[];
        $ready=empty($row['last_respec_at'])?0:strtotime($row['last_respec_at'].' UTC')+86400;
        return ['world_id'=>$worldId,'lord'=>$lord,'earned'=>$lord['level'],'spent'=>array_sum($ranks),'available'=>max(0,$lord['level']-array_sum($ranks)),
            'revision'=>(int)($row['revision']??0),'ranks'=>$ranks?:new \stdClass(),
            'branches'=>self::catalog()['branches'],'nodes'=>array_values(array_map(static fn($n)=>$n+['level'=>$ranks[$n['code']]??0],self::nodes())),
            'respec_available_at'=>$ready,'blocked_reason'=>self::blockedReason($playerId,$worldId),'server_time'=>time(),
            'rule'=>'Ein Talentpunkt je Lord-Level · maximal 60 Punkte. Umskillen einmal alle 24 Stunden kostenlos. Neue Punkte kannst du jederzeit bei zurückgekehrten Armeen vergeben.'];
    }

    public static function change(int $playerId,array $body,?int $worldId=null): array
    {
        $worldId??=WorldContext::id();
        return \Conquer\Game\WorldRules::combatLock(static fn()=>TalentEffects::atomic(static function(Connection $db)use($playerId,$body,$worldId):array{
            WorldContext::assertActionAvailable($worldId);
            $city=WorldContext::city($playerId,$worldId,true);
            LordLevel::ensure($playerId,$worldId);
            $row=$db->query('SELECT * FROM player_lord_progress WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetch();
            if(!is_int($body['revision']??null)||$body['revision']!==(int)$row['revision']) throw new \DomainException('Die Talentverteilung hat sich geändert. Bitte neu laden.');
            if(!is_array($body['ranks']??null)) throw new \DomainException('Talentplan fehlt.');
            $ranks=self::validate($body['ranks'],LordLevel::levelFromTotalXp((int)$row['xp']));
            $old=self::ranks($playerId,$worldId); ksort($old);
            if($ranks===$old) return ['message'=>'Diese Talente sind bereits aktiv.','mastery'=>self::snapshot($playerId,$worldId)];
            if($reason=self::blockedReason($playerId,$worldId)) throw new \DomainException($reason);
            $respec=false;
            foreach($old as $code=>$rank) if(($ranks[$code]??0)<$rank) $respec=true;
            if($respec&&!empty($row['last_respec_at'])&&strtotime($row['last_respec_at'].' UTC')+86400>time()) throw new \DomainException('Umskillen ist 24 Stunden nach dem letzten Wechsel wieder kostenlos möglich.');
            // Settle elapsed production, wall recovery and AP using the old talents.
            $buildings=[];
            foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$city['id']])->fetchAll()as$b)$buildings[$b['building_code']]=['level'=>(int)$b['level']];
            ResourceTick::persist($city,$buildings);
            DefenseService::syncWall((int)$city['id']);
            ActionPoints::get($playerId);
            $db->execute('DELETE FROM player_lord_talents WHERE player_id=? AND world_id=?',[$playerId,$worldId]);
            foreach($ranks as $code=>$rank)$db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,?,?,?)',[$playerId,$worldId,$code,$rank]);
            $db->execute('UPDATE player_lord_progress SET revision=revision+1,last_respec_at=IF(?,UTC_TIMESTAMP(),last_respec_at) WHERE player_id=? AND world_id=?',[$respec?1:0,$playerId,$worldId]);
            ActionPoints::get($playerId);
            return ['message'=>'Deine Talente sind jetzt aktiv.','mastery'=>self::snapshot($playerId,$worldId)];
        }));
    }

    public static function bonuses(int $playerId,?int $worldId=null): array
    {
        $worldId??=WorldContext::id(); $out=[]; $nodes=self::nodes();
        $ranks=self::ranks($playerId,$worldId);
        try {$ranks=self::validate($ranks,LordLevel::snapshot($playerId,$worldId)['level']);}
        catch(\DomainException) {return [];}
        foreach($ranks as $code=>$rank) {
            $n=$nodes[$code]; $out[$n['stat']]=($out[$n['stat']]??0)+$n['bonus']*$rank;
            if($code==='gather_8'&&$rank===5) $out['gather_march_slots']=1;
        }
        return $out;
    }
}
