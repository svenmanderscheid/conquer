<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
function armyCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();\Conquer\Logger::init(ROOT_DIR.'/logs/security-test.log');
 $db->execute("UPDATE worlds SET status='running' WHERE id=1");
 \Conquer\Game\World\LandProgressService::ensureWorld(1,true);
 $tokens=[];$csrf=str_repeat('b',64);
 foreach([1,2,3]as$pid){
  $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen)VALUES(?,?,?,'unused',200,UTC_TIMESTAMP())",[$pid,'Receipt'.$pid,'receipt'.$pid.'@test.invalid']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(?,?,1,'Receipt city',?,50,5,100000,100000,100000,100000)",[$pid,$pid,50+$pid*5]);
  foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,5)',[$pid,$code]);
  $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,10000)',[$pid]);
  $tokens[$pid]=bin2hex(random_bytes(32));
  $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(?,?,?,'127.0.0.1','receipt test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$pid,$tokens[$pid],$csrf]);
 }
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Receipt alliance','RCT',1)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader'),(1,2,1,'member')");
 $source=str_replace(['declare(strict_types=1);',"define('ROOT_DIR', __DIR__);","require_once ROOT_DIR . '/src/Bootstrap.php';",'\\Conquer\\Bootstrap::init(ROOT_DIR);'],'',file_get_contents(ROOT_DIR.'/index.php'));
 $source=preg_replace('/^<\?php\s*/','',$source);
 $base=$fixture->serve("\$_SERVER['SCRIPT_NAME']='/index.php'; ".$source,['-d','display_errors=0','-d','display_startup_errors=0']);
 $request=static function(string $path,array $body,int $player=1)use($base,$tokens,$csrf,$db):array{
  // Each case tests game behavior, not exhausted request budgets (covered separately).
  $db->execute('DELETE FROM security_rate_limits');
  $h=curl_init($base.'/api/'.$path);$headers=[];
  curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$tokens[$player],'X-CSRF-Token: '.$csrf],CURLOPT_HEADERFUNCTION=>static function($h,$line)use(&$headers){$headers[]=trim($line);return strlen($line);}]);
  $raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);return [$status,json_decode((string)$raw,true),$headers,$raw];
 };
 $stock=static fn(int $pid=1)=>(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$pid])->fetchColumn();
 $body=['target_x'=>65,'target_y'=>50,'troops'=>[50100101=>5],'expected_world_id'=>1];
 armyCheck($request('march/dispatch-player',$body)[0]===400,'missing operation key rejected before troop reservation');
 $body['operation_key']='army_receipt_city_001';$before=$stock();
 $first=$request('march/dispatch-player',$body);armyCheck($first[0]===200,'city attack accepted: '.json_encode($first[1]));
 $again=$request('march/dispatch-player',$body);
 armyCheck($again[1]===$first[1]&&$stock()===$before-5,'city attack retry returns original march without reserving again');
 armyCheck((int)$db->query('SELECT SUM(action_count) FROM security_activity')->fetchColumn()===1,'retry does not inflate activity evidence');
 $conflict=$body;$conflict['troops']=[50100101=>6];armyCheck($request('march/dispatch-player',$conflict)[0]===409,'same key with changed army is rejected');
 $reordered=array_reverse($body,true);armyCheck($request('march/dispatch-player',$reordered)[1]===$first[1],'object property order does not change request identity');
 armyCheck($request('march/dispatch-scout',$body)[0]===409,'same operation key cannot be reused through another endpoint');
 $db->execute('DELETE FROM marches');
 $rally=['target_x'=>65,'target_y'=>50,'target_player_id'=>3,'rally_minutes'=>5,'message'=>'','troops'=>[50100101=>5],'operation_key'=>'army_receipt_rally_001'];
 $before=$stock();$first=$request('rally/start',$rally);armyCheck($first[0]===200,'city rally accepted: '.json_encode($first[1]));
 armyCheck($request('rally/start',$rally)[1]===$first[1]&&$stock()===$before-5,'city rally retry does not duplicate rally or troops');
 $join=['rally_id'=>$first[1]['data']['rally_id'],'troops'=>[50100101=>3],'operation_key'=>'army_receipt_join_001'];
 $before=$stock(2);$first=$request('rally/join',$join,2);armyCheck($first[0]===200,'rally member joins');
 armyCheck($request('rally/join',$join,2)[1]===$first[1]&&$stock(2)===$before-3,'join retry returns its original success');
 $db->execute("UPDATE rallies SET status='cancelled'");
 $scout=['target_x'=>65,'target_y'=>50,'operation_key'=>'army_receipt_scout_001'];
 $first=$request('march/dispatch-scout',$scout);armyCheck($first[0]===200,'scout starts');
 armyCheck($request('march/dispatch-scout',$scout)[1]===$first[1],'scout retry returns original order');
 $db->execute('DELETE FROM marches');
 $support=['action'=>'reinforce','target_player_id'=>2,'troops'=>[50100101=>4],'operation_key'=>'army_receipt_support_001'];
 $before=$stock();$first=$request('defense/action',$support);armyCheck($first[0]===200,'defense reinforcement starts: '.json_encode($first[1]));
 armyCheck($request('defense/action',$support)[1]===$first[1]&&$stock()===$before-4,'alternative defense entry point cannot double-reserve');
 $db->execute('DELETE FROM marches');
 $bad=$body;$bad['operation_key']='army_receipt_failure_001';$bad['troops']=[50100101=>50000];$before=$stock();
 $failed=$request('march/dispatch-player',$bad);
 armyCheck($failed[0]>=400&&in_array('X-Operation-Rejected: 1',$failed[2],true)&&$stock()===$before,'failed command rolls back and explicitly releases browser receipt');
 armyCheck(!(bool)$db->query('SELECT 1 FROM api_operation_receipts WHERE operation_key=?',[$bad['operation_key']])->fetchColumn(),'failed command stores no success receipt');
 $db->execute("CREATE TRIGGER receipt_fail BEFORE INSERT ON api_operation_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture receipt failure'");
 $bad=$body;$bad['operation_key']='army_receipt_atomic_001';$before=$stock();$request('march/dispatch-player',$bad);
 armyCheck($stock()===$before && !(int)$db->query('SELECT COUNT(*) FROM marches')->fetchColumn(),'receipt storage failure rolls back the actual nested game transaction');
 $db->execute('DROP TRIGGER receipt_fail');
 $db->execute('DELETE FROM security_activity');
 $now=(int)$db->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP())')->fetchColumn();$slot=intdiv($now,900)*900;
 for($i=0;$i<32;$i++)$db->execute('INSERT INTO security_activity(player_id,world_id,slot_start,action_count)VALUES(1,1,?,4)',[$slot-$i*900]);
 \Conquer\Security\ActivityMonitor::record(1,1);\Conquer\Security\ActivityMonitor::record(1,1);
 armyCheck((int)$db->query("SELECT COUNT(*) FROM security_activity_flags WHERE reason='continuous_activity'")->fetchColumn()===1,'continuous activity produces one review flag per day');
 armyCheck(!(bool)$db->query('SELECT is_banned FROM players WHERE id=1')->fetchColumn(),'activity evidence never automatically bans a player');
 $db->execute('DELETE FROM security_activity');$db->execute('DELETE FROM security_activity_flags');
 \Conquer\Security\ActivityMonitor::record(2,1);
 armyCheck(!(int)$db->query('SELECT COUNT(*) FROM security_activity_flags')->fetchColumn(),'ordinary play produces no flag');
 $db->execute('UPDATE security_activity SET action_count=499 WHERE player_id=2');
 \Conquer\Security\ActivityMonitor::record(2,1);
 armyCheck((int)$db->query("SELECT COUNT(*) FROM security_activity_flags WHERE player_id=2 AND reason='high_command_volume'")->fetchColumn()===1,'high command volume creates a manual review signal');
 echo "ALL ARMY RECEIPT CHECKS PASSED\n";
}finally{$fixture->close();}
