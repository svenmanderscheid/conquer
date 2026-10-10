<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Observability\{EventLog,RequestTrace,SafeData,ClientTelemetry};
date_default_timezone_set('UTC');
$fixture=null;$checks=0;$exit=0;$directory=sys_get_temp_dir().'/conquer-observability-test-'.bin2hex(random_bytes(5));
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";}
try{
    mkdir($directory,0700,true);\Conquer\Logger::init($directory.'/test.log','ERROR');
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0142_observability.sql'));
    EventLog::init($fixture->sessionPath(),$directory,'test-release');
    RequestTrace::begin('POST','/api/kingdom/action');RequestTrace::bind(['player_id'=>19,'active_world_id'=>3]);
    RequestTrace::captureBody(['action'=>'building.upgrade','operation_key'=>'fixture_operation_1234','password'=>'private-password',
        'csrf'=>'private-csrf','message'=>'private-chat','target_id'=>42,'nested'=>['token'=>'private-nested']]);
    $db->getPdo()->beginTransaction();
    $db->execute("UPDATE worlds SET name='Rolled back' WHERE id=1");
    ck(EventLog::record(['category'=>'reward','severity'=>'warning','code'=>'REWARD_UNKNOWN_ITEM','outcome'=>'rejected','context'=>[
        'item_id'=>99999999,'quantity'=>2,'source_type'=>'monster','source_key'=>'20209901','password'=>'private-password',
        'message'=>'private-chat','token'=>'private-token','nested'=>['private'=>1]]]),'Diagnostic can persist while gameplay transaction is open');
    $db->getPdo()->rollBack();
    $row=$db->query('SELECT * FROM operational_events ORDER BY id DESC LIMIT 1')->fetch();
    ck($db->query('SELECT name FROM worlds WHERE id=1')->fetchColumn()==='Fixture Realm' && $row['code']==='REWARD_UNKNOWN_ITEM','Rejected reward survives gameplay rollback');
    ck((int)$row['player_id']===19 && (int)$row['world_id']===3 && $row['operation_id']==='fixture_operation_1234','Server request context binds player, world and operation');
    ck(!str_contains($row['context_json'],'private-') && json_decode($row['context_json'],true)['item_id']===99999999,'Raw bodies, credentials and nested input are excluded');
    RequestTrace::complete(422,['ok'=>false,'error'=>['code'=>'NOT_ENOUGH_RESOURCES']]);
    ck((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE category='action' AND outcome='rejected' AND severity='info'")->fetchColumn()===1,'Expected gameplay rejection is an action, not an incident');
    ck((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE category='error'")->fetchColumn()===0,'Expected business rejection does not inflate error count');
    RequestTrace::begin('POST','/api/kingdom/action');RequestTrace::bind(['player_id'=>19,'active_world_id'=>3]);
    RequestTrace::complete(200,['ok'=>true]);RequestTrace::complete(200,['ok'=>true]);
    ck((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE category='action' AND outcome='success'")->fetchColumn()===1,'A response produces exactly one successful action observation');
    RequestTrace::begin('GET','/api/game/state');RequestTrace::complete(500,['ok'=>false,'error'=>['code'=>'STATE_FAILED']]);
    ck((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE category='error' AND code='STATE_FAILED'")->fetchColumn()===1,'Read API failures are observable without logging routine polling');
    $client=['id'=>'client_test_12345678','code'=>'NETWORK_TIMEOUT','player_id'=>999,'world_id'=>999,'severity'=>'critical',
        'origin'=>'server','message'=>'private-client-message','release_id'=>'spoofed-release','route'=>'/api/game/state?token=private-token',
        'at'=>1,'duration_ms'=>999999999,'context'=>['password'=>'private-password','file'=>'/assets/js/game.js?token=secret','line'=>5,'online'=>false]];
    $normalized=ClientTelemetry::normalize($client,['player_id'=>19,'active_world_id'=>3]);
    ck($normalized!==null && $normalized['origin']==='client' && $normalized['severity']==='warning' && $normalized['player_id']===19 && $normalized['world_id']===0,'Client cannot forge server severity, player or world; stale world observations remain unattributed');
    EventLog::record($normalized);EventLog::record($normalized);
    $clientRow=$db->query("SELECT * FROM operational_events WHERE code='NETWORK_TIMEOUT'")->fetch();
    ck((int)$db->query("SELECT COUNT(*) FROM operational_events WHERE code='NETWORK_TIMEOUT'")->fetchColumn()===1,'Retrying the same client event does not duplicate it');
    ck($clientRow['route']==='/api/game/state' && !str_contains(json_encode($clientRow),'private-') && $clientRow['release_id']==='test-release','Client URL queries and untrusted raw diagnostics never enter stored events');
    ck((int)$clientRow['duration_ms']===86400000 && strtotime($clientRow['occurred_at'])>=time()-86401,'Client duration and timestamps are bounded');
    ck(ClientTelemetry::normalize(['id'=>'client_test_12345678','code'=>'GRANT_REWARD'],['player_id'=>19])===null,'Unknown client event types are ignored');
    $before=(int)$db->query('SELECT COUNT(*) FROM operational_events')->fetchColumn();
    try{$db->query('SELECT nonexistent_column FROM worlds WHERE id=?',['private-value']);}catch(PDOException $error){EventLog::exception($error,'outer.catch');}
    ck((int)$db->query('SELECT COUNT(*) FROM operational_events')->fetchColumn()===$before+1,'Caught database errors log once without recursive or duplicate reporting');
    $dbError=$db->query("SELECT * FROM operational_events WHERE code='DATABASE_EXCEPTION' ORDER BY id DESC LIMIT 1")->fetch();
    ck(!str_contains(json_encode($dbError),'nonexistent_column') && !str_contains(json_encode($dbError),'private-value'),'Database diagnostics exclude SQL text and parameters');
    ck(!str_contains(SafeData::message('password=secretvalue token=othersecret mail@example.com https://host/?token=foo "private prose"'),'secretvalue'),'File logger redacts credential, URL, email and quoted content');
    $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(19,'TelemetryFixture','telemetry@test.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(19,19,1,'Telemetry city',11,12)");
    $cookie=str_repeat('c',64);$csrf=str_repeat('d',64);
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(19,?,?,'127.0.0.1','fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$cookie,$csrf]);
    $base=$fixture->serve("\\Conquer\\Observability\\EventLog::init(__DIR__,__DIR__,'http-test');\\Conquer\\Observability\\RequestTrace::begin(\$_SERVER['REQUEST_METHOD'],'/api/telemetry');\\Conquer\\Observability\\ClientTelemetry::handle(\\Conquer\\Auth\\Session::current()??[]);");
    $request=static function(string $raw,bool $auth=true,bool $csrfValid=true)use($base,$cookie,$csrf):array{
        $handle=curl_init($base.'/api/telemetry');
        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>10,CURLOPT_POSTFIELDS=>$raw,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.($auth?$cookie:'bad'),'X-CSRF-Token: '.($csrfValid?$csrf:'bad')]]);
        $body=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);
        return [$status,json_decode((string)$body,true)];
    };
    $payload=json_encode(['events'=>[['id'=>'http_telemetry_12345678','code'=>'NETWORK_FAILURE','world_id'=>1,'at'=>time()*1000,'route'=>'/api/game/state']]]);
    ck($request($payload,false)[0]===401 && $request($payload,true,false)[0]===403,'HTTP telemetry requires a real player session and matching CSRF');
    ck($request($payload)[0]===200 && (int)$db->query("SELECT COUNT(*) FROM operational_events WHERE code='NETWORK_FAILURE' AND player_id=19 AND world_id=1")->fetchColumn()===1,'Authenticated browser observation reaches the isolated central store');
    ck($request(str_repeat('x',16385))[0]===413 && $request('{broken')[0]===400,'HTTP payload size and JSON depth/format are bounded');
    $db->execute('DELETE FROM security_rate_limits');
    for($i=0;$i<6;$i++)$result=$request($payload);
    ck($result[0]===200 && $request($payload)[0]===429,'Browser diagnostics have a separate six-batch-per-minute limit');
    ck((int)$db->query('SELECT COUNT(*) FROM security_rate_limits WHERE bucket_key=?',[hash('sha256','api.write:19')])->fetchColumn()===0,'Diagnostics never consume gameplay mutation budget');
    $db->execute("UPDATE operational_events SET received_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 31 DAY) WHERE code='REWARD_UNKNOWN_ITEM'");
    ck(EventLog::prune(30,1)===1,'Retention is bounded and removes expired diagnostics');
    EventLog::init($directory,$directory,'fallback-test');
    ck(!EventLog::record(['category'=>'error','code'=>'SINK_TEST','message'=>'Diagnostic fallback test']),'Unavailable diagnostic database does not throw into gameplay');
    $spool=$directory.'/operational-fallback-'.gmdate('Y-m-d').'.ndjson';
    ck(is_file($spool) && str_contains((string)file_get_contents($spool),'file_only'),'Unavailable central sink produces an explicit fallback record');
    echo 'PASS '.$checks." observability checks\n";
}catch(Throwable $error){$exit=1;fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally{
    if($fixture)$fixture->close();
    foreach(glob($directory.'/*')?:[] as $file)if(is_file($file))unlink($file);
    if(is_dir($directory))rmdir($directory);
}
exit($exit);
