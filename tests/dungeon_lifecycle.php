<?php
declare(strict_types=1);

/** Real services and concurrent requests against a disposable local database. */
if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir() . '/conquer-dungeon-test.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\Dungeon\DungeonService as D;
use Conquer\Game\Dungeon\DungeonException;
use Conquer\Game\Player\{LordLevel,MasteryService};
use Conquer\Game\World\WorldContext as W;

if (($argv[1] ?? '') === '--worker') {
    Connection::init($argv[2]);
    W::bind(1, (int) $argv[3]);
    try {
        $body = json_decode(base64_decode($argv[4]), true, 32, JSON_THROW_ON_ERROR);
        if ($body['action'] === '_tick') { D::tick((int) $argv[3]); }
        else { D::action((int) $argv[3], $body); }
        echo json_encode(['ok'=>true]);
    } catch (DungeonException $e) {
        echo json_encode(['ok'=>false,'code'=>$e->errorCode]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'unexpected'=>$e->getMessage()]);
    }
    exit;
}

$checks = 0;
function ck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($label); }
    $checks++; echo "PASS $label\n";
}
function deny(callable $fn, string $label): void {
    try { $fn(); } catch (DungeonException|DomainException $e) { ck(true, $label); return; }
    throw new RuntimeException('Allowed invalid action: ' . $label);
}
function act(int $pid, string $action, int $id=0, array $extra=[]): array {
    W::bind(1, $pid);
    return D::action($pid, ['action'=>$action,'run_id'=>$id,'expected_world_id'=>1]+$extra);
}
function stock(int $pid, int $world=1): int {
    return (int) Connection::getInstance()->query('SELECT COALESCE(SUM(t.count),0) FROM city_troops t JOIN cities c ON c.id=t.city_id WHERE c.player_id=? AND c.world_id=?', [$pid,$world])->fetchColumn();
}
function race(array $requests): array {
    $root = sys_get_temp_dir() . '/' . Connection::getInstance()->query('SELECT DATABASE()')->fetchColumn();
    $jobs=[];
    foreach ($requests as [$pid,$body]) {
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$root,(string)$pid,base64_encode(json_encode($body,JSON_THROW_ON_ERROR))],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if (!is_resource($process)) { throw new RuntimeException('Worker failed to start.'); }
        fclose($pipes[0]); $jobs[]=[$process,$pipes];
    }
    $results=[];
    foreach ($jobs as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process)!==0||$err!=='') { throw new RuntimeException($err ?: $out); }
        $result=json_decode($out,true,32,JSON_THROW_ON_ERROR);
        if (isset($result['unexpected'])) { throw new RuntimeException($result['unexpected']); }
        $results[]=$result;
    }
    return $results;
}

$fixture=null;$exit=0;
try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0082_create_dungeons.sql'));
    \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0082_create_dungeons.sql'));
    ck(true,'additive migration can be replayed');
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Dungeon test','dungeon-test','running',256)");
    foreach ([1=>'attack',2=>'defense',3=>'gather',4=>'hunter',5=>'attack',6=>'defense'] as $pid=>$role) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,'DungeonTester'.$pid,'dungeon'.$pid.'@tests.invalid','unused']);
        foreach ([1,2] as $world) {
            $city=$world===1?$pid:100+$pid;
            $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,?,'Teststadt',?,40,12,100000,100000,100000,100000)",[$city,$pid,$world,30+$pid*5]);
            foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) {
                $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$city,$code,$code==='castle'?12:5]);
            }
            $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,20000)',[$city]);
            LordLevel::addXp($pid,LordLevel::totalForLevel(20),$world,'fixture-level');
            $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,?,?,5)',[$pid,$world,$role.'_0']);
        }
    }
    W::bind(1,1);
    $state=D::state(1);
    ck(array_is_list($state['rotation'])&&array_is_list($state['next_rotation'])&&count($state['rotation'])===3&&count($state['next_rotation'])===3,'weekly rotation and next week each contain three dungeons');
    $code=$state['rotation'][0]['dungeon_code'];
    $create=['dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>10000]];
    ck(isset($state['rotation'][0]['guidance']['requirements']['hard']['explore'])&&array_sum(array_column($state['rotation'][0]['guidance']['mix'],'percent'))===100,'public dungeon definition includes computed whole-party guidance');
    $runsBefore=(int)$db->query('SELECT COUNT(*) FROM dungeon_runs')->fetchColumn();$membersBefore=(int)$db->query('SELECT COUNT(*) FROM dungeon_members')->fetchColumn();$stockBefore=stock(1);
    W::bind(1,1);$soloPreview=D::action(1,['action'=>'preview','expected_world_id'=>1]+$create);ck($soloPreview['forecast']['status']==='missing_members'&&$soloPreview['forecast']['label']==='Weiterer Spieler benötigt'&&$soloPreview['forecast']['missing_roles']===[]&&$soloPreview['forecast']['total_troops']===10000,'create preview reports only the prospective single-member gap');
    ck(stock(1)===$stockBefore&&(int)$db->query('SELECT COUNT(*) FROM dungeon_runs')->fetchColumn()===$runsBefore&&(int)$db->query('SELECT COUNT(*) FROM dungeon_members')->fetchColumn()===$membersBefore,'preview does not reserve troops or mutate dungeon rows');
    deny(fn()=>act(1,'create',0,array_replace($create,['role'=>'defense'])),'role must match invested talents');
    deny(fn()=>act(1,'create',0,array_replace($create,['troops'=>[50100101=>20001]])),'cannot reserve absent troops');
    deny(fn()=>act(1,'create',0,array_replace($create,['troops'=>[50100101=>-1]])),'negative troop count rejected');
    deny(fn()=>act(1,'create',0,array_replace($create,['troops'=>[99999999=>20]])),'unknown troop type rejected');
    deny(fn()=>act(1,'create',0,array_replace($create,['difficulty'=>'invented'])),'unknown difficulty rejected');
    ck(stock(1)===20000,'invalid requests leave troops untouched');
    $result=act(1,'create',0,$create);$id=(int)$result['run_id'];
    ck($id>0,'creation returns its persisted run ID');
    ck(stock(1)===10000&&stock(1,2)===20000,'creation reserves troops only from selected world');
    deny(fn()=>act(1,'create',0,$create),'one active reservation per player and world');
    deny(fn()=>act(1,'start',$id),'a solo player cannot start');
    deny(fn()=>act(2,'start',$id),'outsider cannot start a party');
    deny(fn()=>act(2,'claim',$id),'outsider cannot claim rewards');
    $joinPreview=act(2,'preview',$id,['dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','role'=>'defense','troops'=>[50100101=>10000]]);ck(in_array($joinPreview['forecast']['status'],['ready','risky'],true)&&$joinPreview['forecast']['missing_roles']===[]&&$joinPreview['forecast']['total_troops']===20000,'join preview combines authoritative prospective stats with recruiting snapshots');
    ck(stock(2)===20000,'join preview leaves available army untouched');
    deny(fn()=>act(2,'preview',$id,['dungeon_code'=>$code,'difficulty'=>'hard','stance'=>'cautious','role'=>'defense','troops'=>[50100101=>10000]]),'preview rejects stale run difficulty');
    W::bind(2,2);
    deny(fn()=>D::action(2,['action'=>'join','run_id'=>$id,'expected_world_id'=>2,'role'=>'defense','troops'=>[50100101=>10000]]),'run cannot be joined from another world');
    deny(fn()=>D::action(2,['action'=>'preview','run_id'=>$id,'dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','expected_world_id'=>2,'role'=>'defense','troops'=>[50100101=>10000]]),'preview cannot inspect a group from another world');
    deny(fn()=>D::action(2,['action'=>'create','expected_world_id'=>1]+$create),'stale expected world rejected');
    W::bind(1,2);
    $join=['action'=>'join','run_id'=>$id,'expected_world_id'=>1,'role'=>'defense','troops'=>[50100101=>10000]];
    $results=race([[2,$join],[2,$join]]);
    ck(count(array_filter($results,fn($r)=>$r['ok']))===1&&stock(2)===10000,'concurrent duplicate join reserves troops once: '.json_encode(['id'=>$id,'results'=>$results,'stock'=>stock(2)]));
    act(3,'join',$id,['role'=>'gather','troops'=>[50100101=>10000]]);
    act(4,'join',$id,['role'=>'hunter','troops'=>[50100101=>10000]]);
    deny(fn()=>act(5,'preview',$id,['dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>10000]]),'preview rejects a full recruiting party');
    deny(fn()=>act(5,'join',$id,['role'=>'attack','troops'=>[50100101=>10000]]),'fifth participant rejected');
    act(4,'leave',$id);
    ck(stock(4)===20000,'leaving recruitment returns troops');
    act(4,'join',$id,['role'=>'hunter','troops'=>[50100101=>10000]]);
    W::bind(1,1);
    ck(MasteryService::blockedReason(1,1)!==null,'reserved dungeon army prevents talent changes');
    act(1,'cancel',$id);
    D::tick();D::tick();
    foreach ([1,2,3,4] as $pid) { ck(stock($pid)===20000,'cancel returns player '.$pid.' troops exactly once'); }

    // Utility specializations can form, preview and start a party without combat roles.
    $utilityCreate=array_replace($create,['role'=>'gather']);
    $utilityId=(int)act(3,'create',0,$utilityCreate)['run_id'];
    $utilityPreview=act(4,'preview',$utilityId,['dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','role'=>'hunter','troops'=>[50100101=>10000]]);
    ck(in_array($utilityPreview['forecast']['status'],['ready','risky'],true)&&$utilityPreview['forecast']['missing_roles']===[],'gather and hunter party receives a real forecast');
    act(4,'join',$utilityId,['role'=>'hunter','troops'=>[50100101=>10000]]);
    $utilityState=D::state(3);$utilityRun=current(array_filter($utilityState['runs'],fn($r)=>$r['id']===$utilityId));
    ck($utilityRun&&$utilityRun['can_start'],'gather leader can start with a hunter member');
    act(3,'start',$utilityId);
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$utilityId])->fetchColumn()==='running','utility-only party starts');
    $db->execute("UPDATE dungeon_runs SET status='cancelled',completed_at=UTC_TIMESTAMP() WHERE id=?",[$utilityId]);D::tick();

    // The current low-stat T1 catalogue requires real armies of 10,000 per member, not the historical 200-unit fixture. Execute a successful run; manipulate only fixture deadlines, never results.
    $id=(int)act(1,'create',0,$create)['run_id'];
    act(2,'join',$id,['role'=>'defense','troops'=>[50100101=>10000]]);
    act(3,'join',$id,['role'=>'gather','troops'=>[50100101=>10000]]);
    act(4,'join',$id,['role'=>'hunter','troops'=>[50100101=>10000]]);
    $started=race([[1,['action'=>'start','run_id'=>$id,'expected_world_id'=>1]],[1,['action'=>'start','run_id'=>$id,'expected_world_id'=>1]]]);
    ck(count(array_filter($started,fn($r)=>$r['ok']))===1,'concurrent start begins one run');
    deny(fn()=>act(1,'preview',$id,['dungeon_code'=>$code,'difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>10000]]),'preview rejects a started run');
    deny(fn()=>act(2,'leave',$id),'members cannot abandon an active run');
    deny(fn()=>act(1,'cancel',$id),'leader cannot cancel after departure');
    deny(fn()=>act(1,'vote',$id,['choice'=>'explore']),'voting before the event is rejected');
    $db->execute('UPDATE dungeon_runs SET decision_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),decision_deadline=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE id=?',[$id]);
    D::tick();
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$id])->fetchColumn()==='decision','due first encounter opens the decision');
    deny(fn()=>act(5,'vote',$id,['choice'=>'explore']),'outsider cannot vote');
    deny(fn()=>act(1,'vote',$id,['choice'=>'invented']),'unknown choice rejected');
    act(1,'vote',$id,['choice'=>'explore']);
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$id])->fetchColumn()==='decision','one early vote does not exclude offline members');
    act(2,'vote',$id,['choice'=>'skip']);
    $db->execute('UPDATE dungeon_runs SET decision_deadline=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?',[$id]);
    D::tick();
    ck($db->query('SELECT choice FROM dungeon_runs WHERE id=?',[$id])->fetchColumn()==='skip','tie follows cautious default after deadline');
    for($i=0;$i<3;$i++) {
        $db->execute('UPDATE dungeon_runs SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=? AND finishes_at IS NOT NULL',[$id]);D::tick();
    }
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$id])->fetchColumn()==='completed','real battle reaches victory');
    ck((int)$db->query('SELECT COUNT(*) FROM dungeon_rewards WHERE run_id=?',[$id])->fetchColumn()===4,'each participant has a personal reward');
    foreach ([1,2,3,4] as $pid) { ck(stock($pid)===20000,'completion automatically returns player '.$pid.' troops before claiming'); }
    $before=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    $claims=race([[1,['action'=>'claim','run_id'=>$id,'expected_world_id'=>1]],[1,['action'=>'claim','run_id'=>$id,'expected_world_id'=>1]]]);
    ck(count(array_filter($claims,fn($r)=>$r['ok']))===1,'concurrent reward claims pay once');
    $after=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    ck($after>$before,'real treasure fragments are credited');
    act(2,'claim',$id);
    $other=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=2')->fetchColumn();
    ck($other===$after-$before,'offline defender receives the same fragment reward');
    D::tick();D::tick();
    ck(stock(1)===20000&&(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn()===$after,'repeated ticks do not duplicate troops or loot');

    // A weak party must lose in the actual combat engine and retain its army.
    $weak=array_replace($create,['troops'=>[50100101=>10]]);
    $weakId=(int)act(1,'create',0,$weak)['run_id'];
    act(2,'join',$weakId,['role'=>'defense','troops'=>[50100101=>10]]);
    act(1,'start',$weakId);
    $db->execute('UPDATE dungeon_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY),decision_at=IF(decision_at IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)),decision_deadline=IF(decision_deadline IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)),finishes_at=IF(finishes_at IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)) WHERE id=?',[$weakId]);
    D::tick();
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$weakId])->fetchColumn()==='failed','undersized army loses through real battle calculation');
    ck(stock(1)===20000&&stock(2)===20000,'defeat returns both armies without permanent loss');

    $first=(int)act(1,'create',0,$create)['run_id'];
    $second=(int)act(5,'create',0,$create)['run_id'];
    $joinBody=['action'=>'join','expected_world_id'=>1,'role'=>'defense','troops'=>[50100101=>10000]];
    $cross=race([[2,['run_id'=>$first]+$joinBody],[2,['run_id'=>$second]+$joinBody]]);
    ck(count(array_filter($cross,fn($r)=>$r['ok']))===1&&stock(2)===10000,'one player racing to join different runs reserves only one army');
    act(1,'cancel',$first);act(5,'cancel',$second);D::tick();
    ck(stock(2)===20000,'cross-run join race returns its only reservation');

    $expiry=(int)act(1,'create',0,$create)['run_id'];
    act(2,'join',$expiry,['role'=>'defense','troops'=>[50100101=>10000]]);
    $db->execute("UPDATE dungeon_runs SET week_key='2020-W01' WHERE id=?",[$expiry]);
    D::tick();
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$expiry])->fetchColumn()==='cancelled'&&stock(1)===20000&&stock(2)===20000,'weekly turnover expires recruiting groups and restores armies');

    $offline=(int)act(1,'create',0,$create)['run_id'];
    act(2,'join',$offline,['role'=>'defense','troops'=>[50100101=>10000]]);
    act(3,'join',$offline,['role'=>'gather','troops'=>[50100101=>10000]]);
    act(4,'join',$offline,['role'=>'hunter','troops'=>[50100101=>10000]]);
    act(1,'start',$offline);
    $db->execute("UPDATE dungeon_runs SET week_key='2020-W01',started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY),decision_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),decision_deadline=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?",[$offline]);
    W::bind(2,1);D::tick();
    ck($db->query('SELECT status FROM dungeon_runs WHERE id=?',[$offline])->fetchColumn()==='completed','one background tick catches up an offline run across the weekly turnover');
    ck(stock(1)===20000&&stock(1,2)===20000&&stock(3)===20000&&stock(4)===20000,'background settlement restores persisted origin world');
    W::bind(1,1);
    $state=D::state(1);$report=current(array_filter($state['runs'],fn($r)=>$r['id']===$offline));
    ck($report&&$report['can_claim']&&$report['dungeon']['treasure_name']!=='','offline reward remains available with its catalog name');

    require __DIR__.'/dungeon_http_cases.php';
    echo "OK: $checks dungeon lifecycle checks.\n";
} catch (Throwable $e) { fwrite(STDERR,(string)$e."\n");$exit=1; }
finally { $fixture?->close(); }
exit($exit);
