<?php
declare(strict_types=1);

/** Mixed-army HTTP/service regressions; every fixture is in a disposable database. */
if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir() . '/conquer-march-composition.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\March\MarchArmy;
use Conquer\Game\March\MarchDispatcher;
use Conquer\Game\March\MarchTick;

function verify(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo 'PASS ' . $label . "\n"; }
function rejected(callable $fn, string $label): void {
    try { $fn(); } catch (RuntimeException|DomainException $e) { if($e instanceof PDOException)throw $e; verify(true, $label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function troops(int $city): array {
    return array_map('intval', Connection::getInstance()->query('SELECT troop_code,count FROM city_troops WHERE city_id=? AND count>0 ORDER BY troop_code', [$city])->fetchAll(PDO::FETCH_KEY_PAIR));
}
function composition(int $id): array {
    return json_decode(Connection::getInstance()->query('SELECT troops_json FROM marches WHERE id=?', [$id])->fetchColumn(), true, 32, JSON_THROW_ON_ERROR);
}
function request(string $route, array|string|null $payload = null): array {
    global $url, $sessionToken, $csrfToken;
    $h = curl_init($url . $route);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
        CURLOPT_COOKIE=>'conquer_session='.$sessionToken,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$csrfToken]]);
    if($payload!==null){curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_string($payload)?$payload:json_encode($payload,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION)]);}
    $raw=curl_exec($h); if($raw===false){throw new RuntimeException(curl_error($h));}
    $status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
    $json=json_decode($raw,true);if(!is_array($json)){throw new RuntimeException('Unexpected HTTP output: '.substr($raw,0,300));}
    return ['status'=>$status,'json'=>$json];
}
function copyTree(string $source,string $dest): void {
    mkdir($dest,0700,true);
    foreach(new DirectoryIterator($source)as$item){if($item->isDot()||$item->isLink())continue;$target=$dest.'/'.$item->getFilename();if($item->isDir())copyTree($item->getPathname(),$target);else copy($item->getPathname(),$target);}
}

$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host']??'', ['127.0.0.1','localhost'],true)){exit("Local MySQL only.\n");}
$name='conquer_march_test_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
$admin=null;$server=null;$exit=0;
try {
    $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source database name.');
    $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN)as$table){
        if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid source table name.');
        $admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
    }
    $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
    copyTree(ROOT_DIR.'/src',$root.'/src');copyTree(ROOT_DIR.'/data',$root.'/data');
    mkdir($root.'/config',0700,true);mkdir($root.'/logs',0700,true);
    $cfg['database']=$name;file_put_contents($root.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).";\n");
    file_put_contents($root.'/config/app.php',"<?php\nreturn ['env'=>'development','log_level'=>'ERROR'];\n");
    copy(ROOT_DIR.'/index.php',$root.'/index.php');
    file_put_contents($root.'/router.php',"<?php\n\$_SERVER['SCRIPT_NAME']='/index.php';require __DIR__.'/index.php';\n");
    $db=Connection::init($root);
    \Conquer\Game\World\WorldContext::bind(1);
    $db->execute('UPDATE worlds SET gather_factor=1,speed_factor=1 WHERE id=1');
    foreach(['0083_reward_overrides.sql','0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql','0087_charm_compatibility.sql'] as $migration)\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$migration));
    $player=random_int(400000000,450000000);$other=$player+1;
    foreach([$player,$other]as$i=>$id){
        $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'MarchFixture'.$i,'march'.$i.'@invalid.test','unused']);
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,?,?,50)',[$id,$id,'Mixed Army Fixture',50+$i]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code){$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,1)',[$id,$code]);}
    }
    $initial=[50100101=>10,50100201=>4,50200101=>8,50300101=>6];
    foreach($initial as$code=>$count){$db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,?,?)',[$player,$code,$count]);}
    foreach([51,52,53,54]as$x){$db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at)VALUES(1,?,51,1,1,10000,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))',[$x]);}
    $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current)VALUES(1,20209901,55,51,1)');
    $sessionToken=bin2hex(random_bytes(32));$csrfToken=bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at)VALUES(?,?,?,'127.0.0.1','Mixed army QA',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$player,$sessionToken,$csrfToken]);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No HTTP port.');$address=stream_socket_get_name($socket,false);fclose($socket);$url='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,null,['bypass_shell'=>true]);
    if(!is_resource($server))throw new RuntimeException('No HTTP server.');fclose($pipes[0]);usleep(250000);

    verify(MarchArmy::clean([50100101=>0,50200101=>2,50300101=>3])===[50200101=>2,50300101=>3],'zero entries are omitted while selected types and counts are preserved');
    foreach([[50100101=>-1],[50100101=>1.5],[50100101=>'2'],['50100101x'=>1],[123=>1],[],[50100101=>30000,50200101=>20001]]as$invalid){rejected(fn()=>MarchArmy::clean($invalid),'reject malformed, unknown, empty or over-cap army');}
    verify(array_sum(MarchArmy::clean([50100101=>30000,50200101=>20000]))===50000,'exact aggregate maximum of 50,000 is accepted');
    foreach(['/api/march/dispatch','/api/march/dispatch-gather']as$route){
        foreach([[50100101=>1.5],[50100101=>'1'],[50100101=>-1],['50100101x'=>1],[50100101=>30000,50200101=>20001]]as$invalid){
            $r=request($route,['target_x'=>$route==='/api/march/dispatch'?55:51,'target_y'=>51,'troops'=>$invalid]);verify($r['status']===400&&$r['json']['ok']===false,'HTTP '.$route.' rejects invalid composition without coercion');
        }
        foreach(['{','[]',json_encode(['target_x'=>'51','target_y'=>51,'troops'=>[50100101=>1]])]as$bad){verify(request($route,$bad)['status']===400,'HTTP army handler rejects malformed bodies or typed coordinates');}
    }
    verify(troops($player)===$initial,'validation failures never change the garrison');
    rejected(fn()=>MarchDispatcher::dispatchMonster($other,$player,50,50,55,51,[50100101=>1]),'monster service rejects another player city');
    rejected(fn()=>MarchDispatcher::dispatchGather($other,$player,51,51,selectedTroops:[50100101=>1]),'gather service rejects another player city');
    $r=request('/api/march/dispatch-gather',['target_x'=>51,'target_y'=>51,'troops'=>[50100101=>3,50200101=>999]]);
    verify($r['status']===400&&troops($player)===$initial,'unavailable mixed gather selection atomically rolls back earlier troop deductions');
    $selected=[50100201=>1,50200101=>3,50300101=>2];
    $r=request('/api/march/dispatch-gather',['target_x'=>51,'target_y'=>51,'troops'=>$selected,'troop_count'=>50000,'city_id'=>$other]);
    verify($r['status']===200,'mixed gather request accepts exactly owned types including an owned higher tier');$gather=(int)$r['json']['data']['march_id'];
    verify(composition($gather)===$selected,'gather persists explicit composition instead of replacing it with automatic selection');
    verify(troops($player)===[50100101=>10,50100201=>3,50200101=>5,50300101=>4],'only the chosen gather types are reserved');
    $attack=[50100101=>4,50100201=>1,50200101=>2];$r=request('/api/march/dispatch',['target_x'=>55,'target_y'=>51,'troops'=>$attack,'city_id'=>$other]);
    verify($r['status']===200,'mixed monster attack is accepted');$monster=(int)$r['json']['data']['march_id'];
    verify(composition($monster)===$attack,'monster march preserves exact chosen composition');
    $r=request('/api/march/dispatch-gather',['target_x'=>52,'target_y'=>51,'troop_count'=>3]);verify($r['status']===200,'legacy count-only gathering stays compatible');$legacy=(int)$r['json']['data']['march_id'];
    verify(composition($legacy)===[50100101=>3],'count-only gathering reserves exactly three available tier-one troops');
    $r=request('/api/march/dispatch-gather',['target_x'=>53,'target_y'=>51,'troops'=>[50100101=>1]]);verify($r['status']===400&&$r['json']['error']['code']==='march_slot_full','both selections share the normal three-slot march limit');
    // Arrival is not homecoming: gathering and the return journey both take time.
    // Accelerate only this disposable fixture, preserving each scheduled duration.
    $db->execute("UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND) WHERE player_id=?",[$player]);
    MarchTick::runForPlayer($player);
    $states=$db->query('SELECT id,state FROM marches WHERE player_id=? ORDER BY id',[$player])->fetchAll(PDO::FETCH_KEY_PAIR);
    verify($states===[$gather=>'arrived',$monster=>'complete',$legacy=>'arrived'],'monster army returns while both gathering armies are still working');
    verify(troops($player)===[50100101=>7,50100201=>3,50200101=>5,50300101=>4],'gathering troops remain deployed instead of being credited home early');
    $monsterHaul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$monster])->fetchColumn(),true);
    verify($monsterHaul['survivors']===$attack,'monster homecoming preserves every surviving troop type and count');
    $foodBefore=(int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn();
    $db->execute("UPDATE marches SET departure_time=DATE_SUB(departure_time,INTERVAL 120 SECOND),arrival_time=DATE_SUB(arrival_time,INTERVAL 120 SECOND),gathering_finishes_at=DATE_SUB(gathering_finishes_at,INTERVAL 120 SECOND) WHERE player_id=? AND state='arrived'",[$player]);
    MarchTick::runForPlayer($player);
    verify(troops($player)===$initial,'mixed armies return to their original types without duplication');
    $haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$gather])->fetchColumn(),true);
    // One T2 infantry carries 124; three cavalry and two archers carry 108 each.
    verify($haul['survivors']===$selected&&$haul['loot']['food']===664,'gather carry uses the six selected troops and preserves survivor composition');
    verify((int)$db->query("SELECT COUNT(*) FROM marches WHERE player_id=? AND state='complete'",[$player])->fetchColumn()===3,'offline settlement finishes gathering and both return journeys');
    $foodAfter=(int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn();
    verify($foodAfter-$foodBefore===664+324,'both gathering hauls are credited to the original city');
    $fieldStock=$db->query('SELECT coord_x,resource_amount FROM field_objects WHERE world_id=1 AND coord_y=51 AND coord_x IN (51,52) ORDER BY coord_x')->fetchAll(PDO::FETCH_KEY_PAIR);
    verify(array_map('intval',$fieldStock)===[51=>10000-664,52=>10000-324],'gathered resources are deducted from each field exactly once');
    MarchTick::runForPlayer($player);
    verify(troops($player)===$initial&&(int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn()===$foodAfter,'repeated settlement cannot duplicate returned troops or loot');
    // Maximum selection is validated against stored inventory, not a client flag.
    $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=? AND building_code='castle'",[$player]);
    $db->execute('UPDATE city_troops SET count=50000 WHERE city_id=? AND troop_code=50100101',[$player]);
    $r=request('/api/march/dispatch-gather',['target_x'=>54,'target_y'=>51,'troops'=>[50100101=>50000]]);
    verify($r['status']===200&&composition((int)$r['json']['data']['march_id'])===[50100101=>50000],'the actual 50,000-unit maximum is accepted and fully reserved');
    $r=request('/api/march/dispatch-gather',['target_x'=>53,'target_y'=>51,'troops'=>[50100101=>1]]);verify($r['status']===400,'already dispatched maximum cannot be spent again');
    // Completed research changes the real dispatcher and training queue, not just the preview.
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(?,1,'march_size',1),(?,1,'march_limit',1)",[$player,$player]);
    $db->execute('UPDATE city_troops SET count=51000 WHERE city_id=? AND troop_code=50100101',[$player]);
    $r=request('/api/march/dispatch-gather',['target_x'=>53,'target_y'=>51,'troops'=>[50100101=>50500]]);
    verify($r['status']===200&&array_sum(composition((int)$r['json']['data']['march_id']))===50500,'researched march capacity accepts and reserves more than 50,000 troops');
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(?,1,5,?,58,58,5,0,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$player,$player]);
    MarchDispatcher::assertSlotAvailable($player);
    verify(true,'additional-march research opens a fourth slot');
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(?,1,5,?,59,59,5,0,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$player,$player]);
    rejected(fn()=>MarchDispatcher::assertSlotAvailable($player),'four researched slots still enforce the actual limit');
    $db->execute('UPDATE cities SET food=1000000,lumber=1000000,stone=1000000,gold=1000000,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$player]);
    $trainingCity=$db->query('SELECT * FROM cities WHERE id=?',[$player])->fetch();
    $trainingBuildings=['barrack'=>['level'=>4],'castle'=>['level'=>6],'academy'=>['level'=>1]];
    rejected(fn()=>\Conquer\Game\City\TroopTrainer::train($trainingCity,$trainingBuildings,50100201,10),'school 4 cannot bypass the town center 7 requirement');
    $trainingBuildings['castle']['level']=7;
    \Conquer\Game\City\TroopTrainer::train($trainingCity,$trainingBuildings,50100201,10);
    verify((int)$db->query('SELECT count FROM troop_queue WHERE city_id=? AND troop_code=50100201 AND is_processed=0',[$player])->fetchColumn()===10,'school 4 and town center 7 enable real tier-two training without research');
    $r=request('/api/game/state?map_x=50&map_y=50&map_radius=60');
    verify($r['status']===200&&$r['json']['data']['map_center']===['x'=>50,'y'=>50,'radius'=>60],'world snapshot supports an explicit larger map viewport');
    $snapshot=$r['json']['data'];
    verify(count($snapshot['research_defs'])===117&&array_sum(array_map(static fn($n)=>count($n['levels']),$snapshot['research_defs']))===951,'actual client endpoint delivers all 117 research technologies and 951 levels');
    verify(array_intersect_key($snapshot['army_limits'],array_flip(['march_capacity','march_slots']))===['march_capacity'=>50500,'march_slots'=>4]&&count($snapshot['troop_defs'])===30,'client snapshot exposes researched army limits and all 30 troop definitions');
    verify(request('/api/game/state?map_x=50&map_y=50&map_radius=100')['status']===400,'out-of-range world viewport is rejected');
    echo "ALL MIXED MARCH AND RESEARCH INTEGRATION CHECKS PASSED (disposable database and HTTP server).\n";
} catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($admin&&preg_match('/^conquer_march_test_[a-f0-9]{12}$/D',$name)){$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
    $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
    if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_march_test_[a-f0-9]{12}$/D',basename($resolved))){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);
    }
}
exit($exit);
