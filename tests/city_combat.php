<?php
declare(strict_types=1);

/** City PvP and rally HTTP/service regressions; every fixture is in a disposable database. */
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
    try { $fn(); } catch (RuntimeException) { verify(true, $label); return; }
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
    // Each assertion is an independent command, not a rate-limit/replay test.
    Connection::getInstance()->execute('DELETE FROM security_rate_limits');
    if (is_array($payload)) $payload['operation_key'] ??= bin2hex(random_bytes(16));
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
$name='conquer_city_test_'.bin2hex(random_bytes(6));
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
    // These combat fixtures predate progressive zones and require all targets open.
    \Conquer\Game\World\LandProgressService::ensureWorld(1,true);
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


    $friend=$player+2;$stranger=$player+3;
    foreach([$friend,$stranger] as $i=>$id){
        $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$id,'CityCombat'.$i,'citycombat'.$i.'@invalid.test','unused']);
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,?,?,50)',[$id,$id,'Combat fixture',54+$i]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,1)',[$id,$code]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,100)',[$id]);
    }
    $db->execute("INSERT INTO alliances(name,tag,leader_id)VALUES('Combat Test','CMT',?)",[$player]);$alliance=$db->lastInsertId();
    foreach([$player,$friend] as $id)$db->execute("INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(?,?,?)",[$alliance,$id,$id===$player?'leader':'member']);
    $db->execute('UPDATE city_troops SET count=100 WHERE city_id=?',[$player]);
    $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,10)',[$other]);
    $db->execute('UPDATE cities SET food=20000,lumber=20000,stone=20000,gold=20000 WHERE id=?',[$other]);
    $before=troops($player);
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($stranger,$player,50,50,51,50,[50100101=>5]),'solo service rejects foreign origin ownership');
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($player,$player,50,50,50,50,[50100101=>5]),'solo rejects own city');
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($player,$player,50,50,54,50,[50100101=>5]),'solo rejects alliance member');
    $db->execute('UPDATE cities SET is_shielded=1 WHERE id=?',[$other]);
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($player,$player,50,50,51,50,[50100101=>5]),'solo respects city shield');
    $db->execute('UPDATE cities SET is_shielded=0 WHERE id=?',[$other]);
    $db->execute('UPDATE players SET beginner_shield_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?',[$other]);
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($player,$player,50,50,51,50,[50100101=>5]),'solo respects beginner shield');
    $db->execute('UPDATE players SET beginner_shield_until=NULL WHERE id=?',[$other]);
    foreach(['/api/march/dispatch-player','/api/rally/start'] as $route){
        foreach([[50100101=>1.5],[50100101=>'1'],[50100101=>-1],['50100101x'=>1],[]] as $invalid){
            $r=request($route,['target_x'=>51,'target_y'=>50,'target_player_id'=>$other,'troops'=>$invalid,'rally_minutes'=>1]);
            verify($r['status']===400,'HTTP rejects malformed composition for '.$route);
        }
        verify(request($route,['target_x'=>'51','target_y'=>50,'target_player_id'=>$other,'troops'=>[50100101=>2],'rally_minutes'=>1])['status']===400,'HTTP rejects typed coordinates for '.$route);
    }
    $r=request('/api/march/dispatch-player',['target_x'=>51,'target_y'=>50,'troops'=>[50100101=>5,50200101=>1000]]);
    verify($r['status']===400&&troops($player)===$before,'failed mixed reserve rolls back all garrison changes');
    $db->execute('UPDATE cities SET is_shielded=1 WHERE id=?',[$player]);
    $db->execute('UPDATE players SET beginner_shield_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?',[$player]);
    $selected=[50100101=>20,50200101=>10,50300101=>10];
    $r=request('/api/march/dispatch-player',['target_x'=>51,'target_y'=>50,'troops'=>$selected]);verify($r['status']===200,'HTTP solo dispatch accepted');$march=(int)$r['json']['data']['march_id'];
    verify(composition($march)===$selected,'solo stores exact mixed composition');
    verify((int)$db->query('SELECT is_shielded FROM cities WHERE id=?',[$player])->fetchColumn()===0&&$db->query('SELECT beginner_shield_until FROM players WHERE id=?',[$player])->fetchColumn()===null,'successful attack relinquishes own protection');
    $db->execute("UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND) WHERE id=?",[$march]);
    MarchTick::runForPlayer($player);
    $row=$db->query('SELECT * FROM marches WHERE id=?',[$march])->fetch();verify($row['state']==='returning','solo resolves and starts actual return');$haul=json_decode($row['haul_json'],true);
    verify(array_sum($haul['survivors'])===36&&array_sum($haul['loot'])>0,'real stronger army wins with capped loot and survivors');
    verify((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$march])->fetchColumn()===2,'solo creates attacker and defender reports');
    $battleRows=$db->query('SELECT * FROM battle_reports WHERE march_id=? ORDER BY id',[$march])->fetchAll();
    $attackData=json_decode($battleRows[0]['data_json'],true);$defenseData=json_decode($battleRows[1]['data_json'],true);
    $combat=$attackData['combat'];
    verify($combat===$defenseData['combat']&&$attackData['perspective']==='attacker'&&$defenseData['perspective']==='defender','both perspectives keep the identical historical battle snapshot');
    verify($combat['attacker']['totals']['sent']===40&&$combat['defender']['totals']['sent']===10,'report includes actual original troop counts on both sides');
    verify($combat['attacker']['totals']['survived']===36&&$combat['defender']['totals']['survived']===7,'both report survivor totals match combat settlement');
    foreach(['attacker','defender']as$side){
        $s=$combat[$side];verify($s['totals']['sent']===$s['totals']['dead']+$s['totals']['injured']+$s['totals']['survived'],$side.' troop totals conserve every troop');
        verify(abs($s['score']-array_sum(array_column($s['types'],'strength')))<=2,$side.' type strengths sum to actual combat score');
    }
    $saved=$battleRows[0]['data_json'];$oldName=$db->query('SELECT username FROM players WHERE id=?',[$player])->fetchColumn();
    $db->execute("UPDATE players SET username='RenamedAfterBattle' WHERE id=?",[$player]);
    $detail=request('/api/battle/report/'.$battleRows[0]['id']);
    verify($detail['status']===200&&$detail['json']['data']['report']['data']['combat']===$combat,'authenticated report read retains battle-time names and bonuses');
    verify(request('/api/battle/report/'.$battleRows[1]['id'])['status']===404,'attacker cannot read defender personal report');
    $db->execute('UPDATE players SET username=? WHERE id=?',[$oldName,$player]);
    verify($db->query('SELECT data_json FROM battle_reports WHERE id=?',[$battleRows[0]['id']])->fetchColumn()===$saved,'reading a report never rebuilds its historical data');
    MarchTick::runForPlayer($player);verify((int)$db->query('SELECT COUNT(*) FROM battle_reports WHERE march_id=?',[$march])->fetchColumn()===2,'solo cannot resolve twice');
    $db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);MarchTick::runForPlayer($player);$returned=troops($player);MarchTick::runForPlayer($player);verify(troops($player)===$returned,'solo survivors return only once');

    $r=request('/api/rally/start',['target_player_id'=>$other,'target_x'=>51,'target_y'=>50,'troops'=>[50100101=>20],'rally_minutes'=>1,'message'=>'Fixture rally']);
    verify($r['status']===200,'HTTP rally start is no longer retired');$rally=(int)$r['json']['data']['rally_id'];
    $afterLeader=troops($player);rejected(fn()=>\Conquer\Game\Rally\RallyService::join($stranger,$stranger,$rally,[50100101=>10]),'only same-alliance armies may join');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::join($friend,$player,$rally,[50100101=>10]),'rally joining cannot debit a foreign city');
    \Conquer\Game\Rally\RallyService::join($friend,$friend,$rally,[50100101=>20]);$afterFriend=troops($friend);
    $rallyMarch=\Conquer\Game\Rally\RallyService::activeMarchesForPlayer($friend)[0];verify($rallyMarch['state']==='gathering'&&$rallyMarch['origin_x']===50&&json_decode($rallyMarch['troops_json'],true)[50100101]===40,'rally map snapshot shows actual combined army at leader origin');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::join($friend,$friend,$rally,[50100101=>20]),'duplicate joining rejected');
    verify(troops($friend)===$afterFriend,'duplicate join preserves garrison');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::launch($rally,$friend),'only captain may launch');
    $list=request('/api/rally/list');verify($list['status']===200&&count($list['json']['data']['rallies'][0]['participants'])===1,'HTTP rally list exposes participant and troop contract');
    $detail=request('/api/rally/'.$rally);verify($detail['status']===200&&$detail['json']['data']['rally']['troops'][50100101]===20,'HTTP detail preserves leader composition');
    verify(request('/api/rally/'.$rally.'/launch','{}')['status']===200,'HTTP captain launches rally');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::join($friend,$friend,$rally,[50100101=>1]),'late joining rejected');
    $db->execute("UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE id=?",[$rally]);
    \Conquer\Game\Rally\RallyService::tick();$row=$db->query('SELECT * FROM rallies WHERE id=?',[$rally])->fetch();$result=json_decode($row['result_json'],true);
    verify($row['status']==='returning'&&count($result['armies'])===2&&!$result['cancelled'],'combined two-player rally resolves with both real armies');
    $reportCount=(int)$db->query("SELECT COUNT(*) FROM battle_reports WHERE JSON_EXTRACT(data_json,'$.rally_id')=?",[$rally])->fetchColumn();verify($reportCount===3,'each rally participant and defender get a report');
    $rallyData=json_decode($db->query("SELECT data_json FROM battle_reports WHERE JSON_EXTRACT(data_json,'$.rally_id')=? ORDER BY id LIMIT 1",[$rally])->fetchColumn(),true);
    verify(count($rallyData['combat']['attacker']['armies'])===2&&$rallyData['combat']['attacker']['totals']['sent']===40,'rally report compares all participating armies rather than only its reader');
    \Conquer\Game\Rally\RallyService::tick();verify((int)$db->query("SELECT COUNT(*) FROM battle_reports WHERE JSON_EXTRACT(data_json,'$.rally_id')=?",[$rally])->fetchColumn()===3,'rally battle runs once');
    $db->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rally]);\Conquer\Game\Rally\RallyService::tick();$leaderEnd=troops($player);$friendEnd=troops($friend);\Conquer\Game\Rally\RallyService::tick();
    verify(troops($player)===$leaderEnd&&troops($friend)===$friendEnd,'rally return and payout are once only');
    verify($leaderEnd[50100101]===$afterLeader[50100101]+18&&$friendEnd[50100101]===$afterFriend[50100101]+18,'each owner receives exactly own surviving troops');

    $rally=\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>10],1,'Cancel fixture');
    \Conquer\Game\Rally\RallyService::join($friend,$friend,$rally,[50100101=>10]);rejected(fn()=>\Conquer\Game\Rally\RallyService::cancel($rally,$friend),'participant cannot cancel leader rally');
    verify(request('/api/rally/'.$rally.'/cancel','{}')['status']===200,'HTTP captain cancels gathering rally');
    verify(troops($player)===$leaderEnd&&troops($friend)===$friendEnd,'cancellation returns every owned army');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::cancel($rally,$player),'duplicate cancellation cannot duplicate troops');
    $rally=\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>10],1,'Shield fixture');\Conquer\Game\Rally\RallyService::launch($rally,$player);
    $db->execute('UPDATE cities SET is_shielded=1 WHERE id=?',[$other]);$db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND),return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rally]);
    \Conquer\Game\Rally\RallyService::tick();$result=json_decode($db->query('SELECT result_json FROM rallies WHERE id=?',[$rally])->fetchColumn(),true);
    verify($result['cancelled']===true&&troops($player)===$leaderEnd,'shield acquired in transit aborts battle and refunds full army');
    $db->execute('UPDATE cities SET is_shielded=0 WHERE id=?',[$other]);
    $worker=<<<'PHP'
<?php
define('ROOT_DIR',__DIR__);
require __DIR__.'/src/Autoloader.php';(new \Conquer\Autoloader(__DIR__.'/src'))->register();
date_default_timezone_set('UTC');\Conquer\Logger::init(__DIR__.'/worker.log','ERROR');\Conquer\Db\Connection::init(__DIR__);
try{if($argv[1]==='cancel')\Conquer\Game\Rally\RallyService::cancel((int)$argv[2],(int)$argv[3]);else \Conquer\Game\Rally\RallyService::tick();exit(0);}catch(Throwable $e){exit(2);}
PHP;
    file_put_contents($root.'/worker.php',$worker);
    $parallel=static function(string $mode,int $id)use($root,$player):array{$workers=[];for($i=0;$i<2;$i++){$proc=proc_open([PHP_BINARY,$root.'/worker.php',$mode,(string)$id,(string)$player],[0=>['pipe','r'],1=>['file',$root.'/parallel.log','a'],2=>['file',$root.'/parallel.log','a']],$pipes,$root,null,['bypass_shell'=>true]);fclose($pipes[0]);$workers[]=$proc;}return array_map(fn($p)=>proc_close($p),$workers);};
    $baseline=troops($player);$rally=\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>10],1,'Concurrent cancel');
    $outcomes=$parallel('cancel',$rally);sort($outcomes);verify($outcomes===[0,2]&&troops($player)===$baseline,'parallel cancellation permits exactly one refund');
    $rally=\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>20],1,'Concurrent tick');\Conquer\Game\Rally\RallyService::launch($rally,$player);
    $db->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND),return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rally]);
    verify($parallel('tick',$rally)===[0,0],'concurrent browser-offline ticks both finish');
    verify((int)$db->query("SELECT COUNT(*) FROM battle_reports WHERE JSON_EXTRACT(data_json,'$.rally_id')=?",[$rally])->fetchColumn()===2,'parallel ticks resolve a rally exactly once');
    verify(troops($player)[50100101]===$baseline[50100101]-2,'parallel ticks return exactly surviving troops once');
    $slotRallies=[];for($i=0;$i<3;$i++)$slotRallies[]=\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>1],1,'Slot fixture');
    rejected(fn()=>\Conquer\Game\Rally\RallyService::start($player,$player,$other,51,50,[50100101=>1],1,'Fourth slot'),'active rallies consume actual march slots');
    rejected(fn()=>MarchDispatcher::dispatchPlayerAttack($player,$player,50,50,51,50,[50100101=>1]),'solo and rally share the same march-slot cap');
    foreach($slotRallies as$id)\Conquer\Game\Rally\RallyService::cancel($id,$player);
    $oldNode=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM field_objects')->fetchColumn();$oldMonster=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM field_monsters')->fetchColumn();
    \Conquer\Game\Map\FrontierService::refresh($player,$db->query('SELECT * FROM cities WHERE id=?',[$player])->fetch());
    $collisions=(int)$db->query('SELECT COUNT(*) FROM field_objects f JOIN cities c ON ABS(f.coord_x-c.coord_x)<=1 AND ABS(f.coord_y-c.coord_y)<=1 WHERE f.id>?',[$oldNode])->fetchColumn()+(int)$db->query('SELECT COUNT(*) FROM field_monsters f JOIN cities c ON ABS(f.coord_x-c.coord_x)<=1 AND ABS(f.coord_y-c.coord_y)<=1 WHERE f.id>?',[$oldMonster])->fetchColumn();
    verify($collisions===0,'new frontier spawns respect every 3 by 3 city footprint');
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,50,49,1,1,5757,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$idleNode=$db->lastInsertId();
    $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current) VALUES(1,20209901,50,51,99)');$idleMonster=$db->lastInsertId();
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,49,50,1,1,8000,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');$busyNode=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(?,1,9,?,49,50,5,?,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$player,$player,$busyNode]);$busyMarch=$db->lastInsertId();
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,gatherer_march_id,expires_at) VALUES(1,51,49,1,1,8000,10000,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))',[$busyMarch]);$gatheredNode=$db->lastInsertId();
    \Conquer\Game\Map\FrontierService::refresh($player,$db->query('SELECT * FROM cities WHERE id=?',[$player])->fetch());
    $fixed=$db->query('SELECT * FROM field_objects WHERE id=?',[$idleNode])->fetch();$fixedMonster=$db->query('SELECT * FROM field_monsters WHERE id=?',[$idleMonster])->fetch();
    verify((int)$fixed['resource_amount']===5757&&((int)$fixed['coord_x']!==50||(int)$fixed['coord_y']!==49),'idle legacy resource relocates while preserving ID and exact stock');
    verify((int)$fixedMonster['hp_current']===99&&((int)$fixedMonster['coord_x']!==50||(int)$fixedMonster['coord_y']!==51),'idle legacy monster relocates without healing or replacement');
    verify((int)$db->query('SELECT coord_x FROM field_objects WHERE id=?',[$busyNode])->fetchColumn()===49&&(int)$db->query('SELECT coord_y FROM field_objects WHERE id=?',[$gatheredNode])->fetchColumn()===49,'active march targets and gathered fields are never relocated');
    $db->execute('UPDATE cities SET food=10000,lumber=10000,stone=10000,gold=10000,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$player]);
    $stale=$db->query('SELECT * FROM cities WHERE id=?',[$player])->fetch();$buildings=array_fill_keys(\Conquer\Game\City\CityState::BUILDING_CODES,['level'=>1]);
    $db->execute('UPDATE cities SET food=10,lumber=10,stone=10,gold=10 WHERE id=?',[$player]);
    \Conquer\Game\City\ResourceTick::persist($stale,$buildings);
    verify((int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn()===10,'persist uses fresh locked resources rather than restoring pre-battle snapshot');
    rejected(fn()=>\Conquer\Game\City\TroopTrainer::train($stale,$buildings,50100101,50),'training with stale affordable snapshot rejects newly unaffordable debit');
    verify((int)$db->query('SELECT COUNT(*) FROM troop_queue WHERE city_id=?',[$player])->fetchColumn()===0&&(int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn()===10,'failed stale training creates no order or negative resource balance');
    rejected(fn()=>$db->transaction(function()use($db,$player,$stale,$buildings){$db->execute('UPDATE cities SET food=7 WHERE id=?',[$player]);\Conquer\Game\City\ResourceTick::persist($stale,$buildings);throw new RuntimeException('fixture rollback');}),'resource persistence respects its caller transaction');
    verify((int)$db->query('SELECT food FROM cities WHERE id=?',[$player])->fetchColumn()===10,'outer rollback still restores fresh resource transaction');
    $goodCsrf=$csrfToken;$csrfToken='invalid';verify(request('/api/rally/start',['target_player_id'=>$other,'target_x'=>51,'target_y'=>50,'troops'=>[50100101=>1],'rally_minutes'=>1])['status']===403,'rally mutation requires CSRF');$csrfToken=$goodCsrf;
    $goodSession=$sessionToken;$sessionToken='invalid';verify(request('/api/rally/list')['status']===401,'rally read requires authentication');$sessionToken=$goodSession;
    echo "ALL CITY COMBAT AND RALLY CHECKS PASSED (isolated database).\n";
} catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($admin&&preg_match('/^conquer_city_test_[a-f0-9]{12}$/D',$name)){$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
    $resolved=realpath($root);$parent=realpath(sys_get_temp_dir());
    if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_city_test_[a-f0-9]{12}$/D',basename($resolved))){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);
    }
}
exit($exit);

