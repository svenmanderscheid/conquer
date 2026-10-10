<?php
declare(strict_types=1);
/** Dashboard projections against a disposable schema, without production data or game actions. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Admin\OperationsDashboard as Ops;
use Conquer\Db\Connection;
$fixture=null;$checks=0;$exit=0;
function opsCheck(bool $pass,string $label):void {global $checks;if(!$pass)throw new RuntimeException($label);$checks++;echo 'PASS '.$label.PHP_EOL;}
try {
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
 $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Other realm','other-realm','running')");
 $db->execute("INSERT INTO admin_users(id,username,password_hash,role) VALUES(1,'Fixture admin','unused','superadmin')");
 $_SESSION['admin']=['id'=>1,'role'=>'superadmin'];
 foreach([1,2,3,4] as $id)$db->execute("INSERT INTO players(id,username,email,password_hash,created_at) VALUES(?,?,?,'unused',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 DAY))",[$id,'OpsPlayer'.$id,'ops'.$id.'@example.invalid']);
 $db->execute('UPDATE players SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=3');
 foreach([[1,1],[1,2],[2,1],[3,2],[4,1]] as [$p,$w])$db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(?,?,'Fixture city',?,?)",[$p,$w,$p*3,$w*3]);
 foreach([[1,1,0,0],[1,1,0,0],[2,2,0,0],[3,1,10,0],[4,1,0,1]] as [$p,$w,$age,$expired])$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,last_active,expires_at,active_world_id) VALUES(?,?,?,'127.0.0.1','Fixture',DATE_SUB(UTC_TIMESTAMP(),INTERVAL $age MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ".($expired?-1:1)." HOUR),?)",[$p,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),$w]);
 foreach([[1,1,1],[1,1,2],[2,1,1],[1,2,1],[3,2,1],[4,1,25]] as [$p,$w,$hours])$db->execute("INSERT INTO player_activity_minutes(player_id,world_id,minute_slot) VALUES(?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL $hours HOUR))",[$p,$w]);
 $worlds=$db->query('SELECT id,name FROM worlds ORDER BY id')->fetchAll();
 $all=Ops::filters([], $worlds);$one=Ops::filters(['world_id'=>1],$worlds);$two=Ops::filters(['world_id'=>2],$worlds);
 foreach([&$all,&$one,&$two] as &$f)$f['until']=gmdate('Y-m-d H:i:s',time()+2);unset($f);
 $input=Ops::filters(['world_id'=>['bad'],'days'=>99,'tab'=>['bad']],$worlds);
 opsCheck($input['world_id']===0&&$input['days']===1&&$input['tab']==='activity','Malformed filters fall back safely to all worlds and 24 hours');
 $write=static function(string $category,string $code,int $player,int $world,string $outcome='observed',?int $duration=null,string $origin='server',int $hours=0)use($db):int {
   $db->execute("INSERT INTO operational_events(event_uid,category,severity,origin,code,outcome,occurred_at,player_id,world_id,route,duration_ms,context_json,group_hash) VALUES(?,?,?, ?,?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL $hours HOUR),?,?, '/api/fixture',?,'{}',?)",[bin2hex(random_bytes(16)),$category,$category==='error'?'error':'info',$origin,$code,$outcome,$player,$world,$duration,hash('sha256',$code)]);return $db->lastInsertId();
 };
 $write('error','SERVER_FAILURE',1,1);$write('error','SERVER_FAILURE',1,1);$other=$write('error','OTHER_WORLD_FAILURE',3,2);
 $write('action','NOT_ENOUGH_RESOURCES',2,1,'rejected',80);$write('action','BUILD_STARTED',1,1,'success',120);
 $write('action','BUILD_STARTED',1,1,'replayed',100);
 $write('connection','NETWORK_FAILURE',1,1);$write('connection','NETWORK_TIMEOUT',1,1);$write('connection','CONNECTION_RESTORED',1,1);
 $write('connection','BROWSER_OFFLINE',2,1);$write('connection','BROWSER_ONLINE',2,1);
 $write('reward','REWARD_UNKNOWN_ITEM',2,1,'rejected');$write('error','OUTSIDE_PERIOD',2,1,'failed',null,'server',48);
 $s=Ops::snapshot($one);$g=Ops::snapshot($all);
 opsCheck($s['online']===1&&$g['online']===2&&count(Ops::players($one,'online')['rows'])===1,'Online counts use active session world and deduplicate sessions; expired and stale sessions are excluded');
 opsCheck($s['active']===2&&$g['active']===3&&$s['previous_active']===1,'Active players are unique across minutes and worlds with a separate equal previous period');
 opsCheck(count(Ops::players($one,'players')['rows'])===$s['active'],'Active-player drilldown matches the headline metric');
 opsCheck($s['registrations']===0&&Ops::snapshot($two)['registrations']===1&&count(Ops::players($two,'registrations')['rows'])===1,'Registration counts and drilldowns respect world membership and account creation period');
 opsCheck($s['errors']===2&&$s['affected']===1&&$s['rejected']===1,'Business rejection is not a technical error and affected players are deduplicated');
 opsCheck($s['connections']===2&&$s['recovered']===1,'Network failures and recovery are separate; offline browser notifications are excluded from failure count');
 opsCheck($s['invalid_rewards']===1&&$s['actions']===1,'Invalid rewards and successful actions exclude retries');
 opsCheck($s['p95']===120&&$s['latency_samples']===3,'Duration percentile uses only recorded server actions');
 opsCheck(count($s['activity'])>=12&&max(array_column($s['activity'],'count'))<=2,'Chart fills empty buckets and counts distinct players per bucket');
 opsCheck(Ops::event($other,$one)===null&&Ops::event($other,$all)['code']==='OTHER_WORLD_FAILURE','Event detail enforces the selected world');
 $groups=Ops::groups($one);$server=array_values(array_filter($groups,fn($row)=>$row['code']==='SERVER_FAILURE'))[0];
 opsCheck((int)$server['total']===2&&(int)$server['players']===1&&!in_array('BROWSER_OFFLINE',array_column($groups,'code')),'Incident groups retain occurrence counts and affected-player counts');
 opsCheck(count(Ops::events($one,['category'=>'action','player_id'=>2,'outcome'=>'rejected','q'=>'NOT_ENOUGH'])['rows'])===1,'Event search, category, player, outcome, world and time combine');
 $db->execute("UPDATE operational_events SET context_json=? WHERE code='BUILD_STARTED'",[json_encode(['action'=>'building.upgrade'])]);
 opsCheck(count(Ops::events($one,['category'=>'action','q'=>'building.upgrade'])['rows'])===2,'Action names are searchable even when the response code is generic');
 foreach(range(1,55) as $i)$write('action','PAGED_ACTION',1,1,'success');
 $page1=Ops::events($one,['category'=>'action']);$page2=Ops::events($one,['category'=>'action','before'=>$page1['next']]);
 opsCheck(count($page1['rows'])===50&&$page1['next']>0&&count($page2['rows'])===8&&!array_intersect(array_column($page1['rows'],'id'),array_column($page2['rows'],'id')),'Stable event cursor returns bounded pages without duplicate entries');
 foreach([[1,'complete',200],[1,'returning',900],[2,'complete',500]] as [$w,$state,$food])$db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,departure_time,arrival_time,return_time,state,haul_json) VALUES(1,?,9,1,20,20,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 MINUTE),?,?)",[$w,$state,json_encode(['loot'=>['food'=>$food,'gold'=>10]])]);
 $economy=Ops::economy($one);
 opsCheck((int)$economy['gathered']['total']===1&&(int)$economy['gathered']['food']===200&&(int)$economy['gathered']['gold']===10,'Gathering totals include completed returns only in the selected world');
 $db->execute('RENAME TABLE operational_events TO fixture_events_hold');
 try{$missing=Ops::snapshot($one);opsCheck(!$missing['events_available']&&$missing['errors']===null&&$missing['affected']===null&&!Ops::events($one,[])['available'],'Missing event migration is unavailable, never a healthy zero');}finally{$db->execute('RENAME TABLE fixture_events_hold TO operational_events');}
 $db->execute('RENAME TABLE player_activity_minutes TO fixture_activity_hold');
 try{$missing=Ops::snapshot($one);opsCheck(!$missing['activity_available']&&$missing['active']===null&&!Ops::players($one,'players')['available'],'Missing activity history remains explicitly unavailable');}finally{$db->execute('RENAME TABLE fixture_activity_hold TO player_activity_minutes');}
 require ROOT_DIR.'/views/admin/helpers.php';require ROOT_DIR.'/views/admin/operations_helpers.php';
 $fallback=$fixture->sessionPath().'/operational-fallback-'.gmdate('Y-m-d').'.ndjson';file_put_contents($fallback,"{}\n");ob_start();opsFallbackNotice();$notice=ob_get_clean();
 opsCheck(str_contains($notice,'not indexed')&&!str_contains($notice,$fixture->sessionPath()),'Unindexed fallback data is visible without disclosing a private filesystem path');
 echo "ALL $checks ADMIN OPERATIONS CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' '.$e->getFile().':'.$e->getLine().PHP_EOL);}
finally{if($fixture)$fixture->close();}exit($exit);
