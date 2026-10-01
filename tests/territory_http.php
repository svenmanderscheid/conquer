<?php
declare(strict_types=1);
/** Actual front-controller checks against schema-only disposable worlds. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';require __DIR__.'/Support/HttpApp.php';
use Conquer\Db\Connection;
use Conquer\Auth\OAuth;
use Conquer\Game\World\{WorldMapProfile,WorldService};
use ConquerTests\HttpApp;
function territoryHttpCheck(bool $condition,string $label,?array $response=null):void{
 if(!$condition)throw new RuntimeException($label.($response?' | '.json_encode($response,JSON_UNESCAPED_UNICODE):''));
 echo 'PASS '.$label."\n";
}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed)VALUES(2,'Territory HTTP','territory-http','running',768,42)");
 WorldMapProfile::configureEmptyWorld(2);WorldService::initializeWorld(2);
 foreach([1=>'Leader',2=>'Member',3=>'Rival']as$pid=>$name){
  $db->transaction(static function($db)use($pid,$name){
   $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$pid,'Territory'.$name,strtolower($name).'@tests.invalid','unused']);
   OAuth::createDefaultCity($db,$pid,'Territory'.$name,2);
  });
 }
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,2,'First Alliance','ONE',1),(2,2,'Other Alliance','TWO',3)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,2,'leader'),(1,2,2,'member'),(2,3,2,'leader')");
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(3,1,'Other World','OLD',1)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(3,1,1,'leader')");
 $tokens=[];$csrf=str_repeat('c',64);
 foreach([1,2,3]as$pid){$tokens[$pid]=str_pad((string)$pid,64,'0');$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(?,?,?,'127.0.0.1','territory-http',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),2)",[$pid,$tokens[$pid],$csrf]);}
 $base=$fixture->serve(HttpApp::source(),['-d','display_errors=0']);
 $call=static function(string $path,string $method='GET',array $body=[],int $pid=1,bool $sendCsrf=true,?int $headerWorld=2)use($base,$db,$tokens,$csrf):array{
  $db->execute('DELETE FROM security_rate_limits');$headers=['Content-Type: application/json'];
  if($pid)$headers[]='Cookie: conquer_session='.$tokens[$pid];
  if($sendCsrf)$headers[]='X-CSRF-Token: '.$csrf;
  if($headerWorld!==null)$headers[]='X-World-ID: '.$headerWorld;
  return HttpApp::request($base,$path,$method,$headers,$method==='GET'?null:json_encode($body,JSON_THROW_ON_ERROR));
 };
 $targets=$db->query("SELECT id FROM territory_targets WHERE world_id=2 AND kind='commune' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);$target=$targets[0];
 // Existing worlds retain their stored names and IDs; the API presents the new titles.
 $db->execute("UPDATE territory_targets SET name='Vogtei Dippach' WHERE world_id=2 AND id=?",[$target]);
 $db->execute("UPDATE territory_targets SET name='Markfeste Capellen' WHERE world_id=2 AND id='canton:01'");
 $db->execute("UPDATE territory_targets SET name='Krounbuerg' WHERE world_id=2 AND kind='crown'");
 $body=['action'=>'set_goal','target_id'=>$target,'world_id'=>2,'expected_world_id'=>2,'request_id'=>'territory_http_goal_0001'];
 foreach(['/api/territory/state','/api/territory/target?id='.rawurlencode($target)]as$path){
  $r=$call($path,'GET',[],0);territoryHttpCheck($r['status']===401,'anonymous read denied: '.$path,$r);
  $r=$call($path,'GET',[],1,true,1);territoryHttpCheck($r['status']===409,'stale world read denied: '.$path,$r);
 }
 $r=$call('/api/territory/action','POST',$body,1,false);territoryHttpCheck($r['status']===403,'territory write requires CSRF',$r);
 $r=$call('/api/territory/action','POST',array_replace($body,['world_id'=>1]));territoryHttpCheck($r['status']===409,'body cannot select a foreign world',$r);
 $r=$call('/api/territory/action','POST',$body,2);territoryHttpCheck(in_array($r['status'],[403,422],true),'ordinary member cannot set alliance goal',$r);
 $r=$call('/api/territory/state');territoryHttpCheck($r['status']===200&&count($r['json']['data']['targets']??[])===113,'authenticated state exposes 113 Luxembourg targets',$r);
 $names=array_column($r['json']['data']['targets'],'name','id');
 territoryHttpCheck($names[$target]==='Commune Dippach'&&$names['canton:01']==='Shrine of Capellen'&&$names['crown:krounbuerg']==='Royal Castle','existing targets expose Commune, Shrine and Royal Castle without changing IDs');
 territoryHttpCheck($db->query('SELECT name FROM territory_targets WHERE world_id=2 AND id=?',[$target])->fetchColumn()==='Vogtei Dippach','name presentation does not rewrite stored territory data');
 $r=$call('/api/territory/target?id='.rawurlencode($target));territoryHttpCheck($r['status']===200&&($r['json']['data']['id']??'')===$target,'stable territory identifier reaches actual detail route',$r);
 $first=$call('/api/territory/action','POST',$body);territoryHttpCheck($first['status']===200,'officer sets valid goal',$first);
 $retry=$call('/api/territory/action','POST',$body);territoryHttpCheck($retry['status']===200&&$retry['json']['data']===$first['json']['data'],'lost response can be retried without a second mutation',$retry);
 $changed=$call('/api/territory/action','POST',array_replace($body,['target_id'=>$targets[1]]));territoryHttpCheck($changed['status']===409,'receipt cannot be reused for a different goal',$changed);
 territoryHttpCheck((int)$db->query('SELECT COUNT(*) FROM territory_operations WHERE world_id=2 AND player_id=1')->fetchColumn()===1,'only one successful operation receipt persisted');
 $r=$call('/api/territory/state','GET',[],3);territoryHttpCheck($r['status']===200&&($r['json']['data']['goal']??null)===null,'rival alliance has its own goal state',$r);
 $r=$call('/api/game/state?map_x=420&map_y=1030&map_radius=30');
 territoryHttpCheck($r['status']===200&&($r['json']['data']['world']['map_profile']['height']??0)===1100,'main game accepts southern viewport and real dimensions',$r);
 territoryHttpCheck(empty($r['json']['data']['shrines'])&&empty($r['json']['data']['congress']),'Luxembourg game state omits old shrine objectives');
 $r=$call('/api/map/search?category=resource&resource=food&level=1');territoryHttpCheck($r['status']!==410&&$r['status']<500,'current map search remains reachable outside world one',$r);
 $r=$call('/api/map/info');territoryHttpCheck($r['status']===200&&($r['json']['data']['map_height']??0)===1100&&($r['json']['data']['world_id']??0)===2,'map metadata supports the active second world',$r);
 $city=$db->query('SELECT coord_x,coord_y FROM cities WHERE player_id=1 AND world_id=2')->fetch();$x=(int)$city['coord_x'];$y=(int)$city['coord_y'];
 $r=$call('/api/map/tiles?x_min='.$x.'&x_max='.$x.'&y_min='.$y.'&y_max='.$y);
 $cities=array_values(array_filter($r['json']['data']['entities']??[],fn($v)=>($v['type']??'')==='city'&&($v['player_id']??0)===1));
 territoryHttpCheck($r['status']===200&&count($cities)===1&&($cities[0]['alliance_tag']??'')==='ONE','viewport has one city with the alliance of its own world',$r);
 $r=$call('/api/map/tile/'.$x.'/'.$y);territoryHttpCheck($r['status']===200&&($r['json']['data']['occupant']['alliance_tag']??'')==='ONE','single tile uses world-qualified alliance membership',$r);
 $r=$call('/api/map/info?world_id=1');territoryHttpCheck($r['status']===409,'map URL cannot select a foreign world',$r);
 $db->execute("INSERT INTO territory_rewards(world_id,continent_id,player_id,alliance_id,target_id,event_key,reward_json,created_at)VALUES(2,'luxembourg',1,1,?,'http-earned-before-pause','{\"gold\":17}',UTC_TIMESTAMP())",[$target]);$rewardId=(int)$db->lastInsertId();
 $db->execute("UPDATE worlds SET status='paused' WHERE id=2");
 $r=$call('/api/territory/action','POST',array_replace($body,['request_id'=>'territory_http_paused_01']));territoryHttpCheck($r['status']===409,'paused world blocks new commands',$r);
 $r=$call('/api/territory/state');territoryHttpCheck($r['status']===200,'paused world remains readable',$r);
 $claim=['action'=>'claim','reward_id'=>$rewardId,'world_id'=>2,'expected_world_id'=>2,'request_id'=>'territory_http_claim_001'];
 $r=$call('/api/territory/action','POST',$claim);territoryHttpCheck($r['status']===200&&($r['json']['data']['reward']['gold']??0)===17,'paused world permits an already earned personal reward',$r);
 echo "ALL TERRITORY HTTP CHECKS PASSED\n";
}catch(Throwable $e){
 $log=sys_get_temp_dir().DIRECTORY_SEPARATOR.(string)$db->query('SELECT DATABASE()')->fetchColumn().DIRECTORY_SEPARATOR.'server.log';
 if(is_file($log))fwrite(STDERR,substr((string)file_get_contents($log),-6000));
 throw $e;
}finally{$fixture->close();}
