<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';require __DIR__.'/Support/HttpApp.php';
use Conquer\Db\Connection;
use ConquerTests\HttpApp;
function apiCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$fixture=new \ConquerTests\FeatureDatabase();
try {
    $db=Connection::getInstance();$db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(2,'Matrix World','security-matrix','running')");
    $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(991,'MatrixActor','actor@example.invalid','unused'),(992,'MatrixOther','other@example.invalid','unused'),(993,'MatrixThird','third@example.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(991,991,1,'Own city',50,50),(992,992,1,'Other city',60,60),(993,991,2,'Other world city',70,70),(994,993,1,'Third city',80,80)");
    $token=str_repeat('a',64);$csrf=str_repeat('b',64);
    $session=static function()use($db,$token,$csrf):void{$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(991,?,?,'127.0.0.1','matrix',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at)",[$token,$csrf]);};$session();
    $base=$fixture->serve(HttpApp::source(),['-d','disable_functions=mail','-d','display_errors=0']);
    $call=static function(string $path,string $method='GET',array $body=[],bool $auth=true,?string $csrfValue=null,?int $world=null)use($base,$token,$db):array{
        $db->execute('DELETE FROM security_rate_limits');$headers=['Content-Type: application/json'];if($auth)$headers[]='Cookie: conquer_session='.$token;if($csrfValue!==null)$headers[]='X-CSRF-Token: '.$csrfValue;if($world!==null)$headers[]='X-World-ID: '.$world;
        return HttpApp::request($base,$path,$method,$headers,$method==='GET'?null:json_encode($body,JSON_THROW_ON_ERROR));
    };
    preg_match_all('/\$router->(get|post|delete)\(\s*\x27([^\x27]+)\x27/',(string)file_get_contents(ROOT_DIR.'/index.php'),$matches,PREG_SET_ORDER);
    $counts=['anonymous'=>0,'csrf'=>0,'foreign_world'=>0,'banned'=>0];
    foreach($matches as$m){$method=strtoupper($m[1]);$path=preg_replace('/:([a-z_]+)/','987654',$m[2]);
        $r=$call($path,$method,[],false);apiCheck($r['status']===401,"anonymous $method $path must be 401, got ".$r['status']);$counts['anonymous']++;
        if($method!=='GET'){$r=$call($path,$method);apiCheck($r['status']===403,"missing CSRF $method $path must be 403, got ".$r['status']);$counts['csrf']++;}
        // World selection uses its own expected_world_id field and authorization below.
        if($path!=='/api/worlds/action'){$r=$call($path,$method,[],true,$csrf,999);apiCheck(in_array($r['status'],[409,410],true),"foreign world $method $path denied, got ".$r['status']);$counts['foreign_world']++;}
    }
    $db->execute('UPDATE players SET is_banned=1 WHERE id=991');
    foreach($matches as$m){$session();$method=strtoupper($m[1]);$path=preg_replace('/:([a-z_]+)/','987654',$m[2]);$r=$call($path,$method,[],true,$csrf);apiCheck($r['status']===401,"banned $method $path must be 401, got ".$r['status']);$counts['banned']++;}
    $db->execute('UPDATE players SET is_banned=0 WHERE id=991');$session();
    echo 'PASS complete registered-route guard matrix '.json_encode($counts)."\n";
    $otherWorld=$call('/api/worlds/action','POST',['action'=>'select','world_id'=>999,'expected_world_id'=>1,'request_id'=>'security_world_000001'],true,$csrf);apiCheck($otherWorld['status']>=400&&$otherWorld['status']<500,'unowned world selection denied');
    foreach([992,993]as$city){
        $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at,is_processed)VALUES(?,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),0)",[$city]);$queue=$db->lastInsertId();
        foreach(['cancel-build','speedup-build']as$op){$r=$call('/api/city/'.$op.'/'.$queue,'POST',['item_code'=>10103011],true,$csrf);apiCheck($r['status']>=400&&$r['status']<500,"$op cannot touch foreign city/world");}
        apiCheck((int)$db->query('SELECT COUNT(*) FROM building_queue WHERE id=? AND is_processed=0',[$queue])->fetchColumn()===1,'foreign building queue unchanged');
        $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at,is_processed)VALUES(?,50100101,100,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),0)",[$city]);$queue=$db->lastInsertId();
        foreach(['cancel-train','speedup-train']as$op){$r=$call('/api/troops/'.$op.'/'.$queue,'POST',['item_code'=>10103031],true,$csrf);apiCheck($r['status']>=400&&$r['status']<500,"$op cannot touch foreign city/world");}
        apiCheck((int)$db->query('SELECT COUNT(*) FROM troop_queue WHERE id=? AND is_processed=0',[$queue])->fetchColumn()===1,'foreign troop queue unchanged');
    }
    foreach([[992,1],[991,2]]as[$player,$world]){
        $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at,is_processed)VALUES(?,?,'food_production',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),0)",[$player,$world]);$queue=$db->lastInsertId();
        foreach(['cancel','speedup']as$op){$r=$call('/api/research/'.$op.'/'.$queue,'POST',['item_code'=>10103021],true,$csrf);apiCheck($r['status']>=400&&$r['status']<500,"research $op denies foreign player/world");}
        $db->execute("INSERT INTO mailbox_entries(player_id,world_id,source,source_id,category,subject,body)VALUES(?,?,'notification',?,'system','PRIVATE','PRIVATE')",[$player,$world,$world]);$id=$db->lastInsertId();
        $r=$call('/api/mailbox/message?id='.$id);apiCheck($r['status']>=400&&$r['status']<500,'mail detail denies foreign owner/world');
        $r=$call('/api/community/action','POST',['action'=>'mailbox.star','ids'=>[$id],'starred'=>true,'request_id'=>'security_mail_'.$id.'_0001'],true,$csrf);apiCheck($r['status']>=400&&$r['status']<500,'mail mutation denies foreign owner/world');
    }
    $db->execute("INSERT INTO notifications(player_id,type,data_json)VALUES(992,'build_complete','{}')");$notification=$db->lastInsertId();$call('/api/notifications/read','POST',['ids'=>[$notification]],true,$csrf);apiCheck(!$db->query('SELECT read_at FROM notifications WHERE id=?',[$notification])->fetchColumn(),'cannot mark another player notification read');
    $db->execute("INSERT INTO notifications(player_id,type,data_json)VALUES(991,'build_complete','{\"world_id\":2,\"title\":\"OTHER-WORLD-NOTIFICATION\"}')");$foreignNotification=$db->lastInsertId();
    $r=$call('/api/notifications/poll');apiCheck($r['status']===200&&!str_contains($r['body'],'OTHER-WORLD-NOTIFICATION')&&count($r['json']['data']['active_research'])===0,'legacy notification poll stays in active world: status='.$r['status'].' body='.substr($r['body'],0,800));
    $call('/api/notifications/read','POST',['ids'=>[$foreignNotification]],true,$csrf);apiCheck(!$db->query('SELECT read_at FROM notifications WHERE id=?',[$foreignNotification])->fetchColumn(),'cannot acknowledge notification from inactive world');
    $db->execute("INSERT INTO private_chat_messages(world_id,sender_id,recipient_id,message)VALUES(1,992,993,'SECRET-OTHER-PAIR')");
    $r=$call('/api/community/chat?player_id=992');apiCheck($r['status']===200&&!str_contains($r['body'],'SECRET-OTHER-PAIR'),'private chat lookup only returns own conversation');
    $r=$call('/api/auth/me');apiCheck($r['status']===200&&(int)$r['json']['data']['player_id']===991,'legitimate authenticated read still works');
    $r=$call('/auth/logout');apiCheck($r['status']===405,'legacy logout GET is non-mutating');
    $r=$call('/auth/logout','POST');apiCheck($r['status']===403,'legacy logout missing CSRF denied');
    $r=$call('/api/auth/me');apiCheck($r['status']===200,'denied logout retains legitimate session');
    echo "PASS targeted ownership, world, private chat, notification and logout matrix\nALL API SECURITY MATRIX CHECKS PASSED\n";
}finally{$fixture->close();}
