<?php
declare(strict_types=1);
/** Disposable-schema defense integration: no saved player data is written. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Defense\DefenseService as D;
use Conquer\Game\WorldRules;
use Conquer\Game\March\{MarchDispatcher as M,MarchTick};
use Conquer\Game\City\TroopData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;
function checkD(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
function rejectD(callable $f,string $message):void{try{$f();}catch(DomainException|RuntimeException $e){if($e instanceof PDOException)throw $e;checkD(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function stockD(int $id,int $code=50100101):int{return (int)Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$id,$code])->fetchColumn();}
function arriveD(int $id):void{$db=Connection::getInstance();$pid=(int)$db->query('SELECT player_id FROM marches WHERE id=?',[$id])->fetchColumn();$db->execute('UPDATE marches SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 11 SECOND) WHERE id=?',[$id]);MarchTick::runForPlayer($pid);}
function homeD(int $id):void{$db=Connection::getInstance();$pid=(int)$db->query('SELECT player_id FROM marches WHERE id=?',[$id])->fetchColumn();$db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);MarchTick::runForPlayer($pid);}
function rowD(string $table,int $id):array{return Connection::getInstance()->query('SELECT * FROM '.$table.' WHERE id=?',[$id])->fetch();}
function httpD(string $path,?array $body=null,bool $auth=true,bool $csrf=true):array{
    global $url,$token,$csrfToken;$h=curl_init($url.$path);$headers=['Content-Type: application/json'];if($csrf)$headers[]='X-CSRF-Token: '.$csrfToken;
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>$headers]);if($auth)curl_setopt($h,CURLOPT_COOKIE,'conquer_session='.$token);
    if($body!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION)]);
    $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);$json=json_decode((string)$raw,true);if(!is_array($json))throw new RuntimeException('Unexpected HTTP output: '.substr((string)$raw,0,300));return ['status'=>$status,'json'=>$json];
}
$cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))exit("Local DB required.\n");
$name='conquer_defense_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;$admin=null;$server=null;$exit=0;
try{
    $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Bad source DB name.');
    $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid table.');$admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');}
    mkdir($temp.'/config',0700,true);$cfg['database']=$name;file_put_contents($temp.'/config/database.php',"<?php return ".var_export($cfg,true).';');$db=Connection::init($temp);\Conquer\Logger::init($temp.'/test.log');
    foreach(['0066_community_systems.sql','0067_defense_and_promotions.sql','0068_progression_and_events.sql','0072_multiworld_context.sql','0073_city_anti_spy.sql','0090_training_buildings.sql'] as $migration)\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    $db->execute("INSERT INTO worlds(id,name,slug,map_size) VALUES(1,'Defense A','defense-a',256),(2,'Defense B','defense-b',320)");
    for($pid=1;$pid<=5;$pid++){
        $world=$pid<4?1:2;$x=[1=>30,2=>80,3=>95,4=>280,5=>290][$pid];
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",[$pid,'DefenseFixture'.$pid,'defense'.$pid.'@invalid.test']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update) VALUES(?,?,?,'Test city',?,40,2000000,2000000,2000000,2000000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$pid,$pid,$world,$x]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$pid,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,1000)',[$pid]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Defense Dawn','DD',1),(2,1,'Defense Dusk','DK',2)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(1,1,'leader'),(1,3,'member'),(2,2,'leader')");
    $now=time();checkD(!WorldRules::shieldActive(['is_shielded'=>1,'shield_expires_at'=>gmdate('Y-m-d H:i:s',$now-1)],$now),'expired shields never protect cities');
    checkD(WorldRules::shieldActive(['is_shielded'=>1,'shield_expires_at'=>null],$now),'legacy permanent admin shield remains valid');
    checkD(WorldRules::shieldActive(['is_shielded'=>0,'beginner_shield_until'=>gmdate('Y-m-d H:i:s',$now+60)],$now),'beginner protection works without city shield flag');
    $fraction=D::protectedResources(['food'=>1000,'lumber'=>2000,'stone'=>3000,'gold'=>4000],['resource_protect'=>.1,'resource_protection'=>.2]);
    checkD($fraction===['food'=>300,'lumber'=>600,'stone'=>900,'gold'=>1200],'research and relic protection protect exact bounded shares');
    checkD(D::protectionFraction(['resource_protect'=>2])===1.0,'resource protection cannot exceed 100 percent');
    rejectD(fn()=>D::action(1,['action'=>'wall.repair','city_id'=>2,'hp_amount'=>100]),'all army actions reject foreign city ownership');
    rejectD(fn()=>D::activateShield(1,2,3600),'shield service validates ownership');
    D::activateShield(2,2,3600);rejectD(fn()=>M::dispatchPlayerAttack(1,1,0,0,80,40,[50100101=>10]),'dispatch rejects a timed shield');
    $db->execute('UPDATE cities SET shield_expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=2');D::activateShield(1,1,600);
    $march=M::dispatchPlayerAttack(1,1,999,999,80,40,[50100101=>10]);checkD(stockD(1)===990&&rowD('cities',1)['shield_expires_at']===null,'successful attack reserves exact troops and removes own shield expiry');
    rejectD(fn()=>D::activateShield(1,1,600),'outgoing attack prevents re-shielding');D::activateShield(2,2,600);$before=stockD(2);arriveD($march);
    checkD(stockD(2)===$before&&rowD('marches',$march)['state']==='returning','shield acquired in transit cancels battle at arrival');homeD($march);homeD($march);checkD(stockD(1)===1000,'cancelled attack returns troops exactly once');
    WorldRules::relinquishShield(2,2);
    $db->execute("INSERT INTO alliance_diplomacy(alliance_id,target_id,relation,initiated_by) VALUES(1,2,'nap',1),(2,1,'nap',2)");
    rejectD(fn()=>M::dispatchPlayerAttack(1,1,0,0,80,40,[50100101=>10]),'mutual nonaggression treaty blocks city attack');$db->execute('DELETE FROM alliance_diplomacy');
    $march=M::dispatchPlayerAttack(1,1,0,0,80,40,[50100101=>10]);$db->execute("INSERT INTO alliance_diplomacy(alliance_id,target_id,relation,initiated_by) VALUES(1,2,'ally',1),(2,1,'ally',2)");arriveD($march);checkD(stockD(2)===$before,'new reciprocal alliance treaty cancels an arriving attack');homeD($march);$db->execute('DELETE FROM alliance_diplomacy');
    $db->execute("UPDATE city_buildings SET level=3 WHERE city_id=2 AND building_code='wall'");$db->execute('UPDATE cities SET wall_hp_current=3000,wall_hp_max=5000,wall_last_update=UTC_TIMESTAMP() WHERE id=2');$wall=D::syncWall(2);
    checkD($wall['wall_hp_max']===15000&&$wall['wall_hp_current']===13000,'wall upgrades add HP while preserving existing damage');
    $db->execute('UPDATE cities SET wall_hp_current=13000,wall_last_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=2');$wall=D::syncWall(2);checkD($wall['wall_hp_current']===14500,'wall regenerates at ten percent maximum HP per hour');
    $before=rowD('cities',2);$repair=D::repairWall(2,2,9999);$after=rowD('cities',2);checkD($repair['hp_repaired']===500&&(int)$before['stone']-(int)$after['stone']===100&&(int)$before['lumber']-(int)$after['lumber']===50,'capped repair charges actual schema stone and lumber');
    rejectD(fn()=>D::repairWall(2,2,10),'full wall cannot charge resources');
    $db->execute('UPDATE cities SET wall_hp_current=100,stone=0,lumber=0,wall_last_update=UTC_TIMESTAMP() WHERE id=2');rejectD(fn()=>D::repairWall(2,2,500),'repair fails atomically when resources are unavailable');checkD((int)rowD('cities',2)['wall_hp_current']===100,'failed repair leaves HP unchanged');
    $db->execute('UPDATE cities SET wall_hp_current=8321,wall_hp_max=15000,wall_last_update=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),stone=333,lumber=222,food=111,gold=444 WHERE id=2');
    $treasures=\Conquer\Game\Treasure\TreasureData::all();$treasure=reset($treasures);$treasureCode=(int)$treasure['code'];$db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments,equipped_slot) VALUES(2,?,1000,1)',[$treasureCode]);$db->execute('INSERT INTO player_treasure_loadouts(player_id,world_id,slot,treasure_code) VALUES(2,1,1,?)',[$treasureCode]);
    $scout=M::dispatchScout(1,1,0,0,80,40);arriveD($scout);arriveD($scout);$report=$db->query("SELECT data_json FROM battle_reports WHERE march_id=? AND outcome='scouted'",[$scout])->fetchColumn();checkD($report!==false,'successful scout generates a real report');$report=json_decode($report,true);
    checkD($report['wall']['durability']===8321&&$report['wall']['durability_max']===15000&&$report['wall']['defense_buff']!==42,'scout reports exact wall HP and actual defense bonus');
    checkD($report['resources']['food']===111&&$report['troops'][50100101]===1000&&count($report['treasures'])===1&&is_array($report['mastery']),'scout exposes actual troops resources equipped treasures and mastery');
    checkD((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$scout])->fetchColumn()===1,'scout settlement is exactly once');homeD($scout);
    $scout=M::dispatchScout(1,1,0,0,80,40);D::activateShield(2,2,600);arriveD($scout);checkD((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$scout])->fetchColumn()===0,'shield obtained in transit prevents scout information leakage');homeD($scout);WorldRules::relinquishShield(2,2);
    foreach([[50100101=>1.5],[50100101=>'5'],[50100101=>-1],[999=>1],[50100101=>100000]] as $troops)rejectD(fn()=>M::dispatchReinforce(1,1,0,0,95,40,3,3,$troops),'reinforcement strictly rejects malformed or oversized composition');
    rejectD(fn()=>M::dispatchReinforce(1,1,0,0,80,40,2,2,[50100101=>10]),'reinforcement rejects a nonmember');
    rejectD(fn()=>M::dispatchReinforce(1,2,0,0,95,40,3,3,[50100101=>10]),'reinforcement rejects foreign origin');
    $before=stockD(1);$m=M::dispatchReinforce(1,1,0,0,95,40,3,3,[50100101=>20]);checkD(stockD(1)===$before-20,'reinforcement reserves full selected troops');arriveD($m);arriveD($m);$r=$db->query("SELECT * FROM reinforcements WHERE march_id=?",[$m])->fetch();
    checkD((int)$r['sender_id']===1&&json_decode($r['troops_json'],true)[50100101]===20&&rowD('marches',$m)['state']==='arrived','reinforcement arrives using correct sender schema exactly once');
    rejectD(fn()=>D::recallReinforcement(3,(int)$r['id']),'recipient cannot recall foreign reinforcement');D::recallReinforcement(1,(int)$r['id']);checkD(stockD(1)===$before-20&&rowD('marches',$m)['state']==='returning','recall starts real travel with no instant troop credit');rejectD(fn()=>D::recallReinforcement(1,(int)$r['id']),'duplicate reinforcement recall cannot mint troops');homeD($m);homeD($m);checkD(stockD(1)===$before,'reinforcement return credits original troops once');
    $m=M::dispatchReinforce(1,1,0,0,95,40,3,3,[50100101=>20]);$db->execute('DELETE FROM alliance_members WHERE player_id=3');arriveD($m);checkD(rowD('marches',$m)['state']==='returning','alliance departure in transit returns reinforcement');homeD($m);$db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(1,3,'member')");
    $m=M::dispatchReinforce(1,1,0,0,95,40,3,3,[50100101=>20]);$db->execute('UPDATE cities SET coord_x=96 WHERE id=3');arriveD($m);checkD(rowD('marches',$m)['state']==='returning','city relocation in transit returns reinforcement without corrupting target');homeD($m);$db->execute('UPDATE cities SET coord_x=95 WHERE id=3');
    $m=M::dispatchReinforce(1,1,0,0,95,40,3,3,[50100101=>20]);D::recallMarch(1,$m);checkD(stockD(1)===$before-20,'normal recall preserves its outgoing army until return');homeD($m);homeD($m);checkD(stockD(1)===$before,'normal recall no longer deletes or duplicates troops');
    D::action(1,['action'=>'formation.save','slot'=>1,'name'=>'Mixed army','troops'=>[50100101=>600,50300501=>200]]);checkD(stockD(1)===$before,'saving future troop presets never reserves troops');
    rejectD(fn()=>D::action(1,['action'=>'formation.save','slot'=>5,'name'=>'Bad','troops'=>[50100101=>1]]),'formation slot range is enforced');rejectD(fn()=>D::action(1,['action'=>'formation.save','slot'=>1,'name'=>'Bad','troops'=>[50100101=>'2']]),'formation counts must be integers');
    D::action(3,['action'=>'formation.delete','slot'=>1]);checkD((int)$db->query('SELECT COUNT(*) FROM troop_formations WHERE player_id=1')->fetchColumn()===1,'players cannot delete another player formation');D::action(1,['action'=>'formation.delete','slot'=>1]);
    rejectD(fn()=>D::promote(1,1,50100101,10),'promotion requires school and town center levels');$db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1 AND building_code IN ('castle','barrack')");
    $before=stockD(1);$p=D::promote(1,1,50100101,10);checkD(stockD(1)===$before-10&&stockD(1,50100201)===0,'promotion deducts source troops while next tier trains');
    rejectD(fn()=>D::promote(1,1,50100101,1),'promotion respects shared single training slot');rejectD(fn()=>D::cancelPromotion(3,$p['promotion_id']),'foreign promotion cannot be cancelled');
    $funds=rowD('cities',1);D::cancelPromotion(1,$p['promotion_id']);$refund=rowD('cities',1);checkD(stockD(1)===$before&&(int)$refund['food']-(int)$funds['food']===$p['cost']['food'],'promotion cancellation returns source troops and actual cost');rejectD(fn()=>D::cancelPromotion(1,$p['promotion_id']),'promotion cannot be refunded twice');
    $p=D::promote(1,1,50100101,10);$db->execute('UPDATE defense_promotions SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$p['promotion_id']]);D::processPromotions(1);D::processPromotions(1);checkD(stockD(1)===$before-10&&stockD(1,50100201)===10,'promotion completion credits next tier once');
    $db->execute('UPDATE city_troops SET count=0 WHERE city_id=2');$db->execute('UPDATE cities SET food=1000,lumber=0,stone=0,gold=0,wall_hp_current=15000,wall_last_update=UTC_TIMESTAMP() WHERE id=2');$db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(2,1,'production_resource_protect',5)");
    $protected=D::protectedResources(rowD('cities',2),BuffEngine::getBuffs(2));$m=M::dispatchPlayerAttack(1,1,0,0,80,40,[50100101=>100]);arriveD($m);$haul=json_decode(rowD('marches',$m)['haul_json'],true);checkD(($haul['loot']['food']??-1)===(int)floor((1000-$protected['food'])*.2),'actual PvP loot applies researched resource protection');checkD((int)rowD('cities',2)['wall_hp_current']===13500,'successful PvP battle actually damages wall HP');homeD($m);
    $db->execute('UPDATE cities SET wall_hp_current=1,wall_last_update=UTC_TIMESTAMP() WHERE id=2');$m=M::dispatchPlayerAttack(1,1,0,0,80,40,[50100101=>10]);arriveD($m);$moved=rowD('cities',2);checkD(((int)$moved['coord_x']!==80||(int)$moved['coord_y']!==40)&&(int)$moved['wall_hp_current']===15000,'wall break relocates city and restores its current maximum');checkD(\Conquer\Game\Map\WorldPlacement::canPlace($db,1,'city',(int)$moved['coord_x'],(int)$moved['coord_y'],2),'wall break reserves a dry legal city footprint');homeD($m);
    $db->execute('UPDATE city_troops SET count=0 WHERE city_id=5');$m=WorldContext::run(2,fn()=>M::dispatchPlayerAttack(4,4,0,0,290,40,[50100101=>10]));checkD((int)rowD('marches',$m)['world_id']===2,'city marches use actual world and support coordinates beyond 255');arriveD($m);checkD((int)$db->query('SELECT world_id FROM battle_reports WHERE march_id=? LIMIT 1',[$m])->fetchColumn()===2,'combat reports preserve origin world');homeD($m);
    $state=D::state(1);checkD(count($state['troops'])===30&&$state['city_id']===1&&count($state['targets'])===2,'defense state supplies all thirty units and same-world targets');
    require __DIR__.'/Support/lower_world_cases.php';
    $token=bin2hex(random_bytes(32));$csrfToken=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at) VALUES(1,?,?,'127.0.0.1','defense fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$token,$csrfToken]);
    $router="<?php declare(strict_types=1); define('ROOT_DIR',".var_export(ROOT_DIR,true).");require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');\\Conquer\\Db\\Connection::init(__DIR__);\\Conquer\\Logger::init(__DIR__.'/http.log');\n";
    $router.='if(preg_match("~^/api/(build|train|research)/cancel/(\\d+)$~",parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH),$m)){ $p=["queue_id"=>(int)$m[2]];match($m[1]){"build"=>\\Conquer\\Api\\Handlers\\CityHandler::cancelBuild($p),"train"=>\\Conquer\\Api\\Handlers\\TroopHandler::cancelTrain($p),"research"=>\\Conquer\\Api\\Handlers\\ResearchHandler::cancel($p)};exit;}';
    $router.='switch(parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH)){case "/api/player/me":\\Conquer\\Api\\Handlers\\PlayerHandler::me([]);break;case "/api/player/profile/1":\\Conquer\\Api\\Handlers\\PlayerHandler::profile(["id"=>1]);break;case "/api/progression/state":\\Conquer\\Api\\Handlers\\ProgressionHandler::state([]);break;case "/api/progression/action":\\Conquer\\Api\\Handlers\\ProgressionHandler::action([]);break;case "/api/defense/state":\\Conquer\\Api\\Handlers\\DefenseHandler::state([]);break;case "/api/defense/action":\\Conquer\\Api\\Handlers\\DefenseHandler::action([]);break;case "/api/research/start":\\Conquer\\Api\\Handlers\\ResearchHandler::start([]);break;case "/api/march/reinforce":\\Conquer\\Api\\Handlers\\MarchHandler::dispatchReinforce([]);break;default:http_response_code(404);echo "{}";}';
    file_put_contents($temp.'/router.php',$router);$socket=stream_socket_server('tcp://127.0.0.1:0',$err,$message);if(!$socket)throw new RuntimeException('No local HTTP port');$address=stream_socket_get_name($socket,false);fclose($socket);$url='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$temp,$temp.'/router.php'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$temp,null,['bypass_shell'=>true]);if(!is_resource($server))throw new RuntimeException('HTTP fixture could not start');fclose($pipes[0]);usleep(200000);
    checkD(httpD('/api/defense/state',null,false)['status']===401,'defense read requires an authenticated session');
    checkD(httpD('/api/defense/action',['action'=>'formation.delete','slot'=>1],true,false)['status']===403,'defense mutations require CSRF');
    checkD(httpD('/api/defense/action',['action'=>'wall.repair','city_id'=>2,'hp_amount'=>1])['status']===403,'HTTP defense action rejects a foreign city');
    checkD(httpD('/api/defense/action',['action'=>'promotion.start','troop_code'=>50100101,'count'=>1.5])['status']===422,'HTTP defense action rejects fractional counts');
    checkD(httpD('/api/defense/state?city_id=4')['status']===403,'HTTP defense read cannot expose another city');
    checkD(httpD('/api/defense/state')['status']===200,'authenticated defense read returns a usable state');
    $before=stockD(1);$http=httpD('/api/march/reinforce',['target_player_id'=>3,'troops'=>[50100101=>10]]);checkD($http['status']===200&&stockD(1)===$before-10,'legacy reinforcement route accepts real city troop read model');$m=(int)$http['json']['data']['march_id'];D::recallMarch(1,$m);homeD($m);
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(101,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$bq=$db->lastInsertId();
    $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at) VALUES(101,50100101,1,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$tq=$db->lastInsertId();
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at) VALUES(1,2,'food_production',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$rq=$db->lastInsertId();
    foreach(['build'=>$bq,'train'=>$tq,'research'=>$rq] as $kind=>$id){
        $denied=httpD('/api/'.$kind.'/cancel/'.$id,[]);
        checkD($denied['status']===($kind==='build'?422:404)&&($denied['json']['ok']??true)===false,'HTTP '.$kind.' cancellation rejects own queue in another world');
    }
    checkD((int)$db->query('SELECT COUNT(*) FROM building_queue WHERE id=?',[$bq])->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM troop_queue WHERE id=?',[$tq])->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM research_queue WHERE id=?',[$rq])->fetchColumn()===1,'denied cross-world cancellations leave every queue intact');
    $db->execute('UPDATE sessions SET active_world_id=2 WHERE token=?',[$token]);
    $http=httpD('/api/defense/state');checkD($http['status']===200&&$http['json']['data']['city_id']===101,'authenticated session selects second world city');
    $http=httpD('/api/player/me');checkD($http['status']===200&&$http['json']['data']['world_id']===2&&$http['json']['data']['lord_xp']===\Conquer\Game\Player\LordLevel::snapshot(1,2)['xp'],'player HUD endpoint uses world-specific lord XP');
    $http=httpD('/api/player/profile/1');checkD($http['status']===200&&$http['json']['data']['world_id']===2&&$http['json']['data']['lord_level']===\Conquer\Game\Player\LordLevel::snapshot(1,2)['level'],'public profile uses selected world lord level');
    checkD(httpD('/api/progression/state',null,false)['status']===401,'talent state requires authentication');
    $talentBody=['action'=>'mastery.apply','ranks'=>['defense_0'=>1],'revision'=>0,'expected_world_id'=>2,'operation_key'=>'lord_http_apply_00001'];
    checkD(httpD('/api/progression/action',$talentBody,true,false)['status']===403,'talent apply requires CSRF');
    checkD(httpD('/api/progression/action',array_replace($talentBody,['expected_world_id'=>1]))['status']===422,'talent apply rejects an old-world request');
    $http=httpD('/api/progression/action',$talentBody);checkD($http['status']===200&&$http['json']['data']['mastery']['spent']===1,'authenticated talent plan persists through actual HTTP handler');
    $http=httpD('/api/progression/action',$talentBody);checkD($http['status']===200&&$http['json']['data']['mastery']['revision']===1,'HTTP retry returns receipt without another talent mutation');

    foreach(['build'=>$bq,'train'=>$tq,'research'=>$rq] as $kind=>$id)checkD(httpD('/api/'.$kind.'/cancel/'.$id,[])['status']===200,'HTTP '.$kind.' cancellation works in selected second world');
    $db->execute("UPDATE worlds SET status='closed' WHERE id=2");checkD(httpD('/api/research/start',['code'=>'food_production','level_to'=>2])['status']===409,'HTTP closed world cannot start research');
    checkD(httpD('/api/defense/action',['action'=>'scout','target_city_id'=>102])['status']===409,'HTTP closed world cannot dispatch scouting');
    $db->execute('UPDATE sessions SET active_world_id=1 WHERE token=?',[$token]);
    echo "ALL DEFENSE LIFECYCLE CHECKS PASSED (isolated database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($admin&&preg_match('/^conquer_defense_test_[a-f0-9]{12}$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    $resolved=realpath($temp);$base=realpath(sys_get_temp_dir());if($resolved&&$base&&str_replace('\\','/',$resolved)===str_replace('\\','/',$base).'/'.$name&&preg_match('/^conquer_defense_test_[a-f0-9]{12}$/D',basename($resolved))){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($resolved);}
}
exit($exit);
