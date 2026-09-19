<?php
declare(strict_types=1);
/** Focused persistence checks in a disposable copy of the schema; existing player data is never copied or modified. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-research-tests.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Expedition\ExpeditionService;
function liveResearchCheck(bool $ok,string $label): void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$config=require ROOT_DIR.'/config/database.php';
if(!in_array($config['host']??'',['127.0.0.1','localhost'],true))exit("Local schema only.\n");
$database='conquer_research_test_'.bin2hex(random_bytes(6));$tempRoot=sys_get_temp_dir().DIRECTORY_SEPARATOR.$database;$admin=null;$failed=false;
try{
    $source=$config['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source identifier');
    $admin=new PDO('mysql:host='.$config['host'].';port='.(int)($config['port']??3306).';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid table identifier');$admin->exec('CREATE TABLE `'.$database.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');}
    $admin->exec('INSERT INTO `'.$database.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
    mkdir($tempRoot.'/config',0700,true);$config['database']=$database;file_put_contents($tempRoot.'/config/database.php',"<?php\nreturn ".var_export($config,true).";\n");$db=Connection::init($tempRoot);
    foreach([101,102] as $pid){
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,'ResearchLive'.$pid,'research'.$pid.'@tests.invalid','unused']);
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,?,?,1,10000,10000,10000,5000)',[$pid,$pid,'Test city',$pid]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$pid,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10)',[$pid]);
    }
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(101,1,'infantry_atk',5)");
    $challenge=KingdomService::action(101,['action'=>'arena.challenge','opponent_id'=>102]);
    $result=KingdomService::action(102,['action'=>'arena.accept','challenge_id'=>$challenge['result']['challenge_id']]);
    liveResearchCheck($result['result']['battle']['challenger_score']===1128 && $result['result']['battle']['opponent_score']===1060,'live arena acceptance loads the challenger research and persists the improved score');
    liveResearchCheck((int)$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn()===20,'persisted arena duel leaves both armies untouched');
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,member_count) VALUES(11,1,'Research host','RHOST',101,1),(12,1,'Research guest','RGUEST',102,1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(11,101,'leader'),(12,102,'leader')");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(101,1,'rally_attack_amount',10),(101,1,'troop_speed_when_participating_a_rally',10)");
    $db->execute('UPDATE city_troops SET count=7100 WHERE city_id=101 AND troop_code=50100101');
    $raid=ExpeditionService::action(101,['action'=>'create'])['expedition_id'];
    ExpeditionService::action(101,['action'=>'invite','expedition_id'=>$raid,'alliance_id'=>12]);
    ExpeditionService::action(102,['action'=>'accept','expedition_id'=>$raid]);
    $dispatch=ExpeditionService::action(101,['action'=>'dispatch','expedition_id'=>$raid,'objective'=>'defenses','troops'=>[50100101=>7000]]);
    $mission=$db->query('SELECT TIMESTAMPDIFF(SECOND,created_at,arrival_at) AS outward,TIMESTAMPDIFF(SECOND,arrival_at,return_at) AS back FROM expedition_missions WHERE player_id=101')->fetch();
    liveResearchCheck((int)$mission['outward']===15 && (int)$mission['back']===15,'actual inserted mission timestamps use the researched outward and return durations');
    liveResearchCheck((int)$db->query('SELECT count FROM city_troops WHERE city_id=101 AND troop_code=50100101')->fetchColumn()===100,'the researched 7000-troop mission reserves the actual garrison');
    $state=ExpeditionService::state(101);
    liveResearchCheck($state['rules']['max_troops_per_mission']===7000 && $state['rules']['march_seconds']===15 && $state['rules']['return_seconds']===15,'live rule snapshot agrees with the actual reserved army and persisted travel times');
    echo "ALL RESEARCH LIVE SERVICE CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n");}
finally{
    if($admin!==null && preg_match('/^conquer_research_test_[a-f0-9]{12}$/D',$database))$admin->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if(is_file($tempRoot.'/config/database.php'))unlink($tempRoot.'/config/database.php');
    if(is_dir($tempRoot.'/config'))rmdir($tempRoot.'/config');if(is_dir($tempRoot))rmdir($tempRoot);
}
if($failed)exit(1);
