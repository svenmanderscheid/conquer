<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\{BuildingProgression,CityState,TroopTrainer};
use Conquer\Game\Research\{ResearchEffects,BuffEngine};
use Conquer\Game\March\{GatherService,MarchTick,MarchDispatcher};
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\World\WorldContext;
$fixture=null;$checks=0;$exit=0;$logFile=tempnam(sys_get_temp_dir(),'conquer-guide-');\Conquer\Logger::init($logFile);
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function rejects(callable $work,string $label):void{try{$work();}catch(DomainException|RuntimeException $e){if($e instanceof PDOException)throw $e;ck(true,$label);return;}ck(false,$label);}
function row(int $id):array{return Connection::getInstance()->query('SELECT * FROM marches WHERE id=?',[$id])->fetch();}
function stock():int{return (int)Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=50100101')->fetchColumn();}
function node(int $amount=10000,int $world=1,int $type=1):int{
 $db=Connection::getInstance();$db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,spawned_at,expires_at) VALUES(?,90,90,?,1,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$world,$type,$amount,$amount]);return (int)$db->lastInsertId();
}
function reach(int $id,int $ago=1):void{Connection::getInstance()->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?',[$ago+20,$ago,$id]);MarchTick::runForPlayer(1);}
function home(int $id):void{Connection::getInstance()->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);MarchTick::runForPlayer(1);}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
 foreach(['0079_monster_rallies.sql','0080_gathering_duration.sql'] as $file){$sql=file_get_contents(ROOT_DIR.'/migrations/'.$file);\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);}
 $db->execute("UPDATE worlds SET status='running',speed_factor=1,gather_factor=1 WHERE id=1");
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Other','other','running',256)");
 $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'GuideFixture','guide@invalid.test','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(1,1,1,'Guide city',20,20,100000,100000,100000,100000),(2,1,2,'Other city',20,20,100000,100000,100000,100000)");
 foreach([1,2] as $city)foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$city,$code]);
 $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(1,50100101,25000),(1,50200101,25000),(1,50300101,25000)');
 ck(ResearchEffects::limits(BuffEngine::getBuffs(1))['march_capacity']===5000,'Castle 1 enables 5000 troops per march');
 $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1 AND building_code IN ('castle','barrack')");$buffs=BuffEngine::getBuffs(1);
 ck(ResearchEffects::limits($buffs)['march_capacity']===50000,'Castle 30 enables 50000 troops per march');
 ck(ResearchEffects::training(50100101,$buffs)['max_count']===5000,'Barrack 30 enables 5000 troops per batch');
 ck(ResearchEffects::limits(BuffEngine::getBuffs(1,2))['march_capacity']===5000,'Building bonuses stay within their world');
 ck(ResearchEffects::limits($buffs+[])['march_capacity']===50000&&ResearchEffects::limits(array_replace($buffs,['march_size'=>.15,'march_capacity_flat'=>100]))['march_capacity']===57600,'Research and flat talents apply on the castle base');
 $previous=0;for($level=1;$level<=30;$level++){$current=BuildingProgression::atLevels(['castle'=>$level])['base_march_capacity'];ck($current>$previous,'Castle capacity grows at level '.$level);$previous=$current;}
 ck(ResearchEffects::carryCapacity([50100101=>100,50200101=>100,50300101=>100],[])===450,'Mixed T1 army uses 2, 1.5 and 1 carry');
 ck(ResearchEffects::carryCapacity([50100101=>10000],[])===20000,'Large armies have no artificial 5000 haul ceiling');
 ck(ResearchEffects::carryCapacity([50100101=>100,50200101=>100],['troops_storage'=>.1,'infantry_storage'=>.2])===425,'Carry buffs respect each troop type');
 ck(GatherService::troopSpeed(50300101,[])>GatherService::troopSpeed(50100101,[]),'Cavalry travels faster than infantry');
 ck(GatherService::troopSpeed(50100101,['gathering_speed'=>1])===GatherService::troopSpeed(50100101,[]),'Gather speed never shortens travel');
 ck(GatherService::rate('food',1,['gathering_speed'=>.2,'food_gathering_speed'=>.3],2)===30.0,'Resource, general and world gathering bonuses combine');
 $protected=DefenseService::protectedResources(['food'=>20000,'lumber'=>9000,'gold'=>0],$buffs+['resource_protect'=>.1]);
 ck($protected['food']===12000&&$protected['lumber']===9000&&$protected['gold']===0,'Warehouse protects a fixed amount plus a bounded share');
 $db->execute("UPDATE city_buildings SET level=2 WHERE city_id=1 AND building_code='storage'");ck(BuffEngine::getBuffs(1)['storage_protection_flat']===12000,'Warehouse upgrade raises protection');
 $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='castle'");
 node();rejects(fn()=>GatherService::dispatch(1,1,90,90,0,[50100101=>5001]),'Server enforces castle capacity before dispatch');
 $id=GatherService::dispatch(1,1,90,90,0,[50100101=>100])['march_id'];ck(stock()===24900,'Gather dispatch reserves exactly the selected troops');
 rejects(fn()=>GatherService::dispatch(1,1,90,90,0,[50100101=>10]),'Reserved resource field rejects a second march');
 reach($id);$r=row($id);ck($r['state']==='arrived'&&$r['gathering_finishes_at']!==null,'Arrival starts gathering instead of instantly taking resources');
 ck((int)$db->query('SELECT resource_amount FROM field_objects WHERE world_id=1')->fetchColumn()===10000,'Resource node stays reserved until harvest settlement');
 ck(count(MarchDispatcher::listActive(1))===1,'Gathering remains in the active march read model');
 // Five seconds of work at ten resources per second; return still takes twenty seconds.
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 25 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND),gathering_finishes_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 SECOND) WHERE id=?',[$id]);
 DefenseService::recallMarch(1,$id);$r=row($id);$loot=json_decode($r['haul_json'],true)['loot'];
 ck($r['state']==='returning'&&$loot['food']>=50&&$loot['food']<=60,'Recall keeps only the gathered portion');
 ck(stock()===24900,'Recalled troops stay away until homecoming');rejects(fn()=>DefenseService::recallMarch(1,$id),'Repeated recall cannot repeat a harvest');
 $before=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();home($id);home($id);
 ck(stock()===25000&&(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$before+$loot['food'],'Return credits troops and resources exactly once');
 $id=GatherService::dispatch(1,1,90,90,100)['march_id'];reach($id,1000);
 ck(row($id)['state']==='complete'&&stock()===25000,'Offline tick completes travel, work and return in order');
 ck(json_decode(row($id)['haul_json'],true)['loot']['food']===200,'Legacy count-only request carries its real selected troop load');
 $db->execute('DELETE FROM field_objects');node(45);$id=GatherService::dispatch(1,1,90,90,0,[50100101=>100])['march_id'];reach($id,1000);
 ck(json_decode(row($id)['haul_json'],true)['loot']['food']===45,'Depleted node returns its remaining amount without overdraw');
 $db->execute('DELETE FROM field_objects');node();$id=GatherService::dispatch(1,1,90,90,0,[50100101=>100])['march_id'];$db->execute('DELETE FROM field_objects');node();reach($id,1000);
 ck(row($id)['state']==='complete'&&empty(json_decode(row($id)['haul_json'],true)['loot']),'Replacement node at the same tile is never harvested');
 ck(stock()===25000,'Vanished node safely returns the army');
 $db->execute('DELETE FROM field_objects');node(10000,2);rejects(fn()=>GatherService::dispatch(1,1,90,90,0,[50100101=>100]),'Gather targets cannot cross worlds');
 $db->execute('DELETE FROM field_objects');node(10000,1,5);$before=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();$id=GatherService::dispatch(1,1,90,90,0,[50100101=>1])['march_id'];reach($id,1000);
 ck((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$before+2,'Gem node yields gems to the player on return');
 $db->execute('DELETE FROM field_objects');node();$id=GatherService::dispatch(1,1,90,90,0,[50100101=>10])['march_id'];$db->execute('UPDATE marches SET haul_json=NULL WHERE id=?',[$id]);reach($id,1000);ck(row($id)['state']==='complete','Pre-migration gather marches receive a snapshot on arrival');

 $city=CityState::loadForPlayer(1);TroopTrainer::train($city['city'],$city['buildings'],50100101,600);
 ck((int)$db->query('SELECT count FROM troop_queue WHERE city_id=1')->fetchColumn()===600,'Upgraded barrack accepts a real batch above the old 500 ceiling');
 $db->execute('DELETE FROM troop_queue');$db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='barrack'");$city=CityState::loadForPlayer(1);
 rejects(fn()=>TroopTrainer::train($city['city'],$city['buildings'],50100101,501),'Level 1 barrack rejects a batch beyond its capacity');
 $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));
 $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','guide fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Guide group','GDE',1)");$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader')");
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20202101,95,95,1000,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
 $url=$fixture->serve('switch(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)){case "/api/rally/start-monster":\Conquer\Api\Handlers\RallyHandler::startMonster([]);break;case "/api/game/state":\Conquer\Api\Handlers\GameHandler::state([]);break;case "/api/march/dispatch-gather":\Conquer\Api\Handlers\MarchHandler::dispatchGather(\Conquer\Auth\Session::current()??[]);break;default:http_response_code(404);echo "{}";}');
 $http=static function(string $path,?array $body=null,bool $auth=true,bool $withCsrf=true)use($url,$token,$csrf):array{
    $h=curl_init($url.$path);$headers=['Content-Type: application/json'];if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>$headers]);if($auth)curl_setopt($h,CURLOPT_COOKIE,'conquer_session='.$token);
    if($body!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
    $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);$data=json_decode((string)$raw,true);if(!is_array($data))throw new RuntimeException('Unexpected HTTP response '.substr((string)$raw,0,800));return ['status'=>$status,'body'=>$data];
 };
 $body=['target_x'=>95,'target_y'=>95,'troops'=>[50100101=>100],'rally_minutes'=>15];
 ck($http('/api/rally/start-monster',$body,false)['status']===401,'Monster rally API requires authentication');
 ck($http('/api/rally/start-monster',$body,true,false)['status']===403,'Monster rally API requires CSRF');
 ck($http('/api/rally/start-monster',array_replace($body,['target_x'=>'95']))['status']===400,'Monster rally API rejects malformed coordinates');
 $response=$http('/api/rally/start-monster',$body);ck($response['status']===200,'Monster rally API starts through the real handler');
 $rid=(int)$response['body']['data']['rally_id'];ck((int)$db->query('SELECT TIMESTAMPDIFF(MINUTE,created_at,launch_at) FROM rallies WHERE id=?',[$rid])->fetchColumn()===15,'HTTP rally uses the selected preparation time');
 $game=$http('/api/game/state?map_x=90&map_y=90');ck($game['status']===200,'Game state API returns the integrated progression model');$state=$game['body']['data'];
 ck(count(array_filter($state['monsters'],fn($m)=>($m['definition']['art']??'')==='monsters/frostgrimm'&&($m['definition']['biome']??'')==='ice'))===1,'HTTP state preserves regional boss artwork and biome');
 ck($state['army_limits']['march_capacity']===5000&&(int)$state['active_rally_count']===1,'Game state includes castle capacity and occupied rally slots');
 ck(count(array_filter($state['marches'],fn($m)=>$m['march_type']==='rally'))===1,'Real rally appears once in the shared march list');
 ck($state['troop_defs'][0]['gather_carry']==2&&$state['troop_defs'][0]['training']['max_count']===500,'UI receives real troop carry and barrack capacity');
 ck(count(array_filter($state['nodes'],fn($n)=>isset($n['gather_rate'])&&$n['gather_rate']>0))>0,'Resource previews receive effective work rates');
 $gather=$http('/api/march/dispatch-gather',['target_x'=>90,'target_y'=>90,'troops'=>[50100101=>10]]);ck($gather['status']===200,'Gather API accepts the selected army');
 $db->execute('UPDATE sessions SET active_world_id=2 WHERE token=?',[$token]);$other=$http('/api/game/state?map_x=90&map_y=90');
 ck($other['status']===200&&(int)$other['body']['data']['city']['world_id']===2&&(int)$other['body']['data']['active_rally_count']===0,'HTTP state uses the selected world for progression and rallies');
 ck(!in_array($rid,array_column($other['body']['data']['marches'],'id'))&&count($other['body']['data']['monsters'])===0,'HTTP map never exposes another world target');
 ck(str_contains(file_get_contents(ROOT_DIR.'/index.php'),"'/api/rally/start-monster'"),'Application router registers the monster rally endpoint');
 echo "ALL $checks GUIDE PROGRESSION AND GATHERING CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();if(is_file($logFile))unlink($logFile);}exit($exit);
