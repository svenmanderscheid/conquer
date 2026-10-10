<?php
declare(strict_types=1);

/** Real key/army/loot lifecycle against disposable fixture data only. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-melusina-test.log','ERROR');

use Conquer\Db\Connection;
use Conquer\Game\Dungeon\{DungeonService as D,DungeonException,MelusinaProgress as M};
use Conquer\Game\Inventory\InventoryService as I;
use Conquer\Game\World\{WorldContext as W,WorldMapProfile};

if (($argv[1]??'')==='--worker') {
    Connection::init($argv[2]);
    W::bind(1,(int)$argv[3]);
    try {
        $body=json_decode(base64_decode($argv[4]),true,32,JSON_THROW_ON_ERROR);
        D::action((int)$argv[3],$body);
        echo json_encode(['ok'=>true]);
    } catch (DungeonException|DomainException $e) {
        echo json_encode(['ok'=>false,'code'=>$e instanceof DungeonException?$e->errorCode:'DOMAIN']);
    } catch (Throwable $e) { echo json_encode(['unexpected'=>$e->getMessage()]); }
    exit;
}

$checks=0;
function ck(bool $ok,string $label): void {
    global $checks;
    if(!$ok)throw new RuntimeException($label);
    $checks++;echo "PASS $label\n";
}
function deny(callable $fn,string $label): void {
    try{$fn();}catch(DungeonException|DomainException $e){ck(true,$label);return;}
    throw new RuntimeException('Invalid action allowed: '.$label);
}
function act(int $pid,string $action,int $runId=0,array $extra=[],int $world=1): array {
    W::bind($world,$pid);
    return D::action($pid,['action'=>$action,'run_id'=>$runId,'expected_world_id'=>$world]+$extra);
}
function race(array $requests): array {
    $root=sys_get_temp_dir().'/'.Connection::getInstance()->query('SELECT DATABASE()')->fetchColumn();
    $jobs=[];
    foreach($requests as[$pid,$body]){
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$root,(string)$pid,base64_encode(json_encode($body,JSON_THROW_ON_ERROR))],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Unable to start worker.');
        fclose($pipes[0]);$jobs[]=[$process,$pipes];
    }
    $out=[];
    foreach($jobs as[$process,$pipes]){
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0||$stderr!=='')throw new RuntimeException($stderr?:$stdout);
        $row=json_decode($stdout,true,32,JSON_THROW_ON_ERROR);
        if(isset($row['unexpected']))throw new RuntimeException($row['unexpected']);
        $out[]=$row;
    }
    return $out;
}
function run(int $id): array {return Connection::getInstance()->query('SELECT * FROM dungeon_runs WHERE id=?',[$id])->fetch();}
function stock(int $pid): int {return (int)Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$pid])->fetchColumn();}
function report(int $pid,int $id): array {
    W::bind(1,$pid);
    return current(array_filter(D::state($pid)['runs'],fn($r)=>$r['id']===$id));
}
function finish(int $id): void {
    $db=Connection::getInstance();
    $db->execute('UPDATE dungeon_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 DAY),decision_at=IF(decision_at IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)),decision_deadline=IF(decision_deadline IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)),finishes_at=IF(finishes_at IS NULL,NULL,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)) WHERE id=?',[$id]);
    D::tick();
}

$fixture=null;$exit=0;
try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    WorldMapProfile::configureEmptyWorld(1);
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Legacy fixture','legacy-fixture','running',256)");
    foreach([1=>'attack',2=>'defense'] as$pid=>$role){
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,'MelusinaTester'.$pid,'melusina'.$pid.'@tests.invalid','unused']);
        foreach([1,2] as$world){
            $city=$world===1?$pid:100+$pid;
            $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,?,'Fixture city',?,40,12,100000,100000,100000,100000)",[$city,$pid,$world,30+$pid*5]);
            foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$city,$code,$code==='castle'?12:5]);
            $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,40000)',[$city]);
            \Conquer\Game\Player\LordLevel::addXp($pid,\Conquer\Game\Player\LordLevel::totalForLevel(20),$world,'fixture-level');
            $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,?,?,5)',[$pid,$world,$role.'_0']);
        }
    }
    $create=['dungeon_code'=>'melusina_well','difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>40000]];
    $join=['role'=>'defense','troops'=>[50100101=>40000]];
    W::bind(2,1);$legacy=D::state(1);
    ck($legacy['rotation']===[]&&$legacy['next_rotation']===[]&&$legacy['permanent_dungeons']===[],'legacy worlds expose no disabled dungeons');
    deny(fn()=>act(1,'create',0,$create,2),'Melusina cannot be created on a legacy map');
    W::bind(1,1);$state=D::state(1);
    ck($state['rotation']===[]&&$state['next_rotation']===[]&&array_column($state['permanent_dungeons'],'dungeon_code')===['melusina_well'],'Luxembourg exposes only the permanent Melusina dungeon');
    act(1,'accept_melusina',0,['request_id'=>'fixture-accept-0001']);
    $id=(int)act(1,'create',0,$create)['run_id'];
    act(2,'join',$id,$join);
    ck(!report(1,$id)['can_start'],'leader without a key cannot start');
    deny(fn()=>act(1,'start',$id),'missing key is enforced server-side');
    ck(run($id)['status']==='recruiting'&&M::reservation($id)===null,'failed start leaves no key reservation');
    I::addItems(1,10309002,1,1);
    ck(report(1,$id)['can_start'],'one leader key unlocks start for the whole group');
    $db->execute("UPDATE dungeon_runs SET week_key='2020-W01' WHERE id=?",[$id]);D::tick();
    ck(run($id)['status']==='recruiting','permanent recruiting party survives weekly turnover');
    $start=['action'=>'start','run_id'=>$id,'expected_world_id'=>1];
    $started=race([[1,$start],[1,$start]]);
    ck(count(array_filter($started,fn($r)=>$r['ok']))===1&&M::keyCount(1,1)===0&&M::reservation($id)['status']==='reserved','concurrent starts reserve exactly one key');
    ck(M::keyCount(2,1)===0,'guest needs no key');
    deny(fn()=>act(2,'cancel',$id),'only leader may cancel a running adventure');
    act(1,'cancel',$id);D::tick();
    deny(fn()=>act(1,'cancel',$id),'replayed cancellation cannot return another key');
    ck(M::keyCount(1,1)===1&&M::reservation($id)['status']==='returned'&&stock(1)===40000&&stock(2)===40000,'cancel returns one key and both armies');
    ck((int)$db->query('SELECT COUNT(*) FROM dungeon_rewards WHERE run_id=?',[$id])->fetchColumn()===0,'cancel grants no partial rewards');

    $weak=(int)act(1,'create',0,array_replace($create,['troops'=>[50100101=>10]]))['run_id'];
    act(2,'join',$weak,['role'=>'defense','troops'=>[50100101=>10]]);act(1,'start',$weak);finish($weak);D::tick();
    ck(run($weak)['status']==='failed'&&M::keyCount(1,1)===1&&M::reservation($weak)['status']==='returned','real combat defeat returns the reserved key once');
    $lost=report(1,$weak);
    ck($lost['reward']['fragments']===0&&$lost['reward']['item_quantity']===0&&M::status(1,1)['completed_runs']===0,'defeat gives no loot or chronicle completion');

    $victory=(int)act(1,'create',0,$create)['run_id'];act(2,'join',$victory,$join);
    ck(report(1,$victory)['story_mode']==='first'&&report(2,$victory)['story_mode']==='first','first adventure snapshot is recorded for every participant');
    act(1,'start',$victory);
    W::bind(2,1);finish($victory);D::tick();
    ck(run($victory)['status']==='completed'&&M::reservation($victory)['status']==='consumed','offline tick finishes a successful run using its persisted world');
    ck(M::keyCount(1,1)===0&&M::keyCount(1,2)===0,'successful settlement consumes only the original world key');
    ck(M::status(1,1)['completed_runs']===1&&M::status(2,1)['completed_runs']===1&&M::status(1,2)['completed_runs']===0,'chronicle completion is per member and per world');
    ck(report(1,$victory)['story_mode']==='first','completed first story stays first after the chronicle unlocks');
    $claim=['action'=>'claim','run_id'=>$victory,'expected_world_id'=>1];
    $claimed=race([[1,$claim],[1,$claim]]);
    ck(count(array_filter($claimed,fn($r)=>$r['ok']))===1,'concurrent claims grant personal loot once');
    ck((int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn()>0,'success grants an existing non-retired treasure');
    ck(stock(1)===40000&&stock(2)===40000,'both armies return on success before reward collection');

    I::addItems(1,10309002,1,1);
    $echo=(int)act(1,'create',0,$create)['run_id'];act(2,'join',$echo,$join);act(1,'start',$echo);
    ck(report(1,$echo)['story_mode']==='echo','later adventure uses echo story mode');
    $db->execute('UPDATE dungeon_runs SET decision_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),decision_deadline=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE id=?',[$echo]);D::tick();
    ck(run($echo)['status']==='decision'&&report(1,$echo)['can_cancel'],'leader may cancel while the side chamber vote is open');
    act(1,'cancel',$echo);D::tick();D::tick();
    ck(M::keyCount(1,1)===1&&M::status(1,1)['completed_runs']===1,'cancelled echo returns key without advancing the chronicle');
    ck((int)$db->query('SELECT COUNT(*) FROM dungeon_rewards WHERE run_id=?',[$echo])->fetchColumn()===0,'cancel after first encounter still has no partial loot');
    echo "OK: $checks Melusina lifecycle checks.\n";
}catch(Throwable $e){fwrite(STDERR,(string)$e."\n");$exit=1;}
finally{$fixture?->close();}
exit($exit);
