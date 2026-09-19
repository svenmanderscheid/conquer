<?php
declare(strict_types=1);
/** Real persistence in a disposable schema; no saved armies are used. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\March\{GatherService as G,MarchTick,MarchDispatcher};
use Conquer\Game\Map\FieldObjectService as F;
use Conquer\Game\World\WorldContext;
function checkG(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectG(callable $f,string $label):void{try{$f();}catch(RuntimeException|DomainException $e){if($e instanceof PDOException)throw $e;checkG(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function rowG(int $id):array{return Connection::getInstance()->query('SELECT * FROM marches WHERE id=?',[$id])->fetch();}
function nodeG(int $id):array{return Connection::getInstance()->query('SELECT * FROM field_objects WHERE id=?',[$id])->fetch();}
function makeNodeG(int $x):int{$db=Connection::getInstance();$db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,spawned_at,expires_at) VALUES(1,?,60,1,1,100000,100000,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$x]);return $db->lastInsertId();}
function sendG(int $pid,int $node,int $count=100,bool $attack=false):int{$n=nodeG($node);return G::dispatch($pid,$pid,(int)$n['coord_x'],60,selectedTroops:[50100101=>$count],attack:$attack)['march_id'];}
function dueG(int $march,int $secondsAgo=1):void{Connection::getInstance()->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND),departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?',[$secondsAgo,$secondsAgo+10,$march]);}
function settleG(int $march):void{$m=rowG($march);G::resolveGather(Connection::getInstance(),\Conquer\Logger::getInstance(),$march,(int)$m['player_id'],(int)$m['origin_city_id'],(int)$m['target_x'],60,(int)$m['target_id']);}
function clearG():void{$db=Connection::getInstance();foreach($db->query("SELECT id FROM marches WHERE state='arrived'")->fetchAll(PDO::FETCH_COLUMN) as $id)G::finish((int)$id,true);$db->execute("UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE state='returning'");foreach([1,2,3] as $pid)MarchTick::runForPlayer($pid);}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();\Conquer\Logger::init(sys_get_temp_dir().'/conquer-gathering-tests.log','ERROR');
try{
    WorldContext::bind(1);$db->execute('UPDATE worlds SET gather_factor=1,speed_factor=1 WHERE id=1');
    foreach([1,2,3] as $pid){
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",[$pid,'Gather'.$pid,'gather'.$pid.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Gather city',?,50,10000,10000,10000,10000)",[$pid,$pid,30+$pid*5]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$pid,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10000)',[$pid]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Gather allies','GA',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,3,1,'member')");
    $n=makeNodeG(50);$a=sendG(1,$n);$b=sendG(2,$n);
    checkG(nodeG($n)['gatherer_march_id']===null,'two armies can depart to a free field without reserving it');
    checkG((MarchDispatcher::listActive(1)[0]['target_resource']??null)==='food','active gathering march exposes its referenced resource type');
    checkG(!F::withOccupations([nodeG($n)],1,1)[0]['is_own_gathering'],'outbound army does not appear as a gathering owner');
    dueG($a,2);dueG($b,1);settleG($b);
    checkG(rowG($a)['state']==='arrived'&&(int)nodeG($n)['gatherer_march_id']===$a,'earliest actual arrival wins even when the later player ticks first');
    checkG(rowG($b)['state']==='returning'&&(json_decode(rowG($b)['haul_json'],true)['reason']??'')==='field_occupied','later arriving gatherers return without combat');
    checkG((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===0&&json_decode(rowG($b)['troops_json'],true)[50100101]===100,'race causes neither battle reports nor troop losses');
    foreach([1,2,3] as $viewer){$node=F::withOccupations([nodeG($n)],1,$viewer)[0];checkG(array_key_exists('gathering_finishes_at',$node)===($viewer===1),'gathering timer private for viewer '.$viewer);checkG($node['can_attack']===($viewer===2),'field attack permission for viewer '.$viewer);}
    rejectG(fn()=>sendG(3,$n,10,true),'alliance member cannot attack the occupying army');
    rejectG(fn()=>sendG(1,$n,10,true),'owner cannot attack own army');
    rejectG(fn()=>sendG(2,$n,10),'ordinary gather dispatch cannot silently attack an occupied field');
    $attack=sendG(2,$n,1000,true);dueG($attack,0);settleG($attack);
    checkG(rowG($a)['state']==='returning'&&rowG($attack)['state']==='arrived'&&(int)nodeG($n)['gatherer_march_id']===$attack,'winning attacker takes over the field and displaced survivors return');
    checkG(json_decode(rowG($a)['troops_json'],true)[50100101]===70&&json_decode(rowG($attack)['troops_json'],true)[50100101]===900,'field battle losses apply only to deployed troops');
    checkG((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===2,'both combatants receive a field report');
    $stock=$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn();settleG($attack);checkG($stock===$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn()&&(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===2,'repeated field tick cannot duplicate combat');
    clearG();$after=$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn();clearG();checkG($after===$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn(),'homecoming returns survivors exactly once');

    $n=makeNodeG(55);$a=sendG(1,$n,500);dueG($a);settleG($a);$b=sendG(2,$n,10,true);dueG($b,0);settleG($b);
    checkG(rowG($b)['state']==='returning'&&rowG($a)['state']==='arrived'&&(int)nodeG($n)['gatherer_march_id']===$a,'losing field attack leaves surviving defenders gathering');clearG();

    $n=makeNodeG(60);$a=sendG(1,$n);dueG($a);settleG($a);$b=sendG(2,$n,500,true);
    $reports=(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn();G::finish($a,true);dueG($b,0);settleG($b);
    checkG(rowG($b)['state']==='returning'&&(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===$reports,'attack turns back if the targeted gatherers have left');clearG();

    $n=makeNodeG(65);$a=sendG(1,$n);dueG($a);settleG($a);$b=sendG(2,$n,500,true);
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,2,1,'member')");dueG($b,0);settleG($b);
    checkG(rowG($b)['state']==='returning'&&rowG($a)['state']==='arrived'&&(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===$reports,'alliance membership is checked again at attack arrival');
    $db->execute('DELETE FROM alliance_members WHERE player_id=2');clearG();

    $n=makeNodeG(70);$a=sendG(1,$n);$b=sendG(2,$n);dueG($a,1);dueG($b,1);settleG($b);
    checkG(rowG($a)['state']==='arrived'&&rowG($b)['state']==='returning','identical arrival times use stable march id order');clearG();

    $n=makeNodeG(75);$a=sendG(1,$n);dueG($a,1);settleG($a);$b=sendG(2,$n,500,true);
    // Both events overdue: battle precedes the original gathering finish and must still happen.
    $db->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 SECOND),departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 100 SECOND),gathering_finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND) WHERE id=?',[$a]);dueG($b,20);
    G::finish($a);
    checkG((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===$reports+2,'offline settlement processes attacks before a later gathering completion');clearG();

    $n=makeNodeG(80);$a=sendG(1,$n);dueG($a,2);$nodes=F::withOccupations([nodeG($n)],1,2);
    checkG($nodes[0]['can_attack']&&!isset($nodes[0]['gathering_finishes_at']),'other player viewing the map settles an offline arrival without leaking the timer');
    $db->execute('UPDATE field_objects SET gatherer_march_id=NULL WHERE id=?',[$n]);$db->execute("UPDATE marches SET state='complete' WHERE id=?",[$a]);
    $a=sendG(1,$n);$db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$a,$n]);$b=sendG(2,$n);
    checkG(nodeG($n)['gatherer_march_id']===null,'legacy departure reservations are removed safely');
    dueG($a,2);dueG($b,1);settleG($a);clearG();

    $n=makeNodeG(85);$a=sendG(1,$n);dueG($a,3);settleG($a);
    $amount=(int)nodeG($n)['resource_amount'];G::finish($a,true);$haul=json_decode(rowG($a)['haul_json'],true);
    checkG(($haul['loot']['food']??0)>0&&$haul['loot']['food']<=100&&nodeG($n)['resource_amount']===$amount-$haul['loot']['food'],'recall returns only actual work and deducts the same amount once');clearG();

    $db->execute("INSERT INTO worlds(id,name,slug,map_size,status,speed_factor,gather_factor) VALUES(2,'Other harvest','other-harvest',256,'open',1,1)");
    // This regression models two established, fully open worlds.
    \Conquer\Game\World\LandProgressService::ensureWorld(2,true);
    foreach([1,2] as $pid){
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,2,'Other world',?,50)",[100+$pid,$pid,30+$pid*5]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[100+$pid,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,1000)',[100+$pid]);
    }
    $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(2,50,60,1,1,10000,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");$n=$db->lastInsertId();
    WorldContext::bind(2);$a=G::dispatch(1,101,50,60,selectedTroops:[50100101=>100])['march_id'];dueG($a);WorldContext::bind(1);MarchTick::runForPlayer(1);
    checkG(rowG($a)['state']==='arrived'&&(int)nodeG($n)['gatherer_march_id']===$a&&WorldContext::id()===1,'settlement uses the stored world and restores the viewing context');
    checkG(F::withOccupations([nodeG($n)],2,2)[0]['can_attack'],'field ownership and alliance rules are scoped to the field world');
    echo "ALL GATHERING LIFECYCLE CHECKS PASSED\n";
}finally{$fixture->close();}
