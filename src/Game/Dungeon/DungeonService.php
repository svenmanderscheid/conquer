<?php
declare(strict_types=1);
namespace Conquer\Game\Dungeon;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Treasure\TreasureService;
use Conquer\Game\World\WorldContext;

final class DungeonService
{
    private const ROLES=['attack','defense','gather','hunter'];
    private const ACTIVE=['recruiting','running','decision'];

    public static function state(int $playerId): array
    {
        return self::runLocked('conquer-player-'.$playerId,function()use($playerId){self::tick($playerId);return self::snapshot($playerId);});
    }

    public static function action(int $playerId,array $body): array
    {
        return self::runLocked('conquer-player-'.$playerId,fn()=>self::actionLocked($playerId,$body));
    }

    private static function actionLocked(int $playerId,array $body): array
    {
        try { WorldContext::current($body['expected_world_id']??null); WorldContext::assertActionAvailable(); }
        catch(\DomainException $e){throw new DungeonException('WORLD_RULE',$e->getMessage(),$e->getCode()?:422);}
        $action=$body['action']??null;
        if(!is_string($action)||!in_array($action,['create','join','leave','start','cancel','vote','claim','preview'],true))throw new DungeonException('INVALID_ACTION','Diese Dungeon-Aktion ist unbekannt.');
        if($action==='preview')return self::previewArmy($playerId,$body);
        self::tick($playerId);
        $db=Connection::getInstance();
        $runId=null;
        if($action==='create'){$created=self::runLocked('conquer-dungeon-create-'.WorldContext::id(),fn()=> $db->transaction(fn()=>self::create($playerId,$body)));$message=$created['message'];$runId=$created['run_id'];}
        else {
            $id=self::id($body['run_id']??null);
            $runId=$id;
            $message=self::runLocked('conquer-dungeon-'.$id,fn()=> $db->transaction(function()use($db,$id,$playerId,$body,$action){
                $run=$db->query('SELECT * FROM dungeon_runs WHERE id=? AND world_id=? FOR UPDATE',[$id,WorldContext::id()])->fetch();
                if(!$run)throw new DungeonException('NOT_FOUND','Diese Dungeon-Gruppe wurde nicht gefunden.',404);
                return match($action){
                    'join'=>self::join($run,$playerId,$body), 'leave'=>self::leave($run,$playerId),
                    'start'=>self::start($run,$playerId), 'cancel'=>self::cancel($run,$playerId),
                    'vote'=>self::vote($run,$playerId,$body), 'claim'=>self::claim($run,$playerId)
                };
            }));
        }
        return ['run_id'=>$runId,'message'=>$message,'state'=>self::snapshot($playerId)];
    }

    public static function tick(?int $playerId=null): void
    {
        $db=Connection::getInstance();
        $ids=$db->query("SELECT id FROM dungeon_runs WHERE (status='running' AND ((decision_at IS NOT NULL AND decision_at<=UTC_TIMESTAMP()) OR (finishes_at IS NOT NULL AND finishes_at<=UTC_TIMESTAMP()))) OR (status='decision' AND decision_deadline<=UTC_TIMESTAMP()) OR (status='recruiting' AND week_key<>?) ORDER BY id LIMIT 100",[self::weekKey()])->fetchAll(\PDO::FETCH_COLUMN);
        foreach($ids as$id)self::runLocked('conquer-dungeon-'.(int)$id,function()use($db,$id){$db->transaction(function()use($db,$id){
            for($step=0;$step<4;$step++){$run=$db->query('SELECT * FROM dungeon_runs WHERE id=? FOR UPDATE',[(int)$id])->fetch();if(!$run)return;
                if($run['status']==='recruiting'&&$run['week_key']!==self::weekKey()){$db->execute("UPDATE dungeon_runs SET status='cancelled',completed_at=UTC_TIMESTAMP() WHERE id=?",[$run['id']]);continue;}
                if($run['status']==='running'&&$run['decision_at']!==null&&$run['decision_at']<=gmdate('Y-m-d H:i:s')){$db->execute("UPDATE dungeon_runs SET status='decision' WHERE id=? AND status='running'",[(int)$id]);continue;}
                if($run['status']==='decision'&&$run['decision_deadline']<=gmdate('Y-m-d H:i:s')){self::resolveDecision($run);continue;}
                if($run['status']==='running'&&$run['finishes_at']!==null&&$run['finishes_at']<=gmdate('Y-m-d H:i:s')){self::complete($run);continue;}break;
            }
        });});
        self::returnTroops($playerId);
    }

    private static function create(int $playerId,array $body): array
    {
        self::assertNoActive($playerId);$defs=self::rotation();$code=$body['dungeon_code']??'';$def=null;
        foreach($defs as$d)if(($d['code']??$d['dungeon_code']??null)===$code)$def=$d;
        if(!$def)throw new DungeonException('DUNGEON_UNAVAILABLE','Dieser Dungeon ist diese Woche nicht verfügbar.');
        $difficulty=self::enum($body['difficulty']??'normal',['normal','hard'],'Schwierigkeit');$stance=self::enum($body['stance']??'balanced',['cautious','balanced','risky'],'Haltung');
        [$city,$role,$troops,$stats]=self::prepareMember($playerId,$body);
        $db=Connection::getInstance();$seed=random_int(1,2147483647);
        $db->execute("INSERT INTO dungeon_runs(world_id,dungeon_code,week_key,difficulty,stance,leader_player_id,seed,definition_json) VALUES(?,?,?,?,?,?,?,?)",[WorldContext::id(),$code,self::weekKey(),$difficulty,$stance,$playerId,$seed,self::json($def)]);
        $id=$db->lastInsertId();self::reserve($city,$troops);
        $db->execute('INSERT INTO dungeon_members(run_id,player_id,city_id,role,troops_json,stats_json) VALUES(?,?,?,?,?,?)',[$id,$playerId,$city,$role,self::json($troops),self::json($stats)]);
        return ['run_id'=>$id,'message'=>'Dungeon-Gruppe erstellt. Bis zu drei weitere Spieler können beitreten.'];
    }

    private static function join(array $run,int $playerId,array $body): string
    {
        if($run['status']!=='recruiting')throw new DungeonException('RUN_STARTED','Diese Gruppe ist bereits aufgebrochen.');self::assertNoActive($playerId);
        $db=Connection::getInstance();if((int)$db->query('SELECT COUNT(*) FROM dungeon_members WHERE run_id=? FOR UPDATE',[$run['id']])->fetchColumn()>=4)throw new DungeonException('PARTY_FULL','Diese Gruppe ist voll.');
        [$city,$role,$troops,$stats]=self::prepareMember($playerId,$body);self::reserve($city,$troops);
        $db->execute('INSERT INTO dungeon_members(run_id,player_id,city_id,role,troops_json,stats_json) VALUES(?,?,?,?,?,?)',[$run['id'],$playerId,$city,$role,self::json($troops),self::json($stats)]);
        return 'Du bist der Dungeon-Gruppe beigetreten.';
    }

    private static function leave(array $run,int $playerId): string
    {
        if($run['status']!=='recruiting')throw new DungeonException('RUN_STARTED','Nach dem Start kann niemand die Gruppe verlassen.');
        if((int)$run['leader_player_id']===$playerId)throw new DungeonException('LEADER_MUST_CANCEL','Der Gruppenleiter muss die Gruppe auflösen.');
        if(!self::member($run,$playerId,true))throw new DungeonException('NOT_MEMBER','Du gehörst nicht zu dieser Gruppe.',403);
        self::restoreMember((int)$run['id'],$playerId);Connection::getInstance()->execute('DELETE FROM dungeon_members WHERE run_id=? AND player_id=?',[$run['id'],$playerId]);return 'Du hast die Gruppe verlassen. Deine Truppen sind zurück.';
    }

    private static function cancel(array $run,int $playerId): string
    {
        if((int)$run['leader_player_id']!==$playerId)throw new DungeonException('NOT_LEADER','Nur der Gruppenleiter kann auflösen.',403);
        if($run['status']!=='recruiting')throw new DungeonException('RUN_STARTED','Ein laufender Dungeon kann nicht abgebrochen werden.');
        Connection::getInstance()->execute("UPDATE dungeon_runs SET status='cancelled',completed_at=UTC_TIMESTAMP() WHERE id=?",[$run['id']]);self::restoreMember((int)$run['id'],$playerId);return 'Gruppe aufgelöst. Deine Truppen sind zurück; die übrigen kehren bei der nächsten Aktualisierung zurück.';
    }

    private static function start(array $run,int $playerId): string
    {
        if((int)$run['leader_player_id']!==$playerId)throw new DungeonException('NOT_LEADER','Nur der Gruppenleiter kann starten.',403);
        if($run['status']!=='recruiting')throw new DungeonException('RUN_STARTED','Dieser Dungeon wurde bereits gestartet.');
        $party=self::party((int)$run['id']);
        try{DungeonRules::validateParty($party);}catch(\InvalidArgumentException $e){throw new DungeonException('INVALID_PARTY',$e->getMessage());}
        $def=self::runDefinition($run);$sim=DungeonRules::simulate($def,$party,(string)$run['difficulty'],(string)$run['stance'],(int)$run['seed'],null);
        $seconds=max(30,(int)($sim['duration_seconds']??300));$decision=max(15,intdiv($seconds,2));$db=Connection::getInstance();
        if(!empty($sim['decision_required'])){$window=max(60,(int)($sim['decision_seconds']??1800));$db->execute("UPDATE dungeon_runs SET status='running',started_at=UTC_TIMESTAMP(),decision_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),decision_deadline=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),simulation_json=? WHERE id=?",[$decision,$decision+$window,self::json($sim),$run['id']]);}
        else $db->execute("UPDATE dungeon_runs SET status='running',started_at=UTC_TIMESTAMP(),finishes_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),simulation_json=? WHERE id=?",[$seconds,self::json($sim),$run['id']]);
        return 'Die Gruppe ist in den Dungeon aufgebrochen.';
    }

    private static function vote(array $run,int $playerId,array $body): string
    {
        if($run['status']!=='decision')throw new DungeonException('NO_DECISION','Derzeit steht keine Entscheidung offen.');self::member($run,$playerId,true);
        $choice=self::enum($body['choice']??'', ['explore','skip'],'Entscheidung');$db=Connection::getInstance();
        $db->execute('INSERT INTO dungeon_votes(run_id,player_id,choice) VALUES(?,?,?) ON DUPLICATE KEY UPDATE choice=VALUES(choice),voted_at=UTC_TIMESTAMP()',[$run['id'],$playerId,$choice]);
        $count=(int)$db->query('SELECT COUNT(*) FROM dungeon_votes WHERE run_id=?',[$run['id']])->fetchColumn();$members=(int)$db->query('SELECT COUNT(*) FROM dungeon_members WHERE run_id=?',[$run['id']])->fetchColumn();
        if($count>=$members)self::resolveDecision($run);return 'Deine Entscheidung wurde gespeichert.';
    }

    private static function resolveDecision(array $run): void
    {
        $db=Connection::getInstance();$votes=$db->query('SELECT player_id,choice FROM dungeon_votes WHERE run_id=? ORDER BY player_id',[$run['id']])->fetchAll();
        $choices=array_column($votes,'choice');$deadline=strtotime((string)$run['decision_deadline'].' UTC');
        $choice=DungeonRules::resolveSideChoice($choices,(string)$run['stance'],$deadline,time());
        if($choice===null)return;$party=self::party((int)$run['id']);$sim=DungeonRules::simulate(self::runDefinition($run),$party,(string)$run['difficulty'],(string)$run['stance'],(int)$run['seed'],$choice);
        $seconds=max(30,(int)($sim['duration_seconds']??300));$elapsed=max(0,strtotime((string)$run['decision_at'].' UTC')-strtotime((string)$run['started_at'].' UTC'));$remaining=max(30,$seconds-$elapsed);$finish=gmdate('Y-m-d H:i:s',$deadline+$remaining);$db->execute("UPDATE dungeon_runs SET status='running',choice=?,decision_at=NULL,finishes_at=?,simulation_json=? WHERE id=?",[$choice,$finish,self::json($sim),$run['id']]);
    }

    private static function complete(array $run): void
    {
        $db=Connection::getInstance();$sim=json_decode((string)$run['simulation_json'],true)?:[];$status=!empty($sim['success'])?'completed':'failed';
        $db->execute("UPDATE dungeon_runs SET status=?,completed_at=UTC_TIMESTAMP() WHERE id=? AND status='running'",[$status,$run['id']]);
        foreach(self::party((int)$run['id'])as$m){$reward=self::reward($sim,$m,$status==='completed');$db->execute('INSERT IGNORE INTO dungeon_rewards(run_id,player_id,reward_json) VALUES(?,?,?)',[$run['id'],$m['player_id'],self::json($reward)]);}
    }

    private static function claim(array $run,int $playerId): string
    {
        if(!in_array($run['status'],['completed','failed'],true))throw new DungeonException('NOT_COMPLETE','Dieser Dungeon ist noch nicht abgeschlossen.');self::member($run,$playerId,true);
        $db=Connection::getInstance();$row=$db->query('SELECT * FROM dungeon_rewards WHERE run_id=? AND player_id=? FOR UPDATE',[$run['id'],$playerId])->fetch();
        if(!$row)throw new DungeonException('NO_REWARD','Für diesen Lauf liegt keine Belohnung vor.');if($row['claimed_at']!==null)throw new DungeonException('ALREADY_CLAIMED','Diese Belohnung wurde bereits abgeholt.',409);
        $reward=json_decode($row['reward_json'],true)?:[];$awarded=[];
        if(($reward['fragments']??0)>0&&($reward['treasure_code']??0)>0)$awarded['treasure']=TreasureService::addFragments($playerId,(int)$reward['treasure_code'],(int)$reward['fragments']);
        if(($reward['item_code']??0)>0&&($reward['item_quantity']??0)>0){InventoryService::addItems($playerId,(int)$reward['item_code'],(int)$reward['item_quantity']);$awarded['item_code']=(int)$reward['item_code'];}
        $reward['awarded']=$awarded;$db->execute('UPDATE dungeon_rewards SET reward_json=?,claimed_at=UTC_TIMESTAMP() WHERE run_id=? AND player_id=? AND claimed_at IS NULL',[self::json($reward),$run['id'],$playerId]);return 'Dungeon-Belohnung abgeholt.';
    }

    private static function prepareMember(int $playerId,array $body): array
    {
        $role=self::enum($body['role']??'',self::ROLES,'Rolle');$eligible=self::eligibleRoles($playerId);if(!in_array($role,$eligible,true))throw new DungeonException('ROLE_NOT_SPECIALIZED','Diese Rolle passt nicht zu deinen verteilten Lord-Talenten.');
        $city=WorldContext::city($playerId,WorldContext::id(),true);$troops=self::troops($body['troops']??null);$buffs=BuffEngine::getBuffs($playerId,WorldContext::id());$attack=$defense=$hp=$carry=$speed=0.0;$typeStats=[];
        foreach($troops as$code=>$count){$t=TroopData::get((int)$code);if(!$t)throw new DungeonException('INVALID_TROOPS','Die Armee enthält unbekannte Truppen.');$typeCode=(string)(int)$t['type'];$type=[1=>'infantry',2=>'ranged',3=>'cavalry'][(int)$t['type']]??null;$a=(int)$t['attack']*$count*BuffEngine::effectiveMultiplier($buffs,$type,'atk');$de=(int)$t['defense']*$count*BuffEngine::effectiveMultiplier($buffs,$type,'def');$h=(int)$t['hp']*$count*BuffEngine::effectiveMultiplier($buffs,$type,'hp');$attack+=$a;$defense+=$de;$hp+=$h;$carry+=(int)$t['carry']*$count;$speed+=(int)$t['speed']*$count;$typeStats[$typeCode]['attack']=($typeStats[$typeCode]['attack']??0)+$a;$typeStats[$typeCode]['defense']=($typeStats[$typeCode]['defense']??0)+$de;$typeStats[$typeCode]['hp']=($typeStats[$typeCode]['hp']??0)+$h;}
        $ranks=self::roleRanks($playerId);$pve=1+max(0,(float)($buffs['vs_monster_attack']??0));$attack*=$pve;foreach($typeStats as&$s)$s['attack']*=$pve;unset($s);$roleEffects=['attack_pct'=>0.0,'defense_pct'=>0.0,'hp_pct'=>0.0];
        if($role==='attack'){$roleEffects['attack_pct']=min(.30,.02*$ranks[$role]);$attack*=1+$roleEffects['attack_pct'];foreach($typeStats as&$s)$s['attack']*=1+$roleEffects['attack_pct'];unset($s);}
        if($role==='defense'){$roleEffects['defense_pct']=min(.30,.02*$ranks[$role]);$roleEffects['hp_pct']=min(.15,.01*$ranks[$role]);$defense*=1+$roleEffects['defense_pct'];$hp*=1+$roleEffects['hp_pct'];foreach($typeStats as&$s){$s['defense']*=1+$roleEffects['defense_pct'];$s['hp']*=1+$roleEffects['hp_pct'];}unset($s);}
        foreach($typeStats as&$s)foreach(['attack','defense','hp']as$key)$s[$key]=round((float)$s[$key],6);unset($s);
        $stats=['player_id'=>$playerId,'role'=>$role,'specialty_points'=>$ranks[$role],'role_effects'=>$roleEffects,'troops'=>$troops,'total_troops'=>array_sum($troops),'attack'=>(int)round($attack),'defense'=>(int)round($defense),'hp'=>(int)round($hp),'type_stats'=>$typeStats,'gather_bonus'=>$role==='gather'?round(.05+.01*$ranks['gather'],3):0,'hunter_bonus'=>$role==='hunter'?round(.05+.01*$ranks['hunter'],3):0,'speed'=>(int)round($speed/max(1,array_sum($troops))),'carry'=>(int)$carry];
        return [(int)$city['id'],$role,$troops,$stats];
    }

    private static function previewArmy(int$playerId,array$body): array
    {
        [$city,$role,$troops,$stats]=self::prepareMember($playerId,$body);$db=Connection::getInstance();$runId=$body['run_id']??null;$party=[];$ownReserved=[];
        if($runId!==null){$id=self::id($runId);$run=$db->query('SELECT * FROM dungeon_runs WHERE id=? AND world_id=?',[$id,WorldContext::id()])->fetch();if(!$run)throw new DungeonException('NOT_FOUND','Diese Dungeon-Gruppe wurde nicht gefunden.',404);if($run['status']!=='recruiting')throw new DungeonException('RUN_STARTED','Diese Gruppe ist bereits aufgebrochen.');
            $difficulty=self::enum($body['difficulty']??'', ['normal','hard'],'Schwierigkeit');$stance=self::enum($body['stance']??'',DungeonRules::STANCES,'Haltung');if(($body['dungeon_code']??'')!==$run['dungeon_code']||$difficulty!==$run['difficulty']||$stance!==$run['stance'])throw new DungeonException('PREVIEW_STALE','Die Gruppenplanung ist nicht mehr aktuell.',409);
            $party=self::party($id);$kept=[];$alreadyMember=false;foreach($party as$m){if((int)$m['player_id']===$playerId){$alreadyMember=true;$ownReserved=$m['troops']??[];continue;}$kept[]=$m;}$party=$kept;if(!$alreadyMember&&count($party)>=4)throw new DungeonException('PARTY_FULL','Diese Gruppe ist voll.');if(!$alreadyMember&&$db->query("SELECT 1 FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND r.world_id=? AND r.status IN ('recruiting','running','decision') LIMIT 1",[$playerId,WorldContext::id()])->fetchColumn())throw new DungeonException('ALREADY_ACTIVE','Du gehörst bereits zu einer aktiven Dungeon-Gruppe.');$definition=self::runDefinition($run);
        }else{$code=$body['dungeon_code']??'';$definition=null;foreach(self::rotation()as$d)if(($d['dungeon_code']??null)===$code)$definition=$d;if(!$definition)throw new DungeonException('DUNGEON_UNAVAILABLE','Dieser Dungeon ist diese Woche nicht verfügbar.');$difficulty=self::enum($body['difficulty']??'normal',['normal','hard'],'Schwierigkeit');$stance=self::enum($body['stance']??'balanced',DungeonRules::STANCES,'Haltung');$run=['difficulty'=>$difficulty,'stance'=>$stance,'dungeon_code'=>$code,'definition_json'=>self::json($definition)];if($db->query("SELECT 1 FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND r.world_id=? AND r.status IN ('recruiting','running','decision') LIMIT 1",[$playerId,WorldContext::id()])->fetchColumn())throw new DungeonException('ALREADY_ACTIVE','Du gehörst bereits zu einer aktiven Dungeon-Gruppe.');}
        foreach($troops as$code=>$count){$available=(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$city,(int)$code])->fetchColumn()+(int)($ownReserved[(string)$code]??0);if($count>$available)throw new DungeonException('INSUFFICIENT_TROOPS','Nicht genügend freie Truppen.');}
        $party[]=$stats;return ['forecast'=>self::forecast($run,$party),'guidance'=>DungeonRules::guidance($definition)];
    }

    private static function troops(mixed $input): array
    {if(!is_array($input)||$input===[]||array_is_list($input))throw new DungeonException('INVALID_TROOPS','Wähle mindestens zehn Truppen.');$out=[];$sum=0;foreach($input as$code=>$count){if(!ctype_digit((string)$code)||!is_int($count)||$count<1)throw new DungeonException('INVALID_TROOPS','Ungültige Truppenmenge.');$out[(string)(int)$code]=$count;$sum+=$count;}if($sum<10)throw new DungeonException('ARMY_TOO_SMALL','Mindestens zehn Truppen müssen teilnehmen.');if($sum>50000)throw new DungeonException('ARMY_TOO_LARGE','Maximal 50.000 Truppen pro Spieler.');ksort($out);return$out;}
    private static function reserve(int $city,array $troops): void {foreach($troops as$code=>$count)if(Connection::getInstance()->execute('UPDATE city_troops SET count=count-? WHERE city_id=? AND troop_code=? AND count>=?',[$count,$city,(int)$code,$count])!==1)throw new DungeonException('INSUFFICIENT_TROOPS','Nicht genügend freie Truppen.');}
    private static function restoreMember(int $runId,?int $playerId): void {$db=Connection::getInstance();$sql='SELECT * FROM dungeon_members WHERE run_id=? AND returned_at IS NULL'.($playerId===null?'':' AND player_id=?').' FOR UPDATE';$params=$playerId===null?[$runId]:[$runId,$playerId];foreach($db->query($sql,$params)->fetchAll()as$m){foreach(json_decode($m['troops_json'],true)?:[]as$code=>$count)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$m['city_id'],(int)$code,(int)$count]);$db->execute('UPDATE dungeon_members SET returned_at=UTC_TIMESTAMP() WHERE run_id=? AND player_id=? AND returned_at IS NULL',[$runId,$m['player_id']]);}}
    private static function returnTroops(?int $playerId): void {$db=Connection::getInstance();$players=$playerId===null?$db->query("SELECT DISTINCT m.player_id FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.returned_at IS NULL AND r.status IN ('completed','failed','cancelled')")->fetchAll(\PDO::FETCH_COLUMN):[$playerId];foreach($players as$pid)self::runLocked('conquer-player-'.(int)$pid,function()use($db,$pid){$db->transaction(function()use($db,$pid){$runs=$db->query("SELECT m.run_id FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND m.returned_at IS NULL AND r.status IN ('completed','failed','cancelled') FOR UPDATE",[(int)$pid])->fetchAll(\PDO::FETCH_COLUMN);foreach($runs as$id)self::restoreMember((int)$id,(int)$pid);});});}

    private static function snapshot(int $playerId): array
    {$db=Connection::getInstance();$world=WorldContext::id();$city=WorldContext::city($playerId);$available=[];foreach($db->query('SELECT troop_code,count FROM city_troops WHERE city_id=? AND count>0 ORDER BY troop_code',[$city['id']])->fetchAll()as$r)$available[(string)$r['troop_code']]=(int)$r['count'];
        $sets=[];$sets[]=$db->query("SELECT DISTINCT r.* FROM dungeon_runs r JOIN dungeon_members m ON m.run_id=r.id LEFT JOIN dungeon_rewards rw ON rw.run_id=r.id AND rw.player_id=m.player_id WHERE r.world_id=? AND m.player_id=? AND (r.status IN ('recruiting','running','decision') OR (r.status IN ('completed','failed') AND rw.claimed_at IS NULL)) ORDER BY r.id DESC",[$world,$playerId])->fetchAll();$sets[]=$db->query("SELECT DISTINCT r.* FROM dungeon_runs r JOIN dungeon_members m ON m.run_id=r.id WHERE r.world_id=? AND m.player_id=? ORDER BY r.id DESC LIMIT 25",[$world,$playerId])->fetchAll();$sets[]=$db->query("SELECT * FROM dungeon_runs WHERE world_id=? AND status='recruiting' ORDER BY id DESC LIMIT 50",[$world])->fetchAll();$byId=[];foreach($sets as$rows)foreach($rows as$r)$byId[(int)$r['id']]=$r;krsort($byId);$runs=[];foreach($byId as$r)$runs[]=self::describe($r,$playerId);$rotation=DungeonRules::weeklyRotation();return ['server_time'=>time(),'world_id'=>$world,'player_id'=>$playerId,'week_key'=>self::weekKey(),'rotation_starts_at'=>$rotation['week_start'],'rotation_ends_at'=>$rotation['week_end'],'rotation'=>array_map(self::publicDefinition(...),$rotation['available']),'next_rotation'=>array_map(self::publicDefinition(...),DungeonRules::preview()['available']),'available_troops'=>$available,'eligible_roles'=>self::eligibleRoles($playerId),'runs'=>$runs];}
    private static function describe(array $r,int $viewer): array {$party=self::party((int)$r['id']);foreach($party as&$m)$m['is_self']=(int)$m['player_id']===$viewer;unset($m);$votes=Connection::getInstance()->query('SELECT player_id,choice,voted_at FROM dungeon_votes WHERE run_id=? ORDER BY voted_at',[$r['id']])->fetchAll();$sim=json_decode($r['simulation_json']??'null',true);$terminal=in_array($r['status'],['completed','failed'],true);$visibleBattle=$terminal||$r['status']==='decision'||$r['choice']!==null;$visibleRounds=$visibleBattle?($sim['rounds']??[]):[];if(!$terminal&&$r['choice']!==null){$prefix=[];foreach($visibleRounds as$round){$prefix[]=$round;if($round['enemy_hp']<=0||$round['party_hp']<=0)break;}$visibleRounds=$prefix;}$reward=Connection::getInstance()->query('SELECT reward_json,claimed_at FROM dungeon_rewards WHERE run_id=? AND player_id=?',[$r['id'],$viewer])->fetch();if($reward){$reward=json_decode($reward['reward_json'],true)+['claimed_at'=>$reward['claimed_at']];$treasure=\Conquer\Game\Treasure\TreasureData::get((int)($reward['treasure_code']??0));$item=InventoryService::getItemDef((int)($reward['item_code']??0));$reward['treasure_name']=$treasure['name_de']??$treasure['name']??'Relikt';$reward['item_name']=$item['name_de']??$item['name']??'Gegenstand';}$mine=false;foreach($party as$m)if((int)$m['player_id']===$viewer)$mine=true;$armyForecast=$r['status']==='recruiting'?self::forecast($r,$party):null;return ['id'=>(int)$r['id'],'dungeon_code'=>$r['dungeon_code'],'dungeon'=>self::publicDefinition(self::runDefinition($r)),'difficulty'=>$r['difficulty'],'stance'=>$r['stance'],'status'=>$r['status'],'leader_player_id'=>(int)$r['leader_player_id'],'created_at'=>$r['created_at'],'started_at'=>$r['started_at'],'decision_at'=>$r['decision_at'],'decision_deadline'=>$r['decision_deadline'],'finishes_at'=>$r['finishes_at'],'completed_at'=>$r['completed_at'],'choice'=>$r['choice'],'members'=>$party,'readiness'=>$armyForecast['label']??null,'army_forecast'=>$armyForecast,'votes'=>$votes,'progress'=>['encounters_done'=>$terminal?(int)($sim['encounters_done']??0):($visibleBattle?1:0),'encounters_total'=>(int)($sim['encounters_total']??3),'hp_remaining'=>$visibleRounds?(int)$visibleRounds[array_key_last($visibleRounds)]['party_hp']:0],'battle_log'=>$visibleRounds,'reward'=>$reward,'can_start'=>$mine&&(int)$r['leader_player_id']===$viewer&&$r['status']==='recruiting'&&count($party)>=2,'can_leave'=>$mine&&(int)$r['leader_player_id']!==$viewer&&$r['status']==='recruiting','can_cancel'=>$mine&&(int)$r['leader_player_id']===$viewer&&$r['status']==='recruiting','can_vote'=>$mine&&$r['status']==='decision','can_claim'=>$reward!==false&&$reward['claimed_at']===null];}
    private static function party(int $runId): array {$rows=Connection::getInstance()->query('SELECT m.*,COALESCE(k.display_name,p.username) username FROM dungeon_members m JOIN players p ON p.id=m.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE m.run_id=? ORDER BY m.joined_at,m.player_id',[$runId])->fetchAll();$out=[];foreach($rows as$r){$s=json_decode($r['stats_json'],true)?:[];$out[]=$s+['player_id'=>(int)$r['player_id'],'username'=>$r['username'],'role'=>$r['role'],'troops'=>json_decode($r['troops_json'],true)?:[],'returned_at'=>$r['returned_at']];}return$out;}
    private static function member(array $run,int $playerId,bool $required=false): ?array {$r=Connection::getInstance()->query('SELECT * FROM dungeon_members WHERE run_id=? AND player_id=?'.(Connection::getInstance()->getPdo()->inTransaction()?' FOR UPDATE':''),[$run['id'],$playerId])->fetch();if(!$r&&$required)throw new DungeonException('NOT_MEMBER','Du gehörst nicht zu dieser Gruppe.',403);return$r?:null;}
    private static function reward(array $sim,array $m,bool $won): array
    {
        $base=is_array($sim['base_reward']??null)?$sim['base_reward']:[];
        $fragments=$won?(int)($sim['fragments']??0):0;
        $items=array_values(array_filter(array_map('intval',$base['item_codes']??[]),fn($c)=>InventoryService::getItemDef($c)!==null));
        $seed=(string)$m['player_id'].':'.self::json($sim);
        $roll=((crc32($seed)&0xffff)/(isset($base['item_weights'])?65536:65535));
        $item=$won&&$items&&$roll<(float)($sim['item_chance']??0)?$items[((int)$m['player_id'])%count($items)]:0;
        if($item && isset($base['item_weights'])){
            $total=array_sum(array_column($base['item_weights'],'weight'));$item=0;
            if($total>0){$pick=(crc32('item:'.$seed)&0x7fffffff)%$total+1;foreach($base['item_weights'] as $row){$pick-=$row['weight'];if($pick<=0){$item=(int)$row['item_code'];break;}}}
        }
        return ['fragments'=>$fragments,'treasure_code'=>(int)($base['treasure_code']??0),'grade'=>(string)($base['grade']??'normal'),'item_code'=>$item,'item_quantity'=>$item?(int)($base['item_quantity']??1):0,'success'=>$won];
    }
    private static function assertNoActive(int $playerId): void {if(Connection::getInstance()->query("SELECT m.run_id FROM dungeon_members m JOIN dungeon_runs r ON r.id=m.run_id WHERE m.player_id=? AND r.world_id=? AND r.status IN ('recruiting','running','decision') LIMIT 1 FOR UPDATE",[$playerId,WorldContext::id()])->fetchColumn())throw new DungeonException('ALREADY_ACTIVE','Du gehörst bereits zu einer aktiven Dungeon-Gruppe.');}
    private static function roleRanks(int $playerId): array {$out=array_fill_keys(self::ROLES,0);foreach(Connection::getInstance()->query("SELECT talent_code,rank FROM player_lord_talents WHERE player_id=? AND world_id=? AND (talent_code LIKE 'attack\\_%' OR talent_code LIKE 'defense\\_%' OR talent_code LIKE 'gather\\_%' OR talent_code LIKE 'hunter\\_%')",[$playerId,WorldContext::id()])->fetchAll()as$r){$branch=explode('_',(string)$r['talent_code'])[0];if(isset($out[$branch]))$out[$branch]+=(int)$r['rank'];}return$out;}
    private static function eligibleRoles(int $playerId): array {$r=self::roleRanks($playerId);return array_keys(array_filter($r,fn($v)=>$v>0));}
    private static function rotation(): array {return DungeonRules::weeklyRotation()['available'];}
    private static function definition(string $code): array {foreach(DungeonRules::catalog()['dungeons']as$d)if(($d['dungeon_code']??null)===$code)return$d;throw new DungeonException('DUNGEON_UNKNOWN','Dungeon-Konfiguration fehlt.',500);}
    private static function runDefinition(array $run): array {$saved=json_decode((string)($run['definition_json']??''),true);return is_array($saved)&&$saved? $saved:self::definition((string)$run['dungeon_code']);}
    /** Deterministic samples are advisory; the actual encounter uses its hidden saved seed. */
    private static function forecast(array $run,array $party): array
    {
        $total=array_sum(array_map(static fn(array$m):int=>(int)($m['total_troops']??array_sum($m['troops']??[])),$party));$side=$run['stance']==='cautious'?'skip':'explore';
        if(count($party)<2)return ['status'=>'missing_members','label'=>'Weiterer Spieler benötigt','estimated'=>true,'remaining_hp_percent'=>0,'missing_roles'=>[],'total_troops'=>$total,'includes_side_room'=>$side==='explore'];
        try{$min=100;$won=true;$definition=self::runDefinition($run);$maxHp=DungeonRules::effectivePartyHp($definition,$party);foreach([1,42,777,20260912,2147483647]as$seed){$trial=DungeonRules::simulate($definition,$party,$run['difficulty'],$run['stance'],$seed,$side);$won=$won&&$trial['success'];$min=min($min,(int)floor(100*$trial['hp_remaining']/max(1,$maxHp)));}
            $ready=$won&&$min>=35;return ['status'=>$ready?'ready':'risky','label'=>$ready?'Gut vorbereitet':($won?'Knapp':'Sehr riskant'),'estimated'=>true,'remaining_hp_percent'=>max(0,$min),'missing_roles'=>[],'total_troops'=>$total,'includes_side_room'=>$side==='explore'];
        }catch(\InvalidArgumentException){return ['status'=>'risky','label'=>'Sehr riskant','estimated'=>true,'remaining_hp_percent'=>0,'missing_roles'=>[],'total_troops'=>$total,'includes_side_room'=>$side==='explore'];}
    }
    /** Display labels come from the same item catalog used for payout. */
    private static function publicDefinition(array $definition): array
    {
        $treasure=\Conquer\Game\Treasure\TreasureData::get((int)$definition['treasure_code']);
        $definition['treasure_name']=$treasure['name_de']??$treasure['name']??'Relikt';
        $definition['item_names']=array_map(static function(int $code): string {
            $item=InventoryService::getItemDef($code);
            return $item['name_de']??$item['name']??'Gegenstand';
        },$definition['item_codes']);
        $definition['guidance']=DungeonRules::guidance($definition);
        return $definition;
    }
    private static function weekKey(): string {return (new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('o-\WW');}
    private static function id(mixed $v): int {if(!is_int($v)||$v<1)throw new DungeonException('INVALID_INPUT','run_id ist ungültig.');return$v;}
    private static function enum(mixed $v,array $allowed,string $label): string {if(!is_string($v)||!in_array($v,$allowed,true))throw new DungeonException('INVALID_INPUT',$label.' ist ungültig.');return$v;}
    private static function json(array $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
    private static function runLocked(string $key,callable $fn): mixed {$db=Connection::getInstance();if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new DungeonException('BUSY','Der Dungeon wird gerade aktualisiert.',409);try{return$fn();}finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}}
}
