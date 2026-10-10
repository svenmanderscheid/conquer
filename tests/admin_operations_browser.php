<?php
declare(strict_types=1);
/** Read-only browser walkthrough over synthetic records in a disposable local schema. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-operations-browser.log','ERROR');
use Conquer\Db\Connection;
$fixture=null;$exit=0;
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
 $db->execute("UPDATE worlds SET name='North Fixture Realm' WHERE id=1");
 $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(3,'Southern Fixture Realm','southern-fixture','running')");
 $hash=password_hash('Fixture-Operations-123!',PASSWORD_DEFAULT);
 $db->execute("INSERT INTO admin_users(id,username,password_hash,role)VALUES(1,'OperationsAdmin',?,'superadmin')",[$hash]);
 foreach([[1,'ArdentKeeper',1],[2,'MireScout',3]] as [$id,$name,$world]){
  $db->execute('INSERT INTO players(id,username,email,password_hash,last_active_at)VALUES(?,?,?,?,UTC_TIMESTAMP())',[$id,$name,'ops'.$id.'@example.invalid','unused']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level)VALUES(?,?,?,'Fixture city',?,20,5)",[$id,$id,$world,20+$id]);
  $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id,last_active)VALUES(?,?,?,'127.0.0.1','Fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?,UTC_TIMESTAMP())",[$id,bin2hex(random_bytes(32)),bin2hex(random_bytes(32)),$world]);
  foreach([1,5,32,131,252,424] as $minutes)$db->execute("INSERT INTO player_activity_minutes(player_id,world_id,minute_slot)VALUES(?,?,DATE_FORMAT(DATE_SUB(UTC_TIMESTAMP(),INTERVAL $minutes MINUTE),'%Y-%m-%d %H:%i:00'))",[$id,$world]);
 }
 $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y,castle_level)VALUES(1,3,'Ardent second world',33,22,2)");
 $db->execute("INSERT INTO bug_reports(operation_key,player_id,world_id,report_type,category,severity,title,description,reproduction_steps,priority,admin_note) VALUES('operations_browser_bug1',1,1,'bug','gameplay','normal','Monster reward missing','The expected item was not credited.','Return from the fixture encounter.','urgent','')");
 $records=[
  ['OK','action','info','server','success',1,1,'/api/kingdom/action',180,'building.upgrade'],
  ['OK','action','info','server','success',2,3,'/api/march/dispatch-scout',260,'march.scout'],
  ['NOT_ENOUGH_RESOURCES','action','info','server','rejected',1,1,'/api/kingdom/action',42,'building.upgrade'],
  ['STATE_FAILURE','error','error','server','failed',1,1,'/api/game/state',1250,''],
  ['STATE_FAILURE','error','error','server','failed',1,1,'/api/game/state',2100,''],
  ['NETWORK_TIMEOUT','connection','warning','client','observed',2,3,'/api/game/state',20000,''],
  ['CONNECTION_RESTORED','connection','info','client','observed',2,3,'/api/game/state',43000,''],
  ['REWARD_UNKNOWN_ITEM','reward','warning','server','rejected',2,3,'/api/kingdom/action',22,'chest.open'],
 ];
 foreach($records as $i=>[$code,$category,$severity,$origin,$outcome,$player,$world,$route,$duration,$action]){
  $context=$category==='reward'?['item_id'=>99999999,'quantity'=>2,'source_type'=>'monster','source_key'=>'20209901','reason'=>'unknown_item']:['action'=>$action,'method'=>'POST','http_status'=>$outcome==='failed'?500:200];
  $db->execute("INSERT INTO operational_events(event_uid,category,severity,origin,code,message,outcome,occurred_at,player_id,world_id,request_id,operation_id,release_id,route,duration_ms,context_json,group_hash)VALUES(?,?,?,?,?,?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),?,?,?,?,?,?,?,?,?)",[
   bin2hex(random_bytes(16)),$category,$severity,$origin,$code,'Synthetic operational observation',$outcome,$player,$world,'request_fixture_'.str_repeat((string)($i+1),32),'operation_fixture_'.str_repeat((string)($i+1),32),'fixture-release-2026-10-10',$route,$duration,json_encode($context),hash('sha256',$code)]);
 }
 $db->execute("INSERT INTO reward_grant_ledger(player_id,world_id,item_code,quantity,source_type,source_reference)VALUES(2,3,10103001,2,'monster','browser-fixture-monster')");
 $routes= <<<'ROUTES'
define('APP_BASE','/conquer');$path=(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/conquer/assets/')){$file=realpath(ROOT_DIR.substr($path,8));$assetRoot=realpath(ROOT_DIR.'/assets');if(!$file||!str_starts_with($file,$assetRoot.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}$ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][$ext]??'application/octet-stream'));readfile($file);exit;}
session_name('conquer_admin');session_start();
if($path==='/conquer/fixture-login'){$row=\Conquer\Db\Connection::getInstance()->query('SELECT * FROM admin_users WHERE id=1')->fetch();$_SESSION['admin']=['id'=>1,'username'=>$row['username'],'role'=>$row['role'],'must_change_password'=>false,'credential_version'=>hash('sha256',$row['password_hash']),'authenticated_at'=>time(),'last_seen_at'=>time()];$_SESSION['admin_csrf']=bin2hex(random_bytes(32));header('Location: /conquer/admin');exit;}
$routes=['/conquer/admin'=>'dashboard','/conquer/admin/activity'=>'activity','/conquer/admin/technical'=>'technical','/conquer/admin/analytics'=>'analytics','/conquer/admin/cases'=>'cases','/conquer/admin/rewards'=>'rewards','/conquer/admin/world'=>'world','/conquer/admin/players'=>'players','/conquer/admin/audit'=>'auditLog'];
if(isset($routes[$path])){\Conquer\Admin\AdminController::{$routes[$path]}();exit;}
if(preg_match('#^/conquer/admin/players/(\d+)$#',$path,$m)){\Conquer\Admin\AdminController::playerDetail((int)$m[1]);exit;}
http_response_code(404);
ROUTES;
 $url=$fixture->serve($routes,['-d','display_errors=0']);
 $env=array_merge(getenv(),['OPS_TEST_BASE'=>$url.'/conquer']);
 $process=proc_open(['node',ROOT_DIR.'/tests/admin_operations_browser.cjs'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,$env,['bypass_shell'=>true]);
 if(!is_resource($process))throw new RuntimeException('Cannot launch operations browser checks');
 fclose($pipes[0]);$exit=proc_close($process);
 $serverLog=(string)file_get_contents($fixture->sessionPath().'/server.log');
 if(preg_match('/PHP (?:Warning|Fatal|Notice)|Uncaught /i',$serverLog))throw new RuntimeException('PHP diagnostic in browser fixture: '.$serverLog);
 $renderFailures=(int)$db->query("SELECT COUNT(*) FROM operational_events WHERE code='ADMIN_RENDER_FAILED'")->fetchColumn();
 if($renderFailures)throw new RuntimeException('Admin renderer caught '.$renderFailures.' failure(s)');
 if(!$exit)echo "PASS admin operations browser fixture has no PHP warnings or caught render failures\n";
}catch(Throwable $error){$exit=1;fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
