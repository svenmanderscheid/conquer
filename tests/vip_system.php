<?php
declare(strict_types=1);
/** VIP persistence and contention checks use an empty disposable copy of the local schema. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Vip\VipService;
use Conquer\Game\City\{CityState,BuildingData,TroopTrainer,TroopData};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;
if(($argv[1]??'')==='worker'){
    Connection::init($argv[2]);WorldContext::bind(1);
    if($argv[3]==='daily')echo VipService::dailyLogin(1)?'1':'0';else{VipService::addPoints(1,100);echo 'ok';}exit;
}
require __DIR__.'/Support/FeatureDatabase.php';$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$failed=false;$checks=0;$server=null;$httpFiles=[];
function vc(bool $ok,string $label):void{global$checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function rejected(callable $fn,string $label):void{try{$fn();}catch(DomainException $e){vc(true,$label);return;}throw new RuntimeException($label);}
function workers(string $kind):array{
    global$fixture;$property=new ReflectionProperty($fixture,'directory');$path=$property->getValue($fixture);$jobs=[];
    for($i=0;$i<4;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$path,$kind],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker failed');fclose($pipes[0]);$jobs[]=[$process,$pipes];}
    $results=[];foreach($jobs as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);vc(proc_close($process)===0,'concurrent worker succeeds: '.$err);$results[]=$out;}return$results;
}
function vipHttp(string $path,array $body,int $status=200,bool $csrf=true,bool $authenticated=true):array{
    global$base,$token;$ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.($authenticated?$token:'invalid'),'X-CSRF-Token: '.($csrf?'vip-test':'wrong')],CURLOPT_TIMEOUT=>15]);$raw=curl_exec($ch);$actual=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$json=json_decode((string)$raw,true);vc($actual===$status&&is_array($json),$path.' HTTP '.$status.' '.$raw);return$json;
}
try{
    WorldContext::bind(1);$db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,speed_factor)VALUES(2,'VIP fixture','vip-fixture','open',1)");
    $db->execute("INSERT INTO players(id,username,email,password_hash,vip_points,vip_level)VALUES(1,'VIPFixture','vip@invalid.test','unused',190,0),(2,'OtherFixture','vip-other@invalid.test','unused',0,0)");
    $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(3,'NewVIPFixture','vip-new@invalid.test','unused')");
    $new=VipService::status(3);vc($new['points']===200&&$new['level']===1,'new players start automatically at VIP 1');
    foreach([1,2]as$id){$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,castle_level)VALUES(?,1,?,'VIP fixture',30,40,1000000,1000000,1000000,1000000,30)",[$id,$id]);foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,?)',[$id,$code,$code==='castle'?30:5]);}
    foreach(VipService::levels()as$l){vc(VipService::levelForPoints($l['points'])===$l['level'],'exact level boundary');if($l['level'])vc(VipService::levelForPoints($l['points']-1)===$l['level']-1,'just below boundary');}
    $s=VipService::status(1);vc($s['level']===0&&$s['points_remaining']===10&&$s['progress_required']===200,'initial progress');
    $db->execute('UPDATE players SET action_points=100,last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');
    $db->getPdo()->beginTransaction();$ap=\Conquer\Game\Player\ActionPoints::get(1);vc($ap['current']===112,'AP regeneration reuses inventory transaction');$db->getPdo()->rollBack();vc((int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===100,'AP regeneration rolls back with inventory transaction');
    vc(array_sum(workers('daily'))===1,'only one concurrent daily claim wins');$s=VipService::status(1);vc($s['points']===200&&$s['level']===1&&$s['daily_claimed'],'daily crosses boundary exactly once');
    rejected(fn()=>KingdomService::action(1,['action'=>'vip.daily','points'=>999999,'player_id'=>2]),'double daily claim rejected');
    vc(VipService::status(2)['points']===0,'client target cannot select another player');
    workers('points');$s=VipService::status(1);vc($s['points']===600&&$s['level']===2,'concurrent item points are never lost and level agrees');
    $db->getPdo()->beginTransaction();VipService::addPoints(1,1000);$db->getPdo()->rollBack();vc(VipService::status(1)['points']===600,'nested mutation rolls back with owning transaction');
    VipService::addPoints(1,-20);vc(VipService::status(1)['points']===600,'negative credit cannot reduce points');
    $db->execute("UPDATE players SET last_vip_login='2020-01-01' WHERE id=1");KingdomService::action(1,['action'=>'vip.daily']);vc(VipService::status(1)['points']===610,'next UTC day may claim again');
    $item=array_values(array_filter(InventoryService::allDefs(),fn($i)=>$i['category']==='vip_point'))[0];InventoryService::addItems(1,(int)$item['code'],1);
    $before=VipService::status(1)['points'];KingdomService::action(1,['action'=>'inventory.use','item_code'=>$item['code'],'vip_points'=>999999]);vc(VipService::status(1)['points']===$before+$item['vip_points'],'item grants only catalog amount');
    rejected(fn()=>KingdomService::action(1,['action'=>'inventory.use','item_code'=>$item['code']]),'consumed item cannot be reused');
    $db->execute('UPDATE players SET vip_points=149999,vip_level=8 WHERE id=1');
    $db->execute('UPDATE cities SET food=0,lumber=0,stone=0,gold=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE player_id=1');
    $oldRate=BuildingData::getHourlyRate('farm',5,VipService::bonuses(8));VipService::addPoints(1,1);
    foreach($db->query('SELECT food FROM cities WHERE player_id=1')->fetchAll()as$c)vc(abs((float)$c['food']-$oldRate)<2,'all worlds settle offline production using previous VIP level');
    $buffs=BuffEngine::getBuffs(1);vc(abs($buffs['training_speed']-.05)<.00001&&abs($buffs['research_speed']-.2)<.00001,'VIP9 training and research reach gameplay buff map');
    $db->execute('UPDATE cities SET food=1000000,lumber=1000000,stone=1000000,gold=1000000 WHERE player_id=1');
    $state=CityState::loadForPlayer(1);$training=ResearchEffects::training(50100101,$buffs);TroopTrainer::train($state['city'],$state['buildings'],50100101,10);
    $seconds=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,started_at,finishes_at) FROM troop_queue WHERE city_id=1 AND is_processed=0')->fetchColumn();vc($seconds===(int)ceil(TroopData::trainingSeconds(50100101,10)/$training['speed_multiplier']),'training queue timestamp matches VIP preview');
    // The real handlers validate authentication/CSRF and persist the research duration.
    $property=new ReflectionProperty($fixture,'directory');$temp=$property->getValue($fixture);
    $token=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,'vip-test','127.0.0.1','VIP fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),1)",[$token]);
    $router="<?php define('ROOT_DIR',".var_export(ROOT_DIR,true).");require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');\\Conquer\\Db\\Connection::init(".var_export($temp,true).");if(parse_url(\$_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/research')\\Conquer\\Api\\Handlers\\ResearchHandler::start([]);else \\Conquer\\Api\\Handlers\\KingdomHandler::action([]);";
    $httpFiles=[$temp.'/router.php',$temp.'/server.log'];file_put_contents($httpFiles[0],$router);$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$base='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,$httpFiles[0]],[0=>['pipe','r'],1=>['file',$httpFiles[1],'a'],2=>['file',$httpFiles[1],'a']],$pipes,$temp);fclose($pipes[0]);
    $ready=false;for($i=0;$i<40;$i++){$probe=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errno,$error,.1);if($probe){fclose($probe);$ready=true;break;}usleep(50000);}vc($ready,'fixture HTTP server ready');
    $db->execute("UPDATE players SET last_vip_login='2020-01-01' WHERE id=1");$points=VipService::status(1)['points'];
    vipHttp('/action',['action'=>'vip.daily'],401,true,false);vipHttp('/action',['action'=>'vip.daily'],403,false);vc(VipService::status(1)['points']===$points,'unauthorized/CSRF request never awards points');
    $result=vipHttp('/action',['action'=>'vip.daily','points'=>999999]);vc($result['data']['state']['vip']['points']===$points+10&&$result['data']['state']['vip']['daily_claimed'],'HTTP daily response includes authoritative refreshed VIP status');vipHttp('/action',['action'=>'vip.daily'],422);
    vipHttp('/research',['code'=>'food_production','level_to'=>1]);$seconds=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,started_at,finishes_at) FROM research_queue WHERE player_id=1')->fetchColumn();$research=\Conquer\Game\Research\ResearchData::get('food_production')['levels'][0];vc($seconds===(int)round($research['time']*.8),'actual research queue receives VIP9 twenty percent time reduction');
    $db->execute('UPDATE players SET vip_points=20000000,vip_level=20 WHERE id=1');$s=VipService::status(1);vc($s['is_max']&&$s['points_remaining']===0&&$s['next_bonuses']===null,'maximum level avoids nonexistent next threshold');vc(BuildingData::getBuildTime('farm',10,$s['bonuses'])===1,'existing maximum build-time balance retained');
    VipService::addPoints(1,PHP_INT_MAX);vc(VipService::status(1)['points']===2147483647&&VipService::status(1)['level']===20,'large credit does not overflow signed database column');
    vc(VipService::status(1)['building_slots']===2,'VIP4+ unlocks second building slot');
    echo "PASS $checks VIP checks (boundaries, concurrent claims/credits, rollback, items, production, actual training).\n";
}catch(Throwable$e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}foreach($httpFiles as$file)if(is_file($file))unlink($file);$fixture->close();}exit($failed?1:0);
