<?php
declare(strict_types=1);
/** Actual research endpoint: new affordability boundary, exact debit, old timer preservation. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';require __DIR__.'/Support/HttpApp.php';
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use ConquerTests\HttpApp;
function economyCheck(bool $ok,string $label,array $response=[]):void{
 if(!$ok)throw new RuntimeException($label.' '.json_encode($response));
 echo "PASS $label\n";
}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'EconomyFixture','economy@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'Economy',65,65,30,1040235,1040235,1040235,1040234)");
 // Low storage caps prevent passive income from moving the exact affordability boundary.
 foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,?)',[$code,in_array($code,['academy','castle'])?30:1]);
 $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,'advanced_infantry_spd',5)");
 $token=str_repeat('e',64);$csrf=str_repeat('c',64);
 $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,?,'127.0.0.1','economy-http',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
 $base=$fixture->serve(HttpApp::source(),['-d','display_errors=0']);
 $call=static fn(string $path,string $method='GET',array $body=[])=>HttpApp::request($base,$path,$method,['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.$csrf,'X-World-ID: 1'],$method==='GET'?null:json_encode($body));
 $body=['code'=>'crusader','level_to'=>1,'expected_world_id'=>1];
 // A paid order started under the old catalogue must retain its 131.25-day duration.
 $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,1,'crusader',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 11340000 SECOND))");
 $old=$db->query('SELECT * FROM research_queue WHERE player_id=1')->fetch();
 $r=$call('/api/research/state');
 economyCheck($r['status']===200&&$r['json']['data']['queue']['finishes_at']===$old['finishes_at'],'historical T5 research keeps its original 131.25-day timer',$r);
 $db->execute('DELETE FROM research_queue WHERE id=?',[$old['id']]);

 $r=$call('/api/research/start','POST',$body);
 economyCheck($r['status']===400&&($r['json']['error']['code']??null)==='NOT_ENOUGH_RESOURCES','one gold below the new T5 price is rejected',$r);
 economyCheck((int)$db->query('SELECT COUNT(*) FROM research_queue')->fetchColumn()===0,'unaffordable research creates no queue');
 economyCheck((int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn()===1040234,'rejected research consumes no gold');
 $db->execute('UPDATE cities SET gold=1040235 WHERE id=1');
 $r=$call('/api/research/start','POST',$body);
 economyCheck($r['status']===200,'T5 starts at exactly the reduced price',$r);
 $stock=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
 economyCheck(array_sum(array_map('intval',$stock))===0,'all four resources debited exactly once at 1040235 each');
 $queue=$db->query('SELECT * FROM research_queue WHERE player_id=1')->fetch();
 economyCheck(strtotime($queue['finishes_at'])-strtotime($queue['started_at'])===3888000,'T5 research starts with the approved 45-day base duration');
 $r=$call('/api/research/start','POST',$body);
 economyCheck($r['status']===400&&($r['json']['error']['code']??null)==='QUEUE_BUSY','duplicate start cannot buy another research',$r);
 economyCheck((int)$db->query('SELECT COUNT(*) FROM research_queue')->fetchColumn()===1,'only one research order exists');
 $r=$call('/api/research/state');
 economyCheck($r['status']===200&&$r['json']['data']['queue']['finishes_at']===$queue['finishes_at'],'reading existing research retains its stored completion time',$r);

 // All three T2 branches get the early military discount at their actual academy gate.
 $db->execute('DELETE FROM research_queue WHERE player_id=1');
 $db->execute("UPDATE city_buildings SET level=10 WHERE city_id=1 AND building_code='academy'");
 $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,'troops_storage',3)");
 $earlyPrice=52082;
 foreach(['warrior','longbow_man','horseman'] as $code){
  // Give each independent fixture case its own write budget.
  $db->execute('DELETE FROM security_rate_limits WHERE bucket_key=?',[hash('sha256','api.write:1')]);
  $body=['code'=>$code,'level_to'=>1,'expected_world_id'=>1];
  foreach(['food','lumber','stone','gold'] as $shortResource){
   $before=array_fill_keys(['food','lumber','stone','gold'],$earlyPrice);
   $before[$shortResource]--;
   $db->execute('UPDATE cities SET food=?,lumber=?,stone=?,gold=?,last_resource_update=UTC_TIMESTAMP() WHERE id=1',array_values($before));
   $r=$call('/api/research/start','POST',$body);
   economyCheck($r['status']===400&&($r['json']['error']['code']??null)==='NOT_ENOUGH_RESOURCES',"$code rejects one $shortResource below its reduced price",$r);
   $stock=array_map('intval',$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch());
   economyCheck($stock===$before&&(int)$db->query('SELECT COUNT(*) FROM research_queue')->fetchColumn()===0,"$code rejection spends nothing and creates no order");
  }
  $db->execute('UPDATE cities SET food=?,lumber=?,stone=?,gold=?,last_resource_update=UTC_TIMESTAMP() WHERE id=1',array_fill(0,4,$earlyPrice));
  $r=$call('/api/research/start','POST',$body);
  economyCheck($r['status']===200,"$code starts at academy 10 for exactly 52082 of each resource",$r);
  $stock=array_map('intval',$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch());
  economyCheck($stock===array_fill_keys(['food','lumber','stone','gold'],0),"$code debits all four reduced costs exactly once");
  $queue=$db->query('SELECT * FROM research_queue WHERE player_id=1')->fetch();
  economyCheck($queue['research_code']===$code&&strtotime($queue['finishes_at'])-strtotime($queue['started_at'])===72000,"$code keeps its original 20-hour research duration");
  $r=$call('/api/research/start','POST',$body);
  economyCheck($r['status']===400&&($r['json']['error']['code']??null)==='QUEUE_BUSY',"$code duplicate start is rejected",$r);
  $after=array_map('intval',$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch());
  economyCheck($after===$stock&&(int)$db->query('SELECT COUNT(*) FROM research_queue')->fetchColumn()===1,"$code duplicate start cannot debit again or add an order");
  $r=$call('/api/research/state');
  economyCheck($r['status']===200&&$r['json']['data']['queue']['finishes_at']===$queue['finishes_at'],"$code state read preserves its stored completion time",$r);
  $db->execute('DELETE FROM research_queue WHERE id=?',[$queue['id']]);
 }
 // Early gold relief also applies when the player already owns a higher academy.
 $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,'infantry_hp',2)");
 $db->execute('DELETE FROM security_rate_limits WHERE bucket_key=?',[hash('sha256','api.write:1')]);
 $body=['code'=>'infantry_def','level_to'=>1,'expected_world_id'=>1];
 $db->execute('UPDATE cities SET food=1764,lumber=882,stone=2646,gold=1852,last_resource_update=UTC_TIMESTAMP() WHERE id=1');
 $r=$call('/api/research/start','POST',$body);
 economyCheck($r['status']===400&&($r['json']['error']['code']??null)==='NOT_ENOUGH_RESOURCES','first defense research rejects one gold below the extra-discounted price',$r);
 economyCheck((int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn()===1852,'failed early research does not consume gold');
 $db->execute('UPDATE cities SET gold=1853,last_resource_update=UTC_TIMESTAMP() WHERE id=1');
 $r=$call('/api/research/start','POST',$body);
 economyCheck($r['status']===200,'first defense research starts for 1853 gold even at academy 10',$r);
 $stock=array_map('intval',$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch());
 economyCheck($stock===array_fill_keys(['food','lumber','stone','gold'],0),'early gold discount preserves and exactly debits the other three prices');
 $queue=$db->query('SELECT * FROM research_queue WHERE player_id=1')->fetch();
 economyCheck(strtotime($queue['finishes_at'])-strtotime($queue['started_at'])===360,'early gold relief preserves the six-minute defense research timer');
 echo "ALL ECONOMY HTTP CHECKS PASSED\n";
}finally{$fixture->close();}
