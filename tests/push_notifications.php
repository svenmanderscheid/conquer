<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
require __DIR__.'/Support/HttpApp.php';
date_default_timezone_set('UTC');

use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\Notification\{PushService as Push,PushWorker as Worker,PushConfig,PushTransport};
use Conquer\Game\City\CityState;
use ConquerTests\HttpApp;

function pushCheck(bool $ok,string $label):void { if(!$ok)throw new RuntimeException($label);echo "PASS $label\n"; }
function pushReject(callable $call,string $label):void { try{$call();}catch(InvalidArgumentException){pushCheck(true,$label);return;}throw new RuntimeException($label); }
function b64(string $bytes):string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }

$fixture=new \ConquerTests\FeatureDatabase();
$credentialFile=$fixture->sessionPath().'/push-fixture-service-account.json';
$oldEnv=[];
try {
    foreach(['ENABLED','NATIVE_ENABLED','SUBJECT','PUBLIC_KEY','PRIVATE_KEY'] as $key) $oldEnv[$key]=getenv('UOK_PUSH_'.$key);
    putenv('UOK_PUSH_ENABLED=0');putenv('UOK_PUSH_NATIVE_ENABLED=0');
    $db=Connection::getInstance();
    foreach([1,2] as $player){
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$player,'Push'.$player,'push'.$player.'@tests.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(?,?,1,'Push',?,60,5)",[$player,$player,60+8*$player]);
        foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,5)',[$player,$code]);
    }
    $sessions=[];$cookies=[];
    foreach([1,2]as$player){$token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,expires_at,active_world_id)VALUES(?,?,?,'127.0.0.1',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$player,$token,$csrf]);$sessions[$player]=['id'=>$db->lastInsertId(),'player_id'=>$player,'csrf_token'=>$csrf,'active_world_id'=>1];$cookies[$player]=$token;}
    $s1=$sessions[1];$s2=$sessions[2];
    pushCheck(!Push::status($s1)['available'],'missing migration/config is honestly unavailable');
    foreach([1,2]as$i)MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0145_web_push.sql'));
    pushCheck(Push::schemaReady(),'migration is repeatable on fixture schema');
    $subscription=['endpoint'=>'https://fcm.googleapis.com/fcm/send/test-device','keys'=>['p256dh'=>b64("\x04".str_repeat('a',64)),'auth'=>b64(str_repeat('b',16))]];
    foreach(['http://fcm.googleapis.com/send/x','https://127.0.0.1/x','https://fcm.googleapis.com.attacker.invalid/x','https://fcm.googleapis.com@127.0.0.1/x','https://fcm.googleapis.com:444/x','https://web.push.apple.com/x#frag','https://notify.windows.com.attacker.invalid/x']as$url)pushReject(fn()=>Push::validateEndpoint($url),'unsafe push endpoint rejected');
    foreach(['https://fcm.googleapis.com/fcm/send/x','https://updates.push.services.mozilla.com/wpush/v2/x','https://web.push.apple.com/Qx','https://wns2.notify.windows.com/w/?token=x']as$url)pushCheck(Push::validateEndpoint($url)===$url,'known HTTPS provider accepted');
    pushReject(fn()=>Push::decodeKey('invalid',65),'invalid encryption key rejected');
    pushReject(fn()=>Push::preferences(['completions'=>'yes']),'nonboolean preference rejected');
    pushReject(fn()=>Push::device(['platform'=>'android','token'=>str_repeat('a',2045)]),'native token including internal prefix is bounded');
    $notice=static function(string $type='build_complete',int $player=1,int $world=1)use($db):int{$db->execute('INSERT INTO notifications(player_id,type,data_json)VALUES(?,?,?)',[$player,$type,json_encode(['world_id'=>$world,'report_enabled'=>false,'message'=>'PRIVATE DETAILS'])]);return $db->lastInsertId();};
    $old=$notice();
    Push::subscribe($s1,['subscription'=>$subscription,'locale'=>'fr']);
    Push::subscribe($s1,['subscription'=>$subscription,'locale'=>'fr']);
    pushCheck((int)$db->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn()===1,'subscription retries are idempotent');
    pushCheck(Push::status($s1,$subscription['endpoint'])['enabled']&&!Push::status($s1)['enabled']&&!Push::status($s2,$subscription['endpoint'])['enabled'],'status is current device and account only');
    pushCheck(Worker::capture()===0,'historical backlog before opt-in is excluded');
    $notice();$notice('scout_incoming');$notice('welcome_back');$notice('build_complete',2);$notice('build_complete',1,999);
    pushCheck(Worker::capture()===2&&Worker::capture()===0,'capture filters category/owner/world and deduplicates independent of report mute');
    // A smaller allocated ID commits after a larger ID was already captured.
    $cfg=require $fixture->sessionPath().'/config/database.php';
    $other=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';dbname='.$cfg['database'].';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $other->exec("SET time_zone='+00:00'");$other->beginTransaction();
    $other->exec("INSERT INTO notifications(player_id,type,data_json)VALUES(1,'train_complete','{\"world_id\":1}')");
    $notice('heal_complete');
    pushCheck(Worker::capture()===1,'larger committed event can be captured while smaller ID remains pending');
    $other->commit();
    pushCheck(Worker::capture()===1,'out-of-order commit is captured on overlap scan');
    $payloads=[];$result=Worker::dispatch(static function(array $sub,array $payload)use(&$payloads):string{$payloads[]=$payload;return 'sent';});
    pushCheck($result['sent']===4&&Worker::dispatch(static fn()=>'sent')['sent']===0,'successful sends are never replayed');
    pushCheck(array_keys($payloads[0])===['title','body','tag','url']&&!str_contains(json_encode($payloads),'PRIVATE')&&preg_match('/^uok-[a-f0-9]{32}$/D',$payloads[0]['tag'])===1,'external payload contains only approved localized text and opaque tag');
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',6,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(1,50100101,7,1,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at)VALUES(1,1,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    $db->execute("INSERT INTO hospital_wounded(city_id,troop_code,count,healing_count,healing_started_at,healing_ends_at,healing_batch)VALUES(1,50200101,3,3,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'push-fixture')");
    \Conquer\Game\World\WorldContext::bind(9);
    pushCheck(Worker::settleDue()===1&&\Conquer\Game\World\WorldContext::id()===9,'offline completion settles canonical queues and restores world context');
    pushCheck(Worker::settleDue()===0&&Worker::capture()===4&&(int)$db->query('SELECT SUM(count)FROM city_troops WHERE city_id=1')->fetchColumn()===10,'build/training/research/healing settle exactly once');
    Worker::dispatch(static fn()=>'sent');
    Push::test($s1,$subscription['endpoint']);Push::test($s1,$subscription['endpoint']);
    pushCheck((int)$db->query("SELECT COUNT(*) FROM push_deliveries WHERE category='test'")->fetchColumn()===1,'replayed test request queues only once per minute');
    for($i=0;$i<4;$i++){$db->execute('UPDATE push_deliveries SET next_attempt_at=UTC_TIMESTAMP() WHERE completed_at IS NULL');Worker::dispatch(static fn()=>'retry');}
    pushCheck((int)$db->query("SELECT attempts FROM push_deliveries WHERE category='test'")->fetchColumn()===4&&$db->query("SELECT outcome FROM push_deliveries WHERE category='test'")->fetchColumn()==='failed','network retries stop after four attempts');
    Push::updatePreferences($s1,$subscription['endpoint'],['completions'=>false]);$notice();$notice('scout_incoming');
    pushCheck(Worker::capture()===1,'disabled category is not delivered');
    Push::unsubscribe($s2,$subscription['endpoint']);
    pushCheck(Push::status($s1,$subscription['endpoint'])['enabled'],'another account cannot unsubscribe device');
    Worker::dispatch(static fn()=>'expired');
    pushCheck(!Push::status($s1,$subscription['endpoint'])['enabled']&&(int)$db->query('SELECT COUNT(*)FROM push_deliveries')->fetchColumn()===0,'expired endpoint revokes subscription and outbox');
    Push::subscribe($s1,['subscription'=>$subscription]);$notice();Worker::capture();Push::subscribe($s2,['subscription'=>$subscription]);
    pushCheck(!Push::status($s1,$subscription['endpoint'])['enabled']&&Push::status($s2,$subscription['endpoint'])['enabled']&&(int)$db->query('SELECT COUNT(*)FROM push_deliveries')->fetchColumn()===0,'explicit account transfer drops previous account queue');
    Push::revokeCookie($cookies[2]);pushCheck(!Push::status($s2,$subscription['endpoint'])['enabled'],'login replacement/logout revokes only associated device');
    Push::subscribe($s1,['platform'=>'android','token'=>str_repeat('native-token-',6)]);
    $nativeEndpoint=Push::device(['platform'=>'android','token'=>str_repeat('native-token-',6)])['endpoint'];
    pushCheck(Push::status($s1,$nativeEndpoint,'android')['enabled']&&!Push::status($s1,$nativeEndpoint,'android')['available'],'native token persists independently but unavailable without real Firebase config');
    $original=Push::owned($s1,$nativeEndpoint);
    for($i=0;$i<9;$i++)Push::subscribe($s1,['platform'=>'android','token'=>str_repeat('extra-token-'.$i.'-',6)]);
    Push::subscribe($s2,['platform'=>'android','token'=>str_repeat('foreign-token-',6)]);
    $notice('scout_incoming');Worker::capture();
    $db->execute('UPDATE push_subscriptions SET completions=0 WHERE id=?',[$original['id']]);
    $rotation=['platform'=>'android','token'=>str_repeat('rotated-token-',6),'previous_token'=>str_repeat('native-token-',6)];
    Push::subscribe($s1,$rotation);Push::subscribe($s1,$rotation);
    $rotatedEndpoint=Push::device($rotation)['endpoint'];$rotated=Push::owned($s1,$rotatedEndpoint);
    pushCheck((int)$rotated['id']===(int)$original['id']&&(int)$rotated['last_notification_id']===(int)$original['last_notification_id']&&!(bool)$rotated['completions']&&(int)$db->query('SELECT COUNT(*)FROM push_subscriptions WHERE player_id=1')->fetchColumn()===10,'atomic native rotation at ten-device limit preserves ID, baseline, preferences and supports replay');
    pushCheck((int)$db->query('SELECT COUNT(*)FROM push_deliveries WHERE subscription_id=?',[$rotated['id']])->fetchColumn()===1,'native rotation preserves queued delivery');
    $db->execute('UPDATE sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$s1['id']]);
    $expiredCalls=0;Worker::dispatch(static function()use(&$expiredCalls):string{$expiredCalls++;return 'sent';});
    pushCheck($expiredCalls===0,'expired login session cannot receive queued notifications');
    $db->execute('UPDATE sessions SET expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?',[$s1['id']]);
    try{Push::subscribe($s1,['platform'=>'android','token'=>str_repeat('new-token-',6),'previous_token'=>str_repeat('foreign-token-',6)]);throw new RuntimeException('foreign previous token accepted');}catch(DomainException $error){pushCheck($error->getCode()===404,'foreign previous native token cannot authorize rotation');}
    $db->execute('DELETE FROM push_subscriptions WHERE player_id=1 AND id<>?',[$rotated['id']]);
    Push::revokeCookie($cookies[2]);
    $inFlightToken=str_repeat('inflight-token-',6);
    Worker::dispatch(static function()use($s1,$rotation,$inFlightToken):string{Push::subscribe($s1,['platform'=>'android','token'=>$inFlightToken,'previous_token'=>$rotation['token']]);return 'expired';});
    pushCheck(Push::owned($s1,'fcm:'.$inFlightToken)!==false&&(int)$db->query('SELECT COUNT(*)FROM push_deliveries WHERE completed_at IS NULL')->fetchColumn()===1,'old in-flight provider response cannot revoke newly rotated token or lose its delivery');
    $nextToken=str_repeat('next-inflight-token-',6);
    $inFlightResult=Worker::dispatch(static function()use($s1,$inFlightToken,$nextToken):string{Push::subscribe($s1,['platform'=>'android','token'=>$nextToken,'previous_token'=>$inFlightToken]);return 'sent';});
    $inFlightToken=$nextToken;
    pushCheck($inFlightResult['sent']===0&&$inFlightResult['retry']===1&&(int)$db->query('SELECT COUNT(*)FROM push_deliveries WHERE completed_at IS NULL AND attempts=0')->fetchColumn()===1,'old successful provider response remains pending without acknowledgement after atomic token rotation');

    $base=$fixture->serve(HttpApp::source());
    $call=static function(string $path,string $method='GET',?array $data=null,bool $auth=true,bool $csrf=true)use($base,$cookies,$s1):array{$headers=['Content-Type: application/json'];if($auth)$headers[]='Cookie: conquer_session='.$cookies[1];if($csrf)$headers[]='X-CSRF-Token: '.$s1['csrf_token'];return HttpApp::request($base,$path,$method,$headers,$data===null?null:json_encode($data));};
    pushCheck($call('/api/push/status','GET',null,false)['status']===401,'HTTP status requires authenticated session');
    pushCheck($call('/api/push/subscribe','POST',['subscription'=>$subscription],true,false)['status']===403,'HTTP writes enforce CSRF');
    pushCheck($call('/api/push/subscribe','POST',['subscription'=>$subscription,'expected_player_id'=>2])['status']===409,'cross-tab account change rejected before opt-in');
    pushCheck($call('/api/push/subscribe','POST',['subscription'=>$subscription,'expected_player_id'=>1])['status']===503,'HTTP subscribe never reports configured without provider keys');
    pushCheck($call('/api/push/status?endpoint='.urlencode($subscription['endpoint']))['status']===400,'GET forbids token-bearing device status');
    $response=$call('/api/push/status','POST',['platform'=>'android','token'=>$inFlightToken,'expected_player_id'=>1]);
    pushCheck($response['status']===200&&$response['json']['data']['enabled']&&!$response['json']['data']['available'],'POST device status is authenticated without query-token disclosure');
    $response=$call('/api/auth/logout','POST',[]);
    pushCheck($response['status']===200&&(int)$db->query('SELECT COUNT(*)FROM push_subscriptions')->fetchColumn()===0,'real HTTP logout cascades native subscription and deliveries');
    echo "ALL PUSH BACKEND CHECKS PASSED\n";
}finally{
    if(isset($other)&&$other->inTransaction())$other->rollBack();
    foreach($oldEnv as$key=>$value)putenv('UOK_PUSH_'.$key.($value===false?'':'='.$value));
    if(is_file($credentialFile))unlink($credentialFile);
    $fixture->close();
}
